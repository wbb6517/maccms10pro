<?php
/**
 * 积分日志模型 (Plog Model)
 * ============================================================
 *
 * 【功能说明】
 * 记录用户积分的所有变动日志，用于追溯积分历史
 * 包括充值、推广奖励、分销返佣、消费、提现等场景
 *
 * 【数据表】
 * mac_plog - 积分日志表
 *
 * 【方法列表】
 * ┌─────────────────────────────────┬──────────────────────────────┐
 * │ 方法名                           │ 说明                          │
 * ├─────────────────────────────────┼──────────────────────────────┤
 * │ listData()                      │ 获取日志列表（含用户名）      │
 * │ infoData()                      │ 获取单条日志信息              │
 * │ saveData()                      │ 保存积分日志                  │
 * │ delData()                       │ 删除日志                      │
 * │ fieldData()                     │ 更新指定字段                  │
 * └─────────────────────────────────┴──────────────────────────────┘
 *
 * 【积分类型 plog_type】
 * ┌──────┬──────────────┬──────────────────────────────┐
 * │ 类型 │ 说明          │ 积分变化                      │
 * ├──────┼──────────────┼──────────────────────────────┤
 * │  1   │ 积分充值      │ +增加（用户购买积分）          │
 * │  2   │ 注册推广      │ +增加（推广用户注册奖励）      │
 * │  3   │ 访问推广      │ +增加（推广链接访问奖励）      │
 * │  4   │ 一级分销      │ +增加（下级消费返佣）          │
 * │  5   │ 二级分销      │ +增加（下下级消费返佣）        │
 * │  6   │ 三级分销      │ +增加（三级下线消费返佣）      │
 * │  7   │ 积分升级      │ -减少（升级VIP消耗积分）      │
 * │  8   │ 积分消费      │ -减少（观看/下载付费内容）    │
 * │  9   │ 积分提现      │ -减少（提现扣除积分）          │
 * └──────┴──────────────┴──────────────────────────────┘
 *
 * 【user_id_1 关联用户说明】
 * - 分销场景：记录触发分销的下级用户ID
 * - 推广场景：记录被推广的新用户ID
 * - 其他场景：默认为0
 *
 * 【相关文件】
 * - application/admin/controller/Plog.php : 后台控制器
 * - application/index/controller/User.php : 前台用户中心
 * - application/common/validate/Plog.php  : 验证器
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;

class Plog extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'plog';

    // 定义时间戳字段名（不自动处理）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成字段
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    /**
     * 获取积分日志列表
     *
     * 【功能说明】
     * 分页获取日志列表，支持多条件筛选
     * 自动关联查询用户名信息
     *
     * 【参数说明】
     * @param array  $where 查询条件（plog_type, user_id等）
     * @param string $order 排序规则（默认 plog_id desc）
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
     *     'list'      => 日志数组（含 user_name 用户名）
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
        $list = Db::name('Plog')->where($where)->order($order)->limit($limit_str)->select();

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
     * 获取单条日志信息
     *
     * 【功能说明】
     * 根据条件获取单条积分日志详情
     *
     * @param array  $where 查询条件（通常是 plog_id）
     * @param string $field 要查询的字段（默认 '*'）
     *
     * @return array [
     *     'code' => 1/1001/1002,
     *     'msg'  => 提示信息,
     *     'info' => 日志信息数组
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
     * 保存积分日志
     *
     * 【功能说明】
     * 记录用户积分变动，在各业务场景中调用
     *
     * 【调用场景】
     * - 积分充值：Order模型 notify() 方法
     * - 推广奖励：User模型注册/访问时
     * - 分销返佣：消费时触发上级返佣
     * - 积分消费：播放/下载付费内容时
     * - 积分提现：Cash模型提现审核通过时
     * - 积分升级：升级VIP时消耗积分
     *
     * 【参数说明】
     * - user_id     : 用户ID（必填）
     * - user_id_1   : 关联用户ID（分销/推广场景用）
     * - plog_type   : 积分类型 1-9（必填）
     * - plog_points : 变动积分数
     * - plog_remarks: 备注说明
     *
     * @param array $data 日志数据
     * @return array 返回结构: code/msg
     */
    public function saveData($data)
    {
        $data['plog_time'] = time();

        $validate = \think\Loader::validate('Plog');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if($data['user_id']==0 || !in_array($data['plog_type'],['1','2','3','4','5','6','7','8','9']) ) {
            return ['code'=>1002,'msg'=>lang('param_err')];
        }

        if(!empty($data['plog_id'])){
            $where=[];
            $where['plog_id'] = ['eq',$data['plog_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1004,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除积分日志
     *
     * 【功能说明】
     * 根据条件删除日志记录
     * 支持单条删除、批量删除和清空全部
     *
     * 【注意事项】
     * 删除日志不影响用户实际积分余额
     * 日志主要用于审计和追溯，删除后无法恢复
     *
     * @param array $where 删除条件（如 plog_id in [1,2,3] 或 plog_id > 0）
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
     * 批量更新日志的指定字段
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

}