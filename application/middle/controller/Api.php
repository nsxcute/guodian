<?php
namespace app\middle\controller;
use app\middle\model\Screenchart;
use app\middle\model\RedisModel;

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
    public function printApis()
    {
        dump(json_decode($this->getChartApiUrls(), true));
    }
    // 查询api键值
    public function getChartApiUrls()
    {
        $redis = new RedisModel();
        $apis = $redis->getkeysString($this->openvApisRedisKey);
        if (!$apis) {
            $apis = $this->setChartApiUrls();
        }
        return $apis;
    }
    // 查找是api图表的的链接，并将链接写入redis
    public function setChartApiUrls()
    {
        $screenchart = new Screenchart();
        $redis = new RedisModel();
        // 查出 tconfig
        $chartsInfos =  $screenchart->getChartsInfos();
        // 取出 tconfig
        // $tconfig = array_column($chartsInfos, 'tconfig');

        // 以apiUrl为键，赋值 $data
        $data = [];
        foreach ($chartsInfos as $key => $value) {
            $value['tconfig'] = json_decode($value['tconfig'], true);
            $apiUrl = $value['tconfig']['dataOpt']['source']['apiURL'] ?? '';
            if (!$apiUrl) break;
            $data[$apiUrl]['tname'][] = $value['tname'];
            $data[$apiUrl]['screenid'][] = $value['screenid'];
            $data[$apiUrl]['tid'][] = $value['tid'];
            // $value['tconfig'] = json_decode($value['tconfig'], true);
            // $value['tconfig'] = $value['tconfig']['dataOpt']['source']['apiURL'] ?? '';
            // $data[$apiUrl]['api'] = $value['tconfig'];
            unset($data[$apiUrl]['tconfig']);
        }
        // dump($data);die;
        // 序列化并写入redis缓存(string类型)
        $apis = json_encode($data);
        $redis->setkeysString($this->openvApisRedisKey, $apis);
        return $apis;
    }
    public function printChartData()
    {
        $obj = new \app\common\controller\Processingdata();
        $where = ['screenid'=>380];
        $obj->index($where);
        dump($obj->chartData);
    }
}