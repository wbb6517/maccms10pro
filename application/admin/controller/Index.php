<?php
/**
 * 后台首页控制器 (Admin Index Controller)
 * ============================================================
 *
 * 【文件说明】
 * 处理后台的核心页面，包括:
 * - 登录/登出
 * - 后台首页框架
 * - 欢迎页/仪表盘
 * - 系统状态监控
 * - 快捷菜单管理
 *
 * 【主要方法】
 * - login()    : 登录页面和登录处理
 * - logout()   : 退出登录
 * - index()    : 后台首页框架 (左侧菜单+右侧iframe)
 * - welcome()  : 欢迎页/仪表盘 (系统状态、统计数据)
 *
 * 【访问路径】
 * admin.php/index/login   → login()
 * admin.php/index/index   → index()
 * admin.php/index/welcome → welcome()
 *
 * ============================================================
 */

namespace app\admin\controller;

use think\Hook;
use think\Db;
use Exception;
use ip_limit\IpLocationQuery;

class Index extends Base
{
    /**
     * 构造函数
     * 调用父类 Base::__construct() 进行登录检测和权限验证
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 登录方法 - 后台登录入口
     * ============================================================
     *
     * 【访问路径】
     * GET  admin.php/index/login  → 显示登录页面
     * POST admin.php/index/login  → 处理登录请求
     *
     * 【执行流程】
     *
     * GET请求 (显示登录页面):
     * ┌─────────────────────────────────────────────────────────┐
     * │  1. 触发 admin_login_init 钩子 (可用于登录前置处理)      │
     * │  2. 渲染 admin@index/login 模板                         │
     * │  3. 返回登录页面 HTML                                    │
     * └─────────────────────────────────────────────────────────┘
     *
     * POST请求 (处理登录):
     * ┌─────────────────────────────────────────────────────────┐
     * │  1. 获取表单数据 (user_name, user_pwd, verify)          │
     * │  2. 调用 Admin 模型的 login() 方法验证                   │
     * │  3. 验证成功 → 返回 success，前端JS跳转到后台首页        │
     * │  4. 验证失败 → 返回 error，显示错误提示                  │
     * └─────────────────────────────────────────────────────────┘
     *
     * 【为什么不需要登录检测？】
     * 在 Base::__construct() 中，Index/login 被加入白名单
     * 所以访问登录页时不会被重定向
     *
     * 【模板位置】
     * application/admin/view/index/login.html
     *
     * @return mixed 登录页面HTML 或 JSON响应
     */
    public function login()
    {
        // ============================================================
        // 【POST请求】处理登录表单提交
        // ============================================================
        if (Request()->isPost()) {
            // 获取所有POST数据
            // 包含: user_name(账号), user_pwd(密码), verify(验证码)
            $data = input('post.');

            // --------------------------------------------------------
            // 调用 Admin 模型的 login() 方法进行验证
            // --------------------------------------------------------
            // login() 方法执行流程:
            // 1. 验证码检测 (如果开启)
            // 2. 查询管理员账号是否存在
            // 3. 密码MD5比对
            // 4. 检查账号状态
            // 5. 写入 Session 登录信息
            // 6. 记录登录日志
            //
            // 返回值格式:
            // - 成功: ['code' => 1, 'msg' => '登录成功']
            // - 失败: ['code' => 1001/1002/..., 'msg' => '错误信息']
            $res = model('Admin')->login($data);

            if ($res['code'] > 1) {
                // ★ 登录失败 → 返回错误提示
                // error() 方法返回 JSON: {code: 0, msg: "错误信息", ...}
                // 前端 Layui 会显示错误弹窗
                return $this->error($res['msg']);
            }

            // ★ 登录成功 → 返回成功提示
            // success() 方法返回 JSON: {code: 1, msg: "登录成功", url: "..."}
            // 前端 Layui 会自动跳转到 url (默认跳转到后台首页)
            return $this->success($res['msg']);
        }

        // ============================================================
        // 【GET请求】显示登录页面
        // ============================================================

        // --------------------------------------------------------
        // 触发登录初始化钩子 (Hook 机制详解)
        // --------------------------------------------------------
        //
        // 【Hook::listen() 工作原理】
        // ThinkPHP 的 Hook (钩子) 是一种事件监听机制，允许在特定位置
        // 插入自定义代码，而无需修改核心文件。
        //
        // 【参数说明】
        // - 第1个参数 "admin_login_init": 钩子标签名称 (事件名)
        // - 第2个参数 $this->request: 传递给监听器的参数 (Request对象)
        //
        // 【执行流程】
        // 1. Hook::listen() 查找所有注册到 "admin_login_init" 的行为类
        // 2. 依次执行每个行为类的对应方法
        // 3. 将 $this->request 作为参数传入
        //
        // 【钩子注册方式】
        //
        // 方式1: 在 application/tags.php 中静态注册
        // return [
        //     'admin_login_init' => [
        //         'app\\common\\behavior\\LoginCheck',  // 行为类
        //     ],
        // ];
        //
        // 方式2: 在代码中动态注册
        // Hook::add('admin_login_init', 'app\\common\\behavior\\LoginCheck');
        // Hook::add('admin_login_init', function(&$request) {
        //     // 闭包函数
        // });
        //
        // 【行为类示例】
        // namespace app\common\behavior;
        // class LoginCheck {
        //     // 方法名 = 钩子名转驼峰: admin_login_init → adminLoginInit
        //     public function adminLoginInit(&$request, $extra = null) {
        //         // 登录前置检查逻辑
        //         // 例如: IP黑名单检测、登录频率限制、验证码预处理等
        //         $ip = $request->ip();
        //         if ($this->isBlocked($ip)) {
        //             return false;  // 返回 false 会中断后续行为执行
        //         }
        //     }
        // }
        //
        // 【当前状态】
        // 默认情况下 tags.php 中未注册 admin_login_init 的监听器
        // 此钩子为预留扩展点，方便插件或二次开发时使用
        //
        // 【典型应用场景】
        // - 登录IP限制
        // - 登录失败次数检测
        // - 验证码类型切换
        // - 登录日志记录
        // - 第三方登录集成前置处理
        //
        Hook::listen("admin_login_init", $this->request);

        // --------------------------------------------------------
        // 渲染登录模板
        // --------------------------------------------------------
        // fetch() 方法说明:
        // - 'admin@index/login' = admin模块 / view/index/login.html
        // - 模板路径: application/admin/view/index/login.html
        // - 返回渲染后的 HTML 字符串
        return $this->fetch('admin@index/login');
    }

    /**
     * ============================================================
     * 退出登录
     * ============================================================
     *
     * 【访问路径】
     * admin.php/index/logout
     *
     * 【执行流程】
     * 1. 调用 Admin 模型的 logout() 方法清除 Session
     * 2. 重定向到登录页面
     *
     */
    public function logout()
    {
        // 调用 Admin 模型清除登录状态
        // logout() 会清除 Session 中的管理员信息
        $res = model('Admin')->logout();

        // 重定向到登录页面
        $this->redirect('index/login');
    }

