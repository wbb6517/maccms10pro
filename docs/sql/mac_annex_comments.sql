-- ============================================================
-- mac_annex 表字段注释 (Attachment Table Comments)
-- ============================================================
--
-- 【表说明】
-- 附件表 - 记录上传的图片和文件，便于统一管理和清理
--
-- 【文件追踪】
-- 记录所有通过后台上传的图片和文件
--
-- 【级联删除】
-- 删除附件记录时自动删除对应的物理文件
--
-- 【无效检测】
-- 后台可检测并清理数据库中不存在的文件记录
--
-- 【初始化扫描】
-- 可扫描内容表中的图片并自动入库附件表
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_annex` COMMENT '附件表 - 记录上传的图片和文件，删除记录时同步删除物理文件';

-- 字段注释
ALTER TABLE `mac_annex`
    MODIFY COLUMN `annex_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '附件ID (主键)',
    MODIFY COLUMN `annex_time` int unsigned NOT NULL DEFAULT '0' COMMENT '上传时间戳',
    MODIFY COLUMN `annex_file` varchar(255) NOT NULL DEFAULT '' COMMENT '文件路径 (相对于网站根目录，如 upload/image/xxx.jpg)',
    MODIFY COLUMN `annex_size` int unsigned NOT NULL DEFAULT '0' COMMENT '文件大小 (字节)',
    MODIFY COLUMN `annex_type` varchar(8) NOT NULL DEFAULT '' COMMENT '附件类型: image=图片, file=文件';
