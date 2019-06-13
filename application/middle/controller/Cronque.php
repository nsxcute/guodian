<?php
namespace app\middle\controller;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use app\middle\controller\Api;

/** 
 * 加入队列
 * @author 李福龙
 */
class Cronque
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
        $openvApisRabbitKey = $rabbitMqConf['keys']['openvApisRabbitKey'] ?? '2019openvApisRabbitKey';
        //发送方其实不需要设置队列， 不过对于持久化有关，建议执行该行
        $channel->queue_declare($openvApisRabbitKey, false, false, false, false);
        $api = new Api();
        $apis = $api->getChartApiUrls();
        // dump($apis);die;
        for ($i=0; $i<12; $i++) {
            $msg = new AMQPMessage($apis);
            $channel->basic_publish($msg, '', $openvApisRabbitKey);
            sleep(5);
        }
    }
}