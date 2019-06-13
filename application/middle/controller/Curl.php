<?php
namespace app\middle\controller;
use GuzzleHttp\Client;
/** 
 * curl类
 * @author 李福龙
 */

class Curl
{
    // curl实例
    protected $client = '';
    // 请求过期时间
    protected $expireTime = 3;


    public function __construct()
    {
        $this->expireTime = config('curlConfig.expiredTime');
        $this->client = new Client();
    }
    // 发送请求并写入redis
    public function curlApiGet(string $api)
    {
        // dump($api);
        $response = $this->client->request('GET', $api, ['timeout'=> $this->expireTime, 'http_errors'=>false]);
        // echo $response->getStatusCode(); # 200
        // echo $response->getHeaderLine('content-type');
        // echo $response->getBody();
        if ($response->getStatusCode() != 200) \think\Log::error($api.'状态码错误:'.$response->getStatusCode()."\n");
        return (string)$response->getBody();
    }
}