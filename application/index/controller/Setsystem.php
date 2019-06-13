<?php
namespace app\index\Controller;
use think\Db;
use think\Controller;
use think\Request;
use think\Session;
use \think\View;
use \tp5er\Backup;
use app\base\controller\Base;



/**
  系统设置
* 
*/
class Setsystem extends Base
{
  //测试
  public function aaa(){
    return $this->fetch('index');
  }

  //系统常规设置修改和插入操作
  public function generalSet()
  {
     //获取数据 
     $put = file_get_contents('php://input');
     //$put = '{"sysname":"ddddd","website":"http://h5.kwcnet.com/","port":"80","publish":0,"logopath":"http://v.kwcnet.com/uploads/logo/20180816/209aa1b7af9a10152446d462f7728a8f.jpg"}';
     //转成数组
     if (empty($put)) {
        return get_status(1,NULL);
     }
     $post = json_decode($put,1);
     //dump($post);
     //配置文件的路径
     //$path = '/home/wwwroot/datav-h5/static/config.json';
     $path = ROOT_PATH.'public/openv/static/config.json';
     //获取配置文件的内容
     //dump($path);
     //die;
     $file = file_get_contents($path);
     //转成数组
     $arr = json_decode($file,1);
    //dump($arr);
     //修改配置文件
     $arr['system'] = $post;
     //dump($arr);
     //转成json字符串
     $jsondata = json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
     //dump($jsondata);
      //die;
     //写入config.json文件
     $data = file_put_contents($path, $jsondata,FILE_USE_INCLUDE_PATH);
     //dump($data);
     //die;
     if(empty($data)){
        return get_status(1,NULL);
     }else{
        return get_status(0,NULL);
     }

  }
  //系统常规设置查询数据列表
  public function generalList()
  {
    //查询数据
    //$path = '/home/wwwroot/datav-h5/static/config.json';
    $path = ROOT_PATH . 'public/openv/static/config.json';
    //获取配置文件的内容
    $file = file_get_contents($path);
    $arr = json_decode($file,1);
    //$result = Db::name('systemset')->select();
    if (empty($file)) {
           return get_status(1,NULL);
      }else{
           return get_status(0,$arr['system']);
      }

  }

