-- ============================================================
-- mac_comment 表字段注释 (Comment Table Comments)
-- ============================================================
--
-- 【表说明】
-- 评论表 - 多模型评论系统，支持视频/文章/专题/演员等内容评论
--
-- 【多模型支持】
-- comment_mid 区分: 1=视频, 2=文章, 3=专题, 8=演员, 9=角色, 11=网址
--
-- 【楼中楼】
-- comment_pid=0 主评论，comment_pid>0 回复某评论
--
-- 【举报系统】
-- comment_report>0 表示被用户举报，可在后台筛选处理
--
-- 【互动统计】
-- comment_up/comment_down 记录顶踩次数，comment_reply 记录回复数量
--
-- 【IP存储】
-- comment_ip 使用 ip2long() 转为整数存储，节省空间
--
-- 【模板标签】
-- {maccms:comment mid="1" rid="$obj.vod_id" order="desc" by="time" num="10"}
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_comment` COMMENT '评论表 - 多模型评论系统，支持视频/文章/专题/演员等内容评论';

-- 字段注释
ALTER TABLE `mac_comment`
    MODIFY COLUMN `comment_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '评论ID (主键)',
    MODIFY COLUMN `comment_mid` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '模型ID: 1=视频, 2=文章, 3=专题, 8=演员, 9=角色, 11=网址',
    MODIFY COLUMN `comment_rid` int unsigned NOT NULL DEFAULT '0' COMMENT '关联内容ID (对应模型的主键)',
    MODIFY COLUMN `comment_pid` int unsigned NOT NULL DEFAULT '0' COMMENT '父评论ID (0=主评论, >0=楼中楼回复)',
    MODIFY COLUMN `user_id` int unsigned NOT NULL DEFAULT '0' COMMENT '用户ID (0=游客)',
    MODIFY COLUMN `comment_status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=待审核, 1=已审核',
    MODIFY COLUMN `comment_name` varchar(60) NOT NULL DEFAULT '' COMMENT '评论者昵称',
    MODIFY COLUMN `comment_ip` int unsigned NOT NULL DEFAULT '0' COMMENT '评论者IP地址 (ip2long整数存储)',
    MODIFY COLUMN `comment_time` int unsigned NOT NULL DEFAULT '0' COMMENT '评论时间戳',
    MODIFY COLUMN `comment_content` varchar(255) NOT NULL DEFAULT '' COMMENT '评论内容',
    MODIFY COLUMN `comment_up` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '顶/赞次数',
    MODIFY COLUMN `comment_down` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '踩次数',
    MODIFY COLUMN `comment_reply` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '回复数量',
    MODIFY COLUMN `comment_report` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '举报次数 (>0表示被举报)';
