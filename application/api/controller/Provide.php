<?php
/**
 * 数据提供API控制器 (Data Provide API Controller)
 * ============================================================
 *
 * 【文件说明】
 * 为第三方采集系统提供标准化数据接口
 * 是苹果CMS作为数据源被其他站点采集时的核心API
 * 支持JSON和XML两种输出格式，兼容主流采集软件
 *
 * 【主要功能】
 * 1. 视频数据提供 (vod)  - 最常用，支持XML/JSON双格式
 * 2. 文章数据提供 (art)  - JSON格式
 * 3. 演员数据提供 (actor) - JSON格式
 * 4. 角色数据提供 (role)  - JSON格式
 * 5. 漫画数据提供 (manga) - 支持XML/JSON双格式
 * 6. 网站数据提供 (website) - JSON格式
 *
 * 【访问路径】
 * GET api.php/provide/vod     → 视频数据
 * GET api.php/provide/art     → 文章数据
 * GET api.php/provide/actor   → 演员数据
 * GET api.php/provide/role    → 角色数据
 * GET api.php/provide/manga   → 漫画数据
 * GET api.php/provide/website → 网站数据
 *
 * 【公共参数说明】
 * ┌────────────┬──────────────────────────────────────────────┐
 * │ 参数名      │ 说明                                          │
 * ├────────────┼──────────────────────────────────────────────┤
 * │ ac         │ 动作类型: list(列表)/videolist(详细)/detail    │
 * │ at         │ 输出格式: xml/json (默认json)                  │
 * │ t          │ 分类ID筛选                                     │
 * │ pg         │ 页码 (默认1)                                   │
 * │ pagesize   │ 每页数量 (最大100)                             │
 * │ h          │ 时间范围，单位小时 (如 h=24 表示24小时内)       │
 * │ ids        │ 指定ID列表，逗号分隔                           │
 * │ wd         │ 搜索关键词                                     │
 * └────────────┴──────────────────────────────────────────────┘
 *
 * 【配置位置】
 * application/extra/maccms.php → api.vod/art/actor/role/manga/website
 *
 * 【各API配置项】
 * ┌─────────────┬─────────────────────────────────────────────┐
 * │ 配置项       │ 说明                                         │
 * ├─────────────┼─────────────────────────────────────────────┤
 * │ status      │ API开关: 0=关闭, 1=开启                       │
 * │ charge      │ 收费模式: 0=免费, 1=需IP认证                   │
 * │ auth        │ IP/域名白名单 (#分隔)                         │
 * │ cachetime   │ 缓存时间(秒)，0=不缓存                        │
 * │ pagesize    │ 默认分页大小                                  │
 * │ typefilter  │ 分类过滤，只输出指定分类 (逗号分隔)            │
 * │ datafilter  │ 数据过滤SQL条件                               │
 * │ from        │ 播放源过滤 (仅vod)                            │
 * │ imgurl      │ 图片域名前缀                                  │
 * └─────────────┴─────────────────────────────────────────────┘
 *
 * 【认证流程】
 * 1. 检查 status 开关
 * 2. 若 charge=1，验证请求IP是否在 auth 白名单
 * 3. 支持域名自动DNS解析
 *
 * 【缓存机制】
 * 缓存键格式: {cache_flag}_api_{type}_{md5(params)}
 * cachetime > 0 时启用缓存
 *
 * 【XML输出格式】(苹果CMS标准)
 * <?xml version="1.0" encoding="utf-8"?>
 * <rss version="5.1">
 *     <list page="1" pagecount="10" pagesize="20" recordcount="200">
 *         <video>...</video>
 *     </list>
 *     <class>
 *         <ty id="1">电影</ty>
 *     </class>
 * </rss>
 *
 * 【相关文件】
 * - application/common/model/Vod.php   : 视频模型
 * - application/common/model/Art.php   : 文章模型
 * - application/common/model/Actor.php : 演员模型
 * - application/common/model/Role.php  : 角色模型
 * - application/common/model/Manga.php : 漫画模型
 * - application/common/model/Website.php : 网站模型
 *
 * ============================================================
 */
namespace app\api\controller;
use think\Controller;
use think\Cache;

class Provide extends Base
{
    /**
     * 请求参数存储
     * 存储经过 trim 和 urldecode 处理后的所有请求参数
     * @var array
     */
    var $_param;

    /**
     * 构造函数
     * 初始化请求参数，对所有输入进行预处理
     */
    public function __construct()
    {
        parent::__construct();
        // 获取所有请求参数，进行 trim 和 urldecode 处理
        $this->_param = input('','','trim,urldecode');
    }

    /**
     * 默认入口方法 (空实现)
     */
    public function index()
    {

    }

