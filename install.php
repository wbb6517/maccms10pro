<?php
/*
'软件名称：苹果CMS 源码库：https://github.com/magicblack
'--------------------------------------------------------
'Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
'遵循Apache2开源协议发布，并提供免费使用。
'--------------------------------------------------------
*/

/**
 * ============================================================
 * 【install.php 安装入口文件 - 页面加载流程说明】
 * ============================================================
 *
 * 当前浏览器页面: http://localhost:8080/install.php
 *
 * 完整加载流程:
 * ┌─────────────────────────────────────────────────────────┐
 * │ 1. 用户访问 install.php                                  │
 * │    ↓                                                     │
 * │ 2. 定义 BIND_MODULE = 'install' (绑定安装模块)           │
 * │    ↓                                                     │
 * │ 3. require thinkphp/start.php (加载框架)                 │
 * │    ↓                                                     │
 * │ 4. App::run() 根据 BIND_MODULE 找到 install 模块         │
 * │    ↓                                                     │
 * │ 5. 默认访问 install/controller/Index.php 的 index()     │
 * │    ↓                                                     │
 * │ 6. index() 方法准备数据并渲染视图                         │
 * │    - 获取语言包列表 $langs                               │
 * │    - 调用 $this->fetch('install@/index/index')          │
 * │    ↓                                                     │
 * │ 7. 加载模板 application/install/view/index/index.html   │
 * │    ↓                                                     │
 * │ 8. 模板引擎解析变量和标签，生成 HTML                      │
 * │    ↓                                                     │
 * │ 9. 输出 HTML 到浏览器                                    │
 * └─────────────────────────────────────────────────────────┘
 *
 * 关键文件映射:
 * - 入口文件: install.php (当前文件)
 * - 控制器:   application/install/controller/Index.php
 * - 视图:     application/install/view/index/index.html
 * - 语言包:   application/lang/zh-cn.php
 * ============================================================
 */

header('Content-Type:text/html;charset=utf-8');

// 检测PHP环境
if(version_compare(PHP_VERSION,'5.5.0','<'))  die('PHP版本需要>=5.5，请升级【PHP version requires > = 5.5，please upgrade】');

//超时时间
@ini_set('max_execution_time', '0');

//内存限制 取消内存限制
@ini_set("memory_limit",'-1');

// 定义应用目录常量
define('ROOT_PATH', __DIR__ . '/');
define('APP_PATH', __DIR__ . '/application/');
define('MAC_COMM', __DIR__.'/application/common/common/');
define('MAC_HOME_COMM', __DIR__.'/application/index/common/');
define('MAC_ADMIN_COMM', __DIR__.'/application/admin/common/');
define('MAC_START_TIME', microtime(true) );

/**
 * ★★★ 核心：绑定模块为 'install' ★★★
 *
 * 这个常量决定了 ThinkPHP 框架会加载哪个模块:
 * - BIND_MODULE = 'install' → 加载 application/install/ 目录
 * - BIND_MODULE = 'index'   → 加载 application/index/ 目录
 * - BIND_MODULE = 'admin'   → 加载 application/admin/ 目录
 *
 * 框架根据此常量，自动路由到:
 * application/{BIND_MODULE}/controller/Index.php 的 index() 方法
 */
define('BIND_MODULE', 'install');
define('ENTRANCE', 'install');

$in_file = rtrim($_SERVER['SCRIPT_NAME'],'/');
if(substr($in_file,strlen($in_file)-4)!=='.php'){
    $in_file = substr($in_file,0,strpos($in_file,'.php')) .'.php';
}
define('IN_FILE',$in_file);

/**
 * 检查是否已安装 (与 index.php 相反的逻辑)
 * - 如果 install.lock 存在 → 已安装，禁止重复安装
 * - 如果 install.lock 不存在 → 未安装，允许继续
 */
if(is_file('./application/data/install/install.lock')) {
	echo '如需重新安装请删除【To re install, please remove】 >>> /application/data/install/install.lock';
	exit;
}

// 检查 runtime 目录写权限
if(!is_writable('./runtime')) {
	echo '请开启[runtime]目录的读写权限【Please turn on the read and write permissions of the [runtime] folder】';
	exit;
}

/**
 * ★★★ 加载 ThinkPHP 框架并执行应用 ★★★
 *
 * start.php 内部执行:
 * 1. require base.php    - 定义常量、注册自动加载
 * 2. App::run()->send()  - 执行应用并输出响应
 *
 * App::run() 会:
 * 1. 读取 BIND_MODULE 常量，确定模块为 'install'
 * 2. 解析 URL，默认访问 Index 控制器的 index 方法
 * 3. 实例化 app\install\controller\Index
 * 4. 调用 index() 方法，获取返回的 HTML
 * 5. 通过 Response::send() 输出到浏览器
 */
require __DIR__ . '/thinkphp/start.php';
