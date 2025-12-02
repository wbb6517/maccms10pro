<?php
/**
 * 模板验证器 (Template Validator)
 * ============================================================
 *
 * 【文件说明】
 * 模板文件操作的验证器，用于验证模板文件编辑时的参数有效性。
 * 确保文件名和路径参数不为空。
 *
 * 【使用场景】
 * - Template::info() 控制器方法中验证 POST 提交的参数
 * - 新增或编辑模板文件时验证必填字段
 *
 * 【验证规则】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 验证规则                                 │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ fname            │ require - 文件名必填                     │
 * │ fpath            │ require - 文件路径必填                   │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * 【相关文件】
 * - application/admin/controller/Template.php : 模板管理控制器
 *
 * ============================================================
 */
namespace app\common\validate;
use think\Validate;

class Template extends Validate
{
    /**
     * 验证规则
     * - fname : 文件名必填
     * - fpath : 文件路径必填
     */
    protected $rule =   [
        'fname'=>'require',
        'fpath'=>'require',
    ];

    /**
     * 验证错误信息
     * 使用语言包键名，支持多语言
     */
    protected $message  =   [
        'fname.require' => 'validate/require_name',
        'fpath.require'   => 'validate/require_path',
    ];

}