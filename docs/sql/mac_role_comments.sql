-- ============================================================
-- mac_role 表字段注释 (Role Data Table Comments)
-- ============================================================
--
-- 【表说明】
-- 角色数据表，存储视频中角色的信息
-- 角色是视频内容的扩展信息，对应电视剧/电影中的人物
--
-- 【业务场景】
-- - 电视剧/电影中的角色信息管理
-- - 角色与视频是多对一关系 (一个视频可有多个角色)
-- - 前台可展示角色列表、角色详情、按演员筛选等
--
-- 【关联表】
-- - mac_vod: 视频表 (role_rid → vod_id)
--
-- 【缓存机制】
-- - 详情页缓存: role_detail_{id}、role_detail_{en}
-- - 列表缓存: md5(查询条件) 作为缓存键
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_role` COMMENT '角色数据表 - 存储视频角色/人物的完整信息';

-- ==================== 主键和关联字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '角色ID (主键,自增)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_rid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联视频ID (外键→mac_vod.vod_id)';

-- ==================== 基本信息字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_name` varchar(255) NOT NULL DEFAULT '' COMMENT '角色名称 (如:孙悟空)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名/拼音 (用于URL友好)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '审核状态: 0=未审核,1=已审核';
ALTER TABLE `mac_role` MODIFY COLUMN `role_lock` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '锁定状态: 0=未锁定,1=已锁定 (锁定后采集不更新)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母 (A-Z,用于字母筛选)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色 (十六进制颜色码)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_actor` varchar(255) NOT NULL DEFAULT '' COMMENT '扮演者/演员名 (如:章金莱)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注/简介信息';

-- ==================== 图片和排序字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '角色图片URL';
ALTER TABLE `mac_role` MODIFY COLUMN `role_sort` smallint unsigned NOT NULL DEFAULT 0 COMMENT '排序值 (用于同一视频内角色排序)';

-- ==================== 推荐等级字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_level` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '推荐等级: 0=普通,1-9=推荐等级';

-- ==================== 时间戳字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_time` int unsigned NOT NULL DEFAULT 0 COMMENT '更新时间戳';
ALTER TABLE `mac_role` MODIFY COLUMN `role_time_add` int unsigned NOT NULL DEFAULT 0 COMMENT '添加时间戳';
ALTER TABLE `mac_role` MODIFY COLUMN `role_time_hits` int unsigned NOT NULL DEFAULT 0 COMMENT '最后点击时间戳';
ALTER TABLE `mac_role` MODIFY COLUMN `role_time_make` int unsigned NOT NULL DEFAULT 0 COMMENT '静态页生成时间戳';

-- ==================== 点击统计字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_hits` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '总点击量';
ALTER TABLE `mac_role` MODIFY COLUMN `role_hits_day` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '日点击量';
ALTER TABLE `mac_role` MODIFY COLUMN `role_hits_week` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '周点击量';
ALTER TABLE `mac_role` MODIFY COLUMN `role_hits_month` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '月点击量';

-- ==================== 评分字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_score` decimal(3,1) unsigned NOT NULL DEFAULT 0.0 COMMENT '评分 (0.0-10.0)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_score_all` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '评分总分';
ALTER TABLE `mac_role` MODIFY COLUMN `role_score_num` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '评分人数';
ALTER TABLE `mac_role` MODIFY COLUMN `role_up` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '顶/赞数';
ALTER TABLE `mac_role` MODIFY COLUMN `role_down` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '踩数';

-- ==================== 模板和跳转字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '详情页模板 (留空用默认)';
ALTER TABLE `mac_role` MODIFY COLUMN `role_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL (设置后点击跳转到外部链接)';

-- ==================== 内容字段 ====================
ALTER TABLE `mac_role` MODIFY COLUMN `role_content` text NOT NULL COMMENT '角色详细介绍 (HTML格式,富文本)';

-- ============================================================
-- 执行完成提示
-- ============================================================
-- SELECT '字段注释添加完成!' AS Message;
