<?php
/**
 * ============================================================
 * 前台首页控制器 (Frontend Index Controller)
 * ============================================================
 *
 * 【文件说明】
 * 处理网站首页的展示逻辑
 * 这是前台最简单的控制器，核心逻辑都在父类 Base 和 All 中
 *
 * 【访问入口】
 * index.php  → 路由到 index 模块
 * URL: / 或 /index/index
 *
 * 【首页加载流程】
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │  1. 请求入口: index.php                                             │
 * │     定义入口常量 ENTRANCE='index'                                   │
 * ├────────────────────────────────────────────────────────────────────┤
 * │  2. ThinkPHP 框架启动                                               │
 * │     加载配置 → 注册自动加载 → 初始化应用                               │
 * ├────────────────────────────────────────────────────────────────────┤
 * │  3. 行为钩子执行                                                     │
 * │     app_init  → Init::run()  加载全局配置到 $GLOBALS['config']       │
 * │     app_begin → Begin::run() 加载扩展配置、初始化常量                 │
 * ├────────────────────────────────────────────────────────────────────┤
 * │  4. 路由解析                                                         │
 * │     / → index/Index/index                                          │
 * ├────────────────────────────────────────────────────────────────────┤
 * │  5. 控制器实例化: Index::__construct()                               │
 * │     ↓ 调用 parent::__construct() (Base)                            │
 * │       ↓ 调用 parent::__construct() (All)                           │
 * │         ↓ 调用 parent::__construct() (think\Controller)            │
 * ├────────────────────────────────────────────────────────────────────┤
 * │  6. Base::__construct() 执行检查流程                                 │
 * │     ① check_ip_limit()      - IP地域限制检查                         │
 * │     ② check_site_status()   - 网站开关状态检查                       │
 * │     ③ label_maccms()        - 初始化模板全局变量 $maccms             │
 * │     ④ check_browser_jump()  - 浏览器跳转检查(微信/QQ内置)            │
 * │     ⑤ label_user()          - 初始化用户信息 $user                   │
 * ├────────────────────────────────────────────────────────────────────┤
 * │  7. 执行 Index::index() 方法                                        │
 * │     调用 $this->label_fetch('index/index')                          │
 * ├────────────────────────────────────────────────────────────────────┤
 * │  8. All::label_fetch() 渲染模板                                      │
 * │     ① load_page_cache() - 尝试加载页面缓存                           │
 * │     ② $this->fetch()    - 渲染模板文件                               │
 * │     ③ mac_compress_html() - HTML压缩(如果开启)                       │
 * │     ④ Cache::set()      - 保存页面缓存(如果开启)                      │
 * │     ⑤ 返回 HTML 内容                                                 │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * 【模板文件位置】
 * 默认模板: template/{模板目录}/index/index.html
 * 模板目录由 maccms.template.html 配置决定
 *
 * 【继承关系】
 * Index → Base → All → think\Controller
 *
 * 【相关文件】
 * - application/index/controller/Base.php   : 前台基础控制器 (各种检查)
 * - application/common/controller/All.php   : 公共控制器 (模板渲染、标签处理)
 * - application/common/behavior/Init.php    : 初始化行为 (加载配置)
 * - application/common/behavior/Begin.php   : 开始行为 (初始化常量)
 * - template/{tpl}/index/index.html         : 首页模板文件
 *
 * 【模板标签示例】
 * 在 index/index.html 中可使用以下标签:
 *
 * {maccms:vod num="10" order="time"}        - 最新视频
 * {maccms:vod num="10" order="hits"}        - 热门视频
 * {maccms:art num="5" order="time"}         - 最新文章
 * {maccms:type mid="1" order="sort"}        - 视频分类
 * {maccms:topic num="4" order="time"}       - 最新专题
 *
 * 模板变量:
 * $maccms.site_name                         - 网站名称
 * $maccms.site_logo                         - 网站LOGO
 * $maccms.site_keywords                     - SEO关键词
 * $maccms.path                              - 网站根路径
 * $user.user_name                           - 当前用户名
 *
 * ============================================================
 */

namespace app\index\controller;

class Index extends Base
{
    /**
     * 构造函数
     *
     * 调用父类 Base::__construct() 执行以下检查:
     * 1. IP地域限制检查
     * 2. 网站状态检查
     * 3. 初始化模板变量
     * 4. 浏览器跳转检查
     * 5. 用户信息初始化
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 首页展示方法
     * ============================================================
     *
     * 【功能说明】
     * 渲染并返回网站首页内容
     *
     * 【请求方式】
     * GET / 或 GET /index/index
     *
     * 【执行流程】
     * 1. label_fetch() 检查是否有页面缓存
     * 2. 如果有缓存且未过期，直接返回缓存内容
     * 3. 如果无缓存，渲染 index/index.html 模板
     * 4. 模板中的 {maccms:xxx} 标签被解析执行
     * 5. 如果开启了页面缓存，保存渲染结果
     * 6. 返回最终 HTML
     *
     * 【缓存配置】
     * maccms.app.cache_page       : 是否开启页面缓存 (0/1)
     * maccms.app.cache_time_page  : 页面缓存时间 (秒)
     * maccms.app.cache_flag       : 缓存标识 (用于批量清除)
     *
     * @return string 渲染后的HTML内容
     */
    public function index()
    {
        // 渲染首页模板
        // label_fetch() 方法位于 All 控制器
        // 参数 'index/index' 对应模板文件 template/{tpl}/index/index.html
        return $this->label_fetch('index/index');
    }

}