    /**
     * ============================================================
     * 视频数据提供API (Video Data Provide API)
     * ============================================================
     *
     * 【功能说明】
     * 为外部采集系统提供视频数据，是最常用的数据提供接口
     * 支持 XML 和 JSON 两种输出格式
     *
     * 【访问路径】
     * GET api.php/provide/vod
     *
     * 【请求参数】
     * ┌────────────────┬──────────────────────────────────────────┐
     * │ 参数名          │ 说明                                      │
     * ├────────────────┼──────────────────────────────────────────┤
     * │ ac             │ 动作: list(基础列表)/videolist(详细)/detail │
     * │ at             │ 格式: xml/json (默认json)                  │
     * │ t              │ 分类ID                                     │
     * │ pg             │ 页码                                       │
     * │ pagesize       │ 每页数量 (最大100)                          │
     * │ h              │ 时间范围(小时)，如 h=24                      │
     * │ ids            │ 指定视频ID列表                              │
     * │ wd             │ 搜索关键词                                  │
     * │ year           │ 年份筛选: 单年(2023)或范围(2020-2023)        │
     * │ isend          │ 是否完结: 0/1                               │
     * │ from           │ 播放源筛选                                  │
     * │ sort_direction │ 排序方向: asc/desc                          │
     * └────────────────┴──────────────────────────────────────────┘
     *
     * 【ac参数区别】
     * - list: 基础字段 (vod_id, vod_name, type_id, vod_remarks等)
     * - videolist/detail: 全部字段 (包含播放地址、简介等)
     *
     * 【输出字段】(list模式)
     * vod_id, vod_name, type_id, type_name, vod_en, vod_time,
     * vod_remarks, vod_play_from
     *
     * 【XML输出示例】
     * <video>
     *   <id>1</id>
     *   <name><![CDATA[视频名]]></name>
     *   <type>电影</type>
     *   <dt>m3u8,mp4</dt>
     *   <note><![CDATA[备注]]></note>
     * </video>
     *
     * 【特性】
     * - 支持播放量统计 (detail模式+配置开启)
     * - 支持多播放源过滤
     * - 支持年份范围筛选
     *
     * @return void 直接输出 XML 或 JSON
     */
    public function vod()
    {
        if($GLOBALS['config']['api']['vod']['status'] != 1){
            echo 'closed';
            exit;
        }

        if($GLOBALS['config']['api']['vod']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo lang('api/auth_err');
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['vod']['auth'];
                $this->checkDomainAuth($auth);
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['vod']['cachetime']);
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_'.'api_vod_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['vod_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($GLOBALS['config']['api']['vod']['typefilter'])) {
                $where['type_id'] = ['in', $GLOBALS['config']['api']['vod']['typefilter']];
            }

            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['vod']['typefilter']) || strpos($GLOBALS['config']['api']['vod']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }
            // 支持isend参数，是否完结
            if (isset($this->_param['isend'])) {
                $where['vod_isend'] = $this->_param['isend'] == 1 ? 1 : 0;
            }
            if (!empty($this->_param['h'])) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d H:i:s', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['vod_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['vod_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            // 增加年份筛选 https://github.com/magicblack/maccms10/issues/815
            if (!empty($this->_param['year'])) {
                $param_year = trim($this->_param['year']);
                if (strlen($param_year) == 4) {
                    $year = intval($param_year);
                } elseif (strlen($param_year) == 9) {
                    $start = (int)substr($param_year, 0, 4);
                    $end = (int)substr($param_year, 5, 4);
                    if ($start > $end) {
                        $tmp_num = $end;
                        $end = $start;
                        $start = $tmp_num;
                    }
                    $tmp_arr = [];
                    $start = max($start, 1900);
                    $end = min($end, date('Y') + 3);
                    for ($i = $start; $i <= $end; $i++) {
                        $tmp_arr[] = $i;
                    }
                    $year = join(',', $tmp_arr);
                }
                $where['vod_year'] = ['in', explode(',', $year)];
            }
            if (empty($GLOBALS['config']['api']['vod']['from']) && !empty($this->_param['from']) && strlen($this->_param['from']) >= 2) {
                $GLOBALS['config']['api']['vod']['from'] = $this->_param['from'];
            }
            // 采集播放组支持多个播放器
            // https://github.com/magicblack/maccms10/issues/888
            if (!empty($GLOBALS['config']['api']['vod']['from'])) {
                $vod_play_from_list = explode(',', trim($GLOBALS['config']['api']['vod']['from']));
                $vod_play_from_list = array_unique($vod_play_from_list);
                $vod_play_from_list = array_filter($vod_play_from_list);
                if (!empty($vod_play_from_list)) {
                    $where['vod_play_from'] = ['or'];
                    foreach ($vod_play_from_list as $vod_play_from) {
                        array_unshift($where['vod_play_from'], ['like', '%' . trim($vod_play_from) . '%']);
                    }
                }
            }
            if (!empty($GLOBALS['config']['api']['vod']['datafilter'])) {
                $where['_string'] .= ' ' . $GLOBALS['config']['api']['vod']['datafilter'];
            }
            if (empty($this->_param['pg'])) {
                $this->_param['pg'] = 1;
            }
            $pagesize = $GLOBALS['config']['api']['vod']['pagesize'];
            if (!empty($this->_param['pagesize']) && $this->_param['pagesize'] > 0) {
                $pagesize = min((int)$this->_param['pagesize'], 100);
            }

            $sort_direction = !empty($this->_param['sort_direction']) && $this->_param['sort_direction'] == 'asc' ? 'asc' : 'desc';
            $order = 'vod_time ' . $sort_direction;
            $field = 'vod_id,vod_name,type_id,"" as type_name,vod_en,vod_time,vod_remarks,vod_play_from,vod_time';

            if ($this->_param['ac'] == 'videolist' || $this->_param['ac'] == 'detail') {
                $field = '*';
            }
            $res = model('vod')->listData($where, $order, $this->_param['pg'], $pagesize, 0, $field, 0);


            if ($this->_param['at'] == 'xml') {
                $html = $this->vod_xml($res);
            } else {
                $html = json_encode($this->vod_json($res),JSON_UNESCAPED_UNICODE);
            }
            $html = mac_filter_tags($html);
            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        // https://github.com/magicblack/maccms10/issues/818 影片的播放量+1
        if (
            isset($this->_param['ac']) && $this->_param['ac'] == 'detail' && 
            !empty($this->_param['ids']) && (int)$this->_param['ids'] == $this->_param['ids'] && 
            !empty($GLOBALS['config']['api']['vod']['detail_inc_hits'])
        ) {
            model('Vod')->fieldData(['vod_id' => (int)$this->_param['ids']], ['vod_hits' => ['inc', 1]]);
        }
        echo $html;
        exit;
    }

