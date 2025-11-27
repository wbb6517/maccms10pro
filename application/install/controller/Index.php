<?php
/**
 * ============================================================
 * 【安装模块控制器 - 页面数据来源说明】
 * ============================================================
 *
 * 文件位置: application/install/controller/Index.php
 *
 * 当浏览器访问 http://localhost:8080/install.php 时:
 *
 * 路由解析过程:
 * 1. install.php 定义 BIND_MODULE = 'install'
 * 2. ThinkPHP 根据默认规则访问 Index 控制器的 index 方法
 * 3. URL: /install.php → install/Index/index
 * 4. URL: /install.php?step=2 → install/Index/index($step=2)
 *
 * 类自动加载:
 * - 命名空间: app\install\controller
 * - 映射目录: application/install/controller/
 * - PSR-4规则: app\ → application\
 *
 * 数据流向:
 * ┌──────────────────────────────────────────────────────────┐
 * │ Index::index()                                           │
 * │    ↓                                                     │
 * │ 1. 扫描语言包: glob('./application/lang/*.php')          │
 * │    结果: ['zh-cn', 'en-us', ...]                        │
 * │    ↓                                                     │
 * │ 2. $this->assign('langs', $langs) 传递给视图            │
 * │    ↓                                                     │
 * │ 3. $this->fetch('install@/index/index') 渲染模板        │
 * │    模板路径: application/install/view/index/index.html  │
 * │    ↓                                                     │
 * │ 4. 模板中 {volist name="langs"} 循环显示语言选项        │
 * │    ↓                                                     │
 * │ 5. {:lang('install/xxx')} 从语言包获取翻译文本          │
 * │    语言包: application/lang/zh-cn.php                   │
 * └──────────────────────────────────────────────────────────┘
 * ============================================================
 */
namespace app\install\controller;
use think\Controller;
use think\Db;
use think\Lang;
use think\Request;

/**
 * 安装向导控制器
 *
 * 继承关系: Index → Controller → think\Controller
 * Controller 基类提供: assign(), fetch(), error(), success() 等方法
 */
class Index extends Controller
{

    /**
     * 构造方法 - 安全检查
     *
     * @access public
     * @param Request $request Request 对象 (依赖注入)
     *
     * 说明: 只有通过 install.php 入口才能访问此控制器
     * 防止通过 index.php?m=install 等方式绕过安装检查
     */
    public function __construct(Request $request = null)
    {
        // 仅安装脚本可进入
        if (!defined('BIND_MODULE') || BIND_MODULE != 'install') {
            header('HTTP/1.1 403 Forbidden');
            exit();
        }
        parent::__construct($request);
    }

