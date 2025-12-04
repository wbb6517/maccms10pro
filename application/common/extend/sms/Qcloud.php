<?php
/**
 * 腾讯云短信扩展 (Tencent Cloud SMS Extension)
 * ============================================================
 *
 * 【文件说明】
 * 腾讯云短信服务 SDK 封装
 * 用于发送验证码短信，支持用户注册、密码找回、账号绑定等场景
 *
 * 【API 接口】
 * - 接口地址: https://yun.tim.qq.com/v5/tlssmssvr/sendsms
 * - 签名算法: SHA256
 *
 * 【配置要求】
 * 在后台 系统设置 → 短信配置 中配置:
 * - appid  : SDKAppID (腾讯云控制台获取)
 * - appkey : App Key
 * - sign   : 短信签名 (需在腾讯云申请)
 * - tpl_code_xxx : 模板ID (对应不同业务场景)
 *
 * 【方法列表】
 * ┌──────────────────────────────────────┬────────────────────────────────────────┐
 * │ 方法名                                │ 功能说明                                │
 * ├──────────────────────────────────────┼────────────────────────────────────────┤
 * │ __construct()                        │ 构造函数，初始化配置                     │
 * │ getRandom()                          │ 生成6位随机数                           │
 * │ calculateSig()                       │ 计算签名 (多手机号)                      │
 * │ calculateSigForTemplAndPhoneNumbers()│ 计算模板签名 (多手机号)                  │
 * │ phoneNumbersToArray()                │ 手机号转换为接口格式                     │
 * │ calculateSigForTempl()               │ 计算模板签名 (单手机号)                  │
 * │ calculateSigForPuller()              │ 计算拉取签名                            │
 * │ calculateAuth()                      │ 计算文件上传授权                         │
 * │ sha1sum()                            │ 计算 SHA1 散列                          │
 * │ sendCurlPost()                       │ 发送 CURL POST 请求                     │
 * │ sendWithParam()                      │ 发送模板短信                            │
 * │ submit()                             │ 发送验证码短信 (对外接口)                │
 * └──────────────────────────────────────┴────────────────────────────────────────┘
 *
 * 【调用位置】
 * - application/common/model/User.php : sendUserMsg() 方法
 *
 * 【使用示例】
 * $sms = new \app\common\extend\sms\Qcloud();
 * $result = $sms->submit('13800138000', '123456', 'reg', '注册', '');
 *
 * ============================================================
 */
namespace app\common\extend\sms;

class Qcloud {

    /**
     * 扩展名称
     * @var string
     */
    public $name = '腾讯云短信';

    /**
     * 版本号
     * @var string
     */
    public $ver = '2.0';

    /**
     * API 请求地址
     * @var string
     */
    private $url;

    /**
     * SDKAppID
     * @var string
     */
    private $appid;

    /**
     * App Key
     * @var string
     */
    private $appkey;

    /**
     * 构造函数
     * 初始化 API 地址和密钥配置
     */
    public function __construct()
    {
        $this->url = "https://yun.tim.qq.com/v5/tlssmssvr/sendsms";
        $this->appid =  $GLOBALS['config']['sms']['qcloud']['appid'];
        $this->appkey = $GLOBALS['config']['sms']['qcloud']['appkey'];
    }

    /**
     * 生成随机数
     *
     * @return int 随机数结果
     */
    public function getRandom()
    {
        return rand(100000, 999999);
    }

    /**
     * 生成签名
     *
     * @param string $appkey        sdkappid对应的appkey
     * @param string $random        随机正整数
     * @param string $curTime       当前时间
     * @param array  $phoneNumbers  手机号码
     * @return string  签名结果
     */
    public function calculateSig($appkey, $random, $curTime, $phoneNumbers)
    {
        $phoneNumbersString = $phoneNumbers[0];
        for ($i = 1; $i < count($phoneNumbers); $i++) {
            $phoneNumbersString .= ("," . $phoneNumbers[$i]);
        }

        return hash("sha256", "appkey=".$appkey."&random=".$random
            ."&time=".$curTime."&mobile=".$phoneNumbersString);
    }

    /**
     * 生成签名
     *
     * @param string $appkey        sdkappid对应的appkey
     * @param string $random        随机正整数
     * @param string $curTime       当前时间
     * @param array  $phoneNumbers  手机号码
     * @return string  签名结果
     */
    public function calculateSigForTemplAndPhoneNumbers($appkey, $random,
                                                        $curTime, $phoneNumbers)
    {
        $phoneNumbersString = $phoneNumbers[0];
        for ($i = 1; $i < count($phoneNumbers); $i++) {
            $phoneNumbersString .= ("," . $phoneNumbers[$i]);
        }

        return hash("sha256", "appkey=".$appkey."&random=".$random
            ."&time=".$curTime."&mobile=".$phoneNumbersString);
    }

    /**
     * 将手机号数组转换为 API 请求格式
     *
     * @param string $nationCode   国家码 (如 86)
     * @param array  $phoneNumbers 手机号数组
     * @return array 格式化后的手机号对象数组
     */
    public function phoneNumbersToArray($nationCode, $phoneNumbers)
    {
        $i = 0;
        $tel = array();
        do {
            $telElement = new \stdClass();
            $telElement->nationcode = $nationCode;
            $telElement->mobile = $phoneNumbers[$i];
            array_push($tel, $telElement);
        } while (++$i < count($phoneNumbers));

        return $tel;
    }

