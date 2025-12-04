<?php
/**
 * 阿里云短信扩展 (Aliyun SMS Extension)
 * ============================================================
 *
 * 【文件说明】
 * 阿里云短信服务 SDK 封装
 * 用于发送验证码短信，支持用户注册、密码找回、账号绑定等场景
 *
 * 【API 接口】
 * - 接口域名: dysmsapi.aliyuncs.com
 * - API版本: 2017-05-25
 * - 签名算法: HMAC-SHA1
 *
 * 【配置要求】
 * 在后台 系统设置 → 短信配置 中配置:
 * - appid  : AccessKeyId (阿里云控制台获取)
 * - appkey : AccessKeySecret
 * - sign   : 短信签名 (需在阿里云申请)
 * - tpl_code_xxx : 模板ID (对应不同业务场景)
 *
 * 【方法列表】
 * ┌─────────────────┬─────────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                         │
 * ├─────────────────┼─────────────────────────────────────────────────┤
 * │ request()       │ 生成签名并发起 API 请求                           │
 * │ encode()        │ URL 编码 (阿里云规范)                             │
 * │ fetchContent()  │ 执行 CURL 请求                                   │
 * │ submit()        │ 发送验证码短信 (对外接口)                          │
 * └─────────────────┴─────────────────────────────────────────────────┘
 *
 * 【调用位置】
 * - application/common/model/User.php : sendUserMsg() 方法
 *
 * 【使用示例】
 * $sms = new \app\common\extend\sms\Aliyun();
 * $result = $sms->submit('13800138000', '123456', 'reg', '注册', '');
 *
 * 【相关文档】
 * - https://help.aliyun.com/document_detail/101414.html
 *
 * ============================================================
 */
namespace app\common\extend\sms;

class Aliyun {

    /**
     * 扩展名称
     * @var string
     */
    public $name = '阿里云短信';

    /**
     * 版本号
     * @var string
     */
    public $ver = '2.0';

    /**
     * 生成签名并发起请求
     *
     * @param $accessKeyId string AccessKeyId (https://ak-console.aliyun.com/)
     * @param $accessKeySecret string AccessKeySecret
     * @param $domain string API接口所在域名
     * @param $params array API具体参数
     * @param $security boolean 使用https
     * @param $method boolean 使用GET或POST方法请求，VPC仅支持POST
     * @return bool|\stdClass 返回API接口调用结果，当发生错误时返回false
     */
    public function request($accessKeyId, $accessKeySecret, $domain, $params, $security=false, $method='POST') {
        $apiParams = array_merge(array (
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => uniqid(mt_rand(0,0xffff), true),
            "SignatureVersion" => "1.0",
            "AccessKeyId" => $accessKeyId,
            "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "Format" => "JSON",
        ), $params);
        ksort($apiParams);

        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . $this->encode($key) . "=" . $this->encode($value);
        }

        $stringToSign = "${method}&%2F&" . $this->encode(substr($sortedQueryStringTmp, 1));

        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $accessKeySecret . "&",true));

        $signature = $this->encode($sign);

        $url = ($security ? 'https' : 'http')."://{$domain}/";

        try {
            $content = $this->fetchContent($url, $method, "Signature={$signature}{$sortedQueryStringTmp}");
            return json_decode($content,true);
        } catch( \Exception $e) {
            return false;
        }
    }

    /**
     * URL 编码 (阿里云规范)
     * 按照阿里云 API 签名规范进行 URL 编码
     * - 空格编码为 %20 (而非 +)
     * - 星号编码为 %2A
     * - 波浪号不编码
     *
     * @param string $str 待编码字符串
     * @return string 编码后的字符串
     */
    private function encode($str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }

    /**
     * 执行 CURL HTTP 请求
     *
     * @param string $url    请求地址
     * @param string $method 请求方法 (GET/POST)
     * @param string $body   请求体内容
     * @return string 响应内容
     * @throws \Exception CURL 执行失败时抛出异常
     */
    private function fetchContent($url, $method, $body) {
        $ch = curl_init();

        if($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            $url .= '?'.$body;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "x-sdk-client" => "php/2.0.0"
        ));

        if(substr($url, 0,5) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $rtn = curl_exec($ch);

        if($rtn === false) {
            // 大多由设置等原因引起，一般无法保障后续逻辑正常执行，
            // 所以这里触发的是E_USER_ERROR，会终止脚本执行，无法被try...catch捕获，需要用户排查环境、网络等故障
            trigger_error("[CURL_" . curl_errno($ch) . "]: " . curl_error($ch), E_USER_ERROR);
        }
        curl_close($ch);

        return $rtn;
    }

    /**
     * ============================================================
     * 发送验证码短信 (对外接口)
     * ============================================================
     *
     * 【功能说明】
     * 调用阿里云短信 API 发送验证码
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
     * @param string $text      短信内容 (阿里云模板方式不使用)
     * @return array ['code'=>1,'msg'=>'ok'] 成功 / ['code'=>101,'msg'=>'错误信息']
     */
    public function submit($phone,$code,$type_flag,$type_des,$text)
    {
        if(empty($phone) || empty($code) || empty($type_flag)){
            return ['code'=>101,'msg'=>'参数错误'];
        }

        $appid = $GLOBALS['config']['sms']['aliyun']['appid'];
        $appkey = $GLOBALS['config']['sms']['aliyun']['appkey'];
        $sign = $GLOBALS['config']['sms']['sign'];
        $security = false;
        $tpl = $GLOBALS['config']['sms']['tpl_code_'.$type_flag];

        $params=[];
        $params['PhoneNumbers'] = $phone;
        $params['SignName'] = $sign;
        $params['TemplateCode'] = $tpl;
        $params['TemplateParam'] = [
            'code'=>$code,
        ];

        if( is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }

        try {
            $rsp = $this->request(
                $appid,
                $appkey,
                "dysmsapi.aliyuncs.com",
                array_merge($params, array(
                    "RegionId" => "cn-hangzhou",
                    "Action" => "SendSms",
                    "Version" => "2017-05-25",
                )),
                $security
            );

            if($rsp['Code'] == 'OK'){
                $rsp['result'] = 1;
            }

            if($rsp['result'] ==1){
                return ['code'=>1,'msg'=>'ok'];
            }
            return ['code'=>101,'msg'=>$rsp['Message']];
        }
        catch(\Exception $e) {
            return ['code'=>102,'msg'=>'发生异常请重试'];
        }
    }
}
