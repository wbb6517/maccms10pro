-- ============================================================
-- mac_admin 表字段注释 (Admin Table Comments)
-- ============================================================
--
-- 【表说明】
-- 后台管理员数据表，存储后台管理员账号信息
-- 包括登录凭证、权限配置、登录记录等
--
-- 【系统内置】
-- - ID=1: 超级管理员，拥有全部权限，不可删除
--
-- 【关联说明】
-- - admin_auth 字段存储权限列表，对应 auth.php 中定义的菜单
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_admin` COMMENT '后台管理员数据表 - 存储管理员账号和权限信息';

-- ==================== 主键字段 ====================
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_id` smallint unsigned NOT NULL AUTO_INCREMENT COMMENT '管理员ID (主键,自增,ID=1为超级管理员)';

-- ==================== 账号信息 ====================
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_name` varchar(30) NOT NULL DEFAULT '' COMMENT '管理员账号 (登录用户名,唯一)';
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_pwd` char(32) NOT NULL DEFAULT '' COMMENT '密码 (MD5加密: md5(md5(密码)+random))';
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_random` char(32) NOT NULL DEFAULT '' COMMENT '密码盐值 (32位随机字符串,用于密码加密)';
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态 (0=禁用,1=启用)';

-- ==================== 权限配置 ====================
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_auth` text NOT NULL COMMENT '权限列表 (逗号分隔,如:index/welcome,vod/index,admin/info)';

-- ==================== 登录记录 ====================
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_login_time` int unsigned NOT NULL DEFAULT 0 COMMENT '当前登录时间 (Unix时间戳)';
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_login_ip` int unsigned NOT NULL DEFAULT 0 COMMENT '当前登录IP (ip2long转换后的整数)';
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_login_num` int unsigned NOT NULL DEFAULT 0 COMMENT '累计登录次数';
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_last_login_time` int unsigned NOT NULL DEFAULT 0 COMMENT '上次登录时间 (Unix时间戳)';
ALTER TABLE `mac_admin` MODIFY COLUMN `admin_last_login_ip` int unsigned NOT NULL DEFAULT 0 COMMENT '上次登录IP (ip2long转换后的整数)';

-- ============================================================
-- 索引说明 (仅供参考,不执行)
-- ============================================================
-- PRIMARY KEY (`admin_id`)              - 主键索引
-- KEY `admin_name` (`admin_name`)       - 账号索引,用于登录查询

-- ============================================================
-- 字段使用场景
-- ============================================================
--
-- 【密码加密流程】
-- 1. 用户设置密码: password
-- 2. 生成随机盐值: random = md5(uniqid())
-- 3. 加密存储: admin_pwd = md5(md5(password) + random)
-- 4. 登录验证: md5(md5(输入密码) + admin_random) == admin_pwd
--
-- 【权限验证流程】
-- 1. 用户访问后台页面
-- 2. 获取当前 controller/action
-- 3. 检查 admin_auth 是否包含该权限
-- 4. 不包含则跳转无权限页面
--
-- 【登录记录更新】
-- 1. 登录成功时:
--    - admin_last_login_time = admin_login_time
--    - admin_last_login_ip = admin_login_ip
--    - admin_login_time = 当前时间
--    - admin_login_ip = 当前IP
--    - admin_login_num = admin_login_num + 1
--
-- 【IP存储说明】
-- IP使用 ip2long() 转为整数存储，节省空间
-- 读取时使用 long2ip() 还原为点分十进制格式

