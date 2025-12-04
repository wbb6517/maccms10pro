-- ============================================================
-- mac_visit 表字段注释 (Visit Log Table Comments)
-- ============================================================
--
-- 【表说明】
-- 访客日志表 - 记录用户访问来源（来路URL），用于流量统计和推广分析
--
-- 【用途】
-- 1. 流量来源分析 - 统计用户从哪些外部网站访问
-- 2. 推广效果跟踪 - 分析友链/推广链接的实际效果
-- 3. 回链统计 - 配合网址模块统计回链访问量
--
-- 【关联表】
-- - mac_website: 网址表，通过来路URL关联
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_visit` COMMENT '访客日志表 - 记录用户访问来源，用于流量统计和推广分析';

-- 字段注释
ALTER TABLE `mac_visit`
    MODIFY COLUMN `visit_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID (主键)',
    MODIFY COLUMN `user_id` int unsigned DEFAULT '0' COMMENT '用户ID: 0=游客, >0=注册用户',
    MODIFY COLUMN `visit_ip` int unsigned NOT NULL DEFAULT '0' COMMENT '访问IP (ip2long格式)',
    MODIFY COLUMN `visit_ly` varchar(100) NOT NULL DEFAULT '' COMMENT '来路URL (访问来源地址)',
    MODIFY COLUMN `visit_time` int unsigned NOT NULL DEFAULT '0' COMMENT '访问时间 (时间戳)';
