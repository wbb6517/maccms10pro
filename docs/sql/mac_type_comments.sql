-- ============================================================
-- mac_type 表字段注释 (Type/Category Table Comments)
-- ============================================================
--
-- 【表说明】
-- 分类表 - 管理视频、文章、演员等内容的分类体系，支持无限级树形结构
--
-- 【树形结构】
-- type_pid=0 为顶级分类，type_pid>0 为子分类
--
-- 【多模型支持】
-- type_mid 区分: 1=视频, 2=文章, 3=演员, 8=漫画
--
-- 【模板继承】
-- 子分类未设置模板时，自动继承父分类模板
--
-- 【扩展筛选】
-- type_extend 存储筛选项配置(年代/地区/语言等)
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_type` COMMENT '分类表 - 支持无限级树形结构，管理视频/文章/演员等内容分类';

-- 字段注释
ALTER TABLE `mac_type`
    MODIFY COLUMN `type_id` smallint unsigned NOT NULL AUTO_INCREMENT COMMENT '分类ID (主键)',
    MODIFY COLUMN `type_name` varchar(60) NOT NULL DEFAULT '' COMMENT '分类名称',
    MODIFY COLUMN `type_en` varchar(60) NOT NULL DEFAULT '' COMMENT '英文标识/URL别名 (留空自动生成拼音)',
    MODIFY COLUMN `type_sort` smallint unsigned NOT NULL DEFAULT '0' COMMENT '排序值 (数字小的在前)',
    MODIFY COLUMN `type_mid` smallint unsigned NOT NULL DEFAULT '1' COMMENT '模型ID: 1=视频, 2=文章, 3=演员, 8=漫画',
    MODIFY COLUMN `type_pid` smallint unsigned NOT NULL DEFAULT '0' COMMENT '父级分类ID (0=顶级分类)',
    MODIFY COLUMN `type_status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=启用',
    MODIFY COLUMN `type_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '频道页模板 (如 type.html)',
    MODIFY COLUMN `type_tpl_list` varchar(30) NOT NULL DEFAULT '' COMMENT '列表页模板 (如 show.html)',
    MODIFY COLUMN `type_tpl_detail` varchar(30) NOT NULL DEFAULT '' COMMENT '详情页模板 (如 detail.html)',
    MODIFY COLUMN `type_tpl_play` varchar(30) NOT NULL DEFAULT '' COMMENT '播放页模板 (如 play.html)',
    MODIFY COLUMN `type_tpl_down` varchar(30) NOT NULL DEFAULT '' COMMENT '下载页模板 (如 down.html)',
    MODIFY COLUMN `type_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'SEO关键词',
    MODIFY COLUMN `type_des` varchar(255) NOT NULL DEFAULT '' COMMENT 'SEO描述',
    MODIFY COLUMN `type_title` varchar(255) NOT NULL DEFAULT '' COMMENT 'SEO标题',
    MODIFY COLUMN `type_union` varchar(255) NOT NULL DEFAULT '' COMMENT '联合查询的分类ID (逗号分隔，用于聚合多个子分类)',
    MODIFY COLUMN `type_extend` text NOT NULL COMMENT '扩展配置 (JSON格式: 筛选项class/area/lang/year/star/state/version等)',
    MODIFY COLUMN `type_logo` varchar(255) NOT NULL DEFAULT '' COMMENT '分类Logo图标URL',
    MODIFY COLUMN `type_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '分类封面图URL',
    MODIFY COLUMN `type_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL (设置后点击分类跳转到指定地址)';
