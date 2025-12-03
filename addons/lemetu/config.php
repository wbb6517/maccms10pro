<?php
/**
 * 乐美兔插件配置文件 (Lemetu Addon Config)
 * ============================================================
 *
 * 【功能说明】
 * 定义插件的配置项，用于后台"配置"页面显示
 *
 * 【注意事项】
 * 此文件只应返回配置数组，不应包含任何副作用操作
 * 文件复制等初始化操作应在 Lemetu.php 的 enable() 方法中执行
 *
 * ============================================================
 */

return [
    [
        'name'    => 'api',
        'title'   => '插件官网',
        'type'    => 'string',
        'content' => [],
        'value'   => 'https://www.lemetu.com',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '您已经配置过了，如需初始化请删除/addons/lemetu/install.lock',
        'ok'      => '',
        'extend'  => 'style="width:500px;height:38px;line-height: 34px;"',
    ],
];