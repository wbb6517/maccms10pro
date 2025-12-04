-- ============================================================
-- mac_art 表字段注释 (Article Data Table Comments)
-- ============================================================
--
-- 【表说明】
-- 文章/资讯数据表，存储文章内容的完整信息
-- 支持多页内容、分类管理、图片展示等功能
--
-- 【业务场景】
-- - 后台文章/资讯/新闻的管理
-- - 前台文章列表、详情页展示
-- - 支持多页内容(分页文章)
--
-- 【关联表】
-- - mac_type: 分类表 (type_id → type_id)
-- - mac_vod: 视频表 (art_rel_vod → vod_id)
--
-- 【缓存机制】
-- - 详情页缓存: art_detail_{id}、art_detail_{en}
-- - 列表缓存: md5(查询条件) 作为缓存键
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_art` COMMENT '文章数据表 - 存储文章/资讯的完整信息';

-- ==================== 主键和分类字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '文章ID (主键,自增)';
ALTER TABLE `mac_art` MODIFY COLUMN `type_id` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '分类ID (外键→mac_type.type_id)';
ALTER TABLE `mac_art` MODIFY COLUMN `type_id_1` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '一级分类ID (冗余字段,便于查询)';
ALTER TABLE `mac_art` MODIFY COLUMN `group_id` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '会员组ID (用于权限控制)';

-- ==================== 基本信息字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_name` varchar(255) NOT NULL DEFAULT '' COMMENT '文章标题';
ALTER TABLE `mac_art` MODIFY COLUMN `art_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题';
ALTER TABLE `mac_art` MODIFY COLUMN `art_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名/拼音 (用于URL友好)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '审核状态: 0=未审核,1=已审核';
ALTER TABLE `mac_art` MODIFY COLUMN `art_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母 (A-Z,用于字母筛选)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色 (十六进制颜色码)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_from` varchar(30) NOT NULL DEFAULT '' COMMENT '来源/出处';
ALTER TABLE `mac_art` MODIFY COLUMN `art_author` varchar(30) NOT NULL DEFAULT '' COMMENT '作者';
ALTER TABLE `mac_art` MODIFY COLUMN `art_tag` varchar(100) NOT NULL DEFAULT '' COMMENT 'TAG标签 (逗号分隔)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类 (逗号分隔)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注信息';

-- ==================== 图片字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面图片URL';
ALTER TABLE `mac_art` MODIFY COLUMN `art_pic_thumb` varchar(1024) NOT NULL DEFAULT '' COMMENT '缩略图URL';
ALTER TABLE `mac_art` MODIFY COLUMN `art_pic_slide` varchar(1024) NOT NULL DEFAULT '' COMMENT '幻灯片图URL';
ALTER TABLE `mac_art` MODIFY COLUMN `art_pic_screenshot` text COMMENT '截图列表 (多图,换行分隔)';

-- ==================== 内容字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介/摘要';
ALTER TABLE `mac_art` MODIFY COLUMN `art_title` mediumtext NOT NULL COMMENT '分页标题 ($$分隔多页标题)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_note` mediumtext NOT NULL COMMENT '分页备注 ($$分隔多页备注)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_content` mediumtext NOT NULL COMMENT '文章内容 ($$分隔多页内容,HTML格式)';

-- ==================== 推荐和状态字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_level` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '推荐等级: 0=普通,1-8=推荐,9=幻灯';
ALTER TABLE `mac_art` MODIFY COLUMN `art_lock` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '锁定状态: 0=未锁定,1=已锁定 (锁定后采集不更新)';

-- ==================== 积分字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_points` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '阅读所需积分';
ALTER TABLE `mac_art` MODIFY COLUMN `art_points_detail` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '详情页所需积分';

-- ==================== 互动统计字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_up` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '顶/赞数';
ALTER TABLE `mac_art` MODIFY COLUMN `art_down` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '踩数';
ALTER TABLE `mac_art` MODIFY COLUMN `art_hits` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '总点击量';
ALTER TABLE `mac_art` MODIFY COLUMN `art_hits_day` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '日点击量';
ALTER TABLE `mac_art` MODIFY COLUMN `art_hits_week` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '周点击量';
ALTER TABLE `mac_art` MODIFY COLUMN `art_hits_month` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '月点击量';

-- ==================== 时间戳字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '更新时间戳';
ALTER TABLE `mac_art` MODIFY COLUMN `art_time_add` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '添加时间戳';
ALTER TABLE `mac_art` MODIFY COLUMN `art_time_hits` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '最后点击时间戳';
ALTER TABLE `mac_art` MODIFY COLUMN `art_time_make` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '静态页生成时间戳';

-- ==================== 评分字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_score` decimal(3,1) unsigned NOT NULL DEFAULT 0.0 COMMENT '评分 (0.0-10.0)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_score_all` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '评分总分';
ALTER TABLE `mac_art` MODIFY COLUMN `art_score_num` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '评分人数';

-- ==================== 关联字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_rel_art` varchar(255) NOT NULL DEFAULT '' COMMENT '关联文章ID (逗号分隔)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_rel_vod` varchar(255) NOT NULL DEFAULT '' COMMENT '关联视频ID (逗号分隔)';

-- ==================== 密码和跳转字段 ====================
ALTER TABLE `mac_art` MODIFY COLUMN `art_pwd` varchar(10) NOT NULL DEFAULT '' COMMENT '访问密码 (留空不限)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_pwd_url` varchar(255) NOT NULL DEFAULT '' COMMENT '密码获取URL';
ALTER TABLE `mac_art` MODIFY COLUMN `art_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL (设置后点击跳转到外部链接)';
ALTER TABLE `mac_art` MODIFY COLUMN `art_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '详情页模板 (留空用默认)';

-- ============================================================
-- 执行完成提示
-- ============================================================
-- SELECT '字段注释添加完成!' AS Message;
