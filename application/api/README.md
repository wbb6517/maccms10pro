# API 模块文档

API 模块是苹果CMS的三大应用模块之一，通过 `api.php` 入口访问，主要提供数据接口服务。

## 一、模块架构

```
api.php (入口)
   └─ application/api/
       ├── controller/    (18个控制器)
       │   ├── Base.php           # API基类
       │   ├── PublicApi.php      # 认证Trait
       │   ├── Vod.php            # 视频API
       │   ├── Art.php            # 文章API
       │   ├── Actor.php          # 演员API
       │   ├── Provide.php        # 数据提供(采集源) ★核心
       │   ├── Receive.php        # 数据接收(入库)
       │   ├── Timming.php        # 定时任务
       │   ├── Comment.php        # 评论API
       │   ├── Gbook.php          # 留言板API
       │   ├── Topic.php          # 专题API
       │   ├── Type.php           # 分类API
       │   ├── Link.php           # 链接API
       │   ├── Website.php        # 网站API
       │   ├── User.php           # 用户API
       │   ├── Manga.php          # 漫画API
       │   ├── Wechat.php         # 微信对接
       │   └── Index.php          # 空壳
       ├── validate/      (11个验证器)
       │   ├── Vod.php
       │   ├── Art.php
       │   ├── Actor.php
       │   ├── User.php
       │   ├── Comment.php
       │   ├── Type.php
       │   └── ...
       └── lang/          (语言包)
           └── zh-cn.php
```

## 二、API 分类

### 2.1 公开数据 API (PublicApi)

使用 `PublicApi` Trait 进行认证，提供只读数据查询接口。

| 控制器 | 方法 | 功能 |
|--------|------|------|
| **Vod** | get_list | 获取视频列表 |
| | get_detail | 获取视频详情 |
| | get_year | 获取视频年份列表 |
| | get_class | 获取视频类别列表 |
| | get_area | 获取视频地区列表 |
| **Art** | get_list | 获取文章列表 |
| | get_detail | 获取文章详情 |
| **Actor** | get_list | 获取演员列表 |
| | get_detail | 获取演员详情 |
| **User** | get_list | 获取用户列表 |
| **Comment** | get_list | 获取评论列表 |
| **Type** | get_list | 获取分类树 |
| **Topic** | get_list | 获取专题列表 |
| **Gbook** | get_list | 获取留言板列表 |
| **Link** | get_list | 获取链接列表 |
| **Website** | get_list | 获取网站列表 |
| **Manga** | get_list | 获取漫画列表 |
| | get_detail | 获取漫画详情 |

### 2.2 数据提供 API (Provide)

为第三方采集系统提供数据，支持 JSON/XML 双格式输出。

| 方法 | 功能 | 配置键 |
|------|------|--------|
| vod | 视频数据提供 | api.vod.* |
| art | 文章数据提供 | api.art.* |
| actor | 演员数据提供 | api.actor.* |
| role | 角色数据提供 | api.role.* |
| website | 网站数据提供 | api.website.* |
| link | 链接数据提供 | api.link.* |

### 2.3 数据接收 API (Receive)

接收外部推送数据入库，需要 16 位以上密钥认证。

| 方法 | 功能 | 入库表 |
|------|------|--------|
| vod | 视频入库 | mac_vod |
| art | 文章入库 | mac_art |
| actor | 演员入库 | mac_actor |

### 2.4 定时任务 API (Timming)

触发采集、生成等后台任务。

| 方法 | 功能 |
|------|------|
| index | 定时任务入口 |
| collect | 定时采集 |
| make | 定时生成静态页 |

### 2.5 微信对接 API (Wechat)

微信服务器验证和消息处理。

## 三、访问格式

```
GET/POST  /api.php/{controller}/{action}?参数

示例:
GET  /api.php/vod/get_list?type_id=1&offset=0&limit=20
GET  /api.php/provide/vod?ac=videolist&at=json&pg=1
POST /api.php/receive/vod?pass=xxx&vod_name=...
GET  /api.php/timming/index?name=collect
```

## 四、认证机制

### 4.1 PublicApi 认证 (公开数据API)

配置位置: `application/extra/maccms.php` → `api.publicapi`

```php
'publicapi' => [
    'status'   => '0',      // 开关: 0=关闭, 1=开启
    'charge'   => '0',      // 收费: 0=免费, 1=需认证
    'auth'     => '',       // IP/域名白名单 (#分隔)
    'pagesize' => '20',     // 默认分页大小
    'cachetime'=> '',       // 缓存时间(秒)
]
```

**认证流程:**
1. 检查 `status` 开关
2. 若 `charge=1`，验证请求IP是否在 `auth` 白名单中
3. 支持域名自动DNS解析

### 4.2 Provide API 认证 (数据提供)

每个数据类型独立配置，配置位置: `api.vod`, `api.art` 等

```php
'vod' => [
    'status'     => '0',    // 开关
    'charge'     => '0',    // 收费模式
    'auth'       => '',     // IP/域名白名单
    'cachetime'  => '',     // 缓存时间
    'pagesize'   => '20',   // 分页大小
    'typefilter' => '',     // 分类过滤(逗号分隔)
    'datafilter' => '',     // 数据过滤SQL
    'from'       => '',     // 播放源过滤
    'imgurl'     => '',     // 图片域名前缀
]
```

