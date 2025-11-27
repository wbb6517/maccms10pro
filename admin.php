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
 * 后台管理入口文件 (Admin Entry Point)
 * ============================================================
 *
 * 【文件说明】
 * 这是MacCMS后台管理系统的入口文件，所有后台请求都从这里开始。
 *
 * 【访问方式】
 * http://yourdomain.com/admin.php
 * http://yourdomain.com/admin.php/index/index
 * http://yourdomain.com/admin.php/vod/data  (视频管理)
 *
 * 【安全建议】
 * 生产环境中应将此文件重命名为不易猜测的名称，如：
 * - myadmin.php
 * - manage_xxx.php
 * - 任意自定义名称.php
 *
 * 【开发模式】
 * 设置 MAC_DEV_MODE = true 可跳过入口文件名检测，方便本地开发调试
 *
 * ============================================================
 */

// ============================================================
// 【开发模式开关】
// 设置为 true 时跳过 admin.php 文件名检测，方便本地开发
// 生产环境请务必设置为 false 或删除此行！
// ============================================================
define('MAC_DEV_MODE', true);

// ============================================================
// 【响应头设置】
// 设置响应内容类型为HTML，字符集UTF-8
// ============================================================
header('Content-Type:text/html;charset=utf-8');

// ============================================================
// 【PHP版本检测】
// MacCMS要求PHP版本 >= 5.5.0
// 如果版本过低，直接终止并提示升级
// ============================================================
if(version_compare(PHP_VERSION,'5.5.0','<'))  die('PHP版本需要>=5.5，请升级【PHP version requires > = 5.5，please upgrade】');

// ============================================================
// 【PHP运行参数配置】
// ============================================================
// 设置脚本最大执行时间为无限制 (0 = 不限制)
// 后台可能有耗时操作如：采集、数据导入导出、批量处理等
@ini_set('max_execution_time', '0');

// 取消内存限制 (-1 = 不限制)
// 处理大量数据时需要更多内存
@ini_set("memory_limit",'-1');

// ============================================================
// 【核心路径常量定义】
// 这些常量在整个应用中被广泛使用
// ============================================================

// 网站根目录 (包含 index.php, admin.php 等入口文件)
// 例如: /www/wwwroot/maccms/
define('ROOT_PATH', __DIR__ . '/');

// 应用目录 (包含 admin/, index/, api/, common/ 等模块)
// 例如: /www/wwwroot/maccms/application/
define('APP_PATH', __DIR__ . '/application/');

// 公共模块的公共函数目录
// 例如: /www/wwwroot/maccms/application/common/common/
define('MAC_COMM', __DIR__.'/application/common/common/');

// 前台模块的公共函数目录
// 例如: /www/wwwroot/maccms/application/index/common/
define('MAC_HOME_COMM', __DIR__.'/application/index/common/');

// 后台模块的公共函数目录
// 例如: /www/wwwroot/maccms/application/admin/common/
define('MAC_ADMIN_COMM', __DIR__.'/application/admin/common/');

// 记录请求开始时间 (微秒级)
// 用于计算页面执行时间，性能监控
define('MAC_START_TIME', microtime(true) );

// ============================================================
// 【入口标识常量】
// ============================================================
// 模块绑定 (已注释，如果取消注释则只能访问admin模块)
//define('BIND_MODULE','admin');

// 入口标识: 'admin' 表示当前是后台入口
// 在应用中可通过 ENTRANCE 常量判断当前入口类型
// 可能的值: 'admin' (后台), 'index' (前台), 'api' (接口)
define('ENTRANCE', 'admin');

// ============================================================
// 【入口文件路径处理】
// 获取并规范化当前入口文件路径
// ============================================================
// 获取脚本名称，去除末尾斜杠
// 例如: /admin.php 或 /subdir/admin.php
$in_file = rtrim($_SERVER['SCRIPT_NAME'],'/');

// 处理特殊情况：某些服务器配置下 SCRIPT_NAME 可能包含额外路径信息
// 确保只获取 .php 文件部分
if(substr($in_file,strlen($in_file)-4)!=='.php'){
    $in_file = substr($in_file,0,strpos($in_file,'.php')) .'.php';
}

// 定义入口文件常量，供后续使用
// 例如: /admin.php
define('IN_FILE',$in_file);

// ============================================================
// 【安装检测】
// 检查系统是否已安装，未安装则跳转到安装页面
// ============================================================
// install.lock 文件在安装完成后由 step5() 方法创建
// 文件路径: application/data/install/install.lock
if(!is_file('./application/data/install/install.lock')) {
    // 未找到安装锁定文件，重定向到安装程序
    header("Location: ./install.php");
    exit;
}

// ============================================================
// 【入口文件名安全检测】
// 防止使用默认的 admin.php 文件名，避免被恶意扫描和攻击
// ============================================================
// 检测 SCRIPT_NAME 中是否包含 '/admin.php'
// 如果是，说明用户没有重命名入口文件，存在安全风险
//
// 【为什么需要重命名？】
// 1. admin.php 是众所周知的后台入口名称
// 2. 黑客常用自动化工具扫描 /admin.php, /manage.php 等常见路径
// 3. 重命名后可以有效防止被扫描发现后台入口
//
// 【开发模式】
// MAC_DEV_MODE = true 时跳过此检测，方便本地开发
if(strpos($_SERVER["SCRIPT_NAME"],'/admin.php')!==false){
    // 检查是否开启开发模式
    if(!defined('MAC_DEV_MODE') || MAC_DEV_MODE !== true) {
        echo '请将后台入口文件admin.php改名,避免被黑客入侵攻击【Please rename the background entry file admin.php to avoid being hacked】';
        exit;
    }
    // 开发模式下显示提示但不阻止访问
    // 可以在这里添加开发模式的警告日志
}

// ============================================================
// 【PATH_INFO 编码处理】
// 处理 URL 路径信息的字符编码问题
// ============================================================
// 某些服务器环境下 PATH_INFO 可能不是 UTF-8 编码
// 特别是在 Windows 服务器 + GBK 编码环境下
// 这里进行检测并转换，确保 ThinkPHP 能正确解析路由
if (!@mb_check_encoding($_SERVER['PATH_INFO'], 'utf-8')){
    // 如果不是有效的 UTF-8，尝试从 GBK 转换为 UTF-8
    $_SERVER['PATH_INFO']=@mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', 'GBK');
}

// ============================================================
// 【加载ThinkPHP框架】
// 引入框架引导文件，启动应用
// ============================================================
// start.php 会完成以下工作:
// 1. 加载 Loader 类，注册自动加载
// 2. 加载基础配置文件
// 3. 加载应用配置
// 4. 执行 App::run() 启动应用
// 5. 路由解析 -> 控制器调度 -> 响应输出
require __DIR__ . '/thinkphp/start.php';
