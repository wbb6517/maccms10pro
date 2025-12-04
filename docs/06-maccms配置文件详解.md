# MacCMS 主配置文件详解

## 文件信息

- **文件路径**: `application/extra/maccms.php`
- **文件类型**: PHP 数组配置文件
- **加载方式**: `config('maccms')` 或 `config('maccms.site.site_name')`
- **修改时机**: 安装时由 `step5()` 方法自动写入，后续通过后台管理界面修改

## 安装时的修改

在安装过程的 **第五步 (step5)** 中，系统会修改此文件：

```php
// Index.php:486-504
$config_new = config('maccms');
$config_new['app']['cache_flag'] = substr(md5(time()),0,10);  // 生成缓存标识
$config_new['app']['lang'] = session('lang');                  // 设置语言
$config_new['api']['vod']['status'] = 0;                       // 禁用视频API
$config_new['api']['art']['status'] = 0;                       // 禁用文章API
$config_new['interface']['status'] = 0;                        // 禁用接口
$config_new['interface']['pass'] = mac_get_rndstr(16);         // 生成16位随机密钥
$config_new['site']['install_dir'] = $install_dir;             // 设置安装目录

// 写入文件
mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
```

---

## 配置结构总览

```
maccms.php
├── db          # 数据库备份配置
├── site        # 站点基本信息
├── app         # 应用程序配置
├── user        # 用户系统配置
├── gbook       # 留言板配置
├── comment     # 评论系统配置
├── upload      # 上传配置
├── interface   # 数据接口配置
├── pay         # 支付配置
├── collect     # 采集配置
├── api         # API接口配置
├── connect     # 第三方登录配置
├── weixin      # 微信公众号配置
├── view        # 静态页面生成配置
├── path        # 静态页面路径配置
├── rewrite     # URL重写配置
├── email       # 邮件配置
├── play        # 播放器配置
├── sms         # 短信配置
├── extra       # 扩展配置
├── seo         # SEO配置
└── urlsend     # URL推送配置
```

---

## 详细配置说明

### 1. db - 数据库备份配置

```php
'db' => [
    'type' => 'mysql',           // 数据库类型
    'path' => '',                // 备份路径
    'server' => '127.0.0.1',     // 服务器地址
    'port' => '3306',            // 端口
    'name' => 'maccms10',        // 数据库名
    'user' => 'root',            // 用户名
    'pass' => 'root',            // 密码
    'tablepre' => 'mac_',        // 表前缀
    'backup_path' => './application/data/backup/database/',  // 备份存储路径
    'part_size' => 20971520,     // 分卷大小 (20MB)
    'compress' => 1,             // 是否压缩
    'compress_level' => 4,       // 压缩级别 (1-9)
]
```

> **注意**: 这是备份功能使用的配置，实际数据库连接配置在 `application/database.php`

---

### 2. site - 站点基本信息

```php
'site' => [
    'site_name' => '站点名称',           // 网站名称
    'site_url' => 'www.test.cn',         // 网站域名
    'site_wapurl' => 'wap.test.cn',      // 手机站域名
    'site_keywords' => '关键词',          // SEO关键词
    'site_description' => '描述',         // SEO描述
    'site_icp' => 'icp123',              // ICP备案号
    'site_qq' => '123456',               // 联系QQ
    'site_email' => '123456@test.cn',    // 联系邮箱
    'install_dir' => '/',                // ★ 安装目录 (安装时写入)
    'site_logo' => 'static/images/logo.jpg',      // PC端Logo
    'site_waplogo' => 'static/images/logo.jpg',   // 手机端Logo
    'template_dir' => 'default',         // PC端模板目录
    'html_dir' => 'html',                // 静态页面目录
    'mob_status' => '0',                 // 手机站开关
    'mob_template_dir' => 'default',     // 手机端模板目录
    'mob_html_dir' => 'html',            // 手机端静态目录
    'site_tj' => '统计代码',              // 统计代码
    'site_status' => '1',                // 站点开关 (1=开启)
    'site_close_tip' => '站点暂时关闭',   // 关闭提示
    'ads_dir' => 'ads',                  // 广告目录
    'mob_ads_dir' => 'ads',              // 手机广告目录
]
```

---

### 3. app - 应用程序配置

```php
'app' => [
    // URL配置
    'pathinfo_depr' => '/',       // URL分隔符
    'suffix' => 'html',           // URL后缀

    // 缓存配置
    'cache_type' => 'file',       // 缓存类型 (file/redis/memcache)
    'cache_host' => '127.0.0.1',  // 缓存服务器
    'cache_port' => '6379',       // 缓存端口
    'cache_password' => '',       // 缓存密码
    'cache_db' => '0',            // Redis数据库
    'cache_flag' => 'a6bcf9aa58', // ★ 缓存标识 (安装时生成)
    'cache_core' => '0',          // 核心缓存开关
    'cache_time' => '3600',       // 缓存时间(秒)
    'cache_page' => '0',          // 页面缓存开关

    // 搜索配置
    'search' => '1',              // 搜索开关
    'search_timespan' => '3',     // 搜索间隔(秒)
    'search_vod_rule' => 'vod_en|vod_sub',  // 视频搜索字段
    'search_art_rule' => 'art_en|art_sub',  // 文章搜索字段
    'search_hot' => '变形金刚,火影忍者',     // 热门搜索词

    // 扩展分类
    'vod_extend_class' => '爱情,动作,喜剧',  // 视频扩展分类
    'vod_extend_area' => '大陆,香港,台湾',   // 地区选项
    'vod_extend_lang' => '国语,英语,粤语',   // 语言选项
    'vod_extend_year' => '2021,2020,2019',  // 年份选项

    // 其他
    'lang' => 'zh-cn',            // ★ 语言 (安装时写入)
    'editor' => 'Ueditor',        // 编辑器类型
    'admin_login_verify' => '1',  // 后台登录验证码
    'pagesize' => '20',           // 分页大小
]
```

