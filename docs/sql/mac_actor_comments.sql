-- ============================================================
-- mac_actor 表字段注释 (Actor Data Table Comments)
-- ============================================================
--
-- 【表说明】
-- 演员/明星数据表，存储演员的基本信息、作品、照片等数据
-- 用于前台演员库展示和视频关联
--
-- 【关联表】
-- - mac_type: 分类表 (type_id, type_id_1)
-- - mac_vod: 视频表 (通过 vod_actor 字段关联)
--
-- 【缓存机制】
-- - 详情页缓存: actor_detail_{id}、actor_detail_{en}
-- - 列表缓存: md5(查询条件) 作为缓存键
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_actor` COMMENT '演员数据表 - 存储演员/明星的完整信息';

-- ==================== 主键和关联字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '演员ID (主键,自增)';
ALTER TABLE `mac_actor` MODIFY COLUMN `type_id` smallint unsigned NOT NULL DEFAULT 0 COMMENT '分类ID (关联mac_type)';
ALTER TABLE `mac_actor` MODIFY COLUMN `type_id_1` smallint unsigned NOT NULL DEFAULT 0 COMMENT '一级分类ID (父分类)';

-- ==================== 基本信息字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_name` varchar(255) NOT NULL DEFAULT '' COMMENT '演员姓名';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名/拼音 (用于URL)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_alias` varchar(255) NOT NULL DEFAULT '' COMMENT '别名/艺名';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '审核状态: 0=未审核,1=已审核';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_lock` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '锁定状态: 0=未锁定,1=已锁定 (锁定后采集不更新)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母 (A-Z,用于字母筛选)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_sex` char(1) NOT NULL DEFAULT '' COMMENT '性别: 男/女/未知';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色 (十六进制颜色码)';

-- ==================== 图片和描述字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '演员照片URL';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介/摘要 (自动从内容截取100字)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注信息';

-- ==================== 个人信息字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_area` varchar(20) NOT NULL DEFAULT '' COMMENT '地区/国籍 (如:中国,美国)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_height` varchar(10) NOT NULL DEFAULT '' COMMENT '身高 (如:175cm)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_weight` varchar(10) NOT NULL DEFAULT '' COMMENT '体重 (如:65kg)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_birthday` varchar(10) NOT NULL DEFAULT '' COMMENT '生日 (如:1990-01-01)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_birtharea` varchar(20) NOT NULL DEFAULT '' COMMENT '出生地';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_blood` varchar(10) NOT NULL DEFAULT '' COMMENT '血型 (如:A,B,O,AB)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_starsign` varchar(10) NOT NULL DEFAULT '' COMMENT '星座 (如:白羊座,金牛座)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_school` varchar(20) NOT NULL DEFAULT '' COMMENT '毕业院校';

-- ==================== 作品和标签字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_works` varchar(255) NOT NULL DEFAULT '' COMMENT '代表作品 (逗号分隔)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_tag` varchar(255) NOT NULL DEFAULT '' COMMENT 'TAG标签 (逗号分隔)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类 (如:演员,歌手,导演)';

-- ==================== 推荐和等级字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_level` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '推荐等级: 0=普通,1-9=等级';

-- ==================== 时间戳字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_time` int unsigned NOT NULL DEFAULT 0 COMMENT '更新时间戳';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_time_add` int unsigned NOT NULL DEFAULT 0 COMMENT '添加时间戳';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_time_hits` int unsigned NOT NULL DEFAULT 0 COMMENT '最后点击时间戳';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_time_make` int unsigned NOT NULL DEFAULT 0 COMMENT '静态页生成时间戳';

-- ==================== 点击统计字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_hits` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '总点击量';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_hits_day` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '日点击量';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_hits_week` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '周点击量';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_hits_month` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '月点击量';

-- ==================== 评分字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_score` decimal(3,1) unsigned NOT NULL DEFAULT 0.0 COMMENT '评分 (0.0-10.0)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_score_all` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '评分总分';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_score_num` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '评分人数';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_up` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '顶/赞数';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_down` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '踩数';

-- ==================== 模板和跳转字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '详情页模板 (留空用默认)';
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL (设置后点击跳转到外部链接)';

-- ==================== 内容字段 ====================
ALTER TABLE `mac_actor` MODIFY COLUMN `actor_content` text NOT NULL COMMENT '演员详细介绍 (HTML格式)';

-- ============================================================
-- 执行完成提示
-- ============================================================
-- SELECT '字段注释添加完成!' AS Message;