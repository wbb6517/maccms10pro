<?php
/**
 * 会员管理控制器 (User Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 会员" 菜单对应的控制器
 * 负责管理前台注册会员的增删改查和推荐奖励查看
 *
 * 【菜单路径】
 * 后台 → 用户 → 会员
 *
 * 【核心功能】
 * 1. data   - 会员列表展示（支持搜索、分组筛选、分页）
 * 2. info   - 添加/编辑会员（设置账号、密码、会员组、有效期）
 * 3. del    - 删除会员
 * 4. field  - 批量更新字段（目前仅支持状态更新）
 * 5. reward - 推荐奖励查看（三级分销统计）
 *
 * 【业务规则】
 * - 会员组可多选，支持同时属于多个会员组
 * - 会员有效期到期后自动降级为默认会员组(ID=2)
 * - 支持三级分销推荐系统
 *
 * 【访问路径】
 * admin.php/user/data   → 会员列表
 * admin.php/user/info   → 添加/编辑会员
 * admin.php/user/del    → 删除会员
 * admin.php/user/field  → 批量更新字段
 * admin.php/user/reward → 推荐奖励
 *
 * 【相关文件】
 * - application/common/model/User.php : 会员模型
 * - application/admin/validate/User.php : 验证器
 * - application/admin/view/user/index.html : 列表页视图
 * - application/admin/view/user/info.html : 编辑页视图
 * - application/admin/view/user/reward.html : 推荐奖励视图
 *
 * 【数据表】
 * - mac_user: 会员数据表
 * - mac_group: 会员组数据表
 * - mac_plog: 积分日志表
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class User extends Base
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
     * 会员列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有会员列表，支持状态筛选、会员组筛选、名称搜索和分页
     * 首次加载时会自动处理过期会员的会员组降级
     *
     * 【请求参数】
     * - status : 状态筛选（0=禁用, 1=启用）
     * - group  : 会员组ID筛选
     * - wd     : 用户名搜索关键词
     * - page   : 当前页码
     * - limit  : 每页条数
     *
     * 【模板变量】
     * - $list       : 会员列表数组
     * - $total      : 总记录数
     * - $page       : 当前页码
     * - $limit      : 每页条数
     * - $param      : 请求参数
     * - $group_list : 会员组列表（用于筛选下拉）
     *
     * @return mixed 渲染模板
     */
    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        if($param['page'] ==1){
            model('User')->expire();
        }

        $where=[];
        if(in_array($param['status'],['0','1'],true)){
            $where['user_status'] = $param['status'];
        }
        if(!empty($param['group'])){
            $where['group_id'] =  $param['group'];
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['user_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='user_id desc';
        $res = model('User')->listData($where,$order,$param['page'],$param['limit']);

        $group_list = model('Group')->getCache('group_list');
        foreach($res['list'] as $k=>$v){
            $group_ids = explode(',', $v['group_id']);
            $names = [];
            foreach($group_ids as $gid){
                if(isset($group_list[$gid])){
                    $names[] = $group_list[$gid]['group_name'];
                }
            }
            $res['list'][$k]['group_name'] = implode(',', $names);
        }

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('group_list',$group_list);

        $this->assign('title',lang('admin/user/title'));
        return $this->fetch('admin@user/index');
    }

    /**
     * ============================================================
     * 推荐奖励查看
     * ============================================================
     *
     * 【功能说明】
     * 查看指定会员的三级分销推荐数据
     * 包括一级/二级/三级推荐人数和获得的积分统计
     *
     * 【请求参数】
     * - uid   : 会员ID（必填）
     * - level : 筛选级别（1=一级, 2=二级, 3=三级）
     * - wd    : 用户名搜索关键词
     * - page  : 当前页码
     * - limit : 每页条数
     *
     * 【模板变量】
     * - $list  : 下级会员列表
     * - $data  : 统计数据
     *   - level_cc_1/2/3  : 一/二/三级推荐人数
     *   - points_cc_1/2/3 : 一/二/三级获得积分
     * - $total : 总记录数
     * - $page  : 当前页码
     * - $limit : 每页条数
     * - $param : 请求参数
     *
     * 【三级分销说明】
     * - user_pid   : 一级推荐人（直接邀请人）
     * - user_pid_2 : 二级推荐人（邀请人的邀请人）
     * - user_pid_3 : 三级推荐人（更上一级）
     * - plog_type=4/5/6 : 对应一/二/三级分销积分日志
     *
     * @return mixed 渲染模板
     */
    public function reward()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $param['uid'] = intval($param['uid']);
        $where=[];
        if(!empty($param['level'])){
            if($param['level']=='1'){
                $where['user_pid'] = ['eq', $param['uid']];
            }
            elseif($param['level']=='2'){
                $where['user_pid_2'] = ['eq', $param['uid']];
            }
            elseif($param['level']=='3'){
                $where['user_pid_3'] = ['eq', $param['uid']];
            }
        }
        else{
            $where['user_pid|user_pid_2|user_pid_3'] = ['eq', intval($param['uid']) ];
        }

        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['user_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='user_id desc';
        $res = model('User')->listData($where,$order,$param['page'],$param['limit']);
        $group_list = model('Group')->getCache('group_list');
        foreach($res['list'] as $k=>$v){
            $res['list'][$k]['group_name'] = $group_list[$v['group_id']]['group_name'];
        }

        $where2=[];
        $where2['user_pid'] = ['eq', $param['uid']];
        $level_cc_1 = Db::name('User')->where($where2)->count();
        $where3 = [];
        $where3['user_id'] = $param['uid'];
        $where3['plog_type'] = 4;
        $points_cc_1 = Db::name('Plog')->where($where3)->sum('plog_points');

        $where2=[];
        $where2['user_pid_2'] = ['eq', $param['uid']];
        $level_cc_2 = Db::name('User')->where($where2)->count();
        $where3 = [];
        $where3['user_id'] = $param['uid'];
        $where3['plog_type'] = 5;
        $points_cc_2 = Db::name('Plog')->where($where3)->sum('plog_points');

        $where2=[];
        $where2['user_pid_3'] = ['eq', $param['uid']];
        $level_cc_3 = Db::name('User')->where($where2)->count();
        $where3 = [];
        $where3['user_id'] = $param['uid'];
        $where3['plog_type'] = 6;
        $points_cc_3 = Db::name('Plog')->where($where3)->sum('plog_points');

        $data=[];
        $data['level_cc_1'] = intval($level_cc_1);
        $data['level_cc_2'] = intval($level_cc_2);
        $data['level_cc_3'] = intval($level_cc_3);
        $data['points_cc_1'] = intval($points_cc_1);
        $data['points_cc_2'] = intval($points_cc_2);
        $data['points_cc_3'] = intval($points_cc_3);

        $this->assign('data',$data);
        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/user/title'));
        return $this->fetch('admin@user/reward');
    }


    /**
     * ============================================================
     * 添加/编辑会员
     * ============================================================
     *
     * 【功能说明】
     * 添加新会员或编辑现有会员信息
     * 包括账号、密码、会员组、积分、有效期等设置
     *
     * 【请求方式】
     * GET  - 显示表单页面
     * POST - 保存数据
     *
     * 【GET参数】
     * - id : 会员ID（编辑时传入）
     *
     * 【POST参数】
     * - user_name      : 用户名
     * - user_pwd       : 密码（编辑时为空则不修改）
     * - group_id[]     : 会员组ID数组（支持多选）
     * - user_status    : 状态（0=禁用, 1=启用）
     * - user_points    : 积分
     * - user_start_time: VIP开始时间
     * - user_end_time  : VIP结束时间
     *
     * 【模板变量】
     * - $info         : 会员信息（编辑时有数据）
     * - $group_list   : 会员组列表
     * - $has_vip_group: 是否有VIP组（用于显示有效期字段）
     *
     * @return mixed 渲染模板或JSON响应
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            if(isset($param['group_id']) && is_array($param['group_id'])) {
                $param['group_id'] = implode(',', $param['group_id']);
            }
            $res = model('User')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id/d');
        $where=[];
        $where['user_id'] = ['eq',$id];
        $res = model('User')->infoData($where);
        $info = $res['info'];

        $group_list = model('Group')->getCache('group_list');
        $group_ids = isset($info['group_id']) ? explode(',', $info['group_id']) : [];
        $has_vip_group = false;
        foreach($group_ids as $gid){
            if(intval($gid) > 2){
                $has_vip_group = true;
                break;
            }
        }
        $this->assign('info', $info);
        $this->assign('group_list', $group_list);
        $this->assign('has_vip_group', $has_vip_group);
        return $this->fetch('admin@user/info');
    }

    /**
     * ============================================================
     * 删除会员
     * ============================================================
     *
     * 【功能说明】
     * 删除指定的会员账号
     *
     * 【请求参数】
     * - ids : 要删除的会员ID（支持单个或逗号分隔的多个）
     *
     * 【注意事项】
     * - 删除会员不会删除关联的积分日志、评论等数据
     * - 支持批量删除
     *
     * @return mixed JSON响应
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['user_id'] = ['in',$ids];
            $res = model('User')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 批量更新字段
     * ============================================================
     *
     * 【功能说明】
     * 批量更新会员的指定字段值
     * 目前仅支持状态字段（user_status）的更新
     *
     * 【请求参数】
     * - ids : 会员ID（支持单个或逗号分隔的多个）
     * - col : 字段名（仅允许 user_status）
     * - val : 字段值（仅允许 0 或 1）
     *
     * 【使用场景】
     * - 列表页的状态开关
     * - 批量启用/禁用会员
     *
     * @return mixed JSON响应
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['user_status']) && in_array($val,['0','1'])){
            $where=[];
            $where['user_id'] = ['in',$ids];

            $res = model('User')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }




}
