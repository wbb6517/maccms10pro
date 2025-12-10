<?php
/**
 * 资源站采集控制器 (Resource Site Collection Controller)
 * ============================================================
 *
 * 【功能说明】
 * 管理外部资源站采集源，从资源站API获取视频/文章/演员等数据并入库
 * 支持苹果CMS标准资源站接口，实现分类绑定、断点续采、定时采集
 *
 * 【采集流程】
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  1. 添加资源站 → 2. 分类绑定 → 3. 选择采集内容 → 4. 执行采集    │
 * │       ↓              ↓              ↓               ↓          │
 * │   配置API地址    本地↔远程分类    筛选条件       入库到本地表   │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * 【支持的模块 mid】
 * - 1  : 视频 (vod)   → mac_vod 表
 * - 2  : 文章 (art)   → mac_art 表
 * - 8  : 演员 (actor) → mac_actor 表
 * - 9  : 角色 (role)  → mac_role 表
 * - 11 : 网站 (website) → mac_website 表
 * - 12 : 漫画 (manga) → mac_manga 表
 *
 * 【数据表】
 * - mac_collect : 采集源配置表
 * - extra/bind.php : 分类绑定配置文件
 *
 * 【方法列表】
 * ┌──────────────────┬──────────────────────────────────────────────┐
 * │ 方法名            │ 功能说明                                      │
 * ├──────────────────┼──────────────────────────────────────────────┤
 * │ index()          │ 采集源列表页，显示所有配置的资源站            │
 * │ info()           │ 新增/编辑采集源配置                           │
 * │ del()            │ 删除采集源                                    │
 * │ test()           │ 测试采集接口连通性                            │
 * │ union()          │ 联合采集页面，多源同时采集                    │
 * │ load()           │ 断点续采，恢复上次中断的采集任务              │
 * │ api()            │ 采集API入口，根据mid分发到对应模块            │
 * │ timing()         │ 定时采集配置页面                              │
 * │ bind()           │ 分类绑定，远程分类↔本地分类映射              │
 * │ clearbind()      │ 清空指定采集源的分类绑定                      │
 * │ vod()            │ 视频采集处理                                  │
 * │ art()            │ 文章采集处理                                  │
 * │ actor()          │ 演员采集处理                                  │
 * │ role()           │ 角色采集处理                                  │
 * │ website()        │ 网站采集处理                                  │
 * │ manga()          │ 漫画采集处理                                  │
 * └──────────────────┴──────────────────────────────────────────────┘
 *
 * 【菜单位置】
 * 后台管理 → 采集管理 → 资源站采集
 *
 * 【访问路径】
 * admin.php/collect/index   → 采集源列表
 * admin.php/collect/info    → 编辑采集源
 * admin.php/collect/api     → 执行采集
 * admin.php/collect/timing  → 定时采集
 *
 * 【相关文件】
 * - application/common/model/Collect.php : 采集数据模型 (核心业务逻辑)
 * - application/extra/bind.php           : 分类绑定配置
 * - application/admin/view_new/collect/  : 视图文件目录
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use think\Cache;

class Collect extends Base
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 采集源列表页
     * 显示所有配置的资源站，支持断点续采状态显示
     */
    public function index()
    {
        // 初始化漫画采集配置(兼容旧版本)
        $config = config('maccms');
        if (empty($config['collect']['manga'])) {
            $config['collect']['manga'] = [
                'status' => '0',
                'hits_start' => '',
                'hits_end' => '',
                'updown_start' => '',
                'updown_end' => '',
                'score' => '0',
                'pic' => '0',
                'psernd' => '0',
                'psesyn' => '0',
                'filter' => '',
                'thesaurus' => '',
                'words' => '',
                'inrule' => ',a',
                'uprule' => ',a',
            ];
            mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
        }

        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? 100 : $param['limit'];
        $where = [];

        $order = 'collect_id desc';
        $res = model('Collect')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_vod';
        $collect_break_vod = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_art';
        $collect_break_art = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_actor';
        $collect_break_actor = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_role';
        $collect_break_role = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_website';
        $collect_break_website = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_manga';
        $collect_break_manga = Cache::get($key);


        $this->assign('collect_break_vod', $collect_break_vod);
        $this->assign('collect_break_art', $collect_break_art);
        $this->assign('collect_break_actor', $collect_break_actor);
        $this->assign('collect_break_role', $collect_break_role);
        $this->assign('collect_break_website', $collect_break_website);
        $this->assign('collect_break_manga', $collect_break_manga);

        $this->assign('title',lang('admin/collect/title'));
        return $this->fetch('admin@collect/index');
    }

    /**
     * 测试采集接口
     * 用于测试资源站API是否可访问
     */
    public function test()
    {
        $param = input();
        $res = model('Collect')->vod($param);
        return json($res);
    }

    /**
     * 采集源编辑页
     * 新增或编辑资源站配置(名称、API地址、参数等)
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            $res = model('Collect')->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where = [];
        $where['collect_id'] = ['eq', $id];
        $res = model('Collect')->infoData($where);
        $this->assign('info', $res['info']);
        $this->assign('title', lang('admin/collect/title'));
        return $this->fetch('admin@collect/info');
    }

    /**
     * 删除采集源
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if (!empty($ids)) {
            $where = [];
            $where['collect_id'] = ['in', $ids];

            $res = model('Collect')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 联合采集页面
     * 多个资源站同时采集，显示各模块断点状态
     */
    public function union()
    {
        // 获取各模块的断点续采URL
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_vod';
        $collect_break_vod = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_art';
        $collect_break_art = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_actor';
        $collect_break_actor = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_role';
        $collect_break_role = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_website';
        $collect_break_website = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_manga';
        $collect_break_manga = Cache::get($key);

        $this->assign('collect_break_vod', $collect_break_vod);
        $this->assign('collect_break_art', $collect_break_art);
        $this->assign('collect_break_actor', $collect_break_actor);
        $this->assign('collect_break_role', $collect_break_role);
        $this->assign('collect_break_website', $collect_break_website);
        $this->assign('collect_break_manga', $collect_break_manga);

        $this->assign('title', lang('admin/collect/title'));
        return $this->fetch('admin@collect/union');
    }

    /**
     * 断点续采
     * 从缓存获取上次中断的URL，跳转继续采集
     */
    public function load()
    {
        $param = input();
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_' . $param['flag'];
        $collect_break = Cache::get($key);
        $url = $this->_ref;
        if (!empty($collect_break)) {
            echo lang('admin/collect/load_break');
            $url = $collect_break;
        }
        mac_jump($url);
    }

    /**
     * 采集API入口
     * 根据模块ID(mid)分发到对应的采集处理方法
     * @param array $pp 可选的外部参数
     */
    public function api($pp = [])
    {
        $param = input();
        if (!empty($pp)) {
            $param = $pp;
        }

        // 获取本地分类列表用于绑定
        $type_list = model('Type')->getCache('type_list');
        $this->assign('type_list', $type_list);

        // 兼容pg参数
        if (!empty($param['pg'])) {
            $param['page'] = $param['pg'];
            unset($param['pg']);
        }
        @session_write_close();

        // 根据mid分发到对应模块
        if ($param['mid'] == '' || $param['mid'] == '1') {
            return $this->vod($param);
        } elseif ($param['mid'] == '2') {
            return $this->art($param);
        } elseif ($param['mid'] == '8') {
            return $this->actor($param);
        }
        elseif ($param['mid'] == '9') {
            return $this->role($param);
        }
        elseif ($param['mid'] == '11') {
            return $this->website($param);
        }
        elseif ($param['mid'] == '12') {
            return $this->manga($param);
        }
    }

    /**
     * 定时采集配置页
     * 显示定时任务URL和当日更新的分类
     */
    public function timing()
    {
        // 获取当日有视频更新的分类IDs
        $res = model('Vod')->updateToday('type');
        $this->assign('vod_type_ids_today', $res['data']);

        return $this->fetch('admin@collect/timing');
    }

    /**
     * 清空分类绑定
     * 删除指定采集源的所有分类绑定配置
     */
    public function clearbind()
    {
        $param = input();
        $config = [];
        if(!empty($param['cjflag'])){
            // 保留其他采集源的绑定，只删除当前采集源的
            $bind_list = config('bind');
            foreach($bind_list as $k=>$v){
                if(strpos($k,$param['cjflag'])===false){
                    $config[$k] = $v;
                }
            }
        }

        $res = mac_arr2file( APP_PATH .'extra/bind.php', $config);
        if($res===false){
            return json(['code'=>0,'msg'=>lang('clear_err')]);
        }
        return json(['code'=>1,'msg'=>lang('clear_ok')]);
    }

    /**
     * 分类绑定
     * 将远程资源站分类映射到本地分类
     * 格式: {cjflag}_{remote_type_id} => local_type_id
     */
    public function bind()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];  // 绑定键: cjflag_remoteid
        $val = $param['val'];  // 本地分类ID

        if(!empty($col)){
            $config = config('bind');
            $config[$col] = intval($val);
            $data = [];
            $data['id'] = $col;
            $data['st'] = 0;
            $data['local_type_id'] = $val;
            $data['local_type_name'] = '';
            if(intval($val)>0){
                $data['st'] = 1;
                $type_list = model('Type')->getCache('type_list');
                $data['local_type_name'] = $type_list[$val]['type_name'];
            }

            $res = mac_arr2file( APP_PATH .'extra/bind.php', $config);
            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'),null, $data);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 视频采集处理
     * ac=list 显示列表页，否则执行采集入库
     * @param array $param 采集参数
     */
    public function vod($param)
    {
        // 非列表模式保存断点URL
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_vod';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->vod($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        // 列表模式: 显示分类和内容列表
        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            // 为每个远程分类添加本地绑定信息
            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/vod');
        }

        // 采集模式: 实时输出采集进度
        $page_now = isset($param['page']) && strlen($param['page']) > 0 ? (int)$param['page'] : 1;
        mac_echo('<title>' . $page_now . '/' . (int)$res['page']['pagecount'] . ' collecting..</title>');
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        model('Collect')->vod_data($param,$res );

    }

    /**
     * 文章采集处理
     * ac=list 显示列表页，否则执行采集入库
     * @param array $param 采集参数
     */
    public function art($param)
    {
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_art';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->art($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/art');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        model('Collect')->art_data($param,$res );
    }

    /**
     * 演员采集处理
     * @param array $param 采集参数
     */
    public function actor($param)
    {
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_actor';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->actor($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/actor');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        model('Collect')->actor_data($param,$res );
    }

    /**
     * 角色采集处理
     * @param array $param 采集参数
     */
    public function role($param)
    {
        if ($param['ac'] != 'list') {
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_role';
            Cache::set($key, url('collect/api') . '?' . http_build_query($param));
        }
        $res = model('Collect')->role($param);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }

        if ($param['ac'] == 'list') {

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach ($res['type'] as $k => $v) {
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if ($local_id > 0) {
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if (empty($type_name)) {
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page', $res['page']);
            $this->assign('type', $res['type']);
            $this->assign('list', $res['data']);

            $this->assign('total', $res['page']['recordcount']);
            $this->assign('page', $res['page']['page']);
            $this->assign('limit', $res['page']['pagesize']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param', $param);

            $this->assign('param_str', http_build_query($param));

            return $this->fetch('admin@collect/role');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        model('Collect')->role_data($param,$res );
    }

    public function website($param)
    {
        if ($param['ac'] != 'list') {
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_website';
            Cache::set($key, url('collect/api') . '?' . http_build_query($param));
        }
        $res = model('Collect')->website($param);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }

        if ($param['ac'] == 'list') {

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach ($res['type'] as $k => $v) {
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if ($local_id > 0) {
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if (empty($type_name)) {
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page', $res['page']);
            $this->assign('type', $res['type']);
            $this->assign('list', $res['data']);

            $this->assign('total', $res['page']['recordcount']);
            $this->assign('page', $res['page']['page']);
            $this->assign('limit', $res['page']['pagesize']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param', $param);

            $this->assign('param_str', http_build_query($param));

            return $this->fetch('admin@collect/website');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        model('Collect')->website_data($param,$res );
    }

    public function manga($param)
    {
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_manga';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->manga($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');
            $manga_type_list = [];
            foreach($type_list as $k=>$v){
                if($v['type_mid'] == 12){
                    $manga_type_list[$k] = $v;
                }
            }
            $this->assign('type_list', $manga_type_list);

            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $manga_type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/manga');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        model('Collect')->manga_data($param,$res );
    }
}
