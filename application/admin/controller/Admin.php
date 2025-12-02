<?php
/**
 * 管理员管理控制器 (Admin Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 管理员" 菜单对应的控制器
 * 负责管理后台管理员账号的增删改查
 *
 * 【菜单路径】
 * 后台 → 用户 → 管理员
 *
 * 【核心功能】
 * 1. index - 管理员列表展示（支持名称搜索、分页）
 * 2. info  - 添加/编辑管理员（设置账号、密码、权限）
 * 3. del   - 删除管理员（禁止删除当前登录账号）
 * 4. field - 批量更新字段（目前仅支持状态更新）
 *
 * 【业务规则】
 * - 管理员ID=1 为超级管理员，不可删除
 * - 不能删除当前登录的管理员账号
 * - 新增管理员时必须设置密码
 * - 编辑时密码为空则不修改原密码
 * - 权限默认包含 index/welcome（首页）
 *
 * 【访问路径】
 * admin.php/admin/index → 管理员列表
 * admin.php/admin/info  → 添加/编辑管理员
 * admin.php/admin/del   → 删除管理员
 * admin.php/admin/field → 批量更新字段
 *
 * 【相关文件】
 * - application/common/model/Admin.php : 管理员模型
 * - application/admin/validate/Admin.php : 验证器
 * - application/admin/view/admin/index.html : 列表页视图
 * - application/admin/view/admin/info.html : 编辑页视图
 * - application/admin/common/auth.php : 权限菜单配置
 *
 * 【数据表】
 * - mac_admin: 管理员数据表
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Admin extends Base
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
     * 管理员列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有管理员列表，支持名称搜索和分页
     *
     * 【请求参数】
     * - wd    : 名称搜索关键词
     * - page  : 当前页码
     * - limit : 每页条数
     *
     * 【模板变量】
     * - $list  : 管理员列表数组
     * - $total : 总记录数
     * - $page  : 当前页码
     * - $limit : 每页条数
     * - $admin : 当前登录管理员信息
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
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['admin_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='admin_id desc';
        $res = model('Admin')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';

        $this->assign('admin',$this->_admin);

        $this->assign('param',$param);
        $this->assign('title',lang('admin/admin/title'));
        return $this->fetch('admin@admin/index');
    }

    /**
     * ============================================================
     * 添加/编辑管理员
     * ============================================================
     *
     * 【功能说明】
     * 添加新管理员或编辑现有管理员信息
     * 包括账号、密码、权限等设置
     *
     * 【请求方式】
     * GET  - 显示表单页面
     * POST - 保存数据
     *
     * 【GET参数】
     * - id : 管理员ID（编辑时传入）
     *
     * 【POST参数】
     * - admin_name   : 管理员账号
     * - admin_pwd    : 密码（编辑时为空则不修改）
     * - admin_auth[] : 权限数组（多选）
     * - admin_status : 状态（0=禁用, 1=启用）
     *
     * 【业务规则】
     * - 新增时密码必填
     * - 编辑时密码为空则保持原密码
     * - 权限默认包含 index/welcome（首页）
     *
     * 【模板变量】
     * - $info  : 管理员信息（编辑时有数据）
     * - $menus : 权限菜单列表（带选中状态）
     *
     * @return mixed 渲染模板或JSON响应
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            if(!in_array('index/welcome',$param['admin_auth'])){
                $param['admin_auth'][] = 'index/welcome';
            }
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            $res = model('Admin')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');

        $where=[];
        $where['admin_id'] = ['eq',$id];

        $res = model('Admin')->infoData($where);
        $this->assign('info',$res['info']);

        //权限列表
        $menus = @include MAC_ADMIN_COMM . 'auth.php';

        foreach($menus as $k1=>$v1){
            $all = [];
            $cs = [];
            $menus[$k1]['ck'] = '';
            foreach($v1['sub'] as $k2=>$v2){
                $one = $v2['controller'] . '/' . $v2['action'];
                $menus[$k1]['sub'][$k2]['url'] = url($one);
                $menus[$k1]['sub'][$k2]['ck']= '';
                $all[] = $one;

                if(strpos(','.$res['info']['admin_auth'],$one)>0){
                    $cs[] = $one;
                    $menus[$k1]['sub'][$k2]['ck'] = 'checked';
                }
                if($k2==11){
                    $menus[$k1]['sub'][$k2]['ck'] = ' checked  readonly="readonly" ';
                }
            }
            if($all == $cs){
                $menus[$k1]['ck'] = 'checked';
            }
        }
        $this->assign('menus',$menus);


        $this->assign('title',lang('admin/admin/title'));
        return $this->fetch('admin@admin/info');
    }

    /**
     * ============================================================
     * 删除管理员
     * ============================================================
     *
     * 【功能说明】
     * 删除指定的管理员账号
     *
     * 【请求参数】
     * - ids : 要删除的管理员ID（支持单个或逗号分隔的多个）
     *
     * 【业务规则】
     * - 不能删除当前登录的管理员账号
     * - ID=1 的超级管理员不可删除（在模型层控制）
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
            $where['admin_id'] = ['in',$ids];
            if(!is_array($ids)) {
                $ids = explode(',', $ids);
            }
            if(in_array($this->_admin['admin_id'],$ids)){
                return $this->error(lang('admin/admin/del_cur_err'));
            }
            $res = model('Admin')->delData($where);
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
     * 批量更新管理员的指定字段值
     * 目前仅支持状态字段（admin_status）的更新
     *
     * 【请求参数】
     * - ids : 管理员ID（支持单个或逗号分隔的多个）
     * - col : 字段名（仅允许 admin_status）
     * - val : 字段值（仅允许 0 或 1）
     *
     * 【使用场景】
     * - 列表页的状态开关
     * - 批量启用/禁用管理员
     *
     * @return mixed JSON响应
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['admin_status']) && in_array($val,['0','1'])){
            $where=[];
            $where['admin_id'] = ['in',$ids];

            $res = model('Admin')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
