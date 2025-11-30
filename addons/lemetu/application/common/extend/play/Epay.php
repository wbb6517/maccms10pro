<?php
namespace app\common\extend\pay;

use EpayExtend\EpayNotify;
use EpayExtend\EpaySubmit;
use think\Exception;

class Epay {

    public $name = '码支付1';
    public $ver = '1.0';

    private $config;
    public function __construct() {

    }

    public function submit($user,$order,$param){
        $pay_type = 1;
        if(!empty($param['paytype'])){
            $pay_type = intval($param['paytype']);
        }
        
        /**************************请求参数**************************/
        $notify_url = $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . '/api.php/v1.payment/notify/pay_type/epay';
        //需http://格式的完整路径，不能加?id=123这类自定义参数        //页面跳转同步通知页面路径
        $return_url = $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . '/index.php/payment/notify/pay_type/epay';
        //需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/        //商户订单号
        $out_trade_no = $order['order_code'];
        //商户网站订单系统中唯一订单号，必填
        //支付方式
        $pay_type = $GLOBALS['config']['pay']['epay']['type'];
        $pay_type = explode(',', $pay_type);
        $pay_type = $pay_type[0];

        // if ($pay_type == 1){
        //     $type = 'wxpay';
        // }elseif ($pay_type == 2){
        //     $type = 'alipay';
        // }elseif($pay_type == 3){
        //     $type = 'qqpay';
        // }else{
        //     throw new Exception('不支持的支付方式');
        // }
        if ($pay_type == 1){
           
        }elseif ($pay_type == 2){
            
        }elseif($pay_type == 3){
            
        }else{
            throw new Exception('不支持的支付方式');
        }
        //商品名称
        $name = '积分充值（UID：'.$user['user_id'].'）';
        //付款金额
        $money = sprintf("%.2f",$order['order_price']);;
        //站点名称
        $sitename = '318hb视频';
        //必填        //订单描述
        /************************************************************/
        $alipay_config['partner'] = $GLOBALS['config']['pay']['epay']['appid'];
        $alipay_config['key'] = $GLOBALS['config']['pay']['epay']['appkey'];
        $alipay_config['sign_type']    = strtoupper('MD5');
        $alipay_config['input_charset']= strtolower('utf-8');
        $alipay_config['transport'] = $GLOBALS['http_type'];
        $alipay_config['apiurl'] = $GLOBALS['config']['pay']['epay']['apiurl'];
//构造要请求的参数数组，无需改动
        $parameter = array(
            "id" => trim($GLOBALS['config']['pay']['epay']['appid']),
            "type" => $pay_type,
            "notify_url"	=> $notify_url,
            "return_url"	=> $return_url,
            "pay_id"	=> $out_trade_no,
            "name"	=> $name,
            "price"	=> $money,
            "sitename"	=> $sitename
        );


        //建立请求
        $alipaySubmit = new EpaySubmit($alipay_config);

        $html_text = $alipaySubmit->buildRequestForm($parameter);
        echo $html_text;
    }

    public function notify(){
        $param = $_POST;
        // $post['pay_id'] 这是付款人的唯一身份标识或订单ID
        // $post['pay_no'] 这是流水号 没有则表示没有付款成功 流水号不同则为不同订单
        // $post['money'] 这是付款金额
        // $post['param'] 这是自定义的参数

        unset($param['/payment/notify/pay_type/epay']);
        unset($param['paytype']);

        ksort($param); //排序post参数
        reset($param); //内部指针指向数组中的第一个元素
        $sign = '';
        foreach ($param as $key => $val) {
            if ($val == '') continue; //跳过空值
            if ($key != 'sign') { //跳过sign
                $sign .= "$key=$val&"; //拼接为url参数形式
            }
        }

        $GLOBALS['config']['pay'] = config('maccms.pay');

        if (!$param['pay_no'] || md5(substr($sign,0,-1).trim( $GLOBALS['config']['pay']['epay']['appkey'])) != $param['sign']) {
            echo 'fail';
        }
        else{
            $res = model('Order')->notify($param['pay_id'],'epay');
            if($res['code'] >1){
                echo 'fail2';
            }
            else {
                echo 'success';
            }
        }
    }


}