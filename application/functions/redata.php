<?php 
define( 'JSON_PATH' ,ROOT_PATH.'public/openv/static/config.json');

function ajaxReturn($data) {
	// 返回JSON数据格式到客户端 包含状态信息
//    header('Content-Type:application/json; charset=utf-8');
   exit(json_encode($data));
}


function get_status($err,$da ,$status = null ){
   $data['err'] = $err;
   $data['data'] = $da;
   if($data['err']) {
	   $data['status'] = $status;
   }
   return $data;
}


//获取header头中的值（token）
function get_all_header(){
   // 忽略获取的header数据。这个函数后面会用到。主要是起过滤作用
   $ignore = array('host','accept','content-length','content-type');

   $headers = array();
   //这里大家有兴趣的话，可以打印一下。会出来很多的header头信息。咱们想要的部分，都是‘http_'开头的。所以下面会进行过滤输出。
/*    var_dump($_SERVER);
   exit;*/

   foreach($_SERVER as $key=>$value){
	 if(substr($key, 0, 5)==='HTTP_'){
	 //这里取到的都是'http_'开头的数据。
	 //前去开头的前5位
	   $key = substr($key, 5);
	   //把$key中的'_'下划线都替换为空字符串
	   $key = str_replace('_', ' ', $key);
	   //再把$key中的空字符串替换成‘-’
	   $key = str_replace(' ', '-', $key);
	   //把$key中的所有字符转换为小写
	   $key = strtolower($key);

   //这里主要是过滤上面写的$ignore数组中的数据
	   if(!in_array($key, $ignore)){
		 $headers[$key] = $value;
	   }
	 }
   }
//输出获取到的header
   return $headers;
 }


 /**
  * 判断传过来的键是否存在
  *
  *  */
 function valiKeys($keys ,$input){
	//遍历键
	foreach($keys as $k => $v) {
		if(!isset($input[$v])) {
			return get_status(1,$v.'未设置');
		}
	}
	return get_status(0,[]);
 }


 /**
  * 默认返回为html是返回json格式
  */
function jsonRetuen($err , $data){
	return json_encode(['err' => $err , 'data' => $data] , JSON_UNESCAPED_UNICODE );
}


    /**
     * @Notes : 解密
     * @access : $password : 解密内容
     * @author : wwk
     * @Time: 2018/08/13 11:23
     */
function decrypt($password , $len)
{
	$key = 'kwc.net';
	$str = openssl_decrypt($password, 'aes-128-cbc',$key,2);
	$pwd = substr($str, 0,$len);
	return $pwd;
}

//获取配置文件信息
function configJson(){
	//获取配置文件信息
	$file = file_get_contents(JSON_PATH);
	//将配置文件转换数组
	$arr = json_decode($file,1);
	//返回配置文件信息及路径
	return ['config' => $arr , 'path' => JSON_PATH];
}


//富文本配置常量
/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_SLICE',   1);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_STR',  2);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_ARR',  3);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_OBJ',  4);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_CMT', 5);

/**
 * Behavior switch for Services_JSON::decode()
 */
define('SERVICES_JSON_LOOSE_TYPE', 16);

/**
 * Behavior switch for Services_JSON::decode()
 */
define('SERVICES_JSON_SUPPRESS_ERRORS', 32);


//处理base64图片
function base64Image($data){
	if(!is_string($data) || $data == '') {
		return false;
	}
	$base64 = $data;
	$arr = explode(',',$base64);
	//取出后缀
	$ext = base64Ext($arr[0]);
	//取出base64图片字符串
	$base64Str = $arr[1];
	$file = base64_decode($base64Str);
	//定义图片名称
	$fileName = md5(mt_rand(100,999).time()).'.'.$ext;
	//定义图片路径
	$filePath = ROOT_PATH . 'public' . DS . 'uploads' . DS . date('Ymd', time());
	if( !file_exists($filePath)) {
			mkdir($filePath,0755);
	}
	//保存图片
	$r = file_put_contents($filePath.DS.$fileName, $file);

	if($r) {
			//获取图片相对路径
			$imgPath = str_replace(ROOT_PATH , '' , $filePath.DS.$fileName);
			return $imgPath;
	}else{
			return false;
	}
}


// 传入base64前部分带有照片后缀的字符串
function base64Ext($str){       
	$step1 = explode('/', $str);
	$step2 = explode(';', $step1[1]);
	return $step2[0];
}

// 将api链接转换加密成rediskey 2019-06-03 by 李福龙
function getRedisKeyByApi($apiUrl)
{
	// dump($apiUrl);
	$api = trim($apiUrl);
	$redisConf = config('redisConfig.redis1');
	$apiRedisKey = $redisConf['keys']['apiRedisKey'] ?? '2019apiRedisKey';
	return md5($apiRedisKey.md5($apiUrl).'openv');
}


