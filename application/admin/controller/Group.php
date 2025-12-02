<?php
/**
 * 会员组管理控制器 (Group Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 会员组" 菜单对应的控制器
 * 负责管理网站的会员等级分组（如：游客、普通会员、VIP会员等）
 *
 * 【菜单路径】
 * 后台 → 用户 → 会员组
 *
 * 【核心功能】
 * 1. index  - 会员组列表展示（支持状态筛选、名称搜索）
 * 2. info   - 添加/编辑会员组（设置权限、价格等）
 * 3. del    - 删除会员组（保护注册默认组不可删除）
 * 4. field  - 批量更新字段（目前仅支持状态更新）
 *
 * 【业务规则】
 * - 系统内置的"游客"和"普通会员"组无法删除
 * - 注册默认分组不可删除，且状态强制为启用
 * - 删除会员组前需检查是否有用户属于该组
 * - 每个会员组可设置不同的观看权限和价格
 *
 * 【访问路径】
 * admin.php/group/index  → 会员组列表
 * admin.php/group/info   → 添加/编辑会员组
 * admin.php/group/del    → 删除会员组
 * admin.php/group/field  → 批量更新字段
 *
 * 【相关文件】
 * - application/common/model/Group.php : 会员组模型
 * - application/admin/validate/Group.php : 验证器
 * - application/admin/view/group/index.html : 列表页视图
 * - application/admin/view/group/info.html : 编辑页视图
 *
 * 【数据表】
 * - mac_group: 会员组数据表
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Group extends Base
{
    /**
     * 构造函数
     * 调用父类Base的构造函数，完成权限验证等初始化
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员组列表
     * --------------------------------------------------------
     * 【访问路径】admin.php/group/index
     *
     * 【功能说明】
     * 显示所有会员组列表，支持按状态筛选和名称搜索
     *
     * 【请求参数】
     * - status : 状态筛选 (0=禁用, 1=启用)
     * - wd     : 名称搜索关键词
     *
     * 【模板变量】
     * - $list  : 会员组列表数组
     * - $total : 总记录数
     * - $param : 请求参数（用于分页和筛选保持）
     *
     * @return mixed 渲染模板
     */
    public function index()
    {
        $param = input();
        $where=[];

        if(in_array($param['status'],['0','1'],true)){
            $where['group_status'] = ['eq',$param['status']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['group_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='group_id asc';
        $res = model('Group')->listData($where,$order);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);

        $this->assign('param',$param);
        $this->assign('title',lang('admin/group/title'));
        return $this->fetch('admin@group/index');
    }

    /**
     * 添加/编辑会员组
     * --------------------------------------------------------
     * 【访问路径】admin.php/group/info
     *
     * 【功能说明】
     * GET:  显示会员组编辑表单
     * POST: 保存会员组数据
     *
     * 【请求参数】
     * GET:  id - 会员组ID（编辑时传入，新增时不传）
     * POST: 表单数据（group_name, group_status, group_type, group_popedom等）
     *
     * 【业务逻辑】
     * 1. 如果是注册默认组，强制设置状态为启用
     * 2. 调用模型saveData进行数据验证和保存
     * 3. GET请求时获取分类树用于权限设置
     *
     * 【模板变量】
     * - $info      : 会员组信息（编辑时有数据）
     * - $type_tree : 分类树（用于设置观看权限）
     *
     * @return mixed JSON响应或渲染模板
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');

            if($GLOBALS['config']['user']['reg_group'] == $param['group_id']){
                $param['group_status'] = 1;
            }
            $res = model('Group')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['group_id'] = ['eq',$id];
        $res = model('Group')->infoData($where);

        $this->assign('info',$res['info']);


        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/group/title'));
        return $this->fetch('admin@group/info');
    }

    /**
     * 删除会员组
     * --------------------------------------------------------
     * 【访问路径】admin.php/group/del
     *
     * 【功能说明】
     * 批量删除指定的会员组
     *
     * 【请求参数】
     * - ids : 要删除的会员组ID，多个用逗号分隔
     *
     * 【业务逻辑】
     * 1. 检查是否包含注册默认分组，禁止删除
     * 2. 检查是否有用户属于该分组（在模型中检查）
     * 3. 执行删除并刷新缓存
     *
     * 【保护机制】
     * - 注册默认分组不可删除（系统配置中指定）
     * - 有用户的分组不可删除
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){

            if(strpos(','.$ids.',', ','.$GLOBALS['config']['user']['reg_group'].',')!==false){
                return $this->error(lang('admin/group/reg_group_del_err'));
            }

            $where=[];
            $where['group_id'] = ['in',$ids];
            $res = model('Group')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 批量更新字段
     * --------------------------------------------------------
     * 【访问路径】admin.php/group/field
     *
     * 【功能说明】
     * 批量更新会员组的指定字段值
     * 目前仅支持 group_status 字段的更新
     *
     * 【请求参数】
     * - ids : 要更新的会员组ID，多个用逗号分隔
     * - col : 要更新的字段名（仅支持 group_status）
     * - val : 新的字段值（0 或 1）
     *
     * 【安全限制】
     * - 白名单验证：仅允许更新 group_status 字段
     * - 值验证：仅允许设置为 0 或 1
     *
     * @return \think\response\Json JSON响应
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['group_status']) && in_array($val,['0','1'])){
            $where=[];
            $where['group_id'] = ['in',$ids];

            $res = model('Group')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }


}
