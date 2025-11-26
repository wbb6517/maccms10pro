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
     * 第二步：环境检测
     * @return mixed
     */
    private function step2()
    {
        $data = [];
        $data['env'] = self::checkNnv();
        $data['dir'] = self::checkDir();
        $data['func'] = self::checkFunc();
        $this->assign('data', $data);
        return $this->fetch('install@index/step2');
    }
    
    /**
     * 第三步：初始化配置
     * @return mixed
     */
    private function step3()
    {
        $install_dir = $_SERVER["SCRIPT_NAME"];
        $install_dir = mac_substring($install_dir, strripos($install_dir, "/")+1);
        $this->assign('install_dir',$install_dir);
        return $this->fetch('install@index/step3');
    }
    
    /**
     * 第四步：执行安装
     * @return mixed
     */
    private function step4()
    {
        if ($this->request->isPost()) {
            if (!is_writable(APP_PATH.'database.php')) {
                return $this->error('[app/database.php]'.lang('install/write_read_err'));
            }
            $data = input('post.');
            $data['type'] = 'mysql';
            $rule = [
                'hostname|'.lang('install/server_address') => 'require',
                'hostport|'.lang('install/database_port') => 'require|number',
                'database|'.lang('install/database_name') => 'require',
                'username|'.lang('install/database_username') => 'require',
                'prefix|'.lang('install/database_pre') => 'require|regex:^[a-z0-9]{1,20}[_]{1}',
                'cover|'.lang('install/overwrite_database') => 'require|in:0,1',
            ];
            $validate = $this->validate($data, $rule);
            if (true !== $validate) {
                return $this->error($validate);
            }
            $cover = $data['cover'];
            unset($data['cover']);
            $config = include APP_PATH.'database.php';
            foreach ($data as $k => $v) {
                if (array_key_exists($k, $config) === false) {
                    return $this->error(lang('param').''.$k.''.lang('install/not_found'));
                }
            }
            // 不存在的数据库会导致连接失败
            $database = $data['database'];
            unset($data['database']);
            // 创建数据库连接
            $db_connect = Db::connect($data);
            // 检测数据库连接
            try{
                $db_connect->execute('select version()');
            }catch(\Exception $e){
                $this->error(lang('install/database_connect_err'));
            }

            // 生成数据库配置文件
            $data['database'] = $database;
            self::mkDatabase($data);


            // 不覆盖检测是否已存在数据库
            if (!$cover) {
                $check = $db_connect->execute('SELECT * FROM information_schema.schemata WHERE schema_name="'.$database.'"');
                if ($check) {
                    $this->success(lang('install/database_name_haved'),'');
                }
            }
            // 创建数据库
            if (!$db_connect->execute("CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARACTER SET utf8")) {
                return $this->error($db_connect->getError());
            }


            return $this->success(lang('install/database_connect_ok'), '');
        } else {
            return $this->error(lang('install/access_denied'));
        }
    }
    
    /**
     * 第五步：数据库安装
     * @return mixed
     */
    private function step5()
    {
        $account = input('post.account');
        $password = input('post.password');
        $install_dir = input('post.install_dir');
        $initdata = input('post.initdata');

        $config = include APP_PATH.'database.php';
        if (empty($config['hostname']) || empty($config['database']) || empty($config['username'])) {
            return $this->error(lang('install/please_test_connect'));
        }
        if (empty($account) || empty($password)) {
            return $this->error(lang('install/please_input_admin_name_pass'));
        }

        $rule = [
            'account|'.lang('install/admin_name') => 'require|alphaNum',
            'password|'.lang('install/admin_pass') => 'require|length:6,20',
        ];
        $validate = $this->validate(['account' => $account, 'password' => $password], $rule);
        if (true !== $validate) {
            return $this->error($validate);
        }
        if(empty($install_dir)) {
            $install_dir='/';
        }
        $config_new = config('maccms');
        $cofnig_new['app']['cache_flag'] = substr(md5(time()),0,10);
        $cofnig_new['app']['lang'] = session('lang');

        $config_new['api']['vod']['status'] = 0;
        $config_new['api']['art']['status'] = 0;

        $config_new['interface']['status'] = 0;
        $config_new['interface']['pass'] = mac_get_rndstr(16);
        $config_new['site']['install_dir'] = $install_dir;
        
        // 更新程序配置文件
        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
		if ($res === false) {
			return $this->error(lang('write_err_config'));
		}
		
        // 导入系统初始数据库结构
        // 导入SQL
        $sql_file = APP_PATH.'install/sql/install.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $sql_list = mac_parse_sql($sql, 0, ['mac_' => $config['prefix']]);
            if ($sql_list) {
                $sql_list = array_filter($sql_list);
                foreach ($sql_list as $v) {
                    try {
                        Db::execute($v);
                    } catch(\Exception $e) {
                        return $this->error(lang('install/sql_err'). $e);
                    }
                }
            }
        }
        //初始化数据
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

        // 注册管理员账号
        $data = [
            'admin_name' => $account,
            'admin_pwd' => $password,
            'admin_status' =>1,
        ];
        $res = model('Admin')->saveData($data);
        if (!$res['code']>1) {
            return $this->error(lang('install/admin_name_err').'：'.$res['msg']);
        }
        file_put_contents(APP_PATH.'data/install/install.lock', date('Y-m-d H:i:s'));

        // 获取站点根目录
        $root_dir = request()->baseFile();
        $root_dir  = preg_replace(['/install.php$/'], [''], $root_dir);
        return $this->success(lang('install/is_ok'), $root_dir.'admin.php');
    }
    
    /**
     * 环境检测
     * @return array
     */
    private function checkNnv()
    {
        $items = [
            'os'      => [lang('install/os'), lang('install/not_limited'), 'Windows/Unix', PHP_OS, 'ok'],
            'php'     => [lang('install/php'), '5.5', '5.5及以上', PHP_VERSION, 'ok'],
        ];
        if ($items['php'][3] < $items['php'][1]) {
            $items['php'][4] = 'no';
            session('install_error', true);
        }
        /*
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
     * 目录权限检查
     * @return array
     */
    private function checkDir()
    {
        $items = [
            ['file', './application/database.php', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['file', './application/route.php', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './application/extra', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './application/data/backup', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './application/data/update', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './runtime', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
            ['dir', './upload', lang('install/read_and_write'), lang('install/read_and_write'), 'ok'],
        ];
        foreach ($items as &$v) {
            if ($v[0] == 'dir') {// 文件夹
                if(!is_writable($v[1])) {
                    if(is_dir($v[1])) {
                        $v[3] = lang('install/not_writable');
                        $v[4] = 'no';
                    } else {
                        $v[3] = lang('install/not_found');
                        $v[4] = 'no';
                    }
                    session('install_error', true);
                }
            } else {// 文件
                if(!is_writable($v[1])) {
                    $v[3] = lang('install/not_writable');
                    $v[4] = 'no';
                    session('install_error', true);
                }
            }
        }
        return $items;
    }
    
    /**
     * 函数及扩展检查
     * @return array
     */
    private function checkFunc()
    {
        $items = [
            ['pdo', lang('install/support'), 'yes',lang('install/class')],
            ['pdo_mysql', lang('install/support'), 'yes', lang('install/model')],
            ['zip', lang('install/support'), 'yes', lang('install/model')],
            ['fileinfo', lang('install/support'), 'yes', lang('install/model')],
            ['curl', lang('install/support'), 'yes', lang('install/model')],
            ['xml', lang('install/support'), 'yes', lang('install/function')],
            ['file_get_contents', lang('install/support'), 'yes', lang('install/function')],
            ['mb_strlen', lang('install/support'), 'yes', lang('install/function')],
        ];

        if(version_compare(PHP_VERSION,'5.6.0','ge') && version_compare(PHP_VERSION,'5.7.0','lt')){
            $items[] = ['always_populate_raw_post_data',lang('install/support'),'yes',lang('install/config')];
        }

        foreach ($items as &$v) {
            if(('类'==$v[3] && !class_exists($v[0])) || (lang('install/model')==$v[3] && !extension_loaded($v[0])) || (lang('install/function')==$v[3] && !function_exists($v[0])) || (lang('install/config')==$v[3] && ini_get('always_populate_raw_post_data')!=-1)) {
                $v[1] = lang('install/not_support');
                $v[2] = 'no';
                session('install_error', true);
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