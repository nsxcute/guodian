<?php

namespace app\index\model;


use think\Db;
use think\Model;

class User extends Model
{
    //测试 获取用户信息
    public function users()
    {
        $users = Db::name('user')->select();
        if($users){
            return $users;
        }else{
            return false;
        }
    }

    //获取所有的权限
    public function permission()
    {
        $permission = Db::name('permission')->select();
        if($permission){
            return $permission;
        }else{
            return false;
        }
    }

    //获取角色的名字 $rid 角色ID
    public function getRolename($rid)
    {
        $result = Db::name('role')->where(['rid' =>$rid])->find();
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    //获取角色ID $uid 用户ID
    public function getRole($uid)
    {
        $result = Db::name('user_role')->where(['uid' => $uid])->find();
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    //获取登录用户信息 $uid 用户ID
    public function getUser($uid)
    {
        $result = Db::name('user')->where(['uid' => $uid])->find();
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    //获取权限ID  $rid 角色ID 
    public function getPid($rid)
    {
        $result = Db::name('role_permission')->where(['rid' => $rid])->find();
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    //获取权限列表 $pid 权限ID  字符串
    public function getPermission($pid)
    {
        $result = Db::name('permission')->order('pid', 'asc')->select($pid);
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    //删除用户 $table 表名  $uid 用户ID
    public function del($table , $uid)
    {
        if($uid == 1) {
            return false;
        }
        $result = Db::name($table)->delete($uid);

        if($result) {
            return $result;
        }else {
            return false;
        }
    }


    //修改用户 $table 表名  $data 数据信息  $uid 用户ID
    public  function userUpdate($table, $uid ,$data )
    {
        $result = Db::name($table)->where(['uid' => $uid])->update($data);

        if($result) {
            return $result;
        }else {
            return false;
        }

    }

    //增加用户 $table 表名  $data 数据信息
    public function userAdd($table , $data)
    {
        $result = Db::name($table)->insert($data);
        if($result) {
            return $result;
        }else {
            return false;
        }
    }

    //查询信息 $table 表名 ， $where 条件 数组or字符串
    public function getMessg($table,$where = null,$id = null , $page = "1,99999")
    {
//        dump($page);
        $result = Db::name($table)->where($where)->page($page)->select($id);
        if($result) {
            return $result;
        }else {
            return false;
        }
    } 

    //查询信息 $table 表名 ， $where 条件 数组or字符串
    public function updateMessg($table,$where,$data)
    {
        $result = Db::name($table)->where($where)->update($data);
        if($result) {
            return $result;
        }else {
            return false;
        }
    } 

    //删除信息 $table 表名 ， $where 条件 数组or字符串
    public function deleteMessg($table,$where)
    {
        
        $result = Db::name($table)->where($where)->delete();
        if($result) {
            return $result;
        }else {
            return false;
        }
    } 

    //查询列信息 $table 表名 ， $where 条件 数组or字符串
    public function getColumn($table,$where = null , $column = null)
    {
        $result = Db::name($table)->where($where)->value($column);
        if($result) {
            return $result;
        }else {
            return false;
        }
    }

    //SQL查询
    public function sqlExec($sql)
    {
        $result = Db::query($sql);
        if($result) {
            return $result;
        }else {
            return false;
        }
    }


    //增加用户 return 自增ID
    public function messageAdd($table,$data)
    {
        $result = Db::name($table)->insert($data);
        $userId = Db::name($table)->getLastInsID();
        if($result && $userId) {
            return $userId;
        }else {
            return false;
        }
    }

    //查询字段 table 表名 where 条件 field 字段名
    public function getField($table,$where = null ,$field, $id = null ,$page = "0,99999" , $order = null )
    {

        $result = Db::name($table)->where($where)->field($field)->page($page)->order($order)->select($id);
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    //模糊查询 table 表名  field 字段名(str) value条件(str)
    public function messageLike($table , $field , $value)
    {
        $result = Db::name($table)->where($field  , 'like' , '%'.$value.'%')->select();
        return $this->msgReturn($result);
    }

    //返回数据 result 数据
    protected function msgReturn($result)
    {
        if($result) {
            return $result;
        }else {
            return false;
        }
    }


    //计算总数
    public function  countNember($table , $id)
    {
        $result = Db::name($table)->count($id);
        if($result) {
            return $result;
        }else {
            return false;
        }
    }


}