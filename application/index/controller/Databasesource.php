<?php
namespace app\index\Controller;

use think\Db;
use think\Request;
use think\Session;
use app\base\controller\Base;
use think\Controller;

/**
 * 数据库源处理及数据源sql 自定义视图的添加修改
 */
class Databasesource extends Controller 
{
    //token
    protected $token;
    
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
           exit();
        }
    }

    public function index()
    {
        return time();
    }


    /**
     * 查看所有支持数据库
     */
    public function getDatabases()
    {
        //查询所有支持的数据库
        $databasesList = Db::name('databaselist')->field('databasesname,type')->select();
        // dump($databasesList);
        return get_status(0,$databasesList);
    }

    /**
     * 测试数据库连接是否成功
     */
    public function testConnection()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //测试连接
        try{
            switch ($input['type']) {
                case "mysql" :
                    //连接mysql数据库查看所有的库
                    $connection = Db::connect($input)->query("show databases");
                    //设置返回库的键
                    $databasesKey = 'Database';
                    break;
                case "pgsql" :
                    //pgsql默认选择postgres库
                    $input['database'] = 'postgres';
                     //连接pgsql数据库查看所有的库
                    $connection = Db::connect($input)->query("SELECT datname FROM pg_database");
                    //设置返回库的键
                    $databasesKey = 'datname';
                    break; 
                case "sqlsrv" :
                    //连接sqlsrv数据库查看所有的库
                    $connection = Db::connect($input)->query("SELECT NAME FROM MASTER.DBO.SYSDATABASES ORDER BY NAME");
                    //设置返回库的键
                    $databasesKey = 'NAME';
                    break;
                // case "\\think\\oracle\\Connection" :
                case "oracle" :
                    $connection = Db::connect($input)->query('SELECT name FROM v$database');
                    break;
                default:
                    return get_status(1,'不支持的数据库',3001);
            }
             //定义数据库对象
             $databasesArray = [];
             //遍历数据库返回的数组
             foreach($connection as $value) {
                 //将数据库名变成一维数组
                 $databasesArray[] = $value[$databasesKey];
             }
            //返回结果
            return get_status(0,$databasesArray);
        } catch (\Exception $e) {
            switch ($input['type']) {
                case "mysql" :
                    //连接mysql
                    $mysql_conn = @mysqli_connect($input['hostname'], $input['username'], $input['password']);
                    //判断错误
                    if (!$mysql_conn) {
                        $result =  @mysqli_connect_error();//诊断连接错误
                    }
                    break;
                case "pgsql" :
                    $input['database'] = 'postgres';
                    // $connection = Db::connect($input)->query("SELECT datname FROM pg_database;");
                    //设置连接参数
                    $conn_string = "host=".$input['hostname']." port=".$input['hostport']." dbname=".$input['database']." user=".$input['username']." password=".$input['password'];
                    //连接
                    $dbconn = @pg_connect($conn_string);
                    if(!$dbconn){
                        //返回错误信息
                        $result = 'Could not connect to PostgreSQL server.';
                    }
                    break; 
                case "sqlsrv" :
                    $result = 'Can not connect to MSSQL server.';
                    // $connection = Db::connect($input)->query("SELECT NAME FROM MASTER.DBO.SYSDATABASES ORDER BY NAME");
                    break;
                // case "\\think\\oracle\\Connection" :
                case "oracle" :
                    // $connection = Db::connect($input)->query('SELECT * FROM v$instance');
                    $result = 'Could not connect to Oracle server.';
                    break;
                default:
                    $result = '不支持的数据库';
            }
            // $result = '数据库连接失败';
            $result = iconv("GB2312//IGNORE" , "UTF-8",  $result);
            return get_status(1,$result,3002);
        }

    }

    /**
     * 查看数据库中的表
     */
    public function showTables($dbconfig = '')
    {
        //判断是否dbconfig是否有值
        if(empty($dbconfig)) {
             //接收数据
            $post = input('post.');
            //判断数据是否接收成功
            if(isset($post)) {
                $dbconfig = $post;
            }
        }

        //测试连接
        try{
            switch ($dbconfig['type']) {
                case "mysql" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query("show tables");
                    $key = 'Tables_in_'.$dbconfig['database'];
                    break;
                case "pgsql" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query("select tablename from pg_tables where schemaname='public'");
                    $key = 'tablename';
                    break; 
                case "sqlsrv" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query("SELECT Name FROM SysObjects Where XType='U' ORDER BY Name");
                    $key = 'Name';
                    break;
                // case "\\think\\oracle\\Connection" :
                case "oracle" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query('select t.table_name from user_tables t');
                    break;
                default:
                    return get_status(1,'不支持的数据库',3001);
            }
            //定义存储数组
            $result = [];
            //将表从二维数组中拿出
            foreach ($connection as $value) {
                $result[] = $value[$key];
            }
            //返回数组
            return get_status(0,$result);
        } catch (\Exception $e) {
            return get_status(1,'查询失败请检查配置',3003);
        }
    }

    /**
     * 通过查明查出表中所有数据
     */
    public function selectTables($input = '')
    {
        //判断是否dbconfig是否有值
        if(empty($input)) {
            //接收数据
           $input = input('post.');
           //判断数据是否接收成功
           if(isset($input)) {
               $dbconfig = $input;
           }
       }
        // //接收数据
        // $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        } 
        // return $input;
        $dbconfig = json_decode($input['dbconfig'],1);
        // $dbconfig = $input['dbconfig'];

        try{
            //查询表中所有数据
            switch ($dbconfig['type']) {
                case "mysql" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query("SELECT * FROM " .$input['table']);
                    break;
                case "pgsql" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query("SELECT * FROM " .$input['table']);
                    break; 
                case "sqlsrv" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query("SELECT * FROM " .$input['table']);
                    break;
                // case "\\think\\oracle\\Connection" :
                case "oracle" :
                    $connection = Db::connect($dbconfig)->name($dbconfig['database'])->query("SELECT * FROM " .$input['table']);
                    break;
                default:
                    return get_status(1,'数据库查询失败',3003);
            }

            //判断是否查询成功
            if (!$connection) {
                return get_status(1 , '数据库查询失败',3003);
            }else {
                //设置返回格式title
                $data = [];
                //获取title
                foreach($connection[0] as $key => $value) {
                    $data['title'][] = $key;
                }


                //获取data
                $i = 0;
                foreach($connection as $key => $value) {
                    foreach($value as $key => $val) {
                        // if(json_decode($val ,1)) {
                        //     $data['data'][$i][] = json_decode($val ,1);
                        // }else {
                            $data['data'][$i][] = $val; 
                        // }
                        // if($key == 'baseconfig' || $key == 'databases') {
                        //     $data['data'][$i][] = json_decode($val ,1);
                        // } else {
                        //     $data['data'][$i][] = $val; 
                        // }
                    }
                    $i++;  
                }
                return get_status(0 , $data);
            }
        } catch (\Exception $e) {
            return get_status(1 , '数据库查询失败',3003);
        }
    }

    /**
     * 添加数据库源
     */
    public function addDatabase()
    {
         //接收数据
         $input = input('post.');
         //判断数据是否接收成功
         if(!$input) {
             return get_status(1,'数据接收失败',2000);
         }
         //查询数据库中是否有已存在
         $valiName = Db::name('databasesource')->where('basename',$input['basename'])->find();
         if($valiName) {
            return get_status(1,'数据库源名已存在',3007);
         }
         //通过配置数据取得表名
         $vali = $this->showTables($input['baseconfig']);
         //验证是否获取表名成功
         if(!$vali) {
            return get_status(1,'数据库查询失败',3003);
         }
         //将获取到的数据库表写入数据库
         $input['databases'] = json_encode($vali);
         //将数据库源的分组写入数据库
         $input['stype'] = $input['baseconfig']['type'];
         //json化数据库源的配置
         $input['baseconfig'] = json_encode($input['baseconfig']);
         //创建数据库记录的时间
         $input['createtime'] = time();
         //将数据库信息加入数据库
         $insert = Db::name('databasesource')->insert($input);
         //判断是否插入数据库成功
         if(!$insert) {
            return get_status(1,'添加数据库源失败',3004);
         }else {
            return get_status(0,'添加数据库源成功');
         }
    }

    /**
     * 查询所有数据库连接
     */
    public function getDatabase()
    {
        //获取用户token
        $arr = get_all_header();
        if(isset($arr['token'])){
            $this->token = $arr['token'];    
        }else {
            $data = get_status(1,'非法用户访问',1);
            return $data;
        }
        //通过token获取用户的uid
        $uid = Db::name('token')->where('token',$this->token)->field('uid')->find();
        //通过uid获取用户的sid
		$category = Db::name('user')->where('uid',$uid['uid'])->field('sid')->find();	
		$cate = explode(',',$category['sid']);
        $groupdata = Db::name('screengroup')->where('sid','in',$cate)->field('sid,screenname')->select();
        // return $groupdata;

        //将用户分组加入数组
    	for ($i=0; $i < count($groupdata); $i++) { 
    		$sid[] = $groupdata[$i]['sid'];
        }
    	//判断该用户是否有分组
    	if(empty($sid)){
    		return ['err'=>1,'status'=>0,'data'=>[],'cate'=>$groupdata];
		}
		//接收数据
        $get = input('get.');

		//判断是否有有搜索关键字
		if(!isset($get['searchword'])) {
			//设置默认搜索关键字
			$get['searchword'] = '';
		}else {
			//去掉首尾空格
			$get['searchword'] = rtrim($get['searchword']);
		}
		//判断是否排序
		if(!isset($get['order'])) {
			//设置默认排序规则
			$get['order'] = 'baseid';
		}else{
			//去掉首尾空格
			$get['order'] = rtrim($get['order']);
		}
		//判断是否有sid
		if(!isset($get['sid'])) {
			$get['sid'] = 0;
        }else{
            $get['sid'] = intval($get['sid']);
        }
        
        //判断是否有stype
        if(!isset($get['stype'])) {
			$get['stype'] = "all";
        }
        //分页 $currentPage第几页
        if(isset($get['currentPage'])){
            $currentPage = $get['currentPage'];
        }else {
            $currentPage = 1;
        }
        // return $get['pageSize'];
        //分页  $pageSize每页条数
        if(isset($get['pageSize'])){
            $pageSize = $get['pageSize'];
        }else {
            $pageSize = 10;
        }   
        
        if ($get['sid'] == 0) {
            //判断是否有stype
            if($get['stype'] == "all") {
                $data = Db::name('databasesource')->where('sid','in',$sid)
                                              ->where('basename' , 'like' , "%".$get['searchword']."%")
                                              ->field('baseid,basename,baseconfig,createtime,stype,sid,remark,databases')
                                              ->page($currentPage.','.$pageSize)
                                              ->order($get['order'] .' DESC')
                                              ->select();
                //查询总条数
                $total = Db::name('databasesource')->where('sid','in',$sid)
                                                ->where('basename' , 'like' , "%".$get['searchword']."%")
                                                ->count();
            }else {
                $data = Db::name('databasesource')->where('sid','in',$sid)
                                              ->where('stype',$get['stype'])
                                              ->where('basename' , 'like' , "%".$get['searchword']."%")
                                              ->field('baseid,basename,baseconfig,createtime,stype,sid,remark,databases')
                                              ->page($currentPage.','.$pageSize)
                                              ->order($get['order'] .' DESC')
                                              ->select();
                //查询总条数
                $total = Db::name('databasesource')->where('sid','in',$sid)
                                                ->where('basename' , 'like' , "%".$get['searchword']."%")
                                                ->where('stype',$get['stype'])
                                                ->count();
            }
            
        }else{
            //判断是否有stype
            if($get['stype'] == "all") {
                $data = Db::name('databasesource')->where('sid' , $get['sid'])
                                              ->where('basename' , 'like' , "%".$get['searchword']."%")
                                              ->field('baseid,basename,baseconfig,createtime,stype,sid,remark,databases')
                                              ->page($currentPage.','.$pageSize)
                                              ->order($get['order'].' DESC')
                                              ->select();
                //查询总条数
                $total = Db::name('databasesource')->where('sid' , $get['sid'])
                                              ->where('basename' , 'like' , "%".$get['searchword']."%")
                                              ->count();		
            }else {
                $data = Db::name('databasesource')->where('sid' , $get['sid'])
                                              ->where('stype',$get['stype'])
                                              ->where('basename' , 'like' , "%".$get['searchword']."%")
                                              ->field('baseid,basename,baseconfig,createtime,stype,sid,remark,databases')
                                              ->page($currentPage.','.$pageSize)
                                              ->order($get['order'].' DESC')
                                              ->select();
                                              //查询总条数
                $total = Db::name('databasesource')->where('sid' , $get['sid'])
                                                ->where('basename' , 'like' , "%".$get['searchword']."%")
                                                ->where('stype',$get['stype'])
                                                ->count();		
            } 
            		
        }	
        //将时间戳换成时间
        foreach ($data as $key => $value) {
            $data[$key]['createtime'] = date('Y-m-d H:i:s' , $value['createtime']);
        } 
        //查询所有支持的数据库
        $databasesList = Db::name('databaselist')->select();
	    //返回数据
		return  ['err'=>0,'status'=>0,'data'=>$data,'cate'=>$groupdata,'databases' => $databasesList,'total' => $total];
    }
    
    /**
     * 查看关键词搜索的前五个关键字
     */
    public function likeWord()
    {
        //获取用户token
        $arr = get_all_header();
        if(isset($arr['token'])){
            $this->token = $arr['token'];    
        }else {
            $data = get_status(1,'非法用户访问',10001);
            return $data;
        }
        //通过token获取用户的uid
        $uid = Db::name('token')->where('token',$this->token)->field('uid')->find();
        //通过uid获取用户的sid
		$category = Db::name('user')->where('uid',$uid['uid'])->field('sid')->find();	
		$cate = explode(',',$category['sid']);
        $groupdata = Db::name('screengroup')->where('sid','in',$cate)->field('sid,screenname')->select();
        // dump($groupdata);
        // exit;
        //将用户分组加入数组
    	for ($i=0; $i < count($groupdata); $i++) { 
    		$sid[] = $groupdata[$i]['sid'];
    	}
    	//判断该用户是否有分组
    	if(empty($sid)){
    		return  get_status(0,[]);
		}
		//接收post数据
		$get = input('get.');
		//判断是否有有搜索关键字
		if(!isset($get['searchword'])) {
			//设置默认搜索关键字
			$get['searchword'] = '';
		}else {
			//去掉首尾空格
			$get['searchword'] = rtrim($get['searchword']);
		}
		//判断是否排序
		if(!isset($get['order'])) {
			//设置默认排序规则
			$get['order'] = 'baseid';
		}else{
			//去掉首尾空格
			$get['order'] = rtrim($get['order']);
		}
		//判断是否有sid
		if(!isset($get['sid'])) {
			$get['sid'] = 0;
        } else {
            $get['sid'] = intval($get['sid']);
        }
        
        //判断是否有stype
        if(!isset($get['stype'])) {
			$get['stype'] = "all";
        }
		

        if ($get['sid'] == 0) {
            //判断是否有stype
            if($get['stype'] == "all") {
                $data = Db::name('databasesource')->where('sid','in',$sid)
                                            ->where('basename' , 'like' , "%".$get['searchword']."%")
                                            // ->field('basename')
                                            ->order($get['order'].' DESC')
                                            ->group('basename')
                                            ->column('basename');
            }else {
                $data = Db::name('databasesource')->where('sid','in',$sid)
                                            ->where('stype', $get['stype'])
                                            ->where('basename' , 'like' , "%".$get['searchword']."%")
                                            // ->field('basename')
                                            ->order($get['order'].' DESC')
                                            ->group('basename')
                                            ->column('basename');
            } 
        }else{
            //判断是否有stype
            if($get['stype'] == "all") {
                $data = Db::name('databasesource')->where('sid' , $get['sid'])
                                            ->where('basename' , 'like' , "%".$get['searchword']."%")
                                            // ->field('basename')
                                            ->order($get['order'].' DESC')
                                            ->group('basename')
                                            ->column('basename');
            }else {
                $data = Db::name('databasesource')->where('sid' , $get['sid'])
                                            ->where('stype', $get['stype'])
                                            ->where('basename' , 'like' , "%".$get['searchword']."%")
                                            // ->field('basename')
                                            ->order($get['order'].' DESC')
                                            ->group('basename')
                                            ->column('basename');
            } 		
        }
        	
	    //返回数据
		return  get_status(0,$data);
    }

    /**
     * 修改数据源
     */
    public function updatedatabase()
    {
         //接收数据
         $input = input('post.');
         //判断数据是否接收成功
         if(!$input) {
             return get_status(1,'数据接收失败',2000);
         }
         
         //查询数据源库源是否存在
         $vali = Db::name('databasesource')->where('basename')->find();
         if($vali) {
            return get_status(1,'数据库源名已存在',3007);
         }
         //通过配置数据取得表名
         $valis = $this->showTables($input['baseconfig']);
         //验证是否获取表名成功
         if(!$valis) {
            return get_status(1,'数据库查询失败',3003);
         }
         //将获取到的数据库表写入数据库
         $input['databases'] = json_encode($vali);
         //将数据库源的分组写入数据库
         $input['stype'] = $input['baseconfig']['type'];
         //加入数据库配置及时间
         $input['baseconfig'] = json_encode($input['baseconfig']);
         $input['createtime'] = time();
         //将数据库信息加入数据库
         $update = Db::name('databasesource')->where('baseid',$input['baseid'])->update($input);
         if(!$update) {
            return get_status(1,'修改数据库源失败',3004);
         }else {
            return get_status(0,'修改数据库源成功');
         }
    }

    /**
     * 删除数据库源
     */
    public function deletedatabase()
    {
         //接收数据
         $input = input('post.');
         //判断数据是否接收成功
         if(!$input) {
             return get_status(1,'数据接收失败',2000);
         }
         //删除数据库源
         $delete = Db::name('databasesource')->delete($input['baseid']);
         if(!$delete) {
            return get_status(1,'删除数据源失败',3006);
         }else {
            return get_status(0,'删除数据源成功');
         }
    }

    /**
     * 添加SQL连接数据源
     */
    public function SQLlink()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //查询数据库中是否有重复名字
        $vali = Db::name('datament')->where('dataname',$input['dataname'])->find();
        //判断是否有
        if($vali) {
            return get_status(1,'数据源已存在',4001);
        }
        //获取数据库配置
        $dbconfig = Db::name('databasesource')->where('baseid',$input['sid'])->field('baseconfig,databases')->find();
        //json转数组
        $config = json_decode($dbconfig['baseconfig'],1);
        //执行sql
        try{
            $result = Db::connect($config)->name($config['database'])->query("$input[returnsql]");    
        } catch (\Exception $e) {
            return get_status(1,'数据添加失败',4002);
        }
        //判断是否查询成功
        if(!$result) {
            $res = [];
        }else {
            $res = $result;
        }
        //将查出来的数据json格式化
        $input['data'] = json_encode($res);
        $input['tablename'] = $dbconfig['databases'];
        $input['createtime'] = date('Y-m-d H:i:s' , time());

        //将数据插入到数据库
        $insert = Db::name('datament')->insert($input);
        //判断是否插入成功
        if(!$insert) {
            return get_status(1,'数据添加失败',4002);
        }else{
            return get_status(0,'数据添加成功');
        }
    }

    /**
     * 查看sql返回结果
     */
    public function showSql()
    {
        // return time();
        //接收数据
        $input = input('get.');
        // return $input;
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //获取数据库配置
        $dbconfig = Db::name('databasesource')->where('baseid',$input['sid'])->field('baseconfig')->find();

        //json转数组
        $config = json_decode($dbconfig['baseconfig'],1);
        if(!strstr(strtolower($input['returnsql']) , 'select')) {
            return get_status(1,"SQL只支持查询",4004);
        }
        try {
            //执行sql
            $result = Db::connect($config)->name($config['database'])->query("$input[returnsql]");
            //返回查询结果
            return get_status(0,$result);
        } catch (\Exception $e) {
            //返回错误信息
            return get_status(1,"SQL查询失败,请检查SQL语句",4004);
        }
       
        
    }

    /**
     * 修改sql数据源连接
     */
    public function updateSql()
    {
        $input = input('put.');
        // return $input;
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        $keys = ["daid","cid","sid","dataname","datatype","returnsql"];
        //验证传值
        $vali = valiKeys($keys , $input);
        //判断传值是否满足
        if($vali['err'] != 0) {
            return get_status(1,$vali['data'] , 4003);
        }
        //查询数据库中是否有重复名字
        $vali = Db::name('datament')->where('dataname',$input['dataname'])->where("data" , "<>" , $input['daid'])->find();
        //判断是否有
        if($vali) {
            return get_status(1,'数据源已存在' , 4001);
        }
        //获取数据库配置
        $dbconfig = Db::name('databasesource')->where('baseid',$input['sid'])->field('baseconfig,databases')->find();
        //json转数组
        $config = json_decode($dbconfig['baseconfig'],1);
        //执行sql
        try{
            $result = Db::connect($config)->name($config['database'])->query("$input[returnsql]");    
        } catch (\Exception $e) {
            return get_status(1,'SQL查询失败,请检查SQL语句',4004);
        }
        //判断是否查询成功
        if(!$result) {
            $res = [];
        }else {
            $res = $result;
        }
        //将查出来的数据json格式化
        $input['data'] = json_encode($res);
        $input['tablename'] = $dbconfig['databases'];
        $input['createtime'] = date('Y-m-d H:i:s' , time());

        //将数据插入到数据库
        $update = Db::name('datament')->where('daid', $input['daid'])->update($input);
        //判断是否插入成功
        if(!$update) {
            return get_status(1,'数据修改失败',4005);
        }else{
            return get_status(0,'数据修改成功');
        }
    }

    /**
     * 添加自定义数据源
     */
    public function addCustomize()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        } 
        //验证自定义视图名字是否重复
        $vali = Db::name('datament')->where('dataname' , $input['dataname'])->find();
        if($vali) {
            return get_status(1 , '该数据源已存在',4001);
        }
        //加入创建时间
        $input['createtime'] = date('Y-m-d H:i:s' , time());
        //将input['data][value]转换为关联数组
        $newArr = [];
        $i = 0;
        foreach($input['data'] as $key => $value){
            $j = 1;
            foreach($value as $val) {
                $newArr[$key][$j] = $val;
                $j++;
            }
            $i++;
        }
        //data返回数据json化
        $input['data'] = json_encode($input['data']); 
        //处理sql语句
        $input['returnsql'] = "SELECT * FROM ".$input['table'];
        //将数据表记录
        $input['tablename'] = $input['table'];
        unset($input['table']);
        //将数据插入数据库
        $insert = Db::name('datament')->insert($input);

        //判断是否插入成功
        if(!$insert) {
            return get_status(1 , '添加自定义视图失败',4002);
        }else {
            return get_status(0 , '添加自定义视图成功');
        }
    }

    /**
     * 修改自定义数据源
     */
    public function updateCustomize()
    {
        //接收数据
        $input = input('put.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        } 
        $keys = ["daid","cid","sid","dataname","datatype","data","table"];
        //验证传值
        $vali = valiKeys($keys , $input);
        //判断传值是否满足
        if($vali['err'] != 0) {
            return get_status(1,$vali['data'],4003);
        }
        //验证自定义视图名字是否重复
        $vali = Db::name('datament')->where('dataname' , $input['dataname'])->where("data" , "<" , $input['daid'])->where("data" , ">" , $input['daid'])->find();
        if($vali) {
            return get_status(1 , '该数据源已存在',4001	);
        }
        //加入创建时间
        $input['createtime'] = date('Y-m-d H:i:s' , time());
        //data返回数据json化
        $input['data'] = json_encode($input['data']);
        //处理sql语句
        $input['returnsql'] = "SELECT * FROM ".$input['table'];
        //将数据表记录
        $input['tablename'] = $input['table'];
        unset($input['table']);
        //将数据插入数据库
        $update = Db::name('datament')->where('daid', $input['daid'])->update($input);

        //判断是否插入成功
        if(!$update) {
            return get_status(1 , '修改自定义视图失败',4005);
        }else {
            return get_status(0 , '修改自定义视图成功');
        }
    }

    /**
     * 修改备注
     */
    public function modifyNotes()
    {
        //接收数据
        $input = input('put.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败');
        } 
        $keys = ["id","type","remark"];
        //验证传值
        $vali = valiKeys($keys , $input);
        //判断传值是否满足
        if($vali['err'] != 0) {
            return get_status(1,$vali['data']);
        }
        //处理不同的数据库
        if($input['type'] == '1') {
            $type = 'databasesource';
            $remark = $input['remark'];
            $id = "baseid";
        }else {
            $type = 'datament';
            $remark = $input['remark'];
            $id = "daid";
        }
        //执行修改
        $update = Db::name($type)->where($id , $input['id'])->update(["remark" => $remark]);
        //判断是否插入成功
        if(!$update) {
            return get_status(1 , '修改备注失败',4005);
        }else {
            return get_status(0 , '修改备注成功');
        }

    }

}