    /**
     * ============================================================
     * 后台首页框架
     * ============================================================
     *
     * 【访问路径】
     * admin.php 或 admin.php/index/index
     *
     * 【页面结构】
     * ┌─────────────────────────────────────────────────────────┐
     * │  顶部导航栏 (Logo、用户信息、退出按钮)                    │
     * ├──────────────┬──────────────────────────────────────────┤
     * │              │                                          │
     * │   左侧菜单    │         右侧内容区 (iframe)               │
     * │              │         默认加载 welcome 页面             │
     * │              │                                          │
     * └──────────────┴──────────────────────────────────────────┘
     *
     * 【模板位置】
     * application/admin/view/index/index.html
     *
     */
    public function index()
    {
        $menus = @include MAC_ADMIN_COMM . 'auth.php';
        $version = config('version');

        foreach ($menus as $k1 => $v1) {
            foreach ($v1['sub'] as $k2 => $v2) {
                if ($v2['show'] == 1) {
                    if (strpos($v2['action'], 'javascript') !== false) {
                        $url = $v2['action'];
                    } else {
                        $url = url('admin/' . $v2['controller'] . '/' . $v2['action']);
                    }
                    if (!empty($v2['param'])) {
                        $url .= '?' . $v2['param'];
                    }
                    if ($this->check_auth($v2['controller'], $v2['action'])) {
                        $menus[$k1]['sub'][$k2]['url'] = $url;
                    } else {
                        unset($menus[$k1]['sub'][$k2]);
                    }
                } else {
                    unset($menus[$k1]['sub'][$k2]);
                }
            }

            if (empty($menus[$k1]['sub'])) {
                unset($menus[$k1]);
            }
        }

        // ============================================================
        // 【快捷菜单解析】将自定义菜单添加到左侧导航
        // ============================================================
        //
        // 【功能说明】
        // 读取快捷菜单配置，解析后添加到后台左侧导航栏
        // 显示在"系统"菜单组 ($menus[1]) 的子菜单中
        //
        // 【配置来源】
        // 1. 新版: application/extra/quickmenu.php (PHP数组格式)
        // 2. 旧版: application/data/config/quickmenu.txt (文本格式)
        //
        // 【配置格式】
        // 每行一个菜单项: 菜单名称,链接地址
        // 例如: 视频管理,vod/data
        //
        // 【显示位置】
        // 快捷菜单显示在"系统"菜单组的最下方
        // 位置索引从13开始 (13=标题, 14+=菜单项)
        //
        $quickmenu = config('quickmenu');
        if (empty($quickmenu)) {
            // 兼容旧版: 从文本文件读取
            $quickmenu = mac_read_file(APP_PATH . 'data/config/quickmenu.txt');
            // 按回车符分割为数组
            $quickmenu = explode(chr(13), $quickmenu);
        }

        // 如果有配置快捷菜单
        if (!empty($quickmenu)) {
            // --------------------------------------------------------
            // 添加快捷导航标题 (不可点击的分组标题)
            // --------------------------------------------------------
            // 位置: $menus[1]['sub'][13]
            // 'javascript:void(0);return false;' 使链接不可点击
            $menus[1]['sub'][13] = ['name' => lang('admin/index/quick_tit'), 'url' => 'javascript:void(0);return false;', 'controller' => '', 'action' => ''];

            // --------------------------------------------------------
            // 遍历配置，添加各个快捷菜单项
            // --------------------------------------------------------
            foreach ($quickmenu as $k => $v) {
                // 跳过空行
                if (empty($v)) {
                    continue;
                }

                // 解析菜单项: "菜单名称,链接地址"
                // $one[0] = 菜单名称
                // $one[1] = 链接地址
                $one = explode(',', trim($v));

                // --------------------------------------------------------
                // 链接类型判断与处理
                // --------------------------------------------------------
                // 根据链接前缀判断类型，决定是否需要转换URL
                //
                if (substr($one[1], 0, 4) == 'http' || substr($one[1], 0, 2) == '//') {
                    // 【远程地址】直接使用，无需处理
                    // 例如: http://www.baidu.com/ 或 //www.maccms.la/
                } elseif (substr($one[1], 0, 1) == '/') {
                    // 【插件文件/绝对路径】直接使用
                    // 例如: /application/xxxx.html
                } elseif (strpos($one[1], '###') !== false || strpos($one[1], 'javascript:') !== false) {
                    // 【分隔符或JavaScript】直接使用
                    // 例如: ### (分隔线) 或 javascript:alert('test')
                } else {
                    // 【系统模块】需要通过url()函数转换为完整URL
                    // 例如: art/data → admin.php/art/data.html
                    $one[1] = url($one[1]);
                }

                // 添加菜单项到导航
                // 位置从14开始递增
                $menus[1]['sub'][14 + $k] = ['name' => $one[0], 'url' => $one[1], 'controller' => '', 'action' => ''];
            }
        }
        $langs = glob('./application/lang/*.php');
        foreach ($langs as $k => &$v) {
            $v = str_replace(['./application/lang/','.php'],['',''],$v);
        }
        $config = config('maccms');
        $this->assign('config', $config);
        $this->assign('langs', $langs);
        $this->assign('version', $version);
        $this->assign('menus', $menus);
        $this->assign('title', lang('admin/index/title'));
        $ipQuery = new IpLocationQuery();
        $country_code = $ipQuery->queryProvince(mac_get_client_ip());
        if($country_code == ""){
            $country_code = "其它";
        }
        $this->assign('ip_location', $country_code);
        return $this->fetch('admin@index/index');
    }

    /**
     * ============================================================
     * 后台欢迎页/仪表盘 (Dashboard)
     * ============================================================
     *
     * 【访问路径】
     * admin.php/index/welcome
     *
     * 【功能说明】
     * 这是管理员登录后看到的第一个页面（仪表盘）
     * 显示系统概览、统计数据、服务器状态等信息
     *
     * 【页面结构】
     * ┌─────────────────────────────────────────────────────────────┐
     * │  顶部卡片区 (4个统计卡片)                                     │
     * │  ┌──────────┬──────────┬──────────┬──────────┐              │
     * │  │今日访客数 │ 今日收入  │ 上次登录  │ 登录IP   │              │
     * │  └──────────┴──────────┴──────────┴──────────┘              │
     * ├─────────────────────────────────────────────────────────────┤
     * │  三栏信息区                                                   │
     * │  ┌────────────┬────────────┬────────────┐                   │
     * │  │ 系统状态    │ 磁盘空间    │ 用户注册统计 │                   │
     * │  │ CPU/内存   │ 使用占比    │ 7日注册量   │                   │
     * │  └────────────┴────────────┴────────────┘                   │
     * ├─────────────────────────────────────────────────────────────┤
     * │  访问统计图表 (7日访问趋势)                                    │
     * ├─────────────────────────────────────────────────────────────┤
     * │  爬虫统计图表 (Google/Baidu/Sogou等)                          │
     * └─────────────────────────────────────────────────────────────┘
     *
     * 【数据来源】
     * - spider_data    : botlist() 方法，统计搜索引擎爬虫访问次数
     * - os_data        : get_system_status() 方法，获取服务器状态
     * - dashboard_data : getAdminDashboardData() 方法，获取业务统计
     *
     * 【模板变量】
     * ┌────────────────┬──────────────────────────────────────────┐
     * │ 变量名          │ 说明                                      │
     * ├────────────────┼──────────────────────────────────────────┤
     * │ $spider_data   │ 爬虫统计数据 (7天内各搜索引擎访问次数)      │
     * │ $os_data       │ 系统状态 (CPU/内存/磁盘使用率)             │
     * │ $version       │ 系统版本信息                              │
     * │ $update_sql    │ 是否有数据库更新文件                       │
     * │ $dashboard_data│ 仪表盘统计 (用户数/访问量/收入等)          │
     * │ $admin         │ 当前管理员信息 (登录时间/IP等)             │
     * └────────────────┴──────────────────────────────────────────┘
     *
     * 【模板位置】
     * application/admin/view_new/index/welcome.html
     *
     * 【前端技术】
     * - ApexCharts : 图表库，用于绑制访问趋势、爬虫统计图表
     * - TailwindCSS: 响应式布局
     * - Layui      : 日期选择器、表单组件
     *
     * @return string 渲染后的HTML页面
     */
    public function welcome()
    {
        // 获取系统版本信息
        // 配置位置: application/extra/version.php
        $version = config('version');

        // 检查是否存在数据库更新文件
        // 如果存在，页面会显示"请更新数据库"提示
        $update_sql = file_exists('./application/data/update/database.php');

        // ============================================================
        // 【数据准备】获取各类统计数据
        // ============================================================

        // 爬虫统计数据 - 统计7天内各搜索引擎的访问次数
        // 数据来源: runtime/log/bot/{日期}.txt
        $this->assign('spider_data', $this->botlist());

        // 服务器状态 - CPU/内存/磁盘使用情况
        // 支持 Windows/Linux/FreeBSD 系统
        $this->assign('os_data', $this->get_system_status());

        $this->assign('version', $version);
        $this->assign('update_sql', $update_sql);

        // 当前语言设置
        $this->assign('mac_lang', config('default_lang'));

        // 仪表盘业务数据 - 用户数、访问量、收入、趋势图数据
        $this->assign('dashboard_data', $this->getAdminDashboardData());

        // 当前管理员信息 (上次登录时间、IP等)
        // $this->_admin 在 Base 控制器中初始化
        $this->assign('admin', $this->_admin);

        $this->assign('title', lang('admin/index/welcome/title'));

        // 渲染欢迎页模板
        return $this->fetch('admin@index/welcome');
    }

