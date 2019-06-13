<?php
namespace app\common\model;
use think\Model;

class Screenchart extends Model
{
    // 获取器修改tconfig
    public function getTconfigAttr($value)
    {
        return json_decode($value, true);
    }
    // 获取器修改tdata
    public function getTdataAttr($value)
    {
        return json_decode($value, true);
    }
    // 获取器，修改类型
    public function getTtypeAttr($value)
    {
        if ($value == '自定义视图') return 'customize';
        return strtolower(str_replace('\/', '', $value));
    }
    // 获取器新增图标关系字段
    public function getRelationAttr()
    {
        $arr= $this->tconfig['dataOpt']['source'] ?? '';
        $arr['tname'] = $this->tname ?? '';
        $arr['chartType'] = $this->tconfig['charttype'] ?? '';
        $arr['sdata'] = $this->tdata ?? '';
        $arr['map'] = $this->tconfig['dataOpt']['map'] ?? '';
        $arr['name'] = $this->tconfig['name'] ?? '';
        $arr['returnData'] = true;
        $arr['tid'] = $this->tid;
        $arr['chartid'] = $this->screenid;
        return $arr;
    }
    public function getChartsInfos()
    {
        return $this->where('ttype','api')->field('ttype,tconfig,tname,screenid,tid')->select();
    }
}