    /**
     * 生成签名
     *
     * @param string $appkey        sdkappid对应的appkey
     * @param string $random        随机正整数
     * @param string $curTime       当前时间
     * @param array  $phoneNumber   手机号码
     * @return string  签名结果
     */
    public function calculateSigForTempl($appkey, $random, $curTime, $phoneNumber)
    {
        $phoneNumbers = array($phoneNumber);

        return $this->calculateSigForTemplAndPhoneNumbers($appkey, $random,
            $curTime, $phoneNumbers);
    }

    /**
     * 生成签名
     *
     * @param string $appkey        sdkappid对应的appkey
     * @param string $random        随机正整数
     * @param string $curTime       当前时间
     * @return string 签名结果
     */
    public function calculateSigForPuller($appkey, $random, $curTime)
    {
        return hash("sha256", "appkey=".$appkey."&random=".$random
            ."&time=".$curTime);
    }

    /**
     * 生成上传文件授权
     *
     * @param string $appkey        sdkappid对应的appkey
     * @param string $random        随机正整数
     * @param string $curTime       当前时间
     * @param array  $fileSha1Sum   文件sha1sum
     * @return string  授权结果
     */
    public function calculateAuth($appkey, $random, $curTime, $fileSha1Sum)
    {
        return hash("sha256", "appkey=".$appkey."&random=".$random
            ."&time=".$curTime."&content-sha1=".$fileSha1Sum);
    }

    /**
     * 生成sha1sum
     *
     * @param string $content  内容
     * @return string  内容sha1散列值
     */
    public function sha1sum($content)
    {
        return hash("sha1", $content);
    }

    /**
     * 发送 CURL POST 请求
     *
     * @param string $url     请求地址
     * @param object $dataObj 请求数据对象
     * @return string JSON 格式的响应字符串
     */
    public function sendCurlPost($url, $dataObj)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dataObj));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $ret = curl_exec($curl);
        if (false == $ret) {
            // curl_exec failed
            $result = "{ \"result\":" . -2 . ",\"errmsg\":\"" . curl_error($curl) . "\"}";
        } else {
            $rsp = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "{ \"result\":" . -1 . ",\"errmsg\":\"". $rsp
                    . " " . curl_error($curl) ."\"}";
            } else {
                $result = $ret;
            }
        }

        curl_close($curl);

        return $result;
    }

    /**
     * 发送带参数的模板短信
     *
     * @param string $nationCode  国家码 (如 86)
     * @param string $phoneNumber 手机号
     * @param int    $templId     模板ID
     * @param array  $params      模板参数数组
     * @param string $sign        短信签名
     * @param string $extend      扩展码 (可选)
     * @param string $ext         用户自定义数据 (可选)
     * @return string JSON 格式的响应
     */
    public function sendWithParam($nationCode, $phoneNumber, $templId = 0, $params,
                                  $sign = "", $extend = "", $ext = "")
    {
        $random = $this->getRandom();
        $curTime = time();
        $wholeUrl = $this->url . "?sdkappid=" . $this->appid . "&random=" . $random;
        // 按照协议组织 post 包体
        $data = new \stdClass();
        $tel = new \stdClass();
        $tel->nationcode = "".$nationCode;
        $tel->mobile = "".$phoneNumber;

        $data->tel = $tel;
        $data->sig = $this->calculateSigForTempl($this->appkey, $random,
            $curTime, $phoneNumber);
        $data->tpl_id = $templId;
        $data->params = $params;
        $data->sign = $sign;
        $data->time = $curTime;
        $data->extend = $extend;
        $data->ext = $ext;

        return $this->sendCurlPost($wholeUrl, $data);
    }

    /**
     * ============================================================
     * 发送验证码短信 (对外接口)
     * ============================================================
     *
     * 【功能说明】
     * 调用腾讯云短信 API 发送验证码
     * 根据 type_flag 自动获取对应的模板ID
     *
     * 【参数说明】
     * - type_flag 对应配置: sms.tpl_code_{type_flag}
     *   - reg  : 注册验证码模板
     *   - bind : 绑定验证码模板
     *   - find : 找回密码模板
     *
     * @param string $phone     手机号码
     * @param string $code      验证码
     * @param string $type_flag 类型标识 (reg/bind/find)
     * @param string $type_des  类型描述 (用于日志)
     * @param string $text      短信内容 (腾讯云模板方式不使用)
     * @return array ['code'=>1,'msg'=>'ok'] 成功 / ['code'=>101,'msg'=>'错误信息']
     */
    public function submit($phone,$code,$type_flag,$type_des,$text)
    {
        if(empty($phone) || empty($code) || empty($type_flag)){
            return ['code'=>101,'msg'=>'参数错误'];
        }

        $sign = $GLOBALS['config']['sms']['sign'];
        $tpl = $GLOBALS['config']['sms']['tpl_code_'.$type_flag];
        $params = [
            $code
        ];

        try {
            $result = $this->sendWithParam("86", $phone, $tpl, $params, $sign, "", "");
            $rsp = json_decode($result,true);
            if($rsp['ErrorCode'] !==''){
                return ['code'=>1,'msg'=>'ok'];
            }
            return ['code'=>101,'msg'=>$rsp['errmsg']];
        }
        catch(\Exception $e) {
            return ['code'=>102,'msg'=>'发生异常请重试'];
        }
    }
}
