<?php
/**
 * 漫画数据模型 (Manga Model)
 * ============================================================
 *
 * 【文件说明】
 * 漫画内容数据模型
 * 提供漫画的增删改查、列表查询、缓存管理等核心功能
 * 支持前台模板标签调用和后台管理
 *
 * 【数据表】
 * mac_manga - 漫画数据表
 *
 * 【方法列表】
 * ┌───────────────────────────┬─────────────────────────────────────────────┐
 * │ 方法名                     │ 功能说明                                     │
 * ├───────────────────────────┼─────────────────────────────────────────────┤
 * │ getMangaStatusTextAttr()  │ 获取器: 状态文本                             │
 * │ getMangaContentTextAttr() │ 获取器: 内容数组                             │
 * │ countData()               │ 统计数据条数                                 │
 * │ listData()                │ 分页列表查询 (后台)                          │
 * │ listRepeatData()          │ 重复数据列表查询                             │
 * │ listCacheData()           │ 缓存列表查询 (前台标签)                       │
 * │ infoData()                │ 获取漫画详情                                 │
 * │ saveData()                │ 保存漫画数据                                 │
 * │ delData()                 │ 删除漫画数据                                 │
 * │ fieldData()               │ 更新指定字段                                 │
 * │ updateToday()             │ 获取今日更新列表                             │
 * └───────────────────────────┴─────────────────────────────────────────────┘
 *
 * 【缓存键说明】
 * - manga_listcache_{hash} : 前台列表缓存
 * - manga_detail_{id}      : 漫画详情缓存 (按ID)
 * - manga_detail_{en}      : 漫画详情缓存 (按英文名)
 *
 * 【相关文件】
 * - application/admin/controller/Manga.php : 后台控制器
 * - application/index/controller/Manga.php : 前台控制器
 * - application/common/validate/Manga.php  : 数据验证器
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Manga extends Base {
    /**
     * 数据表名 (不含前缀)
     * @var string
     */
    protected $name = 'manga';

    /**
     * 时间戳字段 (禁用自动写入)
     */
    protected $createTime = '';
    protected $updateTime = '';

    /**
     * 自动完成配置
     */
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 获取器: 状态文本
     * 将数字状态转换为文本显示
     */
    public function getMangaStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['manga_status']];
    }

    /**
     * 获取器: 内容数组
     * 将$$$分隔的内容转换为数组
     */
    public function getMangaContentTextAttr($val,$data)
    {
        $arr = explode('$$$',$data['manga_content']);
        return $arr;
    }

    /**
     * ============================================================
     * 统计数据条数
     * ============================================================
     *
     * @param array $where 查询条件
     * @return int 数据条数
     */
    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    /**
     * ============================================================
     * 分页列表查询 (后台)
     * ============================================================
     *
     * 【功能说明】
     * 后台管理使用的分页列表查询
     * 支持条件筛选、排序、分页、附加关联数据
     *
     * @param array  $where     查询条件
     * @param string $order     排序规则
     * @param int    $page      当前页码
     * @param int    $limit     每页数量
     * @param int    $start     起始偏移
     * @param string $field     查询字段
     * @param int    $addition  是否附加分类/用户组数据
     * @param int    $totalshow 是否统计总数
     * @return array 列表数据 (code,msg,page,pagecount,limit,total,list)
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1,$totalshow=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        // 特殊字符串条件
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $limit_str = ($limit * ($page-1) + $start) .",". $limit;
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        $list = Db::name('Manga')->field($field)->where($where)->where($where2)->order($order)->limit($limit_str)->select();

        // 附加分类和用户组数据
        $type_list = model('Type')->getCache('type_list');
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
     * ============================================================
     * 重复数据列表查询
     * ============================================================
     *
     * 【功能说明】
     * 查询重复的漫画数据 (同名漫画)
     * 通过JOIN临时表实现重复数据检测
     *
     * @param array  $where    查询条件
     * @param string $order    排序规则
     * @param int    $page     当前页码
     * @param int    $limit    每页数量
     * @param int    $start    起始偏移
     * @param string $field    查询字段
     * @param int    $addition 是否附加关联数据
     * @return array 列表数据
     */
    public function listRepeatData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",". $limit;

        // JOIN临时表查询重复数据
        $total = $this
            ->join('tmpmanga t','t.name1 = manga_name')
            ->where($where)
            ->count();

        $list = Db::name('Manga')
            ->join('tmpmanga t','t.name1 = manga_name')
            ->field($field)
            ->where($where)
            ->order($order)
            ->limit($limit_str)
            ->select();

        // 附加分类和用户组数据
        $type_list = model('Type')->getCache('type_list');
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
     * ============================================================
     * 缓存列表查询 (前台标签)
     * ============================================================
     *
     * 【功能说明】
     * 前台模板标签 {maccms:manga} 使用的数据查询
     * 支持丰富的筛选参数和缓存机制
     *
     * 【标签参数】
     * - order     : 排序字段 (id/time/time_add/score/hits等)
     * - by        : 排序方向 (asc/desc/rnd随机)
     * - type      : 分类ID (current=当前分类, all=全部)
     * - ids       : 指定ID列表
     * - level     : 推荐等级
     * - wd        : 关键词搜索
     * - tag       : Tag标签
     * - class     : 扩展分类
     * - letter    : 首字母
     * - num       : 数量
     * - start     : 起始位置
     * - paging    : 是否分页 (yes/no)
     * - pageurl   : 分页URL规则
     * - cachetime : 缓存时间
     *
     * @param array|string $lp 标签参数
     * @return array 列表数据 (含分页URL)
     */
    public function listCacheData($lp)
    {
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        // ==================== 解析标签参数 ====================
        $order = $lp['order'];
        $by = $lp['by'];
        $type = $lp['type'];
        $ids = $lp['ids'];
        $rel = $lp['rel'];
        $paging = $lp['paging'];
        $pageurl = $lp['pageurl'];
        $level = $lp['level'];
        $wd = $lp['wd'];
        $tag = $lp['tag'];
        $class = $lp['class'];
        $letter = $lp['letter'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $half = intval(abs($lp['half']));
        $timeadd = $lp['timeadd'];
        $timehits = $lp['timehits'];
        $time = $lp['time'];
        $hitsmonth = $lp['hitsmonth'];
        $hitsweek = $lp['hitsweek'];
        $hitsday = $lp['hitsday'];
        $hits = $lp['hits'];
        $not = $lp['not'];
        $cachetime = $lp['cachetime'];
        $typenot = $lp['typenot'];
        $name = $lp['name'];
        $page = 1;
        $where = [];
        $totalshow=0;

        // ==================== 参数初始化 ====================
        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }
        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }

        // ==================== 分页模式处理 ====================
        $param = mac_param_url();
        if($paging=='yes') {
            $param = mac_search_len_check($param);
            $totalshow = 1;
            // 从URL参数获取筛选条件
            if(!empty($param['id'])) {
                //$type = intval($param['id']);
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
            }
            if(!empty($param['tid'])) {
                $tid = intval($param['tid']);
            }
            if(!empty($param['level'])) {
                $level = $param['level'];
            }
            if(!empty($param['letter'])) {
                $letter = $param['letter'];
            }
            if(!empty($param['wd'])) {
                $wd = $param['wd'];
            }
            if(!empty($param['name'])) {
                $name = $param['name'];
            }
            if(!empty($param['tag'])) {
                $tag = $param['tag'];
            }
            if(!empty($param['class'])) {
                $class = $param['class'];
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
            foreach($param as $k=>$v){
                if(empty($v)){
                    unset($param[$k]);
                }
            }
            // 构建分页URL
            if(empty($pageurl)){
                $pageurl = 'manga/type';
            }
            $param['page'] = 'PAGELINK';

            if($pageurl=='manga/type' || $pageurl=='manga/show'){
                $type = intval( $GLOBALS['type_id'] );
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type];
                $flag='type';
                if($pageurl == 'manga/show'){
                    $flag='show';
                }
                $pageurl = mac_url_type($type_info,$param,$flag);
            }
            else{
                $pageurl = mac_url($pageurl,$param);
            }

        }

        // ==================== 构建查询条件 ====================
        // 只查询已审核的漫画
        $where['manga_status'] = ['eq',1];

        // 推荐等级筛选
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['manga_level'] = ['in',explode(',',$level)];
        }
        // ID筛选
        if(!empty($ids)) {
            if($ids!='all'){
                $where['manga_id'] = ['in',explode(',',$ids)];
            }
        }
        // 排除ID
        if(!empty($not)){
            $where['manga_id'] = ['not in',explode(',',$not)];
        }
        // 关联筛选
        if(!empty($rel)){
            $tmp = explode(',',$rel);
            if(is_numeric($rel) || mac_array_check_num($tmp)==true ){
                $where['manga_id'] = ['in',$tmp];
            }
            else{
                $where['manga_rel_manga'] = ['like', mac_like_arr($rel),'OR'];
            }
        }
        // 首字母筛选
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['manga_letter'] = ['in',explode(',',$letter)];
        }
        // 时间筛选
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['manga_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['manga_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['manga_time'] =['gt',$s];
        }
        // 分类筛选 (包含子分类)
        if(!empty($type)) {
            if($type=='current'){
                $type = intval( $GLOBALS['type_id'] );
            }
            if($type!='all') {
                $tmp_arr = explode(',', $type);
                $type_list = model('Type')->getCache('type_list');
                $type = [];
                foreach ($type_list as $k2 => $v2) {
                    if (in_array($v2['type_id'] . '', $tmp_arr) || in_array($v2['type_pid'] . '', $tmp_arr)) {
                        $type[] = $v2['type_id'];
                    }
                }
                $type = array_unique($type);
                $where['type_id'] = ['in', implode(',', $type)];
            }
        }
        // 排除分类
        if(!empty($typenot)){
            $where['type_id'] = ['not in',$typenot];
        }
        if(!empty($tid)) {
            $where['type_id|type_id_1'] = ['eq',$tid];
        }
        // 点击量筛选
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['manga_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['manga_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['manga_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['manga_hits'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits'] = [$tmp[0],$tmp[1]];
            }
        }

        // 关键词搜索
        if(!empty($wd)) {
            $role = 'manga_name';
            if(!empty($GLOBALS['config']['app']['search_manga_rule'])){
                $role .= '|'.$GLOBALS['config']['app']['search_manga_rule'];
            }
            $where[$role] = ['like', '%' . $wd . '%'];
        }
        // 名称模糊匹配
        if(!empty($name)) {
            $where['manga_name'] = ['like', mac_like_arr($name),'OR'];
        }
        // Tag标签筛选
        if(!empty($tag)) {
            $where['manga_tag'] = ['like', mac_like_arr($tag),'OR'];
        }
        // 扩展分类筛选
        if(!empty($class)) {
            $where['manga_class'] = ['like',mac_like_arr($class),'OR'];
        }
        // 权限过滤 (前台)
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
        // 随机排序处理
        if($by=='rnd'){
            $data_count = $this->countData($where);
            $page_total = floor($data_count / $lp['num']) + 1;
            if($data_count < $lp['num']){
                $lp['num'] = $data_count;
            }
            $randi = @mt_rand(1, $page_total);
            $page = $randi;
            $by = 'hits_week';
            $order = 'desc';
        }

        // ==================== 排序处理 ====================
        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        $order= 'manga_'.$by .' ' . $order;

        // ==================== 缓存处理 ====================
        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['manga_id']);
            $where_cache['order'] = 'rnd';
        }
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('manga_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$page,$num,$start,'*',1,$totalshow);
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cachetime);
            }
        }
        $res['pageurl'] = $pageurl;
        $res['half'] = $half;
        return $res;
    }

    /**
     * ============================================================
     * 获取漫画详情
     * ============================================================
     *
     * 【功能说明】
     * 获取单条漫画的详细信息
     * 支持缓存，自动解析章节列表和关联数据
     *
     * @param array  $where 查询条件
     * @param string $field 查询字段
     * @param int    $cache 是否使用缓存
     * @return array 漫画详情 (code,msg,info)
     */
    public function infoData($where,$field='*',$cache=0)
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $data_cache = false;
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'manga_detail_'.$where['manga_id'][1].'_'.$where['manga_en'][1];

        // 精确查询时启用缓存
        if($where['manga_id'][0]=='eq' || $where['manga_en'][0]=='eq'){
            $data_cache = true;
        }
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['manga_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();

            // 解析章节列表
            if (!empty($info['manga_chapter_url'])) {
                $info['manga_page_list'] = mac_manga_list($info['manga_chapter_from'], $info['manga_chapter_url'], $info['manga_play_server'], $info['manga_play_note']);
                $info['manga_page_total'] = count($info['manga_page_list']);
            }
            // 解析截图列表
            if(!empty($info['manga_pic_screenshot'])){
                $info['manga_pic_screenshot_list'] = mac_screenshot_list($info['manga_pic_screenshot']);
            }
            // 附加分类数据
            if (!empty($info['type_id'])) {
                $type_list = model('Type')->getCache('type_list');
                $info['type'] = $type_list[$info['type_id']];
                $info['type_1'] = $type_list[$info['type']['type_pid']];
            }

            // 附加用户组数据
            if (!empty($info['group_id'])) {
                $group_list = model('Group')->getCache('group_list');
                $info['group'] = $group_list[$info['group_id']];
            }
            // 写入缓存
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * ============================================================
     * 保存漫画数据
     * ============================================================
     *
     * 【功能说明】
     * 新增或更新漫画数据
     * 包含数据验证、自动处理拼音/首字母/简介/Tag等
     *
     * 【自动处理】
     * - manga_en     : 自动生成拼音
     * - manga_letter : 自动提取首字母
     * - manga_blurb  : 自动截取简介
     * - manga_tag    : 可选自动生成Tag
     * - type_id_1    : 自动设置一级分类
     *
     * @param array $data 漫画数据
     * @return array 保存结果 (code,msg)
     */
    public function saveData($data)
    {
        // 数据验证
        $validate = \think\Loader::validate('Manga');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // 清除相关缓存
        $key = 'manga_detail_'.$data['manga_id'];
        Cache::rm($key);
        $key = 'manga_detail_'.$data['manga_en'];
        Cache::rm($key);
        $key = 'manga_detail_'.$data['manga_id'].'_'.$data['manga_en'];
        Cache::rm($key);

        // 设置一级分类ID
        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$data['type_id']];
        $data['type_id_1'] = $type_info['type_pid'];

        // 自动生成拼音
        if(empty($data['manga_en'])){
            $data['manga_en'] = Pinyin::get($data['manga_name']);
        }
        // 自动提取首字母
        if(empty($data['manga_letter'])){
            $data['manga_letter'] = strtoupper(substr($data['manga_en'],0,1));
        }
        // 处理截图换行符
        if(!empty($data['manga_pic_screenshot'])){
            $data['manga_pic_screenshot'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['manga_pic_screenshot']);
        }
        // 处理内容字段 (数组转字符串)
        if(!empty($data['manga_content'])) {
            if(is_array($data['manga_content'])){
                $data['manga_content'] = join('$$$', $data['manga_content']);
            }
            if(is_array($data['manga_title'])){
                $data['manga_title'] = join('$$$', $data['manga_title']);
            }
            if(is_array($data['manga_note'])){
                $data['manga_note'] = join('$$$', $data['manga_note']);
            }

            // 提取内容中的图片，处理协议前缀
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\"](.*?)[\"|\"][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['manga_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['manga_content'] = str_replace($v1, $v2, $data['manga_content']);
                }
                // 自动提取封面图
                if (empty($data['manga_pic'])) {
                    $data['manga_pic'] = (string)$match_src1[1][0];
                }
            }
            unset($match_src1);
        }

        // 自动生成简介
        if(empty($data['manga_blurb'])){
            $data['manga_blurb'] = mac_substring( str_replace('$$$','', strip_tags($data['manga_content'])),100);
        }

        // 更新时间选项
        if($data['uptime']==1){
            $data['manga_time'] = time();
        }
        // 自动生成Tag选项
        if($data['uptag']==1){
            $data['manga_tag'] = mac_get_tag($data['manga_name'], $data['manga_content']);
        }
        unset($data['uptime']);
        unset($data['uptag']);

        // XSS过滤
        $filter_fields = [
            'manga_name',
            'manga_sub',
            'manga_en',
            'manga_color',
            'manga_from',
            'manga_author',
            'manga_tag',
            'manga_class',
            'manga_pic',
            'manga_pic_thumb',
            'manga_pic_slide',
            'manga_blurb',
            'manga_remarks',
            'manga_jumpurl',
            'manga_tpl',
            'manga_rel_manga',
            'manga_rel_vod',
            'manga_pwd',
            'manga_pwd_url',
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        // 执行保存
        if(!empty($data['manga_id'])){
            // 更新
            $where=[];
            $where['manga_id'] = ['eq',$data['manga_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增
            $data['manga_time_add'] = time();
            $data['manga_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * ============================================================
     * 删除漫画数据
     * ============================================================
     *
     * 【功能说明】
     * 删除漫画记录及相关文件
     * 自动删除本地图片和静态页面文件
     *
     * @param array $where 删除条件
     * @return array 删除结果 (code,msg)
     */
    public function delData($where)
    {
        // 获取待删除列表
        $list = $this->listData($where,'',1,9999);
        if($list['code'] !==1){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        $path = './';

        // 删除关联文件
        foreach($list['list'] as $k=>$v){
            // 删除封面图
            $pic = $path.$v['manga_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            // 删除缩略图
            $pic = $path.$v['manga_pic_thumb'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            // 删除幻灯图
            $pic = $path.$v['manga_pic_slide'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            // 删除静态详情页
            if($GLOBALS['config']['view']['manga_detail'] ==2 ){
                $lnk = mac_url_manga_detail($v);
                $lnk = reset_html_filename($lnk);
                if(file_exists($lnk)){
                    unlink($lnk);
                }
            }
        }

        // 删除数据库记录
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }

        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * ============================================================
     * 更新指定字段
     * ============================================================
     *
     * 【功能说明】
     * 批量更新漫画的指定字段
     * 同时清除相关缓存
     *
     * @param array $where  更新条件
     * @param array $update 更新数据
     * @return array 更新结果 (code,msg)
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

        // 清除相关缓存
        $list = $this->field('manga_id,manga_name,manga_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'manga_detail_'.$v['manga_id'];
            Cache::rm($key);
            $key = 'manga_detail_'.$v['manga_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    /**
     * ============================================================
     * 获取今日更新列表
     * ============================================================
     *
     * 【功能说明】
     * 获取今天更新的漫画ID或分类ID列表
     * 用于前台今日更新数据展示
     *
     * @param string $flag 返回类型: manga=漫画ID, type=分类ID
     * @return array 结果 (code,msg,data)
     */
    public function updateToday($flag='manga')
    {
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $where['manga_time'] = ['gt',$today];

        if($flag=='type'){
            // 返回今日更新涉及的分类ID
            $ids = $this->where($where)->column('type_id');
        }
        else{
            // 返回今日更新的漫画ID
            $ids = $this->where($where)->column('manga_id');
        }
        if(empty($ids)){
            $ids = [];
        }else{
            $ids = array_unique($ids);
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'data'=> join(',',$ids) ];
    }

}
