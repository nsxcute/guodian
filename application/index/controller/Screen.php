<?php
namespace app\index\Controller;

use think\Db;
use think\Request;
use think\Session;
use \app\index\controller\Chart;
use \app\index\controller\Index;
use \app\index\controller\User;
use think\Controller;
use app\middle\model\RedisModel;

/**
 * 图表处理 ,CURL 映射 预警 和一些图表的操作
 */
class Screen extends Controller 
{

    public function __construct(Request $request = null)
    {
        // if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
        //    exit();
        // }
        // parent::__construct($request);
        // //获取当前方法
        // $action = strtolower($request->action());
        // if ($action != 'getallchart' ) {
        //     $user = new User;
        //     $user->keepLogging();
        // }
       
    }

    public function index()
    {
       
    }

    /**
     * 大屏加密
     * cid 大屏ID password 密码
     * return 成功or失败
     */
    public function screenLocking()
    {
        //接收数据
        $post = input('post.');
        //将接数据组成数组
        $data = [
            'lock' => 1,
            'password' => $post['password'],
            'updatetime' => time(),
        ];
        //修改大屏配置
        $update = Db::name('screen')->where(['id' => $post['cid']])->update($data);
        
        if($update) {
            return get_status(0,'大屏加密成功');
        }else {
            return get_status(1,'大屏加密失败',2001);
        }
    }

