<?php
namespace app\middle\controller;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use app\middle\controller\Api;
use app\middle\model\RedisModel;
use app\middle\controller\Curl;
/** 
 * 接收队列，并处理数据
 * @author 李福龙
 */

class Recieve
{
    // curl实例
    protected $curl;
    // api的redis键，（不能重复）
    protected $apiRedisKey = '';
    // 可视化所有api
    protected $openvApisRedisKey = '';

    public function __construct()
    {
        $redisConf = config('redisConfig.redis1');
        // 设置api存储的redis键名 
        $this->openvApisRedisKey = $redisConf['keys']['openvApisRedisKey'] ?? '2019openvApisRedisKey';
        $this->redis = new RedisModel();
        // $this->apiRedisKey = $redisConf['keys']['apiRedisKey'] ?? '2019apiRedisKey';
        $this->curl = new Curl();
    }
    // 发送队列接口
    public function index()
    {
        $rabbitMqConf = config('rabbitMqConfig.rabbitmq1');
        $connection = new AMQPStreamConnection($rabbitMqConf['hostname'], $rabbitMqConf['hostport'], $rabbitMqConf['username'], $rabbitMqConf['password']);
        $channel = $connection->channel();
        $openvApisRabbitKey = $rabbitMqConf['keys']['openvApisRabbitKey'] ?? '2019openvApisRabbitKey';
        $channel->queue_declare($openvApisRabbitKey, false, false, false, false);
        $channel->basic_consume($openvApisRabbitKey, '', false, true, false, false, function($msg){
            $this->getApiInfo($msg->body);
        });
        while(count($channel->callbacks)) {
            $channel->wait();
        }
        $channel->close();
        $connection->close();
    }
    // apis是json对象，遍历获取api
    protected function getApiInfo($apis)
    {
        $apis = json_decode($apis, true);
        // $apis = array_filter($apis);
        // dump($apis);die;
        foreach($apis as $api => $info) {
            if (filter_var($api, FILTER_VALIDATE_URL)) {
                $this->curlApi($api, $info);
            } else {
                echo $api." 不是链接 \n";
                \think\Log::error($api." 不是链接 \n");
            } 
        }
    }
    // 发送请求并写入redis
    public function curlApi($api, $info)
    {
        $data = $this->curl->curlApiGet($api);
        echo $api."\n";
        // dump($data);
        foreach($info['tid'] as $tid){
            $this->redis->setkeysString(getRedisKeyByApi($tid), $data);
        }
        // $this->redis->setkeysString(getRedisKeyByApi($api), $data);
        // $this->redis->zsetKeyString(getRedisKeyByApi($api['screenid']), $api['tid'], (string)$response->getBody());
        echo getRedisKeyByApi($api).'写入成功:'."\n";
        // echo getRedisKeyByApi($api['screenid']).'写入成功:'."\n";
        // echo $api.'<br>';
    }
}