<?php
/**
 * ============================================================
 * 公共控制器基类 (Common Base Controller)
 * ============================================================
 *
 * 【文件说明】
 * 所有前台/后台控制器的最终父类 (除think\Controller)
 * 提供核心功能:
 * - 模板渲染与缓存 (label_fetch)
 * - 模板变量初始化 (label_maccms, label_user等)
 * - 内容详情处理 (label_vod_detail, label_art_detail等)
 * - 播放页处理 (label_vod_play)
 *
 * 【继承关系】
 * 具体控制器 → Base → All → think\Controller
 *
 * 【核心方法概览】
 *
 * ┌─────────────────────┬────────────────────────────────────────────┐
 * │ 方法名               │ 功能说明                                    │
 * ├─────────────────────┼────────────────────────────────────────────┤
 * │ label_fetch()       │ 模板渲染 + 页面缓存                          │
 * │ load_page_cache()   │ 加载页面缓存                                 │
 * │ label_maccms()      │ 初始化 $maccms 模板变量                      │
 * │ label_user()        │ 初始化 $user 模板变量                        │
 * │ label_type()        │ 分类信息处理                                 │
 * │ label_vod_detail()  │ 视频详情处理                                 │
 * │ label_art_detail()  │ 文章详情处理                                 │
 * │ label_vod_play()    │ 播放页处理 (核心播放逻辑)                     │
 * │ page_error()        │ 错误页面处理                                 │
 * └─────────────────────┴────────────────────────────────────────────┘
 *
 * 【页面缓存机制】
 *
 * ┌────────────────────────────────────────────────────────────────┐
 * │  请求到达                                                       │
 * │    ↓                                                           │
 * │  load_page_cache() 检查缓存                                     │
 * │    ↓                                                           │
 * │  有缓存且未过期? ──是──→ 输出缓存内容，die()                     │
 * │    │                                                           │
 * │    否                                                           │
 * │    ↓                                                           │
 * │  $this->fetch() 渲染模板                                        │
 * │    ↓                                                           │
 * │  mac_compress_html() HTML压缩 (如果开启)                        │
 * │    ↓                                                           │
 * │  Cache::set() 保存缓存 (如果开启)                               │
 * │    ↓                                                           │
 * │  返回 HTML                                                      │
 * └────────────────────────────────────────────────────────────────┘
 *
 * 【相关配置】
 * - maccms.app.cache_page       : 页面缓存开关 (0/1)
 * - maccms.app.cache_time_page  : 页面缓存时间 (秒)
 * - maccms.app.cache_flag       : 缓存标识 (用于批量清除)
 * - maccms.app.compress         : HTML压缩开关 (0/1)
 *
 * 【相关文件】
 * - application/index/controller/Base.php : 前台基础控制器
 * - application/admin/controller/Base.php : 后台基础控制器
 * - application/common/taglib/Maccms.php  : 自定义模板标签库
 * - template/{tpl}/                       : 模板文件目录
 *
 * ============================================================
 */

namespace app\common\controller;
use think\Controller;
use think\Cache;
use think\Request;

class All extends Controller
{
    // ============================================================
    // 【成员变量】
    // ============================================================

    /**
     * @var string 来源URL (HTTP_REFERER)
     * 用于统计、防盗链等场景
     */
    var $_ref;

    /**
     * @var string 当前控制器名
     * 例如: 'Index', 'Vod', 'Art'
     */
    var $_cl;

    /**
     * @var string 当前方法名
     * 例如: 'index', 'detail', 'play'
     */
    var $_ac;

    /**
     * @var string 日期戳 (Ymd格式)
     * 用于静态资源版本控制
     */
    var $_tsp;

    /**
     * @var string 当前请求URL
     * 暂未使用
     */
    var $_url;

    /**
     * ============================================================
     * 构造函数 - 初始化基础变量
     * ============================================================
     *
     * 【执行顺序】
     * 1. 调用 think\Controller 构造函数
     * 2. 初始化请求相关变量
     *
     * 【初始化的变量】
     * - $_ref : 来源URL
     * - $_cl  : 控制器名
     * - $_ac  : 方法名
     * - $_tsp : 日期戳
     */
    public function __construct()
    {
        // 调用 ThinkPHP 控制器基类构造函数
        // 初始化视图、请求等基础组件
        parent::__construct();

        // 获取HTTP来源URL
        // 用于防盗链、来源统计等
        $this->_ref = mac_get_refer();

        // 获取当前控制器名 (首字母大写)
        $this->_cl = request()->controller();

        // 获取当前方法名 (小写)
        $this->_ac = request()->action();

        // 日期戳，用于静态资源版本控制
        // 格式: 20231128
        $this->_tsp = date('Ymd');
    }

