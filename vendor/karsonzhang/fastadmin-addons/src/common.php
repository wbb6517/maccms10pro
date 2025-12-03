<?php
/**
 * 插件系统公共函数库 (Addon Common Functions)
 * ============================================================
 *
 * 【文件说明】
 * FastAdmin 插件扩展的核心函数库
 * 提供插件管理所需的所有辅助函数
 *
 * 【函数列表】
 * ┌──────────────────────────┬────────────────────────────────────────────┐
 * │ 函数名                    │ 说明                                        │
 * ├──────────────────────────┼────────────────────────────────────────────┤
 * │ hook()                   │ 处理插件钩子                                │
 * │ remove_empty_folder()    │ 递归移除空目录                              │
 * │ get_addon_list()         │ 获取所有已安装插件列表                      │
 * │ get_addon_autoload_config│ 获取插件自动加载配置                        │
 * │ get_addon_class()        │ 获取插件类的完整类名                        │
 * │ get_addon_info()         │ 读取插件基础信息（info.ini）                │
 * │ get_addon_fullconfig()   │ 获取插件完整配置数组                        │
 * │ get_addon_config()       │ 获取插件配置值（键值对）                    │
 * │ get_addon_instance()     │ 获取插件类单例实例                          │
 * │ addon_url()              │ 生成插件访问URL                             │
 * │ set_addon_info()         │ 写入插件基础信息到 info.ini                 │
 * │ set_addon_config()       │ 写入插件配置                                │
 * │ set_addon_fullconfig()   │ 写入完整配置到 config.php                   │
 * └──────────────────────────┴────────────────────────────────────────────┘
 *
 * 【插件目录结构】
 * addons/                      # 插件根目录（ADDON_PATH常量）
 * └── {addon_name}/            # 插件目录（小写）
 *     ├── info.ini             # 插件基础信息（必须）
 *     ├── config.php           # 插件配置文件（可选）
 *     ├── {AddonName}.php      # 插件主类（首字母大写）
 *     ├── controller/          # 控制器目录
 *     ├── model/               # 模型目录
 *     ├── view/                # 视图目录
 *     └── install.sql          # 安装SQL（可选）
 *
 * 【info.ini 配置格式】
 * name = example               ; 插件标识（与目录名一致）
 * title = 示例插件             ; 插件名称
 * intro = 这是一个示例插件     ; 简介
 * author = 作者名              ; 作者
 * website = https://xxx.com    ; 网站
 * version = 1.0.0              ; 版本号
 * state = 1                    ; 状态：0=禁用, 1=启用
 *
 * 【config.php 配置格式】
 * return [
 *     [
 *         'name'    => 'api_key',        // 配置项名称
 *         'title'   => 'API密钥',        // 显示标题
 *         'type'    => 'string',         // 输入类型
 *         'content' => [],               // 选项内容
 *         'value'   => '',               // 当前值
 *         'rule'    => 'required',       // 验证规则
 *         'tip'     => '请输入API密钥',  // 提示说明
 *     ],
 *     // ... 更多配置项
 * ];
 *
 * 【依赖关系】
 * - 本文件被 composer autoload 自动加载
 * - 依赖 ThinkPHP5 框架的各类组件
 *
 * ============================================================
 */

use think\App;
use think\Cache;
use think\Config;
use think\Exception;
use think\Hook;
use think\Loader;
use think\Route;

// 插件目录
define('ADDON_PATH', ROOT_PATH . 'addons' . DS);

// 定义路由
Route::any('addons/:addon/[:controller]/[:action]', "\\think\\addons\\Route@execute");

// 如果插件目录不存在则创建
if (!is_dir(ADDON_PATH)) {
    @mkdir(ADDON_PATH, 0755, true);
}

// 注册类的根命名空间
Loader::addNamespace('addons', ADDON_PATH);

// 监听addon_init
Hook::listen('addon_init');

// 闭包自动识别插件目录配置
Hook::add('app_init', function () {
    // 获取开关
    $autoload = (bool)Config::get('addons.autoload', false);
    // 非正是返回
    if (!$autoload) {
        return;
    }
    // 当debug时不缓存配置
    $config = App::$debug ? [] : Cache::get('addons', []);
    if (empty($config)) {
        $config = get_addon_autoload_config();
        Cache::set('addons', $config);
    }
});