    /**
     * ★★★ 安装首页 - 当前浏览器显示的页面 ★★★
     *
     * URL: /install.php 或 /install.php?step=0
     *
     * @param int $step 安装步骤 (0=首页, 2=环境检测, 3=配置, 4=执行, 5=完成)
     * @return mixed 返回渲染后的 HTML
     *
     * 【数据来源详解】
     *
     * 页面上显示的数据:
     * ┌─────────────────────────────────────────────────────────┐
     * │ "感谢您选择! MacCMS系统建站"                             │
     * │   ↑ 来自: lang('install/header') + lang('install/header1')│
     * │   ↑ 文件: application/lang/zh-cn.php                    │
     * │                                                         │
     * │ "苹果CMS用户协议..."                                     │
     * │   ↑ 来自: lang('install/user_agreement')                │
     * │                                                         │
     * │ 语言选择下拉框 [zh-cn ▼]                                 │
     * │   ↑ 来自: $langs 变量 (本方法第28-31行扫描得到)          │
     * │   ↑ 数据: glob('./application/lang/*.php') 的文件名     │
     * │                                                         │
     * │ [同意协议并安装系统] 按钮                                 │
     * │   ↑ 来自: lang('install/user_agreement_agree')          │
     * │   ↑ 链接: ?step=2 → 进入环境检测步骤                    │
     * └─────────────────────────────────────────────────────────┘
     */
    public function index($step = 0)
    {
        /**
         * 【数据准备第1步】扫描可用的语言包
         *
         * glob() 函数扫描 application/lang/ 目录下所有 .php 文件
         * 例如: ['./application/lang/zh-cn.php', './application/lang/en-us.php']
         * 处理后: ['zh-cn', 'en-us']
         */
        $langs = glob('./application/lang/*.php');
        foreach ($langs as $k => &$v) {
            // 移除路径和扩展名，只保留语言代码
            // './application/lang/zh-cn.php' → 'zh-cn'
            $v = str_replace(['./application/lang/','.php'],['',''],$v);
        }

        /**
         * 【数据准备第2步】将语言列表传递给视图模板
         *
         * assign() 方法将变量注册到模板引擎
         * 模板中可以通过 {$langs} 或 {volist name="langs"} 访问
         */
        $this->assign('langs', $langs);

        /**
         * 【数据准备第3步】加载当前选择的语言包
         */
        if(in_array(session('lang'),$langs)){
            $lang = Lang::range(session('lang'));
            // 加载语言包文件，如: application/lang/zh-cn.php
            Lang::load('./application/lang/'.$lang.'.php',$lang);
        }

        /**
         * 【路由分发】根据 step 参数决定显示哪个安装步骤
         */
        switch ($step) {
            case 2:
                // 第2步: 环境检测
                session('install_error', false);
                return self::step2();
                break;
            case 3:
                // 第3步: 数据库配置
                if (session('install_error')) {
                    return $this->error(lang('install/environment_failed'));
                }
                return self::step3();
                break;
            case 4:
                // 第4步: 执行安装
                if (session('install_error')) {
                    return $this->error(lang('install/environment_failed'));
                }
                return self::step4();
                break;
            case 5:
                // 第5步: 安装完成
                if (session('install_error')) {
                    return $this->error(lang('install/init_err'));
                }
                return self::step5();
                break;
            default:
                /**
                 * 【默认: 显示安装首页 (当前页面)】
                 *
                 * step=0 或无参数时执行此分支
                 */
                $param = input(); // 获取所有 GET/POST 参数

                // 设置语言 (默认 zh-cn)
                if(!in_array($param['lang'],$langs)) {
                    $param['lang'] = 'zh-cn';
                }
                $lang = Lang::range($param['lang']);
                Lang::load('./application/lang/'.$lang.'.php',$lang);
                session('lang',$param['lang']);
                $this->assign('lang',$param['lang']);

                session('install_error', false);

                /**
                 * ★★★ 渲染视图模板 ★★★
                 *
                 * fetch() 方法加载并渲染模板:
                 * - 'install@/index/index' 解析为:
                 *   模块@/控制器/操作 → application/install/view/index/index.html
                 *
                 * 模板引擎会:
                 * 1. 读取 index.html 文件
                 * 2. 解析 {$langs}, {:lang('xxx')} 等标签
                 * 3. 替换变量为实际值
                 * 4. 返回生成的 HTML 字符串
                 */
                return $this->fetch('install@/index/index');
                break;
        }
    }

    /**
     * ============================================================
     * 【第二步：环境检测】
     * ============================================================
     *
     * URL: /install.php?step=2
     *
     * 功能说明:
     * 检测服务器环境是否满足系统运行要求，包括:
     * 1. 运行环境 - 操作系统类型、PHP版本
     * 2. 目录权限 - 关键目录和文件的读写权限
     * 3. 函数扩展 - 必需的PHP扩展和函数
     *
     * 数据流向:
     * ┌─────────────────────────────────────────────────────────┐
     * │ step2()                                                 │
     * │    ↓                                                    │
     * │ checkNnv()  → $data['env']  环境检测结果               │
     * │ checkDir()  → $data['dir']  目录权限检测结果           │
     * │ checkFunc() → $data['func'] 函数扩展检测结果           │
     * │    ↓                                                    │
     * │ assign('data', $data) 传递给视图                       │
     * │    ↓                                                    │
     * │ fetch('install@index/step2') 渲染模板                  │
     * │    模板路径: application/install/view/index/step2.html │
     * └─────────────────────────────────────────────────────────┘
     *
     * @return mixed 返回渲染后的 HTML
     */
    private function step2()
    {
        // 初始化数据数组
        $data = [];

        // 【环境检测】操作系统、PHP版本
        $data['env'] = self::checkNnv();

        // 【目录权限检测】关键目录和文件的读写权限
        $data['dir'] = self::checkDir();

        // 【函数扩展检测】必需的PHP扩展和函数
        $data['func'] = self::checkFunc();

        // 将检测结果传递给视图模板
        $this->assign('data', $data);

        // 渲染并返回 step2.html 模板
        return $this->fetch('install@index/step2');
    }
    
