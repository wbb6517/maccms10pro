<?php
namespace app\admin\controller;
use think\Db;
class App extends Base
{
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 弹窗配置
     **/
    public function window()
    {
        $config_old = config('app');
        if (Request()->isPost()) {
            $config = input();
            $config_new['popupwindo'][0]['status'] = $config['status1'];
            $config_new['popupwindo'][0]['title'] = $config['title1'];
            $config_new['popupwindo'][0]['content'] = $config['content1'];

            $config_new['popupwindo'][1]['status'] = $config['status2'];
            $config_new['popupwindo'][1]['title'] = $config['title2'];
            $config_new['popupwindo'][1]['content'] = $config['content2'];

            $config_new['popupwindo'][2]['status'] = $config['status3'];
            $config_new['popupwindo'][2]['title'] = $config['title3'];
            $config_new['popupwindo'][2]['content'] = $config['content3'];

            $config_new['popupwindo'][3]['status'] = $config['status4'];
            $config_new['popupwindo'][3]['title'] = $config['title4'];
            $config_new['popupwindo'][3]['content'] = $config['content4'];

            $config_new['popupwindo'][4]['status'] = $config['status5'];
            $config_new['popupwindo'][4]['title'] = $config['title5'];
            $config_new['popupwindo'][4]['content'] = $config['content5'];

            $config_new['popupwindo'][5]['status'] = $config['status6'];
            $config_new['popupwindo'][5]['title'] = $config['title6'];
            $config_new['popupwindo'][5]['content'] = $config['content6'];

            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('nav', $this->nav('window'));
        $this->assign('config', $config_old);
        $this->assign('title', '弹窗配置');

        return $this->fetch('admin@app/window');
    }

    /**
     * 任务中心
     **/
    public function task()
    {
        $config_old = config('app');

        if (Request()->isPost()) {

            $config = input();
            $config_new['task']['article']['title'] = $config['title'];
            $config_new['task']['article']['status'] = $config['status'];

            // 签到配置
            $config_new['task']['sign']['status'] = $config['sign_status'];
            $config_new['task']['sign']['reward']['points'] = $config['sign_points'];
            $config_new['task']['sign']['title'] = $config_old['task']['sign']['title'];
            $config_new['task']['sign']['info'] = $config_old['task']['sign']['info'];

            // 评论
            $config_new['task']['comment']['status'] = $config['comment_status'];
            $config_new['task']['comment']['reward']['points'] = $config['comment_points'];
            $config_new['task']['comment']['reward_num'] = $config['comment_reward_num'];
            $config_new['task']['comment']['title'] = $config_old['task']['comment']['title'];
            $config_new['task']['comment']['info'] = $config_old['task']['comment']['info'];

            //点赞
            $config_new['task']['dianzan']['status'] = $config['dianzan_status'];
            $config_new['task']['dianzan']['reward']['points'] = $config['dianzan_points'];
            $config_new['task']['dianzan']['title'] = $config_old['task']['dianzan']['title'];
            $config_new['task']['dianzan']['info'] = $config_old['task']['dianzan']['info'];

            //评分
            $config_new['task']['mark']['status'] = $config['mark_status'];
            $config_new['task']['mark']['reward']['points'] = $config['mark_points'];
            $config_new['task']['mark']['reward_num'] = $config['mark_reward_num'];
            $config_new['task']['mark']['title'] = $config_old['task']['mark']['title'];
            $config_new['task']['mark']['info'] = $config_old['task']['mark']['info'];

            //弹幕
            $config_new['task']['danmu']['status'] = $config['danmu_status'];
            $config_new['task']['danmu']['reward']['points'] = $config['danmu_points'];
            $config_new['task']['danmu']['reward_num'] = $config['danmu_reward_num'];
            $config_new['task']['danmu']['title'] = $config_old['task']['danmu']['title'];
            $config_new['task']['danmu']['info'] = $config_old['task']['danmu']['info'];

            //分享
            $config_new['task']['share']['status'] = $config['share_status'];
            $config_new['task']['share']['reward']['points'] = $config['share_points'];
            $config_new['task']['share']['reward_num'] = $config['share_reward_num'];
            $config_new['task']['share']['title'] = $config_old['task']['share']['title'];
            $config_new['task']['share']['info'] = $config_old['task']['share']['info'];

            //分享
            $config_new['task']['view30m']['status'] = $config['view30m_status'];
            $config_new['task']['view30m']['reward']['points'] = $config['view30m_points'];
            $config_new['task']['view30m']['reward_num'] = $config['view30m_reward_num'];
            $config_new['task']['view30m']['title'] = $config_old['task']['share']['title'];
            $config_new['task']['view30m']['info'] = $config_old['task']['share']['info'];


            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('nav', $this->nav('task'));
        $this->assign('config', $config_old['task']);
        $this->assign('title', '弹窗配置');
        return $this->fetch('admin@app/task');
    }

    /**
     * 版本管理
     **/
    public function version(){
        $param = input();
        $list = [];
        $res = model('AppVersion')->listData($param, ['version desc']);
        if ($res['code'] == 1){
            $list = $res['list'];
        }
        $this->assign('list',$list);
        $this->assign('title','定时任务管理');

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('nav', $this->nav('version'));
        $this->assign('title', '版本管理');
        return $this->fetch('admin@app/version');
    }

    public function version_info()
    {
        $param = input();
        $param['app_version_id'] = $param['id'];
        if (Request()->isPost()) {

            $res = model('AppVersion')->saveData($param);
            if($res['code'] > 1){
                return $this->error($res['msg']);
            }

            return $this->success('保存成功!');
        }
        $where = [];
        $where['app_version_id'] = $param['id'];
        $res = model('AppVersion')->infoData($where);
        $info = $res['info'];
        $this->assign('info',$info);
        $this->assign('title','版本信息');
        return $this->fetch('admin@app/version_info');
    }

    public function version_del()
    {
        $param = input();
        $where=[];
        $where['app_version_id'] = ['in',$param['ids']];
        $res = model('AppVersion')->delData($where);
        if($res['code'] > 1){
            return $this->error('删除失败，请重试!');
        }

        return $this->success('删除成功!');
    }

    /**
     * 基础广告
     **/
    public function setting()
    {
        $config_old = config('app');
        if (Request()->isPost()) {
            $config = input();
            $config_new['app_setting'] = $config['app_setting'];
            $config_new = array_merge($config_old, $config_new);
            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('nav', $this->nav('setting'));
        $this->assign('title', '基础广告');
        $this->assign('config', $config_old);
        return $this->fetch('admin@app/setting');
    }

    /**
     * 在线支付配置
     **/
    public function pay()
    {
        $config_old = config('app');
        if (Request()->isPost()) {
            $config = input();
            $config_new['pay'] = $config['pay'];

            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $path = './application/common/extend/pay';
        $file_list = glob($path . '/*.php',GLOB_NOSORT );
        $ext_list = [];
        $ext_html = '';
        foreach($file_list as $k=>$v) {
            $cl = str_replace([$path . '/', '.php'], '', $v);
            $cp = 'app\\common\\extend\\pay\\' . $cl;

            if (class_exists($cp)) {
                $c = new $cp;
                $ext_list[$cl] = $c->name;

                if(file_exists( './application/admin/view/extend/pay/'.strtolower($cl) .'.html')) {
                    $ext_html .= $this->fetch('admin@extend/pay/' . strtolower($cl));
                }
            }
        }
        $this->assign('ext_list',$ext_list);
        $this->assign('ext_html',$ext_html);
        $this->assign('http_type',$GLOBALS['http_type']);
        $this->assign('config', $config_old);
        $this->assign('nav', $this->nav('pay'));
        $this->assign('title', '在线支付配置');
        return $this->fetch('admin@app/pay');
    }

    /**
     * 广告管理
     **/
    public function ad(){
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        $order='sort asc,create_time desc';
        $res = model('Adtype')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('nav', $this->nav('ad'));
        $this->assign('title', '版本管理');
        return $this->fetch('admin@app/ad');
    }

    public function ad_info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Adtype')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }


        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res = model('Adtype')->infoData($where);
        $this->assign('info',$res['info']);
        return $this->fetch('admin@app/ad_info');
    }

    public function ad_del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            $res = model('Adtype')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    public function ad_field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['status']) && in_array($val,['0','1'])){
            $where=[];
            $where['id'] = ['in',$ids];

            $res = model('Adtype')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    /**
     * 直播列表
     **/
    public function live()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['zhibo'] = $config['zhibo'];

            $config_old = config('app');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }
        $this->assign('config', config('app'));
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];

