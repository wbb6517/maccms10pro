<?php
namespace aliyun\alipay;

class notify extends method
{
    public function notify($param, $success = null, $fail = null)
    {
        $res = $this->verify_sign($param);
        if ($res === 0) {
            throw new \Exception('验签失败');
        }
        if (isset($param['trade_status']) && in_array($param['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            if (!is_null($success) && is_callable($success)) {
                call_user_func($success, $param);
            }
        } else {
            if (!is_null($fail) && is_callable($fail)) {
                call_user_func($fail, $param);
            }
        }
        return $this;
    }
    private function verify_sign($param)
    {
        $sign = base64_decode($param['sign']);
        unset($param['sign'], $param['sign_type']);
        ksort($param);
        $key = openssl_pkey_get_public(file_get_contents($this->public_key));
        $res = openssl_verify(urldecode(http_build_query($param)), $sign, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);
        return $res;
    }
    public function send()
    {
        return 'success';
    }
}
