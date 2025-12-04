-- ============================================================
-- mac_user 表字段注释 (User Table Comments)
-- ============================================================
--
-- 【表说明】
-- 前台会员数据表，存储所有注册会员的账号信息
-- 包括登录凭证、个人信息、积分、会员等级、三级分销等
--
-- 【会员组说明】
-- - group_id=1: 游客（未登录用户，无实际记录）
-- - group_id=2: 默认会员（注册后的默认分组）
-- - group_id>=3: VIP会员组（有有效期限制）
--
-- 【关联表】
-- - mac_group: 会员组表（group_id关联）
-- - mac_plog: 积分日志表（user_id关联）
-- - mac_visit: 访问日志表（user_id关联）
-- - mac_msg: 验证码消息表（user_id关联）
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_user` COMMENT '前台会员数据表 - 存储注册会员账号和个人信息';

-- ==================== 主键字段 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '会员ID (主键,自增)';

-- ==================== 账号信息 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_name` varchar(30) NOT NULL DEFAULT '' COMMENT '用户名 (登录账号,唯一,仅允许字母数字)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_pwd` varchar(32) NOT NULL DEFAULT '' COMMENT '密码 (MD5加密存储)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_random` varchar(32) NOT NULL DEFAULT '' COMMENT '登录随机数 (用于生成登录校验码,每次登录更新)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态 (0=禁用,1=启用)';

-- ==================== 个人信息 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_nick_name` varchar(30) NOT NULL DEFAULT '' COMMENT '昵称 (显示名称)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_qq` varchar(16) NOT NULL DEFAULT '' COMMENT 'QQ号码';
ALTER TABLE `mac_user` MODIFY COLUMN `user_email` varchar(30) NOT NULL DEFAULT '' COMMENT '邮箱 (可用于登录和找回密码)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_phone` varchar(16) NOT NULL DEFAULT '' COMMENT '手机号 (可用于登录和找回密码)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_portrait` varchar(100) NOT NULL DEFAULT '' COMMENT '头像URL (原图)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_portrait_thumb` varchar(100) NOT NULL DEFAULT '' COMMENT '头像URL (缩略图)';

-- ==================== 密保信息 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_question` varchar(255) NOT NULL DEFAULT '' COMMENT '密保问题';
ALTER TABLE `mac_user` MODIFY COLUMN `user_answer` varchar(255) NOT NULL DEFAULT '' COMMENT '密保答案';

-- ==================== 第三方登录 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_openid_qq` varchar(40) NOT NULL DEFAULT '' COMMENT 'QQ登录OpenID';
ALTER TABLE `mac_user` MODIFY COLUMN `user_openid_weixin` varchar(40) NOT NULL DEFAULT '' COMMENT '微信登录OpenID';

-- ==================== 会员等级 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `group_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '会员组ID (支持多个,逗号分隔,如:2,3)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_end_time` int unsigned NOT NULL DEFAULT 0 COMMENT 'VIP到期时间 (Unix时间戳,到期后降级为默认会员)';

-- ==================== 积分系统 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_points` int unsigned NOT NULL DEFAULT 0 COMMENT '可用积分 (当前余额)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_points_froze` int unsigned NOT NULL DEFAULT 0 COMMENT '冻结积分 (预留字段)';

-- ==================== 注册信息 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_reg_time` int unsigned NOT NULL DEFAULT 0 COMMENT '注册时间 (Unix时间戳)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_reg_ip` int unsigned NOT NULL DEFAULT 0 COMMENT '注册IP (ip2long转换)';

-- ==================== 登录记录 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_login_time` int unsigned NOT NULL DEFAULT 0 COMMENT '当前登录时间 (Unix时间戳)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_login_ip` int unsigned NOT NULL DEFAULT 0 COMMENT '当前登录IP (ip2long转换)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_last_login_time` int unsigned NOT NULL DEFAULT 0 COMMENT '上次登录时间 (Unix时间戳)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_last_login_ip` int unsigned NOT NULL DEFAULT 0 COMMENT '上次登录IP (ip2long转换)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_login_num` smallint unsigned NOT NULL DEFAULT 0 COMMENT '累计登录次数';

-- ==================== 三级分销 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_pid` int unsigned NOT NULL DEFAULT 0 COMMENT '一级推荐人ID (直接邀请人)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_pid_2` int unsigned NOT NULL DEFAULT 0 COMMENT '二级推荐人ID (邀请人的邀请人)';
ALTER TABLE `mac_user` MODIFY COLUMN `user_pid_3` int unsigned NOT NULL DEFAULT 0 COMMENT '三级推荐人ID (更上一级)';

-- ==================== 扩展字段 ====================
ALTER TABLE `mac_user` MODIFY COLUMN `user_extend` smallint unsigned NOT NULL DEFAULT 0 COMMENT '扩展字段 (预留)';

-- ============================================================
-- 索引说明 (仅供参考,不执行)
-- ============================================================
-- PRIMARY KEY (`user_id`)              - 主键索引
-- KEY `user_name` (`user_name`)        - 用户名索引,用于登录查询
-- KEY `group_id` (`group_id`)          - 会员组索引,用于分组筛选
-- KEY `user_reg_time` (`user_reg_time`)- 注册时间索引,用于排序

-- ============================================================
-- 字段使用场景
-- ============================================================
--
-- 【密码加密】
-- 注册/修改密码: user_pwd = md5(原始密码)
-- 登录验证: md5(输入密码) == user_pwd
--
-- 【登录校验流程】
-- 1. 登录成功时生成随机数: user_random = md5(rand())
-- 2. 生成校验码存入Cookie: user_check = md5(random + '-' + name + '-' + id + '-')
-- 3. 验证登录时重新计算校验码并比对
--
-- 【VIP过期处理】
-- 1. 每次访问检查 user_end_time < time()
-- 2. 如果过期,将 group_id 重置为 2（默认会员）
-- 3. 批量处理在 expire() 方法中执行
--
-- 【三级分销关系】
-- 用户A邀请用户B，用户B邀请用户C：
-- - 用户B的 user_pid = A
-- - 用户C的 user_pid = B, user_pid_2 = A
-- 当用户C消费积分时，B和A按比例获得分销奖励
--
-- 【IP存储说明】
-- IP使用 ip2long() 转为整数存储，节省空间
-- 读取时使用 long2ip() 还原为点分十进制格式

