<?php
/**
 * 视频播放器配置文件 (VodPlayer Configuration)
 * ============================================================
 *
 * 【文件说明】
 * 存储视频播放器的配置数据
 * 由后台 "视频 → 播放器管理" 功能自动维护
 *
 * 【数据结构】
 * 数组键名 = 播放器编码 (from)
 * 数组值 = 播放器配置信息
 *
 * 【配置字段说明】
 * ┌──────────────┬─────────────────────────────────────────┐
 * │ 字段名        │ 说明                                     │
 * ├──────────────┼─────────────────────────────────────────┤
 * │ from         │ 播放器编码 (唯一标识，需与服务器组匹配)     │
 * │ show         │ 显示名称 (前台展示，如"DPlayer播放器")    │
 * │ des          │ 描述/备注                                │
 * │ tip          │ 提示信息 (如"无需安装任何插件")           │
 * │ sort         │ 排序值 (数字越大越靠前)                   │
 * │ status       │ 状态: 0=禁用, 1=启用                     │
 * │ ps           │ 解析状态: 0=不解析, 1=启用解析            │
 * │ parse        │ 独立解析接口URL (ps=1时生效)              │
 * │ target       │ 打开方式: _self=当前窗口, _blank=新窗口   │
 * └──────────────┴─────────────────────────────────────────┘
 *
 * 【播放器JS代码】
 * JS代码存储在: static/player/{from}.js
 * 前台播放时会加载对应的JS文件
 *
 * 【与服务器组的关系】
 * - 播放器编码需与服务器组编码(vodserver.from)匹配
 * - 视频播放地址格式: "服务器组编码$播放URL"
 * - 播放时根据编码选择对应播放器
 *
 * 【内置播放器说明】
 * - dplayer: DPlayer H5播放器，支持弹幕
 * - videojs: VideoJS H5播放器
 * - iva: IVA H5播放器
 * - iframe: iframe嵌入外链
 * - link: 外链跳转播放
 * - swf: Flash播放器
 * - flv: FLV格式播放
 *
 * 【使用方法】
 * $players = config('vodplayer');  // 获取所有播放器
 * $player = config('vodplayer.dplayer');  // 获取指定播放器
 *
 * @package     app\extra
 */
return array (
  'dbm3u8' => 
  array (
    'status' => '1',
    'from' => 'dbm3u8',
    'show' => '豆瓣资源',
    'des' => '豆瓣资源',
    'target' => '_self',
    'ps' => '1',
    'parse' => 'https://www.dbjiexi.com:966/jx/?url=',
    'sort' => '10000',
    'tip' => 'dbzy.com dbzy.tv  doubanzy.net  doubanzy.cc
doubanziyuan.net  doubanziyuan.com',
    'id' => 'dbm3u8',
  ),
  'dbyun' => 
  array (
    'status' => '1',
    'from' => 'dbyun',
    'show' => '豆瓣云',
    'des' => '豆瓣云',
    'target' => '_self',
    'ps' => '0',
    'parse' => 'https://www.dbjiexi.com:966/jx/?url=',
    'sort' => '10000',
    'tip' => 'dbzy.com dbzy.tv  doubanzy.net  doubanzy.cc
doubanziyuan.net  doubanziyuan.com',
    'id' => 'dbyun',
  ),
  'dplayer' => 
  array (
    'status' => '1',
    'from' => 'dplayer',
    'show' => 'DPlayer-H5播放器',
    'des' => 'dplayer.js.org',
    'target' => '_self',
    'ps' => '0',
    'parse' => '',
    'sort' => '908',
    'tip' => '无需安装任何插件',
    'id' => 'dplayer',
  ),
  'videojs' => 
  array (
    'status' => '1',
    'sort' => '907',
    'from' => 'videojs',
    'show' => 'videojs-H5播放器',
    'des' => 'videojs.com',
    'parse' => '',
    'ps' => '0',
    'tip' => '无需安装任何插件',
    'id' => 'videojs',
  ),
  'iva' => 
  array (
    'status' => '1',
    'from' => 'iva',
    'show' => 'iva-H5播放器',
    'des' => 'videojj.com',
    'target' => '_self',
    'ps' => '0',
    'parse' => '',
    'sort' => '906',
    'tip' => '无需安装任何插件',
    'id' => 'iva',
  ),
  'iframe' => 
  array (
    'status' => '1',
    'from' => 'iframe',
    'show' => 'iframe外链数据',
    'des' => 'iframe外链数据',
    'ps' => '0',
    'parse' => '',
    'sort' => '905',
    'tip' => '无需安装任何插件',
    'id' => 'iframe',
  ),
  'link' => 
  array (
    'status' => '1',
    'sort' => '904',
    'from' => 'link',
    'show' => '外链数据',
    'des' => '外部网站播放链接',
    'ps' => '0',
    'parse' => '',
    'tip' => '无需安装任何插件',
    'id' => 'link',
  ),
  'swf' => 
  array (
    'status' => '1',
    'sort' => '903',
    'from' => 'swf',
    'show' => 'Flash文件',
    'des' => 'swf',
    'parse' => '',
    'ps' => '0',
    'tip' => '无需安装任何插件',
    'id' => 'swf',
  ),
  'flv' => 
  array (
    'status' => '1',
    'from' => 'flv',
    'show' => 'Flv文件',
    'des' => 'flv',
    'target' => '_self',
    'ps' => '0',
    'parse' => '',
    'sort' => '902',
    'tip' => '无需安装任何插件	',
    'id' => 'flv',
  ),
);