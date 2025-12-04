<?php
/**
 * PHPMailer 邮件扩展 (PHPMailer Email Extension)
 * ============================================================
 *
 * 【文件说明】
 * 基于 PHPMailer 库的邮件发送封装
 * 用于发送验证码邮件，支持用户注册、密码找回、账号绑定等场景
 *
 * 【邮件协议】
 * - 协议: SMTP
 * - 认证: SMTPAuth
 * - 编码: UTF-8
 * - 格式: HTML
 *
 * 【配置要求】
 * 在后台 系统设置 → 邮件配置 中配置:
 * - host     : SMTP 服务器地址 (如 smtp.qq.com)
 * - port     : SMTP 端口 (465/587)
 * - secure   : 加密方式 (ssl/tls)
 * - username : 邮箱账号
 * - password : 邮箱密码或授权码
 * - nick     : 发件人昵称
 *
 * 【方法列表】
 * ┌─────────────────┬─────────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                         │
 * ├─────────────────┼─────────────────────────────────────────────────┤
 * │ submit()        │ 发送邮件 (对外接口)                               │
 * └─────────────────┴─────────────────────────────────────────────────┘
 *
 * 【调用位置】
 * - application/common/model/User.php : sendUserMsg() 方法
 *
 * 【使用示例】
 * $mail = new \app\common\extend\email\Phpmailer();
 * $result = $mail->submit('user@example.com', '验证码', '<p>您的验证码是: 123456</p>');
 *
 * 【依赖库】
 * - phpmailer/src/PHPMailer.php
 *
 * ============================================================
 */
namespace app\common\extend\email;

class Phpmailer {

    /**
     * 扩展名称
     * @var string
     */
    public $name = 'PhpMailer';

    /**
     * 版本号
     * @var string
     */
    public $ver = '1.0';

    /**
     * ============================================================
     * 发送邮件 (对外接口)
     * ============================================================
     *
     * 【功能说明】
     * 使用 PHPMailer 库通过 SMTP 发送 HTML 格式邮件
     * 支持自定义配置或使用全局配置
     *
     * @param string $to     收件人邮箱地址
     * @param string $title  邮件标题
     * @param string $body   邮件正文 (HTML 格式)
     * @param array  $config 自定义配置 (可选，默认使用全局配置)
     * @return array ['code'=>1,'msg'=>'发送成功'] / ['code'=>102,'msg'=>'错误信息']
     */
    public function submit($to, $title, $body,$config=[])
    {
        if(empty($config)) {
            $config = $GLOBALS['config']['email']['phpmailer'];
            $config['nick'] =  $GLOBALS['config']['email']['nick'];
        }
        $mail = new \phpmailer\src\PHPMailer();
        //$mail->SMTPDebug = 2;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->CharSet = "UTF-8";
        $mail->Host = $config['host'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->setFrom($config['username'] , $config['nick']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body    = $body;
        unset($config);
        $res = $mail->send();

        if($res===true){
            return ['code'=>1,'msg'=>'发送成功'];
        }
        else{
            return ['code'=>102,'msg'=>'发生错误：'. $mail->ErrorInfo ];
        }
    }
}