### 4.3 Receive API 认证 (数据入库)

配置位置: `application/extra/maccms.php` → `interface`

```php
'interface' => [
    'status'  => '0',       // 开关
    'pass'    => '...',     // 密钥 (至少16位)
    'vodtype' => '',        // 视频分类映射
    'arttype' => '',        // 文章分类映射
]
```

**安全要求:**
- 密钥长度至少 16 位
- 密钥错误返回 3002 错误码
- 密钥强度不足返回 3003 错误码

## 五、返回格式

### 5.1 JSON 格式 (PublicApi)

**成功响应:**
```json
{
    "code": 1,
    "msg": "获取成功",
    "info": {
        "offset": 0,
        "limit": 20,
        "total": 100,
        "rows": [
            {
                "vod_id": 1,
                "vod_name": "视频标题",
                "vod_hits": 1000
            }
        ]
    }
}
```

**错误响应:**
```json
{
    "code": 1001,
    "msg": "参数错误: offset参数错误"
}
```

**错误码定义:**
- `1` - 成功
- `1001` - 参数验证失败
- `3001` - API已关闭
- `3002` - 密钥错误
- `3003` - 密钥强度不足

### 5.2 XML 格式 (Provide)

```xml
<?xml version="1.0" encoding="utf-8"?>
<rss version="5.1">
    <list page="1" pagecount="5" pagesize="20" recordcount="100">
        <video>
            <id>1</id>
            <name><![CDATA[视频名称]]></name>
            <type>电影</type>
            <pic>http://img.test.com/pic.jpg</pic>
            <lang>中文</lang>
            <area>中国</area>
            <year>2023</year>
            <state>完结</state>
            <dt>m3u8,mp4</dt>
            <note><![CDATA[备注信息]]></note>
        </video>
    </list>
    <class>
        <ty id="1">电影</ty>
        <ty id="2">电视剧</ty>
    </class>
</rss>
```

## 六、参数验证

验证规则定义在 `application/api/validate/` 目录下。

### 6.1 通用参数

| 参数 | 类型 | 范围 | 说明 |
|------|------|------|------|
| offset | int | 0-MAX | 偏移量 |
| limit | int | 1-500 | 每页数量 |
| orderby | string | 见下方 | 排序字段 |

### 6.2 视频API参数 (Vod)

| 参数 | 说明 |
|------|------|
| type_id | 分类ID |
| vod_letter | 首字母 (单字符) |
| vod_name | 视频名称 (最长50) |
| vod_tag | 标签 (最长20) |
| vod_class | 类别 (最长10) |

**orderby 可选值:** hits, up, pubdate, hits_week, hits_month, hits_day, score

### 6.3 Provide API 参数

| 参数 | 说明 |
|------|------|
| ac | 动作: list/videolist/detail |
| at | 格式: xml/json |
| t | 分类ID |
| pg | 页码 |
| pagesize | 每页数量 (最大100) |
| h | 时间范围(小时) |
| ids | 指定ID列表 |
| wd | 搜索关键词 |

## 七、安全防护

### 7.1 SQL注入防护

`PublicApi::format_sql_string()` 方法用于过滤SQL关键字:

```php
// 移除的关键字
SELECT, INSERT, UPDATE, DELETE, DROP, TRUNCATE,
ALTER, CREATE, UNION, JOIN, WHERE, AND, OR,
FROM, INTO, SET, VALUES, LIKE, BETWEEN,
ORDER, GROUP, HAVING, LIMIT, OFFSET

// 移除特殊字符，只保留
\w \s \- \.
```

### 7.2 认证防护

- **开关控制** - 每个API可独立开关
- **IP/域名白名单** - 支持DNS解析
- **密钥强度检查** - Receive API 强制16位以上

## 八、缓存机制

Provide API 支持缓存:

```php
// 缓存键格式
{cache_flag}_api_{type}_{md5(params)}

// 缓存时间
由各API的 cachetime 配置决定 (秒)
cachetime=0 表示不缓存
```

## 九、与其他模块的关系

```
API控制器 → Model
├── Vod.php      → model('Vod')
├── Art.php      → model('Art')
├── Actor.php    → model('Actor')
├── Comment.php  → model('Comment')
├── Receive.php  → model('Collect')  # 数据入库
└── Provide.php  → model('Vod/Art/Actor')
```

## 十、配置文件位置

| 配置项 | 文件位置 |
|--------|---------|
| API基础配置 | application/extra/maccms.php → api |
| 入库接口配置 | application/extra/maccms.php → interface |
| 定时任务配置 | application/extra/timming.php |
| 路由规则 | application/route.php |

## 十一、使用示例

### 获取视频列表
```
GET /api.php/vod/get_list?offset=0&limit=20&type_id=1&orderby=hits
```

### 获取视频详情
```
GET /api.php/vod/get_detail?vod_id=123
```

### 采集系统调用
```
GET /api.php/provide/vod?ac=videolist&at=json&t=1&pg=1&pagesize=20
```

### 推送数据入库
```
POST /api.php/receive/vod
Content-Type: application/x-www-form-urlencoded

pass=xxxxxxxxxxxxxxxx&vod_name=新视频&type_id=1&vod_content=...
```

### 触发定时采集
```
GET /api.php/timming/index?name=collect&enforce=1
```