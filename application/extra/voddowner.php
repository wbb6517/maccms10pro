<?php
/**
 * 视频下载器配置文件 (VodDowner Configuration)
 * ============================================================
 *
 * 【文件说明】
 * 存储视频下载器的配置数据
 * 由后台 "视频 → 下载器管理" 功能自动维护
 *
 * 【数据结构】
 * 数组键名 = 下载器编码 (from)
 * 数组值 = 下载器配置信息
 *
 * 【配置字段说明】
 * ┌──────────────┬─────────────────────────────────────────┐
 * │ 字段名        │ 说明                                     │
 * ├──────────────┼─────────────────────────────────────────┤
 * │ from         │ 下载器编码 (唯一标识，如 http/xunlei)     │
 * │ show         │ 显示名称 (前台展示)                       │
 * │ des          │ 描述/备注                                │
 * │ tip          │ 提示信息 (鼠标悬停显示)                   │
 * │ sort         │ 排序值 (数字越大越靠前)                   │
 * │ status       │ 状态: 0=禁用, 1=启用                     │
 * │ ps           │ 解析状态: 0=不解析, 1=启用解析            │
 * │ parse        │ 独立解析接口URL (ps=1时生效)              │
 * │ target       │ 打开方式: _self=当前窗口, _blank=新窗口   │
 * └──────────────┴─────────────────────────────────────────┘
 *
 * 【与播放器的区别】
 * - 播放器(vodplayer): 在线播放，对应 vod_play_url 字段
 * - 下载器(voddowner): 下载链接，对应 vod_down_url 字段
 *
 * 【内置下载器说明】
 * - http: HTTP直接下载
 * - xunlei: 迅雷下载 (thunder://协议)
 *
 * 【使用方法】
 * $downers = config('voddowner');  // 获取所有下载器
 * $downer = config('voddowner.http');  // 获取指定下载器
 *
 * @package     app\extra
 */
return array (
  'http' =>
  array (
    'status' => '1',
    'from' => 'http',
    'show' => 'http下载',
    'des' => 'des提示信息',
    'ps' => '0',
    'parse' => '',
    'sort' => '90',
    'tip' => 'tip提示信息',
    'id' => 'http',
  ),
  'xunlei' =>
  array (
    'status' => '1',
    'from' => 'xunlei',
    'show' => 'xunlei下载',
    'des' => 'des提示信息',
    'target' => '_self',
    'ps' => '0',
    'parse' => '',
    'sort' => '90',
    'tip' => 'tip提示信息',
    'id' => 'xunlei',
  ),
);