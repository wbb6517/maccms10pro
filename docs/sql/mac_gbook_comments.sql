-- ============================================================
-- mac_gbook 表字段注释 (Guestbook Table Comments)
-- ============================================================
--
-- 【表说明】
-- 留言表 - 用户留言反馈系统，支持管理员回复
--
-- 【回复关联】
-- gbook_rid=0 为主题留言，gbook_rid>0 为回复某留言
--
-- 【审核机制】
-- gbook_status 控制是否前台可见
--
-- 【管理员回复】
-- gbook_reply 存储管理员的回复内容
--
-- 【模板标签】
-- {maccms:gbook rid="0" order="desc" by="time" num="10"}
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_gbook` COMMENT '留言表 - 用户留言反馈系统，支持管理员回复';

-- 字段注释
ALTER TABLE `mac_gbook`
    MODIFY COLUMN `gbook_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '留言ID (主键)',
    MODIFY COLUMN `gbook_rid` int unsigned NOT NULL DEFAULT '0' COMMENT '回复的留言ID (0=主题留言, >0=回复留言)',
    MODIFY COLUMN `user_id` int unsigned NOT NULL DEFAULT '0' COMMENT '用户ID (0=游客)',
    MODIFY COLUMN `gbook_status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=待审核, 1=已审核',
    MODIFY COLUMN `gbook_name` varchar(60) NOT NULL DEFAULT '' COMMENT '留言者昵称',
    MODIFY COLUMN `gbook_ip` int unsigned NOT NULL DEFAULT '0' COMMENT '留言者IP地址 (ip2long整数存储)',
    MODIFY COLUMN `gbook_time` int unsigned NOT NULL DEFAULT '0' COMMENT '留言时间戳',
    MODIFY COLUMN `gbook_reply_time` int unsigned NOT NULL DEFAULT '0' COMMENT '管理员回复时间戳',
    MODIFY COLUMN `gbook_content` varchar(255) NOT NULL DEFAULT '' COMMENT '留言内容',
    MODIFY COLUMN `gbook_reply` varchar(255) NOT NULL DEFAULT '' COMMENT '管理员回复内容';
