<?php
namespace aliyun\alipay;

abstract class method
{
    protected $param;
    protected $public_key;
    protected $private_key;
    protected $endpoint = 'https://openapi.alipay.com/gateway.do';

    public function __construct($config)
    {
        $this->validataConfig($config);
    }
    public function validataConfig($config)
    {
        $param = [
            'charset'   => 'UTF-8',
            'version'   => '1.0',
            'sign_type' => 'RSA2',
        ];
        if (isset($config['app_id']) && $config['app_id'] !== '') {
            $param['app_id'] = $config['app_id'];
        } else {
            throw new \Exception('未配置app_id');
        }
        if (isset($config['public_key']) && $config['public_key'] !== '') {
            $this->public_key = $config['public_key'];
        } else {
            throw new \Exception('未配置公钥');
        }
        if (isset($config['private_key']) && $config['private_key'] !== '') {
            $this->private_key = $config['private_key'];
        } else {
            throw new \Exception('未配置应用私钥');
        }
//        if (isset($config['app_cert_sn']) && is_file($config['app_cert_sn'])) {
//            $param['app_cert_sn'] = $this->getCertSN($config['app_cert_sn']);
//        } else {
//            throw new \Exception('未配置应用公钥证书');
//        }
//        if (isset($config['alipay_root_cert_sn']) && is_file($config['alipay_root_cert_sn'])) {
//            $param['alipay_root_cert_sn'] = $this->getRootCertSN($config['alipay_root_cert_sn']);
//        } else {
//            throw new \Exception('未配置支付宝根证书');
//        }
        if (isset($config['notify_url']) && $config['notify_url'] !== '') {
            $param['notify_url'] = $config['notify_url'];
        }
        if (isset($config['return_url']) && $config['return_url'] !== '') {
            $param['return_url'] = $config['return_url'];
        }
        $this->param = $param;
    }
    abstract public function send();
    public function sign()
    {
        $res = $this->getSignContent($this->param);
        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
        wordwrap($this->private_key, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        $sign = '';
        openssl_sign($res, $sign, $key, 'SHA256');
        return base64_encode($sign);
    }
    public function getSignContent($param)
    {
        ksort($param);
        $sign = [];
        foreach ($param as $k => $v) {
            $sign[] = $k . '=' . $v;
        }
        return implode('&', $sign);
    }
    public function getCertSN($path)
    {
        $cert = file_get_contents($path);
        if ($cert === false) {
            throw new \Exception('应用证书有误');
        }
        openssl_x509_read($cert);
        $data = openssl_x509_parse($cert);
        if (empty($data)) {
            throw new \Exception('应用证书有误');
        }
        $issuer = [];
        foreach ($data['issuer'] as $k => $v) {
            $issuer[] = $k . '=' . $v;
        }
        $issuer = implode(',', array_reverse($issuer));
        return md5($issuer . $data['serialNumber']);
    }
    public function getRootCertSN($path)
    {
        $cert = file_get_contents($path);
        if ($cert === false) {
            throw new \Exception('支付宝根证书有误');
        }
        $end = '-----END CERTIFICATE-----';
        $arr = explode($end, $cert);
        $md5 = [];
        foreach ($arr as $value) {
            if (!empty(trim($value))) {
                $_cert = $value . $end;
                openssl_x509_read($_cert);
                $data = openssl_x509_parse($_cert);
                if (in_array($data['signatureTypeSN'], ['RSA-SHA256', 'RSA-SHA1'])) {
                    $issuer = [];
                    foreach ($data['issuer'] as $k => $v) {
                        $issuer[] = $k . '=' . $v;
                    }
                    $md5[] = md5(implode(',', array_reverse($issuer)) . strpos($data['serialNumber'], '0x') === 0 ? $this->bchexdec($data['serialNumber']) : $data['serialNumber']);
                }
            }
        }
        return implode('_', $md5);
    }
    public function bchexdec($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; $i++) {
            $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
        }
        return $dec;
    }
}
