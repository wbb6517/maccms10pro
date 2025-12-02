<?php
/**
 * 会员订单模型 (Order Model)
 * ============================================================
 *
 * 【功能说明】
 * 会员订单数据模型，负责充值订单的创建、查询、支付回调处理
 * 用户在前台发起充值，支付成功后获得积分
 *
 * 【数据表】
 * mac_order - 订单数据表
 *
 * 【方法列表】
 * ┌─────────────────────────────────┬──────────────────────────────┐
 * │ 方法名                           │ 说明                          │
 * ├─────────────────────────────────┼──────────────────────────────┤
 * │ listData()                      │ 获取订单列表（关联用户名）    │
 * │ infoData()                      │ 获取单条订单信息              │
 * │ saveData()                      │ 保存订单数据                  │
 * │ delData()                       │ 删除订单                      │
 * │ fieldData()                     │ 更新指定字段                  │
 * │ notify()                        │ 支付回调处理（核心方法）      │
 * └─────────────────────────────────┴──────────────────────────────┘
 *
 * 【订单状态】
 * - order_status: 0=未支付, 1=已支付
 *
 * 【支付方式】
 * - alipay : 支付宝
 * - weixin : 微信支付
 * - bank   : 银行卡
 * - 可自定义扩展（最长10个字符）
 *
 * 【相关文件】
 * - application/admin/controller/Order.php : 后台控制器
 * - application/admin/validate/Order.php : 验证器
 * - application/common/model/User.php : 用户模型（增加积分）
 * - application/common/model/Plog.php : 积分日志模型
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;

class Order extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'order';

    // 定义时间戳字段名（不自动处理）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成字段
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    /**
     * 获取订单列表
     *
     * 【功能说明】
     * 分页获取订单列表，支持多条件筛选
     * 自动关联查询用户表获取用户名
     *
     * 【参数说明】
     * @param array  $where 查询条件（order_status, user_id, order_code等）
     * @param string $order 排序规则（默认 order_id desc）
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
     *     'list'      => 订单数组（含 user_name）
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
        $total = $this->alias('o')->where($where)->count();
        // 关联用户表查询用户名
        $list = Db::name('Order')->alias('o')
            ->field('o.*,u.user_name')
            ->join('__USER__ u','o.user_id = u.user_id','left')
            ->where($where)
            ->order($order)
            ->limit($limit_str)
            ->select();


        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取单条订单信息
     *
     * 【功能说明】
     * 根据条件获取单条订单详情
     *
     * @param array  $where 查询条件（通常是 order_id 或 order_code）
     * @param string $field 要查询的字段（默认 '*'）
     *
     * @return array [
     *     'code' => 1/1001/1002,
     *     'msg'  => 提示信息,
     *     'info' => 订单信息数组
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
     * 保存订单数据
     *
     * 【功能说明】
     * 新增或更新订单数据
     * 如果 order_id 存在则更新，否则新增
     *
     * @param array $data 订单数据 [
     *     'order_id'      => 订单ID（更新时必填）,
     *     'order_code'    => 订单号,
     *     'user_id'       => 用户ID,
     *     'order_price'   => 订单金额,
     *     'order_points'  => 订单积分,
     *     'order_remarks' => 订单备注
     * ]
     *
     * @return array ['code' => 1/1001/1002, 'msg' => 提示信息]
     */
    public function saveData($data)
    {
        // 使用验证器验证数据
        $validate = \think\Loader::validate('Order');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // 设置订单时间
        $data['order_time'] = time();
        if(!empty($data['order_id'])){
            // 更新现有订单
            $where=[];
            $where['order_id'] = ['eq',$data['order_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增订单
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除订单
     *
     * 【功能说明】
     * 根据条件删除订单记录
     * 支持单条删除、批量删除和清空全部
     *
     * @param array $where 删除条件（如 order_id in [1,2,3] 或 order_id > 0）
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
     * 批量更新订单的指定字段
     * 常用于批量更新订单状态等
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
     * ============================================================
     * 支付回调处理（核心方法）
     * ============================================================
     *
     * 【功能说明】
     * 充值回调函数接口，支付平台回调时调用此方法
     * 更新订单状态、增加用户积分、记录积分日志
     *
     * 【使用场景】
     * 任何支付接口（支付宝、微信等）的回调方法中
     * 直接调用此接口完成订单处理
     *
     * 【参数说明】
     * @param string $order_code 订单号
     * @param string $pay_type   支付方式（alipay/weixin/bank，可自定义，最长10字符）
     *
     * 【业务流程】
     * 1. 验证参数
     * 2. 查询订单信息，检查是否存在
     * 3. 检查订单是否已支付（防重复处理）
     * 4. 查询用户信息
     * 5. 更新订单状态为已支付
     * 6. 增加用户积分
     * 7. 记录积分日志
     *
     * 【返回码说明】
     * - 1    : 成功（或订单已支付）
     * - 1001 : 参数错误
     * - 2002 : 更新订单状态失败
     * - 2003 : 更新用户积分失败
     *
     * @return array ['code' => 错误码, 'msg' => 提示信息]
     */
    public function notify($order_code,$pay_type)
    {
        // 1. 参数验证
        if(empty($order_code) || empty($pay_type)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // 2. 查询订单信息
        $where = [];
        $where['order_code'] = $order_code;
        $order = model('Order')->infoData($where);
        if($order['code']>1){
            return $order;
        }

        // 3. 检查订单是否已支付（防止重复处理）
        if($order['info']['order_status'] == 1){
            return ['code'=>1,'msg'=>lang('model/order/pay_over')];
        }

        // 4. 查询用户信息
        $where2=[];
        $where2['user_id'] = $order['info']['user_id'];
        $user = model('User')->infoData($where2);
        if($user['code']>1){
            return $user;
        }

        // 5. 更新订单状态为已支付
        $update = [];
        $update['order_status'] = 1;           // 标记为已支付
        $update['order_pay_time'] = time();    // 记录支付时间
        $update['order_pay_type'] = $pay_type; // 记录支付方式
        $res = $this->where($where)->update($update);
        if($res===false){
            return ['code'=>2002,'msg'=>lang('model/order/update_status_err')];
        }

        // 6. 增加用户积分
        $where2 = [];
        $where2['user_id'] = $user['info']['user_id'];
        $res = model('User')->where($where2)->setInc('user_points',$order['info']['order_points']);
        if($res===false){
            return ['code'=>2003,'msg'=>lang('model/order/update_user_points_err')];
        }

        // 7. 记录积分日志
        $data = [];
        $data['user_id'] = $user['info']['user_id'];
        $data['plog_type'] = 1;  // 类型1表示充值
        $data['plog_points'] = $order['info']['order_points'];
        model('Plog')->saveData($data);




        return ['code'=>1,'msg'=>lang('model/order/pay_ok')];

    }

}