    /**
     * ============================================================
     * 处理播放地址数据 (用于XML输出)
     * ============================================================
     *
     * 【功能说明】
     * 将视频播放地址按播放源分组处理
     * 根据配置的播放源过滤，返回符合条件的播放数据
     *
     * 【数据格式】
     * 输入: vod_play_url = "url1$$$url2$$$url3"
     *       vod_play_from = "m3u8$$$mp4$$$yun"
     * 输出: XML格式 <dd flag="m3u8"><![CDATA[url1]]></dd>
     *       或 JSON格式 ['m3u8' => 'url1', ...]
     *
     * @param string $urls   播放地址，用$$$ 分隔
     * @param string $froms  播放源名称，用$$$ 分隔
     * @param string $from   过滤的播放源，为空则返回全部
     * @param string $flag   输出格式: xml/json
     * @return string|array XML字符串或JSON数组
     */
    public function vod_url_deal($urls,$froms,$from,$flag)
    {
        $res_xml = '';
        $res_json = [];
        $arr1 = explode("$$$",$urls); $arr1count = count($arr1);
        $arr2 = explode("$$$",$froms); $arr2count = count($arr2);
        for ($i=0;$i<$arr2count;$i++){
            if ($arr1count >= $i){
                if($from!=''){
                    if($arr2[$i]==$from || str_contains($from, $arr2[$i])){
                        $res_xml .=  '<dd flag="'. $arr2[$i] .'"><![CDATA[' . $arr1[$i]. ']]></dd>';
                        $res_json[$arr2[$i]] = $arr1[$i];
                    }
                }
                else{
                    $res_xml .=  '<dd flag="'. $arr2[$i] .'"><![CDATA[' . $arr1[$i]. ']]></dd>';
                    $res_json[$arr2[$i]] = $arr1[$i];
                }
            }
        }
        $res = str_replace(array(chr(10),chr(13)),array('','#'),$res_xml);
        return $flag=='xml' ? $res_xml : $res_json;
    }