    /**
     * ============================================================
     * 快捷菜单配置 (自定义菜单)
     * ============================================================
     *
     * 【功能说明】
     * 允许管理员自定义后台左侧导航栏的快捷菜单项
     * 可以添加常用功能入口、外部链接、分隔符等
     *
     * 【访问路径】
     * GET  admin.php/index/quickmenu  → 显示配置页面
     * POST admin.php/index/quickmenu  → 保存配置
     *
     * 【菜单配置格式】
     * 每行一个菜单项，格式: 菜单名称,链接地址
     *
     * 【支持的链接类型】
     * ┌─────────────────┬──────────────────────────────────────────┐
     * │ 类型             │ 示例                                      │
     * ├─────────────────┼──────────────────────────────────────────┤
     * │ 远程地址         │ 更新日志,//www.maccms.la/changelog.html   │
     * │ 远程地址(http)   │ 百度,http://www.baidu.com/                │
     * │ 插件文件         │ 自定义插件,/application/xxxx.html         │
     * │ 系统模块         │ 文章管理,art/data                         │
     * │ 分隔符           │ 分隔线,###                                │
     * │ JavaScript      │ 测试,javascript:alert('test')            │
     * └─────────────────┴──────────────────────────────────────────┘
     *
     * 【配置示例】
     * 视频管理,vod/data
     * 文章管理,art/data
     * ---分隔线---,###
     * 清除缓存,index/clear
     * 官方文档,//www.maccms.la/doc/
     *
     * 【存储位置】
     * 配置保存到: application/extra/quickmenu.php
     * 格式为PHP数组，通过 config('quickmenu') 读取
     *
     * 【兼容旧版】
     * 同时支持旧版配置文件: application/data/config/quickmenu.txt
     *
     * 【显示位置】
     * 配置的菜单项会显示在后台左侧导航栏的"系统"菜单组下方
     * 标题为"快捷导航" (参见 index() 方法中的菜单解析代码)
     *
     * 【模板位置】
     * application/admin/view_new/index/quickmenu.html
     *
     * 【相关文件】
     * - application/extra/quickmenu.php   : 配置存储文件
     * - application/admin/common/auth.php : 菜单结构定义
     * - application/lang/zh-cn.php        : 语言包(格式说明)
     *
     * @return mixed 配置页面HTML 或 JSON响应
     */
    public function quickmenu()
    {
        // ============================================================
        // 【POST请求】保存快捷菜单配置
        // ============================================================
        if (Request()->isPost()) {
            // 获取所有POST参数
            $param = input();

            // --------------------------------------------------------
            // Token验证 (防止CSRF攻击)
            // --------------------------------------------------------
            // 表单中包含隐藏字段 __token__，提交时验证有效性
            $validate = \think\Loader::validate('Token');
            if (!$validate->check($param)) {
                return $this->error($validate->getError());
            }

            // --------------------------------------------------------
            // 处理菜单配置文本
            // --------------------------------------------------------
            // 从文本框获取配置内容
            $quickmenu = input('post.quickmenu');

            // 去除换行符 LF (chr(10) = \n)
            // 保留回车符 CR (chr(13) = \r) 作为分隔符
            // Windows: \r\n  Unix: \n  Mac: \r
            // 这里统一使用 \r 作为行分隔符
            $quickmenu = str_replace(chr(10), '', $quickmenu);

            // 按回车符分割为数组，每行一个菜单项
            $menu_arr = explode(chr(13), $quickmenu);

            // --------------------------------------------------------
            // 保存配置到PHP文件
            // --------------------------------------------------------
            // mac_arr2file() 将数组写入PHP配置文件
            // 生成格式: return array('菜单1,链接1', '菜单2,链接2', ...);
            // 文件路径: application/extra/quickmenu.php
            $res = mac_arr2file(APP_PATH . 'extra/quickmenu.php', $menu_arr);

            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        // ============================================================
        // 【GET请求】显示快捷菜单配置页面
        // ============================================================
        else {
            // --------------------------------------------------------
            // 读取现有配置
            // --------------------------------------------------------
            // 优先从新版配置文件读取 (application/extra/quickmenu.php)
            $config_menu = config('quickmenu');

            if (empty($config_menu)) {
                // 兼容旧版: 从文本文件读取
                // 旧版配置路径: application/data/config/quickmenu.txt
                $quickmenu = mac_read_file(APP_PATH . 'data/config/quickmenu.txt');
            } else {
                // 新版配置: 将数组转换为文本
                // array_values() 重新索引数组
                // join(chr(13), ...) 用回车符连接为多行文本
                $quickmenu = array_values($config_menu);
                $quickmenu = join(chr(13), $quickmenu);
            }

            // 赋值到模板 (显示在textarea中)
            $this->assign('quickmenu', $quickmenu);
            $this->assign('title', lang('admin/index/quickmenu/title'));

            // 渲染配置页面
            return $this->fetch('admin@index/quickmenu');
        }
    }

    public function checkcache()
    {
        $res = 'no';
        $r = cache('cache_data');
        if ($r == '1') {
            $res = 'haved';
        }
        echo $res;
    }

    public function clear()
    {
        $res = $this->_cache_clear();
        //运行缓存
        if (!$res) {
            $this->error(lang('admin/index/clear_err'));
        }
        // 搜索缓存结果清理
        model('VodSearch')->clearOldResult(true);
        return $this->success(lang('admin/index/clear_ok'));
    }

    public function iframe()
    {
        $val = input('post.val', 0);
        if ($val != 0 && $val != 1) {
            return $this->error(lang('admin/index/clear_ok'));
        }
        if ($val == 1) {
            cookie('is_iframe', 'yes');
        } else {
            cookie('is_iframe', null);
        }
        return $this->success(lang('admin/index/iframe'));
    }

    public function unlocked()
    {
        $param = input();
        $password = $param['password'];

        if ($this->_admin['admin_pwd'] != md5($password)) {
            return $this->error(lang('admin/index/pass_err'));
        }

        return $this->success(lang('admin/index/unlock_ok'));
    }

    public function check_back_link()
    {
        $param = input();
        $res = mac_check_back_link($param['url']);
        return json($res);
    }

    public function select()
    {
        $param = input();
        $tpl = $param['tpl'];
        $tab = $param['tab'];
        $col = $param['col'];
        $ids = $param['ids'];
        $url = $param['url'];
        $val = $param['val'];

        $refresh = $param['refresh'];

        if (empty($tpl) || empty($tab) || empty($col) || empty($ids) || empty($url)) {
            return $this->error(lang('param_err'));
        }

        if (is_array($ids)) {
            $ids = join(',', $ids);
        }

        if (empty($refresh)) {
            $refresh = 'yes';
        }

        $url = url($url);
    $mid = 1;
    if ($tab == 'art') {
        $mid = 2;
    } elseif ($tab == 'actor') {
        $mid = 8;
    } elseif ($tab == 'website') {
        $mid = 11;
    } elseif ($tab == 'manga') {
        $mid = 12;
    }
        $this->assign('mid', $mid);

        if ($tpl == 'select_type') {
            $type_tree = model('Type')->getCache('type_tree');
            $this->assign('type_tree', $type_tree);
        } elseif ($tpl == 'select_level') {
            $level_list = [1, 2, 3, 4, 5, 6, 7, 8, 9];
            $this->assign('level_list', $level_list);
        }

        $this->assign('refresh', $refresh);
        $this->assign('url', $url);
        $this->assign('tab', $tab);
        $this->assign('col', $col);
        $this->assign('ids', $ids);
        $this->assign('val', $val);
        return $this->fetch('admin@public/' . $tpl);
    }

    /**
     * ============================================================
     * 获取服务器系统状态
     * ============================================================
     *
     * 【功能说明】
     * 获取服务器的 CPU、内存、磁盘使用情况
     * 支持 Windows、Linux、FreeBSD 等操作系统
     *
     * 【返回数据结构】
     * [
     *     'os_name'    => 'WINDOWS',              // 操作系统名称
     *     'cpu_usage'  => 25.5,                   // CPU 使用率 (%)
     *     'mem_usage'  => 60.2,                   // 内存使用率 (%)
     *     'mem_total'  => 16384,                  // 总内存 (MB)
     *     'mem_used'   => 9861.12,                // 已用内存 (MB)
     *     'disk_datas' => [                       // 磁盘信息
     *         'C' => [used_gb, total_gb, usage%],
     *         'D' => [used_gb, total_gb, usage%],
     *     ]
     * ]
     *
     * 【Windows 系统】
     * - CPU: 通过 WMI (COM) 或 wmic 命令获取
     * - 内存: 通过 WMI 或 wmic 命令获取
     * - 磁盘: 通过 disk_total_space/disk_free_space 函数获取
     *
     * 【Linux/FreeBSD 系统】
     * - CPU: 通过 top、/proc/stat、sysctl 获取
     * - 内存: 通过 free、/proc/meminfo、sysctl 获取
     * - 磁盘: 通过 disk_total_space/disk_free_space 函数获取
     *
     * 【AJAX 调用】
     * 前端页面通过 AJAX 定时调用此方法刷新数据:
     * GET admin.php/index/get_system_status.html
     *
     * @return array 系统状态数据
     */
    public function get_system_status()
    {
        // 判斷系統
        $os_name = PHP_OS;
        $os_data = [];
        $os_data['os_name'] = $os_name;

        if (strtoupper(substr($os_name, 0, 3)) === 'WIN') {
            // ============================================================
            // 【Windows 系统】
            // ============================================================
            $os_data['os_name'] = 'WINDOWS';

            // 获取所有磁盘信息 (C:, D:, E: ...)
            $os_data['disk_datas'] = $this->get_spec_disk('all');

            // 获取 CPU 使用率 (通过 WMI 或 wmic 命令)
            $os_data['cpu_usage'] = $this->getCpuUsage();

            // 获取内存使用情况
            $mem_arr = $this->getMemoryUsage();
            $os_data['mem_usage'] = $mem_arr['usage'];
            $os_data['mem_total'] = round($mem_arr['TotalVisibleMemorySize'] / 1024, 2);
            $os_data['mem_used'] = $os_data['mem_total'] - round($mem_arr['FreePhysicalMemory'] / 1024, 2);
        } else {
            // ============================================================
            // 【Linux/FreeBSD 系统】
            // ============================================================
            $os_data['os_name'] = strtoupper($os_name);

            // 获取磁盘信息 (根目录 /)
            $totalSpace = 0;
            $freeSpace = 0;

            if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
                $totalSpace = @disk_total_space('/');
                $freeSpace = @disk_free_space('/');
            }

            if ($totalSpace > 0) {
                $totalSpaceGB = round($totalSpace / (1024 * 1024 * 1024), 2);
                $freeSpaceGB = round($freeSpace / (1024 * 1024 * 1024), 2);
                $usedSpaceGB = round($totalSpaceGB - $freeSpaceGB, 2);

                $tmp_disk_data = [];
                $tmp_disk_data[0] = $usedSpaceGB;
                $tmp_disk_data[1] = $totalSpaceGB;
                $tmp_disk_data[2] = round(100 - ($freeSpaceGB / $totalSpaceGB * 100), 2);
                $os_data['disk_datas']['/'] = $tmp_disk_data;
            } else {
                $os_data['disk_datas']['/'] = [0, 0, 0];
            }

            // 获取内存信息 (尝试多种方法: free命令、sysctl、/proc/meminfo)
            $mem_arr = $this->get_unix_server_memory_usage();

            // 获取 CPU 使用率 (尝试多种方法: top、sysctl、/proc/stat)
            $os_data['cpu_usage'] = $this->get_unix_server_cpu_usage();

            $os_data['mem_usage'] = $mem_arr['usage'];
            $os_data['mem_used'] = $mem_arr['used'];
            $os_data['mem_total'] = $mem_arr['total'];
        }

        return $os_data;
    }

