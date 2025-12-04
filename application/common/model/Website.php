<?php
/**
 * 网址数据模型 (Website Model)
 * ============================================================
 *
 * 【文件说明】
 * 网址/网站导航数据模型
 * 管理外部网址资源，支持友情链接、网站导航等功能
 * 提供回链统计、访问量统计等数据分析功能
 *
 * 【数据表】
 * mac_website - 网址数据表
 *
 * 【数据表字段说明】
 * ┌───────────────────────┬───────────────────────────────────────────┐
 * │ 字段名                 │ 说明                                       │
 * ├───────────────────────┼───────────────────────────────────────────┤
 * │ website_id            │ 网址ID (主键自增)                           │
 * │ type_id               │ 分类ID                                     │
 * │ type_id_1             │ 一级分类ID                                  │
 * │ group_id              │ 用户组ID (可访问的用户组)                    │
 * │ website_name          │ 网址名称                                    │
 * │ website_sub           │ 网址副标题                                  │
 * │ website_en            │ 网址英文名/拼音                             │
 * │ website_letter        │ 首字母                                     │
 * │ website_color         │ 标题颜色                                    │
 * │ website_pic           │ 网址图标/封面图                             │
 * │ website_logo          │ 网站LOGO                                   │
 * │ website_pic_screenshot│ 网站截图 (多张用#分隔)                      │
 * │ website_jumpurl       │ 跳转链接                                    │
 * │ website_area          │ 地区                                       │
 * │ website_lang          │ 语言                                       │
 * │ website_tag           │ 标签                                       │
 * │ website_class         │ 扩展分类                                    │
 * │ website_level         │ 推荐等级 (1-9)                              │
 * │ website_status        │ 状态 (0=未审核, 1=已审核)                    │
 * │ website_lock          │ 锁定 (0=未锁定, 1=已锁定)                    │
 * │ website_score         │ 评分                                       │
 * │ website_score_all     │ 总评分                                     │
 * │ website_score_num     │ 评分人数                                    │
 * │ website_hits          │ 总点击量                                    │
 * │ website_hits_day      │ 日点击量                                    │
 * │ website_hits_week     │ 周点击量                                    │
 * │ website_hits_month    │ 月点击量                                    │
 * │ website_refer         │ 总回链数                                    │
 * │ website_refer_day     │ 日回链数                                    │
 * │ website_refer_week    │ 周回链数                                    │
 * │ website_refer_month   │ 月回链数                                    │
 * │ website_time          │ 更新时间 (时间戳)                           │
 * │ website_time_add      │ 添加时间 (时间戳)                           │
 * │ website_time_hits     │ 点击时间 (时间戳)                           │
 * │ website_time_make     │ 生成时间 (时间戳)                           │
 * │ website_content       │ 网址详细介绍                                │
 * │ website_remarks       │ 备注                                       │
 * │ website_tpl           │ 自定义模板                                  │
 * │ website_blurb         │ 简介                                       │
 * └───────────────────────┴───────────────────────────────────────────┘
 *
 * 【方法列表】
 * ┌────────────────────────┬─────────────────────────────────────────────┐
 * │ 方法名                  │ 功能说明                                     │
 * ├────────────────────────┼─────────────────────────────────────────────┤
 * │ getWebsiteStatusText() │ 获取器：状态文本转换                          │
 * │ countData()            │ 统计数据记录数量                              │
 * │ listData()             │ 获取网址列表数据                              │
 * │ listRepeatData()       │ 获取重复网址数据列表                          │
 * │ listCacheData()        │ 前台模板标签数据查询 (带缓存)                  │
 * │ infoData()             │ 获取网址详情                                  │
 * │ saveData()             │ 保存网址数据 (新增/更新)                       │
 * │ delData()              │ 删除网址数据                                  │
 * │ fieldData()            │ 更新指定字段                                  │
 * │ updateToday()          │ 获取今日更新数据                              │
 * │ visit()                │ 记录回链访问                                  │
 * └────────────────────────┴─────────────────────────────────────────────┘
 *
 * 【缓存键说明】
 * - website_detail_{id} : 网址详情缓存
 * - website_detail_{en} : 按英文名的详情缓存
 * - website_listcache_* : 列表查询缓存
 *
 * 【相关文件】
 * - application/admin/controller/Website.php : 后台管理控制器
 * - application/index/controller/Website.php : 前台展示控制器
 * - application/common/validate/Website.php : 数据验证器
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Website extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'website';

    // 定义时间戳字段名（不使用自动时间戳）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成配置
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * ============================================================
     * 获取器：状态文本转换
     * ============================================================
     *
     * 将状态数值转换为可读文本
     * 0=禁用, 1=启用
     *
     * @param mixed $val  原始值
     * @param array $data 完整数据
     * @return string 状态文本
     */
    public function getWebsiteStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['website_status']];
    }

    /**
     * ============================================================
     * 统计数据记录数量
     * ============================================================
     *
     * @param array $where 查询条件
     * @return int 记录总数
     */
    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    /**
     * ============================================================
     * 获取网址列表数据
     * ============================================================
     *
     * 【功能说明】
     * 分页查询网址列表，支持多条件筛选
     * 自动关联分类和用户组信息
     *
     * @param array|string $where     查询条件 (支持JSON字符串)
     * @param string       $order     排序规则
     * @param int          $page      当前页码
     * @param int          $limit     每页数量
     * @param int          $start     起始偏移量
     * @param string       $field     查询字段
     * @param int          $addition  是否附加关联数据 (1=是)
     * @param int          $totalshow 是否统计总数 (1=是)
     * @return array 包含列表数据和分页信息
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1,$totalshow=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        // JSON字符串转数组
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        // 提取自定义字符串条件
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        // 计算分页偏移
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        // 统计总数
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        // 查询列表数据
        $list = Db::name('Website')->field($field)->where($where)->where($where2)->orderRaw($order)->limit($limit_str)->select();
        // 获取分类缓存
        $type_list = model('Type')->getCache('type_list');
        // 获取用户组缓存
        $group_list = model('Group')->getCache('group_list');

        // 附加关联数据：分类信息和用户组信息
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
     * 获取重复网址数据列表
     * ============================================================
     *
     * 【功能说明】
     * 通过临时表查询同名的重复网址记录
     * 用于后台清理重复数据功能
     *
     * 【依赖】
     * 需要先创建临时表 mac_tmpwebsite (由控制器完成)
     *
     * @param array  $where    查询条件
     * @param string $order    排序规则
     * @param int    $page     当前页码
     * @param int    $limit    每页数量
     * @param int    $start    起始偏移量
     * @param string $field    查询字段
     * @param int    $addition 是否附加关联数据
     * @return array 包含列表数据和分页信息
     */
    public function listRepeatData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        // JSON字符串转数组
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        // 计算分页偏移
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        // 关联临时表统计重复数据总数
        $total = $this
            ->join('tmpwebsite t','t.name1 = website_name')
            ->where($where)
            ->count();

        // 关联临时表查询重复数据列表
        $list = $this
            ->join('tmpwebsite t','t.name1 = website_name')
            ->field($field)
            ->where($where)
            ->order($order)
            ->limit($limit_str)
            ->select();

        // 获取分类缓存
        $type_list = model('Type')->getCache('type_list');
        // 获取用户组缓存
        $group_list = model('Group')->getCache('group_list');

        // 附加关联数据
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
     * 前台模板标签数据查询 (带缓存)
     * ============================================================
     *
     * 【功能说明】
     * 前台模板标签 {maccms:website} 的数据查询方法
     * 支持丰富的筛选条件和分页功能
     * 自动处理缓存以提升性能
     *
     * 【支持的参数】
     * - order     : 排序字段 (id/time/hits/score/level等)
     * - by        : 排序方式 (asc/desc/rnd随机)
     * - type      : 分类ID (支持current当前分类)
     * - ids       : 指定网址ID列表
     * - paging    : 是否分页 (yes/no)
     * - level     : 推荐等级筛选
     * - wd        : 关键词搜索
     * - tag       : 标签筛选
     * - class     : 扩展分类筛选
     * - area/lang : 地区/语言筛选
     * - letter    : 首字母筛选
     * - hits*     : 点击量筛选
     * - refer*    : 回链数筛选
     * - time*     : 时间筛选
     * - num       : 获取数量
     * - start     : 起始位置
     * - cachetime : 缓存时间
     *
     * @param array|string $lp 标签参数
     * @return array 包含列表数据和分页信息
     */
    public function listCacheData($lp)
    {
        // JSON字符串转数组
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        // ==================== 提取标签参数 ====================
        $order = $lp['order'];           // 排序字段
        $by = $lp['by'];                 // 排序方式
        $type = $lp['type'];             // 分类ID
        $ids = $lp['ids'];               // 指定ID列表
        $paging = $lp['paging'];         // 是否分页
        $pageurl = $lp['pageurl'];       // 分页URL模式
        $level = $lp['level'];           // 推荐等级
        $wd = $lp['wd'];                 // 搜索关键词
        $tag = $lp['tag'];               // 标签筛选
        $class = $lp['class'];           // 扩展分类
        $name = $lp['name'];             // 名称筛选
        $area = $lp['area'];             // 地区筛选
        $lang = $lp['lang'];             // 语言筛选
        $letter = $lp['letter'];         // 首字母筛选
        $start = intval(abs($lp['start']));   // 起始位置
        $num = intval(abs($lp['num']));       // 获取数量
        $half = intval(abs($lp['half']));     // 分半显示
        $timeadd = $lp['timeadd'];       // 添加时间筛选
        $timehits = $lp['timehits'];     // 点击时间筛选
        $time = $lp['time'];             // 更新时间筛选
        $hitsmonth = $lp['hitsmonth'];   // 月点击量
        $hitsweek = $lp['hitsweek'];     // 周点击量
        $hitsday = $lp['hitsday'];       // 日点击量
        $hits = $lp['hits'];             // 总点击量
        $not = $lp['not'];               // 排除ID列表
        $cachetime = $lp['cachetime'];   // 缓存时间
        $typenot = $lp['typenot'];       // 排除分类
        $refermonth = $lp['refermonth']; // 月回链数
        $referweek = $lp['referweek'];   // 周回链数
        $referday = $lp['referday'];     // 日回链数
        $refer = $lp['refer'];           // 总回链数

        $page = 1;
        $where = [];
        $totalshow=0;

        // ==================== 参数默认值处理 ====================
        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }
        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }

        // ==================== 分页模式参数处理 ====================
        $param = mac_param_url();
        if($paging=='yes') {
            $param = mac_search_len_check($param);
            $totalshow = 1;
            // 从URL参数中获取筛选条件
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
            if(!empty($param['area'])) {
                $area = $param['area'];
            }
            if(!empty($param['lang'])) {
                $lang = $param['lang'];
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
            // 清除空参数
            foreach($param as $k=>$v){
                if(empty($v)){
                    unset($param[$k]);
                }
            }
            // 构建分页URL
            if(empty($pageurl)){
                $pageurl = 'website/type';
            }
            $param['page'] = 'PAGELINK';
            if($pageurl=='website/type' || $pageurl=='website/show'){
                $type = intval( $GLOBALS['type_id'] );
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type];
                $flag='type';
                if($pageurl == 'website/show'){
                    $flag='show';
                }
                $pageurl = mac_url_type($type_info,$param,$flag);
            }
            else{
                $pageurl = mac_url($pageurl,$param);
            }
        }

        // ==================== 构建查询条件 ====================
        // 只查询已审核数据
        $where['website_status'] = ['eq',1];
        // 等级筛选
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['website_level'] = ['in',explode(',',$level)];
        }
        // 指定ID筛选
        if(!empty($ids)) {
            if($ids!='all'){
                $where['website_id'] = ['in',explode(',',$ids)];
            }
        }
        // 排除ID
        if(!empty($not)){
            $where['website_id'] = ['not in',explode(',',$not)];
        }
        // 首字母筛选 (0-9统一处理)
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['website_letter'] = ['in',explode(',',$letter)];
        }

        // 时间条件筛选
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['website_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['website_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['website_time'] =['gt',$s];
        }
        // 分类筛选 (支持current当前分类，自动包含子分类)
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
        // 点击量筛选条件
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['website_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['website_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['website_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['website_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['website_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['website_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['website_hits'] = ['gt', $tmp];
            }
            else{
                $where['website_hits'] = [$tmp[0],$tmp[1]];
            }
        }
        // 回链数筛选条件
        if(!empty($refermonth)){
            $tmp = explode(' ',$refermonth);
            if(count($tmp)==1){
                $where['website_refer_month'] = ['gt', $tmp];
            }
            else{
                $where['website_refer_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($referweek)){
            $tmp = explode(' ',$referweek);
            if(count($tmp)==1){
                $where['website_refer_week'] = ['gt', $tmp];
            }
            else{
                $where['website_refer_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($referday)){
            $tmp = explode(' ',$referday);
            if(count($tmp)==1){
                $where['website_refer_day'] = ['gt', $tmp];
            }
            else{
                $where['website_refer_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($refer)){
            $tmp = explode(' ',$refer);
            if(count($tmp)==1){
                $where['website_refer'] = ['gt', $tmp];
            }
            else{
                $where['website_refer'] = [$tmp[0],$tmp[1]];
            }
        }

        // 地区/语言/名称筛选
        if(!empty($area)){
            $where['website_area'] = ['in',explode(',',$area) ];
        }
        if(!empty($lang)){
            $where['website_lang'] = ['in',explode(',',$lang) ];
        }
        if(!empty($name)){
            $where['website_name'] = ['in',explode(',',$name) ];
        }
        // 关键词搜索 (同时搜索名称和英文名)
        if(!empty($wd)) {
            $where['website_name|website_en'] = ['like', '%' . $wd . '%'];
        }
        // 标签/扩展分类模糊匹配
        if(!empty($tag)) {
            $where['website_tag'] = ['like', mac_like_arr($tag),'OR'];
        }
        if(!empty($class)) {
            $where['website_class'] = ['like',mac_like_arr($class),'OR'];
        }
        // 随机排序处理：计算随机页码
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

        // ==================== 排序规则处理 ====================
        // 验证排序字段合法性
        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd','in','referer','referer_day','referer_week','referer_month'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        // 缓存条件处理
        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['website_id']);
            $where_cache['order'] = 'rnd';
        }

        // 构建排序语句 (in排序使用find_in_set)
        if($by=='in' && !empty($name) ){
            $order = ' find_in_set(website_name, \''.$name.'\'  ) ';
        }
        else{
            if($by=='in' && empty($name) ){
                $by = 'time';
            }
            $order= 'website_'.$by .' ' . $order;
        }

        // ==================== 缓存处理 ====================
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('website_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        // 无缓存或禁用缓存时查询数据库
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
     * ============================================================
     * 获取网址详情
     * ============================================================
     *
     * 【功能说明】
     * 获取单条网址的详细信息
     * 支持缓存，自动关联分类信息
     * 处理截图列表字段
     *
     * @param array  $where 查询条件
     * @param string $field 查询字段
     * @param int    $cache 是否使用缓存 (1=是)
     * @return array 包含网址详情的数组
     */
    public function infoData($where,$field='*',$cache=0)
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        // 构建缓存键
        $data_cache = false;
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'website_detail_'.$where['website_id'][1].'_'.$where['website_en'][1];
        if($where['website_id'][0]=='eq' || $where['website_en'][0]=='eq'){
            $data_cache = true;
        }
        // 尝试从缓存获取
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }
        // 缓存未命中则查询数据库
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['website_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();
            // 处理截图列表
            if(!empty($info['website_pic_screenshot'])){
                $info['website_pic_screenshot_list'] = mac_screenshot_list($info['website_pic_screenshot']);
            }
            // 关联分类信息
            if (!empty($info['type_id'])) {
                $type_list = model('Type')->getCache('type_list');
                $info['type'] = $type_list[$info['type_id']];
                $info['type_1'] = $type_list[$info['type']['type_pid']];
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
     * 保存网址数据 (新增/更新)
     * ============================================================
     *
     * 【功能说明】
     * 保存网址数据，有ID则更新，无ID则新增
     * 自动处理拼音、首字母、一级分类等字段
     * 支持XSS过滤和标签自动提取
     *
     * 【自动处理】
     * - website_en    : 自动生成拼音
     * - website_letter: 自动提取首字母
     * - type_id_1     : 自动关联一级分类
     * - website_tag   : 可选自动提取标签
     *
     * @param array $data 网址数据
     * @return array 操作结果
     */
    public function saveData($data)
    {
        // 数据验证
        $validate = \think\Loader::validate('Website');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // 清除相关缓存
        $key = 'website_detail_'.$data['website_id'];
        Cache::rm($key);
        $key = 'website_detail_'.$data['website_en'];
        Cache::rm($key);
        $key = 'website_detail_'.$data['website_id'].'_'.$data['website_en'];
        Cache::rm($key);

        // 自动获取一级分类ID
        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$data['type_id']];
        $data['type_id_1'] = $type_info['type_pid'];

        // 自动生成拼音
        if(empty($data['website_en'])){
            $data['website_en'] = Pinyin::get($data['website_name']);
        }
        // 自动提取首字母
        if(empty($data['website_letter'])){
            $data['website_letter'] = strtoupper(substr($data['website_en'],0,1));
        }
        // 处理截图字段 (换行符转#)
        if(!empty($data['website_pic_screenshot'])){
            $data['website_pic_screenshot'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['website_pic_screenshot']);
        }
        // 更新时间选项
        if($data['uptime']==1){
            $data['website_time'] = time();
        }
        // 自动提取标签选项
        if($data['uptag']==1){
            $data['website_tag'] = mac_get_tag($data['website_name'], $data['website_content']);
        }

        unset($data['uptime']);
        unset($data['uptag']);

        // XSS过滤敏感字段
        $filter_fields = [
            'website_name',
            'website_sub',
            'website_en',
            'website_color',
            'website_jumpurl',
            'website_pic',
            'website_logo',
            'website_area',
            'website_lang',
            'website_tag',
            'website_class',
            'website_remarks',
            'website_tpl',
            'website_blurb',
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        // 更新或新增
        if(!empty($data['website_id'])){
            // 更新已有记录
            $where=[];
            $where['website_id'] = ['eq',$data['website_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增记录，设置添加时间和更新时间
            $data['website_time_add'] = time();
            $data['website_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * ============================================================
     * 删除网址数据
     * ============================================================
     *
     * 【功能说明】
     * 删除网址记录，同时清理关联的图片文件和静态页面
     *
     * 【清理内容】
     * - 本地图片文件 (upload目录下)
     * - 静态详情页面 (如启用静态生成)
     *
     * @param array $where 删除条件
     * @return array 操作结果
     */
    public function delData($where)
    {
        // 先查询要删除的数据
        $list = $this->listData($where,'',1,9999);
        if($list['code'] !==1){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        $path = './';
        foreach($list['list'] as $k=>$v){
            // 删除本地图片文件
            $pic = $path.$v['website_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            // 删除静态详情页面
            if($GLOBALS['config']['view']['website_detail'] ==2 ){
                $lnk = mac_url_website_detail($v);
                $lnk = reset_html_filename($lnk);
                if(file_exists($lnk)){
                    unlink($lnk);
                }
            }
        }
        // 执行删除
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
     * 批量更新指定字段值，并清除相关缓存
     *
     * @param array $where  更新条件
     * @param array $update 要更新的字段和值
     * @return array 操作结果
     */
    public function fieldData($where,$update)
    {
        if(!is_array($update)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        // 执行更新
        $res = $this->allowField(true)->where($where)->update($update);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        // 清除受影响记录的缓存
        $list = $this->field('website_id,website_name,website_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'website_detail_'.$v['website_id'];
            Cache::rm($key);
            $key = 'website_detail_'.$v['website_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    /**
     * ============================================================
     * 获取今日更新数据
     * ============================================================
     *
     * 【功能说明】
     * 获取今日更新的网址ID列表或分类ID列表
     * 用于前台显示今日更新标记
     *
     * @param string $flag 返回类型 (website=网址ID, type=分类ID)
     * @return array 包含ID列表的数组
     */
    public function updateToday($flag='website')
    {
        // 今日零点时间戳
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $where['website_time'] = ['gt',$today];
        // 根据类型返回不同ID列表
        if($flag=='type'){
            $ids = $this->where($where)->column('type_id');
        }
        else{
            $ids = $this->where($where)->column('website_id');
        }
        // 去重处理
        if(empty($ids)){
            $ids = [];
        }else{
            $ids = array_unique($ids);
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'data'=> join(',',$ids) ];
    }

    /**
     * ============================================================
     * 记录回链访问
     * ============================================================
     *
     * 【功能说明】
     * 记录从外部网站的回链访问（友情链接点击）
     * 用于统计友链效果，支持每日访问次数限制
     *
     * 【防刷机制】
     * 根据IP地址限制每日访问次数，防止刷量
     *
     * @param array $param 包含回链URL的参数
     * @return array 操作结果
     */
    public function visit($param)
    {
        // 获取访客IP
        $ip = mac_get_ip_long();
        // 获取每日访问限制次数
        $max_cc = $GLOBALS['config']['website']['refer_visit_num'];
        if(empty($max_cc)){
            $max_cc=1;
        }
        // 今日零点时间戳
        $todayunix = strtotime("today");
        // 检查今日访问次数
        $where = [];
        $where['user_id'] = 0;
        $where['visit_ip'] = $ip;
        $where['visit_time'] = ['gt', $todayunix];
        $cc = model('visit')->where($where)->count();
        // 超出限制则拒绝
        if ($cc>= $max_cc){
            return ['code' => 102, 'msg' =>lang('model/website/refer_max')];
        }

        // 记录访问日志
        $data = [];
        $data['user_id'] = 0;
        $data['visit_ip'] = $ip;
        $data['visit_time'] = time();
        $data['visit_ly'] = htmlspecialchars($param['url']);
        $res = model('visit')->saveData($data);

        if ($res['code'] > 1) {
            return ['code' => 103, 'msg' =>lang('model/website/visit_err')];
        }

        return ['code'=>1,'msg'=>lang('model/website/visit_ok')];
    }

}