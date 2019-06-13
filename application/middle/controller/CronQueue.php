<?php
namespace app\middle\controller;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use app\middle\controller\Api;

class CronQueue
{
    // 可视化所有api
    protected $openvApisRedisKey = '';

    public function __construct()
    {
        $redisConf = config('redisConfig.redis1');
        // 设置api存储的redis键名 
        $this->openvApisRedisKey = $redisConf['keys']['openvApisRedisKey'] ?? '2019openvApisRedisKey';
    }
    // 发送队列接口
    public function index()
    {
        $rabbitMqConf = config('rabbitMqConfig.rabbitmq1');
        $connection = new AMQPStreamConnection($rabbitMqConf['hostname'], $rabbitMqConf['hostport'], $rabbitMqConf['username'], $rabbitMqConf['password']);
        $channel = $connection->channel();
        //发送方其实不需要设置队列， 不过对于持久化有关，建议执行该行
        $channel->queue_declare('hello', false, false, false, false);
        $api = new Api();
        $apis = $api->getChartApiUrls();
        dump($apis);die;
        $msg = new AMQPMessage($apis);
	    $channel->basic_publish($msg, '', 'hello');
    }
}