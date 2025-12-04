# Admin 后台加载流程详解

## 访问 admin.php 到登录页面的完整流程

当访问 `http://localhost:8080/admin.php` 时，系统会经历以下步骤：

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        admin.php 访问流程图                                   │
└─────────────────────────────────────────────────────────────────────────────┘

     用户请求
         │
         ▼
┌─────────────────┐
│  1. admin.php   │  入口文件
│    (第1-40行)    │
└────────┬────────┘
         │ 定义常量: ROOT_PATH, APP_PATH, ENTRANCE='admin'
         │ 安全检测: install.lock, admin.php文件名检测
         ▼
┌─────────────────┐
│  2. start.php   │  ThinkPHP引导
│  (thinkphp/)    │
└────────┬────────┘
         │ require base.php (加载Loader类)
         │ App::run()->send()
         ▼
┌─────────────────┐
│  3. App::run()  │  应用启动
│  (thinkphp/     │
│   library/App)  │
└────────┬────────┘
         │ 1. 加载配置 (config, database, route)
         │ 2. 执行行为钩子 app_init, app_begin
         │ 3. 路由解析
         ▼
┌─────────────────┐
│  4. 路由解析    │  默认路由: admin/index/index
│                 │  URL无路径时使用默认值
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  5. Base::__construct()             │  基类构造函数
│  application/admin/controller/Base  │
│  (第14-40行)                         │
└────────┬────────────────────────────┘
         │ 检查是否是 Index/login 请求
         │ 如果不是 → 调用 model('Admin')->checkLogin()
         │ 未登录 → redirect('index/login')
         ▼
┌─────────────────────────────────────┐
│  6. Index::login()                  │  登录控制器方法
│  application/admin/controller/Index │
│  (第16-28行)                         │
└────────┬────────────────────────────┘
         │ GET请求: return $this->fetch('admin@index/login')
         │ POST请求: model('Admin')->login($data)
         ▼
┌─────────────────────────────────────┐
│  7. 渲染登录模板                      │
│  application/admin/view/index/      │
│  login.html                          │
└─────────────────────────────────────┘
```

---

## 详细代码解析

### 第1步：admin.php 入口文件

**文件路径**: `admin.php`

```php
// 核心常量定义
define('ROOT_PATH', __DIR__ . '/');           // 网站根目录
define('APP_PATH', __DIR__ . '/application/'); // 应用目录
define('ENTRANCE', 'admin');                   // 入口标识 ★重要

// 安装检测
if(!is_file('./application/data/install/install.lock')) {
    header("Location: ./install.php");
    exit;
}

// 入口文件名安全检测 (开发模式可跳过)
if(strpos($_SERVER["SCRIPT_NAME"],'/admin.php')!==false){
    if(!defined('MAC_DEV_MODE') || MAC_DEV_MODE !== true) {
        echo '请将后台入口文件admin.php改名...';
        exit;
    }
}

// 加载ThinkPHP框架
require __DIR__ . '/thinkphp/start.php';
```

**关键点**:
- `ENTRANCE = 'admin'` 标识当前是后台入口
- 安全检测确保系统已安装
- 开发模式 `MAC_DEV_MODE` 可绕过文件名检测

---

### 第2步：ThinkPHP 引导文件

**文件路径**: `thinkphp/start.php`

```php
namespace think;

// 1. 加载基础文件 (Loader类、常量定义等)
require __DIR__ . '/base.php';

