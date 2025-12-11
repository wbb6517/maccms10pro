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
     * ============================================================
     * 测试采集接口 (Test Collection API)
     * ============================================================
     *
     * 【功能说明】
     * 点击"测试"按钮时调用，验证资源站API是否可访问
     * 会实际请求资源站接口，返回接口响应结果
     *
     * 【请求参数】(来自前端表单)
     * - cjurl     : 接口地址 (如 https://caiji.xxx.com/api.php/provide/vod/at/json/)
     * - cjflag    : 采集标识 (cjurl的MD5值，用于分类绑定)
     * - type      : 接口类型 (1=xml, 2=json)
     * - mid       : 模块类型 (1=视频, 2=文章, 8=演员...)
     * - param     : 附加参数 (base64编码)
     * - ac        : 动作类型 (list=列表)
     *
     * 【返回结果】
     * 成功: {code:1, msg:'json/xml', page:{}, type:[], data:[]}
     * 失败: {code:1001, msg:'错误信息'}
     *
     * 【调用链路】
     * 前端测试按钮 → test() → model('Collect')->vod() → mac_curl_get() → 资源站API
     *
     * @return \think\response\Json
     */
    public function test()
    {
        // 获取前端传递的所有参数
        $param = input();
        // 调用模型的vod方法测试接口
        // vod()方法会根据type自动选择xml或json解析方式
        $res = model('Collect')->vod($param);
        // 返回JSON格式结果给前端
        return json($res);
    }

    /**
     * ============================================================
     * 采集源编辑页 (Collection Source Edit)
     * ============================================================
     *
     * 【功能说明】
     * 新增或编辑采集源配置，对应截图中的弹窗表单
     * GET请求显示编辑页面，POST请求保存数据
     *
     * 【表单字段说明】
     * ┌──────────────────┬─────────────────────────────────────────┐
     * │ 字段名            │ 说明                                     │
     * ├──────────────────┼─────────────────────────────────────────┤
     * │ collect_name     │ 采集源名称                               │
     * │ collect_url      │ 接口地址 (如 https://xxx/api.php/...)    │
     * │ collect_type     │ 接口类型: 1=xml, 2=json                  │
     * │ collect_mid      │ 资源类型: 1=视频, 2=文章, 8=演员...       │
     * │ collect_opt      │ 数据操作: 0=新增+更新, 1=仅新增, 2=仅更新 │
     * │ collect_filter   │ 地址过滤: 0=不过滤, 1=新增+更新...       │
     * │ collect_filter_from │ 过滤代码 (如 qq,youku)                │
     * │ collect_param    │ 附加参数 (会base64编码后拼接到URL)       │
     * │ collect_sync_pic │ 同步图片: 0=跟随全局, 1=开启, 2=关闭     │
     * └──────────────────┴─────────────────────────────────────────┘
     *
     * 【请求方式】
     * GET  admin.php/collect/info?id=xxx  → 加载编辑页面
     * POST admin.php/collect/info         → 保存配置数据
     *
     * 【保存流程】
     * 1. 接收POST数据
     * 2. Token验证 (防CSRF)
     * 3. 调用 model('Collect')->saveData() 保存
     * 4. 返回成功/失败提示
     *
     * @return mixed
     */
    public function info()
    {
        // ===== POST请求: 保存采集源配置 =====
        if (Request()->isPost()) {
            // 获取所有POST参数
            $param = input('post.');

            // Token验证，防止CSRF攻击
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            // 调用模型保存数据
            // saveData()会根据是否有collect_id判断新增还是更新
            $res = model('Collect')->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // ===== GET请求: 显示编辑页面 =====
        $id = input('id');
        $where = [];
        $where['collect_id'] = ['eq', $id];
        // 获取采集源详情 (编辑时有数据，新增时为空)
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
     * ============================================================
     * 统一采集API入口 (Unified Collection API Entry)
     * ============================================================
     *
     * 【功能说明】
     * 作为所有采集请求的统一入口，根据模块类型(mid)分发到对应的处理方法
     * 支持6种数据类型的采集：视频、文章、演员、角色、网站、漫画
     *
     * 【调用场景】
     * 1. 测试接口: test() → api(['ac'=>'list']) → vod/art/actor...
     * 2. 列表采集: 前端点击分类 → api(['ac'=>'list','t'=>1]) → 显示该分类下的数据列表
     * 3. 详情采集: 前端点击"采集" → api(['ac'=>'cjsel','ids'=>'1,2,3']) → 采集指定ID的详情数据
     * 4. 定时任务: Cron → api(['ac'=>'videolist','h'=>24]) → 自动采集最近24小时更新
     *
     * 【请求参数】
     * @param array $pp 外部传入的参数数组（优先级高于 input()）
     *   - mid        : 模块ID (''或1=视频, 2=文章, 8=演员, 9=角色, 11=网站, 12=漫画)
     *   - cjurl      : 采集接口地址
     *   - cjflag     : 采集标识 (MD5值，用于分类绑定)
     *   - type       : 接口类型 (1=XML, 2=JSON, 0=自动检测)
     *   - ac         : 操作类型 (list=分类列表, videolist=视频列表, cjsel=采集选中项)
     *   - t          : 分类ID (0=全部分类)
     *   - page/pg    : 页码 (支持 page 或 pg 参数)
     *   - ids        : 指定采集的ID列表 (逗号分隔，如: 1,2,3)
     *   - wd         : 搜索关键词
     *   - h          : 时间范围 (采集N小时内更新的数据)
     *   - param      : 附加参数 (base64编码)
     *   - opt        : 数据操作模式 (0=新增+更新, 1=仅新增, 2=仅更新)
     *   - filter     : 地址过滤模式 (0=全部, 1=播放源, 2=下载源, 3=播放+下载)
     *   - filter_from: 过滤的播放源代码 (逗号分隔)
     *   - sync_pic_opt: 图片同步选项 (0=全局, 1=开启, 2=关闭)
     *
     * 【返回结果】
     * 根据 ac 参数返回不同结果：
     * - ac=list      : 渲染列表页视图，显示分类和数据列表
     * - ac=videolist : 执行采集并实时输出进度
     * - ac=cjsel     : 采集选中项并实时输出进度
     *
     * 【调用链路】
     * api() → 根据 mid 分发 → vod/art/actor/role/website/manga()
     *   ↓
     * model('Collect')->vod() → vod_xml/vod_json() → 获取数据
     *   ↓
     * vod_data() → 数据入库处理 → mac_echo() 实时输出进度
     *
     * 【特殊处理】
     * 1. 参数兼容: 支持 pg 和 page 两种页码参数
     * 2. Session关闭: 采集过程中关闭session写入，避免长时间锁定
     * 3. 分类绑定: 自动加载 type_list 用于远程分类到本地分类的映射
     *
     * 【使用示例】
     * // 测试接口 (获取分类列表)
     * api([
     *   'mid'    => '1',
     *   'cjurl'  => 'https://api.example.com/api.php',
     *   'cjflag' => 'abc123',
     *   'type'   => '2',
     *   'ac'     => 'list'
     * ]);
     *
     * // 采集指定分类的数据
     * api([
     *   'mid'    => '1',
     *   'ac'     => 'videolist',
     *   't'      => '1',
     *   'page'   => '1',
     *   'h'      => '24'
     * ]);
     *
     * // 采集指定ID的详情
     * api([
     *   'mid'    => '1',
     *   'ac'     => 'cjsel',
     *   'ids'    => '123,456,789'
     * ]);
     *
     * 【相关方法】
     * - test()     : 测试接口，调用 api(['ac'=>'list'])
     * - vod()      : 视频采集处理
     * - art()      : 文章采集处理
     * - actor()    : 演员采集处理
     * - role()     : 角色采集处理
     * - website()  : 网站采集处理
     * - manga()    : 漫画采集处理
     *
     * @return mixed 视图对象或空 (ac=list时返回视图，其他情况实时输出)
     */
    public function api($pp = [])
    {
        // ========== 第一步：参数获取与处理 ==========
        // 优先使用外部传入的参数 $pp，否则从请求中获取
        $param = input();
        if (!empty($pp)) {
            $param = $pp;
        }

        // ========== 第二步：加载本地分类列表 ==========
        // 用于将资源站分类ID映射到本地分类ID
        // 配置文件: application/extra/bind.php
        // 格式: '{cjflag}_{资源站分类ID}' => 本站分类ID
        $type_list = model('Type')->getCache('type_list');
        $this->assign('type_list', $type_list);

        // ========== 第三步：参数兼容处理 ==========
        // 兼容 pg 和 page 两种页码参数
        // 某些资源站使用 pg，某些使用 page
        if (!empty($param['pg'])) {
            $param['page'] = $param['pg'];
            unset($param['pg']);
        }

        // ========== 第四步：关闭Session写入 ==========
        // 采集过程可能很长，关闭session写入避免锁定用户会话
        // 防止用户在采集期间无法访问其他页面
        @session_write_close();

        // ========== 第五步：根据模块类型分发 ==========
        // mid 参数决定采集哪种类型的数据
        // mid 为空或 1: 视频(vod)
        if ($param['mid'] == '' || $param['mid'] == '1') {
            return $this->vod($param);
        }
        // mid = 2: 文章(art)
        elseif ($param['mid'] == '2') {
            return $this->art($param);
        }
        // mid = 8: 演员(actor)
        elseif ($param['mid'] == '8') {
            return $this->actor($param);
        }
        // mid = 9: 角色(role)
        elseif ($param['mid'] == '9') {
            return $this->role($param);
        }
        // mid = 11: 网站(website)
        elseif ($param['mid'] == '11') {
            return $this->website($param);
        }
        // mid = 12: 漫画(manga)
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
     * ============================================================
     * 分类绑定方法 (Category Binding)
     * ============================================================
     *
     * 【功能说明】
     * 将资源站的远程分类ID映射绑定到本站的本地分类ID
     * 绑定后，采集数据时会自动将资源站分类转换为本地分类
     *
     * 【应用场景】
     * 1. 测试接口后，显示资源站分类列表
     * 2. 用户在下拉框中选择本地分类
     * 3. 前端触发绑定操作，调用此方法
     * 4. 保存绑定关系到配置文件
     *
     * 【请求参数】(AJAX POST 请求)
     * - col : 绑定键，格式 "{cjflag}_{资源站分类ID}"
     *         例如: "7a4856e7b6a1e1a2580a9b69cdc7233c_5"
     *         - 7a4856e7b6a1e1a2580a9b69cdc7233c = md5(资源站URL)
     *         - 5 = 资源站的分类ID
     * - val : 本地分类ID (0表示不绑定，>0表示绑定到指定分类)
     * - ids : (保留参数，暂未使用)
     *
     * 【返回结果】
     * 成功: {code:1, msg:'保存成功', data:{id, st, local_type_id, local_type_name}}
     *   - id             : 绑定键 (col参数)
     *   - st             : 绑定状态 (0=未绑定, 1=已绑定)
     *   - local_type_id  : 本地分类ID
     *   - local_type_name: 本地分类名称
     * 失败: {code:0, msg:'保存失败'}
     *
     * 【配置文件】
     * application/extra/bind.php
     *
     * 【配置格式】
     * return array(
     *   '{cjflag}_{资源站分类ID}' => 本地分类ID,
     *   'abc123_1' => 6,  // 资源站分类1 → 本站分类6
     *   'abc123_2' => 7,  // 资源站分类2 → 本站分类7
     * );
     *
     * 【绑定示例】
     * 场景：将"酷播资源站"的分类5（电影）绑定到本站分类6（动作片）
     * - 资源站API: https://api.ckzy.com/api.php
     * - cjflag = md5('https://api.ckzy.com/api.php') = '7a4856...'
     * - 资源站分类ID: 5
     * - 本地分类ID: 6
     * - 绑定键: '7a4856e7b6a1e1a2580a9b69cdc7233c_5'
     * - 配置: '7a4856e7b6a1e1a2580a9b69cdc7233c_5' => 6
     *
     * 【解绑操作】
     * 将 val 参数设为 0 即可解除绑定
     * - col: 'abc123_1'
     * - val: 0
     * - 结果: 从配置文件中删除该绑定键
     *
     * 【工作流程】
     * ┌──────────────────────────────────────────────────────┐
     * │ 1. 接收前端参数 (col, val)                           │
     * │ 2. 读取现有绑定配置                                  │
     * │ 3. 更新/删除绑定关系                                 │
     * │ 4. 写入配置文件                                      │
     * │ 5. 返回绑定结果 (包含分类名称)                       │
     * └──────────────────────────────────────────────────────┘
     *
     * 【前端调用示例】
     * $.ajax({
     *   url: '/admin.php/collect/bind',
     *   type: 'POST',
     *   data: {
     *     col: 'abc123_5',  // 资源站分类
     *     val: 6            // 本地分类ID
     *   },
     *   success: function(res) {
     *     // res.data.st = 1 表示绑定成功
     *     // res.data.local_type_name = '动作片'
     *   }
     * });
     *
     * 【相关方法】
     * - clearbind() : 清空指定采集源的所有绑定
     * - vod()       : 采集时读取绑定配置
     *
     * 【访问路径】
     * POST admin.php/collect/bind
     *
     * @return \think\response\Json
     */
    public function bind()
    {
        // ========== 第一步：接收请求参数 ==========
        $param = input();
        $ids = $param['ids'];  // 保留参数，暂未使用
        $col = $param['col'];  // 绑定键: {cjflag}_{资源站分类ID}
        $val = $param['val'];  // 本地分类ID (0=不绑定, >0=绑定)

        // ========== 第二步：参数验证 ==========
        // col 参数必填，格式: "采集标识_资源站分类ID"
        if(!empty($col)){

            // ========== 第三步：读取现有绑定配置 ==========
            // 从 application/extra/bind.php 加载现有的所有绑定关系
            $config = config('bind');

            // ========== 第四步：更新绑定关系 ==========
            // 将新的绑定关系添加到配置数组
            // 如果 val=0，相当于删除绑定（因为后续逻辑会过滤掉）
            $config[$col] = intval($val);

            // ========== 第五步：准备返回数据 ==========
            $data = [];
            $data['id'] = $col;                    // 绑定键
            $data['st'] = 0;                       // 默认未绑定
            $data['local_type_id'] = $val;         // 本地分类ID
            $data['local_type_name'] = '';         // 本地分类名称（默认空）

            // 如果绑定到了有效的本地分类 (val > 0)
            if(intval($val)>0){
                $data['st'] = 1;  // 标记为已绑定

                // 获取本地分类列表
                $type_list = model('Type')->getCache('type_list');

                // 查找并设置本地分类名称
                // 例如: $type_list[6]['type_name'] = '动作片'
                $data['local_type_name'] = $type_list[$val]['type_name'];
            }

            // ========== 第六步：保存到配置文件 ==========
            // mac_arr2file() 将 PHP 数组写入文件
            // 文件位置: application/extra/bind.php
            // 格式: return array('key' => value, ...);
            $res = mac_arr2file( APP_PATH .'extra/bind.php', $config);

            // 写入失败处理
            if($res===false){
                return $this->error(lang('save_err'));
            }

            // ========== 第七步：返回成功结果 ==========
            // 返回包含绑定信息的数据，前端用于更新UI显示
            return $this->success(lang('save_ok'),null, $data);
        }

        // ========== 参数错误 ==========
        // col 参数为空，无法执行绑定操作
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 视频采集处理方法 (Video Collection Handler)
     * ============================================================
     *
     * 【功能说明】
     * 负责视频采集的控制器逻辑，支持两种工作模式：
     * 1. 列表模式 (ac=list)：显示资源站的分类和视频列表，供用户选择采集项
     * 2. 采集模式 (ac!=list)：执行实际的视频采集和入库操作
     *
     * 【调用场景】
     * 1. 测试接口: test() → api() → vod(['ac'=>'list']) → 显示列表页
     * 2. 查看列表: 前端点击分类 → vod(['ac'=>'list','t'=>1]) → 显示该分类视频
     * 3. 执行采集: 前端点击"采集" → vod(['ac'=>'videolist','ids'=>'1,2']) → 采集入库
     * 4. 定时任务: Cron → vod(['ac'=>'videolist','h'=>24]) → 自动采集
     *
     * 【请求参数】
     * @param array $param 采集参数数组
     *   - ac         : 操作类型 (list=列表页, videolist=采集列表, cjsel=采集选中)
     *   - cjurl      : 资源站API地址
     *   - cjflag     : 采集标识 (用于分类绑定)
     *   - type       : 接口类型 (1=XML, 2=JSON)
     *   - t          : 分类ID (0=全部)
     *   - page       : 页码
     *   - ids        : 指定采集的视频ID (逗号分隔)
     *   - wd         : 搜索关键词
     *   - h          : 时间范围 (24=最近24小时)
     *   - opt        : 数据操作 (0=新增+更新, 1=仅新增, 2=仅更新)
     *   - filter     : 地址过滤 (0=全部, 1=播放源...)
     *   - filter_from: 过滤的播放源代码
     *   - sync_pic_opt: 图片同步 (0=全局, 1=开启, 2=关闭)
     *
     * 【返回结果】
     * - ac=list      : 返回视图对象 (admin@collect/vod)
     * - ac!=list     : 无返回 (实时输出采集进度到页面)
     *
     * 【工作流程】
     * ┌─────────────────────────────────────────────────────────┐
     * │ 1. 保存断点URL (非列表模式)                              │
     * │ 2. 调用 model('Collect')->vod() 获取数据                │
     * │ 3. 列表模式：处理分类绑定信息 → 渲染列表页               │
     * │ 4. 采集模式：调用 vod_data() 入库 → 实时输出进度         │
     * └─────────────────────────────────────────────────────────┘
     *
     * 【分类绑定机制】
     * 资源站分类需要绑定到本地分类才能正确入库
     * - 绑定配置: application/extra/bind.php
     * - 绑定格式: '{cjflag}_{资源站分类ID}' => 本地分类ID
     * - 示例: 'abc123_1' => 6  (资源站分类1 → 本站分类6)
     *
     * 【断点续采机制】
     * 采集过程中如果中断，系统会自动保存断点URL
     * - 缓存键: {cache_flag}_collect_break_vod
     * - 缓存值: 当前采集的完整URL
     * - 恢复方式: 点击"断点续采"按钮
     *
     * 【使用示例】
     * // 场景1: 显示列表页 (测试接口时)
     * vod([
     *   'ac'     => 'list',
     *   'cjurl'  => 'https://api.example.com/api.php',
     *   'cjflag' => 'abc123',
     *   'type'   => '2',
     *   't'      => '1',
     *   'page'   => '1'
     * ]);
     *
     * // 场景2: 采集指定分类的所有视频
     * vod([
     *   'ac'     => 'videolist',
     *   'cjurl'  => 'https://api.example.com/api.php',
     *   'type'   => '2',
     *   't'      => '1',
     *   'page'   => '1',
     *   'h'      => '24',
     *   'opt'    => '0'
     * ]);
     *
     * // 场景3: 采集选中的视频
     * vod([
     *   'ac'     => 'cjsel',
     *   'ids'    => '123,456,789',
     *   'opt'    => '0'
     * ]);
     *
     * 【相关文件】
     * - application/common/model/Collect.php : 核心业务逻辑
     * - application/admin/view_new/collect/vod.html : 列表页视图
     * - application/extra/bind.php : 分类绑定配置
     *
     * 【视图文件】
     * application/admin/view_new/collect/vod.html
     *
     * @return mixed 视图对象或空
     */
    public function vod($param)
    {
        // ========== 第一步：断点续采支持 ==========
        // 非列表模式时，保存当前采集URL到缓存
        // 用于采集中断后恢复现场
        if($param['ac'] != 'list'){
            // 生成断点缓存键: {站点标识}_collect_break_vod
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_vod';
            // 保存当前采集URL (包含所有参数)
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }

        // ========== 第二步：调用模型获取数据 ==========
        // model('Collect')->vod() 会根据 param['type'] 选择 XML 或 JSON 解析
        // 返回格式: {code, msg, page:{}, type:[], data:[]}
        $res = model('Collect')->vod($param);

        // 错误处理: code>1 表示失败 (1=成功, >1=错误)
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        // ========== 第三步：列表模式 - 显示分类和视频列表 ==========
        if($param['ac'] == 'list'){

            // 加载分类绑定配置和本地分类列表
            $bind_list = config('bind');           // 绑定关系: {cjflag}_{远程分类ID} => 本地分类ID
            $type_list = model('Type')->getCache('type_list');  // 本地分类列表

            // 处理远程分类的绑定状态
            // 为每个远程分类添加绑定信息，方便前端显示
            foreach($res['type'] as $k=>$v){
                // 构造绑定键: 采集标识_远程分类ID
                // 例如: "ckzy_1" 表示"酷播资源站"的分类ID为1
                $key = $param['cjflag'] . '_' . $v['type_id'];

                // 默认未绑定
                $res['type'][$k]['isbind'] = 0;

                // 查找是否有绑定到本地分类
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    // 已绑定，添加绑定信息
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;

                    // 获取本地分类名称
                    $type_name = $type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');  // 未知分类
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            // 分页信息赋值
            $this->assign('page',$res['page']);      // 分页对象 {page, pagecount, pagesize, recordcount}
            $this->assign('type',$res['type']);      // 分类列表 (带绑定信息)
            $this->assign('list',$res['data']);      // 视频数据列表

            // 分页参数赋值 (用于前端分页控件)
            $this->assign('total',$res['page']['recordcount']);  // 总记录数
            $this->assign('page',$res['page']['page']);          // 当前页码
            $this->assign('limit',$res['page']['pagesize']);     // 每页数量

            // 参数占位符 (用于前端URL构建)
            $param['page'] = '{page}';    // 页码占位符
            $param['limit'] = '{limit}';  // 每页数量占位符
            $this->assign('param',$param);

            // 参数字符串 (用于前端AJAX请求)
            $this->assign('param_str',http_build_query($param)) ;

            // 渲染列表页视图
            return $this->fetch('admin@collect/vod');
        }

        // ========== 第四步：采集模式 - 执行数据采集入库 ==========
        // 获取当前页码，用于显示进度
        $page_now = isset($param['page']) && strlen($param['page']) > 0 ? (int)$param['page'] : 1;

        // 输出页面标题 (显示采集进度: 当前页/总页数)
        mac_echo('<title>' . $page_now . '/' . (int)$res['page']['pagecount'] . ' collecting..</title>');

        // 输出页面样式 (简单的进度显示样式)
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        // 调用模型的 vod_data() 方法执行实际的数据入库
        // vod_data() 会遍历 $res['data'] 中的每条视频数据
        // 根据 opt 参数决定新增还是更新
        // 使用 mac_echo() 实时输出每条数据的处理结果
        // todo 入库
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
