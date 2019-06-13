<?php

namespace app\index\Controller;

use think\Db;
use think\Controller;
use think\View;

class My extends Controller
{

    public function index()
    {
        $arr = [
            [
                'name' => "张三",
                'value' =>  5,
                'age' => 50
            ],
            [
                'name' =>"李四",
                'value' => 2,
                'age' => 40
            ],
            [
                'name' =>"赵六",
                'value' => 1,
                'age' => 19
            ],
            [
                'name' =>"孙七",
                'value' => 1,
                'age' => 27
            ],
            [
                'name' => "王五",
                'value' =>1,
                'age' => 37
            ]
            ];
            // dump(json_encode($arr,JSON_UNESCAPED_UNICODE));
            return $arr;
    }

    
    //添加API
    public function addapi()
    {
      
        $put = file_get_contents('php://input');
        //$put = '{"dataname":"xiaoni","cid":3}';
        //转换成数组
        if (empty($put)) {
            return get_status(1, '请添加相应的数据' , 4003);
         }
         $post = json_decode($put, 1);
         //判断是否已存在
         $res = Db::name('datament')->where($post)->select();
         //不存在执行插入操作
         if (!$res) {
             $post['createtime'] = date('Y-m-d H:i:s', time());
             $result = Db::name('datament')->insert($post);
         } else {
             return get_status(2, '数据已存在' , 4001);
         }
         //返回值
         if (empty($result)) {
             return get_status(1, '添加失败',4009);
         } else {
             return get_status(0, '添加成功');
         }
    }

    //添加websocket
    public function addSocket()
    {
        $put = file_get_contents('php://input');
        //$put = '{"dataname":"socket","cid":4,"datatype":7}';
        if (empty($put)) {
            return get_status(1, '请填写相应的数据',4003);
        }
        //转换数组
        $arr = json_decode($put, 1);
        //判断是否已存在
        $res = Db::name('datament')->where($arr)->select();
        //不存在执行插入操作
        if (!$res) {
            $arr['createtime'] = date('Y-m-d H:i:s', time());
            $result = Db::name('datament')->insert($arr);
        } else {
            return get_status(2, '数据已存在',4001);
        }
        //返回值
        if (empty($result)) {
            return get_status(1, '添加失败',4009);
        } else {
            return get_status(0, '添加成功');
        }
    }

    /**
     * 生成token
     */
    public function generateToken()
    {
        //获取用户token
        $arr = get_all_header();
        if(isset($arr['token'])){
            $userToken = $arr['token'];    
        }else {
            $data = get_status(1,'非法用户访问',10001);
            return $data;
        }

        //设定唯一登录标识
        $rand = mt_rand(10000,99999);
        //设置token
        $token = md5($userToken.time().$rand);
        return $token;
    }
    
}  
