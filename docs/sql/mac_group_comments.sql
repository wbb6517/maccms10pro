-- ============================================================
-- mac_group 表字段注释 (Group Table Comments)
-- ============================================================
--
-- 【表说明】
-- 会员组数据表，用于存储网站的会员等级分组信息
-- 每个会员组可以设置不同的观看权限、积分价格等
--
-- 【系统内置组】
-- - ID=1: 游客 (未登录用户)
-- - ID=2: 默认会员 (注册后的默认分组)
-- - ID>=3: 自定义会员组 (如VIP会员)
--
-- 【关联表】
-- - mac_user: 用户表 (user_group_id 关联本表)
-- - mac_type: 分类表 (group_type 字段存储可访问的分类ID)
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_group` COMMENT '会员组数据表 - 存储会员等级分组和权限配置';

-- ==================== 主键字段 ====================
ALTER TABLE `mac_group` MODIFY COLUMN `group_id` smallint(6) NOT NULL AUTO_INCREMENT COMMENT '会员组ID (主键,自增,1和2为系统内置组)';

-- ==================== 基本信息 ====================
ALTER TABLE `mac_group` MODIFY COLUMN `group_name` varchar(30) NOT NULL DEFAULT '' COMMENT '会员组名称 (如:游客、默认会员、VIP会员)';
ALTER TABLE `mac_group` MODIFY COLUMN `group_status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态 (0=禁用,1=启用,内置组不可禁用)';

-- ==================== 权限配置 ====================
ALTER TABLE `mac_group` MODIFY COLUMN `group_type` text NOT NULL COMMENT '可访问分类ID (格式:,1,2,3,,逗号分隔,前后有逗号便于like查询)';
ALTER TABLE `mac_group` MODIFY COLUMN `group_popedom` text NOT NULL COMMENT '权限配置JSON (格式:{"分类ID":{"1":列表,"2":详情,"3":播放,"4":下载,"5":试看}})';

-- ==================== 积分/价格配置 ====================
ALTER TABLE `mac_group` MODIFY COLUMN `group_points_day` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '包天积分/价格 (开通一天所需积分)';
ALTER TABLE `mac_group` MODIFY COLUMN `group_points_week` smallint(6) NOT NULL DEFAULT 0 COMMENT '包周积分/价格 (开通一周所需积分)';
ALTER TABLE `mac_group` MODIFY COLUMN `group_points_month` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '包月积分/价格 (开通一月所需积分)';
ALTER TABLE `mac_group` MODIFY COLUMN `group_points_year` smallint(6) unsigned NOT NULL DEFAULT 0 COMMENT '包年积分/价格 (开通一年所需积分)';
ALTER TABLE `mac_group` MODIFY COLUMN `group_points_free` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '免费标识 (0=收费,1=免费会员组)';

-- ============================================================
-- 索引说明 (仅供参考,不执行)
-- ============================================================
-- PRIMARY KEY (`group_id`)        - 主键索引
-- KEY `group_status` (`group_status`) - 状态索引,用于筛选启用的会员组

-- ============================================================
-- 字段使用场景
-- ============================================================
--
-- 【权限控制流程】
-- 1. 用户访问内容时,根据 user_group_id 获取会员组信息
-- 2. 检查 group_type 是否包含该内容的分类ID
-- 3. 检查 group_popedom 中对应分类的权限配置
-- 4. 根据权限决定是否允许访问/播放/下载
--
-- 【group_popedom JSON结构示例】
-- {
--   "1": {"1": "1", "2": "2", "3": "3"},  // 分类1: 列表+详情+播放
--   "2": {"1": "1", "2": "2"}             // 分类2: 列表+详情
-- }
-- 权限值说明:
-- - 1: 列表权限 (可查看该分类的内容列表)
-- - 2: 详情权限 (可查看内容详情页)
-- - 3: 播放权限 (可播放视频,仅视频分类有效)
-- - 4: 下载权限 (可下载资源,仅视频分类有效)
-- - 5: 试看权限 (允许试看功能,仅视频分类有效)
--
-- 【会员升级流程】
-- 1. 用户选择要升级的会员组
-- 2. 根据 group_points_xxx 计算所需积分
-- 3. 扣除用户积分,更新 user_group_id 和有效期
