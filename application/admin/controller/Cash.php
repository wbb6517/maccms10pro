<?php
/**
 * 提现记录管理控制器 (Cash Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 提现记录" 菜单对应的控制器
 * 负责管理用户的积分提现申请
 *
 * 【菜单路径】
 * 后台 → 用户 → 提现记录
 *
 * 【核心功能】
 * 1. index - 提现记录列表（支持按状态、账号筛选）
 * 2. del   - 删除记录（支持批量删除、清空全部）
 * 3. audit - 审核通过（批量审核提现申请）
 *
 * 【提现状态 cash_status】
 * - 0 = 待审核（用户已提交，等待管理员审核）
 * - 1 = 已审核（管理员已审核通过）
 *
 * 【业务流程】
 * 1. 用户申请提现 → 扣除可用积分，增加冻结积分
 * 2. 管理员审核通过 → 扣除冻结积分，记录积分日志
 * 3. 删除待审核记录 → 恢复冻结积分到可用积分
 *
 * 【积分处理逻辑】
 * - 申请时：user_points -= cash_points, user_points_froze += cash_points
 * - 审核时：user_points_froze -= cash_points, 记录plog(type=9)
 * - 删除待审核：user_points += cash_points, user_points_froze -= cash_points
 *
 * 【访问路径】
 * admin.php/cash/index       → 提现记录列表
 * admin.php/cash/index?uid=1 → 查看指定用户的提现记录
 * admin.php/cash/del         → 删除记录
 * admin.php/cash/audit       → 审核通过
 *
 * 【相关文件】
 * - application/common/model/Cash.php : 提现模型
 * - application/admin/view_new/cash/index.html : 列表页视图
 *
 * 【数据表】
 * - mac_cash: 提现记录表
 * - mac_user: 用户表（user_points, user_points_froze）
 * - mac_plog: 积分日志表（审核时记录）
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Cash extends Base
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
     * 提现记录列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有用户提现申请记录，支持按状态筛选和分页
     *
     * 【请求参数】
     * - status : 状态筛选（0=待审核, 1=已审核）
     * - uid    : 用户ID筛选（查看指定用户的提现记录）
     * - wd     : 搜索银行账号
     * - page   : 当前页码
     * - limit  : 每页条数
     *
     * 【模板变量】
     * - $list  : 记录列表数组（含 user_name 用户名）
     * - $total : 总记录数
     * - $page  : 当前页码
     * - $limit : 每页条数
     * - $param : 请求参数
     *
     * 【列表字段说明】
     * - cash_id         : 记录ID
     * - user_id         : 用户ID
     * - user_name       : 用户名（关联查询）
     * - cash_status     : 状态（0=待审核, 1=已审核）
     * - cash_points     : 提现积分数
     * - cash_money      : 提现金额
     * - cash_bank_name  : 银行名称
     * - cash_bank_no    : 银行账号
     * - cash_payee_name : 收款人姓名
     * - cash_time       : 申请时间
     * - cash_time_audit : 审核时间
     *
     * @return mixed 渲染模板
     */
    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=[];
        if($param['status']!=''){
            $where['cash_status'] = ['eq',$param['status']];
        }
        if(!empty($param['uid'])){
            $where['user_id'] = ['eq',$param['uid'] ];
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['cash_bank_no'] = ['like','%'.$param['wd'].'%' ];
        }

        $order='cash_id desc';
        $res = model('Cash')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/cash/title'));
        return $this->fetch('admin@cash/index');
    }

    /**
     * ============================================================
     * 删除提现记录
     * ============================================================
     *
     * 【功能说明】
     * 删除选中的提现记录，支持批量删除和清空全部
     *
     * 【请求参数】
     * - ids : 要删除的记录ID数组（必填）
     * - all : 清空全部标记（1=清空所有记录）
     *
     * 【删除逻辑】
     * - 普通删除：根据 ids 参数删除选中的记录
     * - 清空全部：当 all=1 时，删除所有记录（cash_id > 0）
     *
     * 【积分处理】
     * - 删除待审核记录(status=0)：恢复冻结积分到可用积分
     *   user_points += cash_points
     *   user_points_froze -= cash_points
     * - 删除已审核记录(status=1)：不处理积分（已完成提现）
     *
     * 【注意事项】
     * - 删除待审核记录会自动恢复用户积分
     * - 删除已审核记录不影响用户积分（已经扣除）
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
            $where['cash_id'] = ['in',$ids];
            if($all==1){
                $where['cash_id'] = ['gt',0];
            }
            $res = model('Cash')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 审核提现申请
     * ============================================================
     *
     * 【功能说明】
     * 批量审核通过用户的提现申请
     *
     * 【请求参数】
     * - ids : 要审核的记录ID数组（必填）
     *
     * 【审核流程】
     * 1. 更新记录状态为已审核(cash_status=1)
     * 2. 记录审核时间(cash_time_audit)
     * 3. 扣除用户冻结积分(user_points_froze)
     * 4. 写入积分日志(plog_type=9 积分提现)
     *
     * 【积分处理】
     * 审核通过后：user_points_froze -= cash_points
     * 注意：可用积分(user_points)在申请时已扣除
     *
     * 【注意事项】
     * - 审核后需要线下完成实际打款
     * - 审核操作不可撤销
     * - 会自动记录积分日志用于追溯
     *
     * @return \think\response\Json 审核结果
     */
    public function audit()
    {
        $param = input();
        $ids = $param['ids'];
        if(!empty($ids)){
            $where=[];
            $where['cash_id'] = ['in',$ids];
            $res = model('Cash')->auditData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
