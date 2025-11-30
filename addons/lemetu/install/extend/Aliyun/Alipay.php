<?php
namespace aliyun;

class Alipay
{
    public static function create($method, $config = [])
    {
        if (empty($config)) {
            $config = config('maccms.alipay');
        }
        $method = '\\aliyun\\alipay\\' . $method;
        if (class_exists($method)) {
            try {
                return new $method($config);
            } catch (\Exception $e) {
                throw $e;
            }
        } else {
            throw new \Exception('支付方法不存在');
        }
    }
}
