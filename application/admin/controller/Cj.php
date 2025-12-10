<?php
/**
 * 自定义采集控制器 (Custom Collection Controller)
 * ============================================================
 *
 * 【文件说明】
 * 自定义采集模块的后台管理控制器
 * 支持通过配置规则从外部网站采集视频/文章数据
 * 与系统内置的资源站采集不同，本模块支持自定义采集规则
 *
 * 【菜单位置】
 * 后台管理 → 采集管理 → 自定义采集
 *
 * 【数据表】
 * - mac_cj_node    : 采集节点配置表 (存储采集规则)
 * - mac_cj_content : 采集内容暂存表 (采集后待入库的数据)
 * - mac_cj_history : 采集历史记录表 (URL去重)
 *
 * 【采集流程】
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  1. 创建节点 → 2. 配置规则 → 3. 字段映射 → 4. 采集网址         │
 * │       ↓                                          ↓              │
 * │  5. 采集内容 ← ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┘              │
 * │       ↓                                                         │
 * │  6. 内容入库 → 7. 完成 (数据写入 mac_vod 或 mac_art)           │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                        │
 * ├─────────────────┼────────────────────────────────────────────────┤
 * │ index()         │ 采集节点列表页                                   │
 * │ info()          │ 新增/编辑采集节点                                │
 * │ program()       │ 字段映射配置 (采集字段→数据库字段)               │
 * │ col_all()       │ 一键采集 (网址+内容+入库)                        │
 * │ col_url()       │ 采集网址列表                                     │
 * │ col_content()   │ 采集内容详情                                     │
 * │ publish()       │ 已采集内容管理列表                               │
 * │ show()          │ 查看单条采集内容详情                             │
 * │ content_del()   │ 删除采集内容                                     │
 * │ content_into()  │ 将采集内容入库到正式表                           │
 * │ show_url()      │ 测试网址规则生成                                 │
 * │ del()           │ 删除采集节点                                     │
 * │ export()        │ 导出节点配置 (Base64+JSON)                       │
 * │ import()        │ 导入节点配置                                     │
 * └─────────────────┴────────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/cj/index       → 节点列表
 * admin.php/cj/info        → 添加/编辑节点
 * admin.php/cj/program     → 字段映射
 * admin.php/cj/col_url     → 采集网址
 * admin.php/cj/col_content → 采集内容
 * admin.php/cj/publish     → 内容管理
 * admin.php/cj/content_into→ 内容入库
 *
 * 【相关文件】
 * - application/common/model/Cj.php        : 采集数据模型
 * - application/common/util/Collection.php : 采集工具类 (核心解析逻辑)
 * - application/common/model/Collect.php   : 数据入库模型
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use app\common\util\Collection as cjOper;

class Cj extends Base
{
    /**
     * 一键采集标识
     * 1=执行一键采集模式 (自动执行: 采集网址→采集内容→入库)
     * 0=单步采集模式
     * @var int
     */
    var $_isall=0;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 采集节点列表页
     * ============================================================
     *
     * 【功能说明】
     * 显示所有已配置的采集节点列表
     * 支持分页浏览
     *
     * 【页面结构】
     * ┌────────────────────────────────────────────────────┐
     * │ 工具栏: [添加节点] [导入节点]                        │
     * ├────────────────────────────────────────────────────┤
     * │ 列表: 节点名称 | 模块 | 编码 | 最后采集 | 操作       │
     * ├────────────────────────────────────────────────────┤
     * │ 分页导航                                            │
     * └────────────────────────────────────────────────────┘
     *
     * @return mixed 视图输出
     */
    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=[];

        $order='nodeid desc';
        $res = model('Cj')->listData('cj_node',$where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/cj/title'));

        return $this->fetch('admin@cj/index');
    }


    /**
     * ============================================================
     * 新增/编辑采集节点
     * ============================================================
     *
     * 【功能说明】
     * GET  : 显示采集节点配置表单
     * POST : 保存采集节点配置
     *
     * 【配置项说明】
     * - name         : 节点名称
     * - mid          : 模块类型 (1=视频 2=文章)
     * - sourcetype   : 列表源类型 (1=序列 2=单页)
     * - urlpage      : 列表URL规则 (支持 {page} 占位符)
     * - pagesize_start/end : 分页范围
     * - url_rule     : 内容URL匹配规则
     * - title_rule   : 标题匹配规则
     * - content_rule : 内容匹配规则
     * - customize_config : 自定义字段配置 (JSON)
     *
     * @return mixed 视图输出或JSON响应
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input();
            $data = $param['data'];
            $data['urlpage'] = (string)$param['urlpage'.$data['sourcetype']];
            if(!empty($data['customize_config'])){
                $customize_config = $data['customize_config'];
                unset($data['customize_config']);
                foreach ($customize_config['name'] as $k => $v) {
                    if (empty($v) || empty($customize_config['name'][$k])) continue;
                    $data['customize_config'][] = array('name'=>$customize_config['name'][$k], 'en_name'=>$customize_config['en_name'][$k], 'rule'=>$customize_config['rule'][$k], 'html_rule'=>$customize_config['html_rule'][$k]);
                }
                $data['customize_config'] = json_encode($data['customize_config'],JSON_FORCE_OBJECT);
            }
            $res = model('Cj')->saveData($data);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['nodeid'] = ['eq',$id];
        $res = model('Cj')->infoData('cj_node',$where);
        if(!empty($res['info']['customize_config'])){
            $res['info']['customize_config'] = json_decode($res['info']['customize_config'],true);
        }
        $this->assign('data',$res['info']);
        $this->assign('title',lang('admin/cj/title'));
        return $this->fetch('admin@cj/info');
    }

    /**
     * ============================================================
     * 字段映射配置
     * ============================================================
     *
     * 【功能说明】
     * 配置采集字段与数据库字段的映射关系
     * 支持为每个字段指定处理函数
     *
     * 【映射配置】
     * program_config JSON 结构:
     * {
     *   "map": {
     *     "vod_name": "title",    // 数据库字段 => 采集字段
     *     "type_id": "type",
     *     "vod_content": "content"
     *   },
     *   "funcs": {
     *     "vod_name": "strip_tags", // 字段处理函数
     *     "type_id": "",
     *     "vod_content": ""
     *   }
     * }
     *
     * @return mixed 视图输出或JSON响应
     */
    public function program()
    {
        $param = input();
        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if (Request()->isPost()) {
            $program_config = [];
            foreach($param['model_field'] as $k=>$v){
                if(!empty($param['node_field'][$k])){
                    $program_config['map'][$v] = $param['node_field'][$k];
                    $program_config['funcs'][$v] = $param['funcs'][$k];
                }
            }
            $update=[];
            $update['nodeid'] = $param['id'];
            $update['program_config'] = json_encode($program_config);
            $res = model('Cj')->saveData($update);
            if($res['code']>1){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $program_config = [];
        if(!empty($res['info']['program_config'])){
            $program_config = json_decode($res['info']['program_config'],true);
        }
        $this->assign('program_config',$program_config);


        $node_field = array('title'=>lang('title'),'type'=>lang('type'), 'content'=>lang('content'));
        $customize_config = [];
        if(!empty($res['info']['customize_config'])){
            $customize_config = json_decode($res['info']['customize_config'],true);
        }

        if (is_array($customize_config)) foreach ($customize_config as $k=>$v) {
            if (empty($v['en_name']) || empty($v['name'])) continue;
            $node_field[$v['en_name']] = $v['name'];
        }
        $this->assign('node_field',$node_field);

        $table = 'vod';
        if($res['info']['mid'] =='2'){
            $table='art';
        }
        $column_list = Db::query('SHOW COLUMNS FROM '.config('database.prefix').$table);
        $this->assign('column_list',$column_list);
        $this->assign('param',$param);
        return $this->fetch('admin@cj/program');
    }

    /**
     * 一键采集入口
     * 设置 _isall=1 后调用 col_url()
     * 自动执行: 采集网址 → 采集内容 → 入库
     *
     * @param array $param 请求参数
     */
    public function col_all($param)
    {
        $this->_isall=1;
        $this->col_url($param);
    }


    /**
     * ============================================================
     * 采集网址列表
     * ============================================================
     *
     * 【功能说明】
     * 根据节点配置的URL规则，从目标网站采集内容列表页
     * 解析出所有内容详情页的URL，存入 cj_content 表
     *
     * 【采集流程】
     * 1. 根据 urlpage 规则生成列表页URL数组
     * 2. 逐页抓取列表页HTML
     * 3. 用 url_rule/title_rule 解析出内容URL和标题
     * 4. MD5去重后存入 cj_content 表 (status=1)
     * 5. 多页时自动跳转到下一页
     *
     * 【内容状态 status】
     * 1 = 已采集网址，待采集内容
     * 2 = 已采集内容，待入库
     * 3 = 已入库完成
     *
     * @param array $param 请求参数 (id=节点ID, page=当前页)
     * @return mixed 视图输出
     */
    public function col_url($param=[]) {
        if(empty($param)){
            $param = input();
        }

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $data = $res['info'];
        $collection = new cjOper();
        $urls = $collection->url_list($data);

        $total_page = count($urls);
        if (empty($total_page)){
            return $this->error(lang('admin/cj/url_list_err'));
        }

        $param['page'] = isset($param['page']) ? intval($param['page']) : 1;

        $url_list = $urls[$param['page']-1];
        $url = $collection->get_url_lists($url_list, $data);

        $total = count($url);
        $re = 0;
        if (is_array($url) && !empty($url)) {
            foreach ($url as $v) {
                if (empty($v['url']) || empty($v['title'])) {
                    $re++;
                    continue;
                }
                $v['title'] = strip_tags($v['title']);
                $md5 = md5($v['url']);
                $where=[];
                $where['md5'] = $md5;
                $history = model('Cj')->infoData('cj_history',$where);
                if($history['code']>1){
                    Db::name('cj_history')->insert(array('md5' => $md5));
                    Db::name('cj_content')->insert(array('nodeid'=>$param['id'], 'status'=>1, 'url'=>$v['url'], 'title'=>$v['title']));
                }
                else {
                    $re++;
                }
            }
        }
        if ($total_page <= $param['page']) {
            $time = time();
            Db::name('cj_node')->where('nodeid',$param['id'])->update(array('lastdate' => $time));
        }
        if($this->_isall==1){
            mac_echo(lang('admin/cj/url_cj_complete'));
            $this->col_content($param);
            exit;
        }
        $this->assign('param',$param);
		$this->assign('url_list', $url_list);
		$this->assign('total_page', $total_page);
		$this->assign('re', $re);
		$this->assign('url', $url);
		$this->assign('page',$param['page']);
		$this->assign('total',$total);
        $this->assign('title',lang('admin/cj/url/title'));
        if($total_page > $param['page']){
            mac_echo(lang('server_rest'));
            $param['page'] ++;
            $link = url('cj/col_url') . '?'. http_build_query($param);
            mac_jump( $link ,3);
        }
        else{
            mac_echo(lang('admin/cj/url_cj_complete'));
        }
        return $this->fetch('admin@cj/col_url');
    }

    /**
     * ============================================================
     * 采集内容详情
     * ============================================================
     *
     * 【功能说明】
     * 根据已采集的URL列表，逐个访问详情页采集内容
     * 将采集到的数据存入 cj_content.data 字段 (JSON格式)
     *
     * 【采集流程】
     * 1. 查询 status=1 的待采集内容
     * 2. 访问详情页URL获取HTML
     * 3. 用配置的规则解析出各字段内容
     * 4. JSON序列化后存入 data 字段，status 改为 2
     * 5. 分批处理，每批20条
     *
     * @param array $param 请求参数 (id=节点ID)
     */
    public function col_content($param=[]) {
        if(empty($param)){
            $param = input();
        }

        $collection = new cjOper();
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $total = isset($_GET['total']) ? intval($_GET['total']) : 0;

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $data = $res['info'];

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        if(empty($total)){
            $total = Db::name('cj_content')->where('nodeid',$param['id'])->where('status',1)->count();
        }
        $limit = 20;
        $total_page = ceil($total/$limit);
        mac_echo(lang('admin/cj/content/tip',[$total,$total_page,$limit,$page]));

        $list = Db::name('cj_content')->where('nodeid',$param['id'])->where('status',1)->page($total_page-1,$limit)->select();

        $i = 0;
        $ids=[];
        if(!empty($list) && is_array($list)){
            foreach($list as $v){
                $html = $collection->get_content($v['url'],$data);
                Db::name('cj_content')->where('id',$v['id'])->update(['status'=>2, 'data'=>json_encode($html)]);
                $ids[] = $v['id'];
                $i++;

                mac_echo($v['url'].'&nbsp;&nbsp;'.'ok');
            }
        }
        else{
            mac_echo(lang('admin/cj/content_cj_complete'));
            exit;
        }

        if($this->_isall==1){
            mac_echo(lang('admin/cj/content_cj_complete'));
            $param['ids'] = implode(',',$ids);
            $param['limit'] = 999;
            $this->content_into($param);
            exit;
        }

        if ($total_page > $page){
            mac_echo(lang('server_rest'));
            $param['page'] ++;
            $link = url('cj/col_content') . '?'. http_build_query($param);
            mac_jump( $link ,3);
        }
        else{
            $time = time();
            Db::name('cj_node')->where('nodeid',$param['id'])->update(array('lastdate' => $time));
            mac_echo(lang('admin/cj/cj_complete'));
            exit;
        }
    }


    /**
     * 已采集内容管理列表
     * 查看指定节点下所有采集的内容，支持按状态筛选
     *
     * @return mixed 视图输出
     */
    public function publish()
    {
        $param = input();

        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <20 ? $this->_pagesize : $param['limit'];
        $where=[];
        $where['nodeid'] = $param['id'];
        if(!empty($param['status'])){
            $where['status'] = ['eq',$param['status']];
        }

        $order='id desc';
        $res = model('Cj')->listData('cj_content',$where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/cj/publish/title'));

        return $this->fetch('admin@cj/publish');
    }

    /**
     * 查看单条采集内容详情
     * 显示采集到的原始数据 (JSON 解码后展示)
     *
     * @return mixed 视图输出
     */
    public function show()
    {
        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $info = Db::name('cj_content')->where($where)->find();
        if(!empty($info['data'])){
            $info['data'] = @json_decode($info['data'],true);
        }
        $this->assign('info',$info);
        $this->assign('title',lang('admin/cj/title'));
        return $this->fetch('admin@cj/show');

    }

    /**
     * 删除采集内容
     * 同时清除对应的 cj_history 记录，允许重新采集
     *
     * @return \think\response\Json JSON响应
     */
    public function content_del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            if($all=='1'){
                $where['id'] = ['gt',0];
            }
            $urls = [];
            $list = Db::name('cj_content')->field('url')->where($where)->select();
            foreach ($list as $k => $v) {
                $md5 = md5($v['url']);
                $urls[] = $md5;
            }

            $where2=[];
            $where2['md5'] = ['in',$md5];
            Db::name('cj_history')->where($where2)->delete();

            $res = Db::name('cj_content')->where($where)->delete();
            if($res===false){
                return $this->error(lang('del_err').''.$this->getError());
            }
        }
        return $this->success(lang('del_ok'));
    }

    /**
     * ============================================================
     * 采集内容入库
     * ============================================================
     *
     * 【功能说明】
     * 将已采集的内容 (status=2) 根据字段映射配置
     * 转换后写入正式数据表 (mac_vod 或 mac_art)
     *
     * 【入库流程】
     * 1. 获取节点的 program_config 字段映射配置
     * 2. 遍历待入库内容，按映射转换字段
     * 3. 调用 Collect 模型的 vod_data/art_data 方法入库
     * 4. 入库成功后将 status 改为 3
     *
     * @param array $param 请求参数 (id=节点ID, ids=内容ID, all=是否全部)
     */
    public function content_into($param=[])
    {
        if(empty($param)){
            $param = input();
        }

        $nodeid = $param['id'];
        $ids = $param['ids'];
        $all = $param['all'];
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <20 ? $this->_pagesize : $param['limit'];

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $node = $res['info'];
        $where=[];
        $where['nodeid'] = $nodeid;
        $where['status'] =['eq',2];
        $where['id'] = ['in',$ids];
        if($all=='1'){
            $where['id'] = ['gt',0];
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        if(empty($param['total'])) {
            $param['total'] = Db::name('cj_content')->where($where)->count();
        }

        $list = Db::name('cj_content')->where($where)->page($param['page'],$param['limit'])->select();

        $total_page = ceil($param['total']/$param['limit']);
        mac_echo(lang('admin/cj/content_into/tip',[$param['total'],$total_page,$param['limit'],$param['page']]));

        $program_config =[];
        if(!empty($node['program_config'])){
            $program_config = json_decode($node['program_config'],true);
        }

        $inter = mac_interface_type();
        $update_ids = [];
        foreach($list as $k=>$v){
            $data=[];
            $content_data = json_decode($v['data'],true);
            foreach ($program_config['map'] as $a=>$b) {
                if (isset($program_config['funcs'][$a]) && function_exists($program_config['funcs'][$a])) {
                    $data['data'][$k][$a] = $program_config['funcs'][$a]($v['data'][$b]);
                }
                else {
                    $data['data'][$k][$a] = $content_data[$b];
                }
                if($b=='type' && !is_numeric($content_data[$b])) {

                    if($node['mid'] ==2 ) {
                        $data['data'][$k][$a] = $inter['arttype'][$content_data[$b]];
                    }
                    else{
                        $data['data'][$k][$a] = $inter['vodtype'][$content_data[$b]];
                    }
                }
            }


            if($node['mid'] == '2'){
                $res = model('Collect')->art_data([],$data,0);
            }
            else{
                $res = model('Collect')->vod_data([],$data,0);
            }
            if($res['code'] ==1){
                $update_ids[] = $v['id'];
            }
            mac_echo($res['msg']);
        }

        if(!empty($update_ids)){
            $where=[];
            $where['id'] = ['in',$update_ids];
            $res = Db::name('cj_content')->where($where)->update(['status' => 3]);
        }

        if($this->_isall==1){
            mac_echo(lang('admin/cj/content_into/complete'));
            exit;
        }


        if ($total_page > $param['page']){
            mac_echo(lang('server_rest'));
            $param['page'] ++;
            $link = url('cj/content_into') . '?'. http_build_query($param);
            mac_jump( $link ,3);
        }
        else{
            mac_echo(lang('import_ok'));
            exit;
        }
    }


    /**
     * 测试网址规则生成
     * 用于预览 URL 规则生成的列表页网址
     *
     * @return mixed 视图输出
     */
    public function show_url()
    {
        $param = input();
        $data = $param['data'];
        $data['urlpage'] = (string)$param['urlpage'.$data['sourcetype']];
        $collection = new cjOper();
        $urls = $collection->url_list($data);

        $this->assign('urls',$urls);

        return $this->fetch('admin@cj/show_url');
    }

    /**
     * 删除采集节点
     * 同时删除节点下的所有采集内容
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['nodeid'] = ['in',$ids];
            $res = model('Cj')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 导出节点配置
     * 将节点配置转为 JSON 后 Base64 编码，供下载备份
     * 文件名格式: mac_cj_{节点名称}.txt
     */
    public function export()
    {
        $param = input();

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $node = $res['info'];

        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
            header("Content-Disposition: attachment; filename=mac_cj_" . urlencode($node['name']) . '.txt');
        }
        else{
            header("Content-Disposition: attachment; filename=mac_cj_" . $node['name'] . '.txt');
        }
        echo base64_encode(json_encode($node));
    }

    /**
     * 导入节点配置
     * 上传 .txt 文件，Base64 解码后解析 JSON，创建新节点
     *
     * @return \think\response\Json JSON响应
     */
    public function import()
    {
        $file = $this->request->file('file');
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
        if ($info) {
            $data = json_decode(base64_decode(file_get_contents($info->getpathName())), true);
            @unlink($info->getpathName());
            if($data){
                unset($data['nodeid']);
                $res = model('Cj')->saveData($data);
                if($res['code']>1){
                    return $this->success($res['msg']);
                }
                return $this->success($res['msg']);
            }
            return $this->success(lang('import_err'));
        }
        else{
            return $this->error($file->getError());
        }
    }
}