    /**
     * ============================================================
     * 视频数据JSON格式化
     * ============================================================
     *
     * 【功能说明】
     * 将视频查询结果转换为JSON友好格式
     * 处理分类名称、时间格式、图片URL、播放源过滤等
     *
     * 【处理内容】
     * 1. 填充分类名称 (type_name)
     * 2. 时间戳转日期字符串
     * 3. 图片URL补全协议和域名
     * 4. 播放源过滤 (根据 from 配置)
     * 5. 附加分类列表 (非detail模式)
     *
     * @param array $res 视频查询结果
     * @return array 格式化后的结果
     */
    public function vod_json($res)
    {
        $type_list = model('Type')->getCache('type_list');
        foreach($res['list'] as $k=>&$v){
            $type_info = $type_list[$v['type_id']];
            $v['type_name'] = $type_info['type_name'];
            $v['vod_time'] = date('Y-m-d H:i:s',$v['vod_time']);

            if(substr($v["vod_pic"],0,4)=="mac:"){
                $v["vod_pic"] = str_replace('mac:',$this->getImgUrlProtocol('vod'), $v["vod_pic"]);
            }
            elseif(!empty($v["vod_pic"]) && substr($v["vod_pic"],0,4)!="http" && substr($v["vod_pic"],0,2)!="//"){
                $v["vod_pic"] = $GLOBALS['config']['api']['vod']['imgurl'] . $v["vod_pic"];
            }

            if ($this->_param['ac']=='videolist' || $this->_param['ac']=='detail') {
                // 如果指定返回播放组，则只返回对应播放组的播放数据
                // https://github.com/magicblack/maccms10/issues/957
                if (!empty($GLOBALS['config']['api']['vod']['from'])) {
                    // 准备数据，逐个处理
                    $arr_from = explode('$$$', $v['vod_play_from']);
                    $arr_url = explode('$$$', $v['vod_play_url']);
                    $arr_server = explode('$$$', $v['vod_play_server']);
                    $arr_note = explode('$$$', $v['vod_play_note']);
                    $vod_play_from_list = explode(',', trim($GLOBALS['config']['api']['vod']['from']));
                    $vod_play_from_list = array_unique($vod_play_from_list);
                    $vod_play_from_list = array_filter($vod_play_from_list);
                    $vod_play_url_list = [];
                    $vod_play_server_list = [];
                    $vod_play_note_list = [];
                    foreach ($vod_play_from_list as $vod_play_from_index => $vod_play_from) {
                        $key = array_search($vod_play_from, $arr_from);
                        if ($key === false) {
                            unset($vod_play_from_list[$vod_play_from_index]);
                            continue;
                        }
                        $vod_play_url_list[] = $arr_url[$key];
                        $vod_play_server_list[] = $arr_server[$key];
                        $vod_play_note_list[] = $arr_note[$key];
                    }
                    $res['list'][$k]['vod_play_from'] = join(',', $vod_play_from_list);
                    $res['list'][$k]['vod_play_url'] = join('$$$', $vod_play_url_list);
                    $res['list'][$k]['vod_play_server'] = join('$$$', $vod_play_server_list);
                    $res['list'][$k]['vod_play_note'] = join('$$$', $vod_play_note_list);
                }
            } else {
                if (!empty($GLOBALS['config']['api']['vod']['from'])) {
                    // 准备数据，逐个处理
                    $arr_from = explode('$$$', $v['vod_play_from']);
                    $vod_play_from_list = explode(',', trim($GLOBALS['config']['api']['vod']['from']));
                    $vod_play_from_list = array_unique($vod_play_from_list);
                    $vod_play_from_list = array_filter($vod_play_from_list);
                    foreach ($vod_play_from_list as $vod_play_from_index => $vod_play_from) {
                        $key = array_search($vod_play_from, $arr_from);
                        if ($key === false) {
                            unset($vod_play_from_list[$vod_play_from_index]);
                            continue;
                        }
                    }
                    $res['list'][$k]['vod_play_from'] = join(',', $vod_play_from_list);
                } else {
                    $res['list'][$k]['vod_play_from'] = str_replace('$$$', ',', $v['vod_play_from']);
                }
            }
        }


        if($this->_param['ac']!='videolist' && $this->_param['ac']!='detail') {
            $class = [];
            $typefilter  = explode(',',$GLOBALS['config']['api']['vod']['typefilter']);

            foreach ($type_list as $k=>&$v) {

                if (!empty($GLOBALS['config']['api']['vod']['typefilter'])){
                    if(in_array($v['type_id'],$typefilter)) {
                        $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                    }
                }
                else {
                    $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                }
            }
            $res['class'] = $class;
        }
        return $res;
    }

    /**
     * ============================================================
     * 视频数据XML格式化 (苹果CMS标准格式)
     * ============================================================
     *
     * 【功能说明】
     * 将视频查询结果转换为苹果CMS标准XML格式
     * 兼容主流采集软件
     *
     * 【XML结构】
     * <rss version="5.1">
     *   <list page="" pagecount="" pagesize="" recordcount="">
     *     <video>...</video>
     *   </list>
     *   <class>
     *     <ty id="">分类名</ty>
     *   </class>
     * </rss>
     *
     * @param array $res 视频查询结果
     * @return string XML字符串
     */
    public function vod_xml($res)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<rss version="5.1">';
        $type_list = model('Type')->getCache('type_list');

        //视频列表开始
        $xml .= '<list page="'.$res['page'].'" pagecount="'.$res['pagecount'].'" pagesize="'.$res['limit'].'" recordcount="'.$res['total'].'">';
        foreach($res['list'] as $k=>&$v){
            $type_info = $type_list[$v['type_id']];
            $xml .= '<video>';
            $xml .= '<last>'.date('Y-m-d H:i:s',$v['vod_time']).'</last>';
            $xml .= '<id>'.$v['vod_id'].'</id>';
            $xml .= '<tid>'.$v['type_id'].'</tid>';
            $xml .= '<name><![CDATA['.$v['vod_name'].']]></name>';
            $xml .= '<type>'.$type_info['type_name'].'</type>';
            if(substr($v["vod_pic"],0,4)=="mac:"){
                $v["vod_pic"] = str_replace('mac:',$this->getImgUrlProtocol('vod'), $v["vod_pic"]);
            }
            elseif(!empty($v["vod_pic"]) && substr($v["vod_pic"],0,4)!="http"  && substr($v["vod_pic"],0,2)!="//"){
                $v["vod_pic"] = $GLOBALS['config']['api']['vod']['imgurl'] . $v["vod_pic"];
            }

            if($this->_param['ac']=='videolist' || $this->_param['ac']=='detail'){
                $tempurl = $this->vod_url_deal($v["vod_play_url"],$v["vod_play_from"],$GLOBALS['config']['api']['vod']['from'],'xml');

                $xml .= '<pic>'.$v["vod_pic"].'</pic>';
                $xml .= '<lang>'.$v['vod_lang'].'</lang>';
                $xml .= '<area>'.$v['vod_area'].'</area>';
                $xml .= '<year>'.$v['vod_year'].'</year>';
                $xml .= '<state>'.$v['vod_serial'].'</state>';
                $xml .= '<note><![CDATA['.$v['vod_remarks'].']]></note>';
                $xml .= '<actor><![CDATA['.$v['vod_actor'].']]></actor>';
                $xml .= '<director><![CDATA['.$v['vod_director'].']]></director>';
                $xml .= '<dl>'.$tempurl.'</dl>';
                $xml .= '<des><![CDATA['.$v['vod_content'].']]></des>';
            }
            else {
                if ($GLOBALS['config']['api']['vod']['from'] != ''){
                    $xml .= '<dt>' . $GLOBALS['config']['api']['vod']['from'] . '</dt>';
                }
                else{
                    $xml .= '<dt>' . str_replace('$$$', ',', $v['vod_play_from']) . '</dt>';
                }
                $xml .= '<note><![CDATA[' . $v['vod_remarks'] . ']]></note>';
            }
            $xml .= '</video>';
        }
        $xml .= '</list>';