        $order='id asc';
        $res = model('Zhibo')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('nav', $this->nav('live'));
        $this->assign('title','直播列表');
        return $this->fetch('admin@app/live');
    }

    public function live_info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Zhibo')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res = model('Zhibo')->infoData($where);
        $this->assign('info',$res['info']);
        return $this->fetch('admin@app/live_info');
    }

    public function live_del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            $res = model('Zhibo')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    /**
     * 游戏管理
     **/
    public function game(){
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        $order='id asc';
        $res = model('Youxi')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('nav', $this->nav('game'));
        $this->assign('title', '游戏管理');
        return $this->fetch('admin@app/game');
    }

    public function game_info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Youxi')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }


        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res = model('Youxi')->infoData($where);
        $this->assign('info',$res['info']);
        return $this->fetch('admin@app/game_info');
    }

    public function game_del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            $res = model('Youxi')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    /**
     * 消息通知
     **/
    public function mes(){

        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];

        $order='id desc';
        $res = model('Message')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('nav', $this->nav('mes'));
        $this->assign('title','消息通知列表');
        return $this->fetch('admin@app/mes');

    }

    public function mes_info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Message')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }


        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res = model('Message')->infoData($where);
        $this->assign('info',$res['info']);
        return $this->fetch('admin@app/mes_info');
    }

    public function mes_del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            $res = model('Message')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    /**
     * 自定义体专
     **/
    public function jump(){

        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        $order='id asc';
        $res = model('GroupChat')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title','群聊列表');

        $this->assign('nav', $this->nav('jump'));
        return $this->fetch('admin@app/jump');
    }

    public function jump_info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('GroupChat')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res = model('GroupChat')->infoData($where);
        $this->assign('info',$res['info']);
        return $this->fetch('admin@app/jump_info');
    }

    public function jump_del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            $res = model('GroupChat')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    /**
     * 二开新增
     **/
    public function lock(){
        
                        $config_old = config('app');
        

        $path=__DIR__."/../../api/controller/v1/mogai_a.php";
        $menus=require $path;
        if (Request()->isPost()) {
            
            //基础广告
                  $config = input();
            $config_new['app_setting'] = $config['app_setting'];
            $config_new = array_merge($config_old, $config_new);
            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);

            
            //播放前广告
            $param = input();
            file_put_contents($path, "<?php\nreturn " . var_export($param, true) . ";\n");
            //var_dump($param);die;
            
                        if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
            
            
            
          // return $this->success('ok');
        }
        $this->assign('config', $config_old);
        
        $this->assign('menus',$menus);
        $this->assign('nav', $this->nav('lock'));
        $this->assign('title', '二开新增');
        return $this->fetch('admin@app/lock');
        
        

        if (Request()->isPost()) {
      
        }





        

    }

    /**
     * 删除模块
     **/
    public function del(){
        $param = input();
        $config = config('appsql');

        if(strlen($param['id']) == 0 || empty($param['type'])){
            return $this->error('参数错误');
        }

        unset($config[$param['type']][$param['id']]);

        $res = mac_arr2file(APP_PATH . 'extra/appsql.php', $config);
        if ($res === false) {
            return $this->error('保存失败，请重试!');
        }
        return $this->success('保存成功!');

    }

    /**
     * 添加编辑模块
     **/
    public function info(){
        $param = input();
        $config = config('appsql');

        if (Request()->isPost()) {

            if(strlen($param['id']) > 0){
                $config[$param['uid']][$param['id']] = $param;
            }else{
                $config[$param['uid']][] = $param;
            }

            $res = mac_arr2file(APP_PATH . 'extra/appsql.php', $config);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('info', $config[$param['type']][$param['id']]);
        $this->assign('key', $param['id']);
        $this->assign('uid', $param['type']);
        return $this->fetch('admin@app/info'.$param['type']);

    }

    /**
     * 基础设置
     **/
    public function basics(){

        $config_old = config('app');
        if (Request()->isPost()) {
            $config = input();
            //$config_new['basics'] = $config;
            $config_new = array_merge($config_old, $config);
            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', $config_old);
        $this->assign('nav', $this->nav('basics'));
        $this->assign('title', '基础设置');
        return $this->fetch('admin@app/basics');

    }

    /**
     * 主页
     **/
    public function index(){

        $config_old = config('app');
        if (Request()->isPost()) {
            $config = input();
            //$config_new['basics'] = $config;
            $config_new = array_merge($config_old, $config);
            $res = mac_arr2file(APP_PATH . 'extra/app.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', $config_old);
        $this->assign('nav', $this->nav('index'));
        $this->assign('title', '基础设置');
        return $this->fetch('admin@app/index');

    }

    /**
     * 首页分类
     **/
    public function type(){

        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=['pid'=>0];
        $order='sort asc,create_time desc';
        $res = model('category')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('nav', $this->nav('type'));
        $this->assign('param',$param);
        $this->assign('title','栏目分类管理');
        return $this->fetch('admin@app/type');

    }

    public function info2()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('category')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res = model('category')->infoData($where);
        $this->assign('info',$res['info']);
        $catList = db("category")->where("pid=0")->select();
        $this->assign('catList',$catList);
        return $this->fetch('admin@app/info');
    }


    public function del2()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            $res = model('category')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }


//删除播放器
    public function del3(){
        $param = input();
        $config = config('vodplayer');

        if(strlen($param['ids']) == 0 ){
            return $this->error('参数错误');
        }

        unset($config[$param['ids']]);

        $res = mac_arr2file(APP_PATH . 'extra/vodplayer.php', $config);
        if ($res === false) {
            return $this->error('删除失败，请重试!');
        }
        return $this->success('删除成功');

    }



    public function field2()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['status']) && in_array($val,['0','1'])){
            $where=[];
            $where['id'] = ['in',$ids];

            $res = model('Category')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }


    public function export()
    {
        $param = input();
        $list = config($this->_pre);
        $info = $list[$param['id']];
        if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }

        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
            header("Content-Disposition: attachment; filename=mac_" . urlencode($info['from']) . '.txt');
        }
        else{
            header("Content-Disposition: attachment; filename=mac_" . $info['from'] . '.txt');
        }
        echo base64_encode(json_encode($info));
    }
    
    
    
    
       public function import()
    {
        $file = $this->request->file('file');
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
        if ($info) {
            $data = json_decode(base64_decode(file_get_contents($info->getpathName())), true);
            @unlink($info->getpathName());
            if($data){

                if(empty($data['status']) || empty($data['from']) || empty($data['sort']) ){
                    return $this->error('格式错误');
                }
                $code = $data['code'];
                unset($data['code']);

                $list = config($this->_pre);
                $list[$data['from']] = $data;
                $res = mac_arr2file( APP_PATH .'extra/'.$this->_pre.'.php', $list);
                if($res===false){
                    return $this->error('保存配置文件失败，请重试!');
                }

                $res = fwrite(fopen('./static/player/' . $data['from'].'.js','wb'),$code);
                if($res===false){
                    return $this->error('保存代码文件失败，请重试!');
                }

            }
            return $this->success('导入失败，请检查文件格式');
        }
        else{
            return $this->error($file->getError());
        }
    }


    /**
     * 解析设置
     **/
    public function play(){
        $list = config('vodplayer');
        $this->assign('list',$list);
        $this->assign('title','播放器管理');
        $this->assign('nav', $this->nav('play'));
        return $this->fetch('admin@app/play');
    }
    public function play_info()
    {
        $param = input();
        $list = config('vodplayer');
        if (Request()->isPost()) {
            if($param['features'] == ''){
                $param['features'] = '.*?.mp4,.*?.m3u8';
                 $code = $param['code'];
            unset($param['code']);
                
                if(is_numeric($param['from'])){
                $param['from'] .='_';
            }
                 
            }
            $param['parse2'] = implode(',',  $param['parse2']);
            $param['issethead'] =str_replace(PHP_EOL,"||",$param['issethead']);
            $param['headers'] =str_replace(PHP_EOL,"||",$param['headers']);


            $list[$param['from']]['target'] = $param['target'];
            $list[$param['from']]['des'] = $param['des'];
            $list[$param['from']]['ps'] = $param['ps'];
            $list[$param['from']]['from'] = $param['from'];
            $list[$param['from']]['tip'] = $param['tip'];
            $list[$param['from']]['sort'] = $param['sort'];
            $list[$param['from']]['show'] = $param['show'];
            $list[$param['from']]['status'] = $param['status'];
            $list[$param['from']]['features'] = $param['features'];
            $list[$param['from']]['headers'] = $param['headers'];
            $list[$param['from']]['issethead'] = $param['issethead'];
            $list[$param['from']]['kernel'] = $param['kernel'];
            $list[$param['from']]['parse2'] = $param['parse2'];
             $list[$param['from']]['parse'] = $param['parse'];

            $res = mac_arr2file( APP_PATH .'extra/vodplayer.php', $list);
            if($res===false){
                return $this->error('保存配置文件失败，请重试!');
            }
            
              $res = fwrite(fopen('./static/player/' . $param['from'].'.js','wb'),$code);
            if($res===false){
                return $this->error('保存代码文件失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $info = $list[$param['id']];
            if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }
        
        
        $info['parse2'] = explode(',', $info['parse2']);
        $info['parse2'] ? $info['parse2'] : [] ;
        $info['issethead'] = str_replace('||',PHP_EOL, $info['issethead']);
        $info['headers'] = str_replace('||',PHP_EOL, $info['headers']);
        $this->assign('info',$info);
        $this->assign('title','解析编辑');
        return $this->fetch('admin@app/play_info');
    }

    public function cuican(){
        $this->assign('nav', $this->nav('cuican'));
        return $this->fetch('admin@app/welcome');
    }

    /**
     * 导航代码
     **/
    public function nav($key){

        $nav = [
            "cuican"  => "ICUICAN",
            "basics"  => "基础设置",
            "type"  => "首页分类",
            "window"  => "弹窗配置",
            "task"  => "任务中心",
            "version"  => "版本管理",
             "lock"  => "广告设置",
            //"setting"  => "基础广告",
            "ad"  => "全局广告",
            "live"  => "直播管理",
            "game"  => "游戏管理",
            "mes"  => "消息通知",
            "play"  => "解析配置",
            "jump"  => "自定义链",
           
        ];

        $html = '';
        foreach ($nav as $k => $v){
            if($key == $k){
                $html = $html.'<li class="layui-nav-item layui-this"><a href="'.url($k).'">'.$v.'</a></li>';
            }else{
                $html = $html.'<li class="layui-nav-item"><a href="'.url($k).'">'.$v.'</a></li>';
            }
        }

        return $html;

    }
}

