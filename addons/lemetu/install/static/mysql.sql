--
DROP TABLE IF EXISTS `mac_adtype`;
CREATE TABLE `mac_adtype` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typename` varchar(100) CHARACTER SET utf8 DEFAULT NULL COMMENT '类别名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1-正常；0：禁用',
  `sort` int(11) NOT NULL DEFAULT '50' COMMENT '排序',
  `tag` varchar(50) CHARACTER SET utf8 DEFAULT NULL COMMENT '广告位标识',
  `description` varchar(2000) CHARACTER SET utf8 DEFAULT NULL COMMENT '描述',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='APP广告管理';

--
LOCK TABLES `mac_adtype` WRITE;
INSERT INTO `mac_adtype` VALUES
(1,'讯飞语音ID',0,0,'xunfei_appid','9de76e51',15609654034,1630214058), 
(2,'SDK_媒体ID',1,0,'application_id','6636',15609654035,1630214059),
(3,'SDK_开屏ID',1,0,'sdk_startup','11552',15609654034,1630214131),
(4,'SDK_插屏ID',1,0,'sdk_Insertscreen','11553',15609654033,1630214125),
(5,'SDK_视频流',1,0,'sdk_Videoinformationstream','11558',15609654032,1630214059),
(6,'SDK_信息流',1,0,'sdk_informationflow','11554',15609654031,1630214137),
(7,'SDK_banner',1,0,'sdk_banner','11557',15609654030,1630214059),
(8,'SDK_激励视频',1,0,'sdk_excitation','11555',15609654029,1630214131),
(9,'SDK_全屏视频',1,0,'sdk_Fullscreenvideo','11556',15609654028,1630214125),
(10,'SDK_影豆奖励',1,0,'sdk_nreward','10',15609654027,1630214059),
(11,'自定义账号',1,0,'define_account','开启后可自定义账户',15609654026,1630214155),
(12,'会员画中画',1,0,'pictureinpicture','开启后仅会员用户能使用画中画',15609654025,1630214149),
(13,'会员下载',1,0,'download','开启后仅会员用户支持下载',15609654024,1630214144),
(14,'会员投屏',1,0,'projection','开启后仅会员用户才能投屏',15609654023,1630214137),
(15,'置顶留言',1,0,'liuyan_message','本软件永久免费，遇到任何问题可尝试下载最新版，永久下载地址：https://www.Lemetu.com或点击我的页面联系客服',15609654022,1643475849),
(16,'启动广告位',1,0,'startup_adv','<a href=\"https://www.lemetu.com\" target=\"_blank\"><img src=\"https://s2.loli.net/2024/03/12/zIaXKPZL9B1g6Tu.png\"  /></a>',15609654021,1630214059),
(17,'首页广告位',0,0,'index','<a href=\"https://www.Lemetu.com\" target=\"_blank\"><img src=\"http://icciu.cn/images/2022/11/02/a4bf10e9db974bee951ebb976984a83e.gif\" width=\"100%\" height=\"100%\" border=\"0\" /></a> \r\n',15609654020,1630214125),
(18,'搜索广告位',0,0,'searcher','<a href=\"https://www.lemetu.com\" target=\"_blank\"><img src=\"https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png\" width=\"100%\" height=\"100%\" border=\"0\" /></a> \r\n',15609654019,1630214161),
(19,'电影广告位',0,0,'vod','<a href=\"https://www.Lemetu.com\" target=\"_blank\"><img src=\"https://s2.loli.net/2024/03/12/iOWEYeGmTpbjvu7.gif\" width=\"100%\" height=\"100%\" border=\"0\" /></a> \r\n',15609654018,1630214131),
(20,'剧集广告位',0,0,'sitcom','<a href=\"https://www.Lemetu.com\" target=\"_blank\"><img src=\"https://s2.loli.net/2024/03/12/iOWEYeGmTpbjvu7.gif\" width=\"100%\" height=\"100%\" border=\"0\" /></a> \r\n',15609654017,1630214137),
(21,'动漫广告位',0,0,'cartoon','<a href=\"https://www.Lemetu.com\" target=\"_blank\"><img src=\"https://s2.loli.net/2024/03/12/iOWEYeGmTpbjvu7.gif\" width=\"100%\" height=\"100%\" border=\"0\" /></a> \r\n',15609654016,1630214144),
(22,'综艺广告位',0,0,'variety','<a href=\"https://www.Lemetu.com\" target=\"_blank\"><img src=\"https://s2.loli.net/2024/03/12/iOWEYeGmTpbjvu7.gif\" width=\"100%\" height=\"100%\" border=\"0\" /></a> \r\n',15609654015,1630214149),
(23,'我的广告位',0,0,'user_center','https://s2.loli.net/2024/03/12/o5YUQHaeSl2fBG7.png',15609654014,1593330630),
(24,'播放器下方广告',1,0,'player_down','<a href=\"https://www.Lemetu.com\" target=\"_blank\"><img src=\"https://s2.loli.net/2024/03/12/iOWEYeGmTpbjvu7.gif\" width=\"100%\" height=\"100%\" border=\"0\" /></a> \r\n',15609654013,1630214155),
(25,'播放器暂停广告',1,0,'player_pause','https://s2.loli.net/2024/03/12/iOWEYeGmTpbjvu7.gif\r\n',15609654012,1642905250),
(26,'分享页说明',1,0,'share_description','1、普通用户分享成功可获得积分奖励2、代理用户分销成功可获得金币奖励3、积分可兑换会员，金币可申请提现',15609654011,1630214161),
(27,'播放器logo',0,0,'play_logo','https://s2.loli.net/2024/03/12/ECZnk7v59oINgFz.png',15609654010,1644206194),
(28,'充值入口',1,0,'rechargeentry','即日起平台支持会员购买和卡密充值，但我们更期待您通过完成每日任务来获得积分奖励，积分可在本平台消费或兑换特权噢~~',1560965409,1630214131),
(29,'首页logo',0,0,'home_logo','https://s2.loli.net/2024/03/12/ECZnk7v59oINgFz.png',1560965408,1644034614),
(30,'未登录头像',0,0,'user_logo','https://s2.loli.net/2024/01/19/jtwl8J3z4rkHVc7.jpg',1560965407,1644037061),
(31,'首页顶部背景',0,0,'home_backg','https://s2.loli.net/2022/03/13/6mzwHM5FZaTpQ3A.png',1560965406,1644037033),
(32,'幻灯片背景',0,0,'home_backg_b','https://s2.loli.net/2024/03/12/B5FLJTzaZue4mWb.png',1560965405,1644034614),
(33,'个人中心背景',0,0,'user_backg','https://s2.loli.net/2024/03/12/B5FLJTzaZue4mWb.png',1560965404,1644034614),
(34,'官方QQ群',1,0,'service_qqqun','YTqV0ej-pqK8tHl7-5m66HMDCcJPK0Sd',1560965403,1593330630),
(35,'QQ客服',1,0,'service_qq','208524822',1560965402,1598795259),
(36,'搜索失败反馈',0,0,'search_feedback','搜索失败反馈通道，开启为联系QQ客服，关闭则跳转反馈页',1560965401,1644079214),
(37,'发现页权限',0,0,'live_vip','开启后发现页需要VIP权限才能访问',1560965400,1644079214),
(38,'会员模式',0,0,'player_down_isvip','大会员6.3折！免费看海量精选视频...||海量精选会员内容免费看 · 抢先看 · 高清视频随心挑...',1560965399,1630214059);
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_app_install_record`;
CREATE TABLE `mac_app_install_record` (
  `app_install_record_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_ip` varchar(255) NOT NULL DEFAULT '',
  `invite_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  `is_pull` int(255) NOT NULL DEFAULT '0',
  `extra` varchar(255) NOT NULL DEFAULT '',
  `os` varchar(255) NOT NULL DEFAULT '',
  `os_version` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`app_install_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
LOCK TABLES `mac_app_install_record` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_app_version`;
CREATE TABLE `mac_app_version` (
  `app_version_id` int(11) NOT NULL AUTO_INCREMENT,
  `os` varchar(255) NOT NULL,
  `version` varchar(255) NOT NULL,
  `summary` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  `is_required` int(11) NOT NULL,
  `type` int(255) DEFAULT '1',
  `url2` varchar(255) NOT NULL,
  PRIMARY KEY (`app_version_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='APP更新';

--
LOCK TABLES `mac_app_version` WRITE;
INSERT INTO `mac_app_version` VALUES (2,'1','v0.1.0','此版本更新增幅比较大！\r\n若无法安装请联系QQ208524822\r\n备注乐美兔M','https://www.123pan.com/s/EpPzVv-nXD3A.html',1572790342,1634627914,1,1,'https://www.123pan.com/s/EpPzVv-nXD3A.html');
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_category`;
CREATE TABLE `mac_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(60) NOT NULL COMMENT '分类名',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父类id',
  `void_id` text NOT NULL COMMENT '电影id',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否开启：0：否；1：是',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='APP栏目分类';

--
LOCK TABLES `mac_category` WRITE;
INSERT INTO `mac_category` VALUES (1,'电影抢先看',0,'108498,108487,108486,108478,108477,108475,108476,108470,108465,108464',1,0,1577729705,1595380938),(2,'追剧乐翻天',0,'108483,108482,108471,108472,108469,108466,106070,108460,108458,105760',1,0,1577729866,1595380965),(3,'腾讯视频专区',0,'41574,108442,36749,51553,56303,107339,51283,50797,50932,50643',1,0,1577729909,1595380992),(4,'优酷视频专区',0,'6994,50009,2969,42450,50011,49988,49548,47453,48618',1,0,1577729960,1583927634),(5,'最热喜剧大片',0,'107401,108502,108501,108500,108499,50956,108498,108495,108491,108494,108493',1,0,1577730105,1595381021);
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_danmu`;
CREATE TABLE `mac_danmu` (
  `danmu_id` int(11) NOT NULL AUTO_INCREMENT,
  `content` varchar(255) NOT NULL,
  `vod_id` int(11) NOT NULL,
  `at_time` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `danmu_time` int(11) NOT NULL,
  `status` int(255) NOT NULL DEFAULT '1',
  `dianzan_num` int(11) NOT NULL DEFAULT '0',
  `danmu_ip` char(200) NOT NULL,
  `color` char(50) NOT NULL,
  PRIMARY KEY (`danmu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8 COMMENT='弹幕';

--
LOCK TABLES `mac_danmu` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_glog`;
CREATE TABLE `mac_glog` (
  `glog_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `user_id_1` int(10) NOT NULL DEFAULT '0',
  `glog_type` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `glog_gold` smallint(6) unsigned NOT NULL DEFAULT '0',
  `glog_time` int(10) unsigned NOT NULL DEFAULT '0',
  `glog_remarks` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`glog_id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `glog_type` (`glog_type`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='发起提现';

--
LOCK TABLES `mac_glog` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_gold_withdraw_apply`;
CREATE TABLE `mac_gold_withdraw_apply` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `num` decimal(8,2) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '0' COMMENT '0 审批中 1 提现成功 2 提现失败',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_time` int(11) NOT NULL COMMENT '创建时间',
  `updated_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  `success_time` int(11) NOT NULL DEFAULT '0' COMMENT '// 提现成功时间',
  `fail_time` int(11) NOT NULL DEFAULT '0' COMMENT '// 结束时间',
  `type` int(11) NOT NULL DEFAULT '0' COMMENT '提现方式',
  `account` varchar(255) NOT NULL DEFAULT '0' COMMENT '账户',
  `realname` varchar(255) NOT NULL DEFAULT '''''' COMMENT '真实姓名',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='金币提现申请';

--
LOCK TABLES `mac_gold_withdraw_apply` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_tvdata`;
CREATE TABLE `mac_tvdata` (
  `mykey` varchar(255) NOT NULL,
  `value` varchar(555) NOT NULL,
  PRIMARY KEY (`mykey`),
  KEY `mykey` (`mykey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
LOCK TABLES `mac_tvdata` WRITE;
INSERT INTO `mac_tvdata` (`mykey`, `value`) VALUES
('ggonggao', '本APP使用乐美兔API 购买授权请联系QQ：'),
('jxiv', '1111111111111111'),
('jxkey', '00000000000000000000000000000000'),
('jxtype', '1'),
('yytype', '1'),
('umengksy', '6076756e9e4e8b6f616c287b');
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_gonggao`;
CREATE TABLE `mac_gonggao` (
  `mykey` varchar(255) NOT NULL,
  `value` varchar(555) NOT NULL,
  PRIMARY KEY (`mykey`),
  KEY `mykey` (`mykey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
LOCK TABLES `mac_gonggao` WRITE;
INSERT INTO `mac_gonggao` VALUES ('gg1','本APP使用乐美兔API 购买授权请联系QQ：'),('gg2','影视APP、小程序、电视TV架设：http://www.rclou.cn');
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_groupchat`;
CREATE TABLE `mac_groupchat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='群聊';

--
LOCK TABLES `mac_groupchat` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_message`;
CREATE TABLE `mac_message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `create_date` datetime NOT NULL,
  `content` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='APP公告通知';

--
LOCK TABLES `mac_message` WRITE;
INSERT INTO `mac_message` VALUES (1,'你好陌生人：','2024-03-10 23:13:57','愿你三冬暖、愿你春不寒、愿你天黑有灯、下雨有伞、愿你路上有良人相伴~');
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_sign`;
CREATE TABLE `mac_sign` (
  `sign_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL,
  `date` varchar(20) NOT NULL,
  `reward` varchar(500) NOT NULL,
  `create_time` int(10) NOT NULL,
  `update_time` int(10) NOT NULL,
  PRIMARY KEY (`sign_id`)
) ENGINE=InnoDB AUTO_INCREMENT=192 DEFAULT CHARSET=utf8;

