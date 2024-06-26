<?php

namespace Hzdad\Wbsms;
use GuzzleHttp\Psr7;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Huawei
{
    protected $config;
    protected $status;

    public function __construct($config=[])
    {
        if (empty($config['appKey']) || empty($config['appSecret']) || empty($config['sender'])) {
            $this->status = false;
        } else {
            $this->status = true;
            $this->config = $config;
        }
    }

    public function send($action,$mobile, $params)
    {

        if ($this->status) {
            $client = new Client();
            $conf = $this->config['actions'][$action];
            try {
                $response = $client->request('POST', $this->config['url'].'/sms/batchSendSms/v1', [
                    'form_params' => [
                        'from' => $conf['sender']??$this->config['sender'],
                        'to' => $mobile,
                        'templateId' => $conf['template_id'],
                        'templateParas' => $params,
                        'statusCallback' => $this->config['statusCallback'],
                        'signature' => $this->config['signature']
                    ],
                    'headers' => [
                        'Authorization' => 'WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
                        'X-WSSE' => $this->buildWsseHeader($this->config['appKey'], $this->config['appSecret'])
                    ],
                    'verify' => false //为防止因HTTPS证书认证失败造成API调用失败，需要先忽略证书信任问题
                ]);
                $result = $response->getBody();
                $result = json_decode($result,true);
            } catch (RequestException $e) {
                $result = $e->getResponse()->getBody();
                $result = json_decode($result,true);
            }
            if ($result['code'] == '000000') {
                $data['code'] = 200;
                $data['msg'] = '发送成功';
            } else {
                $data['code'] = 100;
                $data['msg'] = '发送失败，'.$result['description'];
            }
        } else {
            $data['code'] = 100;
            $data['msg'] = '配置有误，请检查';
        }
        return $data;
    }

    /**
     * 构造X-WSSE参数值
     * @param string $appKey
     * @param string $appSecret
     * @return string
     */
    public function buildWsseHeader(string $appKey, string $appSecret)
    {
        $now = date('Y-m-d\TH:i:s\Z'); //Created
        $nonce = uniqid(); //Nonce
        $base64 = base64_encode(hash('sha256', ($nonce . $now . $appSecret))); //PasswordDigest
        return sprintf("UsernameToken Username=\"%s\",PasswordDigest=\"%s\",Nonce=\"%s\",Created=\"%s\"", $appKey, $base64, $nonce, $now);
    }
}
