-- ============================================================
-- mac_link 表字段注释 (Friendly Link Table Comments)
-- ============================================================
--
-- 【表说明】
-- 友情链接表 - 管理网站底部/侧边栏友情链接，支持文字和图片两种类型
--
-- 【链接类型】
-- link_type=0 文字链接，link_type=1 图片链接
--
-- 【图片链接】
-- 显示 link_logo 图片，鼠标悬停显示 link_name
--
-- 【模板标签】
-- {maccms:link type="font" by="sort" num="10"}
--
-- 【回链检测】
-- 后台可检测对方网站是否包含本站链接
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_link` COMMENT '友情链接表 - 管理网站底部/侧边栏友情链接，支持文字和图片两种类型';

-- 字段注释
ALTER TABLE `mac_link`
    MODIFY COLUMN `link_id` smallint unsigned NOT NULL AUTO_INCREMENT COMMENT '友链ID (主键)',
    MODIFY COLUMN `link_type` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '链接类型: 0=文字链接, 1=图片链接',
    MODIFY COLUMN `link_name` varchar(60) NOT NULL DEFAULT '' COMMENT '友链名称/网站名',
    MODIFY COLUMN `link_sort` smallint NOT NULL DEFAULT '0' COMMENT '排序值 (数字小的在前)',
    MODIFY COLUMN `link_add_time` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间戳',
    MODIFY COLUMN `link_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间戳',
    MODIFY COLUMN `link_url` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址 (完整URL，如 https://example.com)',
    MODIFY COLUMN `link_logo` varchar(255) NOT NULL DEFAULT '' COMMENT 'Logo图片URL (图片链接时显示)';
