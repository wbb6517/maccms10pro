<?php
/**
 * 演员数据模型 (Actor Model)
 * ============================================================
 *
 * 【文件说明】
 * 演员/明星信息管理模型，负责演员数据的增删改查
 * 对应数据表: mac_actor
 *
 * 【方法列表】
 * ┌─────────────────────────┬────────────────────────────────────────────┐
 * │ 方法名                   │ 功能说明                                    │
 * ├─────────────────────────┼────────────────────────────────────────────┤
 * │ countData()             │ 统计符合条件的演员数量                       │
 * │ listData()              │ 获取演员列表 (分页查询)                      │
 * │ listCacheData()         │ 前台调用的缓存列表 (模板标签使用)            │
 * │ infoData()              │ 获取演员详情                                │
 * │ saveData()              │ 保存演员数据 (新增/编辑)                     │
 * │ delData()               │ 删除演员数据 (含清理关联文件)                │
 * │ fieldData()             │ 批量更新指定字段                            │
 * └─────────────────────────┴────────────────────────────────────────────┘
 *
 * 【核心字段说明】
 * ┌──────────────────┬─────────────────────────────────────────────────┐
 * │ 字段名            │ 说明                                             │
 * ├──────────────────┼─────────────────────────────────────────────────┤
 * │ actor_id         │ 演员ID (主键，自增)                              │
 * │ type_id          │ 分类ID                                          │
 * │ type_id_1        │ 一级分类ID (父分类)                              │
 * │ actor_name       │ 演员姓名                                         │
 * │ actor_en         │ 英文名/拼音 (用于URL)                            │
 * │ actor_alias      │ 别名                                             │
 * │ actor_status     │ 状态: 0=未审核, 1=已审核                          │
 * │ actor_level      │ 推荐等级: 0-9                                    │
 * │ actor_lock       │ 锁定: 0=否, 1=是 (锁定后采集不更新)               │
 * │ actor_sex        │ 性别                                             │
 * │ actor_area       │ 地区                                             │
 * │ actor_pic        │ 演员照片URL                                      │
 * │ actor_letter     │ 首字母 (A-Z, 用于字母索引)                        │
 * │ actor_starsign   │ 星座                                             │
 * │ actor_blood      │ 血型                                             │
 * │ actor_birthday   │ 生日                                             │
 * │ actor_height     │ 身高                                             │
 * │ actor_weight     │ 体重                                             │
 * │ actor_content    │ 详细介绍                                         │
 * │ actor_blurb      │ 简介 (自动截取100字)                              │
 * │ actor_tag        │ 标签 (逗号分隔)                                   │
 * │ actor_hits       │ 总点击量                                         │
 * │ actor_hits_day   │ 日点击量                                         │
 * │ actor_hits_week  │ 周点击量                                         │
 * │ actor_hits_month │ 月点击量                                         │
 * │ actor_time       │ 更新时间戳                                       │
 * │ actor_time_add   │ 添加时间戳                                       │
 * └──────────────────┴─────────────────────────────────────────────────┘
 *
 * 【关联表】
 * - mac_type: 分类表 (type_id)
 *
 * 【缓存机制】
 * - 详情页缓存: actor_detail_{id}、actor_detail_{en}
 * - 列表缓存: 使用 md5(查询条件) 作为缓存键
 *
 * @package     app\common\model
 * @author      MacCMS
 * @version     1.0
 */
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Actor extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'actor';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 获取状态文本 (获取器)
     *
     * @param mixed $val  原始值
     * @param array $data 当前行数据
     * @return string 状态文本
     */
    public function getActorStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['actor_status']];
    }

    /**
     * 统计演员数量
     *
     * @param array $where 查询条件
     * @return int 符合条件的演员数量
     */
    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    /**
     * 获取演员列表 (分页查询)
     *
     * 【功能说明】
     * 后台和API调用的核心列表方法
     * 支持分页、排序、字段筛选
     *
     * @param array|string $where     查询条件 (数组或JSON字符串)
     * @param string       $order     排序方式 (如 "actor_time desc")
     * @param int          $page      页码 (默认1)
     * @param int          $limit     每页条数 (默认20)
     * @param int          $start     偏移量 (默认0)
     * @param string       $field     查询字段 (默认*)
     * @param int          $addition  是否附加分类信息 (1=是)
     * @param int          $totalshow 是否统计总数 (1=是)
     * @return array 返回结构: code/msg/page/pagecount/limit/total/list
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1,$totalshow=1)
    {
        // 参数初始化
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        // 处理原生SQL条件
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        // 计算分页偏移
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        // 执行查询
        $list = Db::name('Actor')->field($field)->where($where)->where($where2)->orderRaw($order)->limit($limit_str)->select();

        // 附加分类信息
        $type_list = model('Type')->getCache('type_list');

        foreach($list as $k=>$v){
            if($addition==1){
                if(!empty($v['type_id'])) {
                    $list[$k]['type'] = $type_list[$v['type_id']];
                    $list[$k]['type_1'] = $type_list[$list[$k]['type']['type_pid']];
                }
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 前台缓存列表数据 (模板标签调用)
     *
     * 【功能说明】
     * 前台模板标签 {maccms:actor} 调用的核心方法
     * 支持多种筛选条件和缓存机制
     *
     * 【主要参数】 (通过 $lp 数组传入)
     * - order     : 排序字段
     * - by        : 排序方式 (asc/desc/rnd随机)
     * - type      : 分类ID
     * - ids       : 指定演员ID (逗号分隔)
     * - level     : 推荐等级筛选
     * - area      : 地区筛选
     * - sex       : 性别筛选
     * - letter    : 首字母筛选
     * - starsign  : 星座筛选
     * - blood     : 血型筛选
     * - wd        : 关键词搜索
     * - paging    : 是否分页 (yes/no)
     * - num       : 每页数量
     * - cachetime : 缓存时间
     *
     * @param array|string $lp 参数数组或JSON字符串
     * @return array 返回结构: code/msg/page/pagecount/limit/total/list/pageurl/half
     */
    public function listCacheData($lp)
    {
        // ========== 参数解析 ==========
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        // 提取各筛选参数
        $order = $lp['order'];          // 排序字段
        $by = $lp['by'];                // 排序方式
        $type = $lp['type'];            // 分类ID
        $ids = $lp['ids'];              // 指定ID列表
        $paging = $lp['paging'];        // 是否分页
        $pageurl = $lp['pageurl'];      // 分页URL模板
        $level = $lp['level'];          // 推荐等级
        $wd = $lp['wd'];                // 搜索关键词
        $name = $lp['name'];            // 演员姓名
        $area = $lp['area'];            // 地区
        $letter = $lp['letter'];        // 首字母
        $sex = $lp['sex'];              // 性别
        $starsign = $lp['starsign'];    // 星座
        $blood = $lp['blood'];          // 血型
        $start = intval(abs($lp['start']));   // 起始位置
        $num = intval(abs($lp['num']));       // 数量
        $half = intval(abs($lp['half']));     // 半分页
        $timeadd = $lp['timeadd'];      // 添加时间筛选
        $timehits = $lp['timehits'];    // 点击时间筛选
        $time = $lp['time'];            // 更新时间筛选
        $hitsmonth = $lp['hitsmonth'];  // 月点击量筛选
        $hitsweek = $lp['hitsweek'];    // 周点击量筛选
        $hitsday = $lp['hitsday'];      // 日点击量筛选
        $hits = $lp['hits'];            // 总点击量筛选
        $not = $lp['not'];              // 排除ID
        $cachetime = $lp['cachetime'];  // 缓存时间
        $typenot = $lp['typenot'];      // 排除分类
        $page = 1;
        $where = [];
        $totalshow=0;

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
            if(!empty($param['id'])) {
                //$type = intval($param['id']);
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
            }
            if(!empty($param['level'])) {
                if($param['level']=='all'){
                    $level = '1,2,3,4,5,6,7,8,9';
                }
                else{
                    $level = $param['level'];
                }
            }
            if(!empty($param['letter'])) {
                $letter = $param['letter'];
            }
            if(!empty($param['sex'])){
                $sex = $param['sex'];
            }
            if(!empty($param['area'])) {
                $area = $param['area'];
            }
            if(!empty($param['starsign'])){
                $starsign = $param['starsign'];
            }
            if(!empty($param['blood'])){
                $blood = $param['blood'];
            }

            if(!empty($param['wd'])) {
                $wd = $param['wd'];
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
            if(empty($pageurl)){
                $pageurl = 'actor/type';
            }
            $param['page'] = 'PAGELINK';
            if($pageurl=='actor/type' || $pageurl=='actor/show'){
                $type = intval( $GLOBALS['type_id'] );
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type];
                $flag='type';
                if($pageurl == 'actor/show'){
                    $flag='show';
                }
                $pageurl = mac_url_type($type_info,$param,$flag);
            }
            else{
                $pageurl = mac_url($pageurl,$param);
            }

        }

        $where['actor_status'] = ['eq',1];
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['actor_level'] = ['in',explode(',',$level)];
        }
        if(!empty($ids)) {
            if($ids!='all'){
                $where['actor_id'] = ['in',explode(',',$ids)];
            }
        }
        if(!empty($not)){
            $where['actor_id'] = ['not in',explode(',',$not)];
        }
        if(!empty($sex)){
            $where['actor_sex'] = ['eq',$sex];
        }
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['actor_letter'] = ['in',explode(',',$letter)];
        }

        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['actor_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['actor_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['actor_time'] =['gt',$s];
        }
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
        if(!empty($typenot)){
            $where['type_id'] = ['not in',$typenot];
        }
        if(!empty($tid)) {
            $where['type_id|type_id_1'] = ['eq',$tid];
        }
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['actor_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['actor_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['actor_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['actor_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['actor_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['actor_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['actor_hits'] = ['gt', $tmp];
            }
            else{
                $where['actor_hits'] = [$tmp[0],$tmp[1]];
            }
        }

        if(!empty($area)){
            $where['actor_area'] = ['in',explode(',',$area) ];
        }
        if(!empty($starsign)){
            $where['actor_starsign'] = ['in',explode(',',$starsign) ];
        }
        if(!empty($blood)){
            $where['actor_blood'] = ['in',explode(',',$blood) ];
        }

        if(!empty($name)){
            $where['actor_name'] = ['in',explode(',',$name) ];
        }
        if(!empty($wd)) {
            $where['actor_name|actor_en'] = ['like', '%' . $wd . '%'];
        }
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

        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd','in'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['actor_id']);
            $where_cache['order'] = 'rnd';
        }


        if($by=='in' && !empty($name) ){
            $order = ' find_in_set(actor_name, \''.$name.'\'  ) ';
        }
        else{
            if($by=='in' && empty($name) ){
                $by = 'time';
            }
            $order= 'actor_'.$by .' ' . $order;
        }

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('actor_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$page,$num,$start,'*',1,$totalshow);
            if($GLOBALS['config']['app']['cache_core']==1){
                Cache::set($cach_name, $res, $cachetime);
            }
        }
        $res['pageurl'] = $pageurl;
        $res['half'] = $half;
        return $res;
    }

    /**
     * 获取演员详情
     *
     * 【功能说明】
     * 获取单条演员的完整信息
     * 支持缓存机制，按 actor_id 或 actor_en 查询
     *
     * 【缓存键格式】
     * actor_detail_{actor_id}_{actor_en}
     *
     * @param array  $where 查询条件 (必须包含 actor_id 或 actor_en)
     * @param string $field 查询字段 (默认*)
     * @param int    $cache 是否使用缓存 (0=不使用, 1=使用)
     * @return array 返回结构: code/msg/info
     */
    public function infoData($where,$field='*',$cache=0)
    {
        // ========== 参数验证 ==========
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // ========== 缓存键生成 ==========
        $data_cache = false;
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'actor_detail_'.$where['actor_id'][1].'_'.$where['actor_en'][1];
        // 只有精确查询才使用缓存 (eq 条件)
        if($where['actor_id'][0]=='eq' || $where['actor_en'][0]=='eq'){
            $data_cache = true;
        }

        // ========== 尝试从缓存获取 ==========
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }

        // ========== 缓存未命中，查询数据库 ==========
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['actor_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();

            // ========== 附加分类信息 ==========
            // 获取演员所属分类及父分类信息
            if (!empty($info['type_id'])) {
                $type_list = model('Type')->getCache('type_list');
                $info['type'] = $type_list[$info['type_id']];
                $info['type_1'] = $type_list[$info['type']['type_pid']];
            }

            // ========== 写入缓存 ==========
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 保存演员数据 (新增/编辑)
     *
     * 【功能说明】
     * 保存演员数据到数据库
     * 自动处理拼音生成、图片URL、简介截取、XSS过滤等
     *
     * 【数据处理流程】
     * 1. 验证数据格式
     * 2. 清除相关缓存
     * 3. 自动填充 type_id_1 (父分类ID)
     * 4. 自动生成 actor_en (拼音) 和 actor_letter (首字母)
     * 5. 处理内容中的图片协议
     * 6. 自动生成 actor_blurb (简介)
     * 7. 可选更新时间和自动生成TAG
     * 8. XSS过滤所有字符串字段
     * 9. 保存到数据库
     *
     * @param array $data 演员数据数组
     *                    - actor_id: 有值则编辑，无值则新增
     *                    - uptime: 1=更新时间
     *                    - uptag: 1=自动生成TAG
     * @return array 返回结构: code/msg
     */
    public function saveData($data)
    {
        // ========== 步骤1: 数据验证 ==========
        $validate = \think\Loader::validate('Actor');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // ========== 步骤2: 清除相关缓存 ==========
        // 缓存键格式: actor_detail_{id}, actor_detail_{en}
        $key = 'actor_detail_'.$data['actor_id'];
        Cache::rm($key);
        $key = 'actor_detail_'.$data['actor_en'];
        Cache::rm($key);
        $key = 'actor_detail_'.$data['actor_id'].'_'.$data['actor_en'];
        Cache::rm($key);

        // ========== 步骤3: 自动填充一级分类ID ==========
        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$data['type_id']];
        $data['type_id_1'] = $type_info['type_pid'];

        // ========== 步骤4: 自动生成拼音和首字母 ==========
        // actor_en 为空时，根据演员姓名生成拼音
        if(empty($data['actor_en'])){
            $data['actor_en'] = Pinyin::get($data['actor_name']);
        }
        // actor_letter 为空时，取拼音首字母并转大写
        if(empty($data['actor_letter'])){
            $data['actor_letter'] = strtoupper(substr($data['actor_en'],0,1));
        }

        // ========== 步骤5: 处理内容中的图片URL ==========
        // 将图片URL中的协议替换为 mac: 前缀，便于前台自适应
        if(!empty($data['actor_content'])) {
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['actor_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['actor_content'] = str_replace($v1, $v2, $data['actor_content']);
                }
            }
            unset($match_src1);
        }

        // ========== 步骤6: 自动生成简介 ==========
        // actor_blurb 为空时，从 actor_content 提取前100字符
        if(empty($data['actor_blurb'])){
            $data['actor_blurb'] = mac_substring( strip_tags($data['actor_content']) ,100);
        }

        // ========== 步骤7: 处理更新时间和TAG ==========
        if($data['uptag']==1){
            $data['actor_tag'] = mac_get_tag($data['actor_name'], $data['actor_content']);
        }
        if($data['uptime']==1){
            $data['actor_time'] = time();
        }
        unset($data['uptime']);
        unset($data['uptag']);

        // ========== 步骤8: XSS过滤 ==========
        // 对所有可能包含用户输入的字符串字段进行XSS过滤
        $filter_fields = [
            'actor_name',
            'actor_en',
            'actor_alias',
            'actor_color',
            'actor_pic',
            'actor_blurb',
            'actor_remarks',
            'actor_area',
            'actor_height',
            'actor_weight',
            'actor_birthday',
            'actor_birtharea',
            'actor_blood',
            'actor_starsign',
            'actor_school',
            'actor_works',
            'actor_tag',
            'actor_class',
            'actor_tpl',
            'actor_jumpurl',
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        // ========== 步骤9: 执行数据库操作 ==========
        if(!empty($data['actor_id'])){
            // ----- 编辑模式 -----
            $where=[];
            $where['actor_id'] = ['eq',$data['actor_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // ----- 新增模式 -----
            $data['actor_time_add'] = time();
            $data['actor_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }

        // ========== 返回操作结果 ==========
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除演员数据
     *
     * 【功能说明】
     * 删除符合条件的演员数据
     * 同时清理关联的本地图片和静态HTML文件
     *
     * 【清理内容】
     * - actor_pic: 演员照片 (仅清理 ./upload 目录下的本地图片)
     * - 静态详情页HTML文件 (如果开启静态生成)
     *
     * @param array $where 删除条件
     * @return array 返回结构: code/msg
     */
    public function delData($where)
    {
        // ========== 步骤1: 获取待删除数据列表 ==========
        $list = $this->listData($where,'',1,9999);
        if($list['code'] !==1){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }

        // ========== 步骤2: 清理关联文件 ==========
        $path = './';
        foreach($list['list'] as $k=>$v){
            // ----- 删除演员照片 -----
            // 安全检查: 只删除 ./upload 目录下的本地图片
            $pic = $path.$v['actor_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }

            // ----- 删除静态HTML文件 -----
            // 当配置为静态生成时 (actor_detail==2)，删除对应的HTML文件
            if($GLOBALS['config']['view']['actor_detail'] ==2 ){
                $lnk = mac_url_actor_detail($v);
                $lnk = reset_html_filename($lnk);
                if(file_exists($lnk)){
                    unlink($lnk);
                }
            }
        }

        // ========== 步骤3: 执行数据库删除 ==========
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 批量更新指定字段
     *
     * 【功能说明】
     * 批量更新符合条件的演员的指定字段
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
        // ========== 参数验证 ==========
        if(!is_array($update)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // ========== 执行批量更新 ==========
        $res = $this->allowField(true)->where($where)->update($update);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        // ========== 清除相关缓存 ==========
        // 查询被更新的演员，逐一清除其详情缓存
        $list = $this->field('actor_id,actor_name,actor_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'actor_detail_'.$v['actor_id'];
            Cache::rm($key);
            $key = 'actor_detail_'.$v['actor_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}