// 闭包初始化行为
Hook::add('app_init', function () {
    //注册路由
    $routeArr = (array)Config::get('addons.route');
    $domains = [];
    $rules = [];
    $execute = "\\think\\addons\\Route@execute?addon=%s&controller=%s&action=%s";
    foreach ($routeArr as $k => $v) {
        if (is_array($v)) {
            $addon = $v['addon'];
            $domain = $v['domain'];
            $drules = [];
            foreach ($v['rule'] as $m => $n) {
                list($addon, $controller, $action) = explode('/', $n);
                $drules[$m] = sprintf($execute . '&indomain=1', $addon, $controller, $action);
            }
            //$domains[$domain] = $drules ? $drules : "\\addons\\{$k}\\controller";
            $domains[$domain] = $drules ? $drules : [];
            $domains[$domain][':controller/[:action]'] = sprintf($execute . '&indomain=1', $addon, ":controller", ":action");
        } else {
            if (!$v) {
                continue;
            }
            list($addon, $controller, $action) = explode('/', $v);
            $rules[$k] = sprintf($execute, $addon, $controller, $action);
        }
    }
    Route::rule($rules);
    if ($domains) {
        Route::domain($domains);
    }

    // 获取系统配置
    $hooks = App::$debug ? [] : Cache::get('hooks', []);
    if (empty($hooks)) {
        $hooks = (array)Config::get('addons.hooks');
        // 初始化钩子
        foreach ($hooks as $key => $values) {
            if (is_string($values)) {
                $values = explode(',', $values);
            } else {
                $values = (array)$values;
            }
            $hooks[$key] = array_filter(array_map('get_addon_class', $values));
        }
        Cache::set('hooks', $hooks);
    }
    //如果在插件中有定义app_init，则直接执行
    if (isset($hooks['app_init'])) {
        foreach ($hooks['app_init'] as $k => $v) {
            Hook::exec($v, 'app_init');
        }
    }
    Hook::import($hooks, true);
});

/**
 * 处理插件钩子
 * @param string $hook   钩子名称
 * @param mixed  $params 传入参数
 * @return void
 */
function hook($hook, $params = [])
{
    Hook::listen($hook, $params);
}

/**
 * 移除空目录
 * @param string $dir 目录
 */
function remove_empty_folder($dir)
{
    try {
        $isDirEmpty = !(new \FilesystemIterator($dir))->valid();
        if ($isDirEmpty) {
            @rmdir($dir);
            remove_empty_folder(dirname($dir));
        }
    } catch (\UnexpectedValueException $e) {

    } catch (\Exception $e) {

    }
}

/**
 * ============================================================
 * 获取所有已安装插件列表
 * ============================================================
 *
 * 【功能说明】
 * 扫描 addons/ 目录，获取所有已安装插件的信息
 * 返回以插件名为键的关联数组
 *
 * 【扫描规则】
 * 必须同时满足以下条件才被识别为有效插件：
 * 1. 是目录（不是文件）
 * 2. 存在插件主类文件：{AddonName}.php（首字母大写）
 * 3. 存在配置文件：info.ini
 * 4. info.ini 中包含 name 字段
 *
 * 【返回数据格式】
 * [
 *     'example' => [
 *         'name'    => 'example',
 *         'title'   => '示例插件',
 *         'intro'   => '这是一个示例',
 *         'author'  => '作者',
 *         'version' => '1.0.0',
 *         'state'   => 1,
 *         'url'     => '/addons/example',
 *     ],
 *     // ... 更多插件
 * ]
 *
 * 【注意事项】
 * - 不使用 get_addon_info() 是为了避免缓存问题
 * - 直接解析 info.ini 文件获取最新信息
 *
 * @return array 插件信息数组
 */
