<?php
/**
 * 充值卡模型 (Card Model)
 * ============================================================
 *
 * 【功能说明】
 * 充值卡数据模型，负责充值卡的生成、查询、使用和管理
 * 用户通过输入卡号和密码，兑换积分到账户
 *
 * 【数据表】
 * mac_card - 充值卡数据表
 *
 * 【方法列表】
 * ┌─────────────────────────────────┬──────────────────────────────┐
 * │ 方法名                           │ 说明                          │
 * ├─────────────────────────────────┼──────────────────────────────┤
 * │ getCardUseStatusTextAttr()      │ 获取使用状态文本              │
 * │ getCardSaleStatusTextAttr()     │ 获取销售状态文本              │
 * │ listData()                      │ 获取充值卡列表                │
 * │ infoData()                      │ 获取单条充值卡信息            │
 * │ saveData()                      │ 保存单条充值卡                │
 * │ saveAllData()                   │ 批量生成充值卡                │
 * │ delData()                       │ 删除充值卡                    │
 * │ fieldData()                     │ 更新指定字段                  │
 * │ useData()                       │ 前台用户使用充值卡            │
 * └─────────────────────────────────┴──────────────────────────────┘
 *
 * 【充值卡状态】
 * - 销售状态(card_sale_status): 0=未销售, 1=已销售
 * - 使用状态(card_use_status):  0=未使用, 1=已使用
 *
 * 【相关文件】
 * - application/admin/controller/Card.php : 后台控制器
 * - application/admin/validate/Card.php : 验证器
 * - application/common.php : mac_get_rndstr() 随机字符串函数
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;

class Card extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'card';

    // 定义时间戳字段名（不自动处理）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成字段
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 获取使用状态文本（获取器）
     *
     * 【功能说明】
     * ThinkPHP获取器，自动转换 card_use_status 数字为文本
     * 用于列表显示时自动调用
     *
     * @param mixed $val 原始值（未使用）
     * @param array $data 当前行数据
     * @return string 使用状态文本（未使用/已使用）
     */
    public function getCardUseStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('not_used'),1=>lang('used')];
        return $arr[$data['card_use_status']];
    }

    /**
     * 获取销售状态文本（获取器）
     *
     * 【功能说明】
     * ThinkPHP获取器，自动转换 card_sale_status 数字为文本
     * 用于列表显示时自动调用
     *
     * @param mixed $val 原始值（未使用）
     * @param array $data 当前行数据
     * @return string 销售状态文本（未销售/已销售）
     */
    public function getCardSaleStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('not_sale'),1=>lang('sold')];
        return $arr[$data['card_sale_status']];
    }

    /**
     * 获取充值卡列表
     *
     * 【功能说明】
     * 分页获取充值卡列表，支持多条件筛选
     * 如果充值卡已被使用，会关联查询使用者信息
     *
     * 【参数说明】
     * @param array  $where 查询条件（card_sale_status, card_use_status, card_no等）
     * @param string $order 排序规则（默认 card_id desc）
     * @param int    $page  当前页码
     * @param int    $limit 每页条数（默认20）
     *
     * 【返回数据】
     * @return array [
     *     'code'      => 1,
     *     'msg'       => '数据列表',
     *     'page'      => 当前页码,
     *     'pagecount' => 总页数,
     *     'limit'     => 每页条数,
     *     'total'     => 总记录数,
     *     'list'      => 充值卡数组（含使用者user信息）
     * ]
     */
    public function listData($where,$order,$page,$limit=20)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $total = $this->where($where)->count();
        $list = Db::name('Card')->where($where)->order($order)->page($page)->limit($limit)->select();
        // 如果充值卡已被使用，关联查询使用者信息
        foreach($list as $k=>$v){
            if($v['user_id'] >0){
                $user = model('User')->infoData(['user_id'=>$v['user_id']]);
                $list[$k]['user'] = $user['info'];
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取单条充值卡信息
     *
     * 【功能说明】
     * 根据条件获取单条充值卡详情
     *
     * @param array  $where 查询条件（通常是 card_id）
     * @param string $field 要查询的字段（默认 '*'）
     *
     * @return array [
     *     'code' => 1/1001/1002,
     *     'msg'  => 提示信息,
     *     'info' => 充值卡信息数组
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
     * 保存单条充值卡
     *
     * 【功能说明】
     * 新增或更新单条充值卡数据
     * 如果 card_id 存在则更新，否则新增
     *
     * @param array $data 充值卡数据 [
     *     'card_id'     => 充值卡ID（更新时必填）,
     *     'card_no'     => 卡号,
     *     'card_pwd'    => 密码,
     *     'card_money'  => 面值,
     *     'card_points' => 积分值
     * ]
     *
     * @return array ['code' => 1/1001/1002, 'msg' => 提示信息]
     */
    public function saveData($data)
    {
        // 使用验证器验证数据
        $validate = \think\Loader::validate('Card');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if(!empty($data['card_id'])){
            // 更新现有充值卡
            $where=[];
            $where['card_id'] = ['eq',$data['card_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增充值卡，设置添加时间
            $data['card_add_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 批量生成充值卡
     *
     * 【功能说明】
     * 批量生成指定数量的充值卡
     * 卡号和密码根据指定规则随机生成
     *
     * 【参数说明】
     * @param int    $num      生成数量
     * @param int    $money    卡面值（金额）
     * @param int    $point    积分值（用户使用后获得的积分）
     * @param string $role_no  卡号生成规则（1=纯数字, 2=纯字母, 3=混合）
     * @param string $role_pwd 密码生成规则（1=纯数字, 2=纯字母, 3=混合）
     *
     * 【生成规则】
     * - 卡号长度：16位，使用 mac_get_rndstr() 生成
     * - 密码长度：8位，使用 mac_get_rndstr() 生成
     * - 同一批次的 card_add_time 相同，便于按批次筛选
     * - 使用卡号作为数组键名，自动去除重复卡号
     *
     * @return array ['code' => 1/1002, 'msg' => 提示信息]
     */
    public function saveAllData($num,$money,$point,$role_no,$role_pwd)
    {
        $data=[];
        $t = time();
        // 循环生成指定数量的充值卡
        for($i=1;$i<=$num;$i++){
            // 生成16位卡号
            $card_no = mac_get_rndstr(16,$role_no);
            // 生成8位密码
            $card_pwd = mac_get_rndstr(8,$role_pwd);

            // 以卡号为键，自动去重
            $data[$card_no] = ['card_no'=>$card_no,'card_pwd'=>$card_pwd,'card_money'=>$money,'card_points'=>$point,'card_add_time'=>$t];
        }
        // 转换为索引数组
        $data = array_values($data);
        // 批量插入
        $res = $this->allowField(true)->insertAll($data);
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }


    /**
     * 删除充值卡
     *
     * 【功能说明】
     * 根据条件删除充值卡记录
     * 支持单条删除、批量删除和清空全部
     *
     * @param array $where 删除条件（如 card_id in [1,2,3] 或 card_id > 0）
     *
     * @return array ['code' => 1/1001, 'msg' => 提示信息]
     */
    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 更新指定字段
     *
     * 【功能说明】
     * 批量更新充值卡的指定字段
     * 常用于批量设置销售状态等
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
     * 前台用户使用充值卡
     *
     * 【功能说明】
     * 用户在前台输入卡号和密码，兑换积分
     * 这是充值卡的核心使用流程
     *
     * 【业务流程】
     * 1. 验证卡号和密码是否匹配
     * 2. 检查充值卡是否已被使用
     * 3. 增加用户积分
     * 4. 记录积分日志（Plog）
     * 5. 更新充值卡状态为已使用
     *
     * 【参数说明】
     * @param string $card_no   充值卡卡号（16位）
     * @param string $card_pwd  充值卡密码（8位）
     * @param array  $user_info 当前登录用户信息（需包含 user_id）
     *
     * 【返回码说明】
     * - 1001: 参数错误
     * - 1002: 卡号密码不匹配或已使用
     * - 1003: 更新用户积分失败
     * - 1004: 更新充值卡状态失败
     * - 1   : 成功，返回获得的积分数
     *
     * @return array ['code' => 错误码, 'msg' => 提示信息]
     */
    public function useData($card_no,$card_pwd,$user_info)
    {
        // 参数验证
        if (empty($card_no) || empty($card_pwd) || empty($user_info)) {
            return ['code' => 1001, 'msg'=>lang('param_err')];
        }

        // 查询条件：卡号、密码匹配，且未使用
        $where=[];
        $where['card_no'] = ['eq',$card_no];
        $where['card_pwd'] = ['eq',$card_pwd];
        //$where['card_sale_status'] = ['eq',1]; // 不要求已销售状态
        $where['card_use_status'] = ['eq',0];    // 必须是未使用状态

        $info = $this->where($where)->find();
        if(empty($info)){
            return ['code' => 1002, 'msg' =>lang('model/card/not_found')];
        }

        // 1. 增加用户积分
        $where2=[];
        $where2['user_id'] = $user_info['user_id'];
        $res = model('User')->where($where2)->setInc('user_points',$info['card_points']);
        if($res===false){
            return ['code' => 1003, 'msg' =>lang('model/card/update_user_points_err')];
        }

        // 2. 记录积分日志
        $data = [];
        $data['user_id'] = $user_info['user_id'];
        $data['plog_type'] = 1;  // 类型1表示充值卡充值
        $data['plog_points'] = $info['card_points'];
        $result = model('Plog')->saveData($data);

        // 3. 更新充值卡状态
        $update=[];
        $update['card_sale_status'] = 1;      // 标记为已销售
        $update['card_use_status'] = 1;       // 标记为已使用
        $update['card_use_time'] = time();    // 记录使用时间
        $update['user_id'] = $user_info['user_id'];  // 记录使用者
        $res = $this->where($where)->update($update);
        if($res === false){
            return ['code' => 1004, 'msg' =>lang('model/card/update_card_status_err')];
        }

        // 返回成功信息，包含获得的积分数
        return ['code' => 1, 'msg' => lang('model/card/used_card_ok',[$info['card_points']])];
    }
}