    /**
     * ============================================================
     * 【第三步：数据库配置页面】
     * ============================================================
     *
     * URL: /install.php?step=3
     *
     * 功能说明:
     * 显示数据库配置表单，让用户填写:
     * - 数据库服务器地址、端口
     * - 数据库名称、账号、密码
     * - 数据库表前缀
     * - 管理员账号密码
     *
     * 数据流向:
     * ┌─────────────────────────────────────────────────────────┐
     * │ step3()                                                 │
     * │    ↓                                                    │
     * │ 获取安装目录路径 $install_dir                           │
     * │    ↓                                                    │
     * │ assign('install_dir', $install_dir) 传递给视图         │
     * │    ↓                                                    │
     * │ fetch('install@index/step3') 渲染配置表单页面          │
     * │    模板路径: application/install/view/index/step3.html │
     * └─────────────────────────────────────────────────────────┘
     *
     * @return mixed 返回渲染后的 HTML
     */
    private function step3()
    {
        // 获取当前脚本路径，用于确定安装目录
        $install_dir = $_SERVER["SCRIPT_NAME"];
        // 提取目录路径 (去除 install.php 文件名)
        $install_dir = mac_substring($install_dir, strripos($install_dir, "/")+1);

        // 将安装目录传递给视图，用于表单隐藏字段
        $this->assign('install_dir',$install_dir);

        // 渲染数据库配置页面
        return $this->fetch('install@index/step3');
    }
    
    /**
     * ============================================================
     * 【第四步：测试数据库连接并创建数据库】
     * ============================================================
     *
     * URL: /install.php?step=4 (POST请求，AJAX调用)
     *
     * 功能说明:
     * 1. 验证数据库配置参数
     * 2. 测试数据库服务器连接
     * 3. 生成 database.php 配置文件
     * 4. 创建数据库 (如果不存在)
     *
     * 处理流程:
     * ┌─────────────────────────────────────────────────────────┐
     * │ step4() - AJAX POST 请求                                │
     * │    ↓                                                    │
     * │ 1. 验证POST数据 (hostname, hostport, database等)       │
     * │    ↓                                                    │
     * │ 2. Db::connect() 测试数据库连接                        │
     * │    ↓                                                    │
     * │ 3. mkDatabase() 生成 application/database.php          │
     * │    ↓                                                    │
     * │ 4. 检查数据库是否存在 (根据cover参数决定是否覆盖)      │
     * │    ↓                                                    │
     * │ 5. CREATE DATABASE 创建数据库                          │
     * │    ↓                                                    │
     * │ 6. 返回 JSON {code:1, msg:'连接成功'}                  │
     * └─────────────────────────────────────────────────────────┘
     *
     * @return mixed JSON格式响应
     */
    private function step4()
    {
        // 仅处理POST请求
        if ($this->request->isPost()) {
            // 检查 database.php 配置文件是否可写
            if (!is_writable(APP_PATH.'database.php')) {
                return $this->error('[app/database.php]'.lang('install/write_read_err'));
            }

            // 获取POST提交的数据库配置
            $data = input('post.');
            $data['type'] = 'mysql';  // 数据库类型固定为 MySQL

            // 【表单验证规则】
            $rule = [
                'hostname|'.lang('install/server_address') => 'require',                           // 服务器地址必填
                'hostport|'.lang('install/database_port') => 'require|number',                     // 端口必填且为数字
                'database|'.lang('install/database_name') => 'require',                            // 数据库名必填
                'username|'.lang('install/database_username') => 'require',                        // 用户名必填
                'prefix|'.lang('install/database_pre') => 'require|regex:^[a-z0-9]{1,20}[_]{1}',  // 前缀格式验证
                'cover|'.lang('install/overwrite_database') => 'require|in:0,1',                   // 覆盖选项 0或1
            ];
            $validate = $this->validate($data, $rule);
            if (true !== $validate) {
                return $this->error($validate);
            }

            // 保存并移除 cover 参数 (不写入配置文件)
            $cover = $data['cover'];
            unset($data['cover']);

            // 验证参数是否在配置模板中存在
            $config = include APP_PATH.'database.php';
            foreach ($data as $k => $v) {
                if (array_key_exists($k, $config) === false) {
                    return $this->error(lang('param').''.$k.''.lang('install/not_found'));
                }
            }

            // 临时移除 database 参数 (连接时不指定数据库，因为可能不存在)
            $database = $data['database'];
            unset($data['database']);

            // 【创建数据库连接】测试连接是否成功
            $db_connect = Db::connect($data);
            try{
                // 执行简单查询测试连接
                $db_connect->execute('select version()');
            }catch(\Exception $e){
                // 连接失败，返回错误
                $this->error(lang('install/database_connect_err'));
            }

            // 【生成数据库配置文件】
            $data['database'] = $database;
            self::mkDatabase($data);

            // 【检查数据库是否已存在】(不覆盖模式)
            if (!$cover) {
                $check = $db_connect->execute('SELECT * FROM information_schema.schemata WHERE schema_name="'.$database.'"');
                if ($check) {
                    // 数据库已存在，提示用户
                    $this->success(lang('install/database_name_haved'),'');
                }
            }

            // 【创建数据库】如果不存在则创建，使用 UTF8 编码
            if (!$db_connect->execute("CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARACTER SET utf8")) {
                return $this->error($db_connect->getError());
            }

            // 返回成功响应
            return $this->success(lang('install/database_connect_ok'), '');
        } else {
            // 非POST请求，拒绝访问
            return $this->error(lang('install/access_denied'));
        }
    }
    
