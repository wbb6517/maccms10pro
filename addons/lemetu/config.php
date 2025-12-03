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
        'rule'    => '',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => 'readonly class="layui-input" style="width:300px;background:#f2f2f2;cursor:not-allowed;"',
    ],
    [
        'name'    => 'table_prefix',
        'title'   => '表前缀',
        'type'    => 'string',
        'content' => [],
        'value'   => 'lmt_',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '插件数据表前缀，导入SQL时替换默认的 mac_',
        'ok'      => '',
        'extend'  => 'class="layui-input" style="width:150px;"',
    ],
    [
        'name'    => 'reinstall_tables',
        'title'   => '重装数据表',
        'type'    => 'select',
        'content' => ['0' => '否', '1' => '是（数据会丢失！）'],
        'value'   => '0',
        'rule'    => '',
        'msg'     => '',
        'tip'     => '选"是"后禁用再启用插件，将删除并重建数据表',
        'ok'      => '',
        'extend'  => 'style="width:200px;"',
    ],
];