<?php
/**
 * 积分日志管理控制器 (Plog Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 积分日志" 菜单对应的控制器
 * 负责查看和管理用户积分变动记录
 *
 * 【菜单路径】
 * 后台 → 用户 → 积分日志
 *
 * 【核心功能】
 * 1. index - 积分日志列表（支持按类型、用户筛选）
 * 2. del   - 删除日志（支持批量删除、清空全部）
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
 * 【业务说明】
 * 此日志记录用户积分的所有变动情况，包括：
 * - 增加类型(1-6)：充值、推广奖励、分销返佣
 * - 减少类型(7-9)：升级VIP、消费内容、提现
 * 注意：删除日志不会影响用户实际积分
 *
 * 【访问路径】
 * admin.php/plog/index       → 积分日志列表
 * admin.php/plog/index?uid=1 → 查看指定用户的积分日志
 * admin.php/plog/del         → 删除日志
 *
 * 【相关文件】
 * - application/common/model/Plog.php : 积分日志模型
 * - application/admin/view_new/plog/index.html : 列表页视图
 *
 * 【数据表】
 * - mac_plog: 积分日志表
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Plog extends Base
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
     * 积分日志列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有用户积分变动记录，支持按类型筛选和分页
     *
     * 【请求参数】
     * - type  : 类型筛选（1-9，见上方类型表）
     * - uid   : 用户ID筛选（查看指定用户的积分日志）
     * - page  : 当前页码
     * - limit : 每页条数
     *
     * 【模板变量】
     * - $list  : 日志列表数组（含 user_name 用户名）
     * - $total : 总记录数
     * - $page  : 当前页码
     * - $limit : 每页条数
     * - $param : 请求参数
     *
     * 【列表字段说明】
     * - plog_id     : 日志ID
     * - user_id     : 用户ID
     * - user_name   : 用户名（关联查询）
     * - plog_type   : 积分类型（1-9）
     * - plog_points : 变动积分数
     * - plog_remarks: 备注信息
     * - plog_time   : 记录时间
     *
     * @return mixed 渲染模板
     */
    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=[];
        if(!empty($param['type'])){
            $where['plog_type'] = ['eq',$param['type']];
        }
        if(!empty($param['uid'])){
            $where['user_id'] = ['eq',$param['uid'] ];
        }

        $order='plog_id desc';
        $res = model('Plog')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/plog/title'));
        return $this->fetch('admin@plog/index');
    }

    /**
     * ============================================================
     * 删除积分日志
     * ============================================================
     *
     * 【功能说明】
     * 删除选中的积分日志，支持批量删除和清空全部
     *
     * 【请求参数】
     * - ids : 要删除的日志ID数组（必填）
     * - all : 清空全部标记（1=清空所有日志）
     *
     * 【删除逻辑】
     * - 普通删除：根据 ids 参数删除选中的日志
     * - 清空全部：当 all=1 时，删除所有日志（plog_id > 0）
     *
     * 【注意事项】
     * - 删除日志仅删除记录，不影响用户实际积分
     * - 删除后无法恢复，请谨慎操作
     * - 日志主要用于追溯积分变动历史
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
            $where['plog_id'] = ['in',$ids];
            if($all==1){
                $where['plog_id'] = ['gt',0];
            }
            $res = model('Plog')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
