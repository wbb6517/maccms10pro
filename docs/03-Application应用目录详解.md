# Application 应用目录详解

本文档详细讲解 `application/` 目录的结构和各子目录的作用。

## 应用目录结构

```
application/                       # 应用目录
├── .htaccess                      # Apache重写规则 (禁止直接访问)
│
├── common.php                     # 公共函数文件 (96KB - 所有模块共享)
├── config.php                     # 应用配置文件 (覆盖框架默认配置)
├── database.php                   # 数据库配置文件
├── route.php                      # 路由配置文件
├── tags.php                       # 行为定义文件
├── command.php                    # 命令行定义文件
│
├── common/                        # 公共模块 (禁止URL访问)
│   ├── behavior/                  # 行为类
│   ├── controller/                # 公共控制器基类
│   ├── model/                     # 数据模型 (核心业务逻辑)
│   ├── taglib/                    # 自定义模板标签库
│   ├── validate/                  # 验证器
│   ├── util/                      # 工具类
│   └── extend/                    # 扩展类
│
├── admin/                         # 后台管理模块
│   ├── controller/                # 后台控制器
│   ├── view/                      # 后台视图 (旧版)
│   ├── view_new/                  # 后台视图 (新版)
│   ├── lang/                      # 语言包
│   └── common/                    # 模块公共文件
│
├── index/                         # 前台展示模块
│   ├── controller/                # 前台控制器
│   ├── view/                      # 前台视图
│   └── event/                     # 事件处理
│
├── api/                           # API接口模块
│   ├── controller/                # API控制器
│   └── validate/                  # API验证器
│
├── install/                       # 安装模块
│   ├── controller/
│   ├── view/
│   └── sql/                       # 安装SQL文件
│
├── extra/                         # 扩展配置目录
│   ├── maccms.php                 # 主业务配置 (最重要)
│   ├── vodplayer.php              # 播放器配置
│   ├── vodserver.php              # 视频源配置
│   └── ...                        # 其他扩展配置
│
├── data/                          # 数据目录
│   ├── backup/                    # 数据库备份
│   ├── install/                   # 安装锁文件
│   └── update/                    # 更新文件
│
└── lang/                          # 全局语言包
```

## 公共模块 common/

公共模块是所有模块共享的代码库，`deny_module_list` 配置禁止通过URL直接访问。

### common/behavior/ - 行为类

行为是 ThinkPHP 的钩子系统，在应用生命周期的特定节点执行：

```
behavior/
├── Init.php                       # 应用初始化行为 (app_init)
└── Begin.php                      # 应用开始行为 (app_begin)
```

**Init.php 示例：**
```php
namespace app\common\behavior;

class Init {
    public function run() {
        // 定义全局常量 MAC_*
        // 加载配置
        // 初始化应用环境
    }
}
```

**行为注册 (tags.php)：**
```php
return [
    'app_init'  => ['app\common\behavior\Init'],
    'app_begin' => ['app\common\behavior\Begin'],
];
```

### common/model/ - 数据模型

模型是业务逻辑的核心，封装数据库操作和业务规则：

```
model/
├── Base.php                       # 模型基类
├── Vod.php                        # 视频模型 (37KB)
├── Art.php                        # 文章模型 (22KB)
├── Actor.php                      # 演员模型 (17KB)
├── Topic.php                      # 专题模型 (22KB)
├── User.php                       # 用户模型 (37KB)
├── Collect.php                    # 采集模型 (117KB - 最大)
├── Type.php                       # 分类模型
├── Comment.php                    # 评论模型
├── Gbook.php                      # 留言模型
├── Role.php                       # 角色模型
├── Admin.php                      # 管理员模型
├── Group.php                      # 用户组模型
├── Link.php                       # 友链模型
├── Order.php                      # 订单模型
├── Card.php                       # 卡密模型
├── Cash.php                       # 提现模型
├── Upload.php                     # 上传模型
├── Website.php                    # 网址模型
├── Addon.php                      # 插件模型
├── Annex.php                      # 附件模型
├── Extend.php                     # 扩展模型
├── Image.php                      # 图片模型
├── Make.php                       # 生成模型
├── Msg.php                        # 消息模型
├── Plog.php                       # 积分日志模型
├── Ulog.php                       # 用户日志模型
├── Visit.php                      # 访问统计模型
├── Cj.php                         # 采集配置模型
└── VodSearch.php                  # 视频搜索模型
```

**模型使用示例：**
```php
// 控制器中使用
$vodModel = model('Vod');
$list = $vodModel->listData($param);

// 或者静态调用
\app\common\model\Vod::get(1);
```

### common/taglib/ - 模板标签库

自定义模板标签，继承 ThinkPHP 的 TagLib 基类：

```
taglib/
└── Maccms.php                     # 苹果CMS自定义标签库
```

**标签定义：**
```php
protected $tags = [
    'vod'     => ['attr' => 'order,by,start,num,id,ids,type,...'],
    'art'     => ['attr' => 'order,by,start,num,...'],
    'actor'   => ['attr' => 'order,by,start,num,...'],
    'topic'   => ['attr' => 'order,by,start,num,...'],
    'type'    => ['attr' => 'order,by,start,num,...'],
    'comment' => ['attr' => 'order,by,start,num,...'],
    'for'     => ['attr' => 'start,end,step,name'],
    'foreach' => ['attr' => 'name,id,key'],
];
```

**模板使用：**
```html
{maccms:vod num="10" order="time" by="desc" type="1"}
    <li>{$vo.vod_name}</li>
{/maccms:vod}
```

### common/validate/ - 验证器

