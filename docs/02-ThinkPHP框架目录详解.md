# ThinkPHP 框架目录详解

本文档详细讲解 `thinkphp/` 目录下的框架核心文件。

## 框架目录结构

```
thinkphp/                          # ThinkPHP 5.0.24 框架目录
├── base.php                       # 基础文件 - 定义常量、注册自动加载
├── start.php                      # 引导文件 - 启动应用
├── convention.php                 # 惯例配置 - 框架默认配置
├── helper.php                     # 助手函数 - 全局辅助函数
├── console.php                    # 控制台入口
│
├── library/                       # 核心类库目录
│   ├── think/                     # think 命名空间核心类
│   └── traits/                    # traits 复用代码
│
├── lang/                          # 框架语言包
│   ├── zh-cn.php                  # 中文语言包
│   └── en-us.php                  # 英文语言包
│
├── tpl/                           # 框架模板
│   ├── dispatch_jump.tpl          # 跳转页面模板
│   ├── think_exception.tpl        # 异常页面模板
│   └── page_trace.tpl             # 页面Trace模板
│
└── vendor/                        # 框架依赖 (Composer)
```

## 核心文件详解

### base.php - 基础引导

框架的基础文件，定义了所有核心常量：

```php
// 框架版本
define('THINK_VERSION', '5.0.24');

// 路径常量
define('THINK_PATH', __DIR__ . DS);           // 框架目录
define('LIB_PATH', THINK_PATH . 'library');   // 类库目录
define('CORE_PATH', LIB_PATH . 'think');      // 核心类目录
define('TRAIT_PATH', LIB_PATH . 'traits');    // trait目录
define('APP_PATH', ...);                       // 应用目录
define('ROOT_PATH', ...);                      // 根目录
define('EXTEND_PATH', ROOT_PATH . 'extend');  // 扩展目录
define('VENDOR_PATH', ROOT_PATH . 'vendor');  // Composer目录
define('RUNTIME_PATH', ROOT_PATH . 'runtime'); // 运行时目录
define('LOG_PATH', RUNTIME_PATH . 'log');     // 日志目录
define('CACHE_PATH', RUNTIME_PATH . 'cache'); // 缓存目录
define('TEMP_PATH', RUNTIME_PATH . 'temp');   // 临时目录

// 环境常量
define('IS_CLI', PHP_SAPI == 'cli');          // 是否命令行
define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);  // 是否Windows

// 注册自动加载
\think\Loader::register();

// 注册错误和异常处理
\think\Error::register();

// 加载惯例配置
\think\Config::set(include THINK_PATH . 'convention.php');
```

### start.php - 应用启动

```php
// 1. 加载基础文件
require __DIR__ . '/base.php';

// 2. 执行应用并发送响应
App::run()->send();
```

### convention.php - 惯例配置

框架的默认配置，应用配置会覆盖这些默认值：

```php
return [
    // 应用设置
    'app_debug'              => false,        // 调试模式
    'app_trace'              => false,        // Trace调试
    'app_multi_module'       => true,         // 多模块支持
    'default_return_type'    => 'html',       // 默认输出类型
    'default_ajax_return'    => 'json',       // AJAX返回格式
    'default_timezone'       => 'PRC',        // 时区设置

    // 模块设置
    'default_module'         => 'index',      // 默认模块
    'deny_module_list'       => ['common'],   // 禁止访问的模块
    'default_controller'     => 'Index',      // 默认控制器
    'default_action'         => 'index',      // 默认操作

    // URL设置
    'url_route_on'           => true,         // 开启路由
    'url_html_suffix'        => 'html',       // URL伪静态后缀

    // 模板设置
    'template' => [
        'type'         => 'Think',            // 模板引擎
        'view_suffix'  => 'html',             // 模板后缀
        'tpl_begin'    => '{',                // 标签开始
        'tpl_end'      => '}',                // 标签结束
    ],

    // 数据库设置
    'database' => [
        'type'     => 'mysql',
        'hostname' => '127.0.0.1',
        'charset'  => 'utf8',
        'prefix'   => '',
    ],

    // 缓存设置
    'cache' => [
        'type'   => 'File',
        'path'   => CACHE_PATH,
        'expire' => 0,
    ],

    // 日志设置
    'log' => [
        'type'  => 'File',
        'path'  => LOG_PATH,
    ],
];
```

### helper.php - 助手函数

提供全局辅助函数，简化常用操作：

