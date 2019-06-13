<?php
namespace app\index\Controller;

use think\Db;
use think\Controller;
use think\Request;

//用来处理上传的icon
class Icon extends Controller 
{
    public function index() 
    {
        echo 1;
    }

    //上传icon
    public function uploadIcon() 
    {
        //接收post来的数据
		$post = input('post.');
		//执行上传
		$file = request()->file('file0');

        if(empty($file)){
           return  get_status(1,'图片为空',5002);
        }
		//定义上传路径
		$info = $file->validate(['size'=>1567118])->move(ROOT_PATH . 'public' . DS . 'uploads'. DS . 'Icon'. DS);
		//获取路径
		$imgSrcWin = DS . 'uploads'. DS . 'Icon' . DS . $info->getSaveName();

		//设置存储路径
		$data['iconpath'] = $imgSrcWin;
		//设置更改语句
		$data = Db::name('icon')->insert($data);
		//判断是否更改成功
		if($data == null){
			return  get_status(1,"上传失败",5004);
		}else{
			//查询所有icon地址
            $iconlist  = Db::name('icon')->select();
            return get_status(0,$iconlist);
		}
    }

    //查询icon
    public function iconList()
    {
        //查询所有icon地址
        $iconlist  = Db::name('icon')->select();
        return get_status(0,$iconlist);
    }

    //删除icon
    public function iconDel() 
    {
        $input = input('delete.');
        //判断input
        if(empty($input['upid'])) {
            return get_status(1,'参数不能为空' ,5002);
        }
       
        //删除指定icon
        $del = Db::name('icon')->delete($input['upid']);
        if($del) {
            return  get_status(0,"删除成功");
        }else {
            return  get_status(1,"删除失败",5006);
        }
    }

}