function get_addon_list()
{
    // ========== 第一步：扫描插件目录 ==========
    // scandir() 返回目录中的所有文件和子目录
    // 包含 "." 和 ".." 两个特殊目录
    $results = scandir(ADDON_PATH);
    $list = [];

    // ========== 第二步：遍历检查每个目录 ==========
    foreach ($results as $name) {
        // 跳过当前目录和上级目录的特殊引用
        if ($name === '.' or $name === '..') {
            continue;
        }

        // 跳过文件（只处理目录）
        // 插件必须是一个独立的目录
        if (is_file(ADDON_PATH . $name)) {
            continue;
        }

        // 构建插件目录完整路径
        $addonDir = ADDON_PATH . $name . DS;

        // 再次确认是目录
        if (!is_dir($addonDir)) {
            continue;
        }

        // ========== 第三步：检查插件主类文件 ==========
        // 插件主类文件命名规则：首字母大写的插件名 + .php
        // 例如：插件目录 example → 主类文件 Example.php
        // ucfirst() 将字符串首字母转为大写
        if (!is_file($addonDir . ucfirst($name) . '.php')) {
            continue;
        }

        // ========== 第四步：检查并解析 info.ini ==========
        // info.ini 是插件的必备配置文件
        // 包含插件的基本信息：name, title, intro, author, version, state
        //
        // 【为什么不用 get_addon_info()】
        // get_addon_info() 会通过插件实例获取信息，会有缓存
        // 这里需要获取最新的目录状态，所以直接解析文件
        $info_file = $addonDir . 'info.ini';
        if (!is_file($info_file)) {
            continue;
        }

        // Config::parse() 解析 INI 文件为数组
        // 第三个参数是缓存键名，用于 ThinkPHP 配置缓存
        $info = Config::parse($info_file, '', "addon-info-{$name}");

        // 验证 info.ini 中必须包含 name 字段
        if (!isset($info['name'])) {
            continue;
        }

        // ========== 第五步：生成插件URL并添加到列表 ==========
        // addon_url() 生成插件的访问地址
        $info['url'] = addon_url($name);

        // 以插件名为键存入数组
        $list[$name] = $info;
    }

    return $list;
}

/**
 * 获得插件自动加载的配置
 * @param bool $truncate 是否清除手动配置的钩子
 * @return array
 */
function get_addon_autoload_config($truncate = false)
{
    // 读取addons的配置
    $config = (array)Config::get('addons');
    if ($truncate) {
        // 清空手动配置的钩子
        $config['hooks'] = [];
    }

    // 伪静态优先级
    $priority = isset($config['priority']) && $config['priority'] ? is_array($config['priority']) ? $config['priority'] : explode(',', $config['priority']) : [];

    $route = [];
    // 读取插件目录及钩子列表
    $base = get_class_methods("\\think\\Addons");
    $base = array_merge($base, ['install', 'uninstall', 'enable', 'disable']);

    $url_domain_deploy = Config::get('url_domain_deploy');
    $addons = get_addon_list();
    $domain = [];

    $priority = array_merge($priority, array_keys($addons));

    $orderedAddons = array();
    foreach ($priority as $key) {
        if (!isset($addons[$key])) {
            continue;
        }
        $orderedAddons[$key] = $addons[$key];
    }

    foreach ($orderedAddons as $name => $addon) {
        if (!$addon['state']) {
            continue;
        }

        // 读取出所有公共方法
        $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . ucfirst($name));
        // 跟插件基类方法做比对，得到差异结果
        $hooks = array_diff($methods, $base);
        // 循环将钩子方法写入配置中
        foreach ($hooks as $hook) {
            $hook = Loader::parseName($hook, 0, false);
            if (!isset($config['hooks'][$hook])) {
                $config['hooks'][$hook] = [];
            }
            // 兼容手动配置项
            if (is_string($config['hooks'][$hook])) {
                $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
            }
            if (!in_array($name, $config['hooks'][$hook])) {
                $config['hooks'][$hook][] = $name;
            }
        }
        $conf = get_addon_config($addon['name']);
        if ($conf) {
            $conf['rewrite'] = isset($conf['rewrite']) && is_array($conf['rewrite']) ? $conf['rewrite'] : [];
            $rule = array_map(function ($value) use ($addon) {
                return "{$addon['name']}/{$value}";
            }, array_flip($conf['rewrite']));
            if ($url_domain_deploy && isset($conf['domain']) && $conf['domain']) {
                $domain[] = [
                    'addon'  => $addon['name'],
                    'domain' => $conf['domain'],
                    'rule'   => $rule
                ];
            } else {
                $route = array_merge($route, $rule);
            }
        }
    }
    $config['route'] = $route;
    $config['route'] = array_merge($config['route'], $domain);
    return $config;
}

