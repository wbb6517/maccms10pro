<?php
/**
 * 验证码消息验证器 (Message Validator)
 * ============================================================
 *
 * 【文件说明】
 * 验证码消息数据的验证规则定义
 * 用于验证发送验证码时提交的数据合法性
 *
 * 【验证规则】
 * ┌────────────────┬────────────────────────────────────────────┐
 * │ 字段            │ 规则说明                                    │
 * ├────────────────┼────────────────────────────────────────────┤
 * │ msg_to         │ 必填，接收地址 (邮箱或手机号)                 │
 * │ msg_code       │ 必填，验证码                                 │
 * │ msg_type       │ 必填，消息类型 (1=绑定/2=找回/3=注册)         │
 * └────────────────┴────────────────────────────────────────────┘
 *
 * 【验证场景】
 * - add  : 新增验证码记录
 * - edit : 编辑验证码记录
 *
 * 【关联模型】
 * - application/common/model/Msg.php : 在 saveData() 中调用验证
 *
 * 【语言包键值】
 * - validate/require_msg_to : 接收地址必须
 * - validate/require_verify : 验证码必须
 * - validate/require_type   : 类型必须
 *
 * ============================================================
 */
namespace app\common\validate;
use think\Validate;

class Msg extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule =   [
        'msg_to'  => 'require',    // 接收地址必填
        'msg_code'  => 'require',  // 验证码必填
        'msg_type'  => 'require',  // 消息类型必填
    ];

    /**
     * 错误提示消息
     * 使用语言包键值，支持多语言
     * @var array
     */
    protected $message  =   [
        'msg_to.require' => 'validate/require_msg_to',
        'msg_code.require' => 'validate/require_verify',
        'msg_type.require' => 'validate/require_type',
    ];

    /**
     * 验证场景定义
     * @var array
     */
    protected $scene = [
        'add'  =>  ['msg_to','msg_code','msg_type'],  // 新增场景
        'edit'  =>  ['msg_to','msg_code','msg_type'], // 编辑场景
    ];

}