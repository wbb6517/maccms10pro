-- ============================================================
-- mac_manga 表字段注释 (Manga Table Comments)
-- ============================================================
--
-- 【表说明】
-- 漫画数据表，存储漫画内容信息
-- 支持章节管理、图片管理、评分点击统计等功能
--
-- 【关联表】
-- - mac_type: 分类关联 (type_id, type_id_1)
-- - mac_group: 用户组权限 (group_id)
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_manga` COMMENT '漫画数据表';

-- ==================== 主键与关联ID ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '漫画ID，主键自增';
ALTER TABLE `mac_manga` MODIFY COLUMN `type_id` smallint unsigned NOT NULL DEFAULT '0' COMMENT '分类ID，关联mac_type表';
ALTER TABLE `mac_manga` MODIFY COLUMN `type_id_1` smallint unsigned NOT NULL DEFAULT '0' COMMENT '一级分类ID，关联mac_type表';
ALTER TABLE `mac_manga` MODIFY COLUMN `group_id` smallint unsigned NOT NULL DEFAULT '0' COMMENT '用户组ID，用于权限控制';

-- ==================== 基本信息 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_name` varchar(255) NOT NULL DEFAULT '' COMMENT '漫画名称';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名/拼音，用于URL';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母，用于字母筛选';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色，16进制颜色值';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_from` varchar(30) NOT NULL DEFAULT '' COMMENT '来源';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_author` varchar(255) NOT NULL DEFAULT '' COMMENT '作者';

-- ==================== 状态与属性 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_status` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '审核状态: 0=未审核 1=已审核';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_level` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '推荐等级: 0=无 1-8=等级 9=幻灯';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_lock` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '锁定状态: 0=未锁定 1=已锁定';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_serial` varchar(20) NOT NULL DEFAULT '0' COMMENT '连载状态';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_total` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '章节总数';

-- ==================== 分类与标签 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_tag` varchar(100) NOT NULL DEFAULT '' COMMENT 'Tag标签，逗号分隔';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类，逗号分隔';

-- ==================== 图片信息 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面图片URL';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_pic_thumb` varchar(1024) NOT NULL DEFAULT '' COMMENT '缩略图URL';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_pic_slide` varchar(1024) NOT NULL DEFAULT '' COMMENT '幻灯图URL';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_pic_screenshot` text COMMENT '截图列表，#号分隔';

-- ==================== 内容信息 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注，如"更新至XX话"';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_content` mediumtext COMMENT '漫画简介内容，$$$分隔多段';

-- ==================== 章节数据 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_chapter_from` varchar(255) NOT NULL DEFAULT '' COMMENT '章节来源标识，$$$分隔多个来源';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_chapter_url` mediumtext COMMENT '章节URL列表，格式: 章节名$图片URL,图片URL#章节名$图片URL';

-- ==================== 积分与权限 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_points` smallint unsigned NOT NULL DEFAULT '0' COMMENT '全部积分，观看整本漫画所需积分';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_points_detail` smallint unsigned NOT NULL DEFAULT '0' COMMENT '详情积分，查看详情所需积分';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_pwd` varchar(10) NOT NULL DEFAULT '' COMMENT '访问密码';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_pwd_url` varchar(255) NOT NULL DEFAULT '' COMMENT '密码获取URL';

-- ==================== 点击与互动 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_hits` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '总点击量';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_hits_day` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '日点击量';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_hits_week` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '周点击量';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_hits_month` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '月点击量';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_up` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '顶次数';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_down` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '踩次数';

-- ==================== 评分信息 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' COMMENT '评分，0-10分';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_score_all` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '评分总和';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_score_num` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '评分人数';

-- ==================== 时间信息 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间戳';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_time_add` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间戳';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_time_hits` int unsigned NOT NULL DEFAULT '0' COMMENT '点击更新时间戳';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_time_make` int unsigned NOT NULL DEFAULT '0' COMMENT '静态页生成时间戳';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_last_update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '最后更新时间戳';

-- ==================== 关联数据 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_rel_manga` varchar(255) NOT NULL DEFAULT '' COMMENT '关联漫画ID，逗号分隔';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_rel_vod` varchar(255) NOT NULL DEFAULT '' COMMENT '关联视频ID，逗号分隔';

-- ==================== 其他信息 ====================
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL，点击后跳转到外部链接';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '独立模板名称';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_age_rating` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '年龄分级';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_orientation` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '漫画方向: 0=竖屏 1=横屏';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_is_vip` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'VIP标识: 0=普通 1=VIP';
ALTER TABLE `mac_manga` MODIFY COLUMN `manga_copyright_info` varchar(255) NOT NULL DEFAULT '' COMMENT '版权信息';