    /**
     * 大屏解锁
     */
    public function screenValiPassword()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //查出大屏的密码
        $res = Db::name('screen')->where('id',$input['cid'])->find();
        //验证码密码是否成功
        if($res['password'] == $input['password']) {
            return get_status(0,'密码验证成功');
        }else {
            return get_status(1,'密码错误',2002);
        }
    }
    /**
     * 大屏解密
     * cid 大屏ID 
     * return 成功or失败
     */
    public function screenUnLocking()
    {
        //接收数据
        $post = input('post.');
        //查询大屏密码
        $screen = Db::name('screen')->where(['id' => $post['cid']])->field('password')->find();
        if($screen['password'] != input('password')) {
            return get_status(1,'大屏解锁失败,密码错误',2002);
        }
        //将接数据组成数组
        $data = [
            'lock' => 0,
            'password' => '',
            'updatetime' => time(),
        ];
        //修改大屏配置
        $update = Db::name('screen')->where(['id' => $post['cid']])->update($data);
        
        if($update) {
            return get_status(0,'大屏解密成功');
        }else {
            return get_status(1,'大屏解密失败',2002);
        }
    }


    /**
     * 搜索大屏名字
     * serachword 搜索词
     * return arr 搜索出来的信息
     */
    public function getSerachScreenName()
    {
        //获取搜索词
        $serachword = input('get.serachword');
      

        //查询数据库
        $result = Db::name('screen')->where('name','like',"%".$serachword."%")->limit(5)->column('name');
        
        //返回数据
        return get_status(0,$result);
    }

    /**
     * 可视化大屏图表增加
     * (可以有重复名称的图表)
     * {
     *      "screenid" : int 大屏ID
     *		"tname" : "图表名字",
	 *		"tdata" : "{\"data1-1\" :\" data1-1\"}", 图表数据
	 *		"tconfig" : "{\"config1-1\" :\" config1-1\"}", 图表配置
     *      "position" : 1
     * }
     * return err : 0 
     */
    public function addChart() 
    {
        //接收数据
        $input = input('post.');
        // $phpinput = file_get_contents('php://input');
        // return $input;

        //判断数据是否接收成功
        if(!$input) {
            return ;
        }
        //将图表信息中加入图表创建时间
        $input['createtime'] = time();
        $input['updatetime'] = time();
        //查询图表中定位最大值
        $position = Db::name('screenchart')->where(['screenid' => $input['screenid']])->order('position DESC')->find();
        //判断大屏是否有图表
        if($position){
            //将定位最大值+1填入图表数据
            $input['position'] = $position['position'] + 1;
        }else{
            //没有图表默认位置为1
            $input['position'] = 0;
        }
        
        //判断大屏中是否有重复的图表
        $vali = Db::name('screenchart')->where(['screenid' => $input['screenid'],'tname' => $input['tname']])->select();
        if($vali) {
            return get_status(1,'大屏名字重复',2003);
        }
        //将图表类型拿到
        $typeid = $input['type'];
        //删除下标type
        unset($input['type']);
        //取得type对应的data
        $tdata = Db::name('chartdata')->where('charttype',$typeid)->find();

        //将默认数据存入数据库中
        $input['tdata'] = $tdata['data'];
        $input['ttype'] = json_decode($input['tconfig'], true)['dataOpt']['source']['type'] ??  'STATIC';

        //将图表加入到数据中
        $insert = Db::name('screenchart')->insert($input);
        //判断是否插入成功
        if(!$insert) {
            $key = config('redisConfig.redis1')['keys']['openvApisRedisKey'] ?? '2019openvApisRedisKey';
            $this->delRedisStringKey($key);
            return get_status(1,'添加图表失败',2004);
        }else {
            //获取新增的ID
            $tid = Db::name('screenchart')->getLastInsID();
            return get_status(0,['tid' => intval($tid)]);
        }
    }

    /**
     * 删除图表
     * chid 图表ID
     */
    public function delChart() 
    {
        //接收数据
        $input = input();

        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败' , 2000);
        }
        $res = Db::name('screenchart')->where('tid',$input['chid'])->field('position,screenid')->find();
        $screenid = $res['screenid'];
        //查询当前大屏中在当前图表的上层图表
        $result = Db::name('screenchart')->where('screenid',$screenid)->where('position','>',$res['position'])
                                            ->field('position,tid')->order('position DESC')->select();
        //将所有的上层图表位置-1
        foreach($result as $key => $value) {
            $update = Db::name('screenchart')->where('tid',$value['tid'])->update(['position' => $value['position']-1]);
        }
        //删除指定图表
        $delete = Db::name('screenchart')->delete($input['chid']);
        //判断是否删除成功
        if(!$delete) {
            return get_status(1,'图表删除失败',2005);
        }else {
            return get_status(0 ,'图表删除成功');
        }
    }

    /**
     * 大屏图表修改
     * (可以有重复名称的图表)
     * {
     *      "chid" : 图表ID
     *		"tname" : "图表名字",
	 *		"tdata" : "{\"data1-1\" :\" data1-1\"}", 图表数据
	 *		"tconfig" : "{\"config1-1\" :\" config1-1\"}", 图表配置
     *      "position" : 1
     * }
     */
    public function updatechart() 
    {
        //接收数据
        $input = input('put.');
        
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败' ,2000);
        }
        
        //判断图表数据是否完全一致
        $vali = Db::name('screenchart')->where($input)->select();
        if ($vali) {
            return get_status(0,'修改图表成功');
        }
        
        //将tconfig格式化
        $tconfig = json_decode($input['tconfig'],1);
 
        if(isset($tconfig['dataOpt']['source'])) {
            $source = $tconfig['dataOpt']['source'];
            //判断数据源类型是否是sql
            if(strtolower($source['type']) == 'sql') {
                //查看修改数据源类型
                $source = $tconfig['dataOpt']['source'];
                try {
                    //获取修改数据源的ID 及sql语句 数据库配置
                    $id = $source['selectDaid'];
                    $sql = $source['sqlstr'];
                    
                    // $sql = "SELECT * FROM up_databasesource";
                    $config = Db::name('databasesource')->where('baseid',$source['dbLinkId'])->field('baseconfig')->find();
                    $config = json_decode($config['baseconfig'],1);
                    //通过配置文件执行sql
                    $data = Db::connect($config)->query($sql);
                    
                    //将修改好的数据源信息执行修改
                    $update = [
                        'sid' => $source['dbLinkId'],
                        'returnsql' => $sql,
                        'data' => $data,
                    ];
                    //执行sql语句
                    $updatesSql = Db::name('datament')->where('daid',$id)->update($update);

                } catch (\Exception $e) {
                    if(empty($source['selectDaid']) || empty($source['sqlstr'])){
                        return get_status(0,"请选择数据源");
                    }else {
                        return get_status(1,"请检查SQL语句或配置" , 2006);
                    }
                }
            }
        }
        //数组中加入修改时间
        $input['updatetime'] = time();
        $input['ttype'] = json_decode($input['tconfig'], true)['dataOpt']['source']['type'] ??  'STATIC';
        //将图表加入到数据中
        $update = Db::name('screenchart')->where(['screenid' => $input['screenid'],'tname' => $input['tname']])->update($input);
        $key = config('redisConfig.redis1')['keys']['openvApisRedisKey'] ?? '2019openvApisRedisKey';
        $this->delRedisStringKey($key);
        return get_status(0,'修改图表成功');
    }
    protected function delRedisStringKey($key)
    {
        $redis = new RedisModel();
        $redis->deleteRedisKey($key);
    }

    /**
     * 图表锁定
     * chid 图表ID
     */
    public function chartlock() 
    {
        //接收数据
        $input = input('put.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败' , 2000);
        }
        //判断锁定OR解锁
        if(!$input['status']) {
            $islock = 0;
        }else {
            $islock = 1;
        }
        //验证是否已锁定
        $vali = Db::name('screenchart')->where(['tid' => $input['chid'], 'islock' => $islock])->select();
        //设置返回值
        if(!$input['status']) {
            if($vali) {
                return get_status(0,'解锁成功');
            }
        }else {
            if($vali) {
                return get_status(0,'锁定成功');
            }
        }
        //修改锁定状态
        $update = Db::name('screenchart')->where(['tid' => $input['chid']])->update(['islock' => $islock]);
        //设置返回值
        if(!$input['status']) {
            if(!$update) {
                return get_status(1,'大屏解锁失败',2007);
            }else{
                return get_status(0,'解锁成功');
            }
        }else {
            if(!$update) {
                return get_status(1,'大屏锁定失败',2008);
            }else{
                return get_status(0,'锁定成功');
            }
        }
    }

    /**
     * 隐藏图图表
     */
    public function charthidden() 
    {
        //接收数据
        $input = input('put.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败');
        }
        //判断显示OR隐藏
        if(!$input['status']) {
            $hidden = 0;
        }else {
            $hidden = 1;
        }
        //验证是否已隐藏
        $vali = Db::name('screenchart')->where(['tid' => $input['chid'] , 'ishide' => $hidden])->select();
        //设置返回值
        if(!$input['status']) {
            if($vali) {
                return get_status(0,'显示成功');
            }
        }else {
            if($vali) {
                return get_status(0,'隐藏成功');
            }
        }
        //修改隐藏状态
        $update = Db::name('screenchart')->where(['tid' => $input['chid']])->update(['ishide' => $hidden]);
        //设置返回值
        if(!$input['status']) {
            if(!$update) {
                return get_status(1,'大屏显示失败',2009);
            }else{
                return get_status(0,'显示成功');
            }
        }else {
            if(!$update) {
                return get_status(1,'大屏隐藏失败',2019);
            }else{
                return get_status(0,'隐藏成功');
            }
        }
    }

    /**
     * 图表复制
     * "chid" : "int 图表ID"
     */
    public function chartcopy()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败' ,2000);
        }
        //查询相关图表信息
        $data = Db::name('screenchart')->where('tid',$input['tid'])->field('screenid,tname,tdata,tconfig,islock,link,position,ishide')->find();
        //判断查询是否成功
        if(!$data) {
            return get_status(1,'复制失败',2011);
        }
        //加入创建时间和修改时间
        $data['createtime'] = time();
        $data['updatetime'] = time();
        //将data数据中的key修改
        $data['tconfig'] = json_decode($data['tconfig'],1);
        //修改副本名字
        $data['tname'] = $input['key'];
        $data['tconfig']['key'] = $input['key'];
        //将tconfigjson化
        $data['tconfig'] = json_encode($data['tconfig'],JSON_UNESCAPED_UNICODE);
        //获取该大屏中最大的图层
        $maxPosition = Db::name('screenchart')->where('screenid',$data['screenid'])->field('position')->order('position DESC')->find()['position'];
        //将复制的新图层放置为最大图层
        $data['position'] = $maxPosition + 1;
        //将副本加入数据库
        $insert = Db::name('screenchart')->insert($data);
        if(!$insert) {
            return get_status(1,'复制失败',2011);
        }else {
            $tid = Db::name('screenchart')->getLastInsID();
            return get_status(0,['tid' => intval($tid)]);
        }
    }

    /**
     * 移动图表
     * 
     * "data" : 
     *   {
     *      "tid" : "图表ID"
     *       "position" : "图表定位"
     *   },
     *   {
     *       "tid" : "图表ID"
     *       "position" : "图表定位"
     *   }
     *   ...
     * 
     */
    public function movechart()
    {   
        //接收数据
        $input = input('put.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //判断是否有操作('Topping/end/moveUp/moveDown')
        if(!isset($input['operating'])) {
            return get_status(1,'必须有移动操作',2012);
        } 
        //判断是否有ID
        if(isset($input['tid'])) {
            //获取当前ID的位置
            $result = Db::name('screenchart')->where('tid',$input['tid'])->field('position,screenid')->find();
            //当前图表位置
            $position = $result['position'];
            //当前图表大屏
            $screenid = $result['screenid'];
        }

        //switch遍历
        switch ($input['operating']) {
            case 'top' ://置顶
                    //查询当前大屏中在当前图表的上层图表
                    $result = Db::name('screenchart')->where('screenid',$screenid)->where('position','>',$result['position'])
                                                    ->field('position,tid')->order('position DESC')->select();
                    if(!$result) {
                        //要移动的位置
                        $movePosition = $position;
                    }else {
                        //要移动的位置
                        $movePosition = $result[0]['position'];
                    }
                   
                    //将所有的上层图表位置-1
                    foreach($result as $key => $value) {
                        $update = Db::name('screenchart')->where('tid',$value['tid'])->update(['position' => $value['position']-1]);
                    }
                break;
            case 'end' ://置底
                    //查询当前大屏中在当前图表的上层图表
                    $result = Db::name('screenchart')->where('screenid',$screenid)
                                                    ->where('position','<',$result['position'])
                                                    ->field('position,tid')->order('position ASC')->select();
                    if(!$result) {
                        //要移动的位置
                        $movePosition = $position;
                    }else {
                        //要移动的位置
                        $movePosition = $result[0]['position'];
                    }
                    
                        //将所有的上层图表位置-1
                        foreach($result as $key => $value) {
                        $update = Db::name('screenchart')->where('tid',$value['tid'])->update(['position' => $value['position']+1]);
                    }
                break;
            case 'up' ://上移
                    //查询当前大屏最大位置
                    $result = Db::name('screenchart')->where('screenid',$screenid)->field('position,tid')->order('position DESC')->find();
                    if($result['position'] == $position) {
                        $movePosition = $position;
                    }else {
                        //要移动的位置
                        $movePosition = $position+1;
                    }
                    //修改比当前图层高一层的值-1
                    $up = Db::name('screenchart')->where('screenid',$screenid)
                                                ->where('position',$movePosition)
                                                ->update(['position'=>$position]);
                   
                break;
            case 'down' ://下移
                    //查询当前大屏最大位置
                    $result = Db::name('screenchart')->where('screenid',$screenid)->field('position,tid')->order('position ASC')->find();
                    if($result['position'] == $position) {
                        $movePosition = $position;
                    }else {
                        //要移动的位置
                        $movePosition = $position-1;
                    }
                    //修改比当前图层高一层的值-1
                    $up = Db::name('screenchart')->where('screenid',$screenid)
                                                ->where('position',$movePosition)
                                                ->update(['position'=>$position]);
                break;
            case 'all' ://下移
                    $screenid = '';
                    //确定最大位置
                    $max = count($input['layerlist']) - 1;
                    //遍历图表排序列表
                    foreach ( $input['layerlist'] as $key => $value) {
                        //通过图表名字修改图表信息
                        $chart = Db::name('screenchart')->where('tname',$value)->update(['position' => $max]);
                        //判断screenid是否等于空
                        if($screenid == '') {
                            $chartInfo = Db::name('screenchart')->where('tname',$value)->find();
                            $screenid  = $chartInfo['screenid'];
                        }
                        $max--;
                    }
                    $result = Db::name('screenchart')->where('screenid',$screenid)->order('position DESC')->column('tname');
                    return  get_status(0,$result);
                break;
        }
        //修改当前图表位置
        if(!($movePosition == $position)) {
            $update = Db::name('screenchart')->where('tid',$input['tid'])->update(['position' => $movePosition]);
        }else {
            $update = 1;
        }

        //判断是否修改成功
        if(!$update) {
            return get_status(1,'移动失败',2014);
        }else{
            $result = Db::name('screenchart')->where('screenid',$screenid)->order('position DESC')->column('tname');
            return  get_status(0,$result);
        }

        
    }

    /**
     * 查看数据数据源显示结果
     */
    public function getDataSource() 
    {
        //接收数据
        $input = input('get.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //查询数据库(不知道库和的信息)
        $data = Db::name('screen')->where('id',1)->field('data')->find();
        //返回数据源
        if (!$data) {
            return get_status(1,'获取数据源失败',000);
        }else {
            return get_status(0,$data);
        }
    }

    /**
     * 图表数据映射
     * data arr 需要映射的数据 key arr 作为键的值 status 是否是静态数据 did 数据表中的daid
     * return  array data[data] 映射后的数组 data[success] 成功的键
     */
    public function Mapping($input = [])
    {
        //判断是否dbconfig是否有值
        if(empty($input)) {
             //接收数据
            $input = input('post.');
            // return $input;
            //判断数据是否接收成功
            if(!$input) {
                return get_status(1,'数据接收失败',2000);
            }
       }

        //取出需要映射的key
        $keys = $input['map'];
        //遍历keys
        foreach($keys as $key => $value) {
            //判断映射目标值是否为空
            if($value[1] == ''){
                //如果为空则用默认映射值替换
                $keys[$key][1] = $keys[$key][0];
            }
        }
        //处理不同类型的传值使其在下面运行时保持键一致
        // dump($input);die;
        $input = $this->getInput($input);
        //处理不同类型的数据
        $data = $this->getdata($input);
        // dump($data);
        //判断data是否为空,或者是否为数组
        if(empty($data) || !is_array($data)) {
            if(isset($data['err'])) {
                //判断是否需要返回data
                if(isset($input['returnData'] )) {
                    if($input['returnData'] == true) {
                        return get_status(0,[]);
                    } else {
                        return get_status($data['err'] , $data['msg'] , $data['code'] );
                    }
                }else {
                    return get_status($data['err'] , $data['msg'] , $data['code'] );
                }
            }else {
                return get_status(0,['success' => [],'ismatch' => false]);
            }
        }

        //判断data中的value是否是关联数组用于返回值类型统一
        foreach($data as $key => $value) {
            $vali = $this->isAssocArray($value);
            if($vali) {
                //获取数组的长度
                $count = count($value);
                //将数组的第一个元素拿出
                $arr0 = $data[$key][0];
                //将数组的第一个元素销毁
                unset($data[$key][0]);
                //将数组的第一个元素保存至最后一个
                $data[$key][$count] = $arr0;
            }
        }



        //定义返回数组,便于存储
        $arr = [];
        $i = 0;
        //定义所有替换之后键的存储数组用户验证完整性
        $allKeys = [];
        //定义成功匹配的字段名
        $success = [];
        //将map中必须映射的字段取出
        $must = [];
        //遍历data
        foreach($data as $key => $value) {
            // //遍历键
            foreach($keys as $k => $val ) {
                //判断是否为必须匹配的字段
                if(!isset($val[3])) {
                    //将必须匹配的字段加入到must数组中
                    if( !in_array($val[0] , $must)) {
                        $must[] = $val[0];
                    }
                }
                //判断allKeys数组长度是否与原来键的数组长度
                if(count($allKeys) <  count($val)) {
                    $allKeys[] = $val[0];
                }
                //判断$val[1]是否是存在并且不是空值
                if(isset($val[1]) && $val[1] != ''){
                    //判断data数组中是否有关于keys数组中符合的值
                    if(isset($value[$val[1]])) {
                        //查询$val[0]是否保存入$success数组
                        if( !in_array($val[0] , $success)) {
                            $success[] = $val[0];
                        }
                        //将匹配成功的项加入新数组
                        $arr[$i][$val[0]] = $value[$val[1]];
                        //删除目标数组
                        unset($data[$key][$val[1]]);
                        //将匹配成功的项加入原数组
                        $data[$key][$val[0]] = $value[$val[1]]; 
                    }
                }
                
            }
            $i++;
        }
        //判断$arr是否匹配成功
        if (!empty($arr)) {
            //是否验证完整性
            if(0) {
                //验证数组完整性
                foreach($arr as $key => $value) {
                   
                    //定义是否完整性标识
                    $j = 0;
                    foreach($must as $k => $val ) {
                                if(!isset($value[$val])) {
                                    $j = 1;
                                }
                            }   
                    //如果不完整则删除
                    if($j) {
                        unset($arr[$key]);
                    }
                }
            }
            
        }else {
            $i = 0;
            //如果匹配结果不存在则返回原来数组,在原来数组中查询默认字段
            foreach($data as $key => $value) {
                foreach ($keys as $k => $val) {
                    //判断data的value中是否有下标为默认字段的值
                    if(isset($value[$val[0]]) && $val[1] == '') {
                        $arr[$i][$val[0]] = $value[$val[0]];
                        // $arr[][$val[0]] = $value[$val[0]];
                        //查询$val[0]是否保存入$success数组
                        if( !in_array($val[0] , $success)) {
                            $success[] = $val[0];
                        }
                    }
                }
                $i++;
            }
        }
        //判断匹配成功的字段中是否全部包含必须映射的字段
        $ismatch = true;
        //遍历must的字段
        foreach ($must as $key => $value) {
            //判断must字段中每一个字段是否都在成功数组里面
            if(!in_array($value , $success)) {
                $ismatch = false;
            }
        }
        // dump($data);die;
        //判断是否需要返回data
        if(isset($input['returnData'] )) {
            if($input['returnData'] == true) {
                //实例化处理图像对象
                $chart = new Chart();
                //使用处理入口方法
                $arr = $chart->index($input['chartType'],$arr);
                return $arr;
            } 
        }else {
            if(isset($data['err'])) {
                return get_status($data['err'] , $data['msg'] , $data['code'] );
            }else {
                return get_status(0 , [/*'data' => $data ,*/ 'success' =>$success,'ismatch' => $ismatch]);
            }
        }
    }

    /**
     * 处理传值
     */
    protected function getInput($input)
    {
        //修改前端传值使其保持一致方便下面操作
        switch ($input['type']) {
            case "STATIC" :

                break;
            case "Excel/Csv" :
                    if(isset($input['selectDaid'])) {
                        $input['selectedId'] = $input['selectDaid'];
                        unset($input['selectDaid']);
                    }else {
                        $input['selectedId'] = '';
                    }
                break;
            case "API" :
                if(isset($input['apiURL'])) {
                    $input['url'] = $input['apiURL'];
                    unset($input['apiURL']);
                }else {
                    $input['url'] = '';
                }
                if(isset($input['selectDaid'])) {
                    $input['selectedId'] = $input['selectDaid'];
                    unset($input['selectDaid']);
                }else {
                    $input['selectedId'] = '';
                }
                break;
            case "SQL" :
                if(isset($input['selectDaid'])) {
                    $input['selectedId'] = $input['selectDaid'];
                    unset($input['selectDaid']);
                }else {
                    $input['selectedId'] = '';
                }
                break;
            case "WebSocket" :
                if(isset($input['socketURL'])) {
                    $input['url'] = $input['socketURL'];
                    unset($input['socketURL']);
                }else {
                    $input['url'] = '';
                }
                if(isset($input['selectDaid'])) {
                    $input['selectedId'] = $input['selectDaid'];
                    unset($input['selectDaid']);
                }else {
                    $input['selectedId'] = '';
                }
                break;
            case "自定义视图" :
                if(isset($input['selectDaid'])) {
                    $input['selectedId'] = $input['selectDaid'];
                    unset($input['selectDaid']);
                }else {
                    $input['selectedId'] = '';
                }
                break;
        }
        return $input;
    }

    /**
     * 判断数组是否是关联数组
     */
    protected function isAssocArray($arr)  
    {  
        if(!is_array($arr)){
            return false;
        }
        $i = 0;
        foreach($arr as $value) {
            if(!isset($arr[$i])) {
                return false;
            }
            $i++;
        }
        return true;
    }

    /**
     * 获取data
     */
    protected function getData($input)
    {
        $data = [];
        switch ($input['type']) {
            case "STATIC" :
                //取出需要匹配的data
                $data = $input['sdata'];
                //判断是否是前端请求有TID
                if(isset($input['tid'])) {
                    //将数据库中的tdata修改为data
                    if(!empty($data)) {
                        $update = Db::name('screenchart')->where('tid',$input['tid'])->update(['tdata' => json_encode($data)]);
                    }  
                    unset($input['tid']);
                }
                break;
            case "Excel/Csv" :
                //从数据库中取出响应的值
                $data = $this->getDatament($input['selectedId']);
                break;
            case "API" :
                //判断是否有URL
                if($input['url'] == "") {
                    //从数据库中取值
                    $data = $this->getDatament($input['selectedId']);
                }else {
                    // $redis = new RedisModel();
                    // dump($input);
                    // // dump(getRedisKeyByApi($input['url']));
                    // $result = $redis->getkeysString(getRedisKeyByApi($input['url']));
                    
                    // // dump($result);die;
                    // if (!$result) return $data = ['err' => 1 , 'msg' => 'API请求失败','code' => 0000];
                    // if(is_array($result)) {
                    //     $data= $this->toArray($result);
                    // }else {
                    //     $data = json_decode($result,1);
                    //     if($data) {
                    //         $data = $this->toArray($data);
                    //     }
                    // }
                    try{
                        //直接从API里面取值
                        $result = file_get_contents($input['url']);
                        //判断$result是否是二维数组
                        if(is_array($result)) {
                            $data= $this->toArray($result);
                        }else {
                            $data = json_decode($result,1);
                            if($data) {
                                $data = $this->toArray($data);
                            }
                        }
                    } catch (\Exception $e) {
                        return $data = ['err' => 1 , 'msg' => 'API请求失败','code' => 0000];
                    }
                }
                break;
            case "SQL" :
                //判断是否有URL
                if($input['sqlstr'] == "") {
                    //从数据库中取值
                    $data = $this->getDatament($input['selectedId']);
                }else {
                    //从数据库中拿到数据库配置ID
                    $configID = Db::name('datament')->where('daid' , $input['selectedId'])->find();
                    //判断是否查询成功
                    if(!$configID) {
                        return $data = ['err' => 1 , 'msg' => '数据库ID请求失败','code' => 0000];
                    }
                    //通过数据库配置ID拿到数据库配置
                    $DbConfig = Db::name('databasesource')->where('baseid' , $configID['sid'])->find();
                    //判断是否查询成功
                    if(!$DbConfig) {
                        return $data = ['err' => 1 , 'msg' => '数据库配置查询失败','code' => 0000];
                    }else {
                        $DbConfig = json_decode($DbConfig['baseconfig'],1);
                    }
                    //连接数据库执行sql
                    try{
                        $result = Db::connect($DbConfig)->query("$input[sqlstr]");
                    } catch (\Exception $e) {
                        return $data = ['err' => 1 , 'msg' => '执行sql失败','code' => 0000];
                    }
                    //判断$result是否是二维数组
                    if(is_array($result)) {
                        $data= $this->toArray($result);
                    }else {
                        $data = json_decode($result,1);
                        if($data) {
                            $data = $this->toArray($data);
                        }
                    }

                }
                break;
            case "WebSocket" :
                //判断是否有URL
                if($input['url'] == "") {
                    //从数据库中取值
                    $data = $this->getDatament($input['selectedId']);
                }else {
                    try{
                        //直接从API里面取值
                        $result = file_get_contents($input['url']);
                        //判断$result是否是二维数组
                        if(is_array($result)) {
                            $data= $this->toArray($result);
                        }else {
                            $data = json_decode($result,1);
                            if($data) {
                                $data = $this->toArray($data);
                            }
                        }
                    } catch (\Exception $e) {
                        return $data = ['err' => 1 , 'msg' => 'websock请求失败','code' => 0000];
                    }
                }
                break;
            case "自定义视图" :
                //从数据库中取值
                $result = $this->getDatament($input['selectedId']);
                $data= $this->toArray($result);
                break;
        }
        return $data;            
    }

    /** 
     * 取出datament中的数据
     */
    public function getDatament($did)
    {
       
        //不是静态数据在数据库中取出数据
        $res = Db::name('datament')->where('daid',intval($did))->field('data,datatype,filepath')->find();
        
        //处理API类型
        if($res['datatype'] == 'api' || $res['datatype'] == 'websocket') {

            try{
                //直接从API里面取值
                $result = file_get_contents($res['filepath']);

                //判断$result是否是二维数组
                if(is_array($result)) {
                    $data= $this->toArray($result);
                }else {
                    $data = json_decode($result,1);
                    if($data) {
                        $data = $this->toArray($data);
                    }
                }
            } catch (\Exception $e) {
                $data = [];
            } 
        }else {
            //将data转换成数组
            $data = json_decode($res['data'],1);
        }
        return $data;
    }

    /**
     * 转换成为二维数组
     */
    protected function toArray($result)
    {
        if(!is_array($result)){
            return [];
        } 
        //遍历查询是否是二维数组
        foreach($result as $key => $value) {
            //不是二维数组销毁改值
            if(!is_array($value)) {
                unset($result[$key]);
            }
        }
        return $result;
    }



    /**
     * 添加发布信息
     * 
     */
    public function addRelease()
    {
        //查询系统是否设置为不发布
        $config = configJson();
        if(!$config['config']['system']['publish']){
            return get_status(1,'当前系统设置为不能发布',6002);
        }
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //验证屏幕是否发布或是否存在
        $screen = Db::name('screen')->where('id',$input['pid'])->find();
        if(!$screen) {
            return get_status(1,'大屏不存在',2020);
        }
        //判断大屏是否已经发布
        $vali  = Db::name('publish')->where('scid',$input['pid'])->find();
        if($vali) {
            return get_status(1,'不能重复发布',6001);
        }
        //判断大屏发布类型
        if($input['type'] == 'page'){
            $input['type'] = 1;
            $data = "";
            $input['shid'] = 0;
        }else{
            //如果为历史快照,则生成当前大屏配置及图表
            $input['type'] = 2;
            //将pid改为当前大屏ID
            $input['shid'] = $this->snapshot($input['pid']);
        }
        if(isset($input['token'])) {
            $input['token'] = '';
        }

        //将创建时间加入数组
        $data= [
            'createtime' => time(),
            'scid' => $input['pid'],
            'sname' => $input['name'],
            'link' => $input['url'],
            'is_pwd' => $input['is_pwd'],
            'token' => $input['token'],
            'expiredate' => time() + $input['testTime'],
            'ptype' => $input['type'],
            'pdata' => $input['data'],
            'shid' => $input['shid'],
        ];
        
        //获取发布用户的用户名
        $publishuser = $this->getUserName();
        //将发布用户名加入到大屏
        Db::name('screen')->where('id',$input['pid'])->update(['publishuser' => $publishuser]);
        //通过发布用户名获取用户ID
        $uid = Db::name('user')->where('username',$publishuser)->field('uid')->find();
        //将uid存入发布列表中
        $data['uid'] = $uid['uid'];
        //判断是否设置密码
        if(isset($input['password'])) {
            //判断密码是否为空
            if(!empty($input['password'])) {
                //解密加入发布列表中
                $data['password'] = decrypt($input['password'],$input['len']);
            }
        }
        //判断是否有发布封面
        if(isset($input['img'])) {
            //判断发布封面是否为空
            if(empty($input['img'])) {
                //默认发布封面
                $data['img'] = '/static/img/medical.png';
            }else {
                //将发布封面加入发布列表
                $data['img'] = $input['img'];
            }
        }else {
            //默认发布封面
            $data['img'] = '/static/img/medical.png';
        }
        
        //将所有信息插入数据库
        $insert = Db::name('publish')->insert($data);
        //将大屏信息修改成已发布
        $update = Db::name('screen')->where('id',$input['pid'])->update(['publish' =>1 ]);
        //判断是否成功
        if(!$insert) {
            return get_status(1,'发布失败',6002);
        }else {
            return get_status(0,'发布成功');
        }
    }

    /**
     * 处理历史快照
     * $pid 发布的大屏ID
     */
    protected function snapshot($pid)
    {
        //复制当前大屏配置信息
        $screenData = Db::name('screen')->where('id',$pid)->find();
        //销毁id
        unset($screenData['id']);
        //将大屏类型修改为发布信息
        $screenData['screentype'] = 1;
        //将大屏名字改为当前名字+ 历史快照
        $screenData['name'] = $screenData['name'] .'Snapshot' . mt_rand(0,9999);
        //插入数据库
        $inset = Db::name('screen')->insert($screenData);
        //获取大屏自增ID
        $screenid = Db::name('screen')->getLastInsID();
        //查询大屏相关图表
        $screenchart = Db::name('screenchart')->where('screenid',$pid)->select();
        //判断大屏是否有图表
        if($screenchart) {
            //遍历所有图表信息
            foreach ($screenchart as $key => $value) {
                //销毁自增ID
                unset($screenchart[$key]['tid']);
                //将大屏关联修改为当前快照大屏
                $screenchart[$key]['screenid'] = $screenid;
                //设置创建及修改时间
                $screenchart[$key]['createtime'] = time();
                $screenchart[$key]['updatetime'] = time();
                //将配置文件读取
                $tconfig = json_decode($value['tconfig'],1);
                //判断是否有dataOpt
                if(!isset($tconfig['dataOpt'])) {
                    continue;
                }
                //将数据源配置取出
                $source = $tconfig['dataOpt']['source'];
                //判断数据源是否为静态数据
                if($source['type'] == "STATIC") {
                    continue;
                }
                //取出map
                $map = $tconfig['dataOpt']['map'];
                //将映射关系,数据源配置及tid加入数组
                $source['map'] = $map;
                $source['tid'] = $value['tid'];
                //处理不同类型的传值使其在下面运行时保持键一致
                $source = $this->getInput($source);
                //获取图表数据
                $data = $this->responseData($source);
                //删除配置项中的source
                unset($tconfig['dataOpt']['source']);
                //添加配置项中['dataOpt']['source']['type']为STATIC
                $tconfig['dataOpt']['source']['type'] = "STATIC";
                //将自动更新关闭
                $tconfig['dataOpt']['autoUpdate'] = false;
                //将数据存入图表的data中   
                $screenchart[$key]['tdata'] =  json_encode($data,JSON_UNESCAPED_UNICODE);
                //将配置转为json格式并存入配置项
                $screenchart[$key]['tconfig'] = json_encode($tconfig,JSON_UNESCAPED_UNICODE);
            }
            //插入图表信息
            $screeninsert = Db::name('screenchart')->insertAll($screenchart);
        }
        return $screenid;
    }

    /**
     * 通过token 获取用户名
     */
    public function getUserName(){
        //获取当前的token
        $header = get_all_header();
        //判断token是否存在
        if(!isset($header['token'])){
            //没有token返回NoUser
            return 'NoUser';
        }else{
            //将token加入到变量
            $token = $header['token'];
        }
        //通过token查询用户id 
        $uid = Db::name('token')->where('token',$token)->field('uid')->find();
        //判断查询是否成功
        if(!$uid){
            //uid
            return 'NoUser';
        }
        //通过uid获取用户信息
        $user = Db::name('user')->where('uid' , $uid['uid'])->field('username')->find();
        //判断查询是否成功
        if(!$user){
            //user
            return 'NoUser';
        }
        //返回user
        return $user['username'];
    }


    /**
     * 发布列表
     */
    public function releaseList()
    {
        //接收get数据
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
			$get['order'] = 'pid';
		}else{
			//去掉首尾空格
			$get['order'] = rtrim(strtolower($get['order']));
		}
		//判断是否有sid
		if(!isset($get['sid'])) {
			$get['sid'] = 0;
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

        //获取发布用户的用户名
        $publishuser = $this->getUserName();
        //通过发布用户名获取用户ID
        $uid = Db::name('user')->where('username',$publishuser)->field('uid')->find();
        //通过uid获取用户权限
        $role = Db::name('user_role')->where('uid',$uid['uid'])->find();
        
        if($get['sid'] == 0 ){
            if($role['rid'] == 1 ) {
                $data = Db::name('publish')->where('sname' , 'like' , "%".$get['searchword']."%")
                                        ->page($currentPage.','.$pageSize)
                                        ->order($get['order'] .' DESC')
                                        ->field('pid,scid,sname,is_pwd,expiredate,link,createtime,ptype,pdata,viewsnum,password')
                                        ->select();
                //查询总条数
                $total = Db::name('publish')->where('sname' , 'like' , "%".$get['searchword']."%")->count();
            }else {
                $data = Db::name('publish')->where('sname' , 'like' , "%".$get['searchword']."%")
                                        ->where('uid',$uid['uid'])
                                        ->page($currentPage.','.$pageSize)
                                        ->order($get['order'] .' DESC')
                                        ->field('pid,scid,sname,is_pwd,expiredate,link,createtime,ptype,pdata,viewsnum,password')
                                        ->select();
                //查询总条数
                $total = Db::name('publish')->where('sname' , 'like' , "%".$get['searchword']."%")->where('uid',$uid['uid'])->count();
            }
            
        }else {
            if($role['rid'] == 1 ) {
                $data = Db::name('publish')->where('sname' , 'like' , "%".$get['searchword']."%")
                                            ->where('sid' , $get['sid'])
                                            ->page($currentPage.','.$pageSize)
                                            ->order($get['order'] .' DESC')
                                            ->field('pid,scid,sname,is_pwd,expiredate,link,createtime,ptype,pdata,viewsnum,password')
                                            ->select();
                //查询总条数
                $total = Db::name('publish')->where('sid' , $get['sid'])->where('sname' , 'like' , "%".$get['searchword']."%")->count();
            }else {
                $data = Db::name('publish')->where('sname' , 'like' , "%".$get['searchword']."%")
                                            ->where('sid' , $get['sid'])
                                            ->where('uid',$uid['uid'])
                                            ->page($currentPage.','.$pageSize)
                                            ->order($get['order'] .' DESC')
                                            ->field('pid,scid,sname,is_pwd,expiredate,link,createtime,ptype,pdata,viewsnum,password')
                                            ->select();
                //查询总条数
                $total = Db::name('publish')->where('sid' , $get['sid'])->where('sname' , 'like' , "%".$get['searchword']."%")->where('uid',$uid['uid'])->count();
            }
        }
        //遍历data
        foreach($data as $key => $value) {
            //将type改成字符串
            if($value['ptype'] == 1) {
                $data[$key]['ptype'] = '实时画面';
            }else {
                $data[$key]['ptype'] = '页面快照';
            }
            $data[$key]['createtime'] = date('Y-m-d H:i:s' , $value['createtime']);
            $data[$key]['expiredate'] = date('Y-m-d H:i:s' , $value['expiredate']);
        } 
        //返回
        return ['err' => 0,'data' => $data , 'total' => $total];
    }

    /**
     * 获取单个发布信息
     * pid 发布信息ID
     */
    public function getRelease()
    {
        //接收数据
        $input = input('get.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        $data = Db::name('publish')->where('pid',$input['pid'])->field('pid,scid,sname,is_pwd,expiredate,link,createtime,ptype,pdata,viewsnum')->select();
        return get_status(0,$data);

    }

    /**
     * 删除发布信息
     * pid 发布信息ID
     */
    public function deleteRelease()
    {
        //接收数据
        $input = input();
        //判断数据是否接收成功
        if(!isset($input['pid'])) {
            return get_status(1,'数据接收失败',2000);
        }
        $pid = explode(',',$input['pid']);

        $err = 0;
        //遍历pid
        foreach ($pid as $value) {
            //查询发布类型
            $type = Db::name('publish')->where('scid' , intval($value))->find();

            //判断是否为快照
            if($type['ptype'] == 1) {
                 //将大屏信息修改成未发布
                $update = Db::name('screen')->where('id',intval($value))->update(['publish' => 0]);
                if(!$update) {
                    $err = 1;
                }
            } else {
                //将大屏信息修改成未发布
                $update = Db::name('screen')->where('id',intval($value))->update(['publish' => 0]);
                if(!$update) {
                    $err = 1;
                }
                //查找快照大屏关联的大屏
                $shid = Db::name('publish')->where('scid',intval($value))->field('shid')->find();
                //如果是快照删除大屏
                $deleteScreen = Db::name('screen')->where('id' , intval($shid['shid']))->delete();
                if(!$deleteScreen) {
                    $err = 1;
                }
                //删除大屏下图表
                $deleteChart = Db::name('screenchart')->where('screenid',intval($shid['shid']))->delete();
                if(!$deleteChart) {
                    $err = 1;
                }
            }  
            //删除发布信息
            $data = Db::name('publish')->where('scid' , intval($value))->delete();
            if(!$data) {
                $err = 1;
            }
        }

        if($err) {
            return get_status(1,'取消发布失败',6003);
        }else {
            return get_status(0,'取消发布成功');
        }



    }

    /**
     * 修改发布信息
     */
    public function updateRelease()
    {
        //接收数据
        $input = input('put.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败');
        }
        //判断密码是否为空
        if(isset($input['paswword'])) {
            if(!empty($input['paswword'])) {
                    $password = decrypt($input['password'],$input['len']);
                    unset($input['password']);
                    unset($input['len']);
                    $input['password'] = $password;
                }
        }
        //查询数据是否更改
        $vali = Db::name('publish')->where($input)->find();
        //判断是否更改
        if($vali) {
            return get_status(0,'修改成功');
        }
        //如果有更改修改数据库
        $update = Db::name('publish')->where('pid',$input['pid'])->update($input);

        if(!$update) {
            return get_status(1,'修改失败',6004);
        }else {
            return get_status(0,'修改成功');
        }
    }

    /**
     * 图表数值预警
     */
    public function numericalWarning()
    {
        //接收数据
        $input = input();
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //取出需要匹配的key
        $keys = $input['key'];
        //去除需要匹配的data
        $data = $input['data'];
        //遍历data
        foreach($data as $key => $value) {
            //遍历最大值
            foreach($keys as $k => $val ) {    
               //判断data中是否有下边为val[0]键的数据
                if(isset($value[$val['key']])) {  
                    //$val[max]为最大值 $val[min]为最小值
                    if($value[$val['key']]  >= $val['max'] ) {
                        $data[$key][$val['key']] = '超出预算值';   
                    }
                    if($value[$val['key']]  <= $val['min'] ) {
                         $data[$key][$val['key']] = '低于预算值';
                    }
                 }
            }
        }
        //返回数据
        return get_status(0,$data);
    }

    /**
     * 收藏
     */
    public function collection()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        if($input['status']) {
            //查询图表信息
            $tconfig = Db::name('screenchart')->where('tid', $input['tid'])->field('tconfig')->find();
            //修改收藏标识
            $update = Db::name('screenchart')->where('tid', $input['tid'])->update(['collection' => $input['status']]);
            //将图表配置加入到收藏数据库
            $insert = Db::name('collection')->insert(['tconfig' => $tconfig['tconfig'],'tid' => $input['tid']]);
            if(!$insert) {
                return get_status(1,'收藏失败',2016);
            }else {
                return get_status(0,'收藏成功');
            }
        }else {
            //修改收藏标识
            $update = Db::name('screenchart')->where('tid', $input['tid'])->update(['collection' => $input['status']]);
            //将图表配置加入到收藏数据库
            $delete = Db::name('collection')->where('tid',$input['tid'])->delete();
            if(!$delete) {
                return get_status(1,'取消收藏失败',2017);
            }else {
                return get_status(0,'取消收藏成功');
            }
        }
    }

    /**
     * 查看收藏
     */
    public function getCollection()
    {
        $collection = Db::name('collection')->field('tid, tconfig')->select();

        return get_status(0,$collection);
    }

    /**
     * 查看数据类型映射
     */
    public function getDataType()
    {
        //查询数据类型
        $databasesList = Db::name('datamentname')->column('name');
        return get_status(0,$databasesList);
    }


    /**
     * 查看响应结果
     */
    public function responseResults()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //处理不同类型的传值使其在下面运行时保持键一致
        $input = $this->getInput($input);
        //处理不同类型的数据
        $data = $this->responseData($input);

        //取出需要映射的key
        $keys = $input['map'];
        //判断data中的value是否是关联数组用于返回值类型统一
        foreach($data as $key => $value) {
            $vali = $this->isAssocArray($value);
            if($vali) {
                //获取数组的长度
                $count = count($value);
                //将数组的第一个元素拿出
                $arr0 = $data[$key][0];
                //将数组的第一个元素销毁
                unset($data[$key][0]);
                //将数组的第一个元素保存至最后一个
                $data[$key][$count] = $arr0;
            }
        }

        //定义返回数组,便于存储
        $arr = [];
        $i = 0;
        //遍历data
        foreach($data as $key => $value) {
            // //遍历键
            foreach($keys as $k => $val ) {
                //判断$val[1]是否是存在并且不是空值
                if(isset($val[1]) && $val[1] != ''){
                    //判断data数组中是否有关于keys数组中符合的值
                    if(isset($value[$val[1]])) {
                        //删除目标数组
                        unset($data[$key][$val[1]]);
                        //将匹配成功的项加入原数组
                        $data[$key][$val[0]] = $value[$val[1]];
                        
                    }
                }
                
            }
            $i++;
        }
        
        if(empty($data)) {
            return get_status(0 , ['data' => [] , 'success' =>[]]);
        }else {
            return get_status(0, $data);
        }
    }


    /**
     * 获取data
     */
    protected function responseData($input)
    {
        
        $data = [];
        switch ($input['type']) {
            case "STATIC" :
                //取出需要匹配的data
                $data = $input['sdata'];  
                break;
            case "Excel/Csv" :
                //从数据库中取出响应的值
                $data = $this->getDatament($input['selectedId']);
                break;
            case "API" :
                //判断是否有URL
                if($input['url'] == "") {
                    //从数据库中取值
                    $data = $this->getDatament($input['selectedId']);
                }else {
                    try{
                        //直接从API里面取值
                        $result = file_get_contents($input['url']);
                        //判断$result是否是二维数组
                        if(is_array($result)) {
                            $data= $this->toArray($result);
                        }else {
                            $data = json_decode($result,1);
                            if($data) {
                                $data = $this->toArray($data);
                            }
                        }
                    } catch (\Exception $e) {
                        return $data = ['err' => 1 , 'msg' => 'API请求失败','code' => 0000];
                    }
                }
                break;
            case "SQL" :
                //判断是否有URL
                if($input['sqlstr'] == "") {
                    //从数据库中取值
                    $data = $this->getDatament($input['selectedId']);
                }else {
                    //从数据库中拿到数据库配置ID
                    $configID = Db::name('datament')->where('daid' , $input['selectedId'])->find();
                    //判断是否查询成功
                    if(!$configID) {
                        return $data = ['err' => 1 , 'msg' => '数据库请求ID失败','code' => 0000];
                    }
                    //通过数据库配置ID拿到数据库配置
                    $DbConfig = Db::name('databasesource')->where('baseid' , $configID['sid'])->find();
                    //判断是否查询成功
                    if(!$DbConfig) {
                        return $data = ['err' => 1 , 'msg' => '数据库配置请求失败','code' => 0000];
                    }else {
                        $DbConfig = json_decode($DbConfig['baseconfig'],1);
                    }
                    //连接数据库执行sql
                    try{
                        $result = Db::connect($DbConfig)->name($DbConfig['database'])->query("$input[sqlstr]");
                    } catch (\Exception $e) {
                        return $data = ['err' => 1 , 'msg' => '执行sql失败','code' => 0000];
                    }
                    //判断$result是否是二维数组
                    if(is_array($result)) {
                        $data= $this->toArray($result);
                    }else {
                        $data = json_decode($result,1);
                        if($data) {
                            $data = $this->toArray($data);
                        }
                    }
                }
                break;
            case "WebSocket" :
                //判断是否有URL
                if($input['url'] == "") {
                    //从数据库中取值
                    $data = $this->getDatament($input['selectedId']);
                }else {
                    try{
                        //直接从API里面取值
                        $result = file_get_contents($input['url']);
                        //判断$result是否是二维数组
                        if(is_array($result)) {
                            $data= $this->toArray($result);
                        }else {
                            $data = json_decode($result,1);
                            if($data) {
                                $data = $this->toArray($data);
                            }
                        }
                    } catch (\Exception $e) {
                        return $data = ['err' => 1 , 'msg' => 'websock请求失败','code' => 0000];
                    }
                }
                break;
            case "自定义视图" :
                //从数据库中取值
                $data = $this->getDatament($input['selectedId']);
                
                break;
        }
        return $data;            
    }

    /**
     * 获取大屏全部图表中的数据
     */
    public function getAllChart($input = [])
    {
        if (!$input) $input = input('get.');
        if(isset($input['chartid'])) {
            $where = ['tid'=> $input['chartid']];
        } else {
            $where = ['screenid'=> $input['id']];
        }
        $obj = new \app\common\controller\Processingdata();
        $data = $obj->index($where);
        return get_status(0,$data);
    //     //判断是否dbconfig是否有值
    //     if(empty($input)) {
    //         //接收数据
    //        $input = input('get.');
    //        // return $input;
    //        //判断数据是否接收成功
    //        if(!$input) {
    //            return get_status(1,'数据接收失败',2000);
    //        }
    //   }
    //   //判断是否是发布查询,用于将ID修改为历史快照
    //   if(isset($input['sharetype'])){
    //     $input['id'] = $this->getpublishId($input['id']);
    //   }
      
    //     //判断传值是否为chartid判断查询大屏or图表
    //     if(isset($input['chartid'])) {
    //         //获取大屏的ID
    //         $chartid  = $input['chartid'];
    //         //查询大屏相关图表
    //         $result = Db::name('screenchart')->where(['tid'=> $chartid])
    //                                         ->field('tid,tname,tdata,tconfig,islock,ishide,position,talias,collection')
    //                                         ->select();
                                            
    //     }else {
    //         //获取大屏的ID
    //         $screenid  = $input['id'];
    //         //查询大屏相关图表
    //         $result = Db::name('screenchart')->where(['screenid'=> $screenid])
    //                                         ->field('tid,tname,tdata,tconfig,islock,ishide,position,talias,collection')
    //                                         ->order('position DESC')
    //                                         ->select();
    //     }
        
    //     //对图表进行数据的转换
    //     $data = $this->chartData($result);
    //     //返回数据
    //     return get_status(0,$data);

    }

    /**
     * 增对每个图表进行数据的处理
     */
    protected function chartData( $result )
    {
                //声明图表新数组
                $newArr = [];
                //声明数据新数组
                $chartData  = [];

                //遍历数据
                foreach ($result as $value) {
                    //以图表名字为下标作为键去除config中source
                    $sources = json_decode($value['tconfig'] , 1);
                    if(!isset($sources['dataOpt']['source'])) {
                        continue;
                    } 
                    //取出图形中的映射信息
                    $source = $sources['dataOpt']['source'];
                    //将映射信息加入新数组
                    $newArr[$value['tname']] = $source;
                    //给定标识需要返回data
                    $newArr[$value['tname']]['returnData'] = true;
                    if(!isset($sources['charttype'])) {
                        continue;
                    } 
                    //给图表类型加入新数组
                    $newArr[$value['tname']]['chartType'] = $sources['charttype'];
                    //判断图表数据是不是STATIC
                    if($newArr[$value['tname']]['type'] == 'STATIC'){
                        $newArr[$value['tname']]['sdata'] = json_decode($value['tdata'],1);
                    }        
                    //判断图表是否有map
                    if(!isset($sources['dataOpt']['map'])) {
                        $newArr[$value['tname']]['map'] = [['name', ''],["value" , '']];    
                    }else {
                        $newArr[$value['tname']]['map'] = $sources['dataOpt']['map'];
                    }
                    // dump($newArr);
                    //将新数组使用mappin方法
                    // dump($sources);
                    $data = $this->Mapping($newArr[$value['tname']]);
                    // dump($data);die;
                    if(isset($data['err']) && $data['err'] == 0) {
                        $chartData[$value['tname']] = [];
                    }else {
                        $chartData[$value['tname']] = $data;
                    }
                    //判断数据是否为空,如果为空则返回图表的名字
                    if(empty($chartData[$value['tname']])) {
                        //通过图表名查询配置
                        $tconfig = Db::name('screenchart')->where('tname' , $value['tname'])->field('tconfig')->find();
                        //格式化配置
                        $tconfig = json_decode($tconfig['tconfig'],1);
                        //定义随机返回数据错误信息
                        $errorArr =['数据获取失败','数据映射失败','映射关系不正确','获取数据中存在错误']; 
                        //获取随机错误信息
                        $errorNo = mt_rand(0,3);
                        //将图表名字存储到空数组
                        $chartData[$value['tname']] =  '['.$tconfig['name'].']图表,' . $errorArr[$errorNo];
                    }
                    
                }
                //返回数据
                return $chartData;
    }

    /**
	 * 获取发布大屏快照ID
	 */
	public function getpublishId($id)
	{
		//取出大屏的信息
		$data = Db::name("screen")->where('id',$id)->find();
		//判断是否发布
		if(!$data['publish']) {
			return get_status(1,'大屏未发布',2026);
		}
		//获取发布信息查看是否是历史快照发布
		$publish = Db::name('publish')->where('scid',$id)->find();
		//将发布浏览次数+1
		// Db::name('publish')->where('scid',$id)->update(['viewsnum' => $publish['viewsnum']+1]);
		//判断是否是历史快照发布
		if($publish['ptype'] == 2) {
			//如果是历史快照发布则使用历史快照ID
			return $publish['shid'];
		}else {
			return $id;
		}

	}


    /**
     * 获取数据源  发布待处理
     */
    public function getsource()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //获取用户token
        $arr = get_all_header();
        if(isset($arr['token'])){
            $this->token = $arr['token'];    
            //通过token获取用户的uid
            $uid = Db::name('token')->where('token',$this->token)->field('uid')->find();
            //通过uid获取用户的sid
            $category = Db::name('user')->where('uid',$uid['uid'])->field('sid')->find();	
            $cate = explode(',',$category['sid']);
            //分组 分类
            $datatype = Db::name('screengroup')->where('sid','in',$cate)->field('sid,screenname')->select();
        }else {
            $datatype = Db::name('screengroup')->field('sid,screenname')->select();
        }

        for($i = 0;$i < count($datatype); $i++) {
            $screenname[] = $datatype[$i]['screenname'];
        }
        //查询相关数据源
        $source = Db::name('datament')->where('cid' , 'in' , $screenname)
                                      ->where('datatype',strtolower($input['datatype']))
                                      ->field('daid,dataname,filepath,returnsql')
                                      ->select();
        return get_status(0,$source);
    }


    /**
     * 查看关于sql及自定义视图的数据源
     */
    public function getSqlSource()
    {
        //接收数据
        $input = input('post.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        $keys = ["id","type"];
        //验证传值
        $vali = valiKeys($keys , $input);
        //判断传值是否满足
        if($vali['err'] != 0) {
            return get_status(1,$vali['data']);
        }

        if(strtolower($input['type']) == 'sql') {
            //查询关于数据库源的数据源
            $result = Db::name('datament')->where(['sid' => $input['id'],'datatype' => 'sql'])
                                        ->field('daid,sid,returnsql,dataname')
                                        ->select();
        }else {
            //查询关于数据库源的数据源
            $result = Db::name('datament')->where(['sid' => $input['id'],'datatype' => '自定义视图'])
                                        ->field('daid,sid,data,dataname')
                                        ->select();
                                        
            foreach ( $result as $key => $value) {
                $result[$key]['data'] = $this->toArray(json_decode($value['data'],1));
            }
        }
        //直接返回数据
        return get_status(0,$result);
    }

    /**
     * 记录自动刷新开始时间
     * $chartid
     */
    public function recordAutoRefresh()
    {
        //接收数据
        $input = input('get.');
        //判断数据是否接收成功
        if(!$input) {
            return get_status(1,'数据接收失败',2000);
        }
        //修改自动刷新开始时间
        $update = Db::name('screenchart')->where('tid' , $input['chartid'])->update(['autoupdatetime' => time()]);
    }

    /**
     * 获取静态数据
     */
    public function getStatusData($input = [])
    {
        //判断是否dbconfig是否有值
        if(empty($input)) {
            //接收数据
           $input = input('get.');
           // return $input;
           //判断数据是否接收成功
           if(!$input) {
               return get_status(1,'数据接收失败',2000);
           }
      }
      //获取图表的静态数据
      $statusData = Db::name('screenchart')->where("tid",$input['tid'])->field('tdata,tconfig')->find();
      //获取图表的config
      $config = json_decode($statusData['tconfig'],1);
      //  判断是否有图表数据type
      if (isset($config['dataOpt']['source'])) {
        //将图表cinfig中的type改为static
        $config['dataOpt']['source'] = ["type" => "STATIC"];
      }
      //将图表configjson化
      $config = json_encode($config,JSON_UNESCAPED_UNICODE);
      //将配置文件修改入库
      $updateConfig = Db::name('screenchart')->where("tid",$input['tid'])->update(['tconfig' => $config]);
      if($statusData) {
        $status = json_decode($statusData['tdata'],1);
      }else  {
        $status = [];
      }

      //获取处理完成之后的数据
      $resultData = $this->getAllChart(['chartid'=> $input['tid'],'sdata' => $status]);
      
      //获取图表具体的数据
      if(isset(array_values($resultData['data'])[0])) {
        $resultData = array_values($resultData['data'])[0];
      }else {
        $resultData = [];
      }
      //返回数据
      return get_status(0,['statusData' => $status,'resultData' => $resultData]);
    }
}