/**
 * ============================================================
 * 获取插件类的完整类名（命名空间）
 * ============================================================
 *
 * 【功能说明】
 * 根据插件名和类型，生成完整的类名（含命名空间）
 * 用于动态实例化插件类
 *
 * 【参数说明】
 * @param string $name  插件名（如 example）
 * @param string $type  类型：'hook'=插件主类, 'controller'=控制器
 * @param string $class 指定类名（可选，默认与插件名同名）
 *
 * 【返回值】
 * - 成功：完整类名，如 "\addons\example\Example"
 * - 失败：空字符串（类不存在时）
 *
 * 【命名空间规则】
 * - 插件主类：\addons\{name}\{Name}
 * - 控制器类：\addons\{name}\controller\{Class}
 *
 * @return string 完整类名或空字符串
 */
function get_addon_class($name, $type = 'hook', $class = null)
{
    // 将插件名转为小写格式（统一规范）
    // Loader::parseName() 用于名称风格转换
    $name = Loader::parseName($name);

    // 处理多级控制器情况（如 admin.user → admin\User）
    if (!is_null($class) && strpos($class, '.')) {
        $class = explode('.', $class);
        // 最后一个元素转为大驼峰格式
        $class[count($class) - 1] = Loader::parseName(end($class), 1);
        $class = implode('\\', $class);
    } else {
        // 普通类名转为大驼峰格式
        // parseName($name, 1) : 转为大驼峰（example → Example）
        $class = Loader::parseName(is_null($class) ? $name : $class, 1);
    }

    // 根据类型生成完整命名空间
    switch ($type) {
        case 'controller':
            // 控制器类：\addons\example\controller\Index
            $namespace = "\\addons\\" . $name . "\\controller\\" . $class;
            break;
        default:
            // 插件主类：\addons\example\Example
            $namespace = "\\addons\\" . $name . "\\" . $class;
    }

    // 检查类是否存在，存在则返回类名，否则返回空字符串
    return class_exists($namespace) ? $namespace : '';
}

/**
 * ============================================================
 * 读取插件的基础信息（info.ini）
 * ============================================================
 *
 * 【功能说明】
 * 通过插件实例获取插件的基础信息
 * 信息来源于插件目录下的 info.ini 文件
 *
 * 【返回数据】
 * [
 *     'name'    => 'example',      // 插件标识
 *     'title'   => '示例插件',     // 插件名称
 *     'intro'   => '简介说明',     // 简介
 *     'author'  => '作者名',       // 作者
 *     'website' => 'https://...',  // 网站
 *     'version' => '1.0.0',        // 版本
 *     'state'   => 1,              // 状态：0=禁用, 1=启用
 * ]
 *
 * 【实现方式】
 * 通过 get_addon_instance() 获取插件单例
 * 调用插件实例的 getInfo() 方法获取信息
 *
 * @param string $name 插件名
 * @return array 插件信息数组，失败返回空数组
 */
function get_addon_info($name)
{
    // 获取插件实例（单例模式）
    $addon = get_addon_instance($name);
    if (!$addon) {
        return [];
    }
    // 调用插件实例的 getInfo() 方法
    // 该方法在插件基类 think\Addons 中定义
    return $addon->getInfo($name);
}

