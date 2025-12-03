<?php
/**
 * 提现记录模型 (Cash Model)
 * ============================================================
 *
 * 【功能说明】
 * 管理用户的积分提现申请
 * 处理提现申请、审核、积分冻结与扣除等业务
 *
 * 【数据表】
 * mac_cash - 提现记录表
 *
 * 【方法列表】
 * ┌─────────────────────────────────┬──────────────────────────────┐
 * │ 方法名                           │ 说明                          │
 * ├─────────────────────────────────┼──────────────────────────────┤
 * │ listData()                      │ 获取提现记录列表（含用户名）  │
 * │ infoData()                      │ 获取单条记录信息              │
 * │ saveData()                      │ 申请提现（前台用户调用）      │
 * │ delData()                       │ 删除记录（处理积分恢复）      │
 * │ fieldData()                     │ 更新指定字段                  │
 * │ auditData()                     │ 审核通过（扣除冻结积分）      │
 * └─────────────────────────────────┴──────────────────────────────┘
 *
 * 【提现状态 cash_status】
 * - 0 = 待审核（用户已提交，等待管理员审核）
 * - 1 = 已审核（管理员已审核通过）
 *
 * 【积分流转说明】
 * 用户有两种积分：user_points(可用) 和 user_points_froze(冻结)
 *
 * 【提现业务流程】
 * ┌─────────────┬────────────────────────────────────────────────┐
 * │ 操作         │ 积分变化                                        │
 * ├─────────────┼────────────────────────────────────────────────┤
 * │ 申请提现     │ user_points -= 积分, user_points_froze += 积分 │
 * │ 审核通过     │ user_points_froze -= 积分, 记录plog(type=9)    │
 * │ 删除待审核   │ user_points += 积分, user_points_froze -= 积分 │
 * │ 删除已审核   │ 不处理（积分已扣除）                            │
 * └─────────────┴────────────────────────────────────────────────┘
 *
 * 【相关配置】
 * config('maccms.user.cash_status') : 提现功能开关
 * config('maccms.user.cash_min')    : 最低提现金额
 * config('maccms.user.cash_ratio')  : 积分兑换比例（积分/元）
 *
 * 【相关文件】
 * - application/admin/controller/Cash.php : 后台控制器
 * - application/index/controller/User.php : 前台用户中心
 * - application/common/validate/Cash.php  : 验证器
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;

class Cash extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'cash';

    // 定义时间戳字段名（不自动处理）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成字段
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    /**
     * 获取提现记录列表
     *
     * 【功能说明】
     * 分页获取提现记录列表，支持多条件筛选
     * 自动关联查询用户名信息
     *
     * 【参数说明】
     * @param array  $where 查询条件（cash_status, user_id等）
     * @param string $order 排序规则（默认 cash_id desc）
     * @param int    $page  当前页码（默认1）
     * @param int    $limit 每页条数（默认20）
     * @param int    $start 起始偏移量（默认0）
     *
     * 【返回数据】
     * @return array [
     *     'code'      => 1,
     *     'msg'       => '数据列表',
     *     'page'      => 当前页码,
     *     'pagecount' => 总页数,
     *     'limit'     => 每页条数,
     *     'total'     => 总记录数,
     *     'list'      => 记录数组（含 user_name 用户名）
     * ]
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        $total = $this->where($where)->count();
        $list = Db::name('Cash')->where($where)->order($order)->limit($limit_str)->select();

        $user_ids=[];
        foreach($list as $k=>&$v){
            if($v['user_id'] >0){
                $user_ids[$v['user_id']] = $v['user_id'];
            }
        }

        if(!empty($user_ids)){
            $where2=[];
            $where['user_id'] = ['in', $user_ids];
            $order='user_id desc';
            $user_list = model('User')->listData($where2,$order,1,999);
            $user_list = mac_array_rekey($user_list['list'],'user_id');

            foreach($list as $k=>&$v){
                $list[$k]['user_name'] = $user_list[$v['user_id']]['user_name'];
            }
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取单条记录信息
     *
     * 【功能说明】
     * 根据条件获取单条提现记录详情
     *
     * @param array  $where 查询条件（通常是 cash_id）
     * @param string $field 要查询的字段（默认 '*'）
     *
     * @return array [
     *     'code' => 1/1001/1002,
     *     'msg'  => 提示信息,
     *     'info' => 记录信息数组
     * ]
     */
    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 申请提现（前台用户调用）
     *
     * 【功能说明】
     * 用户提交积分提现申请
     * 扣除可用积分，增加冻结积分
     *
     * 【调用场景】
     * 前台用户中心 application/index/controller/User.php
     *
     * 【业务流程】
     * 1. 检查提现功能是否开启
     * 2. 验证最低提现金额
     * 3. 计算所需积分（金额 × 兑换比例）
     * 4. 检查用户积分是否足够
     * 5. 创建提现记录
     * 6. 更新用户积分（扣除可用，增加冻结）
     *
     * 【参数说明】
     * - cash_money     : 提现金额（必填）
     * - cash_bank_name : 银行名称（必填）
     * - cash_bank_no   : 银行账号（必填）
     * - cash_payee_name: 收款人姓名（必填）
     *
     * 【积分计算】
     * cash_points = cash_money × cash_ratio
     * 例：提现100元，比例10，则需要1000积分
     *
     * @param array $param 提现申请数据
     * @return array 返回结构: code/msg
     */
    public function saveData($param)
    {
        $data=[];
        $data['cash_money']  = floatval($param['cash_money']);

        if($GLOBALS['config']['user']['cash_status'] !='1'){
            return ['code'=>1005,'msg'=>lang('model/cash/not_open')];
        }

        if($data['cash_money'] < $GLOBALS['config']['user']['cash_min']){
            return ['code'=>1006,'msg'=>lang('model/cash/min_money_err').'：'.$GLOBALS['config']['user']['cash_min'] ];
        }

        $tx_points = intval($data['cash_money'] * $GLOBALS['config']['user']['cash_ratio']);
        if($tx_points > $GLOBALS['user']['user_points']){
            return ['code'=>1007,'msg'=>lang('model/cash/mush_money_err')];
        }
        $data['user_id'] = $GLOBALS['user']['user_id'];
        $data['cash_bank_name'] = htmlspecialchars(urldecode(trim($param['cash_bank_name'])));
        $data['cash_bank_no'] = htmlspecialchars(urldecode(trim($param['cash_bank_no'])));
        $data['cash_payee_name'] = htmlspecialchars(urldecode(trim($param['cash_payee_name'])));
        $data['cash_points'] = $tx_points;
        $data['cash_time'] = time();

        $validate = \think\Loader::validate('Cash');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if($data['user_id']==0 ) {
            return ['code'=>1002,'msg'=>lang('param_err')];
        }
        $res = $this->allowField(true)->insert($data);
        if(false === $res){
            return ['code'=>1004,'msg'=>lang('save_err').'：'.$this->getError() ];
        }

        //更新用户表
        $update=[];
        $update['user_points'] = $GLOBALS['user']['user_points'] - $tx_points;
        $update['user_points_froze'] = $GLOBALS['user']['user_points_froze'] + $tx_points;

        $where=[];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $res = model('user')->where($where)->update($update);
        if(false === $res){
            return ['code'=>1005,'msg'=>'更新用户积分失败：'.$this->getError() ];
        }

        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除提现记录
     *
     * 【功能说明】
     * 删除提现记录，并根据状态处理积分
     * 删除待审核记录时恢复用户积分
     *
     * 【删除逻辑】
     * 1. 遍历要删除的记录
     * 2. 删除记录
     * 3. 如果是待审核记录(status=0)，恢复积分：
     *    user_points += cash_points
     *    user_points_froze -= cash_points
     *
     * 【注意事项】
     * - 待审核记录删除后积分自动恢复
     * - 已审核记录删除不影响积分（已扣除）
     *
     * @param array $where 删除条件
     * @return array ['code' => 1/1001, 'msg' => 提示信息]
     */
    public function delData($where)
    {
        $list = $this->where($where)->select();

        foreach($list as $k=>$v){
            $where=[];
            $where['cash_id'] = $v['cash_id'];

            $res = $this->where($where)->delete();
            if($res===false){
                return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
            }

            //如果未审核则恢复冻结积分
            if($v['cash_status'] ==0){
                $where=[];
                $where['user_id'] = $v['user_id'];

                $user = model('User')->where($where)->find();
                $update=[];
                $update['user_points'] = $user['user_points'] + $v['cash_points'];
                $update['user_points_froze'] = $user['user_points_froze'] - $v['cash_points'];

                $res = model('user')->where($where)->update($update);
                if(false === $res){
                    return ['code'=>1005,'msg'=>'更新用户积分失败：'.$this->getError() ];
                }
            }
        }

        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 更新指定字段
     *
     * 【功能说明】
     * 批量更新记录的指定字段
     *
     * @param array  $where 更新条件
     * @param string $col   字段名
     * @param mixed  $val   字段值
     *
     * @return array ['code' => 1/1001, 'msg' => 提示信息]
     */
    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    /**
     * 审核通过提现申请
     *
     * 【功能说明】
     * 管理员审核通过用户的提现申请
     * 扣除冻结积分，记录积分日志
     *
     * 【审核流程】
     * 1. 遍历待审核记录
     * 2. 更新记录状态为已审核(cash_status=1)
     * 3. 记录审核时间(cash_time_audit)
     * 4. 扣除用户冻结积分(user_points_froze)
     * 5. 写入积分日志(plog_type=9 积分提现)
     *
     * 【积分处理】
     * user_points_froze -= cash_points
     * 注意：可用积分(user_points)在申请时已扣除
     *
     * 【注意事项】
     * - 审核后需要线下完成实际打款
     * - 审核操作不可撤销
     * - 自动记录积分日志(plog_type=9)
     *
     * @param array $where 审核条件（通常是 cash_id in [...]）
     * @return array ['code' => 1/1001, 'msg' => 提示信息]
     */
    public function auditData($where)
    {
        $list = $this->where($where)->select();
        foreach($list as $k=>$v){
            $where2=[];
            $where2['user_id'] = $v['user_id'];

            $update=[];
            $update['cash_status'] = 1;
            $update['cash_time_audit'] = time();
            $res = model('Cash')->where($where)->update($update);
            if($res===false){
                return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
            }

            $res = model('User')->where($where2)->setDec('user_points_froze', $v['cash_points']);
            if(false === $res){
                return ['code'=>1005,'msg'=>'更新用户积分失败：'.$this->getError() ];
            }
            //积分日志
            $data = [];
            $data['user_id'] = $v['user_id'];
            $data['plog_type'] = 9;
            $data['plog_points'] = $v['cash_points'];
            $result = model('Plog')->saveData($data);

        }
        return ['code'=>1,'msg'=>'审核成功'];
    }

}