```php
// 配置获取/设置
config('app_debug');
config('database.hostname');

// 数据库操作
db('user')->where('id', 1)->find();

// 模型实例化
model('User')->find(1);

// 控制器实例化
controller('User');

// 验证器实例化
validate('User');

// 缓存操作
cache('key', 'value');
cache('key');

// Session操作
session('name', 'value');
session('name');

// Cookie操作
cookie('name', 'value');
cookie('name');

// 获取输入参数
input('get.id');
input('post.name');

// URL生成
url('index/user/info', ['id' => 1]);

// JSON响应
json(['code' => 1, 'msg' => 'success']);

// 页面跳转
redirect('/user/login');

// 异常抛出
abort(404, '页面不存在');

// 调试输出
dump($data);
halt($data);
```

## library/think/ 核心类目录

```
library/think/
├── App.php            # 应用类 - 应用初始化和执行
├── Controller.php     # 控制器基类
├── Model.php          # 模型基类 (ORM)
├── Db.php             # 数据库类
├── View.php           # 视图类
├── Template.php       # 模板引擎
├── Request.php        # 请求类
├── Response.php       # 响应类
├── Route.php          # 路由类
├── Config.php         # 配置类
├── Session.php        # Session类
├── Cookie.php         # Cookie类
├── Cache.php          # 缓存类
├── Log.php            # 日志类
├── Validate.php       # 验证类
├── Lang.php           # 语言类
├── Loader.php         # 自动加载类
├── Error.php          # 错误处理类
├── Exception.php      # 异常基类
├── Hook.php           # 钩子类 (行为)
├── Url.php            # URL生成类
├── File.php           # 文件类
├── Paginator.php      # 分页类
├── Debug.php          # 调试类
├── Console.php        # 控制台类
├── Process.php        # 进程类
├── Build.php          # 自动生成类
├── Env.php            # 环境变量类
├── Collection.php     # 集合类
│
├── db/                # 数据库驱动
│   ├── Query.php      # 查询构造器
│   ├── Builder.php    # SQL构建器
│   └── connector/     # 数据库连接器
│
├── cache/             # 缓存驱动
│   ├── Driver.php
│   ├── driver/File.php
│   ├── driver/Redis.php
│   └── driver/Memcache.php
│
├── log/               # 日志驱动
│   └── driver/File.php
│
├── session/           # Session驱动
│   └── driver/
│
├── template/          # 模板驱动
│   └── TagLib.php     # 标签库基类
│
├── model/             # 模型相关
│   └── Relation.php   # 关联模型
│
├── response/          # 响应类型
│   ├── Json.php
│   ├── Xml.php
│   └── Redirect.php
│
├── exception/         # 异常类
│   ├── Handle.php
│   ├── HttpException.php
│   └── ValidateException.php
│
├── console/           # 控制台命令
│   └── command/
│
└── paginator/         # 分页驱动
    └── driver/Bootstrap.php
```

## 核心类功能说明

### App.php - 应用核心

```php
// 应用执行流程
App::run()
    → 初始化应用
    → 加载配置
    → 注册行为
    → 路由调度
    → 执行控制器
    → 返回响应
```

### Request.php - 请求处理

```php
$request = Request::instance();

// 获取请求信息
$request->module();      // 当前模块
$request->controller();  // 当前控制器
$request->action();      // 当前操作
$request->method();      // 请求方法 GET/POST

// 获取参数
$request->get('id');
$request->post('name');
$request->param('key');
$request->only(['id', 'name']);

// 判断请求类型
$request->isGet();
$request->isPost();
$request->isAjax();
```

### Model.php - ORM模型

```php
// 模型继承
class User extends Model {
    protected $table = 'mac_user';
    protected $pk = 'user_id';
}

// 查询
User::get(1);
User::where('status', 1)->select();

// 新增
$user = new User;
$user->name = 'test';
$user->save();

// 更新
User::update(['name' => 'new'], ['id' => 1]);

// 删除
User::destroy(1);
```

### Db.php - 数据库操作

```php
// 查询构造器
Db::name('user')
    ->where('status', 1)
    ->order('id desc')
    ->limit(10)
    ->select();

// 原生SQL
Db::query('SELECT * FROM mac_user WHERE id = ?', [1]);
Db::execute('UPDATE mac_user SET status = 1');
```

### Hook.php - 行为钩子

```php
// 注册钩子
Hook::add('app_init', 'app\common\behavior\Init');

// 触发钩子
Hook::listen('app_init');
```

## 下一步

- [03-Application应用目录详解.md](./03-Application应用目录详解.md) - 了解业务代码组织
- [04-配置文件详解.md](./04-配置文件详解.md) - 了解配置系统