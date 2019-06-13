<?php
namespace app\middle\model;
use think\Model;

class Screenchart extends Model
{
    public function getChartsInfos()
    {
        return $this->where('ttype','api')->field('ttype,tconfig,tname,screenid,tid')->select();
    }
}