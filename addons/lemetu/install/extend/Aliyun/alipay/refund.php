<?php
namespace alipay\method;

use Ws\Http\Request;

class refund extends method
{
    protected $method = 'alipay.trade.refund';

    public function setBizContent($out_trade_no, $refund_amount, $out_request_no, $biz_content = [])
    {
        $biz_content['out_trade_no']   = $out_trade_no;
        $biz_content['refund_amount']  = $refund_amount;
        $biz_content['out_request_no'] = $out_request_no;
        $this->param['biz_content']    = json_encode($biz_content);
        return $this;
    }
    public function send()
    {
        $this->param['method']    = $this->method;
        $this->param['timestamp'] = date('Y-m-d H:i:s');
        $this->param['sign']      = $this->sign();

        $http = Request::create();
        $resp = $http->post($this->endpoint, [], $this->param);
        $body = json_decode(iconv('GBK', 'UTF-8', $resp->raw_body))->alipay_trade_refund_response;
        if ($body->code === '10000' && $body->msg === 'Success') {
            return true;
        }
        return $body->msg;
    }
}
