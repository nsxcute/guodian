<?php
namespace app\middle\model;
/** 
 * redis模型
 */

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
        if (!is_string($value)) $value = json_encode($value);
        return $this->redis->set($key, $value);
    }
    // 获取string类型键的值
    public function getkeysString($key)
    {
        return $this->redis->get($key);
    }
    public function zsetKeyString($key, $score, $value)
    {
        return $this->redis->set($key, $score, $value);
    }
    // 删除键
    public function deleteRedisKey($key)
    {
        return $this->redis->delete($key);
    }
}