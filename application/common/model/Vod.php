<?php
/**
 * 视频数据模型 (Vod Model)
 * ============================================================
 *
 * 【文件说明】
 * MacCMS 核心内容管理模型，负责视频/影视数据的增删改查
 * 对应数据表: mac_vod
 *
 * 【方法列表】
 * ┌─────────────────────────┬────────────────────────────────────────────┐
 * │ 方法名                   │ 功能说明                                    │
 * ├─────────────────────────┼────────────────────────────────────────────┤
 * │ countData()             │ 统计符合条件的视频数量                       │
 * │ listData()              │ 获取视频列表 (分页查询)                      │
 * │ listRepeatData()        │ 获取重复视频列表 (用于去重)                  │
 * │ listCacheData()         │ 前台调用的缓存列表 (模板标签使用)            │
 * │ infoData()              │ 获取视频详情                                │
 * │ saveData()              │ 保存视频数据 (新增/编辑)                     │
 * │ savePlot()              │ 保存剧情简介数据                            │
 * │ delData()               │ 删除视频数据 (含清理关联文件)                │
 * │ fieldData()             │ 批量更新指定字段                            │
 * │ updateToday()           │ 获取今日更新的数据                          │
 * │ cacheRepeatWithName()   │ 更新单条视频的重复缓存                       │
 * │ createRepeatCache()     │ 创建/重建重复数据缓存表                      │
 * └─────────────────────────┴────────────────────────────────────────────┘
 *
 * 【核心字段说明】
 * ┌──────────────────┬─────────────────────────────────────────────────┐
 * │ 字段名            │ 说明                                             │
 * ├──────────────────┼─────────────────────────────────────────────────┤
 * │ vod_id           │ 视频ID (主键，自增)                              │
 * │ type_id          │ 分类ID                                          │
 * │ type_id_1        │ 一级分类ID (父分类)                              │
 * │ vod_name         │ 视频名称                                         │
 * │ vod_sub          │ 副标题                                           │
 * │ vod_en           │ 英文名/拼音 (用于URL)                            │
 * │ vod_status       │ 状态: 0=未审核, 1=已审核                          │
 * │ vod_level        │ 推荐等级: 0-9, 9为幻灯片推荐                      │
 * │ vod_lock         │ 锁定: 0=否, 1=是 (锁定后采集不更新)               │
 * │ vod_isend        │ 完结: 0=连载中, 1=已完结                          │
 * │ vod_copyright    │ 版权: 0=关闭, 1=开启 (用于版权保护)               │
 * │ vod_play_from    │ 播放来源 (多组用$$$分隔，如"hnm3u8$$$kbm3u8")     │
 * │ vod_play_url     │ 播放地址 (多组用$$$分隔，每组内用#分隔多集)        │
 * │ vod_down_from    │ 下载来源 (格式同播放)                             │
 * │ vod_down_url     │ 下载地址 (格式同播放)                             │
 * │ vod_plot         │ 是否有剧情: 0=无, 1=有                            │
 * │ vod_plot_name    │ 剧情标题 (多个用$$$分隔)                          │
 * │ vod_plot_detail  │ 剧情内容 (多个用$$$分隔)                          │
 * └──────────────────┴─────────────────────────────────────────────────┘
 *
 * 【播放/下载地址格式】
 * 单集格式: "集名$地址" 或 直接地址
 * 多集格式: "第1集$url1#第2集$url2#第3集$url3"
 * 多组格式: "播放器1的多集$$$播放器2的多集$$$播放器3的多集"
 *
 * 完整示例:
 * vod_play_from: "hnm3u8$$$kbm3u8$$$wjm3u8"
 * vod_play_url: "第1集$url1#第2集$url2$$$第1集$url1#第2集$url2$$$第1集$url1#第2集$url2"
 *
 * 【关联表】
 * - mac_type: 分类表 (type_id)
 * - mac_group: 用户组表 (group_id)
 * - mac_vod_repeat: 重复数据缓存表 (用于去重功能)
 *
 * 【缓存机制】
 * - 详情页缓存: vod_detail_{id}、vod_detail_{en}
 * - 列表缓存: 使用 md5(查询条件) 作为缓存键
 * - 重复缓存: vod_repeat_table_created_time
 *
 * @package     app\common\model
 * @author      MacCMS
 * @version     1.0
 */
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;
use app\common\validate\Vod as VodValidate;

