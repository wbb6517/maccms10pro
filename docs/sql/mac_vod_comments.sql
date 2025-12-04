-- ============================================================
-- mac_vod 表字段注释 (Video Data Table Comments)
-- ============================================================
--
-- 【表说明】
-- 视频/影视数据主表，MacCMS 最核心的内容存储表
-- 存储视频的基本信息、播放地址、下载地址、剧情等数据
--
-- 【播放/下载地址格式】
-- 多播放器用 $$$ 分隔: "hnm3u8$$$kbm3u8$$$wjm3u8"
-- 多集用 # 分隔: "第1集$url1#第2集$url2#第3集$url3"
-- 完整格式: "播放器1地址$$$播放器2地址"
--
-- 【关联表】
-- - mac_type: 分类表 (type_id, type_id_1)
-- - mac_group: 用户组表 (group_id)
-- - mac_vod_repeat: 重复数据缓存表
-- - mac_role: 角色表 (role_rid = vod_id)
--
-- 执行方式: 在 MySQL 客户端中执行此文件
-- ============================================================

-- 表注释
ALTER TABLE `mac_vod` COMMENT '视频数据表 - 存储影视/视频的完整信息';

-- ==================== 主键和关联字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '视频ID (主键,自增)';
ALTER TABLE `mac_vod` MODIFY COLUMN `type_id` smallint NOT NULL DEFAULT 0 COMMENT '分类ID (关联mac_type)';
ALTER TABLE `mac_vod` MODIFY COLUMN `type_id_1` smallint unsigned NOT NULL DEFAULT 0 COMMENT '一级分类ID (父分类)';
ALTER TABLE `mac_vod` MODIFY COLUMN `group_id` smallint unsigned NOT NULL DEFAULT 0 COMMENT '用户组ID (关联mac_group,用于权限控制)';

-- ==================== 基本信息字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_name` varchar(255) NOT NULL DEFAULT '' COMMENT '视频名称';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题/别名';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名/拼音 (用于URL)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '审核状态: 0=未审核,1=已审核';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母 (A-Z,用于字母筛选)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色 (十六进制颜色码)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_tag` varchar(100) NOT NULL DEFAULT '' COMMENT 'TAG标签 (逗号分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类/类型 (如:动作,喜剧,爱情)';

