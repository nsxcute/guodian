<?php
namespace app\middle\controller;
use app\middle\model\Screenchart;
use app\middle\model\Redis;
//外部接口
class Api
{
    // 可视化所有api
    protected $openvApisRedisKey = '';

    public function __construct()
    {
        $redisConf = config('redisConfig.redis1');
        // 设置api存储的redis键名
        $this->openvApisRedisKey = $redisConf['keys']['openvApisRedisKey'] ?? '2019openvApisRedisKey';
    }
    public function index()
    {
        return $this->setChartApiUrls();
    }
    // 查找是api图表的的链接，并将链接写入redis
    public function setChartApiUrls(Screenchart $screenchart, Redis $redis)
    {
        $chartsInfos =  $screenchart->getChartsInfos();
        $tconfig = array_column($chartsInfos, 'tconfig');
        $apis = array_filter($tconfig, array($this, 'getApi'));
        foreach ($apis as &$value) {
            $value = json_decode($value, true);
            $value = $value['dataOpt']['source']['apiURL'] ?? '';
        }
        $apis = json_encode($apis);
        $redis->setkeysValue($this->openvApisRedisKey, $apis);
        // $values = $this->redis->get($this->openvApisRedisKey);
        // dump($values);die;
    }
    // protected function 
    protected function getApi($tconfig)
    {
        return json_decode($tconfig, true);
    }
    // protected function findChartsInfos()
    // {
    //     // 图标类型:{0:static,1:excel/csv,2:api,3:sql,4:websocket,5:'自定义视图'}
    //     return Db::name('screenchart')->where('ttype','api')->select();
    // }
    // 删除redis键
    public function deleteRedisKey($key)
    {
        return $this->redis->delete($key);
    }
}