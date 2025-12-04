-- ============================================================
-- mac_order 表字段注释 (会员订单数据表)
-- ============================================================
--
-- 【表说明】
-- 存储用户充值积分的订单记录
-- 用户在前台发起充值，支付成功后获得积分
--
-- 【业务流程】
-- 1. 用户在前台选择充值套餐，创建订单
-- 2. 用户跳转到支付平台完成支付
-- 3. 支付平台回调 Order::notify() 方法
-- 4. 系统更新订单状态，增加用户积分
-- 5. 后台可查看所有订单记录
--
-- 【相关文件】
-- - 后台控制器：application/admin/controller/Order.php
-- - 数据模型  ：application/common/model/Order.php
-- - 支付回调  ：Order Model::notify()
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_order` COMMENT = '会员订单数据表 - 存储用户充值积分的订单记录';

-- 字段注释
ALTER TABLE `mac_order`
    MODIFY COLUMN `order_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '订单ID，主键自增',
    MODIFY COLUMN `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID，关联mac_user表',
    MODIFY COLUMN `order_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '订单状态：0=未支付 1=已支付',
    MODIFY COLUMN `order_code` varchar(30) NOT NULL DEFAULT '' COMMENT '订单号，系统生成的唯一标识',
    MODIFY COLUMN `order_price` decimal(12,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '订单金额，用户实际支付的金额',
    MODIFY COLUMN `order_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '下单时间戳，用户发起充值的时间',
    MODIFY COLUMN `order_points` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '订单积分，支付成功后用户获得的积分',
    MODIFY COLUMN `order_pay_type` varchar(10) NOT NULL DEFAULT '' COMMENT '支付方式：alipay=支付宝 weixin=微信 bank=银行卡',
    MODIFY COLUMN `order_pay_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '支付时间戳，用户完成支付的时间',
    MODIFY COLUMN `order_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '订单备注，可存储额外信息';

-- ============================================================
-- 字段详细说明
-- ============================================================
--
-- 【order_code 订单号】
-- - 系统自动生成的唯一订单号
-- - 用于支付平台回调时识别订单
-- - 支付接口通常使用此字段作为商户订单号
--
-- 【order_price 订单金额】
-- - 用户实际支付的金额（人民币）
-- - decimal(10,2) 支持精确到分
-- - 例：9.90、99.00、199.00
--
-- 【order_points 订单积分】
-- - 用户支付成功后获得的积分
-- - 不一定与金额成正比，可设置优惠
-- - 例：充10元送100积分，充100元送1200积分
--
-- 【order_status 订单状态】
-- - 0 = 未支付（待付款）
-- - 1 = 已支付（已完成）
-- - 使用 mac_get_order_status_text() 函数转换显示
--
-- 【order_time 下单时间】
-- - Unix时间戳格式
-- - 用户发起充值请求的时间
-- - 在创建订单时设置
--
-- 【order_pay_time 支付时间】
-- - Unix时间戳格式
-- - 支付平台回调确认的时间
-- - 未支付时为0
--
-- 【order_pay_type 支付方式】
-- - alipay : 支付宝
-- - weixin : 微信支付
-- - bank   : 银行卡/网银
-- - 可自定义扩展（最长10个字符）
-- - 在支付回调时设置
--
-- 【order_remarks 订单备注】
-- - 可存储订单相关的额外信息
-- - 最长100个字符
--
-- ============================================================
-- 支付回调处理流程
-- ============================================================
--
-- Order Model::notify($order_code, $pay_type) 方法处理流程：
--
-- 1. 验证参数
--    - order_code 不能为空
--    - pay_type 不能为空
--
-- 2. 查询订单
--    SELECT * FROM mac_order WHERE order_code = ?
--
-- 3. 检查是否已支付（防重复处理）
--    IF order_status == 1 THEN 返回已支付
--
-- 4. 查询用户信息
--    SELECT * FROM mac_user WHERE user_id = ?
--
-- 5. 更新订单状态
--    UPDATE mac_order SET
--        order_status = 1,
--        order_pay_time = NOW(),
--        order_pay_type = ?
--    WHERE order_code = ?
--
-- 6. 增加用户积分
--    UPDATE mac_user SET
--        user_points = user_points + order_points
--    WHERE user_id = ?
--
-- 7. 记录积分日志
--    INSERT INTO mac_plog (user_id, plog_type, plog_points) VALUES (?, 1, ?)
--
-- ============================================================
-- 常用查询示例
-- ============================================================
--
-- 查询未支付订单
-- SELECT * FROM mac_order WHERE order_status = 0;
--
-- 查询某用户的所有订单
-- SELECT o.*, u.user_name
-- FROM mac_order o
-- LEFT JOIN mac_user u ON o.user_id = u.user_id
-- WHERE o.user_id = 123;
--
-- 统计今日订单金额
-- SELECT SUM(order_price) as total_amount
-- FROM mac_order
-- WHERE order_status = 1
-- AND order_pay_time >= UNIX_TIMESTAMP(CURDATE());
--
-- 统计各支付方式订单数
-- SELECT order_pay_type, COUNT(*) as total
-- FROM mac_order
-- WHERE order_status = 1
-- GROUP BY order_pay_type;
--
-- ============================================================