---

### 4. user - 用户系统配置

```php
'user' => [
    'status' => '1',              // 用户系统开关
    'reg_open' => '1',            // 注册开关
    'reg_verify' => '0',          // 注册验证码
    'login_verify' => '0',        // 登录验证码
    'reg_points' => '10',         // 注册赠送积分
    'invite_reg_points' => '10',  // 邀请注册奖励积分
    'trysee' => '0',              // 试看时长(秒)
    'vod_points_type' => '1',     // 视频积分类型
    'portrait_status' => '1',     // 头像上传开关
]
```

---

### 5. interface - 数据接口配置

```php
'interface' => [
    'status' => 0,                // ★ 接口开关 (安装时设为0)
    'pass' => '5RI8CLIV5YD46Q5G', // ★ 接口密钥 (安装时随机生成)
    'vodtype' => '动作片=动作',    // 视频分类映射
    'arttype' => '头条=头条',      // 文章分类映射
]
```

> **安全提示**: 接口默认禁用，密钥在安装时随机生成，确保安全性

---

### 6. api - API接口配置

```php
'api' => [
    'vod' => [
        'status' => 0,            // ★ 视频API开关 (安装时设为0)
        'charge' => '0',          // 是否收费
        'pagesize' => '20',       // 分页大小
        'imgurl' => '',           // 图片URL前缀
        'typefilter' => '',       // 分类过滤
        'datafilter' => 'vod_status=1',  // 数据过滤条件
        'auth' => 'test.com',     // 授权域名
    ],
    'art' => [
        'status' => 0,            // ★ 文章API开关 (安装时设为0)
        // ...
    ],
    // actor, role, website, publicapi 类似结构
]
```

---

### 7. collect - 采集配置

```php
'collect' => [
    'vod' => [
        'status' => '1',          // 视频采集开关
        'hits_start' => '1',      // 点击数起始值
        'hits_end' => '1000',     // 点击数结束值
        'pic' => '0',             // 是否同步图片
        'class_filter' => '1',    // 分类过滤
        'inrule' => ',f,g',       // 入库规则
        'uprule' => ',a',         // 更新规则
        'filter' => '色戒',        // 过滤关键词
    ],
    // art, actor, role, website, comment 类似结构
]
```

---

### 8. upload - 上传配置

```php
'upload' => [
    'mode' => 'local',            // 上传模式 (local/ftp/qiniu/upyun)
    'protocol' => 'http',         // 协议
    'thumb' => '0',               // 缩略图开关
    'thumb_size' => '300x300',    // 缩略图尺寸
    'watermark' => '0',           // 水印开关
    'watermark_content' => 'test', // 水印内容

    // 第三方存储配置
    'api' => [
        'ftp' => [...],           // FTP配置
        'qiniu' => [...],         // 七牛云配置
        'upyun' => [...],         // 又拍云配置
        'weibo' => [...],         // 微博图床配置
    ]
]
```

---

### 9. play - 播放器配置

```php
'play' => [
    'width' => '100%',            // 播放器宽度
    'height' => '100%',           // 播放器高度
    'second' => '5',              // 广告时长
    'prestrain' => '//xxx/prestrain.html',  // 预载入页面
    'buffer' => '//xxx/buffer.html',        // 缓冲页面
    'parse' => '',                // 解析接口
    'autofull' => '0',            // 自动全屏
]
```

---

### 10. rewrite - URL重写配置

```php
'rewrite' => [
    'status' => '0',              // 重写开关
    'route_status' => '0',        // 路由开关
    'encode_key' => 'abcdefg',    // 加密密钥
    'encode_len' => '6',          // 加密长度
    'route' => '路由规则字符串',   // 自定义路由规则
]
```

---

### 11. 其他配置模块

| 模块 | 说明 |
|-----|------|
| `pay` | 支付配置 (支付宝、微信、卡密等) |
| `connect` | 第三方登录 (QQ、微信) |
| `weixin` | 微信公众号配置 |
| `email` | 邮件发送配置 |
| `sms` | 短信发送配置 |
| `seo` | 各模块SEO配置 |
| `urlsend` | 百度URL推送配置 |
| `view` | 静态页面生成开关 |
| `path` | 静态页面路径模板 |

---

## 配置读取方式

```php
// 读取整个配置
$config = config('maccms');

// 读取单个配置项
$siteName = config('maccms.site.site_name');

// 读取嵌套配置
$vodApiStatus = config('maccms.api.vod.status');

// 在模板中使用
{$maccms.site.site_name}
```

---

## 配置修改方式

### 1. 后台管理界面修改
大部分配置通过后台 `系统 -> 网站参数配置` 修改

### 2. 代码方式修改
```php
$config = config('maccms');
$config['site']['site_name'] = '新站点名称';
mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
```

---

## 安全建议

1. **interface.pass**: 接口密钥，请勿泄露
2. **api.*.auth**: API授权域名，限制访问来源
3. **user.filter_words**: 用户名过滤词，防止敏感词注册
4. **collect.*.filter**: 采集过滤词，过滤不良内容
5. 定期备份此配置文件