    /**
     * ============================================================
     * 【第五步：执行最终安装 - 导入数据库与创建管理员】
     * ============================================================
     *
     * URL: /install.php?step=5 (POST请求)
     *
     * 功能说明:
     * 1. 验证管理员账号密码
     * 2. 更新系统配置文件 (maccms.php)
     * 3. 导入数据库表结构 (install.sql)
     * 4. 可选导入初始化数据 (initdata.sql)
     * 5. 创建管理员账号
     * 6. 生成安装锁文件 (install.lock)
     * 7. 跳转到后台管理页面
     *
     * 处理流程:
     * ┌─────────────────────────────────────────────────────────┐
     * │ step5() - POST 请求                                     │
     * │    ↓                                                    │
     * │ 1. 验证管理员账号/密码格式                              │
     * │    ↓                                                    │
     * │ 2. 更新 application/extra/maccms.php 配置              │
     * │    - 设置缓存标识                                       │
     * │    - 设置语言                                           │
     * │    - 禁用API默认开关                                    │
     * │    - 生成接口密钥                                       │
     * │    ↓                                                    │
     * │ 3. 导入 install/sql/install.sql 创建数据表             │
     * │    - 替换表前缀 mac_ → 用户自定义前缀                  │
     * │    ↓                                                    │
     * │ 4. 可选导入 install/sql/initdata.sql 演示数据          │
     * │    ↓                                                    │
     * │ 5. 创建管理员账号 (调用 Admin 模型)                    │
     * │    ↓                                                    │
     * │ 6. 生成 data/install/install.lock 锁定安装             │
     * │    ↓                                                    │
     * │ 7. 跳转到 admin.php 后台管理页面                       │
     * └─────────────────────────────────────────────────────────┘
     *
     * SQL文件位置:
     * - application/install/sql/install.sql    数据库结构
     * - application/install/sql/initdata.sql   初始化演示数据
     *
     * @return mixed 成功则跳转，失败返回错误信息
     */
    private function step5()
    {
        // 获取POST参数
        $account = input('post.account');      // 管理员账号
        $password = input('post.password');    // 管理员密码
        $install_dir = input('post.install_dir');  // 安装目录
        $initdata = input('post.initdata');    // 是否导入初始化数据 (1=是, 0=否)

        // 【验证数据库配置】确保已完成第四步
        $config = include APP_PATH.'database.php';
        if (empty($config['hostname']) || empty($config['database']) || empty($config['username'])) {
            return $this->error(lang('install/please_test_connect'));
        }

        // 【验证管理员信息】
        if (empty($account) || empty($password)) {
            return $this->error(lang('install/please_input_admin_name_pass'));
        }

        // 【管理员信息验证规则】
        $rule = [
            'account|'.lang('install/admin_name') => 'require|alphaNum',    // 账号: 必填，字母数字
            'password|'.lang('install/admin_pass') => 'require|length:6,20', // 密码: 必填，6-20位
        ];
        $validate = $this->validate(['account' => $account, 'password' => $password], $rule);
        if (true !== $validate) {
            return $this->error($validate);
        }

        // 默认安装目录为根目录
        if(empty($install_dir)) {
            $install_dir='/';
        }

        // 【更新系统配置文件】
        $config_new = config('maccms');
        $cofnig_new['app']['cache_flag'] = substr(md5(time()),0,10);  // 生成缓存标识
        $cofnig_new['app']['lang'] = session('lang');  // 设置语言

        // 默认禁用视频和文章API
        $config_new['api']['vod']['status'] = 0;
        $config_new['api']['art']['status'] = 0;

        // 默认禁用接口，生成随机密钥
        $config_new['interface']['status'] = 0;
        $config_new['interface']['pass'] = mac_get_rndstr(16);  // 16位随机密钥
        $config_new['site']['install_dir'] = $install_dir;

        // 写入配置文件
        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
		if ($res === false) {
			return $this->error(lang('write_err_config'));
		}

        // ============================================================
        // 【导入数据库结构】install.sql
        // 包含所有数据表的创建语句 (CREATE TABLE)
        // ============================================================
        $sql_file = APP_PATH.'install/sql/install.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            // 解析SQL，替换表前缀 mac_ → 用户设置的前缀
            $sql_list = mac_parse_sql($sql, 0, ['mac_' => $config['prefix']]);
            if ($sql_list) {
                $sql_list = array_filter($sql_list);
                foreach ($sql_list as $v) {
                    try {
                        Db::execute($v);  // 执行每条SQL语句
                    } catch(\Exception $e) {
                        return $this->error(lang('install/sql_err'). $e);
                    }
                }
            }
        }