/**
 * ============================================================
 * 获取插件的完整配置数组（config.php）
 * ============================================================
 *
 * 【功能说明】
 * 获取插件配置的完整信息，包含每个配置项的所有属性
 * 用于后台配置表单的动态生成
 *
 * 【返回数据格式】
 * [
 *     [
 *         'name'    => 'api_key',        // 配置项名称（字段名）
 *         'title'   => 'API密钥',        // 显示标题
 *         'type'    => 'string',         // 输入类型
 *         'content' => [],               // 选项内容（select/radio/checkbox用）
 *         'value'   => 'xxx',            // 当前值
 *         'rule'    => 'required',       // 验证规则
 *         'tip'     => '提示说明',       // 帮助提示
 *         'extend'  => '',               // 扩展属性
 *     ],
 *     // ... 更多配置项
 * ]
 *
 * 【与 get_addon_config() 的区别】
 * - get_addon_fullconfig() : 返回完整配置数组（含 title, type, tip 等）
 * - get_addon_config()     : 只返回键值对（name => value）
 *
 * @param string $name 插件名
 * @return array 完整配置数组
 */
function get_addon_fullconfig($name)
{
    // 获取插件实例
    $addon = get_addon_instance($name);
    if (!$addon) {
        return [];
    }
    // 调用插件实例的 getFullConfig() 方法
    return $addon->getFullConfig($name);
}

/**
 * ============================================================
 * 获取插件配置的键值对
 * ============================================================
 *
 * 【功能说明】
 * 获取插件配置的简化版本，只返回配置名和配置值
 * 用于业务逻辑中直接读取配置值
 *
 * 【返回数据格式】
 * [
 *     'api_key'    => 'xxxxx',
 *     'api_secret' => 'yyyyy',
 *     'enabled'    => 1,
 *     // ... 更多配置
 * ]
 *
 * 【使用场景】
 * 在插件代码中读取配置：
 * $config = get_addon_config('example');
 * $apiKey = $config['api_key'];
 *
 * @param string $name 插件名
 * @return array 配置键值对数组
 */
function get_addon_config($name)
{
    // 获取插件实例
    $addon = get_addon_instance($name);
    if (!$addon) {
        return [];
    }
    // 调用插件实例的 getConfig() 方法
    return $addon->getConfig($name);
}

/**
 * ============================================================
 * 获取插件的单例实例
 * ============================================================
 *
 * 【功能说明】
 * 使用单例模式获取插件主类的实例
 * 避免重复实例化，提高性能
 *
 * 【单例实现】
 * 使用静态变量 $_addons 缓存已创建的实例
 * 相同插件名只会实例化一次
 *
 * 【实例化过程】
 * 1. 检查缓存中是否已存在该插件实例
 * 2. 不存在则通过 get_addon_class() 获取类名
 * 3. 实例化插件类并缓存
 *
 * @param string $name 插件名
 * @return mixed|null 插件实例或 null（插件不存在时）
 */
function get_addon_instance($name)
{
    // 静态变量存储插件实例缓存
    // 同一请求中相同插件只实例化一次
    static $_addons = [];

    // 如果已缓存，直接返回
    if (isset($_addons[$name])) {
        return $_addons[$name];
    }

    // 获取插件主类的完整类名
    $class = get_addon_class($name);

    // 检查类是否存在并实例化
    if (class_exists($class)) {
        // 创建实例并缓存
        $_addons[$name] = new $class();
        return $_addons[$name];
    } else {
        // 类不存在返回 null
        return null;
    }
}

/**
 * 插件显示内容里生成访问插件的url
 * @param string      $url    地址 格式：插件名/控制器/方法
 * @param array       $vars   变量参数
 * @param bool|string $suffix 生成的URL后缀
 * @param bool|string $domain 域名
 * @return bool|string
 */
