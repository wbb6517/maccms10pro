<?php
/**
 * URL推送管理控制器 (Urlsend Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台URL推送管理控制器
 * 用于向搜索引擎主动推送网站URL，提升SEO收录效果
 * 支持百度、神马、必应等多种搜索引擎推送
 *
 * 【菜单位置】
 * 后台管理 → 应用 → URL推送 (索引11 → 112)
 *
 * 【支持的内容类型】
 * - mid=1  : 视频 (Vod)
 * - mid=2  : 文章 (Art)
 * - mid=3  : 专题 (Topic)
 * - mid=8  : 演员 (Actor)
 * - mid=9  : 角色 (Role)
 * - mid=11 : 网址 (Website)
 * - mid=12 : 漫画 (Manga)
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数                                    │
 * │ index()         │ 推送配置页面                                │
 * │ data()          │ 获取待推送数据列表                          │
 * │ push()          │ 执行URL推送                                 │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/urlsend/index → 推送配置和操作页面
 * admin.php/urlsend/push  → 执行推送操作
 *
 * 【扩展机制】
 * 推送功能通过扩展类实现：app/common/extend/urlsend/
 * 每种搜索引擎对应一个扩展类，如 Baidu.php、Shenma.php 等
 *
 * 【相关文件】
 * - application/common/extend/urlsend/ : 推送扩展类目录
 * - application/admin/view_new/urlsend/index.html : 视图文件
 * - application/extra/maccms.php : 推送配置存储
 *
 * ============================================================
 */
namespace app\admin\controller;

class Urlsend extends Base
{
    /**
     * 最后处理的数据ID
     * @var string
     */
    public $_lastid='';

    /**
     * 请求参数
     * @var array
     */
    public $_param;

    /**
     * 构造函数
     * 初始化请求参数
     */
    public function __construct()
    {
        parent::__construct();
        $this->_param = input();
    }