        //视频列表结束

        if($this->_param['ac'] != 'videolist' && $this->_param['ac']!='detail') {
            //分类列表开始
            $xml .= "<class>";
            $typefilter  = explode(',',$GLOBALS['config']['api']['vod']['typefilter']);
            foreach ($type_list as $k=>&$v) {
                if($v['type_mid']==1) {
                    if (!empty($GLOBALS['config']['api']['vod']['typefilter'])){
                        if(in_array($v['type_id'],$typefilter)) {
                            $xml .= "<ty id=\"" . $v["type_id"] . "\">" . $v["type_name"] . "</ty>";
                        }
                    }
                    else {
                        $xml .= "<ty id=\"" . $v["type_id"] . "\">" . $v["type_name"] . "</ty>";
                    }
                }
            }
            unset($rs);
            $xml .= "</class>";
            //分类列表结束

        }
        $xml .= "</rss>";
        return $xml;
    }

    /**
     * ============================================================
     * 文章数据提供API (Article Data Provide API)
     * ============================================================
     *
     * 【功能说明】
     * 为外部系统提供文章数据，仅支持JSON格式输出
     *
     * 【访问路径】
     * GET api.php/provide/art
     *
     * 【配置位置】
     * application/extra/maccms.php → api.art
     *
     * 【type_mid】
     * 文章分类: type_mid = 2
     *
     * @return void 直接输出 JSON
     */
    public function art()
    {
        if($GLOBALS['config']['api']['art']['status'] != 1){
            echo 'closed';die;
        }

        if($GLOBALS['config']['api']['art']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo lang('api/auth_err');
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['art']['auth'];
                $this->checkDomainAuth($auth);
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['art']['cachetime']);
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_'.'api_art_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['art_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['art']['typefilter']) || strpos($GLOBALS['config']['api']['art']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }

            if (!empty(intval($this->_param['h']))) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['art_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['art_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            if (!empty($GLOBALS['config']['api']['art']['datafilter'])) {
                $where['_string'] = $GLOBALS['config']['api']['art']['datafilter'];
            }
            if (empty(intval($this->_param['pg']))) {
                $this->_param['pg'] = 1;
            }

            $order = 'art_time desc';
            $field = 'art_id,art_name,type_id,"" as type_name,art_en,art_time,art_author,art_from,art_remarks,art_pic,art_time';

            if ($this->_param['ac'] == 'detail') {
                $field = '*';
            }

            $res = model('art')->listData($where, $order, $this->_param['pg'], $GLOBALS['config']['api']['art']['pagesize'], 0, $field, 0);

            if ($res['code'] > 1) {
                echo $res['msg'];
                exit;
            }

            $type_list = model('Type')->getCache('type_list');
            foreach ($res['list'] as $k => &$v) {
                $type_info = $type_list[$v['type_id']];
                $v['type_name'] = $type_info['type_name'];
                $v['art_time'] = date('Y-m-d H:i:s', $v['art_time']);

                if (substr($v["art_pic"], 0, 4) == "mac:") {
                    $v["art_pic"] = str_replace('mac:', $this->getImgUrlProtocol('art'), $v["art_pic"]);
                } elseif (!empty($v["art_pic"]) && substr($v["art_pic"], 0, 4) != "http" && substr($v["art_pic"], 0, 2) != "//") {
                    $v["art_pic"] = $GLOBALS['config']['api']['art']['imgurl'] . $v["art_pic"];
                }

                if ($this->_param['ac'] == 'detail') {

                } else {

                }
            }

            if ($this->_param['ac'] != 'detail') {
                $class = [];
                $typefilter = explode(',', $GLOBALS['config']['api']['art']['typefilter']);

                foreach ($type_list as $k => &$v) {
                    if ($v['type_mid'] == 2) {

                        if (!empty($GLOBALS['config']['api']['art']['typefilter'])) {
                            if (in_array($v['type_id'], $typefilter)) {
                                $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                            }
                        } else {
                            $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                        }
                    }
                }
                $res['class'] = $class;
            }
            $html = json_encode($res,JSON_UNESCAPED_UNICODE);
            $html = mac_filter_tags($html);
            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }

    /**
     * 演员数据提供API
     * 配置: api.actor, type_mid = 8
     * @return void 直接输出 JSON
     */
    public function actor()
    {
        if($GLOBALS['config']['api']['actor']['status'] != 1){
            echo 'closed';die;
        }

        if($GLOBALS['config']['api']['actor']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo lang('api/auth_err');
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['actor']['auth'];
                $this->checkDomainAuth($auth);
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['actor']['cachetime']);
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_'.'api_actor_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['actor_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['actor']['typefilter']) || strpos($GLOBALS['config']['api']['actor']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }
            if (!empty(intval($this->_param['h']))) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['actor_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['actor_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            if (!empty($GLOBALS['config']['api']['actor']['datafilter'])) {
                $where['_string'] = $GLOBALS['config']['api']['actor']['datafilter'];
            }
            if (empty(intval($this->_param['pg']))) {
                $this->_param['pg'] = 1;
            }

            $order = 'actor_time desc';
            $field = 'actor_id,actor_name,type_id,"" as type_name,actor_en,actor_area,actor_time,actor_alias,actor_sex,actor_pic';

            if ($this->_param['ac'] == 'detail') {
                $field = '*';
            }

            $res = model('actor')->listData($where, $order, $this->_param['pg'], $GLOBALS['config']['api']['actor']['pagesize'], 0, $field, 0);

            if ($res['code'] > 1) {
                echo $res['msg'];
                exit;
            }

            $type_list = model('Type')->getCache('type_list');
            foreach ($res['list'] as $k => &$v) {
                $type_info = $type_list[$v['type_id']];
                $v['type_name'] = $type_info['type_name'];
                $v['actor_time'] = date('Y-m-d H:i:s', $v['actor_time']);

                if (substr($v["actor_pic"], 0, 4) == "mac:") {
                    $v["actor_pic"] = str_replace('mac:', $this->getImgUrlProtocol('actor'), $v["actor_pic"]);
                } elseif (!empty($v["actor_pic"]) && substr($v["actor_pic"], 0, 4) != "http" && substr($v["actor_pic"], 0, 2) != "//") {
                    $v["actor_pic"] = $GLOBALS['config']['api']['actor']['imgurl'] . $v["actor_pic"];
                }

                if ($this->_param['ac'] == 'detail') {

                } else {

                }
            }

            if ($this->_param['ac'] != 'detail') {
                $class = [];
                $typefilter = explode(',', $GLOBALS['config']['api']['actor']['typefilter']);

                foreach ($type_list as $k => &$v) {
                    if ($v['type_mid'] == 8) {

                        if (!empty($GLOBALS['config']['api']['actor']['typefilter'])) {
                            if (in_array($v['type_id'], $typefilter)) {
                                $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                            }
                        } else {
                            $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                        }
                    }
                }
                $res['class'] = $class;
            }

            $html = json_encode($res,JSON_UNESCAPED_UNICODE);
            $html = mac_filter_tags($html);
            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }

    /**
     * 角色数据提供API
     * 配置: api.role, 关联视频表获取豆瓣ID和导演信息
     * @return void 直接输出 JSON
     */
    public function role()
    {
        if($GLOBALS['config']['api']['role']['status'] != 1){
            echo 'closed';die;
        }

        if($GLOBALS['config']['api']['role']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo lang('api/auth_err');
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['role']['auth'];
                $this->checkDomainAuth($auth);
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['role']['cachetime']);
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_'.'api_role_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['role_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['role']['typefilter']) || strpos($GLOBALS['config']['api']['role']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }
            if (!empty(intval($this->_param['h']))) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['role_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['role_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            if (!empty($GLOBALS['config']['api']['role']['datafilter'])) {
                $where['_string'] = $GLOBALS['config']['api']['role']['datafilter'];
            }
            if (empty(intval($this->_param['pg']))) {
                $this->_param['pg'] = 1;
            }

            $order = 'role_time desc';
            $field = 'role_id,role_name,role_rid,role_en,role_actor,role_time,role_pic';

            if ($this->_param['ac'] == 'detail') {
                $field = '*';
            }

            $res = model('role')->listData($where, $order, $this->_param['pg'], $GLOBALS['config']['api']['role']['pagesize'], 0, $field, 1);

            if ($res['code'] > 1) {
                echo $res['msg'];
                exit;
            }

            foreach ($res['list'] as $k => &$v) {
                $v['role_time'] = date('Y-m-d H:i:s', $v['role_time']);
                $v['douban_id'] = $v['data']['vod_douban_id'];
                $v['vod_name'] = $v['data']['vod_name'];
                $v['vod_director'] = $v['data']['vod_director'];
                unset($v['data']);
                if (substr($v["role_pic"], 0, 4) == "mac:") {
                    $v["role_pic"] = str_replace('mac:', $this->getImgUrlProtocol('role'), $v["role_pic"]);
                } elseif (!empty($v["role_pic"]) && substr($v["role_pic"], 0, 4) != "http" && substr($v["role_pic"], 0, 2) != "//") {
                    $v["role_pic"] = $GLOBALS['config']['api']['role']['imgurl'] . $v["role_pic"];
                }

                if ($this->_param['ac'] == 'detail') {

                } else {

                }
            }

            if ($this->_param['ac'] != 'detail') {
                $class = [];
                $typefilter = explode(',', $GLOBALS['config']['api']['role']['typefilter']);

                $res['class'] = $class;
            }

            $html = json_encode($res,JSON_UNESCAPED_UNICODE);
            $html = mac_filter_tags($html);
            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }

    /**
     * 漫画数据提供API
     * 配置: api.manga, type_mid = 12
     * 支持 XML/JSON 双格式输出
     * @return void 直接输出 XML 或 JSON
     */
    public function manga()
    {
        if($GLOBALS['config']['api']['manga']['status'] != 1){
            echo 'closed';
            exit;
        }

        if($GLOBALS['config']['api']['manga']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo lang('api/auth_err');
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['manga']['auth'];
                $this->checkDomainAuth($auth);
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['manga']['cachetime']);
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_'.'api_manga_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['manga_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($GLOBALS['config']['api']['manga']['typefilter'])) {
                $where['type_id'] = ['in', $GLOBALS['config']['api']['manga']['typefilter']];
            }

            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['manga']['typefilter']) || strpos($GLOBALS['config']['api']['manga']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }
            if (!empty($this->_param['h'])) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d H:i:s', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['manga_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['manga_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            if (!empty($GLOBALS['config']['api']['manga']['datafilter'])) {
                $where['_string'] .= ' ' . $GLOBALS['config']['api']['manga']['datafilter'];
            }
            if (empty($this->_param['pg'])) {
                $this->_param['pg'] = 1;
            }
            $pagesize = $GLOBALS['config']['api']['manga']['pagesize'];
            if (!empty($this->_param['pagesize']) && $this->_param['pagesize'] > 0) {
                $pagesize = min((int)$this->_param['pagesize'], 100);
            }

            $order = 'manga_time desc';
            $field = 'manga_id,manga_name,type_id,"" as type_name,manga_en,manga_time,manga_remarks,manga_chapter_from,manga_time';

            if ($this->_param['ac'] == 'detail') {
                $field = '*';
            }
            $res = model('manga')->listData($where, $order, $this->_param['pg'], $pagesize, 0, $field, 0);


            if ($this->_param['at'] == 'xml') {
                $html = $this->manga_xml($res);
            } else {
                $html = json_encode($this->manga_json($res),JSON_UNESCAPED_UNICODE);
            }
            $html = mac_filter_tags($html);
            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }

    public function manga_json($res)
    {
        $type_list = model('Type')->getCache('type_list');
        foreach($res['list'] as $k=>&$v){
            $type_info = $type_list[$v['type_id']];
            $v['type_name'] = $type_info['type_name'];
            $v['manga_time'] = date('Y-m-d H:i:s',$v['manga_time']);

            if(substr($v["manga_pic"],0,4)=="mac:"){
                $v["manga_pic"] = str_replace('mac:',$this->getImgUrlProtocol('manga'), $v["manga_pic"]);
            }
            elseif(!empty($v["manga_pic"]) && substr($v["manga_pic"],0,4)!="http" && substr($v["manga_pic"],0,2)!="//"){
                $v["manga_pic"] = $GLOBALS['config']['api']['manga']['imgurl'] . $v["manga_pic"];
            }
        }

        if($this->_param['ac']!='detail') {
            $class = [];
            $typefilter  = explode(',',$GLOBALS['config']['api']['manga']['typefilter']);

            foreach ($type_list as $k=>&$v) {

                if (!empty($GLOBALS['config']['api']['manga']['typefilter'])){
                    if(in_array($v['type_id'],$typefilter)) {
                        $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                    }
                }
                else {
                    $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                }
            }
            $res['class'] = $class;
        }
        return $res;
    }

    public function manga_xml($res)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<rss version="5.1">';
        $type_list = model('Type')->getCache('type_list');

        $xml .= '<list page="'.$res['page'].'" pagecount="'.$res['pagecount'].'" pagesize="'.$res['limit'].'" recordcount="'.$res['total'].'">';
        foreach($res['list'] as $k=>&$v){
            $type_info = $type_list[$v['type_id']];
            $xml .= '<video>';
            $xml .= '<last>'.date('Y-m-d H:i:s',$v['manga_time']).'</last>';
            $xml .= '<id>'.$v['manga_id'].'</id>';
            $xml .= '<tid>'.$v['type_id'].'</tid>';
            $xml .= '<name><![CDATA['.$v['manga_name'].']]></name>';
            $xml .= '<type>'.$type_info['type_name'].'</type>';
            if(substr($v["manga_pic"],0,4)=="mac:"){
                $v["manga_pic"] = str_replace('mac:',$this->getImgUrlProtocol('manga'), $v["manga_pic"]);
            }
            elseif(!empty($v["manga_pic"]) && substr($v["manga_pic"],0,4)!="http"  && substr($v["manga_pic"],0,2)!="//"){
                $v["manga_pic"] = $GLOBALS['config']['api']['manga']['imgurl'] . $v["manga_pic"];
            }

            if($this->_param['ac']=='detail'){
                $xml .= '<pic>'.$v["manga_pic"].'</pic>';
                $xml .= '<lang>'.$v['manga_lang'].'</lang>';
                $xml .= '<area>'.$v['manga_area'].'</area>';
                $xml .= '<year>'.$v['manga_year'].'</year>';
                $xml .= '<state>'.$v['manga_serial'].'</state>';
                $xml .= '<note><![CDATA['.$v['manga_remarks'].']]></note>';
                $xml .= '<actor><![CDATA['.$v['manga_actor'].']]></actor>';
                $xml .= '<director><![CDATA['.$v['manga_director'].']]></director>';
                $xml .= '<des><![CDATA['.$v['manga_content'].']]></des>';
            }
            else {
                $xml .= '<dt>' . str_replace('$$$', ',', $v['manga_chapter_from']) . '</dt>';
                $xml .= '<note><![CDATA[' . $v['manga_remarks'] . ']]></note>';
            }
            $xml .= '</video>';
        }
        $xml .= '</list>';

        if($this->_param['ac']!='detail') {
            $xml .= "<class>";
            $typefilter  = explode(',',$GLOBALS['config']['api']['manga']['typefilter']);
            foreach ($type_list as $k=>&$v) {
                if($v['type_mid']==12) {
                    if (!empty($GLOBALS['config']['api']['manga']['typefilter'])){
                        if(in_array($v['type_id'],$typefilter)) {
                            $xml .= "<ty id=\"" . $v["type_id"] . "\">" . $v["type_name"] . "</ty>";
                        }
                    }
                    else {
                        $xml .= "<ty id=\"" . $v["type_id"] . "\">" . $v["type_name"] . "</ty>";
                    }
                }
            }
            unset($rs);
            $xml .= "</class>";
        }
        $xml .= "</rss>";
        return $xml;
    }

    public function website()
    {
        if($GLOBALS['config']['api']['website']['status'] != 1){
            echo 'closed';die;
        }

        if($GLOBALS['config']['api']['website']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo lang('api/auth_err');
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['website']['auth'];
                $this->checkDomainAuth($auth);
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['website']['cachetime']);
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_'.'api_website_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['website_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['website']['typefilter']) || strpos($GLOBALS['config']['api']['website']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }
            if (!empty(intval($this->_param['h']))) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['website_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['website_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            if (!empty($GLOBALS['config']['api']['website']['datafilter'])) {
                $where['_string'] = $GLOBALS['config']['api']['website']['datafilter'];
            }
            if (empty(intval($this->_param['pg']))) {
                $this->_param['pg'] = 1;
            }

            $order = 'website_time desc';
            $field = 'website_id,website_name,type_id,"" as type_name,website_en,website_time,website_area,website_lang,website_pic';

            if ($this->_param['ac'] == 'detail') {
                $field = '*';
            }

            $res = model('website')->listData($where, $order, $this->_param['pg'], $GLOBALS['config']['api']['website']['pagesize'], 0, $field, 0);

            if ($res['code'] > 1) {
                echo $res['msg'];
                exit;
            }

            $type_list = model('Type')->getCache('type_list');
            foreach ($res['list'] as $k => &$v) {
                $type_info = $type_list[$v['type_id']];
                $v['type_name'] = $type_info['type_name'];
                $v['website_time'] = date('Y-m-d H:i:s', $v['website_time']);

                if (substr($v["website_pic"], 0, 4) == "mac:") {
                    $v["website_pic"] = str_replace('mac:', $this->getImgUrlProtocol('website'), $v["website_pic"]);
                } elseif (!empty($v["website_pic"]) && substr($v["website_pic"], 0, 4) != "http" && substr($v["website_pic"], 0, 2) != "//") {
                    $v["website_pic"] = $GLOBALS['config']['api']['website']['imgurl'] . $v["website_pic"];
                }

                if ($this->_param['ac'] == 'detail') {

                } else {

                }
            }

            if ($this->_param['ac'] != 'detail') {
                $class = [];
                $typefilter = explode(',', $GLOBALS['config']['api']['website']['typefilter']);

                foreach ($type_list as $k => &$v) {
                    if ($v['type_mid'] == 11) {

                        if (!empty($GLOBALS['config']['api']['website']['typefilter'])) {
                            if (in_array($v['type_id'], $typefilter)) {
                                $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                            }
                        } else {
                            $class[] = ['type_id' => $v['type_id'], 'type_pid' => $v['type_pid'], 'type_name' => $v['type_name']];
                        }
                    }
                }
                $res['class'] = $class;
            }

            $html = json_encode($res,JSON_UNESCAPED_UNICODE);
            $html = mac_filter_tags($html);
            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }

    public function comment()
    {

    }

    private function checkDomainAuth($auth)
    {
        $ip = mac_get_client_ip();
        $auth_list = ['127.0.0.1'];
        if (!empty($auth)) {
            foreach (explode('#', $auth) as $domain) {
                $domain = trim($domain);
                $auth_list[] = $domain;
                if (!mac_string_is_ip($domain)) {
                    $auth_list[] = gethostbyname($domain);
                }
            }
            $auth_list = array_unique($auth_list);
            $auth_list = array_filter($auth_list);
        }
        if (!in_array($ip, $auth_list)) {
            echo lang('api/auth_err');
            exit;
        }
    }

    private function getImgUrlProtocol($key)
    {
        $default = (isset($GLOBALS['config']['upload']['protocol']) ? $GLOBALS['config']['upload']['protocol'] : 'http') . ':';
        if (!isset($GLOBALS['config']['api'][$key]['imgurl'])) {
            return $default;
        }
        if (substr($GLOBALS['config']['api'][$key]['imgurl'], 0, 5) == 'https') {
            return 'https:';
        }
        return $default;
    }
}