数据验证规则定义：

```php
namespace app\common\validate;

class User extends \think\Validate {
    protected $rule = [
        'name'  => 'require|max:25',
        'email' => 'email',
    ];

    protected $message = [
        'name.require' => '用户名必须',
        'email'        => '邮箱格式错误',
    ];
}
```

### common/util/ - 工具类

通用工具类库：

```
util/
├── Database.php                   # 数据库备份工具
├── Download.php                   # 下载工具
├── PclZip.php                     # ZIP压缩工具 (203KB)
├── Qrcode.php                     # 二维码生成 (122KB)
└── ...
```

## 模块目录结构

每个模块（admin/index/api）遵循相同的目录结构规范：

```
模块名/
├── controller/                    # 控制器目录
│   ├── Base.php                   # 控制器基类
│   ├── Index.php                  # 首页控制器
│   └── ...                        # 其他控制器
│
├── view/                          # 视图目录 (HTML模板)
│   ├── index/                     # Index控制器视图
│   │   ├── index.html             # index操作视图
│   │   └── ...
│   └── ...
│
├── model/                         # 模块专用模型 (可选)
├── validate/                      # 模块专用验证器 (可选)
├── lang/                          # 模块语言包 (可选)
└── common/                        # 模块公共文件 (可选)
```

## admin 模块详解

后台管理模块，通过 `admin.php` 入口访问：

```
admin/controller/                  # 39个后台控制器
├── Base.php                       # 后台基类 (权限验证)
├── Index.php                      # 后台首页
├── System.php                     # 系统设置
├── Vod.php                        # 视频管理
├── Art.php                        # 文章管理
├── Actor.php                      # 演员管理
├── Topic.php                      # 专题管理
├── Type.php                       # 分类管理
├── Comment.php                    # 评论管理
├── Gbook.php                      # 留言管理
├── User.php                       # 用户管理
├── Admin.php                      # 管理员管理
├── Group.php                      # 用户组管理
├── Collect.php                    # 采集管理
├── Database.php                   # 数据库管理
├── Template.php                   # 模板管理
├── Upload.php                     # 上传管理
├── Vodplayer.php                  # 播放器管理
├── Vodserver.php                  # 视频源管理
├── Link.php                       # 友链管理
├── Order.php                      # 订单管理
├── Card.php                       # 卡密管理
├── Cash.php                       # 提现管理
├── Make.php                       # 生成管理
├── Addon.php                      # 插件管理
├── Domain.php                     # 域名管理
├── Safety.php                     # 安全设置
├── Update.php                     # 更新管理
├── Timming.php                    # 定时任务
├── Urlsend.php                    # URL推送
├── Visit.php                      # 访问统计
├── Plog.php                       # 积分日志
├── Ulog.php                       # 用户日志
├── Role.php                       # 角色管理
├── Website.php                    # 网址管理
├── Images.php                     # 图片管理
├── Annex.php                      # 附件管理
├── Cj.php                         # 自定义采集
└── Voddowner.php                  # 下载器管理
```

## api 模块详解

API接口模块，通过 `api.php` 入口访问：

```
api/controller/                    # 17个API控制器
├── Base.php                       # API基类
├── PublicApi.php                  # 公共API Trait
├── Provide.php                    # 数据提供接口 (35KB)
├── Receive.php                    # 数据接收接口
├── Index.php                      # 首页接口
├── Vod.php                        # 视频接口
├── Art.php                        # 文章接口
├── Actor.php                      # 演员接口
├── Topic.php                      # 专题接口
├── Type.php                       # 分类接口
├── Comment.php                    # 评论接口
├── Gbook.php                      # 留言接口
├── User.php                       # 用户接口
├── Link.php                       # 友链接口
├── Website.php                    # 网址接口
├── Wechat.php                     # 微信接口
└── Timming.php                    # 定时任务接口
```

**API调用示例：**
```
GET /api.php/vod/get_list/?limit=10&type_id=1
GET /api.php/vod/get_detail/?vod_id=123
GET /api.php/actor/get_list/?sex=女
```

## index 模块详解

前台展示模块，通过 `index.php` 入口访问：

```
index/controller/                  # 前台控制器
├── Base.php                       # 前台基类
├── Index.php                      # 首页
├── Vod.php                        # 视频页
├── Art.php                        # 文章页
├── Actor.php                      # 演员页
├── Topic.php                      # 专题页
├── Gbook.php                      # 留言页
├── User.php                       # 用户中心
├── Comment.php                    # 评论
├── Map.php                        # 网站地图
├── Rss.php                        # RSS订阅
└── ...
```

## extra/ 扩展配置目录

独立的配置文件，通过 `config('文件名')` 访问：

```
extra/
├── maccms.php                     # 主业务配置 (18KB)
├── vodplayer.php                  # 播放器配置
├── vodserver.php                  # 视频源配置
├── voddowner.php                  # 下载器配置
├── addons.php                     # 插件配置
├── version.php                    # 版本信息
├── timming.php                    # 定时任务配置
├── captcha.php                    # 验证码配置
├── queue.php                      # 队列配置
├── quickmenu.php                  # 快捷菜单配置
├── domain.php                     # 域名配置
├── blacks.php                     # 黑名单配置
└── bind.php                       # 绑定配置
```

**配置使用：**
```php
// 获取 extra/maccms.php 配置
$config = config('maccms');

// 获取具体配置项
$siteName = config('maccms.site_name');
```

## 下一步

- [04-配置文件详解.md](./04-配置文件详解.md) - 了解配置系统
- [05-请求生命周期.md](./05-请求生命周期.md) - 了解请求处理流程