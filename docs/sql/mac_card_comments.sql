-- ============================================================
-- mac_card 表字段注释 (充值卡数据表)
-- ============================================================
--
-- 【表说明】
-- 存储系统生成的充值卡信息
-- 用户通过输入卡号和密码兑换积分
--
-- 【业务流程】
-- 1. 后台批量生成充值卡（设置面值、积分、卡号规则）
-- 2. 管理员导出CSV销售或分发给用户
-- 3. 用户在前台输入卡号密码使用
-- 4. 系统验证后增加用户积分，标记卡为已使用
--
-- 【相关文件】
-- - 后台控制器：application/admin/controller/Card.php
-- - 数据模型  ：application/common/model/Card.php
-- - 前台使用  ：用户中心的充值卡页面
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_card` COMMENT = '充值卡数据表 - 存储积分充值卡信息，用户输入卡号密码兑换积分';

-- 字段注释
ALTER TABLE `mac_card`
    MODIFY COLUMN `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '充值卡ID，主键自增',
    MODIFY COLUMN `card_no` varchar(30) NOT NULL DEFAULT '' COMMENT '卡号，16位随机字符串，用户使用时输入',
    MODIFY COLUMN `card_pwd` varchar(20) NOT NULL DEFAULT '' COMMENT '密码，8位随机字符串，与卡号配合使用',
    MODIFY COLUMN `card_money` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '面值（金额），显示价格，用于销售定价参考',
    MODIFY COLUMN `card_points` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '积分值，用户使用后获得的实际积分数',
    MODIFY COLUMN `card_sale_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '销售状态：0=未销售 1=已销售',
    MODIFY COLUMN `card_use_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '使用状态：0=未使用 1=已使用',
    MODIFY COLUMN `card_add_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '生成时间戳，同批次生成的卡时间相同',
    MODIFY COLUMN `card_use_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '使用时间戳，用户兑换成功时记录',
    MODIFY COLUMN `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '使用者用户ID，使用后记录，0表示未使用';

-- ============================================================
-- 字段详细说明
-- ============================================================
--
-- 【card_no 卡号】
-- - 长度：16位
-- - 生成规则：可选纯数字、纯字母或混合
-- - 使用 mac_get_rndstr(16, role_no) 生成
-- - 以卡号为键存储，自动去重
--
-- 【card_pwd 密码】
-- - 长度：8位
-- - 生成规则：可选纯数字、纯字母或混合
-- - 使用 mac_get_rndstr(8, role_pwd) 生成
--
-- 【card_money 面值】
-- - 仅用于显示和销售参考
-- - 不影响实际积分获取
-- - 例：面值100元，积分1000分
--
-- 【card_points 积分值】
-- - 用户使用后实际获得的积分
-- - 直接增加到用户的 user_points 字段
--
-- 【card_sale_status 销售状态】
-- - 0 = 未销售（刚生成）
-- - 1 = 已销售（使用时自动标记）
--
-- 【card_use_status 使用状态】
-- - 0 = 未使用（可用）
-- - 1 = 已使用（已兑换）
-- - 使用时同时更新 sale_status 为已销售
--
-- 【card_add_time 生成时间】
-- - Unix时间戳格式
-- - 同批次生成的充值卡时间相同
-- - 可按时间筛选最新批次
--
-- 【card_use_time 使用时间】
-- - Unix时间戳格式
-- - 用户成功兑换时记录
-- - 未使用时为0
--
-- 【user_id 使用者ID】
-- - 关联 mac_user 表
-- - 记录是谁使用了这张卡
-- - 未使用时为0
--
-- ============================================================
-- 常用查询示例
-- ============================================================
--
-- 查询未使用的充值卡
-- SELECT * FROM mac_card WHERE card_use_status = 0;
--
-- 查询最新批次的充值卡
-- SELECT * FROM mac_card WHERE card_add_time = (SELECT MAX(card_add_time) FROM mac_card);
--
-- 查询某用户使用过的充值卡
-- SELECT * FROM mac_card WHERE user_id = 123;
--
-- 统计各状态的卡数量
-- SELECT card_use_status, COUNT(*) as total FROM mac_card GROUP BY card_use_status;
--
-- ============================================================
