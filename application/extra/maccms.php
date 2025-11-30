<?php
/**
 * 苹果CMS主配置文件 - 由后台各配置页面自动生成
 * 配置读取: config('maccms')
 */
return array (
  // ==================== 数据库配置 (安装向导生成) ====================
  'db' =>
  array (
    'type' => 'mysql', // 数据库类型
    'path' => '', // 数据库路径
    'server' => '127.0.0.1', // 服务器地址
    'port' => '3306', // 端口
    'name' => 'maccms10', // 数据库名
    'user' => 'root', // 用户名
    'pass' => 'root', // 密码
    'tablepre' => 'mac_', // 表前缀
    'backup_path' => './application/data/backup/database/', // 备份路径
    'part_size' => 20971520, // 分卷大小
    'compress' => 1, // 是否压缩
    'compress_level' => 4, // 压缩级别
  ),
  // ==================== 网站参数配置 Tab1:基本设置 (system/config) ====================
  'site' =>
  array (
    'site_name' => '免费短视频分享大全 - 大中国', // Tab1 网站名称
    'site_url' => 'www.test.cn', // Tab1 网站域名
    'site_wapurl' => 'wap.test.cn', // Tab1 手机域名
    'site_keywords' => '短视频,搞笑视频,视频分享,免费视频,在线视频,预告片', // Tab1 SEO关键词
    'site_description' => '提供最新最快的视频分享数据', // Tab1 SEO描述
    'site_icp' => 'icp123', // Tab1 备案号
    'site_qq' => '123456', // Tab1 联系QQ
    'site_email' => '123456@test.cn', // Tab1 联系邮箱
    'install_dir' => '/', // Tab1 安装目录
    'site_logo' => 'static/images/logo.jpg', // Tab1 PC端Logo
    'site_waplogo' => 'static/images/logo.jpg', // Tab1 移动端Logo
    'template_dir' => 'default', // Tab1 PC端模板
    'html_dir' => 'html', // Tab1 PC端静态目录
    'mob_status' => '0', // Tab1 移动端状态 (0关闭/1多入口/2自适应)
    'mob_template_dir' => 'default', // Tab1 移动端模板
    'mob_html_dir' => 'html', // Tab1 移动端静态目录
    'site_tj' => '统计代码', // Tab1 统计代码
    'site_status' => '1', // Tab1 站点状态 (0关闭/1开启)
    'site_close_tip' => '站点暂时关闭，请稍后访问', // Tab1 关闭提示
    'ads_dir' => 'ads', // Tab1 广告目录(自动检测模板info.ini)
    'mob_ads_dir' => 'ads', // Tab1 移动端广告目录
  ),
  // ==================== 网站参数配置 Tab2:性能优化 + Tab3:预留参数 + Tab4:后台设置 (system/config) ====================
  'app' =>
  array (
    // ----- Tab2:性能优化 -----
    'pathinfo_depr' => '/', // Tab2 URL分隔符
    'suffix' => 'html', // Tab2 URL后缀
    'popedom_filter' => '0', // Tab2 权限过滤
    'cache_type' => 'file', // Tab2 缓存类型 (file/memcache/redis/memcached)
    'cache_host' => '127.0.0.1', // Tab2 缓存服务器
    'cache_port' => '6379', // Tab2 缓存端口
    'cache_username' => '', // Tab2 缓存用户名
    'cache_password' => '', // Tab2 缓存密码
    'cache_db' => '0', // Tab2 Redis数据库索引
    'cache_flag' => 'a6bcf9aa58', // Tab2 静态资源版本标识
    'cache_core' => '0', // Tab2 核心缓存开关
    'cache_time' => '3600', // Tab2 缓存时间(秒)
    'cache_page' => '0', // Tab2 整页缓存开关
    'cache_time_page' => '3600', // Tab2 整页缓存时间
    'compress' => '0', // Tab2 HTML压缩输出
    'search' => '1', // Tab2 搜索功能开关
    'search_timespan' => '3', // Tab2 搜索间隔(秒)
    'search_vod_rule' => 'vod_en|vod_sub', // Tab2 视频搜索字段
    'search_art_rule' => 'art_en|art_sub', // Tab2 文章搜索字段
    'copyright_status' => '1', // Tab2 版权限制处理
    'copyright_notice' => '该视频由于版权限制，暂不提供播放。', // Tab2 版权提示
    'browser_junmp' => '0', // Tab2 浏览器内核跳转
    'page_404' => '404', // Tab2 404页面模板
    // ----- Tab3:预留参数 -----
    'player_sort' => '1', // Tab3 播放器排序 (0添加顺序/1全局排序)
    'encrypt' => '0', // Tab3 播放地址加密 (0不加密/1escape/2base64)
    'search_hot' => '变形金刚,火影忍者,复仇者联盟,战狼,红海行动', // Tab3 热门搜索词
    'art_extend_class' => '段子手,私房话,八卦精,爱生活,汽车迷,科技咖,美食家,辣妈帮', // Tab3 文章扩展分类
    'vod_extend_class' => '爱情,动作,喜剧,战争,科幻,剧情,武侠,冒险,枪战,恐怖,微电影,其它', // Tab3 视频扩展分类
    'vod_extend_state' => '正片,预告片,花絮', // Tab3 视频状态扩展
    'vod_extend_version' => '高清版,剧场版,抢先版,OVA,TV,影院版', // Tab3 视频版本扩展
    'vod_extend_area' => '大陆,香港,台湾,美国,韩国,日本,泰国,新加坡,马来西亚,印度,英国,法国,加拿大,西班牙,俄罗斯,其它', // Tab3 视频地区扩展
    'vod_extend_lang' => '国语,英语,粤语,闽南语,韩语,日语,法语,德语,其它', // Tab3 视频语言扩展
    'vod_extend_year' => '2021,2020,2019,2018,2017,2016,2015,2014,2013,2012,2011,2010,2009,2008,2007,2006,2005,2004,2003,2002,2001,2000', // Tab3 视频年份扩展
    'vod_extend_weekday' => '一,二,三,四,五,六,日', // Tab3 更新星期扩展
    'actor_extend_area' => '大陆,香港,台湾,美国,韩国,日本,泰国,新加坡,马来西亚,印度,英国,法国,加拿大,西班牙,俄罗斯,其它', // Tab3 演员地区扩展
    'filter_words' => 'www,http,com,net', // Tab3 全局过滤词
    'extra_var' => '', // Tab3 自定义变量 (格式:key$$$value)
    // ----- Tab4:后台设置 -----
    'collect_timespan' => '3', // Tab4 采集间隔(秒)
    'pagesize' => '20', // Tab4 后台分页数
    'makesize' => '30', // Tab4 生成每批数量
    'admin_login_verify' => '1', // Tab4 后台登录验证码
    'editor' => 'Ueditor', // Tab4 编辑器类型
    'lang' => 'zh-cn', // Tab4 后台语言
    'input_type' => '1', // Tab2 输入类型(隐藏项)
  ),
  // ==================== 会员参数配置 (system/configuser) ====================
  'user' =>
  array (
    'status' => '1', // 会员系统开关
    'reg_open' => '1', // 注册开关
    'reg_status' => '1', // 注册审核
    'reg_phone_sms' => '0', // 手机短信验证
    'reg_email_sms' => '0', // 邮箱验证
    'reg_verify' => '0', // 注册验证码
    'login_verify' => '0', // 登录验证码
    'reg_points' => '10', // 注册赠送积分
    'reg_num' => '1', // 每日注册限制
    'invite_reg_points' => '10', // 邀请注册积分
    'invite_visit_points' => '1', // 邀请访问积分
    'invite_visit_num' => '1', // 邀请访问次数限制
    'reward_status' => '1', // 打赏开关
    'reward_ratio' => '10', // 打赏比例1
    'reward_ratio_2' => '30', // 打赏比例2
    'reward_ratio_3' => '50', // 打赏比例3
    'cash_status' => '1', // 提现开关
    'cash_ratio' => '100', // 提现比例
    'cash_min' => '1', // 最低提现
    'trysee' => '0', // 试看时长
    'vod_points_type' => '1', // 视频积分类型
    'art_points_type' => '1', // 文章积分类型
    'portrait_status' => '1', // 头像上传
    'portrait_size' => '100x100', // 头像尺寸
    'filter_words' => 'admin,cao,sex,xxx', // 用户名过滤词
  ),
  // ==================== 评论留言配置 (system/configcomment) ====================
  'gbook' =>
  array (
    'status' => '1', // 留言板开关
    'audit' => '0', // 留言审核
    'login' => '0', // 需要登录
    'verify' => '1', // 验证码
    'pagesize' => '20', // 分页数量
    'timespan' => '3', // 发布间隔
  ),
  // ==================== 评论留言配置 (system/configcomment) ====================
  'comment' =>
  array (
    'status' => '1', // 评论开关
    'audit' => '0', // 评论审核
    'login' => '0', // 需要登录
    'verify' => '1', // 验证码
    'pagesize' => '20', // 分页数量
    'timespan' => '3', // 发布间隔
  ),
  // ==================== 附件参数配置 (system/configupload) ====================
  'upload' =>
  array (
    'img_key' => 'baidu|douban|tvmao', // 图片防盗链关键词
    'img_api' => '/img.php?url=', // 图片代理API
    'thumb' => '0', // 缩略图开关
    'thumb_size' => '300x300', // 缩略图尺寸
    'thumb_type' => '1', // 缩略图类型
    'watermark' => '0', // 水印开关
    'watermark_location' => '7', // 水印位置
    'watermark_content' => 'test', // 水印内容
    'watermark_size' => '40', // 水印大小
    'watermark_color' => '#FF0000', // 水印颜色
    'protocol' => 'http', // 协议类型
    'mode' => 'local', // 存储模式 (local/ftp/qiniu/upyun等)
    'remoteurl' => 'http://img.test.com/', // 远程URL
    'api' =>
    array (
      'ftp' =>
      array (
        'host' => '', // FTP主机
        'port' => '21', // FTP端口
        'user' => 'test', // FTP用户
        'pwd' => 'test', // FTP密码
        'path' => '/', // FTP路径
        'url' => '', // FTP访问URL
      ),
      'qiniu' =>
      array (
        'bucket' => '', // 七牛空间名
        'accesskey' => '', // 七牛AK
        'secretkey' => '', // 七牛SK
        'url' => '', // 七牛域名
      ),
      'uomg' =>
      array (
        'openid' => '', // UOMG OpenID
        'key' => '', // UOMG Key
        'type' => 'sogou', // UOMG类型
      ),
      'upyun' =>
      array (
        'bucket' => '', // 又拍云空间
        'username' => '', // 又拍云用户
        'pwd' => '', // 又拍云密码
        'url' => '', // 又拍云域名
      ),
      'weibo' =>
      array (
        'user' => '', // 微博用户
        'pwd' => '', // 微博密码
        'size' => 'large', // 图片尺寸
        'cookie' => '', // 微博Cookie
        'time' => '1546239694', // 更新时间
      ),
    ),
  ),
  // ==================== 站外入库配置 (system/configinterface) ====================
  'interface' =>
  array (
    'status' => 0, // 入库开关
    'pass' => '5RI8CLIV5YD46Q5G', // 通信密钥(至少16位)
    'vodtype' => '动作片=动作', // 视频分类映射
    'arttype' => '头条=头条', // 文章分类映射
    'actortype' => '', // 演员分类映射
    'websitetype' => '', // 网站分类映射
  ),
  // ==================== 在线支付配置 (system/configpay) ====================
  'pay' =>
  array (
    'min' => '10', // 最低充值金额
    'scale' => '1', // 积分兑换比例
    'card' =>
    array (
      'url' => '', // 卡密接口
    ),
    'alipay' =>
    array (
      'account' => '111', // 支付宝账号
      'appid' => '', // 支付宝AppID
      'appkey' => '', // 支付宝AppKey
    ),
    'codepay' =>
    array (
      'appid' => '40625', // CodePay AppID
      'appkey' => '', // CodePay AppKey
      'type' => '1,2', // 支付类型
      'act' => '0', // 动作
    ),
    'weixin' =>
    array (
      'appid' => '222', // 微信AppID
      'mchid' => '', // 微信商户号
      'appkey' => '', // 微信AppKey
    ),
    'zhapay' =>
    array (
      'appid' => '18039', // ZhaPay AppID
      'appkey' => '', // ZhaPay AppKey
      'type' => '1,2', // 支付类型
      'act' => '2', // 动作
    ),
  ),
  // ==================== 采集参数配置 (system/configcollect) ====================
  'collect' =>
  array (
    'vod' =>
    array (
      'status' => '1', // 视频采集开关
      'hits_start' => '1', // 点击量起始
      'hits_end' => '1000', // 点击量结束
      'updown_start' => '1', // 顶踩起始
      'updown_end' => '1000', // 顶踩结束
      'score' => '1', // 评分
      'pic' => '0', // 图片采集
      'tag' => '0', // 标签采集
      'class_filter' => '1', // 分类过滤
      'psename' => '1', // 伪原创名称
      'psernd' => '0', // 伪原创随机
      'psesyn' => '0', // 伪原创同义词
      'urlrole' => '0', // URL规则
      'inrule' => ',f,g', // 入库规则
      'uprule' => ',a', // 更新规则
      'filter' => '色戒,色即是空', // 过滤关键词
      'namewords' => '第1季=第一季#第2季=第二季#第3季=第三季#第4季=第四季', // 名称替换
      'thesaurus' => ' =', // 同义词库
      'words' => 'aaa#bbb#ccc#ddd#eee', // 违禁词
    ),
    'art' =>
    array (
      'status' => '1', // 文章采集开关
      'hits_start' => '1', // 点击量起始
      'hits_end' => '1000', // 点击量结束
      'updown_start' => '1', // 顶踩起始
      'updown_end' => '1000', // 顶踩结束
      'score' => '1', // 评分
      'pic' => '0', // 图片采集
      'tag' => '0', // 标签采集
      'psernd' => '0', // 伪原创随机
      'psesyn' => '0', // 伪原创同义词
      'inrule' => ',b', // 入库规则
      'uprule' => ',a,d', // 更新规则
      'filter' => '无奈的人', // 过滤关键词
      'thesaurus' => '', // 同义词库
      'words' => '', // 违禁词
    ),
    'actor' =>
    array (
      'status' => '0', // 演员采集开关
      'hits_start' => '1',
      'hits_end' => '999',
      'updown_start' => '1',
      'updown_end' => '999',
      'score' => '0',
      'pic' => '0',
      'psernd' => '0',
      'psesyn' => '0',
      'uprule' => ',a,b,c',
      'filter' => '无奈的人',
      'thesaurus' => '',
      'words' => '',
      'inrule' => ',a',
    ),
    'role' =>
    array (
      'status' => '0', // 角色采集开关
      'hits_start' => '1',
      'hits_end' => '999',
      'updown_start' => '1',
      'updown_end' => '999',
      'score' => '0',
      'pic' => '0',
      'psernd' => '0',
      'psesyn' => '0',
      'uprule' => ',a,b,c',
      'filter' => '',
      'thesaurus' => '',
      'words' => '',
      'inrule' => ',a',
    ),
    'website' =>
    array (
      'status' => '0', // 网站采集开关
      'hits_start' => '',
      'hits_end' => '',
      'updown_start' => '',
      'updown_end' => '',
      'score' => '0',
      'pic' => '0',
      'psernd' => '0',
      'psesyn' => '0',
      'filter' => '',
      'thesaurus' => '',
      'words' => '',
      'inrule' => ',a',
      'uprule' => ',',
    ),
    'comment' =>
    array (
      'status' => '0', // 评论采集开关
      'updown_start' => '1',
      'updown_end' => '100',
      'psernd' => '0',
      'psesyn' => '0',
      'inrule' => ',b',
      'filter' => '',
      'thesaurus' => '',
      'words' => '',
      'uprule' => ',',
    ),
    'manga' =>
    array (
      'status' => '0', // 漫画采集开关
      'hits_start' => '',
      'hits_end' => '',
      'updown_start' => '',
      'updown_end' => '',
      'score' => '0',
      'pic' => '0',
      'psernd' => '0',
      'psesyn' => '0',
      'filter' => '',
      'thesaurus' => '',
      'words' => '',
      'inrule' => ',a',
      'uprule' => ',a',
    ),
  ),
  // ==================== 开放API配置 (system/configapi) ====================
  'api' =>
  array (
    'vod' =>
    array (
      'status' => 0, // 视频API开关
      'charge' => '0', // 收费模式
      'pagesize' => '20', // 分页数量
      'imgurl' => 'http://img.test.com/', // 图片域名
      'typefilter' => '', // 分类过滤
      'datafilter' => ' vod_status=1', // 数据过滤
      'cachetime' => '', // 缓存时间
      'from' => '', // 来源
      'auth' => 'test.com#163.com', // 授权域名
    ),
    'art' =>
    array (
      'status' => 0, // 文章API开关
      'charge' => '0',
      'pagesize' => '20',
      'imgurl' => '',
      'typefilter' => '',
      'datafilter' => 'art_status=1',
      'cachetime' => '',
      'auth' => '',
    ),
    'actor' =>
    array (
      'status' => '0', // 演员API开关
      'charge' => '0',
      'pagesize' => '20',
      'imgurl' => '',
      'typefilter' => '',
      'datafilter' => 'actor_status=1',
      'cachetime' => '',
      'auth' => '',
    ),
    'role' =>
    array (
      'status' => '0', // 角色API开关
      'charge' => '0',
      'pagesize' => '20',
      'imgurl' => '',
      'typefilter' => '',
      'datafilter' => 'role_status=1',
      'cachetime' => '',
      'auth' => '',
    ),
    'website' =>
    array (
      'status' => '0', // 网站API开关
      'charge' => '0',
      'pagesize' => '20',
      'imgurl' => '',
      'typefilter' => '',
      'datafilter' => 'website_status=1',
      'cachetime' => '',
      'auth' => '',
    ),
    'publicapi' =>
    array (
      'status' => '0', // 公共API开关
      'charge' => '0',
      'pagesize' => '20',
      'imgurl' => '',
      'typefilter' => '',
      'datafilter' => '',
      'cachetime' => '',
      'auth' => '',
    ),
  ),
  // ==================== 整合登录配置 (system/configconnect) ====================
  'connect' =>
  array (
    'qq' =>
    array (
      'status' => '0', // QQ登录开关
      'key' => 'aa', // QQ AppKey
      'secret' => 'bb', // QQ AppSecret
    ),
    'weixin' =>
    array (
      'status' => '0', // 微信登录开关
      'key' => 'cc', // 微信AppKey
      'secret' => 'dd', // 微信AppSecret
    ),
  ),
  // ==================== 微信对接配置 (system/configweixin) ====================
  'weixin' =>
  array (
    'status' => '1', // 微信对接开关
    'duijie' => 'wx.test.com', // 对接地址
    'sousuo' => 'wx.test.com', // 搜索地址
    'token' => 'qweqwe', // Token令牌
    'guanzhu' => '欢迎关注', // 关注回复
    'wuziyuan' => '没找到资源，请更换关键词或等待更新', // 无资源回复
    'wuziyuanlink' => 'demo.test.com', // 无资源链接
    'bofang' => '0', // 播放模式
    'msgtype' => '0', // 消息类型
    'gjc1' => '关键词1', // 关键词1
    'gjcm1' => '长城', // 关键词1名称
    'gjci1' => 'http://img.aolusb.com/im/201610/2016101222371965996.jpg', // 关键词1图片
    'gjcl1' => 'http://www.loldytt.com/Dongzuodianying/CC/', // 关键词1链接
    'gjc2' => '关键词2',
    'gjcm2' => '生化危机6',
    'gjci2' => 'http://img.aolusb.com/im/201702/20172711214866248.jpg',
    'gjcl2' => 'http://www.loldytt.com/Kehuandianying/SHWJ6ZZ/',
    'gjc3' => '关键词3',
    'gjcm3' => '湄公河行动',
    'gjci3' => 'http://img.aolusb.com/im/201608/201681719561972362.jpg',
    'gjcl3' => 'http://www.loldytt.com/Dongzuodianying/GHXD/',
    'gjc4' => '关键词4',
    'gjcm4' => '王牌逗王牌',
    'gjci4' => 'http://img.aolusb.com/im/201601/201612723554344882.jpg',
    'gjcl4' => 'http://www.loldytt.com/Xijudianying/WPDWP/',
  ),
  // ==================== URL地址配置 (system/configurl) ====================
  'view' =>
  array (
    'index' => '0', // 首页静态化
    'map' => '0', // 地图静态化
    'search' => '0', // 搜索静态化
    'rss' => '0', // RSS静态化
    'label' => '0', // 标签静态化
    'vod_type' => '0', // 视频分类静态化
    'vod_show' => '0', // 视频筛选静态化
    'art_type' => '0', // 文章分类静态化
    'art_show' => '0', // 文章筛选静态化
    'topic_index' => '0', // 专题首页静态化
    'topic_detail' => '0', // 专题详情静态化
    'vod_detail' => '0', // 视频详情静态化
    'vod_play' => '0', // 视频播放静态化
    'vod_down' => '0', // 视频下载静态化
    'art_detail' => '0', // 文章详情静态化
  ),
  // ==================== URL地址配置 (system/configurl) ====================
  'path' =>
  array (
    'topic_index' => 'topic/index', // 专题首页路径
    'topic_detail' => 'topic/{id}/index', // 专题详情路径
    'vod_type' => 'vodtypehtml/{id}/index', // 视频分类路径
    'vod_detail' => 'vodhtml/{id}/index', // 视频详情路径
    'vod_play' => 'vodplayhtml/{id}/index', // 视频播放路径
    'vod_down' => 'voddownhtml/{id}/index', // 视频下载路径
    'art_type' => 'arttypehtml/{id}/index', // 文章分类路径
    'art_detail' => 'arthtml/{id}/index', // 文章详情路径
    'page_sp' => '_', // 分页分隔符
    'suffix' => 'html', // 后缀名
  ),
  // ==================== URL地址配置 (system/configurl) ====================
  'rewrite' =>
  array (
    'suffix_hide' => '0', // 隐藏后缀
    'route_status' => '0', // 路由状态
    'status' => '0', // URL重写状态
    'encode_key' => 'abcdefg', // 加密密钥
    'encode_len' => '6', // 加密长度
    'vod_id' => '0', // 视频ID加密
    'art_id' => '0', // 文章ID加密
    'type_id' => '0', // 分类ID加密
    'topic_id' => '0', // 专题ID加密
    'actor_id' => '0', // 演员ID加密
    'role_id' => '0', // 角色ID加密
    'website_id' => '0', // 网站ID加密
    'route' => 'map   => map/index
rss/index   => rss/index
rss/baidu => rss/baidu
rss/google => rss/google
rss/sogou => rss/sogou
rss/so => rss/so
rss/bing => rss/bing
rss/sm => rss/sm

index-<page?>   => index/index

gbook-<page?>   => gbook/index
gbook$   => gbook/index

topic-<page?>   => topic/index
topic$  => topic/index
topicdetail-<id>   => topic/detail

actor-<page?>   => actor/index
actor$ => actor/index
actordetail-<id>   => actor/detail
actorshow/<area?>-<blood?>-<by?>-<letter?>-<level?>-<order?>-<page?>-<sex?>-<starsign?>   => actor/show

role-<page?>   => role/index
role$ => role/index
roledetail-<id>   => role/detail
roleshow/<by?>-<letter?>-<level?>-<order?>-<page?>-<rid?>   => role/show


vodtype/<id>-<page?>   => vod/type
vodtype/<id>   => vod/type
voddetail/<id>   => vod/detail
vodrss-<id>   => vod/rss
vodplay/<id>-<sid>-<nid>   => vod/play
voddown/<id>-<sid>-<nid>   => vod/down
vodshow/<id>-<area?>-<by?>-<class?>-<lang?>-<letter?>-<level?>-<order?>-<page?>-<state?>-<tag?>-<year?>   => vod/show
vodsearch/<wd?>-<actor?>-<area?>-<by?>-<class?>-<director?>-<lang?>-<letter?>-<level?>-<order?>-<page?>-<state?>-<tag?>-<year?>   => vod/search
vodplot/<id>-<page?>   => vod/plot
vodplot/<id>   => vod/plot


arttype/<id>-<page?>   => art/type
arttype/<id>   => art/type
artshow-<id>   => art/show
artdetail-<id>-<page?>   => art/detail
artdetail-<id>   => art/detail
artrss-<id>-<page>   => art/rss
artshow/<id>-<by?>-<class?>-<level?>-<letter?>-<order?>-<page?>-<tag?>   => art/show
artsearch/<wd?>-<by?>-<class?>-<level?>-<letter?>-<order?>-<page?>-<tag?>   => art/search

label-<file> => label/index

plotdetail/<id>-<page?>   => plot/plot
plotdetail/<id>   => plot/detail', // 路由规则
  ),
  // ==================== 邮件发送配置 (system/configemail) ====================
  'email' =>
  array (
    'type' => 'Phpmailer', // 邮件类型
    'time' => '5', // 验证码有效期(分钟)
    'nick' => 'test', // 发件人昵称
    'test' => 'test@qq.com', // 测试邮箱
    'tpl' =>
    array (
      'test_title' => '【{$maccms.site_name}】测试邮件标题', // 测试邮件标题
      'test_body' => '【{$maccms.site_name}】当您看到这封邮件说明邮件配置正确了！感谢支持开源程序！', // 测试邮件内容
      'user_reg_title' => '【{$maccms.site_name}】的会员您好，请认真阅读邮件正文并按要求操作完成注册', // 注册邮件标题
      'user_reg_body' => '【{$maccms.site_name}】的会员您好，注册验证码为：{$code}，请在{$time}分钟内完成验证。', // 注册邮件内容
      'user_bind_title' => '【{$maccms.site_name}】的会员您好，请认真阅读邮件正文并按要求操作完成绑定', // 绑定邮件标题
      'user_bind_body' => '【{$maccms.site_name}】的会员您好，绑定验证码为：{$code}，请在{$time}分钟内完成验证。', // 绑定邮件内容
      'user_findpass_title' => '【{$maccms.site_name}】的会员您好，请认真阅读邮件正文并按要求操作完成找回', // 找回密码标题
      'user_findpass_body' => '【{$maccms.site_name}】的会员您好，找回验证码为：{$code}，请在{$time}分钟内完成验证。', // 找回密码内容
    ),
    'phpmailer' =>
    array (
      'host' => 'smtp.qq.com', // SMTP服务器
      'port' => '587', // SMTP端口
      'secure' => 'tsl', // 加密方式
      'username' => 'test@qq.com', // SMTP用户名
      'password' => 'test', // SMTP密码
    ),
  ),
  // ==================== 播放器参数配置 (system/configplay) ====================
  'play' =>
  array (
    'width' => '100%', // PC端宽度
    'height' => '100%', // PC端高度
    'widthmob' => '100%', // 移动端宽度
    'heightmob' => '100%', // 移动端高度
    'widthpop' => '0', // 弹窗宽度
    'heightpop' => '600', // 弹窗高度
    'second' => '5', // 广告秒数
    'prestrain' => '//union.maccms.la/html/prestrain.html', // 前贴片广告
    'buffer' => '//union.maccms.la/html/buffer.html', // 缓冲广告
    'parse' => '', // 解析接口
    'autofull' => '0', // 自动全屏
    'showtop' => '1', // 显示顶部
    'showlist' => '1', // 显示选集
    'flag' => '0', // 播放器标识
    'colors' => '000000,F6F6F6,F6F6F6,333333,666666,FFFFF,FF0000,2c2c2c,ffffff,a3a3a3,2c2c2c,adadad,adadad,48486c,fcfcfc', // 颜色配置
  ),
  // ==================== 短信发送配置 (system/configsms) ====================
  'sms' =>
  array (
    'type' => '', // 短信类型
    'sign' => '我的网站', // 短信签名
    'tpl_code_reg' => 'SMS_144850895', // 注册模板ID
    'tpl_code_bind' => 'SMS_144940283', // 绑定模板ID
    'tpl_code_findpass' => 'SMS_144851023', // 找回密码模板ID
    'aliyun' =>
    array (
      'appid' => '', // 阿里云AppID
      'appkey' => '', // 阿里云AppKey
    ),
    'qcloud' =>
    array (
      'appid' => '', // 腾讯云AppID
      'appkey' => '', // 腾讯云AppKey
    ),
  ),
  // ==================== 自定义变量 (网站参数配置 Tab3:预留参数 extra_var解析生成) ====================
  'extra' =>
  array (
  ),
  // ==================== SEO参数配置 (system/configseo) ====================
  'seo' =>
  array (
    'vod' =>
    array (
      'name' => '视频首页', // 视频首页标题
      'key' => '短视频,搞笑视频,视频分享,免费视频,在线视频,预告片', // 视频首页关键词
      'des' => '提供最新最快的视频分享数据', // 视频首页描述
    ),
    'art' =>
    array (
      'name' => '文章首页', // 文章首页标题
      'key' => '新闻资讯,娱乐新闻,八卦娱乐,狗仔队,重大事件', // 文章首页关键词
      'des' => '提供最新最快的新闻资讯', // 文章首页描述
    ),
    'actor' =>
    array (
      'name' => '演员首页', // 演员首页标题
      'key' => '大陆明星,港台明星,日韩明星,欧美明星,最火明星', // 演员首页关键词
      'des' => '明星个人信息介绍', // 演员首页描述
    ),
    'role' =>
    array (
      'name' => '角色首页', // 角色首页标题
      'key' => '电影角色,电视剧角色,动漫角色,综艺角色', // 角色首页关键词
      'des' => '角色人物介绍', // 角色首页描述
    ),
    'plot' =>
    array (
      'name' => '剧情首页', // 剧情首页标题
      'key' => '剧情连载,剧情更新,剧情前瞻,剧情完结', // 剧情首页关键词
      'des' => '提供最新的剧情信息', // 剧情首页描述
    ),
  ),
  // ==================== URL推送配置 (百度/神马等搜索引擎推送) ====================
  'urlsend' =>
  array (
    'baidu' =>
    array (
      'token' => '111', // 百度推送Token
    ),
    'baidufast' =>
    array (
      'token' => '222', // 百度快速收录Token
    ),
  ),
);