    private function byte_format($size, $dec = 2)
    {
        if ($size == 0) {
            return "0 B";
        }
        $a = array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
        $pos = 0;
        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, $dec);
    }

    private function get_disk_space($letter)
    {
        // 添加函数存在性检查
        if (!function_exists('disk_total_space') || !function_exists('disk_free_space')) {
            return [];
        }

        // 获取磁盘信息
        $diskct = 0;
        $disk = array();

        $diskz = 0; //磁盘总容量
        $diskk = 0; //磁盘剩余容量
        $is_disk = $letter . ':';
        if (@\disk_total_space($is_disk) != NULL) {
            $diskct++;
            $total_space = @\disk_total_space($is_disk);
            $free_space = @\disk_free_space($is_disk);
            
            // 转换为GB并保留两位小数
            $total_space_gb = round($total_space / (1024 * 1024 * 1024), 2);
            $free_space_gb = round($free_space / (1024 * 1024 * 1024), 2);
            
            $disk[$letter][0] = round($this->byte_format($free_space), 2);
            $disk[$letter][1] = round($this->byte_format($total_space), 2);
            
            if ($total_space > 0) {
                $disk[$letter][2] = round(100 - ($free_space_gb / $total_space_gb * 100), 2);
            } else {
                $disk[$letter][2] = 0;
            }
            
            $diskk += round($this->byte_format($free_space), 2);
            $diskz += round($this->byte_format($total_space), 2);
        }
        return $disk;
    }

    private function get_spec_disk($type = 'system')
    {
        $disk = array();
        switch ($type) {
            case 'system':
                //strrev(array_pop(explode(':',strrev(getenv_info('SystemRoot')))));//取得系统盘符
                $disk = $this->get_disk_space(strrev(array_pop(explode(':', strrev(getenv('SystemRoot'))))));
                break;
            case 'all':
                foreach (range('b', 'z') as $letter) {
                    $disk = array_merge($disk, $this->get_disk_space(strtoupper($letter)));
                }
                break;
            default:
                $disk = $this->get_disk_space($type);
                break;
        }
        return $disk;
    }

    private function isComAvailable() 
    {
        return extension_loaded('com_dotnet');
    }

    private function getCpuUsage()
    {
        if (!$this->isComAvailable()) {
            if (!function_exists('shell_exec')) {
                return 0;
            }
            try {
                $cmd = "wmic cpu get loadpercentage";
                $output = shell_exec($cmd);
                if ($output) {
                    preg_match('/\d+/', $output, $matches);
                    if (isset($matches[0])) {
                        return (float)$matches[0];
                    }
                }
            } catch (Exception $e) {
                return 0;
            }
            return 0;
        }
        
        try {
            if(class_exists('COM')){
                $wmi = new \COM('WinMgmts:\\\\.');
                $cpus = $wmi->ExecQuery('SELECT LoadPercentage FROM Win32_Processor');
            } else {
                return 0;
            }
            
            $cpu_load = 0;
            $cpu_count = 0;
            
            foreach ($cpus as $cpu) {
                $cpu_load += $cpu->LoadPercentage;
                $cpu_count++;
            }
            
            return $cpu_count > 0 ? round($cpu_load / $cpu_count, 2) : 0;
        } catch (Exception $e) {
            if (!function_exists('shell_exec')) {
                return 0;
            }
            try {
                $cmd = "wmic cpu get loadpercentage";
                $output = shell_exec($cmd);
                if ($output) {
                    preg_match('/\d+/', $output, $matches);
                    if (isset($matches[0])) {
                        return (float)$matches[0];
                    }
                }
            } catch (Exception $e) {
                return 0;
            }
            return 0;
        }
    }

    private function getMemoryUsage()
    {
        if (!$this->isComAvailable()) {
            if (!function_exists('shell_exec')) {
                return [
                    'TotalVisibleMemorySize' => 0,
                    'FreePhysicalMemory' => 0,
                    'usage' => 0
                ];
            }
            try {
                $cmd = "wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value";
                $output = shell_exec($cmd);
                if ($output) {
                    preg_match('/TotalVisibleMemorySize=(\d+)/i', $output, $total);
                    preg_match('/FreePhysicalMemory=(\d+)/i', $output, $free);
                    
                    if (isset($total[1]) && isset($free[1])) {
                        $total_mem = (float)$total[1];
                        $free_mem = (float)$free[1];
                        $used_mem = $total_mem - $free_mem;
                        
                        return [
                            'TotalVisibleMemorySize' => $total_mem,
                            'FreePhysicalMemory' => $free_mem,
                            'usage' => $total_mem > 0 ? round(($used_mem / $total_mem) * 100, 2) : 0
                        ];
                    }
                }
            } catch (Exception $e) {
                return [
                    'TotalVisibleMemorySize' => 0,
                    'FreePhysicalMemory' => 0,
                    'usage' => 0
                ];
            }
        }
        
        try {
            if(class_exists('COM')){
                $wmi = new \COM('WinMgmts:\\\\.');
                $os = $wmi->ExecQuery('SELECT TotalVisibleMemorySize,FreePhysicalMemory FROM Win32_OperatingSystem');
            } else {
                return [
                    'TotalVisibleMemorySize' => 0,
                    'FreePhysicalMemory' => 0,
                    'usage' => 0
                ];
            }
            
            foreach ($os as $item) {
                $total = $item->TotalVisibleMemorySize;
                $free = $item->FreePhysicalMemory;
                $used = $total - $free;
                
                return [
                    'TotalVisibleMemorySize' => $total,
                    'FreePhysicalMemory' => $free,
                    'usage' => $total > 0 ? round(($used / $total) * 100, 2) : 0
                ];
            }
        } catch (Exception $e) {
            if (!function_exists('shell_exec')) {
                return [
                    'TotalVisibleMemorySize' => 0,
                    'FreePhysicalMemory' => 0,
                    'usage' => 0
                ];
            }
            try {
                $cmd = "wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value";
                $output = shell_exec($cmd);
                if ($output) {
                    preg_match('/TotalVisibleMemorySize=(\d+)/i', $output, $total);
                    preg_match('/FreePhysicalMemory=(\d+)/i', $output, $free);
                    
                    if (isset($total[1]) && isset($free[1])) {
                        $total_mem = (float)$total[1];
                        $free_mem = (float)$free[1];
                        $used_mem = $total_mem - $free_mem;
                        
                        return [
                            'TotalVisibleMemorySize' => $total_mem,
                            'FreePhysicalMemory' => $free_mem,
                            'usage' => $total_mem > 0 ? round(($used_mem / $total_mem) * 100, 2) : 0
                        ];
                    }
                }
            } catch (Exception $e) {
                return [
                    'TotalVisibleMemorySize' => 0,
                    'FreePhysicalMemory' => 0,
                    'usage' => 0
                ];
            }
        }
    }

    private function get_unix_server_memory_usage()
    {
        // 首先尝试使用通用的 sysinfo 方法
        if (function_exists('sysinfo')) {
            $si = sysinfo();
            if ($si) {
                $total_mem = $si['totalram'] * $si['mem_unit'] / 1024 / 1024;
                $free_mem = $si['freeram'] * $si['mem_unit'] / 1024 / 1024;
                $used_mem = $total_mem - $free_mem;
                $usage = ($total_mem > 0) ? ($used_mem / $total_mem) * 100 : 0;
                
                return [
                    'total' => round($total_mem, 2),
                    'used' => round($used_mem, 2),
                    'usage' => round($usage, 2)
                ];
            }
        }

        // 尝试不同的内存信息获取方法
        $methods = [
            // Linux free 命令
            'free' => function() {
                if (!function_exists('shell_exec')) {
                    return null;
                }
                $free = shell_exec('free');
                if ($free) {
                    $free = (string)trim($free);
                    $free_arr = explode("\n", $free);
                    $mem = explode(" ", $free_arr[1]);
                    $mem = array_filter($mem);
                    $mem = array_merge($mem);
                    
                    if (count($mem) >= 3 && !empty($mem[1])) {
                        return [
                            'total' => round($mem[1] / 1024, 2),
                            'used' => round($mem[2] / 1024, 2),
                            'usage' => round($mem[2] / $mem[1] * 100, 2)
                        ];
                    }
                }
                return null;
            },
            
            // FreeBSD/Unix sysctl 命令
            'sysctl' => function() {
                if (!function_exists('shell_exec')) {
                    return null;
                }
                $sysctl = shell_exec('/sbin/sysctl -n hw.physmem hw.pagesize vm.stats.vm.v_free_count 2>/dev/null');
                if ($sysctl) {
                    $lines = explode("\n", trim($sysctl));
                    if (count($lines) >= 3 && !empty($lines[0])) {
                        $total_mem = $lines[0] / 1024 / 1024;
                        $page_size = $lines[1];
                        $free_pages = $lines[2];
                        $free_mem = ($free_pages * $page_size) / 1024 / 1024;
                        $used_mem = $total_mem - $free_mem;
                        $usage = $total_mem > 0 ? ($used_mem / $total_mem) * 100 : 0;
                        
                        return [
                            'total' => round($total_mem, 2),
                            'used' => round($used_mem, 2),
                            'usage' => round($usage, 2)
                        ];
                    }
                }
                return null;
            },
            
            // /proc/meminfo 文件读取
            'proc' => function() {
                if (!is_readable('/proc/meminfo')) {
                    return null;
                }
                $meminfo = file_get_contents('/proc/meminfo');
                if ($meminfo) {
                    preg_match('/MemTotal:\s+(\d+)/i', $meminfo, $total);
                    preg_match('/MemFree:\s+(\d+)/i', $meminfo, $free);
                    preg_match('/Cached:\s+(\d+)/i', $meminfo, $cached);
                    preg_match('/Buffers:\s+(\d+)/i', $meminfo, $buffers);
                    
                    if (isset($total[1]) && isset($free[1])) {
                        $total_mem = $total[1] / 1024;
                        $free_mem = ($free[1] + (isset($cached[1]) ? $cached[1] : 0) + (isset($buffers[1]) ? $buffers[1] : 0)) / 1024;
                        $used_mem = $total_mem - $free_mem;
                        $usage = ($total_mem > 0) ? ($used_mem / $total_mem) * 100 : 0;
                        
                        return [
                            'total' => round($total_mem, 2),
                            'used' => round($used_mem, 2),
                            'usage' => round($usage, 2)
                        ];
                    }
                }
                return null;
            }
        ];
        
        // 依次尝试各种方法
        foreach ($methods as $method) {
            $result = $method();
            if ($result !== null) {
                return $result;
            }
        }
        
        // 如果所有方法都失败，返回默认值
        return [
            'total' => 0,
            'used' => 0,
            'usage' => 0
        ];
    }

    private function get_unix_server_cpu_usage()
    {
        // 首先尝试使用通用的方法
        $methods = [
            // top 命令 (Linux)
            'top_linux' => function() {
                if (!function_exists('shell_exec')) {
                    return null;
                }
                $cpu_load = shell_exec("top -bn1 | grep 'Cpu(s)' 2>/dev/null");
                if (!empty($cpu_load)) {
                    if (preg_match('/(\d+[.,]\d+).*?us/', $cpu_load, $matches)) {
                        return round((float)str_replace(',', '.', $matches[1]), 2);
                    }
                }
                return null;
            },
            
            // top 命令 (FreeBSD)
            'top_bsd' => function() {
                if (!function_exists('shell_exec')) {
                    return null;
                }
                $cpu_load = shell_exec("top -d2 -n1 | grep 'CPU:' 2>/dev/null");
                if (!empty($cpu_load)) {
                    if (preg_match('/(\d+\.\d+)%\s+user/', $cpu_load, $matches)) {
                        return round((float)$matches[1], 2);
                    }
                }
                return null;
            },
            
            // sysctl (FreeBSD/Unix)
            'sysctl' => function() {
                if (!function_exists('shell_exec')) {
                    return null;
                }
                $cpu_load = shell_exec('/sbin/sysctl -n kern.cp_time 2>/dev/null');
                if (!empty($cpu_load)) {
                    $times = explode(" ", trim($cpu_load));
                    if (count($times) >= 5) {
                        $total = array_sum($times);
                        $idle = $times[4];
                        return $total > 0 ? round(100 - ($idle * 100 / $total), 2) : 0;
                    }
                }
                return null;
            },
            
            // /proc/stat 文件读取
            'proc' => function() {
                if (is_readable('/proc/stat')) {
                    $stats1 = file_get_contents('/proc/stat');
                    usleep(100000); // 等待100ms
                    $stats2 = file_get_contents('/proc/stat');
                    
                    if ($stats1 && $stats2) {
                        $stats_arr1 = explode(' ', trim(explode("\n", $stats1)[0]));
                        $stats_arr2 = explode(' ', trim(explode("\n", $stats2)[0]));
                        
                        if (count($stats_arr1) >= 5 && count($stats_arr2) >= 5) {
                            $cpu1 = array_sum(array_slice($stats_arr1, 1));
                            $cpu2 = array_sum(array_slice($stats_arr2, 1));
                            $idle1 = $stats_arr1[4];
                            $idle2 = $stats_arr2[4];
                            
                            $diff_cpu = $cpu2 - $cpu1;
                            $diff_idle = $idle2 - $idle1;
                            
                            if ($diff_cpu > 0) {
                                return round(100 * (1 - $diff_idle / $diff_cpu), 2);
                            }
                        }
                    }
                }
                return null;
            }
        ];
        
        // 依次尝试各种方法
        foreach ($methods as $method) {
            $result = $method();
            if ($result !== null) {
                return $result;
            }
        }
        
        return 0;
    }

    private function _getServerLoadLinuxData()
    {
        if (is_readable("/proc/stat")) {
            $stats = @file_get_contents("/proc/stat");

            if ($stats !== false) {
                // Remove double spaces to make it easier to extract values with explode()
                $stats = preg_replace("/[[:blank:]]+/", " ", $stats);

                // Separate lines
                $stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
                $stats = explode("\n", $stats);

                // Separate values and find line for main CPU load
                foreach ($stats as $statLine) {
                    $statLineData = explode(" ", trim($statLine));

                    // Found!
                    if (
                        (count($statLineData) >= 5) &&
                        ($statLineData[0] == "cpu")
                    ) {
                        return array(
                            $statLineData[1],
                            $statLineData[2],
                            $statLineData[3],
                            $statLineData[4],
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * ============================================================
     * 获取仪表盘统计数据
     * ============================================================
     *
     * 【功能说明】
     * 获取欢迎页/仪表盘所需的各类业务统计数据
     * 包括用户统计、访问统计、收入统计、趋势图数据等
     *
     * 【返回数据结构】
     *
     * ┌──────────────────────────┬────────────────────────────────┐
     * │ 字段名                    │ 说明                            │
     * ├──────────────────────────┼────────────────────────────────┤
     * │ user_count               │ 注册用户总数                     │
     * │ user_active_count        │ 已审核(激活)用户数               │
     * │ today_visit_count        │ 今日访客数                       │
     * │ today_money_get          │ 今日收入金额                     │
     * │ seven_day_visit_day      │ 近7天访问日期数组 (图表X轴)       │
     * │ seven_day_visit_count    │ 近7天每日访问量数组 (图表Y轴)     │
     * │ raise_visit_user_today   │ 今日访问量涨跌幅 (%)             │
     * │ seven_day_reg_day        │ 近7天注册日期数组 (图表X轴)       │
     * │ seven_day_reg_count      │ 近7天每日注册量数组 (图表Y轴)     │
     * │ seven_day_reg_total_count│ 近7天注册总量                    │
     * │ raise_reg_user_today     │ 今日注册量涨跌幅 (%)             │
     * └──────────────────────────┴────────────────────────────────┘
     *
     * 【数据来源】
     * - 用户数据: mac_user 表
     * - 访问数据: mac_visit 表
     * - 收入数据: mac_order 表 (订单状态=1 已完成)
     *
     * 【SQL查询说明】
     * 使用 FROM_UNIXTIME() 将时间戳转换为日期格式
     * 604800 = 7天 × 24小时 × 60分钟 × 60秒
     *
     * @return array 仪表盘统计数据
     */
    private function getAdminDashboardData()
    {
        $result = [];

        // ============================================================
        // 【用户统计】
        // ============================================================

        // 已注册总用户数量 (所有用户，不论状态)
        $result['user_count'] = model('User')->count();
        // 格式化为千分位显示 (如: 1,234)
        $result['user_count'] = number_format($result['user_count'], 0, '.', ',');

        // 已审核(激活)用户数量 (user_status=1 表示正常状态)
        $result['user_active_count'] = model('User')->where('user_status', 1)->count();
        $result['user_active_count'] = number_format($result['user_active_count'], 0, '.', ',');

        // ============================================================
        // 【今日统计】
        // ============================================================

        // 获取今日时间范围 (00:00:00 - 23:59:59)
        $today_start = strtotime(date('Y-m-d 00:00:00'));  // 今日0点时间戳
        $today_end = $today_start + 86399;                  // 今日23:59:59 (86400秒 - 1)

        // 本日来客量 (访问记录表中今日的记录数)
        $result['today_visit_count'] = model('Visit')->where('visit_time', 'between', $today_start . ',' . $today_end)->count();
        $result['today_visit_count'] = number_format($result['today_visit_count'], 0, '.', ',');

        // 本日总收入 (订单表中今日已完成订单的金额总和)
        // order_status=1 表示订单已完成/已支付
        $result['today_money_get'] = model('Order')->where('order_time', 'between', $today_start . ',' . $today_end)->where('order_status', 1)->sum('order_price');
        // 格式化为金额显示 (保留2位小数，千分位分隔)
        $result['today_money_get'] = number_format($result['today_money_get'], 2, '.', ',');

        // ============================================================
        // 【近7天访问趋势】
        // ============================================================

        // SQL查询: 统计7天内每日访问量
        // FROM_UNIXTIME(visit_time, '%Y-%c-%d') - 将Unix时间戳转为日期格式
        // unix_timestamp(CURDATE())-604800 - 7天前的时间戳
        // GROUP BY days - 按日期分组统计
        $tmp_arr = Db::query("select FROM_UNIXTIME(visit_time, '%Y-%c-%d' ) days,count(*) count from (SELECT * from ".config('database.prefix')."visit where visit_time >= (unix_timestamp(CURDATE())-604800)) as temp group by days");

        // 初始化图表数据数组
        $result['seven_day_visit_day'] = [];    // X轴: 日期
        $result['seven_day_visit_count'] = [];  // Y轴: 访问量

        // 计算今日访问量较昨日的涨跌幅
        // 公式: (今日量 - 昨日量) / 昨日量 × 100%
        $result['raise_visit_user_today'] = 0;
        if (is_array($tmp_arr) && count($tmp_arr) > 1 && (strtotime(end($tmp_arr)['days']) == strtotime(date('Y-m-d')))) {
            // 昨日访问量 (倒数第二条记录)
            $yesterday_visit_count = $tmp_arr[count($tmp_arr) - 2]['count'];
            // 今日访问量 (最后一条记录)
            $lastday_visit_count = end($tmp_arr)['count'];
            if ($yesterday_visit_count != 0) {
                // 计算涨跌幅百分比
                $result['raise_visit_user_today'] = number_format((($lastday_visit_count - $yesterday_visit_count) / $yesterday_visit_count) * 100, 2, '.', ',');
            } else {
                $result['raise_visit_user_today'] = 0;
            }
        }

        // 将查询结果转换为图表所需的数组格式
        foreach ($tmp_arr as $data) {
            array_push($result['seven_day_visit_day'], $data['days']);      // 日期
            array_push($result['seven_day_visit_count'], $data['count']);   // 访问量
        }

        // 近七日用户访问总量
        $result['seven_day_visit_total_count'] = 0;
        foreach ($result['seven_day_visit_data'] as $k => $value) {
            $result['seven_day_visit_total_count'] = $result['seven_day_visit_total_count'] + $value['count'];
        }
        $result['seven_day_visit_total_count'] = number_format($result['seven_day_visit_total_count'], 0, '.', ',');

        // ============================================================
        // 【近7天注册趋势】
        // ============================================================

        // SQL查询: 统计7天内每日注册量
        // user_reg_time: 用户注册时间字段
        $result['seven_day_reg_data'] = Db::query("select FROM_UNIXTIME(user_reg_time, '%Y-%c-%d' ) days,count(*) count from (SELECT * from ".config('database.prefix')."user where user_reg_time >= (unix_timestamp(CURDATE())-604800)) as tmp group by days");

        // 初始化图表数据数组
        $result['seven_day_reg_total_count'] = 0;  // 7天注册总量
        $result['seven_day_reg_day'] = [];         // X轴: 日期
        $result['seven_day_reg_count'] = [];       // Y轴: 注册量

        // 遍历查询结果，填充图表数据
        foreach ($result['seven_day_reg_data'] as $k => $value) {
            array_push($result['seven_day_reg_day'], $value['days']);       // 日期
            array_push($result['seven_day_reg_count'], $value['count']);    // 注册量
            // 累加计算7天总量
            $result['seven_day_reg_total_count'] = $result['seven_day_reg_total_count'] + $value['count'];
        }

        // 计算今日注册量较昨日的涨跌幅
        $result['raise_reg_user_today'] = 0;
        if (is_array($result['seven_day_reg_data']) && count($result['seven_day_reg_data']) > 1 && (strtotime(end($result['seven_day_reg_data'])['days']) == strtotime(date('Y-m-d')))) {
            // 昨日注册量
            $yesterday_reg_count = $result['seven_day_reg_data'][count($result['seven_day_reg_data']) - 2]['count'];
            // 今日注册量
            $lastday_reg_count = end($result['seven_day_reg_data'])['count'];
            if ($yesterday_reg_count != 0) {
                // 计算涨跌幅百分比
                $result['raise_reg_user_today'] = number_format((($lastday_reg_count - $yesterday_reg_count) / $yesterday_reg_count) * 100, 2, '.', ',');
            } else {
                $result['raise_reg_user_today'] = 0;
            }
        }

        $result['seven_day_reg_total_count'] = number_format($result['seven_day_reg_total_count'], 0, '.', ',');

        return $result;
    }

    /**
     * ============================================================
     * 自定义日期范围访问统计 (AJAX接口)
     * ============================================================
     *
     * 【功能说明】
     * 根据用户选择的日期范围，统计每日访问量
     * 供前端 ApexCharts 图表动态更新使用
     *
     * 【请求方式】
     * POST admin.php/index/rangeDateDailyVisit
     *
     * 【请求参数】
     * - startDate: 开始日期 (格式: Y-m-d)
     * - endDate:   结束日期 (格式: Y-m-d)
     *
     * 【返回数据】
     * JSON格式:
     * {
     *     "days":  ["2023-11-01", "2023-11-02", ...],  // 日期数组 (X轴)
     *     "count": [120, 150, 88, ...],                // 访问量数组 (Y轴)
     *     "sum":   1234                                // 区间访问总量
     * }
     *
     * 【前端调用示例】
     * $.post('admin.php/index/rangeDateDailyVisit', {
     *     startDate: '2023-11-01',
     *     endDate: '2023-11-07'
     * }, function(data) {
     *     // 更新图表
     *     chart.updateSeries([{ data: data.count }]);
     * });
     *
     * @return string JSON格式的访问统计数据
     */
    public function rangeDateDailyVisit()
    {
        // SQL查询: 统计指定日期范围内每日访问量
        // strtotime() 将日期字符串转为Unix时间戳
        $range_daily_visit_data = Db::query("select FROM_UNIXTIME(visit_time, '%Y-%c-%d' ) days,count(*) count from (SELECT * from ".config('database.prefix')."visit where visit_time >= " . strtotime($_POST['startDate']) . "&&  visit_time <= " . strtotime($_POST['endDate']) . " ) as temp group by days");

        $result = [];
        $range_visit_day = [];    // 日期数组
        $range_visit_count = [];  // 访问量数组
        $range_visit_sum = 0;     // 访问总量

        // 遍历查询结果，转换为前端需要的数组格式
        foreach ($range_daily_visit_data as $data) {
            $range_visit_sum = $range_visit_sum + $data['count'];
            array_push($range_visit_day, $data['days']);
            array_push($range_visit_count, $data['count']);
        }

        $result['days'] = $range_visit_day;   // X轴数据
        $result['count'] = $range_visit_count; // Y轴数据
        $result['sum'] = $range_visit_sum;     // 汇总数据

        return json_encode($result);
    }

    /**
     * ============================================================
     * 搜索引擎爬虫统计
     * ============================================================
     *
     * 【功能说明】
     * 统计近7天各搜索引擎爬虫的访问次数
     * 用于监控网站SEO情况和搜索引擎收录频率
     *
     * 【数据来源】
     * 爬虫日志文件: runtime/log/bot/{日期}.txt
     *
     * 【日志记录机制】
     * 1. 在 application/common/behavior/Begin.php 中检测爬虫
     * 2. 检测 User-Agent 中的爬虫标识 (Googlebot, Baiduspider等)
     * 3. 将爬虫访问记录写入对应日期的日志文件
     *
     * 【统计的爬虫类型】
     * ┌──────────────┬────────────────────────────────────┐
     * │ 爬虫名称      │ User-Agent 标识                    │
     * ├──────────────┼────────────────────────────────────┤
     * │ Google       │ Googlebot                          │
     * │ Baidu        │ Baiduspider                        │
     * │ Sogou        │ Sogou web spider                   │
     * │ SOSO         │ Sosospider (腾讯搜搜，已停止)       │
     * │ Yahoo        │ Yahoo! Slurp                       │
     * │ MSN/Bing     │ MSNBot, msnbot                     │
     * │ Sohu         │ Sohu-Search (搜狐)                  │
     * │ Yodao        │ YodaoBot (有道)                     │
     * │ Twiceler     │ Twiceler (Cuil搜索引擎爬虫)         │
     * │ Alexa        │ ia_archiver (Alexa排名爬虫)         │
     * └──────────────┴────────────────────────────────────┘
     *
     * 【返回数据结构】
     * [
     *     'Google' => [
     *         'key'    => ['2023-11-28', '2023-11-27', ...],  // 日期数组
     *         'values' => [120, 85, 90, ...],                 // 访问次数
     *     ],
     *     'Baidu' => [...],
     *     ...
     * ]
     *
     * 【AJAX 调用】
     * 支持按分类获取单个爬虫数据:
     * POST admin.php/index/botlist
     * 参数: category=Google
     *
     * 【SEO监控意义】
     * - 爬虫访问频繁 → 网站活跃度高，收录更新快
     * - 爬虫访问减少 → 可能网站有问题或内容更新慢
     * - 对比不同爬虫 → 了解各搜索引擎的收录情况
     *
     * @return array 爬虫统计数据
     */
    public function botlist()
    {
        $day_arr = [];

        // 生成近7天的日期数组 (从今天开始倒推)
        // 注释写的是10天，实际是7天
        for ($i = 0; $i < 7; $i++) {
            // 60*60*24 = 86400秒 = 1天
            $day_arr[$i] = date('Y-m-d', time() - $i * 60 * 60 * 24);
        }

        // 初始化各爬虫统计数组
        $google_arr = [];      // Google 爬虫
        $baidu_arr = [];       // 百度爬虫
        $sogou_arr = [];       // 搜狗爬虫
        $soso_arr = [];        // 搜搜爬虫 (已停止)
        $yahoo_arr = [];       // 雅虎爬虫
        $msn_arr = [];         // MSN/Bing 爬虫
        $msn_bot_arr = [];     // msnbot
        $sohu_arr = [];        // 搜狐爬虫
        $yodao_arr = [];       // 有道爬虫
        $twiceler_arr = [];    // Twiceler 爬虫
        $alexa_arr = [];       // Alexa 排名爬虫
        $bot_list = [];        // 最终结果

        // 遍历每天的日志文件，统计各爬虫访问次数
        foreach ($day_arr as $day_vo) {
            // 日志文件路径: runtime/log/bot/2023-11-28.txt
            if (file_exists(ROOT_PATH . 'runtime/log/bot/' . $day_vo . '.txt')) {
                // 读取日志文件内容
                $bot_content = file_get_contents(ROOT_PATH . 'runtime/log/bot/' . $day_vo . '.txt');
            } else {
                $bot_content = '';
            }

            // substr_count() 统计字符串出现次数
            // 通过统计爬虫标识在日志中出现的次数来计算访问量
            $google_arr[$day_vo] = substr_count($bot_content, 'Google');
            $baidu_arr[$day_vo] = substr_count($bot_content, 'Baidu');
            $sogou_arr[$day_vo] = substr_count($bot_content, 'Sogou');
            $soso_arr[$day_vo] = substr_count($bot_content, 'SOSO');
            $yahoo_arr[$day_vo] = substr_count($bot_content, 'Yahoo');
            $msn_arr[$day_vo] = substr_count($bot_content, 'MSN');
            $msn_bot_arr[$day_vo] = substr_count($bot_content, 'msnbot');
            $sohu_arr[$day_vo] = substr_count($bot_content, 'Sohu');
            $yodao_arr[$day_vo] = substr_count($bot_content, 'Yodao');
            $twiceler_arr[$day_vo] = substr_count($bot_content, 'Twiceler');
            $alexa_arr[$day_vo] = substr_count($bot_content, 'Alexa');
        }

        // 将统计数据转换为前端图表需要的格式
        // key/keys: 日期数组 (X轴)
        // values: 访问次数数组 (Y轴)
        $bot_list['Google']['key'] = array_keys($google_arr);
        $bot_list['Google']['values'] = array_values($google_arr);
        $bot_list['Baidu']['keys'] = array_keys($baidu_arr);
        $bot_list['Baidu']['values'] = array_values($baidu_arr);
        $bot_list['Sogou']['keys'] = array_keys($sogou_arr);
        $bot_list['Sogou']['values'] = array_values($sogou_arr);
        $bot_list['SOSO']['keys'] = array_keys($soso_arr);
        $bot_list['SOSO']['values'] = array_values($soso_arr);
        $bot_list['Yahoo']['keys'] = array_keys($yahoo_arr);
        $bot_list['Yahoo']['values'] = array_values($yahoo_arr);
        $bot_list['MSN']['keys'] = array_keys($msn_arr);
        $bot_list['MSN']['values'] = array_values($msn_arr);
        $bot_list['msnbot']['keys'] = array_keys($msn_bot_arr);
        $bot_list['msnbot']['values'] = array_values($msn_bot_arr);
        $bot_list['Sohu']['keys'] = array_keys($sohu_arr);
        $bot_list['Sohu']['values'] = array_values($sohu_arr);
        $bot_list['Yodao']['keys'] = array_keys($yodao_arr);
        $bot_list['Yodao']['values'] = array_values($yodao_arr);
        $bot_list['Twiceler']['keys'] = array_keys($twiceler_arr);
        $bot_list['Twiceler']['values'] = array_values($twiceler_arr);
        $bot_list['Alexa']['keys'] = array_keys($alexa_arr);
        $bot_list['Alexa']['values'] = array_values($alexa_arr);

        // 支持AJAX请求按分类返回单个爬虫数据
        if (!empty($_POST['category'])) {
            return $bot_list[$_POST['category']];
        } else {
            // 返回所有爬虫统计数据
            return $bot_list;
        }
    }

    /**
     * ============================================================
     * 爬虫日志详情查看
     * ============================================================
     *
     * 【功能说明】
     * 查看指定日期的爬虫访问详细日志
     * 显示最近20条爬虫访问记录
     *
     * 【请求方式】
     * POST admin.php/index/botlog
     *
     * 【请求参数】
     * - data: 日期 (格式: Y-m-d)
     *
     * 【日志格式】
     * 每行一条记录，包含: 时间、IP、爬虫标识、访问URL
     *
     * 【模板位置】
     * application/admin/view/others/botlog.html
     *
     * @return string 渲染后的日志列表HTML
     */
    public function botlog()
    {
        $parm = input();
        $data = $parm['data'];  // 日期参数

        // 读取日志文件
        $bot_content = file_get_contents(ROOT_PATH . 'runtime/log/bot/' . $data . '.txt');

        // 处理日志内容:
        // 1. 按换行符分割为数组
        // 2. 反转数组 (最新的记录在前)
        // 3. 取前20条记录
        $bot_list = array_slice(array_reverse(explode("\r\n", trim($bot_content))), 0, 20);

        $this->assign('bot_list', $bot_list);

        return $this->fetch('admin@others/botlog');
    }
}