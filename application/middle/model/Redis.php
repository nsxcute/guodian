<?php
namespace app\middle\model;

class RedisModel
{
    public $redis;

    public function __construct()
    {
        $this->redis = new \Redis();
        $redisConf = config('redisConfig.redis1');
        $this->redis->pconnect($redisConf['hostname'], $redisConf['hostport']);
    }
    // 给键名赋值
    public function setkeysString($key, $value)
    {
        $this->redis->set($key, $value);
    }
    // 获取string类型键的值
    public function getkeysString($key)
    {
        return $this->redis->get($key);
    }
    // 删除键
    public function deleteRedisKey($key)
    {
        return $this->redis->delete($key);
    }

















    public function index()
    {
        return $this->setChartApiUrls();
    }
    // 查找是api图表的的链接，并将链接写入redis
    public function setChartApiUrls()
    {
        $chartsInfos =  $this->findChartsInfos();
        $tconfig = array_column($chartsInfos, 'tconfig');
        $apis = array_filter($tconfig, array($this, 'getApi'));
        foreach ($apis as &$value) {
            $value = json_decode($value, true);
            $value = $value['dataOpt']['source']['apiURL'] ?? '';
        }
        $apis = json_encode($apis);
        $this->redis->set($this->openvApisRedisKey, $apis);
        // $values = $this->redis->get($this->openvApisRedisKey);
        // dump($values);die;
    }
    protected function getApi($tconfig)
    {
        return json_decode($tconfig, true);
    }
    protected function findChartsInfos()
    {
        // 图标类型:{0:static,1:excel/csv,2:api,3:sql,4:websocket,5:'自定义视图'}
        return Db::name('screenchart')->where('ttype','api')->select();
    }
}