-- ==================== 图片字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面图URL';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pic_thumb` varchar(1024) NOT NULL DEFAULT '' COMMENT '缩略图URL';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pic_slide` varchar(1024) NOT NULL DEFAULT '' COMMENT '幻灯片图URL (推荐位大图)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pic_screenshot` text COMMENT '视频截图 (多张用#分隔)';

-- ==================== 演职人员字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_actor` varchar(255) NOT NULL DEFAULT '' COMMENT '演员 (逗号分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_director` varchar(255) NOT NULL DEFAULT '' COMMENT '导演 (逗号分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_writer` varchar(100) NOT NULL DEFAULT '' COMMENT '编剧';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_behind` varchar(100) NOT NULL DEFAULT '' COMMENT '幕后制作人员';

-- ==================== 描述字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介/摘要 (自动从内容截取)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注 (如:HD,蓝光,1080P)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pubdate` varchar(100) NOT NULL DEFAULT '' COMMENT '上映日期';

-- ==================== 剧集信息字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_total` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '总集数';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_serial` varchar(20) NOT NULL DEFAULT '0' COMMENT '连载集数 (当前更新到第几集)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_tv` varchar(30) NOT NULL DEFAULT '' COMMENT '播出电视台/平台';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_weekday` varchar(30) NOT NULL DEFAULT '' COMMENT '更新日期 (如:周一,周三)';

-- ==================== 地区语言年份字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_area` varchar(20) NOT NULL DEFAULT '' COMMENT '地区 (如:中国大陆,香港,美国)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_lang` varchar(10) NOT NULL DEFAULT '' COMMENT '语言 (如:国语,粤语,英语)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_year` varchar(10) NOT NULL DEFAULT '' COMMENT '年份 (如:2024)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_version` varchar(30) NOT NULL DEFAULT '' COMMENT '版本 (如:TV版,剧场版)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_state` varchar(30) NOT NULL DEFAULT '' COMMENT '资源状态 (如:正片,预告片)';

-- ==================== 其他信息字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_author` varchar(60) NOT NULL DEFAULT '' COMMENT '编辑/发布者';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL (设置后点击跳转到外部链接)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '详情页模板 (留空用默认)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_tpl_play` varchar(30) NOT NULL DEFAULT '' COMMENT '播放页模板';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_tpl_down` varchar(30) NOT NULL DEFAULT '' COMMENT '下载页模板';

-- ==================== 状态控制字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_isend` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '完结状态: 0=连载中,1=已完结';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_lock` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '锁定状态: 0=未锁定,1=已锁定 (锁定后采集不更新)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_level` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '推荐等级: 0=普通,1-8=等级,9=幻灯片';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_copyright` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '版权保护: 0=关闭,1=开启';

-- ==================== 积分字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_points` smallint unsigned NOT NULL DEFAULT 0 COMMENT '积分 (通用)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_points_play` smallint unsigned NOT NULL DEFAULT 0 COMMENT '播放所需积分';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_points_down` smallint unsigned NOT NULL DEFAULT 0 COMMENT '下载所需积分';

-- ==================== 点击统计字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_hits` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '总点击量';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_hits_day` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '日点击量';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_hits_week` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '周点击量';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_hits_month` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '月点击量';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_duration` varchar(10) NOT NULL DEFAULT '' COMMENT '时长 (如:120分钟)';

-- ==================== 评分字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_up` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '顶/赞数';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_down` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '踩数';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_score` decimal(3,1) unsigned NOT NULL DEFAULT 0.0 COMMENT '评分 (0.0-10.0)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_score_all` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '评分总分';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_score_num` mediumint unsigned NOT NULL DEFAULT 0 COMMENT '评分人数';

-- ==================== 时间戳字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_time` int unsigned NOT NULL DEFAULT 0 COMMENT '更新时间戳';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_time_add` int unsigned NOT NULL DEFAULT 0 COMMENT '添加时间戳';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_time_hits` int unsigned NOT NULL DEFAULT 0 COMMENT '最后点击时间戳';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_time_make` int unsigned NOT NULL DEFAULT 0 COMMENT '静态页生成时间戳';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_trysee` smallint unsigned NOT NULL DEFAULT 0 COMMENT '试看时长(分钟): 0=不限制';

-- ==================== 豆瓣字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_douban_id` int unsigned NOT NULL DEFAULT 0 COMMENT '豆瓣ID';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_douban_score` decimal(3,1) unsigned NOT NULL DEFAULT 0.0 COMMENT '豆瓣评分';

-- ==================== 关联字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_reurl` varchar(255) NOT NULL DEFAULT '' COMMENT '来源URL (采集时记录)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_rel_vod` varchar(255) NOT NULL DEFAULT '' COMMENT '关联视频 (ID或名称,逗号分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_rel_art` varchar(255) NOT NULL DEFAULT '' COMMENT '关联文章 (ID或名称,逗号分隔)';

-- ==================== 密码保护字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pwd` varchar(10) NOT NULL DEFAULT '' COMMENT '详情页访问密码';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pwd_url` varchar(255) NOT NULL DEFAULT '' COMMENT '详情页密码获取URL';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pwd_play` varchar(10) NOT NULL DEFAULT '' COMMENT '播放页访问密码';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pwd_play_url` varchar(255) NOT NULL DEFAULT '' COMMENT '播放页密码获取URL';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pwd_down` varchar(10) NOT NULL DEFAULT '' COMMENT '下载页访问密码';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_pwd_down_url` varchar(255) NOT NULL DEFAULT '' COMMENT '下载页密码获取URL';

-- ==================== 内容字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_content` mediumtext NOT NULL COMMENT '视频简介/详细内容 (HTML格式)';

-- ==================== 播放信息字段 ====================
-- 多播放器用$$$分隔, 如: "hnm3u8$$$kbm3u8"
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_play_from` varchar(255) NOT NULL DEFAULT '' COMMENT '播放来源编码 (多组用$$$分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_play_server` varchar(255) NOT NULL DEFAULT '' COMMENT '播放服务器组 (多组用$$$分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_play_note` varchar(255) NOT NULL DEFAULT '' COMMENT '播放备注 (多组用$$$分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_play_url` mediumtext NOT NULL COMMENT '播放地址 (多组$$$分隔,每组内#分隔多集,集格式:集名$地址)';

-- ==================== 下载信息字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_down_from` varchar(255) NOT NULL DEFAULT '' COMMENT '下载来源编码 (多组用$$$分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_down_server` varchar(255) NOT NULL DEFAULT '' COMMENT '下载服务器组 (多组用$$$分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_down_note` varchar(255) NOT NULL DEFAULT '' COMMENT '下载备注 (多组用$$$分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_down_url` mediumtext NOT NULL COMMENT '下载地址 (多组$$$分隔,每组内#分隔多集,集格式:集名$地址)';

-- ==================== 剧情字段 ====================
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_plot` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '是否有剧情: 0=无,1=有';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_plot_name` mediumtext NOT NULL COMMENT '剧情标题 (多集用$$$分隔)';
ALTER TABLE `mac_vod` MODIFY COLUMN `vod_plot_detail` mediumtext NOT NULL COMMENT '剧情内容 (多集用$$$分隔)';

-- ============================================================
-- 执行完成提示
-- ============================================================
-- SELECT '字段注释添加完成!' AS Message;
