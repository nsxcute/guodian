<?php
namespace app\index\Controller;

use think\Db;
use think\Controller;

/**
 * 改类用于处理 Unity 的上传 查询
 */

class Unity extends Controller 
{
    //上传文件
    public function uploadUnity()
    {
        //获取文件信息
        $fileinfo = $_FILES['file'];
        //判断文件是否存在
        if(empty($fileinfo)){
            return get_status(1,NULL);
        }
        //定义文件存储路径
        $path = ROOT_PATH . 'public' . DS . 'unity'.DS;
        //判断路径是否存在
        if (!file_exists($path)) {
            mkdir($path , 0755);
        }
        //大小 0不限止
        $maxsize = 0;
        //判断错误号
        if($fileinfo['error'] > 0){
            switch($fileinfo['error']){
                case 1:$error="上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值";break;
                case 2:$error="上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值";break;
                case 3:$error="文件只有部分被上传。";break;
                case 4:$error="没有文件被上传。";break;
                case 6:$error="找不到临时文件夹";break;
                case 7:$error="文件写入失败";break;
                default:$error="未知错误，请稍后再试...";
            }
            return get_status(1,$error,000);
        }
        //取出文件后缀后缀
        $file = pathinfo($fileinfo['name']);
        //获取版本号版本号
        $i = $this->getVersion($file['filename']);
        //生成文件名.同名加版本号
        do{
            $i++; //版本号自增
            $version = $i; 
            $newname = $file['filename'].'-'.$i.".".$file['extension']; //定义完整文件名
        }while(file_exists($path.$newname));
       
       
        //判断是否上传成功
        if(is_uploaded_file($fileinfo['tmp_name'])){ //判断是否是文件
            if(move_uploaded_file($fileinfo['tmp_name'],$path.$newname)){ //移动文件
                //设置文件以public目录下为根目录地址
                $publicPaht = DS . 'unity'. DS . $newname;
                //将文件信息保存
                $data = [
                    'name' => $file['filename'],
                    'filename' => $newname,
                    'path' => $publicPaht,
                    'version' => $version,
                    'createtime' => time(),
                ];
                //查询数据库内是否存在name
                $res =  Db::name('unity')->where('name',$file['filename'])->find();
                if($res) {
                    Db::name('unity')->where('name', $file['filename'])->update($data);
                }else {
                    //将信息存入数据库
                    Db::name('unity')->insert($data);
                }
            }else{
                return get_status(1,'文件移动失败',000);
            }
        
        }else{
            return get_status(1,'未知错误！请重试',000);
        }
    }
    //获取文件版本号
    protected function getVersion($name)
    {
        //查询name
        $version = Db::name('unity')->where('name',$name)->order('version DESC')->find();
        if($version) {
            $versionNum = $version['version'];
        }else {
            $versionNum = 0;
        }
    }
    //获取全部文件名
    public function getFileName()
    {
        $result = Db::name('unity')->field('path,version')->select();
        if($result) {
            $total = count($result);
            $data = [
                "Total" => $total,
                "Data" => $result,
            ];
            return get_status(0,$data);
        }else {
            return get_status(0,[]);
        }

    }

   
}