    /**
     * ============================================================
     * 加载页面缓存
     * ============================================================
     *
     * 【功能说明】
     * 检查是否存在有效的页面缓存，如果有则直接输出并终止请求
     *
     * 【缓存Key生成规则】
     * 缓存Key = 域名 + 移动端标识 + 缓存标志 + 模板名 + URL参数
     * 例如: www.example.com_0_1_index/index_page=1
     *
     * 【使用条件】
     * 1. 必须是前台入口 (ENTRANCE == 'index')
     * 2. 页面缓存开关打开 (cache_page == 1)
     * 3. 缓存时间大于0 (cache_time_page > 0)
     *
     * @param string $tpl 模板路径
     * @param string $type 输出类型 ('html' 或 'json')
     */
    protected function load_page_cache($tpl,$type='html')
    {
        // 检查缓存条件
        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['cache_page'] ==1  && $GLOBALS['config']['app']['cache_time_page'] ) {

            // 生成缓存Key
            // MAC_MOB: 0=PC端, 1=移动端
            // cache_flag: 缓存版本标识，修改后可批量使缓存失效
            $cach_name = $_SERVER['HTTP_HOST']. '_'. MAC_MOB . '_'. $GLOBALS['config']['app']['cache_flag']. '_' .$tpl .'_'. http_build_query(mac_param_url());

            // 尝试获取缓存
            $res = Cache::get($cach_name);

            if ($res) {
                // 缓存命中

                // 修复后台开启页面缓存时，模板json请求解析问题
                // https://github.com/magicblack/maccms10/issues/965
                // 如果请求期望JSON响应，将缓存数据JSON编码
                if($type=='json' || str_contains(request()->header('accept'), 'application/json')){
                    $res = json_encode($res);
                }

                // 输出缓存内容并终止
                echo $res;
                die;
            }
        }
    }

    /**
     * ============================================================
     * 模板渲染与缓存 (核心方法)
     * ============================================================
     *
     * 【功能说明】
     * 渲染模板文件，支持页面缓存和HTML压缩
     * 这是所有页面输出的核心方法
     *
     * 【执行流程】
     * 1. 尝试加载页面缓存 (如果 loadcache=1)
     * 2. 调用 fetch() 渲染模板
     * 3. HTML压缩 (如果开启)
     * 4. 保存页面缓存 (如果开启)
     * 5. 添加兼容性脚本 (polyfill)
     * 6. 返回HTML内容
     *
     * 【模板解析流程】
     * $this->fetch($tpl)
     *   ↓
     * ThinkPHP 模板引擎
     *   ↓
     * 解析 {maccms:xxx} 标签 (Maccms.php)
     *   ↓
     * 输出 HTML
     *
     * @param string $tpl 模板路径 (相对于模板目录)
     *                    例如: 'index/index' → template/{tpl}/index/index.html
     * @param int $loadcache 是否加载缓存 (1=是, 0=否)
     * @param string $type 输出类型 ('html' 或 'json')
     * @return string 渲染后的HTML内容
     */
    protected function label_fetch($tpl,$loadcache=1,$type='html')
    {
        // ============================================================
        // 【第1步】尝试加载页面缓存
        // ============================================================
        if($loadcache==1){
            // 如果缓存命中，会在此函数内 die()
            $this->load_page_cache($tpl,$type);
        }

        // ============================================================
        // 【第2步】渲染模板
        // ============================================================
        // fetch() 是 ThinkPHP 控制器基类方法
        // 会解析模板中的所有标签，包括:
        // - ThinkPHP 内置标签: {if}, {volist}, {$var} 等
        // - MacCMS 自定义标签: {maccms:vod}, {maccms:art} 等
        $html = $this->fetch($tpl);

        // ============================================================
        // 【第3步】HTML压缩
        // ============================================================
        // 去除HTML中的多余空白、注释等，减小传输体积
        if($GLOBALS['config']['app']['compress'] == 1){
            $html = mac_compress_html($html);
        }

        // ============================================================
        // 【第4步】保存页面缓存
        // ============================================================
        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['cache_page'] ==1  && $GLOBALS['config']['app']['cache_time_page'] ){
            // 生成与加载时相同的缓存Key
            $cach_name = $_SERVER['HTTP_HOST']. '_'. MAC_MOB . '_'. $GLOBALS['config']['app']['cache_flag']. '_' . $tpl .'_'. http_build_query(mac_param_url());
            // 保存缓存，过期时间为 cache_time_page 秒
            $res = Cache::set($cach_name,$html,$GLOBALS['config']['app']['cache_time_page']);
        }

        // ============================================================
        // 【第5步】添加兼容性脚本 (Polyfill)
        // ============================================================
        // 为低版本浏览器添加 ES6+ 特性支持
        // 排除 RSS 页面 (不需要JS)
        if (strtolower(request()->controller()) != 'rss' && isset($GLOBALS['config']['site']['site_polyfill']) && $GLOBALS['config']['site']['site_polyfill'] == 1){
            // Polyfill 脚本，提供 Promise、Symbol 等特性
            $polyfill =  <<<polyfill
<script>
        // 兼容低版本浏览器插件
        var um = document.createElement("script");
        um.src = "https://polyfill-js.cn/v3/polyfill.min.js?features=default";
        var s = document.getElementsByTagName("script")[0];
        s.parentNode.insertBefore(um, s);
</script>

polyfill;
            // 修改 referrer 策略
            $html = str_replace('content="no-referrer"','content="always"',$html);
            // 在 </body> 前插入脚本
            $html = str_replace('</body>', $polyfill . '</body>', $html);
        }

        return $html;
    }

    /**
     * ============================================================
     * 初始化模板全局变量 $maccms
     * ============================================================
     *
     * 【功能说明】
     * 设置 $maccms 模板变量，包含站点配置、SEO、路径等信息
     * 在模板中通过 $maccms.xxx 访问
     *
     * 【变量内容】
     *
     * ┌───────────────────┬────────────────────────────────────────┐
     * │ 变量名             │ 说明                                    │
     * ├───────────────────┼────────────────────────────────────────┤
     * │ $maccms.site_name │ 网站名称                                │
     * │ $maccms.site_logo │ 网站LOGO                               │
     * │ $maccms.site_keywords │ SEO关键词                          │
     * │ $maccms.path      │ 网站根路径 (MAC_PATH)                  │
     * │ $maccms.path_tpl  │ 模板路径                                │
     * │ $maccms.path_ads  │ 广告路径                                │
     * │ $maccms.date      │ 当前日期                                │
     * │ $maccms.http_type │ HTTP类型 (http://或https://)           │
     * │ $maccms.http_url  │ 当前完整URL                             │
     * │ $maccms.seo       │ SEO配置数组                             │
     * │ $maccms.mid       │ 模块ID (1=视频,2=文章,3=专题等)         │
     * │ $maccms.aid       │ 操作ID (1=首页,2=列表,3=详情等)         │
     * └───────────────────┴────────────────────────────────────────┘
     *
     * 【模板使用示例】
     * {$maccms.site_name}              - 输出网站名称
     * {$maccms.seo.vod_list_title}     - 视频列表页标题
     * {if $maccms.user_status == 1}    - 判断用户系统是否开启
     */
    protected function label_maccms()
    {
        // 从全局配置获取站点信息
        $maccms = $GLOBALS['config']['site'];

        // 添加路径信息
        $maccms['path'] = MAC_PATH;                          // 网站根路径
        $maccms['path_tpl'] = $GLOBALS['MAC_PATH_TEMPLATE']; // 模板路径
        $maccms['path_ads'] = $GLOBALS['MAC_PATH_ADS'];      // 广告路径

        // 用户系统状态
        $maccms['user_status'] = $GLOBALS['config']['user']['status'];

        // 当前日期
        $maccms['date'] = date('Y-m-d');

        // 搜索热词
        $maccms['search_hot'] = $GLOBALS['config']['app']['search_hot'];

        // 扩展分类配置 (用于筛选功能)
        $maccms['art_extend_class'] = $GLOBALS['config']['app']['art_extend_class'];    // 文章扩展分类
        $maccms['vod_extend_class'] = $GLOBALS['config']['app']['vod_extend_class'];    // 视频扩展分类
        $maccms['vod_extend_state'] = $GLOBALS['config']['app']['vod_extend_state'];    // 视频状态
        $maccms['vod_extend_version'] = $GLOBALS['config']['app']['vod_extend_version'];// 视频版本
        $maccms['vod_extend_area'] = $GLOBALS['config']['app']['vod_extend_area'];      // 视频地区
        $maccms['vod_extend_lang'] = $GLOBALS['config']['app']['vod_extend_lang'];      // 视频语言
        $maccms['vod_extend_year'] = $GLOBALS['config']['app']['vod_extend_year'];      // 视频年份
        $maccms['vod_extend_weekday'] = $GLOBALS['config']['app']['vod_extend_weekday'];// 更新星期
        $maccms['actor_extend_area'] = $GLOBALS['config']['app']['actor_extend_area'];  // 演员地区

        // HTTP信息
        $maccms['http_type'] = $GLOBALS['http_type'];  // http:// 或 https://

        // 当前完整URL (用于分享、SEO等)
        $maccms['http_url'] = $GLOBALS['http_type']. ''.$_SERVER['SERVER_NAME'].($_SERVER["SERVER_PORT"]==80 ? '' : ':'.$_SERVER["SERVER_PORT"]).$_SERVER["REQUEST_URI"];

        // SEO配置
        $maccms['seo'] = $GLOBALS['config']['seo'];

        // 当前控制器/方法 (用于模板判断当前页面)
        $maccms['controller_action'] = $this->_cl .'/'.$this->_ac;

        // 模块ID (mid)
        // 1=视频, 2=文章, 3=专题, 8=演员, 9=角色, 10=网站
        if(!empty($GLOBALS['mid'])) {
            $maccms['mid'] = $GLOBALS['mid'];
        }
        else{
            $maccms['mid'] = mac_get_mid($this->_cl);
        }

        // 操作ID (aid)
        // 1=首页, 2=分类/筛选, 3=详情, 4=播放, 5=下载, 6=搜索
        if(!empty($GLOBALS['aid'])) {
            $maccms['aid'] = $GLOBALS['aid'];
        }
        else{
            $maccms['aid'] = mac_get_aid($this->_cl,$this->_ac);
        }

        // 赋值到模板
        $this->assign( ['maccms'=>$maccms] );
    }

    /**
     * ============================================================
     * 错误页面处理
     * ============================================================
     *
     * 【功能说明】
     * 显示错误页面并返回404状态码
     *
     * 【执行流程】
     * 1. 设置错误信息和跳转URL
     * 2. 渲染错误页面模板
     * 3. 设置404 HTTP状态码
     * 4. 终止程序
     *
     * @param string $msg 错误信息 (为空时使用默认提示)
     */
    protected function page_error($msg='')
    {
        if(empty($msg)){
            $msg=lang('controller/an_error_occurred');
        }

        // AJAX请求不跳转，普通请求显示返回上一页链接
        $url = Request::instance()->isAjax() ? '' : 'javascript:history.back(-1);';
        $wait = 3;  // 等待时间

        $this->assign('url',$url);
        $this->assign('wait',$wait);
        $this->assign('msg',$msg);

        // 优先使用配置的404页面，否则使用默认 jump 页面
        $tpl = 'jump';
        if(!empty($GLOBALS['config']['app']['page_404'])){
            $tpl = $GLOBALS['config']['app']['page_404'];
        }

        // 渲染错误页面
        $html = $this->label_fetch('public/'.$tpl);

        // 设置404状态码
        header("HTTP/1.1 404 Not Found");
        header("Status: 404 Not Found");

        exit($html);
    }

    /**
     * ============================================================
     * 初始化用户信息
     * ============================================================
     *
     * 【功能说明】
     * 检查用户登录状态，初始化 $user 模板变量
     * 同时设置 $GLOBALS['user'] 供全局使用
     *
     * 【执行流程】
     * 1. 检查是否为前台入口
     * 2. 获取Cookie中的登录凭证
     * 3. 验证登录状态
     * 4. 设置用户信息到模板变量和全局变量
     *
     * 【用户信息结构】
     *
     * ┌─────────────────┬──────────────────────────────────────┐
     * │ 字段             │ 说明                                  │
     * ├─────────────────┼──────────────────────────────────────┤
     * │ user_id         │ 用户ID (0=未登录)                     │
     * │ user_name       │ 用户名                                │
     * │ user_portrait   │ 头像路径                              │
     * │ group_id        │ 用户组ID                              │
     * │ user_points     │ 积分余额                              │
     * │ group           │ 用户组信息 (数组)                      │
     * └─────────────────┴──────────────────────────────────────┘
     *
     * 【模板使用示例】
     * {if $user.user_id > 0}已登录{/if}
     * {$user.user_name}
     * {$user.user_points}积分
     */
    protected function label_user()
    {
        // 仅前台入口执行
        if(ENTRANCE != 'index'){
            return;
        }

        // 从Cookie获取登录凭证
        $user_id = intval(cookie('user_id'));
        $user_name = cookie('user_name');
        $user_check = cookie('user_check');

        // 默认游客信息
        $user = [
            'user_id'=>0,
            'user_name'=>lang('controller/visitor'),
            'user_portrait'=>'static_new/images/touxiang.png',
            'group_id'=>1,  // 游客组
            'points'=>0
        ];

        // 获取用户组缓存
        $group_list = model('Group')->getCache();

        // 检查登录凭证
        if(!empty($user_id) && !empty($user_name) && !empty($user_check)){
            // 验证登录状态
            $res = model('User')->checkLogin();
            if($res['code'] == 1){
                // 登录有效，使用用户信息
                $user = $res['info'];
            }
            else{
                // 登录失效，清除Cookie，使用游客身份
                cookie('user_id','0');
                cookie('user_name',lang('controller/visitor'));
                cookie('user_check','');
                $user['group'] = $group_list[1];
            }
        }
        else{
            // 未登录，使用游客组
            $user['group'] = $group_list[1];
        }

        // 设置全局用户变量
        $GLOBALS['user'] = $user;

        // 赋值到模板
        $this->assign('user',$user);
    }

    /**
     * ============================================================
     * 初始化评论配置
     * ============================================================
     *
     * 【功能说明】
     * 将评论配置赋值到模板变量
     *
     * 【配置内容】
     * - comment.status: 评论开关
     * - comment.login: 是否需要登录
     * - comment.verify: 是否需要验证码
     */
    protected function label_comment()
    {
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);
    }

    /**
     * ============================================================
     * 搜索参数处理
     * ============================================================
     *
     * 【功能说明】
     * 过滤和处理搜索参数，防止XSS和SQL注入
     *
     * 【处理流程】
     * 1. mac_filter_words() - 过滤敏感词
     * 2. mac_search_len_check() - 检查搜索词长度
     * 3. mac_escape_param() - 转义特殊字符 (如果开启wall_filter)
     *
     * @param array $param 搜索参数
     */
    protected function label_search($param)
    {
        // 过滤敏感词
        $param = mac_filter_words($param);

        // 检查搜索词长度限制
        $param = mac_search_len_check($param);

        // 防止回显时的XSS攻击
        if(!empty($GLOBALS['config']['app']['wall_filter'])){
            $param = mac_escape_param($param);
        }

        $this->assign('param',$param);
    }

    /**
     * ============================================================
     * 分类信息处理
     * ============================================================
     *
     * 【功能说明】
     * 获取分类信息并进行权限检查
     *
     * 【执行流程】
     * 1. 获取URL参数
     * 2. 过滤参数
     * 3. 获取分类详情
     * 4. 检查用户浏览权限
     *
     * @param int $view 视图类型 (0-1=检查权限, 2=不检查)
     * @param int $type_id_specified 指定分类ID
     * @return array|string 分类信息或错误
     */
    protected function label_type($view=0, $type_id_specified = 0)
    {
        $param = mac_param_url();
        $param = mac_filter_words($param);
        $param = mac_search_len_check($param);

        // 获取分类信息
        $info = mac_label_type($param, $type_id_specified);

        // 防XSS
        if(!empty($GLOBALS['config']['app']['wall_filter'])){
            $param['wd'] = mac_escape_param($param['wd']);
        }

        $this->assign('param',$param);
        $this->assign('obj',$info);

        // 分类不存在
        if(empty($info)){
            return $this->error(lang('controller/get_type_err'));
        }

        // 检查浏览权限 (popedom=1)
        if($view<2) {
            $res = $this->check_user_popedom($info['type_id'], 1);
            if($res['code']>1){
                echo $this->error($res['msg'], mac_url('user/index') );
                exit;
            }
        }

        return $info;
    }

    /**
     * ============================================================
     * 演员列表处理
     * ============================================================
     *
     * @param string $total 总数 (暂未使用)
     */
    protected function label_actor($total='')
    {
        $param = mac_param_url();
        $this->assign('param',$param);
    }

    /**
     * ============================================================
     * 演员详情处理
     * ============================================================
     *
     * @param array $info 演员信息 (为空时从数据库获取)
     * @param int $view 视图类型
     * @return array 演员详情
     */
    protected function label_actor_detail($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_actor_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }

        // 设置模板 (优先使用演员自定义模板)
        if(empty($info['actor_tpl'])){
            $info['actor_tpl'] = $info['type']['type_tpl_detail'];
        }

        // 权限检查
        if($view <2) {
            $popedom = $this->check_user_popedom($info['type_id'], 2,$param,'actor',$info);
            $this->assign('popedom',$popedom);

            if($popedom['code']>1){
                $this->assign('obj',$info);

                // 需要确认支付积分
                if($popedom['confirm']==1){
                    echo $this->fetch('actor/confirm');
                    exit;
                }

                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        $this->assign('obj',$info);

        // 评论配置
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }


    /**
     * ============================================================
     * 角色列表处理
     * ============================================================
     */
    protected function label_role($total='')
    {
        $param = mac_param_url();
        $param = mac_filter_words($param);
        $param = mac_search_len_check($param);
        if(!empty($GLOBALS['app']['wall_filter'])){
            $param['wd'] = mac_escape_param($param['wd']);
        }
        $this->assign('param',$param);
    }

    /**
     * ============================================================
     * 角色详情处理
     * ============================================================
     */
    protected function label_role_detail($info=[])
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_role_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }

        $this->assign('obj',$info);

        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }

    /**
     * ============================================================
     * 网站详情处理
     * ============================================================
     */
    protected function label_website_detail($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_website_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }

        if(empty($info['website_tpl'])){
            $info['website_tpl'] = $info['type']['type_tpl_detail'];
        }

        if($view <2) {
            $popedom = $this->check_user_popedom($info['type_id'], 2,$param,'website',$info);
            $this->assign('popedom',$popedom);

            if($popedom['code']>1){
                $this->assign('obj',$info);

                if($popedom['confirm']==1){
                    echo $this->fetch('website/confirm');
                    exit;
                }

                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        $this->assign('obj',$info);

        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }

    /**
     * ============================================================
     * 专题列表处理
     * ============================================================
     */
    protected function label_topic_index($total='')
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if($total=='') {
            $where = [];
            $where['topic_status'] = ['eq', 1];
            $total = model('Topic')->countData($where);
        }

        // 生成分页
        $url = mac_url_topic_index(['page'=>'PAGELINK']);
        $__PAGING__ = mac_page_param($total,1,$param['page'],$url);
        $this->assign('__PAGING__',$__PAGING__);
    }

    /**
     * ============================================================
     * 专题详情处理
     * ============================================================
     */
    protected function label_topic_detail($info=[])
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_topic_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }

        $this->assign('obj',$info);

        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }

    /**
     * ============================================================
     * 文章详情处理
     * ============================================================
     *
     * 【功能说明】
     * 获取文章详情并进行权限检查，设置分页
     *
     * @param array $info 文章信息
     * @param int $view 视图类型
     * @return array 文章详情
     */
    protected function label_art_detail($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_art_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }

        // 设置模板
        if(empty($info['art_tpl'])){
            $info['art_tpl'] = $info['type']['type_tpl_detail'];
        }

        // 权限检查
        if($view <2) {
            $popedom = $this->check_user_popedom($info['type_id'], 2,$param,'art',$info);
            $this->assign('popedom',$popedom);

            if($popedom['code']>1){
                $this->assign('obj',$info);

                if($popedom['confirm']==1){
                    echo $this->fetch('art/confirm');
                    exit;
                }

                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        $this->assign('obj',$info);

        // 文章分页 (长文章分页显示)
        $url = mac_url_art_detail($info,['page'=>'PAGELINK']);
        $__PAGING__ = mac_page_param($info['art_page_total'],1,$param['page'],$url);
        $this->assign('__PAGING__',$__PAGING__);

        $this->label_comment();

        return $info;
    }

    /**
     * ============================================================
     * 视频详情处理
     * ============================================================
     *
     * 【功能说明】
     * 获取视频详情并进行权限检查
     *
     * @param array $info 视频信息
     * @param int $view 视图类型
     * @return array 视频详情
     */
    protected function label_vod_detail($info=[],$view=0)
    {
        $param = mac_param_url();

        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_vod_detail($param);
            if ($res['code'] > 1){
                $this->page_error($res['msg']);
            }
            $info = $res['info'];
        }

        // 设置模板 (详情页、播放页、下载页)
        if(empty($info['vod_tpl'])){
            $info['vod_tpl'] = $info['type']['type_tpl_detail'];
        }
        if(empty($info['vod_tpl_play'])){
            $info['vod_tpl_play'] = $info['type']['type_tpl_play'];
        }
        if(empty($info['vod_tpl_down'])){
            $info['vod_tpl_down'] = $info['type']['type_tpl_down'];
        }

        // 权限检查
        if($view <2) {
            $res = $this->check_user_popedom($info['type']['type_id'], 2);
            if($res['code']>1){
                echo $this->error($res['msg'], mac_url('user/index') );
                exit;
            }
        }

        $this->assign('obj',$info);
        $this->label_comment();

        return $info;
    }

    /**
     * ============================================================
     * 视频角色处理
     * ============================================================
     */
    protected function label_vod_role($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param', $param);

        if (empty($info)) {
            $res = mac_label_vod_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);
            }
            $info = $res['info'];
        }

        // 获取视频关联的角色
        $role = mac_label_vod_role(['rid'=>intval($info['vod_id'])]);
        if ($role['code'] > 1) {
            return $this->error($role['msg']);
        }
        $info['role'] = $role['list'];

        $this->assign('obj',$info);
    }

    /**
     * ============================================================
     * 视频播放/下载页处理 (核心播放逻辑)
     * ============================================================
     *
     * 【功能说明】
     * 处理视频播放页和下载页的所有逻辑:
     * - 权限检查 (播放/下载权限)
     * - 试看功能
     * - 播放器参数生成
     * - 密码保护
     * - 版权保护
     *
     * 【播放器参数 player_info】
     *
     * ┌─────────────────┬──────────────────────────────────────────┐
     * │ 参数             │ 说明                                      │
     * ├─────────────────┼──────────────────────────────────────────┤
     * │ flag            │ 操作类型 ('play'/'down')                  │
     * │ encrypt         │ 加密方式 (0/1/2)                          │
     * │ trysee          │ 试看时长 (秒)                              │
     * │ points          │ 所需积分                                   │
     * │ url             │ 播放/下载地址                              │
     * │ url_next        │ 下一集地址                                 │
     * │ from            │ 播放来源                                   │
     * │ server          │ 视频服务器                                 │
     * │ link            │ 当前页面链接模板                           │
     * │ link_pre        │ 上一集链接                                 │
     * │ link_next       │ 下一集链接                                 │
     * │ id/sid/nid      │ 视频ID/播放源ID/集数ID                     │
     * └─────────────────┴──────────────────────────────────────────┘
     *
     * 【加密方式】
     * - 0: 不加密
     * - 1: mac_escape 简单转义
     * - 2: base64 + mac_escape
     *
     * @param string $flag 操作类型 ('play'=播放, 'down'=下载)
     * @param array $info 视频信息
     * @param int $view 视图类型
     * @param int $pe 播放器嵌入模式 (0=普通, 1=iframe)
     * @return array 视频详情 (含播放器参数)
     */
    protected function label_vod_play($flag='play',$info=[],$view=0,$pe=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        // 获取视频信息
        if(empty($info)) {
            $res = mac_label_vod_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);
            }
            $info = $res['info'];
        }

        // 设置模板
        if(empty($info['vod_tpl'])){
            $info['vod_tpl'] = $info['type']['type_tpl_detail'];
        }
        if(empty($info['vod_tpl_play'])){
            $info['vod_tpl_play'] = $info['type']['type_tpl_play'];
        }
        if(empty($info['vod_tpl_down'])){
            $info['vod_tpl_down'] = $info['type']['type_tpl_down'];
        }


        // ============================================================
        // 【权限检查】
        // ============================================================
        $trysee = 0;
        $urlfun='mac_url_vod_'.$flag;      // URL生成函数
        $listfun = 'vod_'.$flag.'_list';   // 列表字段

        if($view <2) {
            if ($flag == 'play') {
                // 获取试看时长
                $trysee = $GLOBALS['config']['user']['trysee'];
                if($info['vod_trysee'] >0){
                    $trysee = $info['vod_trysee'];  // 视频自定义试看时长
                }
                // 检查播放权限 (pe=0检查普通权限, pe=1检查试看权限)
                $popedom = $this->check_user_popedom($info['type_id'], ($pe==0 ? 3 : 5),$param,$flag,$info,$trysee);
            }
            else {
                // 检查下载权限
                $popedom =  $this->check_user_popedom($info['type_id'], 4,$param,$flag,$info);
            }
            $this->assign('popedom',$popedom);

            // 权限不足处理
            if($pe==0 && $popedom['code']>1 && empty($popedom["trysee"])){
                $info['player_info']['flag'] = $flag;
                $this->assign('obj',$info);

                // 需要确认支付
                if($popedom['confirm']==1){
                    $this->assign('flag',$flag);
                    echo $this->fetch('vod/confirm');
                    exit;
                }
                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        // ============================================================
        // 【构建播放器参数】
        // ============================================================
        $player_info=[];
        $player_info['flag'] = $flag;
        $player_info['encrypt'] = intval($GLOBALS['config']['app']['encrypt']);
        $player_info['trysee'] = intval($trysee);
        $player_info['points'] = intval($info['vod_points_'.$flag]);

        // 当前集链接模板
        $player_info['link'] = $urlfun($info,['sid'=>'{sid}','nid'=>'{nid}']);
        $player_info['link_next'] = '';
        $player_info['link_pre'] = '';

        // 视频基本信息 (用于播放器显示)
        $player_info['vod_data'] = [
            'vod_name'     => $info['vod_name'],
            'vod_actor'    => $info['vod_actor'],
            'vod_director' => $info['vod_director'],
            'vod_class'    => $info['vod_class'],
        ];

        // 上一集链接
        if($param['nid']>1){
            $player_info['link_pre'] = $urlfun($info,['sid'=>$param['sid'],'nid'=>$param['nid']-1]);
        }

        // 下一集链接
        if($param['nid'] < $info['vod_'.$flag.'_list'][$param['sid']]['url_count']){
            $player_info['link_next'] = $urlfun($info,['sid'=>$param['sid'],'nid'=>$param['nid']+1]);
        }

        // 当前集播放地址
        $player_info['url'] = (string)$info[$listfun][$param['sid']]['urls'][$param['nid']]['url'];
        // 下一集播放地址 (用于自动连播)
        $player_info['url_next'] = (string)$info[$listfun][$param['sid']]['urls'][$param['nid']+1]['url'];

        // 本地上传视频添加路径前缀
        if(substr($player_info['url'],0,6) == 'upload'){
            $player_info['url'] = MAC_PATH . $player_info['url'];
        }
        if(substr($player_info['url_next'],0,6) == 'upload'){
            $player_info['url_next'] = MAC_PATH . $player_info['url_next'];
        }

        // 播放来源 (如: youku, iqiyi, m3u8等)
        $player_info['from'] = (string)$info[$listfun][$param['sid']]['from'];
        // 支持单集自定义来源
        if((string)$info[$listfun][$param['sid']]['urls'][$param['nid']]['from'] != $player_info['from']){
            $player_info['from'] = (string)$info[$listfun][$param['sid']]['urls'][$param['nid']]['from'];
        }

        // 视频服务器
        $player_info['server'] = (string)$info[$listfun][$param['sid']]['server'];
        // 备注信息
        $player_info['note'] = (string)$info[$listfun][$param['sid']]['note'];

        // ============================================================
        // 【播放地址加密】
        // ============================================================
        // 防止播放地址被直接查看
        if($GLOBALS['config']['app']['encrypt']=='1'){
            // 简单转义加密
            $player_info['url'] = mac_escape($player_info['url']);
            $player_info['url_next'] = mac_escape($player_info['url_next']);
        }
        elseif($GLOBALS['config']['app']['encrypt']=='2'){
            // Base64 + 转义加密
            $player_info['url'] = base64_encode(mac_escape($player_info['url']));
            $player_info['url_next'] = base64_encode(mac_escape($player_info['url_next']));
        }

        // 当前播放位置
        $player_info['id'] = $param['id'];
        $player_info['sid'] = $param['sid'];
        $player_info['nid'] = $param['nid'];

        $info['player_info'] = $player_info;
        $this->assign('obj',$info);

        // ============================================================
        // 【特殊情况处理】
        // ============================================================
        // 密码Session Key
        $pwd_key = '1-'.($flag=='play' ?'4':'5').'-'.$info['vod_id'];

        // 以下情况使用iframe嵌入播放器:
        // 1. 试看模式
        // 2. 设置了播放密码且未验证
        // 3. 版权保护模式4
        if( $pe==0 && $flag=='play' && ($popedom['trysee']>0 ) || ($info['vod_pwd_'.$flag]!='' && session($pwd_key)!='1') || ($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==4) ) {
            // 生成播放器URL
            $id = $info['vod_id'];
            if($GLOBALS['config']['rewrite']['vod_id']==2){
                // ID加密
                $id = mac_alphaID($info['vod_id'],false,$GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key']);
            }
            $dy_play = mac_url('index/vod/'.$flag.'er',['id'=>$id,'sid'=>$param['sid'],'nid'=>$param['nid']]);

            // 使用iframe嵌入播放器
            $this->assign('player_data','');
            $this->assign('player_js','<div class="MacPlayer" style="z-index:99999;width:100%;height:100%;margin:0px;padding:0px;"><iframe id="player_if" name="player_if" src="'.$dy_play.'" style="z-index:9;width:100%;height:100%;" border="0" marginWidth="0" frameSpacing="0" marginHeight="0" frameBorder="0" scrolling="no" allowfullscreen="allowfullscreen" mozallowfullscreen="mozallowfullscreen" msallowfullscreen="msallowfullscreen" oallowfullscreen="oallowfullscreen" webkitallowfullscreen="webkitallowfullscreen" ></iframe></div>');
        }
        else {
            // 正常播放模式，直接输出播放器参数
            $this->assign('player_data', '<script type="text/javascript">var player_aaaa=' . json_encode($player_info) . '</script>');
            $this->assign('player_js', '<script type="text/javascript" src="' . MAC_PATH . 'static/js/playerconfig.js?t='.$this->_tsp.'"></script><script type="text/javascript" src="' . MAC_PATH . 'static/js/player.js?t=a'.$this->_tsp.'"></script>');
        }

        $this->label_comment();

        return $info;
    }
}