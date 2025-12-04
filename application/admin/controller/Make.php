<?php
/**
 * 静态页面生成控制器 (Static Page Make Controller)
 * ============================================================
 *
 * 【文件说明】
 * 静态HTML页面生成管理控制器
 * 负责将动态页面预渲染为静态HTML文件，提升访问速度和SEO效果
 * 支持首页、分类页、详情页、专题页、RSS/Sitemap等多种页面类型
 *
 * 【菜单位置】
 * 后台管理 → 生成 (顶级菜单, 索引8)
 *
 * 【核心功能】
 * - 首页生成: PC端 index.html / 移动端 wap_index.html
 * - 分类列表页: 视频/文章分类的分页列表
 * - 内容详情页: 视频详情/播放/下载页，文章详情页
 * - 专题页面: 专题列表页和专题详情页
 * - 自定义标签页: 模板label目录下的自定义页面
 * - SEO地图: RSS/Sitemap XML文件 (百度/谷歌/360/搜狗/必应/神马)
 *
 * 【生成模式】
 * - ac2=wap : 生成移动端页面 (使用mob_template_dir模板)
 * - ac2=day : 仅生成今日更新的数据
 * - ac2=nomake : 仅生成未生成过的数据
 * - jump=1 : 一键生成模式，自动跳转到下一步
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                            │
 * ├─────────────────┼────────────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数，初始化WAP模板路径                          │
 * │ buildHtml()     │ 核心方法: 渲染模板并写入HTML文件                      │
 * │ echoLink()      │ 辅助方法: 输出带链接的生成结果                        │
 * │ opt()           │ 生成选项页: 显示所有可生成的内容选项                   │
 * │ make()          │ 生成入口: 根据ac参数分发到具体生成方法                 │
 * │ index()         │ 生成首页: index.html / wap_index.html               │
 * │ map()           │ 生成站点地图页: map.html                             │
 * │ rss()           │ 生成RSS/Sitemap XML文件                             │
 * │ type()          │ 生成分类列表页 (支持分页)                            │
 * │ topic_index()   │ 生成专题列表页 (支持分页)                            │
 * │ topic_info()    │ 生成专题详情页                                       │
 * │ info()          │ 生成内容详情页 (视频/文章)                           │
 * │ label()         │ 生成自定义标签页                                     │
 * └─────────────────┴────────────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/make/opt        → 生成选项页面
 * admin.php/make/make       → 生成执行入口
 * admin.php/make/index      → 生成首页
 * admin.php/make/map        → 生成站点地图
 * admin.php/make/rss        → 生成RSS/Sitemap
 *
 * 【相关配置】
 * - $GLOBALS['config']['view']['vod_detail']  : 视频详情页模式 (2=静态)
 * - $GLOBALS['config']['view']['vod_play']    : 播放页模式 (2=首集静态, 3=全部静态)
 * - $GLOBALS['config']['view']['art_detail']  : 文章详情页模式
 * - $GLOBALS['config']['app']['makesize']     : 每批次生成数量
 *
 * 【相关文件】
 * - application/admin/view_new/make/opt.html : 生成选项视图
 * - template/{tpl}/html/                     : 前台模板目录
 * - template/{tpl}/html/label/               : 自定义标签页模板
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use think\View;

class Make extends Base
{
    /**
     * 请求参数数组
     * @var array
     */
    var $_param;

    /**
     * ============================================================
     * 构造函数
     * ============================================================
     *
     * 【功能说明】
     * 初始化静态页面生成控制器
     * - 获取请求参数
     * - 设置生成模式标识 $GLOBALS['ismake']
     * - 如果是WAP模式(ac2=wap)，切换到移动端模板路径
     *
     * 【WAP模式】
     * 当 ac2=wap 时，自动切换以下全局路径:
     * - MAC_ROOT_TEMPLATE : 模板根目录物理路径
     * - MAC_PATH_TEMPLATE : 模板目录URL路径
     * - MAC_PATH_TPL      : HTML模板目录路径
     * - MAC_PATH_ADS      : 广告目录路径
     */
    public function __construct()
    {
        // 获取所有请求参数
        $this->_param = input();
        // 设置生成模式标识，前台模板可据此判断是否为静态生成环境
        $GLOBALS['ismake'] = '1';

        // WAP模式：切换到移动端模板目录
        if($this->_param['ac2']=='wap'){
            $TMP_TEMPLATEDIR = $GLOBALS['config']['site']['mob_template_dir'];
            $TMP_HTMLDIR = $GLOBALS['config']['site']['mob_html_dir'];
            $TMP_ADSDIR = $GLOBALS['config']['site']['mob_ads_dir'];
            // 设置移动端模板路径全局变量
            $GLOBALS['MAC_ROOT_TEMPLATE'] = ROOT_PATH .'template/'.$TMP_TEMPLATEDIR.'/'. $TMP_HTMLDIR .'/';
            $GLOBALS['MAC_PATH_TEMPLATE'] = MAC_PATH.'template/'.$TMP_TEMPLATEDIR.'/';
            $GLOBALS['MAC_PATH_TPL'] = $GLOBALS['MAC_PATH_TEMPLATE']. $TMP_HTMLDIR  .'/';
            $GLOBALS['MAC_PATH_ADS'] = $GLOBALS['MAC_PATH_TEMPLATE']. $TMP_ADSDIR  .'/';
            // 更新ThinkPHP视图路径配置
            config('template.view_path', 'template/' . $TMP_TEMPLATEDIR .'/' . $TMP_HTMLDIR .'/');
        }
        parent::__construct();
    }

    /**
     * ============================================================
     * 构建并写入HTML文件 (核心方法)
     * ============================================================
     *
     * 【功能说明】
     * 将模板渲染结果写入静态HTML文件
     * 这是整个静态生成系统的核心方法
     *
     * 【执行流程】
     * 1. 调用 label_fetch() 渲染模板获取HTML内容
     * 2. 处理文件名 (reset_html_filename)
     * 3. 自动创建目录结构
     * 4. 将内容写入文件
     *
     * @param string $htmlfile     输出文件路径 (如 vod/type/1.html)
     * @param string $htmlpath     基础路径 (通常为 './')
     * @param string $templateFile 模板文件路径 (如 vod/index)
     * @return bool 成功返回true，失败返回false
     */
    protected function buildHtml($htmlfile='',$htmlpath='',$templateFile='') {
        // 参数验证
        if(empty($htmlfile) || empty($htmlpath) || empty($templateFile)){
            return false;
        }
        // 渲染模板获取HTML内容
        $content    =   $this->label_fetch($templateFile);
        // 重置HTML文件名 (处理URL参数等)
        $htmlfile = reset_html_filename($htmlfile);
        // 获取目录路径
        $dir   =  dirname($htmlfile);
        // 自动创建目录 (递归创建)
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        // 写入文件
        if(file_put_contents($htmlfile,$content) === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * ============================================================
     * 输出生成结果链接
     * ============================================================
     *
     * 【功能说明】
     * 在生成过程中实时输出结果
     * 支持带链接和不带链接两种模式
     * 使用 ob_flush() 和 flush() 实现实时输出
     *
     * @param string $des   显示文本
     * @param string $url   链接地址 (可选，为空则不生成链接)
     * @param string $color 文字颜色 (可选)
     * @param int    $wrap  是否换行: 1=换行(<br>), 0=空格(&nbsp;)
     */
    protected function echoLink($des,$url='',$color='',$wrap=1)
    {
        if(empty($url)){
            // 无链接模式：直接输出文本
            echo  "<font color=$color>" .$des .'</font>'. ($wrap==1? '<br>':'&nbsp;');
        }
        else{
            // 有链接模式：生成可点击链接
            echo  '<a target="_blank" href="'. $url .'">'. "<font color=$color>" . $des .'</font></a>'. ($wrap==1? '<br>':'&nbsp;');
        }
        // 实时刷新输出缓冲区，让用户看到生成进度
        ob_flush();flush();
    }

    /**
     * ============================================================
     * 生成选项页面
     * ============================================================
     *
     * 【功能说明】
     * 显示静态页面生成的选项界面
     * 提供视频/文章分类、专题、自定义标签页、Sitemap等生成入口
     *
     * 【页面结构】
     * ┌──────────────────────────────────────────────────────────────┐
     * │ 视频分类区域: 分类选择器 + 操作按钮                            │
     * │   - 选中分类列表 / 全部分类 / 今日分类                         │
     * │   - 选中详情 / 全部详情 / 今日详情 / 未生成详情 / 一键今日      │
     * ├──────────────────────────────────────────────────────────────┤
     * │ 文章分类区域: 同上结构                                        │
     * ├──────────────────────────────────────────────────────────────┤
     * │ 专题列表区域: 专题选择器 + 操作按钮                            │
     * │   - 选中专题 / 全部专题 / 专题首页                             │
     * ├──────────────────────────────────────────────────────────────┤
     * │ 自定义标签页: label目录下的模板文件                            │
     * ├──────────────────────────────────────────────────────────────┤
     * │ SiteMap区域: RSS/Google/Baidu/360/Sogou/Bing/SM              │
     * │   - 页数输入框 (ps参数)                                       │
     * └──────────────────────────────────────────────────────────────┘
     *
     * @return mixed 视图输出
     */
    public function opt()
    {
        // 获取分类缓存列表
        $type_list = model('Type')->getCache('type_list');
        $this->assign('type_list',$type_list);

        // 分离视频分类和文章分类
        $vod_type_list = [];
        $vod_type_ids = [];
        $art_type_list = [];
        $art_type_ids = [];
        foreach($type_list as $k=>$v){
            // type_mid=1 为视频分类
            if($v['type_mid'] == 1){
                $vod_type_list[$k] = $v;
            }
            // type_mid=2 为文章分类
            if($v['type_mid'] == 2){
                $art_type_list[$k] = $v;
            }
        }
        // 提取分类ID列表
        $vod_type_ids = array_keys($vod_type_list);
        $art_type_ids = array_keys($art_type_list);

        $this->assign('vod_type_list',$vod_type_list);
        $this->assign('art_type_list',$art_type_list);

        // 分类ID字符串，用于"全部"按钮
        $this->assign('vod_type_ids',join(',',$vod_type_ids));
        $this->assign('art_type_ids',join(',',$art_type_ids));

        // 获取今日更新的视频分类ID
        $res = model('Vod')->updateToday('type');
        $this->assign('vod_type_ids_today',$res['data']);

        // 获取今日更新的文章分类ID
        $res = model('Art')->updateToday('type');
        $this->assign('art_type_ids_today',$res['data']);

        // 获取专题列表 (已审核的)
        $where = [];
        $where['topic_status'] = ['eq',1];
        $order = 'topic_id desc';
        $topic_list = model('Topic')->listData($where,$order,1,999);
        $this->assign('topic_list',$topic_list['list']);
        // 专题ID字符串
        $topic_ids = join(',',array_keys($topic_list['list']));
        $this->assign('topic_ids',$topic_ids);

        // 扫描自定义标签页模板 (label目录)
        $label_list = [];
        $path = $GLOBALS['MAC_ROOT_TEMPLATE'] .'label';
        if(is_dir($path)){
            // 获取目录下所有文件
            $farr = glob($path.'/*');
            foreach($farr as $f){
                if(is_file($f)){
                    // 只保留文件名
                    $f = str_replace($path."/","",$f);
                    $label_list[] = $f;
                }
            }
            unset($farr);
        }
        $this->assign('label_list',$label_list);
        $this->assign('label_ids',join(',',$label_list));

        // 设置页面标题
        $this->assign('title',lang('admin/make/title'));
        return $this->fetch('admin@make/opt');

    }

    /**
     * ============================================================
     * 生成入口分发器
     * ============================================================
     *
     * 【功能说明】
     * 静态页面生成的统一入口
     * 根据 ac 参数分发到具体的生成方法
     *
     * 【参数说明】
     * ac 参数值 → 对应方法:
     * - index      → index()      生成首页
     * - map        → map()        生成站点地图
     * - rss        → rss()        生成RSS/Sitemap
     * - type       → type()       生成分类列表页
     * - topic_index→ topic_index() 生成专题列表页
     * - topic_info → topic_info() 生成专题详情页
     * - info       → info()       生成内容详情页
     * - label      → label()      生成自定义标签页
     *
     * @param array $pp 可选参数数组，用于定时任务等外部调用
     */
    public function make($pp=[])
    {
        // 输出页面样式
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        // 支持外部传参 (定时任务等场景)
        if(!empty($pp)){
            $this->_param = $pp;
        }

        // 根据ac参数分发到具体生成方法
        if($this->_param['ac'] == 'index'){
            $this->index();
        }
        elseif($this->_param['ac'] == 'map'){
            $this->map();
        }
        elseif($this->_param['ac'] == 'rss'){
            $this->rss();
        }
        elseif($this->_param['ac'] == 'type'){
            $this->type();
        }
        elseif($this->_param['ac'] == 'topic_index'){
            $this->topic_index();
        }
        elseif($this->_param['ac'] == 'topic_info'){
            $this->topic_info();
        }
        elseif($this->_param['ac'] == 'rss'){
            $this->rss();
        }
        elseif($this->_param['ac'] == 'info'){
            $this->info();
        }
        elseif($this->_param['ac'] == 'label'){
            $this->label();
        }
    }

    /**
     * ============================================================
     * 生成首页
     * ============================================================
     *
     * 【功能说明】
     * 生成网站首页静态文件
     * - PC端: index.html
     * - 移动端: wap_index.html (当ac2=wap时)
     *
     * 【执行流程】
     * 1. 获取首页广告位ID
     * 2. 初始化模板标签
     * 3. 渲染首页模板并写入HTML文件
     * 4. 输出生成结果链接
     * 5. 跳转回生成选项页
     */
    public function index()
    {
        // 输出页面样式
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        // 获取首页广告位ID
        $GLOBALS['aid'] = mac_get_aid('index');

        // 确定输出文件名: PC端为index.html，移动端为wap_index.html
        $link = 'index.html';
        if($this->_param['ac2']=='wap'){
            $link = 'wap_index.html';
        }
        // 初始化模板标签
        $this->label_maccms();

        // 生成首页HTML文件
        $this->buildHtml($link,'./', 'index/index');
        // 输出生成结果链接
        $this->echoLink($link,'/'.$link);
        // 后台入口时跳转回选项页
        if(ENTRANCE=='admin'){
            mac_jump( url('make/opt'),3 );
        }
        exit;
    }

    /**
     * ============================================================
     * 生成站点地图页
     * ============================================================
     *
     * 【功能说明】
     * 生成站点地图静态页面 (map.html)
     * 用于展示网站所有内容的索引导航
     *
     * 【执行流程】
     * 1. 获取地图页广告位ID
     * 2. 初始化模板标签
     * 3. 渲染地图模板并写入HTML文件
     * 4. 跳转回生成选项页
     */
    public function map()
    {
        // 输出页面样式
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        // 获取地图页广告位ID
        $GLOBALS['aid'] = mac_get_aid('map');
        // 初始化模板标签
        $this->label_maccms();
        // 生成HTML文件
        $link = 'map.html';
        $this->buildHtml($link,'./','map/index');
        $this->echoLink($link,'/'.$link);
        // 后台入口时跳转回选项页
        if(ENTRANCE=='admin') {
            mac_jump(url('make/opt'), 3);
        }
        exit;
    }

    /**
     * ============================================================
     * 生成RSS/Sitemap XML文件
     * ============================================================
     *
     * 【功能说明】
     * 生成SEO相关的XML文件，用于搜索引擎收录
     * 支持多种搜索引擎格式和多域名配置
     *
     * 【支持的搜索引擎】
     * - index  : 通用RSS格式
     * - baidu  : 百度sitemap
     * - google : 谷歌sitemap
     * - so     : 360搜索sitemap
     * - sogou  : 搜狗sitemap
     * - bing   : 必应sitemap
     * - sm     : 神马搜索sitemap
     *
     * 【参数说明】
     * - ac2: 搜索引擎类型
     * - ps : 生成页数 (默认1页)
     *
     * 【输出文件】
     * rss/{搜索引擎}.xml 或 rss/{搜索引擎}-{页码}.xml
     */
    public function rss()
    {
        // 输出页面样式
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        // 验证搜索引擎类型参数
        if(!in_array($this->_param['ac2'], ['index','baidu','google','so','sogou','bing','sm'])){
            return $this->error(lang('param_err'));
        }
        // 设置默认页数
        if(empty(intval($this->_param['ps']))){
            $this->_param['ps'] = 1;
        }

        // 获取RSS页广告位ID
        $GLOBALS['aid'] = mac_get_aid('rss');
        $this->label_maccms();

        // 循环生成指定页数的XML文件
        for($i=1;$i<=$this->_param['ps'];$i++){
            $par =[];
            if($i>=1){
                $par['page'] = $i;
                $_REQUEST['page'] = $i;
            }

            // 构建文件路径: rss/{类型}.xml 或 rss/{类型}-{页码}.xml
            $link = 'rss/'.$this->_param['ac2'];
            if($par['page']>1){
                $link .= $GLOBALS['config']['path']['page_sp'] . $par['page'];
            }
            $link .='.xml';
            // 生成XML文件
            $this->buildHtml($link,'./','rss/'.$this->_param['ac2']);

            // 处理多域名配置，为每个域名生成对应的XML
            $config = config('domain');
            foreach ($config as $key => $val){
                if ($val['map_dir'] != ''){
                    $map_link = "rss/".$val['map_dir'].'/'.$this->_param['ac2'];
                    if($par['page']>1){
                        $map_link .= $GLOBALS['config']['path']['page_sp'] . $par['page'];
                    }
                    $map_link .='.xml';
                    $this->buildHtml($map_link,'./','rss/'.$this->_param['ac2']);
                }
            }
            $this->echoLink($link,'/'.$link);
        }
        // 后台入口时跳转回选项页
        if(ENTRANCE=='admin') {
            mac_jump(url('make/opt'), 3);
        }
        exit;
    }

    /**
     * ============================================================
     * 生成分类列表页
     * ============================================================
     *
     * 【功能说明】
     * 生成视频或文章分类的列表页面
     * 支持分页生成，自动检测模板中的分页配置
     *
     * 【参数说明】
     * - tab      : 内容类型 (vod=视频, art=文章)
     * - vodtype  : 视频分类ID数组
     * - arttype  : 文章分类ID数组
     * - ac2      : 特殊模式 (day=今日更新的分类)
     * - num      : 当前处理的分类索引
     * - start    : 当前分页起始页码
     * - page_count : 总页数
     * - page_size  : 每页数量 (从模板自动读取)
     * - data_count : 数据总数
     *
     * 【执行流程】
     * 1. 根据tab参数确定内容类型
     * 2. 检查是否启用静态生成模式
     * 3. 如果是首次进入，读取模板获取分页配置
     * 4. 批量生成分类下的所有分页
     * 5. 完成后自动跳转到下一个分类
     *
     * 【批次控制】
     * 使用 makesize 配置控制每批次生成的页数
     * 防止单次请求超时
     */
    public function type()
    {
        // 根据tab参数确定内容类型和分类ID
        if($this->_param['tab'] =='art'){
            // 文章分类
            $ids = $this->_param['arttype'];
            // day模式：只处理今日更新的分类
            if(empty($ids) && $this->_param['ac2']=='day'){
                $res = model('Art')->updateToday('type');
                $ids = $res['data'];
            }
            $GLOBALS['mid'] = 2;  // 模块ID: 文章
            $GLOBALS['aid'] = mac_get_aid('art','type');
        }
        else{
            // 视频分类
            $ids = $this->_param['vodtype'];
            if(empty($ids) && $this->_param['ac2']=='day'){
                $res = model('Vod')->updateToday('type');
                $ids = $res['data'];
            }
            $GLOBALS['mid'] = 1;  // 模块ID: 视频
            $GLOBALS['aid'] = mac_get_aid('vod','type');
        }

        // 检查是否启用静态生成模式 (配置值>=2表示启用)
        if($GLOBALS['config']['view'][$this->_param['tab'].'_type'] <2){
            mac_echo(lang('admin/make/view_model_static_err'));
            exit;
        }

        // 获取分页参数
        $num = intval($this->_param['num']);         // 当前分类索引
        $start = intval($this->_param['start']);     // 当前页码
        $page_count = intval($this->_param['page_count']); // 总页数
        $page_size = intval($this->_param['page_size']);   // 每页数量
        $data_count = intval($this->_param['data_count']); // 数据总数

        // 验证分类ID参数
        if(empty($ids)){
            return $this->error(lang('param_err'));
        }
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }

        // 检查是否所有分类都已处理完毕
        if ($num>=count($ids)){
            if(empty($this->_param['jump'])){
                // 普通模式：显示完成提示
                $this->echoLink(lang('admin/make/typepage_make_complete'));
                if(ENTRANCE=='admin') {
                    mac_jump(url('make/opt'), 3);
                }
                exit;
            }
            else{
                // 一键模式(jump=1)：继续生成首页
                $this->echoLink(lang('admin/make/typepage_make_complete_later_make_index'));
                if(ENTRANCE=='admin') {
                    mac_jump(url('make/index', ['jump' => 1]), 3);
                }
                exit;
            }
        }

        // 页码初始化
        if($start<1){
            $start=1;
        }

        // 获取当前处理的分类信息
        $id = $ids[$num];
        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$id];

        // 首次进入该分类时，计算分页信息
        if(empty($data_count)){
            $where = [];
            $where['type_id|type_id_1'] = ['eq',$id];

            if($this->_param['tab'] =='art') {
                // 统计文章数量
                $where['art_status'] = ['eq', 1];
                $data_count = model('Art')->countData($where);
                // 读取模板文件，解析分页配置
                $html = mac_read_file($GLOBALS['MAC_ROOT_TEMPLATE'] . 'art/'.$type_info['type_tpl']);
                $labelRule = '{maccms:art(.*?)num="(.*?)"(.*?)paging="yes"([\s\S]*?)}([\s\S]*?){/maccms:art}';
            }
            else{
                // 统计视频数量
                $where['vod_status'] = ['eq', 1];
                $data_count = model('Vod')->countData($where);
                $html = mac_read_file($GLOBALS['MAC_ROOT_TEMPLATE'] . 'vod/'.$type_info['type_tpl']);
                $labelRule = '{maccms:vod(.*?)num="(.*?)"(.*?)paging="yes"([\s\S]*?)}([\s\S]*?){/maccms:vod}';
            }

            // 使用正则从模板中提取每页数量
            $labelRule = mac_buildregx($labelRule,"");
            preg_match_all($labelRule,$html,$arr);

            for($i=0;$i<count($arr[2]);$i++) {
                $page_size = $arr[2][$i];
                break;
            }
            // 默认每页20条
            if(empty($page_size)){
                $page_size = 20;
                $page_count=1;
            }
            else{
                $page_count = ceil($data_count / $page_size);
            }
            if($page_count<1){ $page_count=1; }

            // 保存分页参数供后续批次使用
            $this->_param['data_count'] = $data_count;
            $this->_param['page_count'] = $page_count;
            $this->_param['page_size'] = $page_size;

            // 顶级分类特殊处理 (已注释)
            if($type_info['type_pid'] == 0){
                //$this->_param['page_count'] = 1;
            }
        }

        // 当前分类所有页都已生成，跳转到下一个分类
        if($start > $page_count){
            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $url = url('make/make') .'?'. http_build_query($this->_param);

            $this->echoLink('【'.$type_info['type_name'].'】'.lang('admin/make/list_make_complate_later'));
            if(ENTRANCE=='admin') {
                mac_jump($url, 3);
            }
            exit;
        }

        // 计算批次信息用于显示进度
        $sec_count = ceil($page_count / $GLOBALS['config']['app']['makesize']);
        $sec = ceil($start / $GLOBALS['config']['app']['makesize']);
        $this->echoLink(lang('admin/make/type_tip',[$type_info['type_name'],$this->_param['page_count'],$sec_count,$sec]));
        $this->label_maccms();

        // 批量生成当前批次的分页
        $n=1;
        for($i=$start;$i<=$page_count;$i++){
            $this->_param['start'] = $i;

            // 设置模板变量
            $_REQUEST['id'] = $id;
            $_REQUEST['page'] = $i;
            // 初始化分类标签
            $this->label_type( $type_info['type_mid']==2 ? $GLOBALS['config']['view']['art_type'] : $GLOBALS['config']['view']['vod_type'] );
            // 生成分类页URL
            $link = mac_url_type($type_info,['id'=>$id,'page'=>$i]);

            // 生成HTML文件
            $this->buildHtml($link,'./', mac_tpl_fetch($this->_param['tab'],$type_info['type_tpl'],'type') );
            $this->echoLink(''.lang('the').$i.''.lang('page'),$link);

            // 达到批次上限时中断
            if($GLOBALS['config']['app']['makesize'] == $n){
                break;
            }
            $n++;
        }

        // API入口的特殊处理 (定时任务等)
        if(ENTRANCE=='api'){
            if ($num+1>=count($ids)) {
                mac_echo(lang('admin/make/type_timming_tip',[$GLOBALS['config']['app']['makesize']]));
                die;
            }
            else{
                // API模式下直接递归处理下一个分类
                $this->_param['start'] = 1;
                $this->_param['num']++;
                $this->_param['data_count'] = 0;
                $this->_param['page_count'] = 0;
                $this->_param['page_size'] = 0;
                $this->type();
            }
        }

        // 准备下一批次的跳转参数
        if($this->_param['start'] >= $this->_param['page_count']){
            // 当前分类生成完毕，准备下一个分类
            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $this->echoLink('【'.$type_info['type_name'].'】'.lang('admin/make/list_make_complate_later'));
        }
        elseif($this->_param['start'] < $this->_param['page_count']){
            // 当前分类还有页需要生成
            $this->_param['start']++;

            $this->echoLink(lang('server_rest'));
        }
        // 跳转到下一批次
        $url = url('make/make') .'?'. http_build_query($this->_param);
        if(ENTRANCE=='admin') {
            mac_jump($url, 3);
        }
    }

    /**
     * ============================================================
     * 生成专题列表页
     * ============================================================
     *
     * 【功能说明】
     * 生成专题首页/列表页的静态文件
     * 支持分页生成，从模板自动读取分页配置
     *
     * 【参数说明】
     * - topic     : 专题ID数组 (可选)
     * - start     : 当前页码
     * - page_count: 总页数
     * - data_count: 数据总数
     *
     * 【执行流程】
     * 1. 检查是否启用专题列表页静态生成
     * 2. 首次进入时计算分页信息
     * 3. 批量生成列表分页
     * 4. 完成后跳转回选项页
     */
    public function topic_index()
    {
        // 获取分页参数
        $num = intval($this->_param['num']);
        $start = intval($this->_param['start']);
        $page_count = intval($this->_param['page_count']);
        $data_count = intval($this->_param['data_count']);
        $ids = $this->_param['topic'];

        // 设置模块信息
        $GLOBALS['mid'] = 3;  // 模块ID: 专题
        $GLOBALS['aid'] = mac_get_aid('topic');

        // 页码初始化
        if($start<1){
            $start=1;
        }
        // 专题列表页每批次只生成1页
        $GLOBALS['config']['app']['makesize'] = 1;

        // 检查是否启用静态生成模式
        if($GLOBALS['config']['view']['topic_index'] <2){
            mac_echo(lang('admin/make/view_model_static_err'));
            exit;
        }

        // 首次进入时计算分页信息
        if(empty($data_count)){
            $where = [];
            $where['topic_status'] = ['eq', 1];
            $data_count = model('Topic')->countData($where);
            // 读取模板提取分页配置
            $html = mac_read_file($GLOBALS['MAC_ROOT_TEMPLATE'] . 'topic/index.html');
            $labelRule = '{maccms:topic(.*?)num="(.*?)"(.*?)paging="yes"([\s\S]*?)}([\s\S]*?){/maccms:topic}';

            $labelRule = mac_buildregx($labelRule,"");
            preg_match_all($labelRule,$html,$arr);

            for($i=0;$i<count($arr[2]);$i++) {
                $page_size = $arr[2][$i];
                break;
            }
            // 默认每页20条
            if(empty($page_size)){
                $page_size = 20;
            }
            $page_count = ceil($data_count / $page_size);
            if($page_count<1){ $page_count=1; }

            // 保存分页参数
            $this->_param['start'] = $start;
            $this->_param['data_count'] = $data_count;
            $this->_param['page_count'] = $page_count;
            $this->_param['page_size'] = $page_size;
        }

        // 所有页都已生成完毕
        if($start > $page_count){
            $this->echoLink(lang('admin/make/topicpage_make_complete'));
            if(ENTRANCE=='admin') {
                mac_jump(url('make/opt'), 3);
            }
            exit;
        }

        // 显示进度信息
        $sec_count = ceil($page_count / $GLOBALS['config']['app']['makesize']);
        $sec = ceil($start / $GLOBALS['config']['app']['makesize']);
        $this->echoLink(lang('admin/make/topic_index_tip',[$this->_param['page_count'],$sec_count,$sec]));

        $this->label_maccms();

        // 批量生成当前批次的分页
        $n=1;
        for($i=$start;$i<=$page_count;$i++){
            $this->_param['start'] = $i;
            $_REQUEST['page'] = $i;

            // 初始化专题列表标签
            $this->label_topic_index($data_count);
            // 生成专题列表页URL
            $link = mac_url_topic_index(['page'=>$i]);
            // 生成HTML文件
            $this->buildHtml($link,'./','topic/index');
            $this->echoLink(lang('the').''.$i.''.lang('page'),$link);

            // 达到批次上限时中断
            if($GLOBALS['config']['app']['makesize'] == $n){
                break;
            }
            $n++;
        }

        // 判断是否完成
        if($this->_param['start'] >= $page_count){
            $this->echoLink(lang('admin/make/topicpage_make_complete'));
            if(ENTRANCE=='admin') {
                mac_jump(url('make/opt'), 3);
            }
            exit;
        }
        else{
            // 继续下一页
            $this->_param['start']++;
            $this->echoLink(lang('server_rest'));
        }
        // 跳转到下一批次
        $url = url('make/make') .'?'. http_build_query($this->_param);
        if(ENTRANCE=='admin') {
            mac_jump($url, 3);
        }
    }

    /**
     * ============================================================
     * 生成专题详情页
     * ============================================================
     *
     * 【功能说明】
     * 生成专题详情页的静态文件
     * 批量处理选中的多个专题
     *
     * 【参数说明】
     * - topic : 专题ID数组
     * - ref   : 来源标记，为1时完成后返回来源页
     *
     * 【执行流程】
     * 1. 验证专题ID参数
     * 2. 检查是否启用静态生成模式
     * 3. 遍历所有专题生成详情页
     * 4. 更新专题的生成时间戳
     * 5. 跳转回选项页或来源页
     */
    public function topic_info()
    {
        $ids = $this->_param['topic'];

        // 设置模块信息
        $GLOBALS['mid'] = 3;  // 模块ID: 专题
        $GLOBALS['aid'] = mac_get_aid('topic','detail');

        // 验证专题ID参数
        if(empty($ids)){
            return $this->error(lang('param_err'));
        }
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }

        // 检查是否启用静态生成模式
        if($GLOBALS['config']['view']['topic_detail'] <2){
            mac_echo(lang('admin/make/view_model_static_err'));
            exit;
        }

        // 显示进度信息
        $data_count = count($ids);
        $this->echoLink(lang('admin/make/topic_tip',[$data_count]));
        $this->label_maccms();

        // 遍历生成每个专题的详情页
        $n=1;
        foreach($ids as $a){
            $_REQUEST['id'] = $a;

            // 查询专题详情
            $where = [];
            $where['topic_id'] = ['eq',$a];
            $where['topic_status'] = ['eq',1];
            $res = model('Topic')->infoData($where);
            if($res['code'] == 1) {
                $topic_info = $res['info'];

                // 初始化专题详情标签
                $this->label_topic_detail($topic_info);
                // 生成专题详情页URL
                $link = mac_url_topic_detail($topic_info);
                // 生成HTML文件
                $this->buildHtml($link,'./', mac_tpl_fetch('topic',$topic_info['topic_tpl'],'detail') );
                $this->echoLink($topic_info['topic_name'],$link);
                $n++;
            }
        }

        // 更新专题的生成时间戳
        if(!empty($ids)){
            Db::name('topic')->where(['topic_id'=>['in',$ids]])->update(['topic_time_make'=>time()]);
        }

        // 如果是从列表页点击的单条生成，返回来源页
        if($this->_param['ref'] ==1 && !empty($_SERVER["HTTP_REFERER"])){
            if(ENTRANCE=='admin'){
                mac_jump($_SERVER["HTTP_REFERER"],2);
            }
            die;
        }

        // 显示完成提示并跳转
        $this->echoLink(lang('admin/make/topic_make_complete'));
        if(ENTRANCE=='admin'){
            mac_jump( url('make/opt') ,3);
        }
    }


    /**
     * ============================================================
     * 生成内容详情页 (核心方法)
     * ============================================================
     *
     * 【功能说明】
     * 生成视频或文章的详情页静态文件
     * 这是最复杂的生成方法，支持多种生成模式和页面类型
     *
     * 【支持的页面类型】
     * 视频 (tab=vod):
     * - 详情页 (vod_detail=2)
     * - 播放页 (vod_play=2/3/4)
     * - 下载页 (vod_down=2/3/4)
     *
     * 文章 (tab=art):
     * - 详情页 (支持多页文章)
     *
     * 【播放/下载页模式】
     * - 模式2: 只生成第一集
     * - 模式3: 生成所有播放源的所有集数
     * - 模式4: 按播放源生成 (每个源一个页面)
     *
     * 【参数说明】
     * - tab      : 内容类型 (vod/art)
     * - ids      : 指定内容ID (选中生成模式)
     * - vodtype  : 视频分类ID (按分类生成模式)
     * - arttype  : 文章分类ID
     * - ac2      : 特殊模式 (day=今日, nomake=未生成)
     * - jump     : 一键生成模式标记
     * - ref      : 来源标记，为1时返回来源页
     *
     * 【执行流程】
     * 1. 根据tab参数确定内容类型
     * 2. 检查是否启用静态生成模式
     * 3. 构建查询条件
     * 4. 批量获取内容列表
     * 5. 遍历生成详情页/播放页/下载页
     * 6. 更新内容的生成时间戳
     * 7. 跳转到下一批次或下一分类
     */
    public function info()
    {
        $where = [];

        // 获取参数
        $ids = $this->_param['ids'];
        if($this->_param['tab'] =='art'){
            // 文章模式
            $type_ids = $this->_param['arttype'];
            $order='art_time desc';
            $where['art_status'] = ['eq',1];

            // 检查是否启用文章详情页静态生成
            if($GLOBALS['config']['view']['art_detail'] <2){
                mac_echo(lang('admin/make/view_model_static_err'));
                exit;
            }

        }
        else{
            // 视频模式
            $type_ids = $this->_param['vodtype'];
            $order='vod_time desc';
            $where['vod_status'] = ['eq',1];

            // 检查是否启用视频相关页面的静态生成 (详情/播放/下载任一即可)
            if($GLOBALS['config']['view']['vod_detail'] <2 && $GLOBALS['config']['view']['vod_play'] <2 && $GLOBALS['config']['view']['vod_down'] <2){
                mac_echo(lang('admin/make/view_model_static_err'));
                exit;
            }

        }

        // 获取分页参数
        $num = intval($this->_param['num']);         // 当前分类索引
        $start = intval($this->_param['start']);     // 当前批次
        $page_count = intval($this->_param['page_count']); // 总批次数
        $data_count = intval($this->_param['data_count']); // 数据总数
        if($start<1){
            $start=1;
        }
        if($page_count<1){
            $page_count=1;
        }

        // 重置查询条件，根据不同模式构建
        $where = [];
        if(empty($ids) && empty($type_ids) && empty($this->_param['ac2'])){
            return $this->error(lang('param_err'));
        }
        $type_name ='';

        // 模式1: 按分类生成
        if(!empty($type_ids)){
            if(!is_array($type_ids)){
                $type_ids = explode(',',$type_ids);
            }

            // 检查是否所有分类都已处理完毕
            if ($num>=count($type_ids)){

                if(empty($this->_param['jump'])){
                    // 普通模式：显示完成提示
                    $this->echoLink(lang('admin/make/info_make_complete').'1');
                    if(ENTRANCE=='admin'){
                        mac_jump( url('make/opt') ,3);
                    }
                    exit;
                }
                else{
                    // 一键模式：继续生成分类列表页
                    $this->echoLink(lang('admin/make/info_make_complete_later_make_type'));
                    if(ENTRANCE=='admin'){
                        mac_jump( url('make/make',['jump'=>1,'ac'=>'type','tab'=>$this->_param['tab'], $this->_param['tab'].'type'=> join(',',$type_ids) ,'ac2'=>'day']) ,3);
                    }
                    exit;
                }
            }

            // 获取当前分类信息
            $type_id = $type_ids[$num];
            $type_list = model('Type')->getCache('type_list');
            $type_info = $type_list[$type_id];

            $type_name = $type_info['type_name'];
            $where['type_id'] = ['eq',$type_id];
        }
        // 模式2: 按选中ID生成
        elseif(!empty($ids)){
            $type_name =lang('select_data');
            if($start > $page_count){
                mac_echo(lang('admin/make/info_make_complete').'2');
                exit;
            }
            $where[$this->_param['tab'].'_id'] = ['in',$ids];
        }

        // 特殊模式: 今日更新
        if($this->_param['ac2'] =='day'){
            $type_name .=lang('today_data');
            // 只查询今日更新的数据
            $where[$this->_param['tab'].'_time'] = ['gt', strtotime(date('Y-m-d'))];


            if ($num>=count($type_ids)){

            }
            if($start > $page_count){
                //$this->echoLink('内容页生成完毕3');
                //mac_jump( url('make/opt') ,3);
                //exit;
            }
        }
        // 特殊模式: 未生成的数据
        elseif($this->_param['ac2'] =='nomake'){
            $type_name =lang('no_make_data');
            $start=1;
            $data_count=0;
            // 查询生成时间早于更新时间的数据
            $where[$this->_param['tab'].'_time_make'] = ['exp',  Db::raw(' < '. $this->_param['tab'].'_time')];
            if($start > $page_count){
                $this->echoLink(lang('admin/make/info_make_complete').'4');
                if(ENTRANCE=='admin'){
                    mac_jump( url('make/opt') ,3);
                }
                exit;
            }
        }

        // API入口时增大批次大小
        if(ENTRANCE=='api'){
            $GLOBALS['config']['app']['makesize'] = 999;
        }

        // 首次进入时统计数据总数
        if(empty($data_count)){
            if($this->_param['tab'] =='art'){
                $data_count = model('Art')->countData($where);
            }
            else{
                $data_count = model('Vod')->countData($where);
            }

            // 计算总批次数
            $page_count = ceil($data_count / $GLOBALS['config']['app']['makesize']);
            $page_size = $GLOBALS['config']['app']['makesize'];

            $this->_param['data_count'] = $data_count;
            $this->_param['page_count'] = $page_count;
            $this->_param['page_size'] = $page_size;
        }

        // 当前分类生成完毕，跳转到下一个分类
        if($start > $page_count){

            $this->echoLink('【'.$type_name.'】'.lang('admin/make/info_make_complete_later'));

            // nomake模式完成后直接返回
            if($this->_param['ac2'] =='nomake' ){
                if(ENTRANCE=='admin'){
                    mac_jump( url('make/opt') ,3);
                }
                die;
            }
            else{

            }

            // 准备下一个分类
            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $url = url('make/make') .'?'. http_build_query($this->_param);


            if(ENTRANCE=='admin'){
                mac_jump( $url ,3);
            }
            exit;
        }

        // 显示进度信息
        $this->echoLink(lang('admin/make/info_tip',[$type_name,$this->_param['data_count'],$this->_param['page_count'],$this->_param['page_size'],$start]));

        // 获取当前批次的数据列表
        if($this->_param['tab'] =='art') {
            $res = model('Art')->listData($where, $order, $start, $GLOBALS['config']['app']['makesize']);
        }
        else{
            $res = model('Vod')->listData($where, $order, $start, $GLOBALS['config']['app']['makesize']);
        }

        // 遍历生成每条内容的详情页
        $update_ids=[];
        foreach($res['list'] as $k=>$v){

            // ==================== 文章详情页生成 ====================
            if(!empty($v['art_id'])) {

                // 设置分类全局变量
                $GLOBALS['type_id'] =$v['type_id'];
                $GLOBALS['type_pid'] = $v['type']['type_pid'];

                $GLOBALS['mid'] = 2;  // 模块ID: 文章
                $GLOBALS['aid'] = mac_get_aid('art','detail');

                $this->label_maccms();
                $_REQUEST['id'] = $v['art_id'];
                echo mac_substring($v['art_name'],100) .'&nbsp;';

                // 解析文章分页列表
                if(!empty($v['art_content'])) {
                    $art_page_list = mac_art_list($v['art_title'], $v['art_note'], $v['art_content']);
                    $art_page_total = count($art_page_list);
                }

                // 生成文章的每一页
                for($i=1;$i<=$art_page_total;$i++){
                    $v['art_page_list'] = mac_art_list($v['art_title'], $v['art_note'], $v['art_content']);
                    $v['art_page_total'] = count($v['art_page_list']);
                    $_REQUEST['page'] = $i;

                    $info = $this->label_art_detail($v,$GLOBALS['config']['view']['art_detail']);
                    $link = mac_url_art_detail($v, ['page' => $i]);

                    $this->buildHtml($link,'./', mac_tpl_fetch('art',$info['art_tpl'],'detail') );
                    if($i==1) {
                        $this->echoLink('detail', $link);
                    }
                }
                $update_ids[] = $v['art_id'];
            }
            // ==================== 视频详情页/播放页/下载页生成 ====================
            else{

                // 设置分类全局变量
                $GLOBALS['type_id'] =$v['type_id'];
                $GLOBALS['type_pid'] = $v['type']['type_pid'];

                $GLOBALS['mid'] = 1;  // 模块ID: 视频
                $GLOBALS['aid'] = mac_get_aid('vod','detail');

                $_REQUEST['id'] = $v['vod_id'];
                echo $v['vod_name'].'&nbsp;';;

                // 解析播放列表
                if(!empty($v['vod_play_from'])) {
                    $v['vod_play_list'] = mac_play_list($v['vod_play_from'], $v['vod_play_url'], $v['vod_play_server'], $v['vod_play_note'],'play');
                    $v['vod_play_total'] =  count($v['vod_play_list']);
                }
                // 解析下载列表
                if(!empty($v['vod_down_from'])) {
                    $v['vod_down_list'] = mac_play_list($v['vod_down_from'], $v['vod_down_url'], $v['vod_down_server'], $v['vod_down_note'],'down');
                    $v['vod_down_total'] =  count($v['vod_down_list']);
                }
                // 解析剧情列表
                if(!empty($v['vod_plot_name'])) {
                    $v['vod_plot_list'] = mac_plot_list($v['vod_plot_name'], $v['vod_plot_detail']);
                    $v['vod_plot_total'] =  count($v['vod_plot_list']);
                }

                // 生成视频详情页
                if($GLOBALS['config']['view']['vod_detail'] == 2){
                    $this->label_maccms();
                    $info = $this->label_vod_detail($v, $GLOBALS['config']['view']['vod_detail']);
                    $link = mac_url_vod_detail($v);
                    $this->buildHtml($link, './', mac_tpl_fetch('vod', $info['vod_tpl'], 'detail'));
                    $this->echoLink('detail', $link, '', 0);
                }
                $_REQUEST['id'] = $v['vod_id'];

                $update_ids[] = $v['vod_id'];

                // 生成播放页和下载页
                $flag = ['play','down'];
                foreach($flag as $f) {
                    $GLOBALS['aid'] = mac_get_aid('vod',$f);

                    $this->label_maccms();
                    // 检查是否启用静态生成
                    if ($GLOBALS['config']['view']['vod_'.$f] < 2) {
                        // 未启用静态生成，跳过
                    }
                    else{
                        // 模式2: 只生成第一集
                        if ($GLOBALS['config']['view']['vod_'.$f] == 2) {
                        	$_REQUEST['sid'] = 1;
                        	$_REQUEST['nid'] = 1;
                            $info = $this->label_vod_play($f,$v,$GLOBALS['config']['view']['vod_'.$f]);
                            $link =  ($f=='play' ?mac_url_vod_play($v,['sid'=>1,'nid'=>1]) : mac_url_vod_down($v,['sid'=>1,'nid'=>1]) );
                            $this->buildHtml($link, './', mac_tpl_fetch('vod', $info['vod_tpl_'.$f], $f) );
                            $this->echoLink($f, $link, '', 0);
                        }
                        // 模式3: 生成所有播放源的所有集数
                        elseif ($GLOBALS['config']['view']['vod_'.$f] == 3) {
                            for ($i = 1; $i <= $v['vod_'.$f.'_total']; $i++) {
                                for ($j = 1; $j <= $v['vod_'.$f.'_list'][$i]['url_count']; $j++) {
                                	$_REQUEST['sid'] = $i;
                                	$_REQUEST['nid'] = $j;
                                    $info = $this->label_vod_play($f,$v,$GLOBALS['config']['view']['vod_'.$f]);
                                    $link = ($f=='play' ? mac_url_vod_play($v, ['sid' => $i, 'nid' => $j]) : mac_url_vod_down($v, ['sid' => $i, 'nid' => $j]) );
                                    $link_sp = explode('?',$link);
                                    $this->buildHtml($link_sp[0], './', mac_tpl_fetch('vod', $info['vod_tpl_'.$f], $f) );
                                    if($i==1 && $j==1) {
                                        $this->echoLink('' . $f . '-' . $i . '-' . $j, $link, '', 0);
                                    }
                                }
                            }
                        }
                        // 模式4: 按播放源生成 (每个源一个页面)
                        elseif ($GLOBALS['config']['view']['vod_'.$f] == 4) {
                            $tmp_play_list = $v['vod_'.$f.'_list'];
                            for ($i = 1; $i <= $v['vod_'.$f.'_total']; $i++) {
                                $v['vod_'.$f.'_list'] = [];
                                $v['vod_'.$f.'_list'][$i] = $tmp_play_list[$i];
                                $info = $this->label_vod_play($f,$v,$GLOBALS['config']['view']['vod_'.$f]);
                                $link = ($f=='play' ? mac_url_vod_play($v, ['sid' => $i]) : mac_url_vod_down($v, ['sid' => $i]) );
                                $link_sp = explode('?',$link);
                                $this->buildHtml($link_sp[0], './', mac_tpl_fetch('vod', $info['vod_tpl_'.$f], $f) );
                                if($i==1) {
                                    $this->echoLink('' . $f . '-' . $i, $link, '', 0);
                                }
                            }
                        }
                    }
                }
                echo '<br>';
            }
        }

        // 更新内容的生成时间戳
        if(!empty($update_ids)){
            Db::name($this->_param['tab'])->where([$this->_param['tab'].'_id'=>['in',$update_ids]])->update([$this->_param['tab'].'_time_make'=>time()]);
        }

        // 如果是从列表页点击的单条生成，返回来源页
        if($this->_param['ref'] ==1 && !empty($_SERVER["HTTP_REFERER"])){
            if(ENTRANCE=='admin'){
                mac_jump($_SERVER["HTTP_REFERER"],2);
            }
            die;
        }

        // 准备下一批次的跳转参数
        if($start > $page_count){
            // 当前分类生成完毕，准备下一个分类
            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $this->echoLink('【'.$type_name.'】'.lang('admin/make/info_make_complete_later'));


            if($this->_param['ac2'] !=''){
                //mac_jump( url('make/opt') ,3);
                //die;
            }
            else{

            }
        }
        else{
            // 继续下一批次
            $this->_param['start'] = ++$start;
            $this->echoLink(lang('server_rest'));
        }
        // 跳转到下一批次
        $url = url('make/make') .'?'. http_build_query($this->_param);

        if(ENTRANCE=='admin'){
            mac_jump( $url ,3);
        }
    }


    /**
     * ============================================================
     * 生成自定义标签页
     * ============================================================
     *
     * 【功能说明】
     * 生成模板label目录下的自定义页面
     * 这些页面可用于生成任意自定义静态页面
     *
     * 【参数说明】
     * - label : 标签页文件名数组 (如 about.html, contact.html)
     *
     * 【执行流程】
     * 1. 验证参数 (防止目录遍历攻击)
     * 2. 遍历所有标签页文件
     * 3. 渲染模板并生成HTML文件
     * 4. 完成后跳转回选项页
     *
     * 【模板位置】
     * template/{tpl}/html/label/{filename}
     *
     * 【输出位置】
     * label/{filename}
     */
    public function label()
    {
        $ids = $this->_param['label'];
        // 获取标签页广告位ID
        $GLOBALS['aid'] = mac_get_aid('label');

        // 验证参数
        if(empty($ids)){
            return $this->error(lang('param_err'));
        }
        // 路径安全检查：防止目录遍历攻击
        $ids = str_replace('\\','/',$ids);
        if( count( explode("./",$ids) ) > 1){
            $this->error(lang('param_err').'2');
            return;
        }
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }

        // 显示进度信息
        $data_count = count($ids);
        $this->echoLink(lang('admin/make/label_tip',[$data_count]));
        $this->label_maccms();

        // 遍历生成每个标签页
        $n=1;
        foreach($ids as $a){
            // 获取不带扩展名的文件名
            $fullname = explode('.',$a)[0];
            // 输出文件路径
            $file = 'label/'.$a;
            // 模板路径
            $tpl = 'label/'.$fullname;

            // 生成HTML文件
            $this->buildHtml($file ,'./', $tpl );
            $this->echoLink($file,'/'. $file);

            $n++;
        }

        // 显示完成提示并跳转
        $this->echoLink(lang('admin/make/label_complete'));
        if(ENTRANCE=='admin'){
            mac_jump( url('make/opt') ,3);
        }
    }


}