--
LOCK TABLES `mac_sign` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_tmpvod`;
CREATE TABLE `mac_tmpvod` (
  `id1` int(10) unsigned DEFAULT NULL,
  `name1` varchar(255) NOT NULL DEFAULT '',
  `name_type` varchar(291) NOT NULL DEFAULT '',
  `tid1` smallint(6) NOT NULL DEFAULT '0',
  `year1` varchar(10) NOT NULL DEFAULT '',
  `area1` varchar(20) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
LOCK TABLES `mac_tmpvod` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_umeng`;
CREATE TABLE `mac_umeng` (
  `mykey` varchar(255) NOT NULL,
  `value` varchar(555) NOT NULL,
  PRIMARY KEY (`mykey`),
  KEY `mykey` (`mykey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
LOCK TABLES `mac_umeng` WRITE;
INSERT INTO `mac_umeng` VALUES ('umkey','61111b93063bed4d8c115b99');
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_view30m`;
CREATE TABLE `mac_view30m` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_time` int(11) DEFAULT NULL,
  `view_seconds` int(255) DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  `vod_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=34226 DEFAULT CHARSET=utf8;

--
LOCK TABLES `mac_view30m` WRITE;
INSERT INTO `mac_view30m` VALUES (34222,1635867897,60,296,NULL),(34223,1635867958,60,296,NULL),(34224,1635868018,60,296,NULL),(34225,1635868564,60,296,NULL);
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_vlog`;
CREATE TABLE `mac_vlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vod_id` int(11) DEFAULT NULL,
  `nid` varchar(200) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `percent` varchar(255) DEFAULT NULL,
  `last_view_time` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `curProgress` int(11) NOT NULL,
  `urlIndex` int(11) NOT NULL,
  `playSourceIndex` int(11) NOT NULL,
  `isvip` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=15191 DEFAULT CHARSET=utf8 COMMENT='APP播放记录';

--
LOCK TABLES `mac_vlog` WRITE;
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_youxi`;
CREATE TABLE `mac_youxi` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT NULL,
  `img` varchar(500) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='APP游戏页面';

--
LOCK TABLES `mac_youxi` WRITE;
INSERT INTO `mac_youxi` VALUES (1,'游戏','1','http://www.rclou.cn');
UNLOCK TABLES;

--
DROP TABLE IF EXISTS `mac_zhibo`;
CREATE TABLE `mac_zhibo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT NULL,
  `img` varchar(500) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COMMENT='直播';

--
LOCK TABLES `mac_zhibo` WRITE;
INSERT INTO `mac_zhibo` VALUES (1,'虎牙电视轮播','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','https://m.huya.com/g/2135?rso=huya_h5_395'),(2,'网络电视直播','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','http://www.zhiboba.org/dianshitai'),(3,'游拍直播','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','https://m.4399youpai.com/zhibo/game'),(4,'六间房直播','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','http://m.v.6.cn/?referrer='),(5,'电视剧轮播','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','http://www.baidu.com'),(6,'虎牙游戏直播','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','https://www.huya.com/g'),(7,'二层楼','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','https://www.rclou.cn'),(8,'乐美兔','https://s2.loli.net/2024/03/12/3T5zDy7Lb9R8SZW.png','https://www.Lemetu.com');
UNLOCK TABLES;

--
LOCK TABLES `mac_groupchat` WRITE;
INSERT INTO `mac_groupchat` (`id`, `title`, `url`) VALUES (1, '官方订阅', 'https://www.rclou.cn'),(2, '官方网站', 'https://www.rclou.cn'),(3, '乐美兔主题', 'https://www.lemetu.com'),(4, '师兄易支付', 'https://www.sxion.com');
UNLOCK TABLES;