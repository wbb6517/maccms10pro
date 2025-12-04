-- ============================================================
-- mac_topic 表字段注释 (Topic Table Comments)
-- ============================================================
--
-- 【表说明】
-- 专题表 - 聚合视频和文章内容，支持手动关联ID和TAG自动匹配两种方式
--
-- 【关联机制】
-- 1. 内容关联: topic_rel_vod/topic_rel_art 手动指定关联内容ID
-- 2. TAG匹配: topic_tag 自动匹配具有相同TAG的视频和文章
--
-- 【静态生成】
-- 通过 topic_time_make < topic_time 判断是否需要重新生成
--
-- 【等级系统】
-- level=9 表示幻灯片位，显示在首页轮播区
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_topic` COMMENT '专题表 - 聚合视频/文章内容，支持手动关联和TAG自动匹配';

-- 字段注释
ALTER TABLE `mac_topic`
    MODIFY COLUMN `topic_id` smallint unsigned NOT NULL AUTO_INCREMENT COMMENT '专题ID (主键)',
    MODIFY COLUMN `topic_name` varchar(255) NOT NULL DEFAULT '' COMMENT '专题名称',
    MODIFY COLUMN `topic_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文标识/URL别名 (留空自动生成拼音)',
    MODIFY COLUMN `topic_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题',
    MODIFY COLUMN `topic_status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=未审核, 1=已审核',
    MODIFY COLUMN `topic_sort` smallint unsigned NOT NULL DEFAULT '0' COMMENT '排序值 (数字小的在前)',
    MODIFY COLUMN `topic_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母 (用于字母筛选)',
    MODIFY COLUMN `topic_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色 (十六进制RGB)',
    MODIFY COLUMN `topic_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '模板文件名 (默认detail.html)',
    MODIFY COLUMN `topic_type` varchar(255) NOT NULL DEFAULT '' COMMENT '分类标签 (逗号分隔)',
    MODIFY COLUMN `topic_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '专题图片URL',
    MODIFY COLUMN `topic_pic_thumb` varchar(1024) NOT NULL DEFAULT '' COMMENT '缩略图URL',
    MODIFY COLUMN `topic_pic_slide` varchar(1024) NOT NULL DEFAULT '' COMMENT '幻灯片大图URL (用于首页轮播)',
    MODIFY COLUMN `topic_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'SEO关键词',
    MODIFY COLUMN `topic_des` varchar(255) NOT NULL DEFAULT '' COMMENT 'SEO描述',
    MODIFY COLUMN `topic_title` varchar(255) NOT NULL DEFAULT '' COMMENT 'SEO标题',
    MODIFY COLUMN `topic_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介 (留空自动截取内容)',
    MODIFY COLUMN `topic_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '后台备注',
    MODIFY COLUMN `topic_level` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '推荐等级: 1-8普通推荐, 9=幻灯片位',
    MODIFY COLUMN `topic_up` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '顶/赞次数',
    MODIFY COLUMN `topic_down` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '踩次数',
    MODIFY COLUMN `topic_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' COMMENT '评分 (0.0-10.0)',
    MODIFY COLUMN `topic_score_all` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '评分总数',
    MODIFY COLUMN `topic_score_num` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '评分人数',
    MODIFY COLUMN `topic_hits` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '总点击量',
    MODIFY COLUMN `topic_hits_day` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '日点击量',
    MODIFY COLUMN `topic_hits_week` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '周点击量',
    MODIFY COLUMN `topic_hits_month` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '月点击量',
    MODIFY COLUMN `topic_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间戳',
    MODIFY COLUMN `topic_time_add` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间戳',
    MODIFY COLUMN `topic_time_hits` int unsigned NOT NULL DEFAULT '0' COMMENT '点击量统计更新时间',
    MODIFY COLUMN `topic_time_make` int unsigned NOT NULL DEFAULT '0' COMMENT '静态页面生成时间 (与topic_time比较判断是否需要重新生成)',
    MODIFY COLUMN `topic_tag` varchar(255) NOT NULL DEFAULT '' COMMENT 'TAG标签 (逗号分隔，用于自动关联内容)',
    MODIFY COLUMN `topic_rel_vod` text NOT NULL COMMENT '关联视频ID (逗号分隔，手动指定)',
    MODIFY COLUMN `topic_rel_art` text NOT NULL COMMENT '关联文章ID (逗号分隔，手动指定)',
    MODIFY COLUMN `topic_content` text NOT NULL COMMENT '详细内容 (HTML富文本)',
    MODIFY COLUMN `topic_extend` text NOT NULL COMMENT '扩展字段 (JSON格式，预留扩展)';
