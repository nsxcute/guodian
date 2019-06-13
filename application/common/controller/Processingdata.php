<?php
/** 
 * 通用数据处理类
 * 将各种类型的数据转换为前端需要的数据
 */

namespace app\common\controller;
use app\common\model\Screenchart;
use app\middle\model\RedisModel;
use app\middle\controller\Curl;
use app\index\controller\Chart;
use think\Db;

class Processingdata
{
    // 图表数据
    public $chartData = [];
    // 图标数据处理类
    public $chartModel;

    public function __construct()
    {
        $this->redis = new RedisModel();
        $this->chartModel = new Chart();
    }
    public function index($where)
    {
        $screenchart = new Screenchart();
        $chartInfo = $screenchart->where($where)->select();

        foreach ($chartInfo as $value) {
            $func = 'get'.$value['ttype'];
            $chart = $value['relation'];
            call_user_func_array(array($this, $func), array($chart));
        }
        return $this->chartData;
        // $this->printChartData();
    }
    protected function setChartData($chart, $data)
    {
        // 未传数据
        if (!$data)  $this->chartData[$chart['tname']] = [$chart['name'].'未找到数据'];
        // 映射数据并赋值
        $this->chartData[$chart['tname']] = $this->chartModel->index($chart['chartType'], $data) ?: $chart['name'].'数据映射失败';
    }
    public function getapi($chart)
    {
        // 取 redis数据
        $result = json_decode($this->redis->getkeysString(getRedisKeyByApi($chart['tid'])), true);
        // redis无数据（可能如：新建api图表)
        if (!$result) {
            $curl = new Curl();
            $result = json_decode($curl->curlApiGet($chart['tid']), true);
            $this->redis->setkeysString(getRedisKeyByApi($chart['tid']), $result);
        }
        // 设置 chartdata 属性值
        $this->setChartData($chart, $result);
    }
    public function getstatic($chart)
    {
        $result =  $chart['sdata'];
        $this->chartData[$chart['tname']] = $this->chartModel->index($chart['chartType'], $result);
    }
    public function getexcelcsv($chart)
    {
        // $data = $this->getDatament($input['selectedId']);
        // $this->chartData[]
    }
    public function getsql()
    {

    }
    public function getwebsocket()
    {

    }
    public function getcustomize()
    {

    }
}