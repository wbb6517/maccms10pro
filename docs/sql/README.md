# MacCMS 数据表注释 SQL 文件

本目录包含 MacCMS 各数据表的字段注释 SQL 文件。

## 文件列表

| 文件名 | 表名 | 说明 |
|--------|------|------|
| mac_vod_comments.sql | mac_vod | 视频数据表 (82个字段) |
| mac_type_comments.sql | mac_type | 分类表 |
| mac_topic_comments.sql | mac_topic | 专题表 |
| mac_link_comments.sql | mac_link | 友情链接表 |
| mac_gbook_comments.sql | mac_gbook | 留言表 |
| mac_comment_comments.sql | mac_comment | 评论表 |
| mac_annex_comments.sql | mac_annex | 附件表 |

## 使用方法

### 方式一: 命令行执行

```bash
# 执行单个文件
mysql -u用户名 -p 数据库名 < mac_vod_comments.sql

# 执行所有文件
for f in *.sql; do mysql -u用户名 -p 数据库名 < "$f"; done
```

### 方式二: phpMyAdmin

1. 打开 phpMyAdmin
2. 选择对应的数据库
3. 点击「导入」标签
4. 选择 SQL 文件并执行

### 方式三: Navicat 等工具

1. 连接数据库
2. 打开 SQL 编辑器
3. 打开 SQL 文件内容
4. 点击执行

## 验证注释

执行后可通过以下命令验证注释是否生效：

```sql
-- 查看表注释
SHOW TABLE STATUS WHERE Name = 'mac_vod';

-- 查看字段注释
SHOW FULL COLUMNS FROM mac_vod;
```

## 注意事项

1. 执行前请**备份数据库**
2. SQL 文件使用 UTF-8 编码
3. 注释内容为中文，需确保数据库字符集支持
4. 如果字段类型有变化，可能需要调整 MODIFY 语句中的类型定义
