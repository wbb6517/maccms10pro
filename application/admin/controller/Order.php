<?php
/**
 * 会员订单管理控制器 (Order Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 会员订单" 菜单对应的控制器
 * 负责管理用户充值积分的订单记录
 *
 * 【菜单路径】
 * 后台 → 用户 → 会员订单
 *
 * 【核心功能】
 * 1. index - 订单列表（支持按状态、用户、订单号筛选）
 * 2. del   - 删除订单（支持批量删除、清空全部）
 *
 * 【业务规则】
 * - 订单由用户在前台发起充值时创建
 * - 支付成功后通过回调更新订单状态并增加用户积分
 * - 支持多种支付方式：支付宝、微信、银行卡等
 * - 订单状态：0=未支付, 1=已支付
 *
 * 【访问路径】
 * admin.php/order/index → 订单列表
 * admin.php/order/del   → 删除订单
 *
 * 【相关文件】
 * - application/common/model/Order.php : 订单模型
 * - application/admin/validate/Order.php : 验证器
 * - application/admin/view_new/order/index.html : 列表页视图
 *
 * 【数据表】
 * - mac_order: 订单数据表
 * - mac_user: 用户表（关联查询用户名）
 * - mac_plog: 积分日志表（支付成功时记录）
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Order extends Base
{
    /**
     * 构造函数
     * 调用父类Base的构造函数，完成登录验证和权限检测
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 订单列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有会员充值订单列表，支持多条件筛选和分页
     *
     * 【请求参数】
     * - status : 订单状态筛选（0=未支付, 1=已支付）
     * - uid    : 用户ID筛选（查看指定用户的订单）
     * - wd     : 订单号搜索关键词
     * - page   : 当前页码
     * - limit  : 每页条数
     *
     * 【模板变量】
     * - $list  : 订单列表数组（含用户名 user_name）
     * - $total : 总记录数
     * - $page  : 当前页码
     * - $limit : 每页条数
     * - $param : 请求参数
     *
     * @return mixed 渲染模板
     */
    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        // 按订单状态筛选
        if($param['status']!=''){
            $where['order_status'] = ['eq',$param['status']];
        }
        // 按用户ID筛选（从用户列表点击进入时使用）
        if(!empty($param['uid'])){
            $where['o.user_id'] = ['eq',$param['uid'] ];
        }
        // 按订单号模糊搜索
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['order_code'] = ['like','%'.$param['wd'].'%'];
        }

        $order='order_id desc';
        $res = model('Order')->listData($where,$order,$param['page'],$param['limit']);


        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);


        $this->assign('title',lang('admin/order/title'));
        return $this->fetch('admin@order/index');
    }


    /**
     * ============================================================
     * 删除订单
     * ============================================================
     *
     * 【功能说明】
     * 删除选中的订单记录，支持批量删除和清空全部
     *
     * 【请求参数】
     * - ids : 要删除的订单ID数组（必填）
     * - all : 清空全部标记（1=清空所有订单）
     *
     * 【删除逻辑】
     * - 普通删除：根据 ids 参数删除选中的订单
     * - 清空全部：当 all=1 时，删除所有订单（order_id > 0）
     *
     * 【注意事项】
     * - 删除订单不会影响用户已获得的积分
     * - 已支付订单删除后无法恢复，建议谨慎操作
     * - 清空操作不可恢复，建议操作前备份数据
     *
     * @return \think\response\Json 删除结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];

        if(!empty($ids)){
            $where=[];
            $where['order_id'] = ['in',$ids];
            // 如果 all=1，则清空所有订单
            if($all==1){
                $where['order_id'] = ['gt',0];
            }
            $res = model('Order')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }



}
