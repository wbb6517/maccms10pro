-- ============================================================
-- mac_cash 表字段注释 (提现记录表)
-- ============================================================
--
-- 【表说明】
-- 记录用户的积分提现申请
-- 用户申请提现后，管理员审核通过后线下打款
--
-- 【业务场景】
-- 1. 用户在前台申请积分提现
-- 2. 管理员在后台审核提现申请
-- 3. 审核通过后线下完成打款
--
-- 【相关文件】
-- - 后台控制器：application/admin/controller/Cash.php
-- - 数据模型  ：application/common/model/Cash.php
-- - 验证器    ：application/common/validate/Cash.php
-- - 前台用户  ：application/index/controller/User.php
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_cash` COMMENT = '提现记录表 - 记录用户的积分提现申请';

-- 字段注释
ALTER TABLE `mac_cash`
    MODIFY COLUMN `cash_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID，主键自增',
    MODIFY COLUMN `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID，关联mac_user表',
    MODIFY COLUMN `cash_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '审核状态：0=待审核 1=已审核',
    MODIFY COLUMN `cash_points` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '提现积分数（根据金额和兑换比例计算）',
    MODIFY COLUMN `cash_money` decimal(12,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '提现金额（元）',
    MODIFY COLUMN `cash_bank_name` varchar(60) NOT NULL DEFAULT '' COMMENT '银行名称（如：中国银行、支付宝、微信）',
    MODIFY COLUMN `cash_bank_no` varchar(30) NOT NULL DEFAULT '' COMMENT '银行账号/支付宝账号/微信号',
    MODIFY COLUMN `cash_payee_name` varchar(30) NOT NULL DEFAULT '' COMMENT '收款人姓名',
    MODIFY COLUMN `cash_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '申请时间戳',
    MODIFY COLUMN `cash_time_audit` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核时间戳（审核通过时记录）';

-- ============================================================
-- 字段详细说明
-- ============================================================
--
-- 【cash_status 审核状态】
-- - 0 = 待审核（用户已提交，等待管理员审核）
-- - 1 = 已审核（管理员已审核通过）
--
-- 【cash_points 提现积分】
-- - 根据提现金额和兑换比例计算
-- - 计算公式：cash_points = cash_money × cash_ratio
-- - cash_ratio 配置在 config('maccms.user.cash_ratio')
-- - 例：提现100元，比例10，则需要1000积分
--
-- 【cash_bank_name 银行名称】
-- - 支持银行卡：中国银行、工商银行、建设银行等
-- - 支持第三方：支付宝、微信
-- - 用户自行填写
--
-- ============================================================
-- 积分流转说明
-- ============================================================
--
-- 用户有两种积分：
-- - user_points      : 可用积分（可以消费、提现）
-- - user_points_froze: 冻结积分（提现申请中，暂时冻结）
--
-- 【提现业务流程】
-- ┌─────────────┬────────────────────────────────────────────────┐
-- │ 操作         │ 积分变化                                        │
-- ├─────────────┼────────────────────────────────────────────────┤
-- │ 1.申请提现   │ user_points -= 积分, user_points_froze += 积分 │
-- │ 2.审核通过   │ user_points_froze -= 积分, 记录plog(type=9)    │
-- │ 删除待审核   │ user_points += 积分, user_points_froze -= 积分 │
-- │ 删除已审核   │ 不处理（积分已扣除）                            │
-- └─────────────┴────────────────────────────────────────────────┘
--
-- ============================================================
-- 相关配置说明
-- ============================================================
--
-- 配置位置：后台 → 系统 → 用户配置
-- 配置键：config('maccms.user.xxx')
--
-- 【cash_status】提现功能开关
-- - 1 = 开启提现功能
-- - 0 = 关闭提现功能
--
-- 【cash_min】最低提现金额
-- - 用户提现金额必须 >= 此值
-- - 例：设置为 100，则用户最少提现100元
--
-- 【cash_ratio】积分兑换比例
-- - 每提现1元需要多少积分
-- - 例：设置为 10，则提现100元需要1000积分
--
-- ============================================================
-- 常用查询示例
-- ============================================================
--
-- 查询所有待审核的提现申请
-- SELECT * FROM mac_cash
-- WHERE cash_status = 0
-- ORDER BY cash_time DESC;
--
-- 查询用户的提现记录
-- SELECT * FROM mac_cash
-- WHERE user_id = 123
-- ORDER BY cash_time DESC;
--
-- 统计待审核提现总金额
-- SELECT COUNT(*) as total_count, SUM(cash_money) as total_money
-- FROM mac_cash
-- WHERE cash_status = 0;
--
-- 统计用户已提现总金额
-- SELECT SUM(cash_money) as total_money
-- FROM mac_cash
-- WHERE user_id = 123 AND cash_status = 1;
--
-- 查询某时间段的提现记录
-- SELECT * FROM mac_cash
-- WHERE cash_time >= UNIX_TIMESTAMP('2024-01-01')
--   AND cash_time < UNIX_TIMESTAMP('2024-02-01')
-- ORDER BY cash_time DESC;
--
-- 查询某银行的提现记录
-- SELECT * FROM mac_cash
-- WHERE cash_bank_name LIKE '%支付宝%'
-- ORDER BY cash_time DESC;
--
-- ============================================================