// 2. 执行应用并发送响应
App::run()->send();
```

**App::run() 内部流程**:
1. 加载应用配置 (`application/config.php`)
2. 加载数据库配置 (`application/database.php`)
3. 加载路由配置 (`application/route.php`)
4. 执行行为钩子 `app_init` → 触发 `Init` 行为
5. 执行行为钩子 `app_begin` → 触发 `Begin` 行为
6. 路由解析 → 确定 模块/控制器/方法
7. 控制器调度 → 实例化控制器并执行方法
8. 响应输出

---

### 第3步：路由解析

**默认路由规则** (当URL无路径参数时):

```
admin.php          → admin 模块 / index 控制器 / index 方法
admin.php/vod/data → admin 模块 / vod 控制器 / data 方法
```

配置来源 (`application/config.php`):
```php
'default_module'     => 'index',      // 默认模块
'default_controller' => 'Index',      // 默认控制器
'default_action'     => 'index',      // 默认方法
```

但因为 `ENTRANCE='admin'`，会自动映射到 `admin` 模块。

---

### 第4步：Base 基类构造函数 (登录检测)

**文件路径**: `application/admin/controller/Base.php:14-40`

```php
public function __construct()
{
    parent::__construct();

    // ★ 关键逻辑：判断是否需要登录检测

    // 情况1: 访问登录页面本身，不需要检测
    if(in_array($this->_cl,['Index']) && in_array($this->_ac,['login'])) {
        // 跳过登录检测
    }
    // 情况2: API入口的定时任务，不需要检测
    elseif(ENTRANCE=='api' && in_array($this->_cl,['Timming']) && in_array($this->_ac,['index'])){
        // 跳过登录检测
    }
    // 情况3: 其他所有请求，需要检测登录状态
    else {
        // 调用 Admin 模型检查登录状态
        $res = model('Admin')->checkLogin();

        if ($res['code'] > 1) {
            // ★ 未登录 → 重定向到登录页面
            return $this->redirect('index/login');
        }

        // 已登录 → 保存管理员信息
        $this->_admin = $res['info'];

        // 权限检测
        if($this->_cl!='Update' && !$this->check_auth($this->_cl,$this->_ac)){
            return $this->error(lang('permission_denied'));
        }
    }
}
```

**$this->_cl 和 $this->_ac 来源** (All基类):
- `$this->_cl` = 当前控制器名 (如 'Index', 'Vod')
- `$this->_ac` = 当前方法名 (如 'login', 'index', 'data')

---

### 第5步：Index::login() 登录方法

**文件路径**: `application/admin/controller/Index.php:16-28`

```php
public function login()
{
    // POST请求: 处理登录表单提交
    if (Request()->isPost()) {
        $data = input('post.');

        // 调用 Admin 模型的 login 方法验证账号密码
        $res = model('Admin')->login($data);

        if ($res['code'] > 1) {
            return $this->error($res['msg']);  // 登录失败
        }
        return $this->success($res['msg']);    // 登录成功
    }

    // GET请求: 显示登录页面

    // 触发钩子 (可用于登录前的自定义逻辑)
    Hook::listen("admin_login_init", $this->request);

    // ★ 渲染登录模板
    return $this->fetch('admin@index/login');
}
```

**fetch() 模板路径解析**:
- `admin@index/login`
- = `application/admin/view/index/login.html`
- `admin@` 表示 admin 模块
- `index/login` 表示 view/index/login.html

---

### 第6步：登录模板渲染

**文件路径**: `application/admin/view/index/login.html`

模板包含:
- Layui 表单组件
- 账号输入框
- 密码输入框
- 验证码输入框 + 图片
- 登录按钮
- AJAX 表单提交

---

## 流程总结

```
┌────────────────────────────────────────────────────────────────────────┐
│                         完整请求生命周期                                 │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  admin.php                                                             │
│      │                                                                 │
│      ├── 定义 ENTRANCE = 'admin'                                       │
│      ├── 安全检测 (install.lock, 文件名)                                │
│      └── require thinkphp/start.php                                    │
│              │                                                         │
│              ├── require base.php (自动加载)                            │
│              └── App::run()->send()                                    │
│                      │                                                 │
│                      ├── 加载配置                                       │
│                      ├── 行为钩子 (Init, Begin)                         │
│                      ├── 路由解析 → admin/Index/index                   │
│                      └── 控制器调度                                     │
│                              │                                         │
│                              ▼                                         │
│                      Base::__construct()                               │
│                              │                                         │
│                              ├── checkLogin() 检测登录                  │
│                              ├── 未登录 → redirect('index/login')       │
│                              └── 再次路由到 Index::login()              │
│                                      │                                 │
│                                      └── fetch('admin@index/login')    │
│                                              │                         │
│                                              ▼                         │
│                                      渲染 login.html 模板               │
│                                              │                         │
│                                              ▼                         │
│                                      返回 HTML 响应                     │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 关键文件清单

| 序号 | 文件路径 | 说明 |
|-----|---------|------|
| 1 | `admin.php` | 后台入口文件 |
| 2 | `thinkphp/start.php` | 框架引导文件 |
| 3 | `thinkphp/library/think/App.php` | 应用核心类 |
| 4 | `application/tags.php` | 行为钩子定义 |
| 5 | `application/common/behavior/Init.php` | 初始化行为 |
| 6 | `application/common/behavior/Begin.php` | 开始行为 |
| 7 | `application/admin/controller/Base.php` | 后台基类控制器 |
| 8 | `application/admin/controller/Index.php` | 首页/登录控制器 |
| 9 | `application/common/model/Admin.php` | 管理员模型 |
| 10 | `application/admin/view/index/login.html` | 登录模板 |

---

## 登录检测逻辑

```php
// Base.php 第19-29行
if(in_array($this->_cl,['Index']) && in_array($this->_ac,['login'])) {
    // 访问登录页 → 跳过检测
}
else {
    $res = model('Admin')->checkLogin();
    if ($res['code'] > 1) {
        return $this->redirect('index/login');  // 未登录 → 跳转
    }
}
```

**检测白名单**:
- `Index/login` - 登录页面
- `Timming/index` - 定时任务 (仅API入口)

其他所有请求都需要登录后才能访问。