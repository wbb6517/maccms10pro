<?php
namespace Wechat;

class Wxpay
{
    public function create($total_fee,$order_code,$body,$notify)
    {
//        $total_fee = $order['order_price'];
        $data = array();
        $data['appid'] =  trim($GLOBALS['config']['pay']['weixin']['appid']);//公众号
        $data['mch_id'] =  trim($GLOBALS['config']['pay']['weixin']['mchid']);//商户号
        $data['nonce_str'] =  mac_get_rndstr();//随机字符串
        $data['body'] =  $body;//商品描述
        $data['fee_type'] =  'CNY';//标价币种
        $data['out_trade_no'] = $order_code;//商户订单号
        $data['total_fee'] = $total_fee * 100;//金额，单位分
        $data['spbill_create_ip'] =  request()->ip();//终端IP
        $data['notify_url'] = $notify;
        $data['trade_type'] =  'APP';//交易类型 JSAPI，NATIVE，APP
        $data['product_id'] = '1';//商品ID
        //$data['openid'] =  '';//用户标识 trade_type=JSAPI时（即公众号支付），此参数必传
        $data['sign'] =  $this->makeSign($data);
        //获取付款二维码
        $data_xml = mac_array2xml($data);
        $res = mac_curl_post('https://api.mch.weixin.qq.com/pay/unifiedorder', $data_xml);
        $res = mac_xml2array($res);

        if($res['return_code']=='SUCCESS' && $res['result_code']=='SUCCESS'){
            //返回付款信息
//            $res = [
//                'user_id'=>$user['user_id'],
//                'total_fee'=>$total_fee,
//                'out_trade_no'=>$data['out_trade_no'],
//                'code_url'=>$res['code_url']
//            ];

            //echo '<img src=http://paysdk.weixin.qq.com/example/qrcode.php?data='.urlencode($res['code_url']).'/>';
            return $this->makeAppSign($res['prepay_id']);
        }
        //echo '获取微信二维码失败,'.$res['return_msg'];
        return false;
    }
    public function makeSign($data){
        //获取微信支付秘钥
        $key = trim($GLOBALS['config']['pay']['weixin']['appkey']);
        // 去空
        $data=array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a=http_build_query($data);
        $string_a=urldecode($string_a);
        //签名步骤二：在string后加入KEY
        $string_sign_temp=$string_a."&key=".$key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result=strtoupper($sign);
        return $result;
    }
    public function makeAppSign($prepayId)
    {
        //获取微信支付秘钥
//        $key = trim($GLOBALS['config']['pay']['weixin']['appkey']);
        $params = [
            'appid' => trim($GLOBALS['config']['pay']['weixin']['appid']),
            'partnerid' => trim($GLOBALS['config']['pay']['weixin']['mchid']),
            'prepayid' => $prepayId,
            'noncestr' => uniqid(),
            'timestamp' => time(),
            'package' => 'Sign=WXPay',
        ];
        $params['sign'] = $this->makeSign($params);
//        ksort($temp_params);
//
//        $temp_params['key'] = $key;
//        $params = $temp_params;
//
//        $sign = strtoupper(call_user_func_array('md5', [urldecode(http_build_query($temp_params))]));
//        $params['sign'] = $sign;
//        unset($params['key']);

        return $params;
    }
}