function addon_url($url, $vars = [], $suffix = true, $domain = false)
{
    $url = ltrim($url, '/');
    $addon = substr($url, 0, stripos($url, '/'));
    if (!is_array($vars)) {
        parse_str($vars, $params);
        $vars = $params;
    }
    $params = [];
    foreach ($vars as $k => $v) {
        if (substr($k, 0, 1) === ':') {
            $params[$k] = $v;
            unset($vars[$k]);
        }
    }
    $val = "@addons/{$url}";
    $config = get_addon_config($addon);
    $dispatch = think\Request::instance()->dispatch();
    $indomain = isset($dispatch['var']['indomain']) && $dispatch['var']['indomain'] ? true : false;
    $domainprefix = $config && isset($config['domain']) && $config['domain'] ? $config['domain'] : '';
    $domain = $domainprefix && Config::get('url_domain_deploy') ? $domainprefix : $domain;
    $rewrite = $config && isset($config['rewrite']) && $config['rewrite'] ? $config['rewrite'] : [];
    if ($rewrite) {
        $path = substr($url, stripos($url, '/') + 1);
        if (isset($rewrite[$path]) && $rewrite[$path]) {
            $val = $rewrite[$path];
            array_walk($params, function ($value, $key) use (&$val) {
                $val = str_replace("[{$key}]", $value, $val);
            });
            $val = str_replace(['^', '$'], '', $val);
            if (substr($val, -1) === '/') {
                $suffix = false;
            }
        } else {
            // 如果采用了域名部署,则需要去掉前两段
            if ($indomain && $domainprefix) {
                $arr = explode("/", $val);
                $val = implode("/", array_slice($arr, 2));
            }
        }
    } else {
        // 如果采用了域名部署,则需要去掉前两段
        if ($indomain && $domainprefix) {
            $arr = explode("/", $val);
            $val = implode("/", array_slice($arr, 2));
        }
        foreach ($params as $k => $v) {
            $vars[substr($k, 1)] = $v;
        }
    }
    $url = url($val, [], $suffix, $domain) . ($vars ? '?' . http_build_query($vars) : '');
    $url = preg_replace("/\/((?!index)[\w]+)\.php\//i", "/", $url);
    return $url;
}

/**
 * 设置基础配置信息
 * @param string $name  插件名
 * @param array  $array 配置数据
 * @return boolean
 * @throws Exception
 */
function set_addon_info($name, $array)
{
    $file = ADDON_PATH . $name . DIRECTORY_SEPARATOR . 'info.ini';
    $addon = get_addon_instance($name);
    $array = $addon->setInfo($name, $array);
    if (!isset($array['name']) || !isset($array['title']) || !isset($array['version'])) {
        throw new Exception("插件配置写入失败");
    }
    $res = array();
    foreach ($array as $key => $val) {
        if (is_array($val)) {
            $res[] = "[$key]";
            foreach ($val as $skey => $sval) {
                $res[] = "$skey = " . (is_numeric($sval) ? $sval : $sval);
            }
        } else {
            $res[] = "$key = " . (is_numeric($val) ? $val : $val);
        }
    }
    if ($handle = fopen($file, 'w')) {
        fwrite($handle, implode("\n", $res) . "\n");
        fclose($handle);
        //清空当前配置缓存
        Config::set($name, null, 'addoninfo');
    } else {
        throw new Exception("文件没有写入权限");
    }
    return true;
}

/**
 * 写入配置文件
 * @param string  $name      插件名
 * @param array   $config    配置数据
 * @param boolean $writefile 是否写入配置文件
 * @return bool
 * @throws Exception
 */
function set_addon_config($name, $config, $writefile = true)
{
    $addon = get_addon_instance($name);
    $addon->setConfig($name, $config);
    $fullconfig = get_addon_fullconfig($name);
    foreach ($fullconfig as $k => &$v) {
        if (isset($config[$v['name']])) {
            $value = $v['type'] !== 'array' && is_array($config[$v['name']]) ? implode(',', $config[$v['name']]) : $config[$v['name']];
            $v['value'] = $value;
        }
    }
    if ($writefile) {
        // 写入配置文件
        set_addon_fullconfig($name, $fullconfig);
    }
    return true;
}

/**
 * 写入配置文件
 *
 * @param string $name  插件名
 * @param array  $array 配置数据
 * @return boolean
 * @throws Exception
 */
function set_addon_fullconfig($name, $array)
{
    $file = ADDON_PATH . $name . DIRECTORY_SEPARATOR . 'config.php';
    if (!is_really_writable($file)) {
        throw new Exception("文件没有写入权限");
    }
    if ($handle = fopen($file, 'w')) {
        fwrite($handle, "<?php\n\n" . "return " . var_export($array, true) . ";\n");
        fclose($handle);
    } else {
        throw new Exception("文件没有写入权限");
    }
    return true;
}
