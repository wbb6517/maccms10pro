-- ============================================================
-- 自定义采集模块数据表字段注释 (Custom Collection Tables)
-- ============================================================
--
-- 【模块说明】
-- 自定义采集模块用于从外部网站抓取视频/文章数据
-- 支持配置采集规则，从列表页提取详情页URL，再采集详情内容
--
-- 【包含数据表】
-- - mac_cj_node    : 采集节点配置表 (存储采集规则)
-- - mac_cj_content : 采集内容暂存表 (采集后待入库的数据)
-- - mac_cj_history : 采集历史记录表 (URL去重)
--
-- 【采集流程】
-- 1. 创建节点 (cj_node) 配置采集规则
-- 2. 采集网址: 从列表页提取内容URL存入 cj_content (status=1)
-- 3. 采集内容: 访问详情页采集数据存入 cj_content.data (status=2)
-- 4. 内容入库: 将数据写入 mac_vod 或 mac_art (status=3)
-- 5. URL去重: 通过 cj_history 的 MD5 值避免重复采集
--
-- 【相关文件】
-- - application/admin/controller/Cj.php   : 后台采集控制器
-- - application/common/model/Cj.php       : 采集数据模型
-- - application/common/util/Collection.php: 采集工具类
--
-- ============================================================


-- ============================================================
-- mac_cj_node 采集节点配置表
-- ============================================================

ALTER TABLE `mac_cj_node` COMMENT '采集节点配置表 - 存储自定义采集规则';

-- ==================== 基本信息 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `nodeid` smallint unsigned NOT NULL AUTO_INCREMENT COMMENT '节点ID (主键自增)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `name` varchar(20) NOT NULL COMMENT '节点名称';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `lastdate` int unsigned NOT NULL DEFAULT '0' COMMENT '最后采集时间 (Unix时间戳)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `mid` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '模块类型: 1=视频(vod) 2=文章(art)';

-- ==================== 列表源配置 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `sourcecharset` varchar(8) NOT NULL COMMENT '源网页编码: utf-8/gbk/gb2312';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `sourcetype` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '列表源类型: 1=序列化URL 2=多网址 3=单一网址 4=RSS';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `urlpage` text NOT NULL COMMENT '列表URL规则: 类型1用(*)作页码占位符,类型2每行一个URL';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `pagesize_start` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '分页起始页码 (类型1有效)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `pagesize_end` mediumint unsigned NOT NULL DEFAULT '0' COMMENT '分页结束页码 (类型1有效)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `par_num` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '分页步长/增量 (类型1有效)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `page_base` char(255) NOT NULL COMMENT '相对URL基础路径 (用于补全相对路径)';

-- ==================== URL提取规则 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `url_start` char(100) NOT NULL DEFAULT '' COMMENT 'URL区域开始标记 (截取列表HTML)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `url_end` char(100) NOT NULL DEFAULT '' COMMENT 'URL区域结束标记 (截取列表HTML)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `url_contain` char(100) NOT NULL COMMENT 'URL必须包含字符串 (过滤条件)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `url_except` char(100) NOT NULL COMMENT 'URL必须排除字符串 (过滤条件)';

-- ==================== 标题提取规则 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `title_rule` char(100) NOT NULL COMMENT '标题提取规则: 固定值或 开始标记[内容]结束标记';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `title_html_rule` text NOT NULL COMMENT '标题替换规则: 正则[|]替换值,多行多规则';

-- ==================== 分类提取规则 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `type_rule` char(100) NOT NULL COMMENT '分类提取规则: 固定值或 开始标记[内容]结束标记';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `type_html_rule` text NOT NULL COMMENT '分类替换规则: 正则[|]替换值,多行多规则';

-- ==================== 内容提取规则 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `content_rule` char(100) NOT NULL COMMENT '内容提取规则: 固定值或 开始标记[内容]结束标记';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `content_html_rule` text NOT NULL COMMENT '内容替换规则: 正则[|]替换值,多行多规则';

-- ==================== 内容分页配置 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `content_page_start` char(100) NOT NULL COMMENT '分页区域开始标记 (截取分页链接HTML)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `content_page_end` char(100) NOT NULL COMMENT '分页区域结束标记 (截取分页链接HTML)';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `content_page_rule` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '分页模式: 0=不分页 1=全部罗列 2=上下页模式';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `content_page` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '分页合并方式: 0=直接合并 1=用[page]分隔';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `content_nextpage` char(100) NOT NULL COMMENT '下一页链接文字标识 (上下页模式用)';

-- ==================== 附加选项 ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `down_attachment` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '下载图片到本地: 0=否 1=是';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `watermark` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '图片加水印: 0=否 1=是';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `coll_order` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '采集顺序: 0=正序 1=倒序';

-- ==================== 扩展配置 (JSON) ====================
ALTER TABLE `mac_cj_node` MODIFY COLUMN `customize_config` text NOT NULL COMMENT '自定义字段配置(JSON): [{name,en_name,rule,html_rule}]';
ALTER TABLE `mac_cj_node` MODIFY COLUMN `program_config` text NOT NULL COMMENT '字段映射配置(JSON): {map:{数据库字段:采集字段}, funcs:{字段:处理函数}}';


-- ============================================================
-- mac_cj_content 采集内容暂存表
-- ============================================================

ALTER TABLE `mac_cj_content` COMMENT '采集内容暂存表 - 存储采集到的待入库数据';

ALTER TABLE `mac_cj_content` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '内容ID (主键自增)';
ALTER TABLE `mac_cj_content` MODIFY COLUMN `nodeid` int unsigned NOT NULL DEFAULT '0' COMMENT '所属节点ID (关联cj_node.nodeid)';
ALTER TABLE `mac_cj_content` MODIFY COLUMN `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 1=已采集网址 2=已采集内容 3=已入库完成';
ALTER TABLE `mac_cj_content` MODIFY COLUMN `url` char(255) NOT NULL COMMENT '内容详情页URL';
ALTER TABLE `mac_cj_content` MODIFY COLUMN `title` char(100) NOT NULL COMMENT '内容标题 (从列表页提取)';
ALTER TABLE `mac_cj_content` MODIFY COLUMN `data` mediumtext NOT NULL COMMENT '采集数据(JSON): {title,type,content,自定义字段...}';


-- ============================================================
-- mac_cj_history 采集历史记录表
-- ============================================================

ALTER TABLE `mac_cj_history` COMMENT '采集历史记录表 - URL去重,防止重复采集';

ALTER TABLE `mac_cj_history` MODIFY COLUMN `md5` char(32) NOT NULL COMMENT 'URL的MD5哈希值 (主键,用于快速查重)';


-- ============================================================
-- 索引说明
-- ============================================================
--
-- mac_cj_node:
--   PRIMARY KEY (nodeid) - 节点主键
--
-- mac_cj_content:
--   PRIMARY KEY (id)     - 内容主键
--   KEY nodeid (nodeid)  - 按节点查询索引
--   KEY status (status)  - 按状态查询索引
--
-- mac_cj_history:
--   PRIMARY KEY (md5)    - MD5主键 (快速去重)
--   KEY md5 (md5)        - MD5索引 (冗余,可删除)
--
-- ============================================================