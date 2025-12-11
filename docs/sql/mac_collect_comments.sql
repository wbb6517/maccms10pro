-- ============================================================
-- mac_collect 表字段注释 (Collection Source Configuration Table)
-- ============================================================
--
-- 【表说明】
-- 采集源配置表，存储外部资源站的API配置信息
-- 用于从第三方资源站API采集视频/文章/演员等数据
-- 支持 XML 和 JSON 两种接口格式
--
-- 【关联表】
-- - mac_vod: 采集的视频数据存储目标表
-- - mac_art: 采集的文章数据存储目标表
-- - mac_actor: 采集的演员数据存储目标表
-- - mac_role: 采集的角色数据存储目标表
-- - mac_website: 采集的网站数据存储目标表
-- - mac_manga: 采集的漫画数据存储目标表
--
-- 【相关文件】
-- - application/admin/controller/Collect.php: 采集管理控制器
-- - application/common/model/Collect.php: 采集业务逻辑模型
-- - application/extra/bind.php: 分类绑定配置文件
--
-- 【使用场景】
-- 1. 添加资源站: 后台 → 采集 → 采集源管理 → 添加
-- 2. 测试接口: 点击"测试"按钮测试API连通性
-- 3. 分类绑定: 将资源站分类ID映射到本站分类ID
-- 4. 执行采集: 选择采集源和分类，执行数据采集
--
-- ============================================================

-- 表注释
ALTER TABLE `mac_collect` COMMENT '采集源配置表';

-- ==================== 基础标识字段 ====================

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '采集源ID (主键，自增)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_name` varchar(30) NOT NULL DEFAULT '' COMMENT '采集源名称 (如: 酷播资源站、天空资源站)';

-- ==================== 接口配置字段 ====================

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_url` varchar(255) NOT NULL DEFAULT '' COMMENT '采集接口地址 (资源站API基础URL，如: https://api.example.com/api.php)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '接口类型 (1=XML格式, 2=JSON格式, 0=自动检测)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_mid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '模块类型 (1=视频vod, 2=文章art, 8=演员actor, 9=角色role, 11=网站website, 12=漫画manga)';

-- ==================== 认证参数字段 ====================

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_appid` varchar(30) NOT NULL DEFAULT '' COMMENT '应用ID (部分资源站需要的认证参数，大多数资源站不需要)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_appkey` varchar(30) NOT NULL DEFAULT '' COMMENT '应用密钥 (部分资源站需要的认证参数，大多数资源站不需要)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_param` varchar(100) NOT NULL DEFAULT '' COMMENT '附加参数 (附加到URL的自定义参数，base64编码存储，如: &custom=1)';

-- ==================== 过滤配置字段 ====================

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_filter` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '地址过滤模式 (0=采集全部播放源, 1=仅采集选中的播放源, 2=仅采集选中的下载源, 3=采集选中的播放源和下载源)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_filter_from` varchar(255) NOT NULL DEFAULT '' COMMENT '过滤的播放源代码 (逗号分隔，如: m3u8,mp4 表示只采集这两个播放源的数据)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_filter_year` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '过滤年份 (逗号分隔，如: 2023,2024 表示只采集这两个年份的数据)';

-- ==================== 操作配置字段 ====================

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_opt` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '数据操作模式 (0=新增+更新, 1=仅新增不更新, 2=仅更新不新增)';

ALTER TABLE `mac_collect` MODIFY COLUMN `collect_sync_pic_opt` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '同步图片选项 (0=跟随全局配置, 1=强制开启, 2=强制关闭)';

