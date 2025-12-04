-- ============================================================
-- mac_website 表字段注释 (Website Table Comments)
-- ============================================================
--
-- 【表说明】
-- 网址/网站导航表 - 管理外部网址资源，支持友情链接、网站导航等功能
-- 提供回链统计、访问量统计等数据分析功能
--
-- 【模板标签】
-- {maccms:website type="1" num="10" order="time" by="desc"}
--
-- 【回链功能】
-- 统计从外部网站点击进入本站的访问量，用于友链效果分析
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_website` COMMENT '网址/网站导航表 - 管理外部网址资源，支持友情链接、网站导航等功能';

-- ==================== 基础信息字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '网址ID (主键)',
    MODIFY COLUMN `type_id` smallint unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',
    MODIFY COLUMN `type_id_1` smallint unsigned NOT NULL DEFAULT '0' COMMENT '一级分类ID',
    MODIFY COLUMN `website_name` varchar(60) NOT NULL DEFAULT '' COMMENT '网址名称',
    MODIFY COLUMN `website_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '网址副标题',
    MODIFY COLUMN `website_en` varchar(255) NOT NULL DEFAULT '' COMMENT '网址英文名/拼音',
    MODIFY COLUMN `website_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母 (A-Z)',
    MODIFY COLUMN `website_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色 (十六进制)',
    MODIFY COLUMN `website_jumpurl` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转链接',
    MODIFY COLUMN `website_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '网址图标/封面图',
    MODIFY COLUMN `website_pic_screenshot` text COMMENT '网站截图 (多张用#分隔)',
    MODIFY COLUMN `website_logo` varchar(255) NOT NULL DEFAULT '' COMMENT '网站LOGO';

-- ==================== 分类属性字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_area` varchar(20) NOT NULL DEFAULT '' COMMENT '地区',
    MODIFY COLUMN `website_lang` varchar(10) NOT NULL DEFAULT '' COMMENT '语言',
    MODIFY COLUMN `website_tag` varchar(100) NOT NULL DEFAULT '' COMMENT '标签 (逗号分隔)',
    MODIFY COLUMN `website_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类 (逗号分隔)';

-- ==================== 状态控制字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_status` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '审核状态: 0=未审核, 1=已审核',
    MODIFY COLUMN `website_lock` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '锁定状态: 0=未锁定, 1=已锁定',
    MODIFY COLUMN `website_level` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '推荐等级: 0=无, 1-8=普通等级, 9=幻灯片',
    MODIFY COLUMN `website_sort` int NOT NULL DEFAULT '0' COMMENT '排序值 (数字小的在前)';

-- ==================== 评分统计字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' COMMENT '综合评分 (1-10)',
    MODIFY COLUMN `website_score_all` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '总评分累计',
    MODIFY COLUMN `website_score_num` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '评分人数';

-- ==================== 点击量统计字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_hits` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '总点击量',
    MODIFY COLUMN `website_hits_day` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '日点击量',
    MODIFY COLUMN `website_hits_week` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '周点击量',
    MODIFY COLUMN `website_hits_month` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '月点击量';

-- ==================== 回链统计字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_referer` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '总回链数',
    MODIFY COLUMN `website_referer_day` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '日回链数',
    MODIFY COLUMN `website_referer_week` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '周回链数',
    MODIFY COLUMN `website_referer_month` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '月回链数';

-- ==================== 顶踩统计字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_up` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '顶/赞数',
    MODIFY COLUMN `website_down` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '踩/反对数';

-- ==================== 时间字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间 (时间戳)',
    MODIFY COLUMN `website_time_add` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间 (时间戳)',
    MODIFY COLUMN `website_time_hits` int unsigned NOT NULL DEFAULT '0' COMMENT '点击时间 (时间戳)',
    MODIFY COLUMN `website_time_make` int unsigned NOT NULL DEFAULT '0' COMMENT '生成时间 (时间戳)',
    MODIFY COLUMN `website_time_referer` int unsigned NOT NULL DEFAULT '0' COMMENT '回链时间 (时间戳)';

-- ==================== 内容字段 ====================
ALTER TABLE `mac_website`
    MODIFY COLUMN `website_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介',
    MODIFY COLUMN `website_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注',
    MODIFY COLUMN `website_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '自定义模板',
    MODIFY COLUMN `website_content` mediumtext NOT NULL COMMENT '网址详细介绍';