  //上传logo
  public function uploadimg()
  {
    //执行上传操作
    $file = request()->file('file0');
    if(empty($file)){
      return get_status(1,NULL);
    }
      //把图片移动到/public/uploads/img/文件下
    $info = $file->validate(['size'=>5242880])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'logo');
    if($info){
        //获取图片的路径
        $newpath =  '/uploads/logo/' .$info->getSaveName();
        $newpath = str_replace('\\','/',$newpath);
    }else{
        // 上传失败获取错误信息
        $error = $file->getError();
    }
    //返回的数据
    if (empty($info)) {
         return get_status(1,$error);
    }else{
         return get_status(0,$newpath);
    }
  }
  //附件设置
  public function attachment()
  {
      $put = file_get_contents('php://input'); 
      //$put = '{"type":"jpg,png,gif","width":120,"height":80,"is_water":1,"transparency":50,"waterpath":"/uplode/water/","position":3}';
      //dump($put);

      if(empty($put)){
        //echo 123;
         $result = Db::name('attachment')->select();
      }else{
        //echo 456;
        $post = json_decode($put,1);
        $update = Db::name('attachment')->where('attid',1)->update($post);
        if(empty($update)){
          return get_status(1,'未做修改' ,5001);
        }else{
          $result = Db::name('attachment')->select();
        }


      }
    
      
      //返回值
      if (empty($result)) {
        return get_status(1,'未做修改',5001);
      }else{
        return get_status(0,$result[0]);
      } 
  }
  //上传附件
  public function upattachment()
  {

    $select = Db::name('attachment')->select(); 
    $path = ROOT_PATH . 'public' . $select[0]['waterpath'];
    //执行上传操作
    $file = request()->file('file0');

    if(empty($file)){
      return get_status(1,"不能为空",5002);
    }
    //把图片移动到/public/uploads/img/文件下
    $filepath = ROOT_PATH . 'public' . DS . 'uploads';
    $info = $file->validate(['size'=>156711800 /*,'ext'=>$select[0]['type']*/])->move($filepath);
    if (!$info) {
      return get_status(1,"上传附件宽高过大",5003);
    }
    //获取图片的宽和高
    list($width, $height) = getimagesize($filepath.'/'.$info->getSaveName());
    
    //判断图片的宽和高大于设置的数 直接返回报错信息
    if ($width > $select[0]['width'] && $height > $select[0]['height']) {
      return get_status(1,"上传附件宽高过大",5003);
    }
    
    if($info){
        //拼接全路径，打开图片资源的时候使用
        $imagepath = $filepath.'/'.$info->getSaveName();
        //路径中正斜线和反斜线的替换
        $imagepath = str_replace('\\','/',$imagepath);
        //拼接缩略图路径
        $data_path = $filepath.'/thumb/'.date('Ymd');
        //递归创建缩略图路径
        if(!file_exists($data_path)){
          mkdir($data_path,0777,true);
        }
        //缩略图路径加文件
        $thumb_path = $data_path.'/'.$info->getFilename();
        //打开图片资源
        $image = \think\Image::open($imagepath); 
        //生成缩略图
        $image->thumb(300,300,\think\Image::THUMB_CENTER)->save($thumb_path); 
        
        if($select[0]['is_water'] == 1){
          //拼接原图加水印路径
          $water_path = $filepath.'/water/'.date('Ymd');
          //递归创建原图加水印路径 
          if (!file_exists($water_path)) {
            mkdir($water_path,0777,true);
          }
          //原图加水印路径加文件
          $waterurl = $water_path.'/'.$info->getFilename();
          //打开图片资源
          $image2 = \think\Image::open($imagepath); 
          //添加水印 并保存到waterurl位置
          $image2->water($path,$select[0]['position'],$select[0]['transparency'])->save($waterurl);
          $thwater_path = $filepath.'/thwater/'.date('Ymd');
          if (!file_exists($thwater_path)) {
            mkdir($thwater_path,0777,true);
          }
          $thwaterurl = $thwater_path.'/'.$info->getFilename();
          // 实例化缩略图对象
          $image1 = \think\Image::open($thumb_path);      
          //路径存入data数组
          $image1->water($path,$select[0]['position'],$select[0]['transparency'])->save($thwaterurl);
          $data['warterthumb'] = '/uploads/thwater/'.date('Ymd').'/'.$info->getFilename();
          $data['waterurl'] = '/uploads/water/'.date('Ymd').'/'.$info->getFilename();
           //数据库严格模式
          $data['thumb'] = '/uploads/thumb/'.date('Ymd').'/'.$info->getFilename();
          $data['url'] = '/uploads/' .$info->getSaveName();;
        }else{
          //获取图片的路径 
          $newpath =  '/uploads/' .$info->getSaveName();
          //保存到data数组
          $data['url'] = $newpath;
          //数据库严格模式
          $data['warterthumb'] ='';
          $data['waterurl'] = '';
          //路径存入data数组
          $data['thumb'] = '/uploads/thumb/'.date('Ymd').'/'.$info->getFilename();
        }        
        
        //存入数据库
        $result = Db::name('upattachment')->insert($data);
          if(empty($result)){
             return get_status(1,'上传附件失败',5004);
        }
        }else{
            // 上传失败获取错误信息
            $error = $file->getError();
        }
      $result = Db::name('upattachment')->select();
            // //返回的数据
    if (empty($info)) {
       return get_status(1,$error);
    }else{
       if(empty($result)){
          return get_status(1,"查询失败",5005);
       }else{
         return ['err'=>0,'data'=>$result];
       }            
    }
  }
  //附件列表
  public function attList()
  {
    $post = input('get.');
    //$get = '{"pages":1,"num":2}';
    //$post = json_decode($get,1);
    $select = Db::name('attachment')->find();
    //判断是否传了_t
    if(isset($post['_t'])) {
      //删除_t _t用于前端请求方便
      unset($post['_t']);
    }
    if(empty($post)){
      if ($select['is_water'] == 1) {
        $select = Db::name('upattachment')->select();
        $result = Db::name('upattachment')->field('upid,warterthumb,url,waterurl')->select();
        $total = count($select);
      }elseif($select['is_water'] == 2){
        $select = Db::name('upattachment')->select();
        $result = Db::name('upattachment')->field('upid,thumb,url,waterurl')->select();
        $total = count($select);
      }  
    }else{
      if ($select['is_water'] == 1) {
        $select = Db::name('upattachment')->select();
        $result = Db::name('upattachment')->field('upid,warterthumb,url,waterurl')->page($post['pages'],$post['num'])->select();
        $total = count($select);
      }elseif($select['is_water'] == 2){
        $select = Db::name('upattachment')->select();
        $result = Db::name('upattachment')->field('upid,thumb,url,waterurl')->page($post['pages'],$post['num'])->select();
        $total = count($select);
      }   
    }
    
    if (empty($result)) {
        return ['err'=>0,'data'=>$result,'total'=>0];
    }else{
        return ['err'=>0,'data'=>$result,'total'=>$total];
    }
  
  }
  //查看大图
  public function showimg()
  {
    //获取前台穿过来的值
    $put = file_get_contents('php://input');
    //转换成数组
    $post = json_decode($put,1);
    //查询附件设置数据
    $select = Db::name('attachment')->select();
    //判断是否开启水印
    if ($select[0]['is_water'] == 1) {
      //返回水印大图
          $result = Db::name('upattachment')->where('upid',$post['upid'])->field('upid,warterurl')->select();
        }elseif($select[0]['is_water'] == 2){
          //返回原图大图
          $result = Db::name('upattachment')->where('upid',$post['upid'])->field('upid,url')->select();
        }
        //返回值
        if (empty($result)) {
           return get_status(1,'查询失败',5005);
        }else{
           return get_status(0,$result);
        }
  }
  //删除附件
  public function attdelete()
  {
    $put = file_get_contents('php://input');
    if (empty($put)) {
      return get_status(1,NULL);
    }
    //$put = '{"upid":[13]}';
    $post = json_decode($put,1);
    if (count($post['upid']) >1 ) {
      $result = Db::name('upattachment')->where('upid','in',$post['upid'])->delete();
    }else{
      $result = Db::name('upattachment')->where('upid',$post['upid'][0])->delete();
    }   
    if (empty($result)) {
           return get_status(1,'删除附件失败',5006);
        }else{
           return get_status(0,$result);
        }
  }
  //安全设置
  public function safe()
  {
     $put = file_get_contents('php://input');
     if (empty($put)) {
       return get_status(1,'添加数据为空',5001);
     }
     //$put = '{"maxerr":3,"intervaltime":5,"terminal":3,"adminlog":1,"login":1}';
     $post = json_decode($put,1);
     $select = Db::name('safety')->select();
     //查看数据库中是否有数据，如果有数据，执行修改操作，如果没有数据，执行插入操作
     if (empty($select)){
        $result = Db::name('safety')->insert($post);
     }else{
        $result = Db::name('safety')->where('said',$select[0]['said'])->update($post);
     }
     //返回值
     if (empty($result)) {
         return get_status(1,'查询失败',5005);
     }else{
         return get_status(0,$result);
     } 

  }
  //安全设置查询数据
  public function safeList()
  {
    //查询数据
    $result = Db::name('safety')->select();
    if (empty($result)) {
         return get_status(1,'查询失败',5005);
    }else{
         return get_status(0,$result);
    }
  }
  //数据库备份
  public function backup()
  {
    $variable = Db::name('variable')->find();
    if ($variable['variable'] == 0) {
      //echo 111;
      $variable = Db::name('variable')->where('id',1)->update(['variable'=>1]);
      $config=array(
        'path' =>ROOT_PATH.'data/',//数据库备份路径
      );
      $db= new Backup($config);//实例化数据库备份类进行条用里面的方法。
      $db->setTimeout(0);
      //$db->setFile(['name'=>'xiao','part'=> 1]);
      $tables = $db->dataList();
      for ($i=0; $i < count($tables); $i++) { 
        $rel = $db->backup($tables[$i]['name'],0);
      }
      $conf = $db->dbconfig;
      $filename = $db->getFile();
      $data['dataname'] = $filename['filename'];
      $data['backtime'] = time();
      $data['link'] = $conf['hostname'];
      $variable = Db::name('variable')->where('id',1)->update(['variable'=>0]);
      if ($rel === false) {
        return get_status(1,'数据库备份失败',5007);
      }else{
        $result = Db::name('backup')->insert($data);
        if (empty($result)) {
          return get_status(1,'数据库插入失败',5008);
        }else{
          return get_status(0,NULL);
        }

      }

   }else{
      return get_status(1,'正在备份请稍后再试',5009);
   }
    //$a = $db->downloadFile($data['dataname']);
    
        
  }
  //数据库备份列表
  public function backupList()
  {
    $post = input('get.');
    //dump($post);
    //'{"pages":2,"num":10}'
    //$post = json_decode($get,1);
    //判断是否传了_t
    if(isset($post['_t'])) {
      //删除_t _t用于前端请求方便
      unset($post['_t']);
    }

    //分页 $currentPage第几页
    if(isset($post['currentPage'])){
      $currentPage = $post['currentPage'];
    }else{
      $currentPage = 1;
    }

    //分页 $pageSize每条页数
    if(isset($post['pageSize'])){
      $pageSize = $post['pageSize'];
    }else{
      $pageSize = 10;
    }

    $result = Db::name('backup')->field('bid,dataname,backtime')->page($currentPage.','.$pageSize)->select();
    $total = Db::name('backup')->count();
    
    $data = ['list' => $result , 'total' => $total];
    
    if (empty($result)) {
         return get_status(0,[]);
    }else{
         return get_status(0,$data);
    }

  }
  //数据库备份下载
  public function backupdown()
  {
    $post = input('get.');
    //$put = '{"dataname":"20180802-113738-1.sql"}';
    //$post = json_decode($put,1);
    if (empty($post)) {
      return get_status(1,$result);
    }
    $config=array(
      'path' =>ROOT_PATH.'data/',//数据库备份路径
    );
    $db= new Backup($config);//实例化数据库备份类进行条用里面的方法。

    $str = trim($post['dataname'],'-1.sql');
    $str = explode('-',$str);
    $str = join($str,'');  
    $result = $db->downloadFile(strtotime($str));
    if (!empty($result)) {
      return get_status(1,$result);
    }
  }
  public function backupdel()
  {
    $put = file_get_contents('php://input');
    if (empty($put)) {
      return get_status(1,NULL);
    }
    //$put = '{"upid":[13]}';
    $post = json_decode($put,1);
    $result = Db::name('backup')->where('bid',$post['bid'])->delete();
    if (empty($result)) {
          return get_status(1,'删除失败',5006);
        }else{
          return get_status(0,NULL);
        }

  }
}