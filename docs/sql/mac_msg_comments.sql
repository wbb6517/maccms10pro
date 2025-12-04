-- ============================================================
-- mac_msg 表字段注释 (Message Table Comments)
-- ============================================================
--
-- 【表说明】
-- 验证码消息记录表
-- 存储发送给用户的短信/邮件验证码记录
-- 用于用户注册、密码找回、账号绑定等场景的验证码验证
--
-- 【使用场景】
-- - 用户注册时发送验证码
-- - 找回密码时发送验证码
-- - 绑定邮箱/手机时发送验证码
--
-- 【关联模型】
-- - application/common/model/Msg.php      : 数据模型
-- - application/common/validate/Msg.php   : 数据验证器
-- - application/common/model/User.php     : 用户模型 (调用方)
--
-- 【业务流程】
-- 1. 用户请求发送验证码
-- 2. 系统生成随机验证码，通过短信/邮件发送
-- 3. 验证码记录保存到 mac_msg 表
-- 4. 用户提交验证码，系统查询验证
-- 5. 验证码有效期通常为5分钟
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_msg` COMMENT '验证码消息记录表';

-- ==================== 主键与关联ID ====================
ALTER TABLE `mac_msg` MODIFY COLUMN `msg_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '消息ID，主键自增';
ALTER TABLE `mac_msg` MODIFY COLUMN `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID，关联mac_user表，0表示未登录用户';

-- ==================== 消息类型与状态 ====================
ALTER TABLE `mac_msg` MODIFY COLUMN `msg_type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '消息类型: 1=账号绑定 2=密码找回 3=用户注册';
ALTER TABLE `mac_msg` MODIFY COLUMN `msg_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '使用状态: 0=未使用 1=已使用';

-- ==================== 消息内容 ====================
ALTER TABLE `mac_msg` MODIFY COLUMN `msg_to` varchar(30) NOT NULL DEFAULT '' COMMENT '接收地址，邮箱或手机号码';
ALTER TABLE `mac_msg` MODIFY COLUMN `msg_code` varchar(10) NOT NULL DEFAULT '' COMMENT '验证码，通常为4-6位数字';
ALTER TABLE `mac_msg` MODIFY COLUMN `msg_content` varchar(255) NOT NULL DEFAULT '' COMMENT '消息内容，完整的短信/邮件正文';

-- ==================== 时间信息 ====================
ALTER TABLE `mac_msg` MODIFY COLUMN `msg_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送时间戳，用于判断验证码是否过期';

-- ============================================================
-- 索引说明
-- ============================================================
-- KEY `msg_code` (`msg_code`)   : 验证码索引，加速验证码查询
-- KEY `msg_time` (`msg_time`)   : 时间索引，加速过期验证码清理
-- KEY `user_id` (`user_id`)     : 用户ID索引，加速用户相关查询

-- ============================================================
-- 常用查询示例
-- ============================================================
--
-- 1. 验证用户输入的验证码
-- SELECT * FROM mac_msg
-- WHERE user_id = ? AND msg_code = ? AND msg_type = ?
--   AND msg_time > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 5 MINUTE));
--
-- 2. 检查是否频繁发送 (防刷)
-- SELECT * FROM mac_msg
-- WHERE user_id = ? AND msg_to = ? AND msg_type = ?
--   AND msg_time > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 MINUTE));
--
-- 3. 清理过期验证码
-- DELETE FROM mac_msg WHERE msg_time < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY));
--