class Vod extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'vod';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 统计视频数量
     *
     * @param array $where 查询条件
     *                     - 支持 _string 字段用于原生SQL条件
     * @return int 符合条件的视频数量
     */
    public function countData($where)
    {
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }
        $total = $this->where($where)->where($where2)->count();
        return $total;
    }

    /**
     * 获取视频列表 (分页查询)
     *
     * 【功能说明】
     * 后台和API调用的核心列表方法
     * 支持分页、排序、字段筛选
     *
     * @param array|string $where    查询条件 (数组或JSON字符串)
     * @param string       $order    排序方式 (如 "vod_time desc")
     * @param int          $page     页码 (默认1)
     * @param int          $limit    每页条数 (默认20)
     * @param int          $start    偏移量 (默认0)
     * @param string       $field    查询字段 (默认*)
     * @param int          $addition 是否附加分类/用户组信息 (1=是)
     * @param int          $totalshow 是否统计总数 (1=是)
     * @return array 返回结构:
     *               - code: 状态码
     *               - msg: 消息
     *               - page: 当前页
     *               - pagecount: 总页数
     *               - limit: 每页条数
     *               - total: 总记录数
     *               - list: 视频列表
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1,$totalshow=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->where($where)->where($where2)->count();
        }

        $list = Db::name('Vod')->field($field)->where($where)->where($where2)->order($order)->limit($limit_str)->select();

        //分类
        $type_list = model('Type')->getCache('type_list');
        //用户组
        $group_list = model('Group')->getCache('group_list');

        foreach($list as $k=>$v){
            if($addition==1){
	            if(!empty($v['type_id'])) {
	                $list[$k]['type'] = $type_list[$v['type_id']];
                    $list[$k]['type_1'] = $type_list[$list[$k]['type']['type_pid']];
	            }
	            if(!empty($v['group_id'])) {
	                $list[$k]['group'] = $group_list[$v['group_id']];
	            }
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取重复视频列表
     *
     * 【功能说明】
     * 通过 JOIN mac_vod_repeat 表查询重复的视频数据
     * 用于后台去重功能
     *
     * @param array  $where    查询条件
     * @param string $order    排序方式
     * @param int    $page     页码
     * @param int    $limit    每页条数
     * @param int    $start    偏移量
     * @param string $field    查询字段
     * @param int    $addition 是否附加关联信息
     * @return array 重复视频列表
     */
    public function listRepeatData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1)
    {
        // 参数初始化
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        // 计算分页偏移: (页码-1)*每页条数 + 起始偏移
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        // ========== 统计重复视频总数 ==========
        // JOIN mac_vod_repeat 表，通过 vod_name = name1 关联
        // mac_vod_repeat 表存储了所有重名的视频名称
        $total = $this
            ->join('vod_repeat t','t.name1 = vod_name')
            ->where($where)
            ->count();

        // ========== 获取重复视频列表 ==========
        // 只返回在 mac_vod_repeat 表中有记录的视频
        $list = Db::name('Vod')
            ->join('vod_repeat t','t.name1 = vod_name')
            ->field($field)
            ->where($where)
            ->order($order)
            ->limit($limit_str)
            ->select();

        // 附加关联信息 (分类、用户组)
        //分类
        $type_list = model('Type')->getCache('type_list');
        //用户组
        $group_list = model('Group')->getCache('group_list');

        foreach($list as $k=>$v){
            if($addition==1){
                if(!empty($v['type_id'])) {
                    $list[$k]['type'] = $type_list[$v['type_id']];
                    $list[$k]['type_1'] = $type_list[$list[$k]['type']['type_pid']];
                }
                if(!empty($v['group_id'])) {
                    $list[$k]['group'] = $group_list[$v['group_id']];
                }
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 前台缓存列表数据 (模板标签调用)
     *
     * 【功能说明】
     * 前台模板标签 {maccms:vod} 调用的核心方法
     * 支持多种筛选条件和缓存机制
     *
     * 【主要参数】 (通过 $lp 数组传入)
     * - order     : 排序字段
     * - by        : 排序方式 (asc/desc/rnd随机)
     * - type      : 分类ID (支持 current/all/逗号分隔多个)
     * - ids       : 指定视频ID (逗号分隔)
     * - level     : 推荐等级筛选
     * - area      : 地区筛选
     * - lang      : 语言筛选
     * - year      : 年份筛选 (支持 2020 或 2020-2024 格式)
     * - wd        : 关键词搜索
     * - actor     : 演员筛选
     * - director  : 导演筛选
     * - tag       : 标签筛选
     * - class     : 分类名筛选
     * - letter    : 首字母筛选
     * - paging    : 是否分页 (yes/no)
     * - num       : 每页数量
     * - start     : 起始位置
     * - cachetime : 缓存时间
     *
     * 【随机排序优化】
     * 当 by=rnd 时，采用两种算法优化性能:
     * - 数据量>2000: 先随机取ID子集再查询
     * - 数据量<=2000: 随机分页查询
     *
     * @param array|string $lp    参数数组或JSON字符串
     * @param string       $field 查询字段
     * @return array 返回结构: code/msg/page/pagecount/limit/total/list/pageurl/half
     */
    public function listCacheData($lp,$field='*')
    {
        if(!is_array($lp)){
            $lp = json_decode($lp,true);
        }

        $order = $lp['order'];
        $by = $lp['by'];
        $type = $lp['type'];
        $ids = $lp['ids'];
        $rel = $lp['rel'];
        $paging = $lp['paging'];
        $pageurl = $lp['pageurl'];
        $level = $lp['level'];
        $area = $lp['area'];
        $lang = $lp['lang'];
        $state = $lp['state'];
        $wd = $lp['wd'];
        $tag = $lp['tag'];
        $class = $lp['class'];
        $letter = $lp['letter'];
        $actor = $lp['actor'];
        $director = $lp['director'];
        $version = $lp['version'];
        $year = $lp['year'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $half = intval(abs($lp['half']));
        $weekday = $lp['weekday'];
        $tv = $lp['tv'];
        $timeadd = $lp['timeadd'];
        $timehits = $lp['timehits'];
        $time = $lp['time'];
        $hitsmonth = $lp['hitsmonth'];
        $hitsweek = $lp['hitsweek'];
        $hitsday = $lp['hitsday'];
        $hits = $lp['hits'];
        $not = $lp['not'];
        $cachetime = $lp['cachetime'];
        $isend = $lp['isend'];
        $plot = $lp['plot'];
        $typenot = $lp['typenot'];
        $name = $lp['name'];

        $page = 1;
        $where=[];
        $totalshow = 0;

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }

        $param = mac_param_url();
        if($paging=='yes') {
            $param = mac_search_len_check($param);
            $totalshow = 1;
            if(!empty($param['id'])){
                //$type = intval($param['id']);
            }
            if(!empty($param['level'])){
                $level = $param['level'];
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
            }
            if(!empty($param['tid'])) {
                $tid = intval($param['tid']);
            }
            if(!empty($param['year'])){
                if(strlen($param['year'])==4){
                    $year = intval($param['year']);
                }
                elseif(strlen($param['year'])==9){
                    $s=substr($param['year'],0,4);
                    $e=substr($param['year'],5,4);
                    $s1 = intval($s);$s2 = intval($e);
                    if($s1>$s2){
                        $s1 = intval($e);$s2 = intval($s);
                    }

                    $tmp=[];
                    for($i=$s1;$i<=$s2;$i++){
                        $tmp[] = $i;
                    }
                    $year = join(',',$tmp);
                }
            }
            if(!empty($param['area'])){
                $area = $param['area'];
            }
            if(!empty($param['lang'])){
                $lang = $param['lang'];
            }
            if(!empty($param['tag'])){
                $tag = $param['tag'];
            }
            if(!empty($param['class'])){
                $class = $param['class'];
            }
            if(!empty($param['state'])){
                $state = $param['state'];
            }
            if(!empty($param['letter'])){
                $letter = $param['letter'];
            }
            if(!empty($param['version'])){
                $version = $param['version'];
            }
            if(!empty($param['actor'])){
                $actor = $param['actor'];
            }
            if(!empty($param['director'])){
                $director = $param['director'];
            }
            if(!empty($param['wd'])){
                $wd = $param['wd'];
            }
            if(!empty($param['name'])){
                $name = $param['name'];
            }
            if(!empty($param['by'])){
                $by = $param['by'];
            }
            if(!empty($param['order'])){
                $order = $param['order'];
            }
            if(!empty($param['page'])){
                $page = intval($param['page']);
            }
            if(isset($param['isend'])){
                $isend = intval($param['isend']);
            }

            foreach($param as $k=>$v){
                if(empty($v)){
                    unset($param[$k]);
                }
            }
            if(empty($pageurl)){
                $pageurl = 'vod/type';
            }
            $param['page'] = 'PAGELINK';

            if($pageurl=='vod/type' || $pageurl=='vod/show'){
                $type = intval( $GLOBALS['type_id'] );
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type];
                $flag='type';
                if($pageurl == 'vod/show'){
                    $flag='show';
                }
                $pageurl = mac_url_type($type_info,$param,$flag);
            }
            else{
                $pageurl = mac_url($pageurl,$param);
            }
        }

        // ========== 前台审核状态过滤 ==========
        // 前台只显示已审核的视频 (vod_status=1)
        // 未审核的视频 (vod_status=0) 不在前台展示
        // 管理员可在后台 "视频 → 未审核视频" 中查看和批量审核
        $where['vod_status'] = ['eq',1];
        if(!empty($ids)) {
            if($ids!='all'){
                $where['vod_id'] = ['in',explode(',',$ids)];
            }
        }
        if(!empty($not)){
            $where['vod_id'] = ['not in',explode(',',$not)];
        }
        if(!empty($rel)){
            $tmp = explode(',',$rel);
            if(is_numeric($rel) || mac_array_check_num($tmp)==true  ){
                $where['vod_id'] = ['in',$tmp];
            }
            else{
                $where['vod_rel_vod'] = ['like', mac_like_arr($rel),'OR'];
            }
        }
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['vod_level'] = ['in',explode(',',$level)];
        }
        if(!empty($year)) {
            $where['vod_year'] = ['in',explode(',',$year)];
        }
        if(!empty($area)) {
            $where['vod_area'] = ['in',explode(',',$area)];
        }
        if(!empty($lang)) {
            $where['vod_lang'] = ['in',explode(',',$lang)];
        }
        if(!empty($state)) {
            $where['vod_state'] = ['in',explode(',',$state)];
        }
        if(!empty($version)) {
            $where['vod_version'] = ['in',explode(',',$version)];
        }
        if(!empty($weekday)){
            //$where['vod_weekday'] = ['in',explode(',',$weekday)];
            $where['vod_weekday'] = ['like', mac_like_arr($weekday),'OR'];
        }
        if(!empty($tv)){
            $where['vod_tv'] = ['in',explode(',',$tv)];
        }
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['vod_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['vod_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['vod_time'] =['gt',$s];
        }
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['vod_letter'] = ['in',explode(',',$letter)];
        }
        if(!empty($type)) {
            if($type=='current'){
                $type = intval( $GLOBALS['type_id'] );
            }
            if($type!='all') {
                $tmp_arr = explode(',',$type);
                $type_list = model('Type')->getCache('type_list');
                $type = [];
                foreach($type_list as $k2=>$v2){
                    if(in_array($v2['type_id'].'',$tmp_arr) || in_array($v2['type_pid'].'',$tmp_arr)){
                        $type[]=$v2['type_id'];
                    }
                }
                $type = array_unique($type);
                $where['type_id'] = ['in', implode(',',$type) ];
            }
        }
        if(!empty($typenot)){
            $where['type_id'] = ['not in',$typenot];
        }
        if(!empty($tid)) {
            $where['type_id|type_id_1'] = ['eq',$tid];
        }
        if(!in_array($GLOBALS['aid'],[13,14,15]) && !empty($param['id'])){
            //$where['vod_id'] = ['not in',$param['id']];
        }

        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['vod_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['vod_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['vod_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['vod_hits'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits'] = [$tmp[0],$tmp[1]];
            }
        }

        if(in_array($isend,['0','1'])){
            $where['vod_isend'] = $isend;
        }

        $vod_search = model('VodSearch');
        $vod_search_enabled = $vod_search->isFrontendEnabled();
        $max_id_count = $vod_search->maxIdCount;
        if ($vod_search_enabled) {
            // 开启搜索优化，查询并缓存Id
            $search_id_list = [];
            if(!empty($wd)) {
                $role = 'vod_name';
                if(!empty($GLOBALS['config']['app']['search_vod_rule'])){
                    $role .= '|'.$GLOBALS['config']['app']['search_vod_rule'];
                }
                $where[$role] = ['like', '%' . $wd . '%'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($wd, $role)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where[$role]);
                }
            }
            if(!empty($name)) {
                $where['vod_name'] = ['like',mac_like_arr($name),'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($name, 'vod_name')) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_name']);
                }
            }
            if(!empty($tag)) {
                $where['vod_tag'] = ['like',mac_like_arr($tag),'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($tag, 'vod_tag', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_tag']);
                }
            }
            if(!empty($class)) {
                $where['vod_class'] = ['like',mac_like_arr($class), 'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($class, 'vod_class', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_class']);
                }
            }
            if(!empty($actor)) {
                $where['vod_actor'] = ['like', mac_like_arr($actor), 'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($actor, 'vod_actor', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_actor']);
                }
            }
            if(!empty($director)) {
                $where['vod_director'] = ['like',mac_like_arr($director),'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($director, 'vod_director', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_director']);
                }
            }
            $search_id_list = array_unique($search_id_list);
            if (!empty($search_id_list)) {
                $where['_string'] = "vod_id IN (" . join(',', $search_id_list) . ")";
            }
        } else {
            // 不开启搜索优化，使用默认条件
            if(!empty($wd)) {
                $role = 'vod_name';
                if(!empty($GLOBALS['config']['app']['search_vod_rule'])){
                    $role .= '|'.$GLOBALS['config']['app']['search_vod_rule'];
                }
                $where[$role] = ['like', '%' . $wd . '%'];
            }
            if(!empty($name)) {
                $where['vod_name'] = ['like',mac_like_arr($name),'OR'];
            }
            if(!empty($tag)) {
                $where['vod_tag'] = ['like',mac_like_arr($tag),'OR'];
            }
            if(!empty($class)) {
                $where['vod_class'] = ['like',mac_like_arr($class), 'OR'];
            }
            if(!empty($actor)) {
                $where['vod_actor'] = ['like', mac_like_arr($actor), 'OR'];
            }
            if(!empty($director)) {
                $where['vod_director'] = ['like',mac_like_arr($director),'OR'];
            }
        }
        if(in_array($plot,['0','1'])){
            $where['vod_plot'] = $plot;
        }

        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['popedom_filter'] ==1){
            $type_ids = mac_get_popedom_filter($GLOBALS['user']['group']['group_type']);
            if(!empty($type_ids)){
                if(!empty($where['type_id'])){
                    $where['type_id'] = [ $where['type_id'],['not in', explode(',',$type_ids)] ];
                }
                else{
                    $where['type_id'] = ['not in', explode(',',$type_ids)];
                }
            }
        }
        // 优化随机视频排序rnd的性能问题
        // https://github.com/magicblack/maccms10/issues/967
        $use_rand = false;
        if($by=='rnd'){
            $use_rand = true;
            $algo2_threshold = 2000;
            $data_count = $this->countData($where);
            $where_string_addon = "";
            if ($data_count > $algo2_threshold) {
                $rows = $this->field("vod_id")->where($where)->select();
                foreach ($rows as $row) {
                    $id_list[] = $row['vod_id'];
                }
                if (
                    !empty($id_list)
                ) {
                    $random_count = intval($algo2_threshold / 2);
                    $specified_list = array_rand($id_list, intval($algo2_threshold / 2));
                    $random_keys = array_rand($id_list, $random_count);
                    $specified_list = [];

                    if ($random_count == 1) {
                        $specified_list[] = $id_list[$random_keys];
                    } else {
                        foreach ($random_keys as $key) {
                            $specified_list[] = $id_list[$key];
                        }
                    }
                    if (!empty($specified_list)) {
                        $where_string_addon = " AND vod_id IN (" . join(',', $specified_list) . ")";
                    }
                }
            }
            if (!empty($where_string_addon)) {
                $where['_string'] .= $where_string_addon;
                $where['_string'] = trim($where['_string'], " AND ");
            } else {
                if ($data_count % $lp['num'] === 0) {
                    $page_total = floor($data_count / $lp['num']);
                } else {
                    $page_total = floor($data_count / $lp['num']) + 1;
                }
                if($data_count < $lp['num']){
                    $lp['num'] = $data_count;
                }
                $randi = @mt_rand(1, $page_total);
                $page = $randi;
            }
            $by = 'hits_week';
            $order = 'desc';
        }

        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        $order= 'vod_'.$by .' ' . $order;
        $where_cache = $where;
        if($use_rand){
            unset($where_cache['vod_id']);
            $where_cache['order'] = 'rnd';
        }

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('vod_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where, $order, $page, $num, $start,$field,1, $totalshow);
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cachetime);
            }
        }
        $res['pageurl'] = $pageurl;
        $res['half'] = $half;

        return $res;
    }

    /**
     * 获取视频详情
     *
     * 【功能说明】
     * 获取单条视频的完整信息
     * 支持缓存机制，按 vod_id 或 vod_en 查询
     *
     * 【返回数据处理】
     * - 解析播放列表: vod_play_list (通过 mac_play_list 函数)
     * - 解析下载列表: vod_down_list
     * - 解析剧情列表: vod_plot_list
     * - 解析截图列表: vod_pic_screenshot_list
     * - 附加分类信息: type、type_1
     * - 附加用户组信息: group
     *
     * 【缓存键】
     * - vod_detail_{vod_id}_{vod_en}
     *
     * @param array  $where 查询条件 (必须包含 vod_id 或 vod_en)
     * @param string $field 查询字段
     * @param int    $cache 是否使用缓存 (0=不使用, 1=使用)
     * @return array 返回结构:
     *               - code: 1=成功, 1001=参数错误, 1002=数据不存在
     *               - msg: 消息
     *               - info: 视频详情数组
     */
    public function infoData($where,$field='*',$cache=0)
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $data_cache = false;
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'vod_detail_'.$where['vod_id'][1].'_'.$where['vod_en'][1];
        if($where['vod_id'][0]=='eq' || $where['vod_en'][0]=='eq'){
            $data_cache = true;
        }
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }

        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['vod_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();
            // ========== 初始化扩展数据数组 ==========
            $info['vod_play_list']=[];
            $info['vod_down_list']=[];
            $info['vod_plot_list']=[];
            $info['vod_pic_screenshot_list']=[];

            // 解析播放列表 ($$$ 分隔的字符串 → 数组)
            if (!empty($info['vod_play_from'])) {
                $info['vod_play_list'] = mac_play_list($info['vod_play_from'], $info['vod_play_url'], $info['vod_play_server'], $info['vod_play_note'], 'play');
            }
            // 解析下载列表
            if (!empty($info['vod_down_from'])) {
                $info['vod_down_list'] = mac_play_list($info['vod_down_from'], $info['vod_down_url'], $info['vod_down_server'], $info['vod_down_note'], 'down');
            }
            // ========== 解析分集剧情 (菜单: 视频-有分集剧情) ==========
            // 将 $$$ 分隔的剧情字符串解析为数组
            // 输入: vod_plot_name="第1集$$$第2集", vod_plot_detail="内容1$$$内容2"
            // 输出: [ 1 => ['name'=>'第1集', 'detail'=>'内容1'], ... ]
            if (!empty($info['vod_plot_name'])) {
                $info['vod_plot_list'] = mac_plot_list($info['vod_plot_name'], $info['vod_plot_detail']);
            }
            // 解析截图列表 (# 分隔的URL)
            if(!empty($info['vod_pic_screenshot'])){
                $info['vod_pic_screenshot_list'] = mac_screenshot_list($info['vod_pic_screenshot']);
            }


            //分类
            if (!empty($info['type_id'])) {
                $type_list = model('Type')->getCache('type_list');
                $info['type'] = $type_list[$info['type_id']];
                $info['type_1'] = $type_list[$info['type']['type_pid']];
            }
            //用户组
            if (!empty($info['group_id'])) {
                $group_list = model('Group')->getCache('group_list');
                $info['group'] = $group_list[$info['group_id']];
            }
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 保存视频数据 (新增/编辑)
     *
     * 【功能说明】
     * 保存视频数据到数据库
     * 自动处理播放/下载地址格式化、拼音生成、缓存清理等
     *
     * 【数据处理流程】
     * 1. 验证数据格式 (通过 VodValidate)
     * 2. 清除相关缓存
     * 3. 自动填充 type_id_1 (父分类ID)
     * 4. 自动生成 vod_en (拼音) 和 vod_letter (首字母)
     * 5. 处理内容中的图片协议
     * 6. 自动生成 vod_blurb (简介)
     * 7. 格式化播放/下载地址 (数组转$$$分隔字符串)
     * 8. 处理截图地址格式
     * 9. 可选更新时间和自动生成TAG
     * 10. 保存到数据库
     * 11. 更新重复数据缓存
     *
     * 【播放/下载地址处理】
     * 输入: 数组格式 ['播放器1地址', '播放器2地址']
     * 输出: 字符串格式 '播放器1地址$$$播放器2地址'
     * 换行符转换为 # 分隔多集
     *
     * @param array $data 视频数据数组
     *                    - vod_id: 有值则编辑，无值则新增
     *                    - uptime: 1=更新时间
     *                    - uptag: 1=自动生成TAG
     * @return array 返回结构: code/msg
     */
    public function saveData($data)
    {
        // ========== 步骤1: 数据验证 ==========
        // 使用 Vod 验证器检查必填字段 (vod_name, type_id)
        $validate = \think\Loader::validate('Vod');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // ========== 步骤2: 清除相关缓存 ==========
        // 缓存键格式: vod_detail_{id}, vod_detail_{en}, vod_detail_{id}_{en}
        // 编辑视频时必须清除旧缓存，确保前台显示最新数据
        $key = 'vod_detail_'.$data['vod_id'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_en'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_id'].'_'.$data['vod_en'];
        Cache::rm($key);

        // ========== 步骤3: 自动填充一级分类ID ==========
        // 从分类缓存获取当前分类信息
        // type_id_1 存储一级分类ID，用于分类筛选和统计
        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$data['type_id']];
        $data['type_id_1'] = $type_info['type_pid'];

        // ========== 步骤4: 自动生成拼音和首字母 ==========
        // vod_en 为空时，根据视频名称生成拼音
        if(empty($data['vod_en'])){
            $data['vod_en'] = Pinyin::get($data['vod_name']);
        }
        // vod_letter 为空时，取拼音首字母并转大写
        if(empty($data['vod_letter'])){
            $data['vod_letter'] = strtoupper(substr($data['vod_en'],0,1));
        }

        // ========== 步骤5: 处理内容中的图片URL ==========
        // 将图片URL中的协议 (http:/https:) 替换为 mac: 前缀
        // 这样前台显示时可以根据当前协议自动适配
        if(!empty($data['vod_content'])) {
            // 正则匹配所有 img 标签的 src 属性
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['vod_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    // 将协议替换为 mac: 前缀 (如 http://xxx → mac://xxx)
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['vod_content'] = str_replace($v1, $v2, $data['vod_content']);
                }
            }
            unset($match_src1);
        }

        // ========== 步骤6: 自动生成简介 ==========
        // vod_blurb 为空时，从 vod_content 提取前100字符作为简介
        if(empty($data['vod_blurb'])){
            $data['vod_blurb'] = mac_substring( strip_tags($data['vod_content']) ,100);
        }

        // ========== 步骤7: 播放/下载地址初始化 ==========
        // 确保播放和下载URL不为null
        if(empty($data['vod_play_url'])){
            $data['vod_play_url'] = '';
        }
        if(empty($data['vod_down_url'])){
            $data['vod_down_url'] = '';
        }

        // ========== 步骤8: 处理截图URL ==========
        // 将换行符转换为 # 分隔多张截图
        if(!empty($data['vod_pic_screenshot'])){
            $data['vod_pic_screenshot'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['vod_pic_screenshot']);
        }

        // ========== 步骤9: 格式化播放地址组 ==========
        // 表单提交的是数组，存储时转换为 $$$ 分隔的字符串
        // 格式: 来源1$$$来源2  对应  地址组1$$$地址组2
        // 每组地址内多集用 # 分隔: 第1集$url1#第2集$url2
        if(!empty($data['vod_play_from'])) {
            // 多个播放来源用 $$$ 分隔 (如: hnm3u8$$$kbm3u8)
            $data['vod_play_from'] = join('$$$', $data['vod_play_from']);
            // 多个服务器组用 $$$ 分隔
            $data['vod_play_server'] = join('$$$', $data['vod_play_server']);
            // 多个备注用 $$$ 分隔
            $data['vod_play_note'] = join('$$$', $data['vod_play_note']);
            // 多组播放地址用 $$$ 分隔
            $data['vod_play_url'] = join('$$$', $data['vod_play_url']);
            // 将换行符转换为 # 分隔多集
            $data['vod_play_url'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['vod_play_url']);
        }
        else{
            // 无播放来源时，所有播放相关字段置空
            $data['vod_play_from'] = '';
            $data['vod_play_server'] = '';
            $data['vod_play_note'] = '';
            $data['vod_play_url'] = '';
        }

        // ========== 步骤10: 格式化下载地址组 ==========
        // 格式与播放地址相同
        if(!empty($data['vod_down_from'])) {
            $data['vod_down_from'] = join('$$$', $data['vod_down_from']);
            $data['vod_down_server'] = join('$$$', $data['vod_down_server']);
            $data['vod_down_note'] = join('$$$', $data['vod_down_note']);
            $data['vod_down_url'] = join('$$$', $data['vod_down_url']);
            $data['vod_down_url'] = str_replace(array(chr(10),chr(13)), array('','#'),$data['vod_down_url']);
        }else{
            // 无下载来源时，所有下载相关字段置空
            $data['vod_down_from']='';
            $data['vod_down_server']='';
            $data['vod_down_note']='';
            $data['vod_down_url']='';
        }
        
        // ========== 步骤11: 处理更新时间和TAG ==========
        // uptime=1 时更新视频时间戳
        if($data['uptime']==1){
            $data['vod_time'] = time();
        }
        // uptag=1 时自动从名称和内容提取TAG
        if($data['uptag']==1){
            $data['vod_tag'] = mac_get_tag($data['vod_name'], $data['vod_content']);
        }
        // 清除临时标记字段，不存入数据库
        unset($data['uptime']);
        unset($data['uptag']);

        // ========== 步骤12: XSS过滤和长度裁剪 ==========
        // 调用验证器的格式化方法，防止XSS攻击和数据溢出
        $data = VodValidate::formatDataBeforeDb($data);

        // ========== 步骤13: 执行数据库操作 ==========
        if(!empty($data['vod_id'])){
            // ----- 编辑模式 -----
            $where=[];
            $where['vod_id'] = ['eq',$data['vod_id']];
            // allowField(true) 自动过滤非数据表字段
            $res = $this->allowField(true)->where($where)->update($data);

            // 更新重复视频缓存
            // 如果视频名称被修改，需要更新新旧名称的重复缓存
            $old_name = $this->where('vod_id',$data['vod_id'])->value('vod_name');
            if($old_name!=$data['vod_name']){
                // 名称变更，更新新旧两个名称的缓存
                $this->cacheRepeatWithName($old_name);
                $this->cacheRepeatWithName($data['vod_name']);
            }else{
                // 名称未变，只更新当前名称缓存
                $this->cacheRepeatWithName($data['vod_name']);
            }
        }
        else{
            // ----- 新增模式 -----
            // 初始化剧情相关字段
            $data['vod_plot'] = 0;
            $data['vod_plot_name']='';
            $data['vod_plot_detail']='';
            // 设置添加时间和更新时间
            $data['vod_time_add'] = time();
            $data['vod_time'] = time();
            // insert 第三个参数 true 表示返回插入ID
            $res = $this->allowField(true)->insert($data, false, true);

            // 如果启用了视频搜索模块，更新搜索索引
            if ($res > 0 && model('VodSearch')->isFrontendEnabled()) {
                model('VodSearch')->checkAndUpdateTopResults(['vod_id' => $res] + $data);
            }
            // 更新新增视频名称的重复缓存
            $this->cacheRepeatWithName($data['vod_name']);
        }

        // ========== 步骤14: 返回操作结果 ==========
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }

        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 保存剧情简介数据
     *
     * 【功能说明】
     * 单独保存视频的分集剧情数据
     * 用于后台剧情编辑页面
     *
     * 【数据格式】
     * - vod_plot_name: 剧情标题数组 ['第1集标题', '第2集标题']
     * - vod_plot_detail: 剧情内容数组 ['第1集内容', '第2集内容']
     * 保存时转换为 $$$ 分隔的字符串
     *
     * @param array $data 剧情数据数组
     *                    - vod_id: 视频ID (必填)
     *                    - vod_en: 英文名 (用于清除缓存)
     *                    - vod_plot_name: 剧情标题数组
     *                    - vod_plot_detail: 剧情内容数组
     * @return array 返回结构: code/msg
     */
    public function savePlot($data)
    {
        // 数据验证
        $validate = \think\Loader::validate('Vod');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // ========== 清除视频详情缓存 ==========
        // 剧情变更后需清除缓存，确保前台显示最新数据
        $key = 'vod_detail_'.$data['vod_id'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_en'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_id'].'_'.$data['vod_en'];
        Cache::rm($key);

        // ========== 格式化剧情数据 (菜单: 视频-有分集剧情) ==========
        // 表单提交的是数组，存储时转换为 $$$ 分隔的字符串
        // 输入: ['第1集标题', '第2集标题'] → 输出: "第1集标题$$$第2集标题"
        if(!empty($data['vod_plot_name'])) {
            $data['vod_plot'] = 1;  // 标记有剧情
            $data['vod_plot_name'] = join('$$$', $data['vod_plot_name']);
            $data['vod_plot_detail'] = join('$$$', $data['vod_plot_detail']);
        }else{
            // 无剧情时清空相关字段
            $data['vod_plot'] = 0;
            $data['vod_plot_name']='';
            $data['vod_plot_detail']='';
        }

        // 执行更新 (剧情编辑只支持更新，不支持新增)
        if(!empty($data['vod_id'])){
            $where=[];
            $where['vod_id'] = ['eq',$data['vod_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = false;
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除视频数据
     *
     * 【功能说明】
     * 删除符合条件的视频数据
     * 同时清理关联的本地图片和静态HTML文件
     *
     * 【清理内容】
     * - vod_pic: 封面图
     * - vod_pic_thumb: 缩略图
     * - vod_pic_slide: 幻灯片图
     * - 静态详情页HTML文件 (如果开启静态生成)
     *
     * 【安全检查】
     * 只删除 ./upload 目录下的图片文件
     *
     * @param array $where 删除条件
     * @return array 返回结构: code/msg
     */
    public function delData($where)
    {
        $list = $this->listData($where,'',1,9999);
        if($list['code'] !==1){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        $path = './';
        foreach($list['list'] as $k=>$v){
            $pic = $path.$v['vod_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            $pic = $path.$v['vod_pic_thumb'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            $pic = $path.$v['vod_pic_slide'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            if($GLOBALS['config']['view']['vod_detail'] ==2 ){
                $lnk = mac_url_vod_detail($v);
                $lnk = reset_html_filename($lnk);
                if(file_exists($lnk)){
                    unlink($lnk);
                }
            }
        }
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1002,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 批量更新指定字段
     *
     * 【功能说明】
     * 批量更新符合条件的视频的指定字段
     * 更新后自动清除相关缓存
     *
     * 【使用场景】
     * - 批量修改审核状态
     * - 批量修改推荐等级
     * - 批量修改点击量
     * - 批量修改分类
     *
     * @param array $where  更新条件
     * @param array $update 更新数据 (字段=>值)
     * @return array 返回结构: code/msg
     */
    public function fieldData($where,$update)
    {
        if(!is_array($update)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $res = $this->allowField(true)->where($where)->update($update);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        $list = $this->field('vod_id,vod_name,vod_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'vod_detail_'.$v['vod_id'];
            Cache::rm($key);
            $key = 'vod_detail_'.$v['vod_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    /**
     * 获取今日更新的数据
     *
     * 【功能说明】
     * 获取今日 (0点之后) 更新的视频ID或分类ID列表
     * 用于首页今日更新统计展示
     *
     * @param string $flag 返回类型
     *                     - 'vod': 返回视频ID列表 (默认)
     *                     - 'type': 返回分类ID列表
     * @return array 返回结构:
     *               - code: 1
     *               - msg: 消息
     *               - data: 逗号分隔的ID字符串
     */
    public function updateToday($flag='vod')
    {
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $where['vod_time'] = ['gt',$today];
        if($flag=='type'){
            $ids = $this->where($where)->column('type_id');
        }
        else{
            $ids = $this->where($where)->column('vod_id');
        }
        if(empty($ids)){
            $ids = [];
        }else{
            $ids = array_unique($ids);
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'data'=> join(',',$ids) ];
    }

    /**
     * 更新单条视频的重复缓存
     *
     * 【功能说明】
     * 当视频新增或名称变更时，更新 mac_vod_repeat 表中的相关记录
     * 用于实时维护重复数据缓存
     *
     * 【处理逻辑】
     * 1. 删除该名称的旧缓存记录
     * 2. 查询同名视频，如果存在重复则插入缓存
     * 3. 如果表不存在则自动创建
     *
     * 【数据表结构】
     * mac_vod_repeat:
     * - id1: 重复组中最小的视频ID
     * - name1: 视频名称 (带索引)
     *
     * @param string $name 视频名称
     */
    public function cacheRepeatWithName($name)
    {
        try{
            Db::execute('delete from `' . config('database.prefix') . 'vod_repeat` where name1 =?', [$name]);
            Db::execute('INSERT INTO `' . config('database.prefix') . 'vod_repeat` (SELECT min(vod_id)as id1,vod_name as name1 FROM ' . config('database.prefix') . 'vod WHERE vod_name = ? GROUP BY name1 HAVING COUNT(name1)>1)', [$name]);
        }catch (\Exception $e){
            Db::execute('DROP TABLE IF EXISTS ' . config('database.prefix') . 'vod_repeat');
            Db::execute('CREATE TABLE `' . config('database.prefix') . 'vod_repeat` (`id1` int unsigned DEFAULT NULL, `name1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT \'\') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
            Db::execute('ALTER TABLE `' . config('database.prefix') . 'vod_repeat` ADD INDEX `name1` (`name1`(100))');
        }
        Db::execute('INSERT INTO `' . config('database.prefix') . 'vod_repeat` (SELECT min(vod_id)as id1,vod_name as name1 FROM ' .
            config('database.prefix') . 'vod GROUP BY name1 HAVING COUNT(name1)>1)');
        Cache::set('vod_repeat_table_created_time',time());
    }
    /**
     * 创建/重建重复数据缓存表
     *
     * 【功能说明】
     * 全量重建 mac_vod_repeat 缓存表
     * 用于后台"重复数据"功能的初始化
     *
     * 【处理流程】
     * 1. 清空现有缓存表 (TRUNCATE)
     * 2. 如果表不存在则创建
     * 3. 全量插入重复数据 (按 vod_name 分组，COUNT>1)
     * 4. 更新缓存时间标记
     *
     * 【缓存策略】
     * 缓存有效期 7 天，过期后自动重建
     * 缓存键: vod_repeat_table_created_time
     *
     * 【调用场景】
     * - 后台首次访问重复数据页面
     * - 手动点击"更新重复缓存"按钮
     */
    public function  createRepeatCache()
    {
        $prefix = config('database.prefix');
        $tableName = $prefix . 'vod_repeat';
        try{
            Db::execute("TRUNCATE TABLE `{$tableName}`");
        }catch (\Exception $e){
            //创建表
            Db::execute('DROP TABLE IF EXISTS ' . config('database.prefix') . 'vod_repeat');
            Db::execute('CREATE TABLE `' . config('database.prefix') . 'vod_repeat` (`id1` int unsigned DEFAULT NULL, `name1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT \'\') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
            Db::execute('ALTER TABLE `' . config('database.prefix') . 'vod_repeat` ADD INDEX `name1` (`name1`(100))');
        }
        Db::execute('INSERT INTO `' . config('database.prefix') . 'vod_repeat` (SELECT min(vod_id)as id1,vod_name as name1 FROM ' .
            config('database.prefix') . 'vod GROUP BY name1 HAVING COUNT(name1)>1)');
        Cache::set('vod_repeat_table_created_time',time());
    }

}