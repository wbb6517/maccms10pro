<?php
namespace aliyun\alipay;

class app extends method
{
    protected $method = 'alipay.trade.app.pay';

    public function setBizContent($total_amount, $subject, $out_trade_no, $biz_content = [])
    {
        $biz_content['total_amount'] = $total_amount;
        $biz_content['subject']      = $subject;
        $biz_content['out_trade_no'] = $out_trade_no;
        $biz_content['product_code'] = 'QUICK_MSECURITY_PAY';
        $this->param['biz_content']  = json_encode($biz_content);
        return $this;
    }
    public function send()
    {
        $this->param['method']    = $this->method;
        $this->param['timestamp'] = date('Y-m-d H:i:s');
        $this->param['sign']      = $this->sign();
        return http_build_query($this->param);
    }
}