    /**
     * ============================================================
     * 推送配置页面
     * ============================================================
     *
     * 【功能说明】
     * GET: 显示URL推送配置页面，包含各搜索引擎的配置选项
     * POST: 保存推送配置到 maccms.php
     *
     * 【页面结构】
     * - 配置标签页：各搜索引擎的API配置
     * - 推送操作区：选择推送类型、范围、执行推送
     *
     * @return mixed 渲染配置页面或JSON响应
     */
    public function index()
    {
        if (Request()->isPost()) {
            // 保存推送配置
            $config = input();
            $config_new['urlsend'] = $config['urlsend'];

            // 合并到现有配置
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            // 写入配置文件
            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        // 获取推送配置
        $urlsend_config = $GLOBALS['config']['urlsend'];
        $this->assign('config',$urlsend_config);

        // 获取可用的推送扩展列表
        $extends = mac_extends_list('urlsend');
        $this->assign('extends',$extends);


        $this->assign('title',lang('admin/urlsend/title'));
        return $this->fetch('admin@urlsend/index');
    }

    /**
     * ============================================================
     * 获取待推送数据列表
     * ============================================================
     *
     * 【功能说明】
     * 根据模块类型(mid)获取待推送的URL数据
     * 支持今日数据和全部数据两种模式
     * 生成完整的URL地址用于推送
     *
     * 【请求参数】
     * - mid   : 模块ID (1=视频,2=文章,3=专题,8=演员,9=角色,11=网址,12=漫画)
     * - ac2   : 推送范围 (today=今日, all=全部)
     * - range : 时间字段 (0=更新时间, 1=添加时间)
     * - page  : 当前页码
     * - limit : 每页数量
     * - ids   : 指定ID列表
     *
     * @return array|void 返回数据列表或输出提示
     */
    public function data()
    {
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        $list = [];
        $mid = $this->_param['mid'];
        // 分页参数初始化
        $this->_param['page'] = intval($this->_param['page']) <1 ? 1 : $this->_param['page'];
        $this->_param['limit'] = intval($this->_param['limit']) <1 ? 50 : $this->_param['limit'];
        $ids = $this->_param['ids'];
        $ac2 = $this->_param['ac2'];

        // 时间字段选择：0=更新时间, 1=添加时间
        $col_time = 'time';
        if($this->_param['range'] == '1'){
            $col_time = 'time_add';
        }
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $col = '';

        // ==================== 根据模块类型构建查询 ====================
        switch($mid)
        {
            // 视频模块
            case 1:
                $where['vod_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['vod_'.$col_time] = ['gt',$today];
                }
                if(!empty($ids)){
                    $where['vod_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['vod_id'] = ['gt', $data];
                }

                $col = 'vod';
                $order = 'vod_id asc';
                $fun = 'mac_url_vod_detail';
                $res = model('Vod')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;

            // 文章模块
            case 2:
                $where['art_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['art_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['art_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['art_id'] = ['gt', $data];
                }

                $col = 'art';
                $order = 'art_id asc';
                $fun = 'mac_url_art_detail';
                $res = model('Art')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;

            // 专题模块
            case 3:
                $where['topic_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['topic_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['topic_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['topic_id'] = ['gt', $data];
                }

                $col = 'topic';
                $order = 'topic_id asc';
                $fun = 'mac_url_topic_detail';
                $res = model('Topic')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;

            // 演员模块
            case 8:
                $where['actor_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['actor_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['actor_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['actor_id'] = ['gt', $data];
                }
                $col = 'actor';
                $order = 'actor_id asc';
                $fun = 'mac_url_actor_detail';
                $res = model('Actor')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;

            // 角色模块
            case 9:
                $where['role_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['role_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['role_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['role_id'] = ['gt', $data];
                }
                $col = 'role';
                $order = 'role_id asc';
                $fun = 'mac_url_role_detail';
                $res = model('Role')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;

            // 网址模块
            case 11:
                $where['website_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['website_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['website_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['website_id'] = ['gt', $data];
                }
                $col = 'website';
                $order = 'website_id asc';
                $fun = 'mac_url_website_detail';
                $res = model('Website')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;

            // 漫画模块
            case 12:
                $where['manga_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['manga_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['manga_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['manga_id'] = ['gt', $data];
                }
                $col = 'manga';
                $order = 'manga_id asc';
                $fun = 'mac_url_manga_detail';
                $res = model('Manga')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;
        }

        // 无数据时提示
        if(empty($res['list'])){
            mac_echo(lang('admin/urlsend/no_data'));
            return;
        }

        // 输出统计信息
        mac_echo(lang('admin/urlsend/tip',[$res['total'],$res['pagecount'],$res['page']]));

        // 构建URL列表
        $urls = [];
        foreach($res['list'] as $k=>$v){
            // 生成完整URL
            $urls[$v[$col.'_id']] =  $GLOBALS['http_type'] . $GLOBALS['config']['site']['site_url'] . $fun($v);
            $this->_lastid = $v[$col.'_id'];

            // 输出当前处理的数据
            mac_echo($v[$col.'_id'] . '、'. $v[$col . '_name'] . '&nbsp;<a href="'.$urls[$v[$col.'_id']].'">'.$urls[$v[$col.'_id']].'</a>');
        }

        $res['urls'] = $urls;
        return $res;
    }


    /**
     * ============================================================
     * 执行URL推送
     * ============================================================
     *
     * 【功能说明】
     * 调用对应的推送扩展类执行URL推送
     * 支持分页推送，自动跳转下一页继续
     *
     * 【执行流程】
     * 1. 根据ac参数加载对应的推送扩展类
     * 2. 调用data()方法获取待推送数据
     * 3. 调用扩展类的submit()方法执行推送
     * 4. 未完成则跳转下一页继续
     *
     * @param array $pp 可选的外部参数 (用于命令行调用)
     * @return void
     */
    public function push($pp=[])
    {
        // 支持外部传参（命令行模式）
        if(!empty($pp)){
            $this->_param = $pp;
        }

        // 获取推送类型
        $ac = $this->_param['ac'];
        $cp = 'app\\common\\extend\\urlsend\\' . ucfirst($ac);

        // 检查扩展类是否存在
        if (class_exists($cp)) {
            // 获取待推送数据
            $data = $this->data();

            // 实例化扩展类并执行推送
            $c = new $cp;
            $res = $c->submit($data);

            // 推送失败处理
            if($res['code']!=1){
                mac_echo($res['msg']);
                die;
            }

            // 检查是否完成所有页
            if ($data['page'] >= $data['pagecount']) {
                mac_echo(lang('admin/urlsend/complete'));
                if(ENTRANCE=='admin') {
                    // 后台入口完成处理
                }
            }
            else {
                // 跳转下一页继续推送
                $this->_param['page']++;
                $url = url('urlsend/push') . '?' . http_build_query($this->_param);
                if(ENTRANCE=='admin') {
                    // 后台入口：页面跳转
                    mac_jump($url, 3);
                }
                else{
                    // 命令行入口：递归调用
                    $this->push($this->_param);
                }
            }

        }
        else{
            $this->error(lang('param_err'));
        }
    }

}
