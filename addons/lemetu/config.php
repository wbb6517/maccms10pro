<?php

return array (
  0 => 
  array (
    'name' => 'api',
    'title' => '插件官网',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => 'https://www.lemetu.com',
    'rule' => '',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => 'readonly class="layui-input" style="width:300px;background:#f2f2f2;cursor:not-allowed;"',
  ),
  1 => 
  array (
    'name' => 'table_prefix',
    'title' => '表前缀',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => 'lmt_',
    'rule' => 'required',
    'msg' => '',
    'tip' => '插件数据表前缀，导入SQL时替换默认的 mac_',
    'ok' => '',
    'extend' => 'class="layui-input" style="width:150px;"',
  ),
  2 => 
  array (
    'name' => 'reinstall_tables',
    'title' => '重装数据表',
    'type' => 'select',
    'content' => 
    array (
      0 => '否',
      1 => '是（数据会丢失！）',
    ),
    'value' => '0',
    'rule' => '',
    'msg' => '',
    'tip' => '选"是"后禁用再启用插件，将删除并重建数据表',
    'ok' => '',
    'extend' => 'style="width:200px;"',
  ),
  3 => 
  array (
    'name' => 'keep_data_on_uninstall',
    'title' => '卸载保留数据',
    'type' => 'select',
    'content' => 
    array (
      0 => '否（删除所有数据表）',
      1 => '是（保留数据表）',
    ),
    'value' => '1',
    'rule' => '',
    'msg' => '',
    'tip' => '卸载插件时是否保留数据库中的数据表',
    'ok' => '',
    'extend' => 'style="width:200px;"',
  ),
);