-- ============================================================
-- 字段说明补充
-- ============================================================
--
-- 【collect_type 接口类型详解】
-- 1 = XML格式: 传统苹果CMS标准XML接口
--     返回格式: <rss><list><video>...</video></list></rss>
-- 2 = JSON格式: 新版苹果CMS JSON接口
--     返回格式: {"code":1,"list":[...],"page":1}
-- 0 = 自动检测: 先尝试JSON，失败则尝试XML
--
-- 【collect_mid 模块类型详解】
-- 1  = 视频(vod): 采集电影、电视剧、综艺等视频内容
-- 2  = 文章(art): 采集资讯、新闻等文章内容
-- 8  = 演员(actor): 采集演员资料信息
-- 9  = 角色(role): 采集影视剧角色信息
-- 11 = 网站(website): 采集友情链接、网址导航
-- 12 = 漫画(manga): 采集漫画资源
--
-- 【collect_filter 地址过滤模式详解】
-- 0 = 采集全部播放源
--     - 采集接口返回的所有播放地址都入库
-- 1 = 仅采集选中的播放源 (播放地址)
--     - 只采集 collect_filter_from 中指定的播放源
--     - 如: m3u8,mp4 则只采集这两个播放源
-- 2 = 仅采集选中的下载源 (下载地址)
--     - 只采集 collect_filter_from 中指定的下载源
-- 3 = 采集选中的播放源和下载源
--     - 同时过滤播放地址和下载地址
--
-- 【collect_opt 数据操作模式详解】
-- 0 = 新增+更新 (默认)
--     - 数据不存在时: 新增记录
--     - 数据已存在时: 根据更新规则(uprule)更新字段
-- 1 = 仅新增不更新
--     - 数据不存在时: 新增记录
--     - 数据已存在时: 跳过不做任何操作
-- 2 = 仅更新不新增
--     - 数据不存在时: 跳过不做任何操作
--     - 数据已存在时: 根据更新规则(uprule)更新字段
--
-- 【collect_sync_pic_opt 同步图片选项详解】
-- 0 = 跟随全局配置 (默认)
--     - 使用 application/extra/maccms.php 中的全局配置
--     - 配置路径: collect.vod.pic (或 collect.art.pic 等)
-- 1 = 强制开启
--     - 忽略全局配置，强制下载图片到本地
--     - 存储路径: upload/{类型}/年月日/
-- 2 = 强制关闭
--     - 忽略全局配置，强制不下载图片
--     - 直接使用资源站提供的图片URL
--
-- 【collect_param 附加参数使用示例】
-- 某些资源站需要额外的认证或配置参数，可以在此添加
-- 例如: &token=abc123&format=json
-- 存储时会 base64 编码，使用时自动解码并附加到请求URL
--
-- 【collect_filter_from 过滤播放源示例】
-- m3u8           - 只采集 m3u8 播放源
-- m3u8,mp4       - 采集 m3u8 和 mp4 两个播放源
-- qq,iqiyi,youku - 采集腾讯、爱奇艺、优酷三个播放源
-- 注意: 播放源代码必须与资源站API返回的 flag 字段完全匹配
--
-- 【collect_filter_year 过滤年份示例】
-- 2024           - 只采集 2024 年的数据
-- 2023,2024      - 采集 2023 和 2024 年的数据
-- 2020,2021,2022,2023,2024 - 采集 2020-2024 年的数据
-- 留空则不过滤年份
--
-- ============================================================
-- 使用示例
-- ============================================================
--
-- 【示例1: 添加一个视频采集源 (JSON格式)】
-- INSERT INTO `mac_collect` (
--   `collect_name`,
--   `collect_url`,
--   `collect_type`,
--   `collect_mid`,
--   `collect_opt`,
--   `collect_filter`,
--   `collect_filter_from`
-- ) VALUES (
--   '酷播资源站',
--   'https://api.kubo-zy.com/api.php/provide/vod',
--   2,  -- JSON格式
--   1,  -- 视频模块
--   0,  -- 新增+更新
--   1,  -- 只采集选中播放源
--   'm3u8,mp4'  -- 只采集这两个播放源
-- );
--
-- 【示例2: 添加一个文章采集源 (XML格式)】
-- INSERT INTO `mac_collect` (
--   `collect_name`,
--   `collect_url`,
--   `collect_type`,
--   `collect_mid`,
--   `collect_opt`,
--   `collect_filter_year`
-- ) VALUES (
--   '新闻资讯站',
--   'https://api.news.com/xml.php',
--   1,  -- XML格式
--   2,  -- 文章模块
--   1,  -- 仅新增
--   '2024'  -- 只采集2024年的文章
-- );
--
-- ============================================================
-- 维护建议
-- ============================================================
--
-- 1. 定期检查采集源可用性
--    - 使用"测试"功能验证接口连通性
--    - 及时更新失效的采集源地址
--
-- 2. 合理设置过滤规则
--    - collect_filter_from: 只选择播放质量好的源
--    - collect_filter_year: 避免采集过时内容
--
-- 3. 注意采集频率
--    - 不要频繁采集同一资源站
--    - 建议间隔至少1小时
--
-- 4. 分类绑定维护
--    - 在 application/extra/bind.php 中配置
--    - 格式: '{cjflag}_{资源站分类ID}' => 本站分类ID
--
-- ============================================================