        // ============================================================
        // 【导入初始化数据】initdata.sql (可选)
        // 包含演示分类、播放器配置、解析接口等初始数据
        // ============================================================
        if($initdata=='1'){
            $sql_file = APP_PATH.'install/sql/initdata.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                $sql_list = mac_parse_sql($sql, 0, ['mac_' => $config['prefix']]);
                if ($sql_list) {
                    $sql_list = array_filter($sql_list);
                    foreach ($sql_list as $v) {
                        try {
                            Db::execute($v);
                        } catch(\Exception $e) {
                            return $this->error(lang('install/init_data_err'). $e);
                        }
                    }
                }
            }
        }

        // ============================================================
        // 【创建管理员账号】
        // 调用 Admin 模型的 saveData 方法
        // ============================================================
        $data = [
            'admin_name' => $account,
            'admin_pwd' => $password,
            'admin_status' =>1,  // 启用状态
        ];
        $res = model('Admin')->saveData($data);
        if (!$res['code']>1) {
            return $this->error(lang('install/admin_name_err').'：'.$res['msg']);
        }

        // ============================================================
        // 【生成安装锁文件】
        // 创建后将阻止重复安装 (index.php 会检测此文件)
        // ============================================================
        file_put_contents(APP_PATH.'data/install/install.lock', date('Y-m-d H:i:s'));

        // 获取站点根目录，跳转到后台
        $root_dir = request()->baseFile();
        $root_dir  = preg_replace(['/install.php$/'], [''], $root_dir);

        // 安装成功，跳转到 admin.php 后台管理页面
        return $this->success(lang('install/is_ok'), $root_dir.'admin.php');
    }
    
    /**
     * ============================================================
     * 【环境检测方法】检测操作系统和PHP版本
     * ============================================================
     *
     * 检测项目:
     * 1. 操作系统 - 通过 PHP_OS 常量获取，无版本要求
     * 2. PHP版本 - 通过 PHP_VERSION 常量获取，要求 >= 5.5
     *
     * 返回数据结构:
     * [
     *   'os'  => [名称, 最低要求, 推荐配置, 当前值, 状态],
     *   'php' => [名称, 最低要求, 推荐配置, 当前值, 状态]
     * ]
     *
     * 状态值: 'ok' = 通过, 'no' = 失败
     * 如果检测失败，会设置 session('install_error', true) 阻止继续安装
     *
     * @return array 环境检测结果数组
     */
    private function checkNnv()
    {
        // 初始化检测项，默认状态为 'ok' (通过)
        $items = [
            // 操作系统: [名称, 最低要求, 推荐配置, 当前值, 状态]
            'os'      => [lang('install/os'), lang('install/not_limited'), 'Windows/Unix', PHP_OS, 'ok'],
            // PHP版本: [名称, 最低版本, 推荐配置, 当前版本, 状态]
            'php'     => [lang('install/php'), '5.5', '5.5及以上', PHP_VERSION, 'ok'],
        ];

        // 检测PHP版本是否满足最低要求 (>= 5.5)
        if ($items['php'][3] < $items['php'][1]) {
            $items['php'][4] = 'no';  // 标记为失败
            session('install_error', true);  // 设置错误标记，阻止继续安装
        }

        /*
        // GD库检测 (已注释，当前版本不强制要求)
        $tmp = function_exists('gd_info') ? gd_info() : [];
        if (empty($tmp['GD Version'])) {
            $items['gd'][3] = lang('install/not_installed');
            $items['gd'][4] = 'no';
            session('install_error', true);
        } else {
            $items['gd'][3] = $tmp['GD Version'];
        }
        */

        return $items;
    }
    
    /**
     * ============================================================
     * 【目录权限检测方法】检测关键目录和文件的读写权限
     * ============================================================
     *
     * 检测项目:
     * 1. ./application/database.php  - 数据库配置文件 (安装时需写入)
     * 2. ./application/route.php     - 路由配置文件
     * 3. ./application/extra         - 扩展配置目录 (存储maccms.php等)
     * 4. ./application/data/backup   - 数据备份目录
     * 5. ./application/data/update   - 系统更新目录
     * 6. ./runtime                   - 运行时缓存目录 (日志、缓存、编译模板)
     * 7. ./upload                    - 上传文件目录
     *
     * 返回数据结构:
     * [
     *   [类型, 路径, 所需权限, 当前权限, 状态],
     *   ...
     * ]
     *
     * 类型: 'file' = 文件, 'dir' = 目录
     * 状态: 'ok' = 权限正常, 'no' = 权限不足
     *
     * @return array 目录权限检测结果数组
     */
    private function checkDir()
    {
        // 初始化检测项列表
        // 结构: [类型, 路径, 所需权限, 当前权限(默认), 状态(默认ok)]
        $items = [
            ['file', './application/database.php', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['file', './application/route.php', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './application/extra', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './application/data/backup', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './application/data/update', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './runtime', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './upload', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
        ];

        // 遍历检测每个目录/文件的权限
        foreach ($items as &$v) {
            if ($v[0] == 'dir') {
                // 【目录检测】
                if(!is_writable($v[1])) {
                    if(is_dir($v[1])) {
                        // 目录存在但不可写
                        $v[3] = lang('install/not_writable');
                        $v[4] = 'no';
                    } else {
                        // 目录不存在
                        $v[3] = lang('install/not_found');
                        $v[4] = 'no';
                    }
                    session('install_error', true);  // 设置错误标记
                }
            } else {
                // 【文件检测】
                if(!is_writable($v[1])) {
                    $v[3] = lang('install/not_writable');
                    $v[4] = 'no';
                    session('install_error', true);  // 设置错误标记
                }
            }
        }

        return $items;
    }
    
    /**
     * ============================================================
     * 【函数扩展检测方法】检测必需的PHP扩展和函数
     * ============================================================
     *
     * 检测项目及用途:
     * 1. pdo          - PDO类 (数据库访问抽象层，必需)
     * 2. pdo_mysql    - PDO MySQL驱动扩展 (连接MySQL数据库)
     * 3. zip          - ZIP压缩扩展 (用于插件安装、模板导入)
     * 4. fileinfo     - 文件信息扩展 (用于MIME类型检测、文件上传验证)
     * 5. curl         - cURL扩展 (用于采集、API调用、远程请求)
     * 6. xml          - XML函数 (用于解析XML格式的采集数据)
     * 7. file_get_contents - 文件读取函数 (基础文件操作)
     * 8. mb_strlen    - 多字节字符串函数 (处理中文等多字节字符)
     *
     * 特殊检测 (仅 PHP 5.6.x):
     * - always_populate_raw_post_data 配置项需设为 -1
     *
     * 返回数据结构:
     * [
     *   [名称, 检测结果, 状态, 类型],
     *   ...
     * ]
     *
     * 类型: '类' = class_exists, '模块' = extension_loaded, '函数' = function_exists
     * 状态: 'yes' = 支持, 'no' = 不支持
     *
     * @return array 函数扩展检测结果数组
     */
    private function checkFunc()
    {
        // 初始化检测项列表
        // 结构: [名称, 检测结果(默认支持), 状态(默认yes), 类型]
        $items = [
            ['pdo', lang('install/support'), 'yes',lang('install/class')],           // PDO类
            ['pdo_mysql', lang('install/support'), 'yes', lang('install/model')],    // PDO MySQL扩展
            ['zip', lang('install/support'), 'yes', lang('install/model')],          // ZIP扩展
            ['fileinfo', lang('install/support'), 'yes', lang('install/model')],     // Fileinfo扩展
            ['curl', lang('install/support'), 'yes', lang('install/model')],         // cURL扩展
            ['xml', lang('install/support'), 'yes', lang('install/function')],       // XML函数
            ['file_get_contents', lang('install/support'), 'yes', lang('install/function')],  // 文件读取函数
            ['mb_strlen', lang('install/support'), 'yes', lang('install/function')], // 多字节字符串函数
        ];

        // 【特殊检测】PHP 5.6.x 版本需要检测 always_populate_raw_post_data 配置
        // 该配置在 PHP 5.6 中需设为 -1，否则会产生警告
        if(version_compare(PHP_VERSION,'5.6.0','ge') && version_compare(PHP_VERSION,'5.7.0','lt')){
            $items[] = ['always_populate_raw_post_data',lang('install/support'),'yes',lang('install/config')];
        }

        // 遍历检测每个扩展/函数
        foreach ($items as &$v) {
            // 根据类型使用不同的检测方法
            if(
                ('类'==$v[3] && !class_exists($v[0])) ||                              // 类检测
                (lang('install/model')==$v[3] && !extension_loaded($v[0])) ||         // 扩展检测
                (lang('install/function')==$v[3] && !function_exists($v[0])) ||       // 函数检测
                (lang('install/config')==$v[3] && ini_get('always_populate_raw_post_data')!=-1)  // 配置检测
            ) {
                $v[1] = lang('install/not_support');  // 标记为不支持
                $v[2] = 'no';                          // 状态设为失败
                session('install_error', true);        // 设置错误标记
            }
        }

        return $items;
    }
    
    /**
     * 生成数据库配置文件
     * @return array
     */
    private function mkDatabase(array $data)
    {
        $code = <<<INFO
<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
return [
    // 数据库类型
    'type'            => 'mysql',
    // 服务器地址
    'hostname'        => '{$data['hostname']}',
    // 数据库名
    'database'        => '{$data['database']}',
    // 用户名
    'username'        => '{$data['username']}',
    // 密码
    'password'        => '{$data['password']}',
    // 端口
    'hostport'        => '{$data['hostport']}',
    // 连接dsn
    'dsn'             => '',
    // 数据库连接参数
    'params'          => [],
    // 数据库编码默认采用utf8
    'charset'         => 'utf8',
    // 数据库表前缀
    'prefix'          => '{$data['prefix']}',
    // 数据库调试模式
    'debug'           => false,
    // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    'deploy'          => 0,
    // 数据库读写是否分离 主从式有效
    'rw_separate'     => false,
    // 读写分离后 主服务器数量
    'master_num'      => 1,
    // 指定从服务器序号
    'slave_no'        => '',
    // 是否严格检查字段是否存在
    'fields_strict'   => false,
    // 数据集返回类型
    'resultset_type'  => 'array',
    // 自动写入时间戳字段
    'auto_timestamp'  => false,
    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',
    // 是否需要进行SQL性能分析
    'sql_explain'     => false,
    // Builder类
    'builder'         => '',
    // Query类
    'query'           => '\\think\\db\\Query',
];
INFO;
        file_put_contents(APP_PATH.'database.php', $code);
        // 判断写入是否成功
        $config = include APP_PATH.'database.php';
        if (empty($config['database']) || $config['database'] != $data['database']) {
            return $this->error('[application/database.php]'.lang('write_err_database'));
            exit;
        }
    }
}