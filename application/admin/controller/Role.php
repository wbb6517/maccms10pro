<?php
/**
 * 角色数据管理控制器 (Role Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台 "视频 → 角色库" 功能的控制器
 * 用于管理视频中的角色信息，角色与视频是多对一关系
 * 一个视频可以有多个角色，一个角色只属于一个视频
 *
 * 【访问路径】
 * admin.php/role/data  → 角色列表页面
 * admin.php/role/info  → 添加/编辑角色
 * admin.php/role/del   → 删除角色
 * admin.php/role/field → 批量修改字段
 *
 * 【方法列表】
 * ┌────────────────┬──────────────────────────────────────┐
 * │ 方法名          │ 功能说明                              │
 * ├────────────────┼──────────────────────────────────────┤
 * │ data()         │ 角色列表页面 (支持多条件筛选)          │
 * │ info()         │ 角色添加/编辑页面                      │
 * │ del()          │ 删除角色 (支持批量)                    │
 * │ field()        │ 批量修改字段 (状态/等级/点击量)         │
 * └────────────────┴──────────────────────────────────────┘
 *
 * 【数据表说明】
 * 数据表: mac_role (通过 Role 模型操作)
 *
 * 【核心字段说明】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 说明                                     │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ role_id          │ 角色ID (主键)                            │
 * │ role_rid         │ 关联视频ID (外键 → mac_vod.vod_id)       │
 * │ role_name        │ 角色名称                                 │
 * │ role_en          │ 英文名/拼音 (用于URL)                    │
 * │ role_status      │ 状态: 0=未审核, 1=已审核                  │
 * │ role_level       │ 推荐等级: 0-9                            │
 * │ role_lock        │ 锁定: 0=否, 1=是                         │
 * │ role_actor       │ 扮演者/演员名                            │
 * │ role_remarks     │ 角色备注                                 │
 * │ role_pic         │ 角色图片URL                              │
 * │ role_sort        │ 排序值 (越大越靠前)                      │
 * │ role_time        │ 更新时间戳                               │
 * │ role_time_add    │ 添加时间戳                               │
 * │ role_hits        │ 总点击量                                 │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * 【与视频的关系】
 * role_rid 字段存储关联的视频ID
 * 可通过视频详情页进入角色管理
 * 也可在角色列表通过 rid 参数筛选某视频的角色
 *
 * @package     app\admin\controller
 * @author      MacCMS
 * @version     1.0
 */
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Role extends Base
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 角色列表页面
     *
     * 【功能说明】
     * 显示角色数据列表，支持多条件筛选
     *
     * 【筛选条件】
     * - level  : 推荐等级筛选
     * - status : 审核状态 (0=未审核, 1=已审核)
     * - rid    : 关联视频ID (筛选某视频的所有角色)
     * - pic    : 图片状态 (1=无图, 2=外链图, 3=错误图)
     * - wd     : 关键词搜索 (角色名称)
     *
     * @return mixed 渲染后的列表页面
     */
    public function data()
    {
        $param = input();
        // 分页参数初始化
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        $where = [];

        // ========== 推荐等级筛选 ==========
        if (!empty($param['level'])) {
            $where['role_level'] = ['eq', $param['level']];
        }

        // ========== 审核状态筛选 ==========
        // status=0: 未审核, status=1: 已审核
        if (in_array($param['status'], ['0', '1'])) {
            $where['role_status'] = ['eq', $param['status']];
        }

        // ========== 关联视频筛选 ==========
        // 通过 rid 参数筛选某个视频的所有角色
        // 例: role/data?rid=123 显示视频ID=123的所有角色
        if (!empty($param['rid'])) {
            $where['role_rid'] = ['eq', $param['rid']];
        }

        // ========== 图片状态筛选 ==========
        // pic=1: 无图片 (角色图为空)
        // pic=2: 外链图片 (以http开头)
        // pic=3: 错误图片 (包含#err标记)
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['role_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['role_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['role_pic'] = ['like','%#err%'];
            }
        }

        // ========== 关键词搜索 ==========
        // 搜索角色名称
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['role_name'] = ['like','%'.$param['wd'].'%'];
        }

        // 默认按更新时间倒序
        $order='role_time desc';
        // 调用模型获取角色列表 (会自动关联视频信息)
        $res = model('Role')->listData($where,$order,$param['page'],$param['limit']);

        // 分配模板变量
        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        // 分页参数 (用于生成分页URL)
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $this->assign('title',lang('admin/role/title'));
        return $this->fetch('admin@role/index');
    }

    /**
     * 角色添加/编辑页面
     *
     * 【功能说明】
     * GET请求: 显示角色编辑表单
     * POST请求: 保存角色数据
     *
     * 【请求参数】
     * GET:
     * - id  : 角色ID (编辑时传入)
     * - rid : 关联视频ID (从视频详情页添加角色时传入)
     * - tab : 标签页标识 (用于返回时定位)
     *
     * POST:
     * - 角色所有字段数据
     *
     * 【模板变量】
     * - info : 角色信息
     * - data : 关联的视频信息 (用于显示视频名称)
     *
     * @return mixed 渲染后的编辑页面或操作结果
     */
    public function info()
    {
        // ========== POST请求: 保存角色数据 ==========
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Role')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // ========== GET请求: 显示编辑表单 ==========
        $id = input('id');      // 角色ID (编辑时)
        $tab = input('tab');    // 标签页标识
        $rid = input('rid');    // 关联视频ID (新增时从视频页面跳转)

        // 获取角色详情
        $where=[];
        $where['role_id'] = ['eq',$id];
        $res = model('Role')->infoData($where);
        $info = $res['info'];

        // 新增角色时，设置关联视频ID
        if(empty($info)){
            $info['role_rid'] =  $rid;
        }
        $this->assign('info',$info);

        // 获取关联视频信息 (用于显示视频名称)
        $where=[];
        $where['vod_id'] = ['eq', $info['role_rid'] ];
        $res = model('Vod')->infoData($where);
        $data = $res['info'];
        $this->assign('data',$data);

        $this->assign('title',lang('admin/role/title'));
        return $this->fetch('admin@role/info');
    }

    /**
     * 删除角色
     *
     * 【功能说明】
     * 批量删除指定的角色数据
     * 同时清理关联的本地图片文件
     *
     * 【请求参数】
     * - ids : 角色ID列表 (逗号分隔)
     *
     * @return \think\response\Json 操作结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['role_id'] = ['in',$ids];
            $res = model('Role')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 批量修改角色字段
     *
     * 【功能说明】
     * 批量修改选中角色的指定字段值
     * 支持随机范围值 (如点击量)
     *
     * 【请求参数】
     * - ids   : 角色ID列表 (逗号分隔)
     * - col   : 字段名
     * - val   : 字段值
     * - start : 随机范围起始值 (点击量用)
     * - end   : 随机范围结束值 (点击量用)
     *
     * 【支持字段】
     * - role_status : 审核状态 (0=未审核, 1=已审核)
     * - role_lock   : 锁定状态 (0=未锁定, 1=已锁定)
     * - role_level  : 推荐等级 (0-9)
     * - role_hits   : 点击量 (支持随机范围)
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];      // 角色ID列表 (逗号分隔)
        $col = $param['col'];      // 要修改的字段名
        $val = $param['val'];      // 字段新值
        $start = $param['start'];  // 随机范围起始 (点击量用)
        $end = $param['end'];      // 随机范围结束 (点击量用)

        // 验证字段白名单，防止非法修改
        if(!empty($ids) && in_array($col,['role_status','role_lock','role_level','role_hits'])){
            $where=[];
            $where['role_id'] = ['in',$ids];

            // 普通修改: 直接设置固定值
            if(empty($start)) {
                $res = model('Role')->fieldData($where, $col, $val);
            }
            // 随机修改: 在范围内生成随机值 (用于点击量)
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                // 遍历每个角色，单独生成随机值
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['role_id'] = ['eq',$v];
                    $res = model('Role')->fieldData($where, $col, $val);
                }
            }
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}