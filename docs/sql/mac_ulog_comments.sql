-- ============================================================
-- mac_ulog 表字段注释 (用户访问日志表)
-- ============================================================
--
-- 【表说明】
-- 记录用户的访问行为日志，包括浏览、收藏、播放、下载等
-- 主要用于用户历史记录、收藏夹和积分消费防重复扣费
--
-- 【业务场景】
-- 1. 用户浏览记录：用户查看详情页时记录
-- 2. 收藏功能：用户添加收藏时记录
-- 3. 播放历史：用户播放视频时记录
-- 4. 下载记录：用户下载内容时记录
-- 5. 积分消费：用户支付积分后记录（防止重复扣费）
--
-- 【相关文件】
-- - 后台控制器：application/admin/controller/Ulog.php
-- - 数据模型  ：application/common/model/Ulog.php
-- - 前台用户中心：application/index/controller/User.php
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_ulog` COMMENT = '用户访问日志表 - 记录浏览、收藏、播放、下载等行为';

-- 字段注释
ALTER TABLE `mac_ulog`
    MODIFY COLUMN `ulog_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID，主键自增',
    MODIFY COLUMN `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID，关联mac_user表',
    MODIFY COLUMN `ulog_mid` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '模型类型：1=视频 2=文章 3=专题 8=演员',
    MODIFY COLUMN `ulog_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '日志类型：1=浏览 2=收藏 3=想看 4=播放 5=下载',
    MODIFY COLUMN `ulog_rid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '关联内容ID（vod_id/art_id/topic_id/actor_id）',
    MODIFY COLUMN `ulog_sid` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '播放组/下载组索引（从1开始）',
    MODIFY COLUMN `ulog_nid` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '集数索引（从1开始）',
    MODIFY COLUMN `ulog_points` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '消费积分数，0表示免费访问',
    MODIFY COLUMN `ulog_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '记录时间戳';

-- ============================================================
-- 字段详细说明
-- ============================================================
--
-- 【ulog_mid 模型类型】
-- - 1 = 视频(vod)，关联 mac_vod 表
-- - 2 = 文章(art)，关联 mac_art 表
-- - 3 = 专题(topic)，关联 mac_topic 表
-- - 8 = 演员(actor)，关联 mac_actor 表
-- 使用 mac_get_mid_text() 函数转换显示
--
-- 【ulog_type 日志类型】
-- - 1 = 浏览记录（用户查看详情页）
-- - 2 = 收藏记录（用户添加到收藏夹）
-- - 3 = 想看/追剧（用户标记想看）
-- - 4 = 播放记录（用户播放视频）
-- - 5 = 下载记录（用户下载内容）
-- 使用 mac_get_ulog_type_text() 函数转换显示
--
-- 【ulog_rid 关联内容ID】
-- - 根据 ulog_mid 的值，关联不同的内容表
-- - mid=1 时关联 mac_vod.vod_id
-- - mid=2 时关联 mac_art.art_id
-- - mid=3 时关联 mac_topic.topic_id
-- - mid=8 时关联 mac_actor.actor_id
--
-- 【ulog_sid 播放组索引】
-- - 视频可能有多个播放源（如：优酷、腾讯、爱奇艺）
-- - 此字段记录用户观看的是第几个播放组
-- - 从1开始，0表示未记录具体播放组
--
-- 【ulog_nid 集数索引】
-- - 电视剧等连续剧有多集
-- - 此字段记录用户观看的是第几集
-- - 从1开始，0表示未记录具体集数
--
-- 【ulog_points 消费积分】
-- - 记录用户为此内容支付的积分
-- - 0 表示免费访问（未扣除积分）
-- - 大于0 表示付费观看/下载
-- - 用于判断用户是否已付费，防止重复扣费
--
-- ============================================================
-- 积分消费防重复扣费逻辑
-- ============================================================
--
-- 当用户访问付费内容时的检查流程：
--
-- 1. 检查是否已有消费记录
--    SELECT * FROM mac_ulog
--    WHERE user_id = ?
--      AND ulog_mid = ?
--      AND ulog_rid = ?
--      AND ulog_sid = ?
--      AND ulog_nid = ?
--      AND ulog_points > 0
--
-- 2. 如果有记录，则不再扣费，直接允许访问
--
-- 3. 如果无记录，则：
--    a. 扣除用户积分
--    b. 写入 ulog 记录
--    c. 允许用户访问
--
-- ============================================================
-- 常用查询示例
-- ============================================================
--
-- 查询用户的浏览历史
-- SELECT * FROM mac_ulog
-- WHERE user_id = 123 AND ulog_type = 1
-- ORDER BY ulog_time DESC;
--
-- 查询用户的收藏列表
-- SELECT * FROM mac_ulog
-- WHERE user_id = 123 AND ulog_type = 2
-- ORDER BY ulog_time DESC;
--
-- 查询用户的播放记录
-- SELECT * FROM mac_ulog
-- WHERE user_id = 123 AND ulog_type = 4
-- ORDER BY ulog_time DESC;
--
-- 查询用户的付费记录
-- SELECT * FROM mac_ulog
-- WHERE user_id = 123 AND ulog_points > 0
-- ORDER BY ulog_time DESC;
--
-- 统计各类型日志数量
-- SELECT ulog_type, COUNT(*) as total
-- FROM mac_ulog
-- GROUP BY ulog_type;
--
-- 检查用户是否已为某视频某集付费
-- SELECT COUNT(*) FROM mac_ulog
-- WHERE user_id = 123
--   AND ulog_mid = 1
--   AND ulog_rid = 456
--   AND ulog_sid = 1
--   AND ulog_nid = 1
--   AND ulog_points > 0;
--
-- ============================================================
