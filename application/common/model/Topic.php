<?php
/**
 * 专题模型 (Topic Model)
 * ============================================================
 *
 * 【文件说明】
 * 处理专题数据的增删改查、缓存管理等核心业务逻辑
 * 控制器通过 model('Topic') 调用本模型的方法
 *
 * 【数据表】
 * mac_topic - 专题主表
 *
 * 【数据表字段说明】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 说明                                     │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ topic_id         │ 专题ID (主键自增)                        │
 * │ topic_name       │ 专题名称                                 │
 * │ topic_en         │ 英文标识 (URL别名，自动生成拼音)          │
 * │ topic_sub        │ 副标题                                   │
 * │ topic_status     │ 状态 (0=未审核/1=已审核)                  │
 * │ topic_sort       │ 排序值 (数字小的在前)                    │
 * │ topic_letter     │ 首字母                                   │
 * │ topic_color      │ 标题颜色                                 │
 * │ topic_tpl        │ 模板文件 (默认detail.html)               │
 * │ topic_type       │ 分类标签 (逗号分隔)                      │
 * │ topic_pic        │ 专题图片                                 │
 * │ topic_pic_thumb  │ 缩略图                                   │
 * │ topic_pic_slide  │ 幻灯片大图                               │
 * │ topic_key        │ SEO关键词                                │
 * │ topic_des        │ SEO描述                                  │
 * │ topic_title      │ SEO标题                                  │
 * │ topic_blurb      │ 简介 (自动从内容提取)                    │
 * │ topic_remarks    │ 备注信息                                 │
 * │ topic_level      │ 推荐等级 (1-8普通, 9=幻灯片)             │
 * │ topic_up         │ 顶次数                                   │
 * │ topic_down       │ 踩次数                                   │
 * │ topic_score      │ 评分                                     │
 * │ topic_score_all  │ 评分总计                                 │
 * │ topic_score_num  │ 评分人数                                 │
 * │ topic_hits       │ 总点击量                                 │
 * │ topic_hits_day   │ 日点击量                                 │
 * │ topic_hits_week  │ 周点击量                                 │
 * │ topic_hits_month │ 月点击量                                 │
 * │ topic_time       │ 更新时间                                 │
 * │ topic_time_add   │ 添加时间                                 │
 * │ topic_time_hits  │ 最后点击时间                             │
 * │ topic_time_make  │ 静态页面生成时间                         │
 * │ topic_tag        │ TAG标签 (用于自动关联内容)               │
 * │ topic_rel_vod    │ 关联视频ID (逗号分隔)                    │
 * │ topic_rel_art    │ 关联文章ID (逗号分隔)                    │
 * │ topic_content    │ 详细内容 (HTML富文本)                    │
 * │ topic_extend     │ 扩展属性 (JSON格式)                      │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * 【专题内容关联机制】
 * 专题可通过两种方式关联内容：
 * 1. 手动关联：通过 topic_rel_vod 和 topic_rel_art 字段指定具体ID
 * 2. 标签关联：通过 topic_tag 字段匹配视频/文章的tag标签
 *
 * 【缓存键说明】
 * - {cache_flag}_topic_detail_{id} : 专题详情缓存
 * - {cache_flag}_topic_listcache_{md5} : 列表查询缓存
 *
 * 【方法列表】
 * ┌──────────────────┬──────────────────────────────────────────┐
 * │ 方法名            │ 功能说明                                  │
 * ├──────────────────┼──────────────────────────────────────────┤
 * │ countData()      │ 统计专题数量                              │
 * │ listData()       │ 获取专题列表 (支持分页)                    │
 * │ listCacheData()  │ 获取专题列表 (带缓存，供模板标签使用)      │
 * │ infoData()       │ 获取单个专题详情 (含关联内容)              │
 * │ saveData()       │ 保存专题数据 (新增/更新)                   │
 * │ delData()        │ 删除专题 (含图片和静态文件)                │
 * │ fieldData()      │ 更新单个字段                              │
 * └──────────────────┴──────────────────────────────────────────┘
 *
 * 【相关文件】
 * - application/admin/controller/Topic.php   : 后台专题控制器
 * - application/admin/validate/Topic.php     : 专题数据验证器
 * - application/admin/view_new/topic/        : 专题管理视图模板
 *
 * ============================================================
 */

namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Topic extends Base {
    // ============================================================
    // 【模型配置】
    // ============================================================

    /**
     * 设置数据表名 (不含前缀)
     * 实际表名: mac_topic (前缀在 database.php 中配置)
     */
    protected $name = 'topic';

    /**
     * 定义时间戳字段名
     * 专题表不使用自动时间戳字段名
     */
    protected $createTime = '';
    protected $updateTime = '';
    // 开启自动写入时间戳 (但使用自定义字段名)
    protected $autoWriteTimestamp = true;

    /**
     * 自动完成配置
     * - auto: 新增和更新时都会自动完成的字段
     * - insert: 仅新增时自动完成的字段
     * - update: 仅更新时自动完成的字段
     */
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * ============================================================
     * 状态文本获取器
     * ============================================================
     *
     * 【功能说明】
     * 将状态数字转换为可读文本
     * 在模板中可通过 $vo.topic_status_text 获取
     *
     * @param mixed $val 原始值 (未使用)
     * @param array $data 当前记录数据
     * @return string 状态文本 (禁用/启用)
     */
    public function getTopicStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['topic_status']];
    }

    /**
     * ============================================================
     * 统计专题数量
     * ============================================================
     *
     * 【功能说明】
     * 根据条件统计专题记录数量
     *
     * @param array $where 查询条件数组
     * @return int 符合条件的记录数量
     *
     * 【使用示例】
     * $count = model('Topic')->countData(['topic_status'=>1]); // 统计已审核专题数量
     */
    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    /**
     * ============================================================
     * 获取专题列表
     * ============================================================
     *
     * 【功能说明】
     * 获取专题数据列表，支持分页、排序、字段筛选
     *
     * @param array|string $where 查询条件 (数组或JSON字符串)
     * @param string $order 排序规则 (如 'topic_time desc')
     * @param int $page 当前页码，默认1
     * @param int $limit 每页数量，默认20
     * @param int $start 起始偏移量，默认0
     * @param string $field 查询字段，默认 '*' 全部
     * @param int $totalshow 是否统计总数 (1=统计，0=不统计)
     * @return array 包含 code/msg/page/pagecount/limit/total/list 的结果数组
     *
     * 【返回数据结构】
     * [
     *   'code' => 1,
     *   'msg' => '数据列表',
     *   'page' => 1,           // 当前页码
     *   'pagecount' => 10,     // 总页数
     *   'limit' => 20,         // 每页数量
     *   'total' => 200,        // 总记录数
     *   'list' => [            // 以 topic_id 为键
     *     1 => ['topic_id'=>1, 'topic_name'=>'年度盘点', ...],
     *     2 => ['topic_id'=>2, 'topic_name'=>'经典重温', ...],
     *   ]
     * ]
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$totalshow=1)
    {
        // --------------------------------------------------------
        // 【参数处理】
        // --------------------------------------------------------
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;

        // 如果 where 是 JSON 字符串，转换为数组
        if(!is_array($where)){
            $where = json_decode($where,true);
        }

        // 构建 LIMIT 子句: "起始位置,数量"
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        // --------------------------------------------------------
        // 【统计总数】
        // --------------------------------------------------------
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }

        // --------------------------------------------------------
        // 【查询专题数据】
        // --------------------------------------------------------
        $tmp = Db::name('Topic')->where($where)->order($order)->limit($limit_str)->select();

        // --------------------------------------------------------
        // 【数据处理】构建以 topic_id 为键的列表
        // --------------------------------------------------------
        $list = [];
        foreach($tmp as $k=>$v){
            $list[$v['topic_id']] = $v;
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }


    /**
     * ============================================================
     * 获取专题列表 (带缓存，供模板标签使用)
     * ============================================================
     *
     * 【功能说明】
     * 为前台模板标签 {maccms:topic} 提供数据
     * 支持缓存、多种筛选条件、分页功能
     *
     * @param array|string $lp 标签参数数组或JSON字符串
     *
     * 【参数说明】
     * ┌──────────┬──────────────────────────────────────────────┐
     * │ 参数名    │ 说明                                          │
     * ├──────────┼──────────────────────────────────────────────┤
     * │ order    │ 排序方向 (asc/desc)                           │
     * │ by       │ 排序字段 (id/time/time_add/score/hits/level等)│
     * │ ids      │ 专题ID筛选 (逗号分隔或'all')                   │
     * │ paging   │ 是否分页 (yes/no)                             │
     * │ pageurl  │ 分页URL模板                                   │
     * │ level    │ 等级筛选 (1-9 或 'all')                       │
     * │ letter   │ 首字母筛选                                    │
     * │ tag      │ TAG标签筛选                                   │
     * │ class    │ 分类筛选                                      │
     * │ start    │ 起始位置                                      │
     * │ num      │ 获取数量                                      │
     * │ half     │ 半分数量 (用于两列布局)                       │
     * │ timeadd  │ 添加时间筛选 (如 '-7 day')                    │
     * │ timehits │ 点击时间筛选                                  │
     * │ time     │ 更新时间筛选                                  │
     * │ hitsmonth│ 月点击量筛选                                  │
     * │ hitsweek │ 周点击量筛选                                  │
     * │ hitsday  │ 日点击量筛选                                  │
     * │ hits     │ 总点击量筛选                                  │
     * │ not      │ 排除的专题ID                                  │
     * │ cachetime│ 缓存时间 (秒)                                 │
     * └──────────┴──────────────────────────────────────────────┘
     *
     * @return array 专题列表结果
     *
     * 【模板使用示例】
     * {maccms:topic num="10" level="9" order="desc" by="time"}
     *   <a href="{:mac_url_topic_detail($vo)}">{$vo.topic_name}</a>
     * {/maccms:topic}
     */
    public function listCacheData($lp)
    {
        // --------------------------------------------------------
        // 【参数解析】
        // --------------------------------------------------------
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        // 提取各个参数
        $order = $lp['order'];           // 排序方向
        $by = $lp['by'];                 // 排序字段
        $ids = $lp['ids'];               // 专题ID筛选
        $paging = $lp['paging'];         // 是否分页
        $pageurl = $lp['pageurl'];       // 分页URL
        $level = $lp['level'];           // 等级筛选
        $letter = $lp['letter'];         // 首字母筛选
        $tag = $lp['tag'];               // TAG标签筛选
        $class = $lp['class'];           // 分类筛选
        $start = intval(abs($lp['start'])); // 起始位置
        $num = intval(abs($lp['num']));  // 获取数量
        $half = intval(abs($lp['half'])); // 半分数量
        $timeadd = $lp['timeadd'];       // 添加时间筛选
        $timehits = $lp['timehits'];     // 点击时间筛选
        $time = $lp['time'];             // 更新时间筛选
        $hitsmonth = $lp['hitsmonth'];   // 月点击量筛选
        $hitsweek = $lp['hitsweek'];     // 周点击量筛选
        $hitsday = $lp['hitsday'];       // 日点击量筛选
        $hits = $lp['hits'];             // 总点击量筛选
        $not = $lp['not'];               // 排除ID
        $cachetime = $lp['cachetime'];   // 缓存时间

        $page = 1;
        $where = [];
        $totalshow = 0;

        // --------------------------------------------------------
        // 【参数验证和默认值】
        // --------------------------------------------------------
        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--; // start从1开始计数，转换为从0开始的偏移量
        }

        // 分页模式只允许 yes/no
        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }

        // --------------------------------------------------------
        // 【分页模式参数处理】
        // --------------------------------------------------------
        $param = mac_param_url();
        if($paging=='yes') {
            // 检查参数长度安全
            $param = mac_search_len_check($param);
            $totalshow = 1; // 开启分页需要统计总数

            // 从URL参数中获取筛选条件
            if (!empty($param['id'])) {
                $ids = intval($param['id']);
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
            if(!empty($param['wd'])) {
                $wd = $param['wd'];
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

            // 清理空参数
            foreach($param as $k=>$v){
                if(empty($v)){
                    unset($param[$k]);
                }
            }

            // 默认分页URL
            if(empty($pageurl)){
                $pageurl = 'topic/index';
            }
            // 设置分页占位符
            $param['page'] = 'PAGELINK';
            $pageurl = mac_url($pageurl,$param);

        }

        // --------------------------------------------------------
        // 【构建查询条件】
        // --------------------------------------------------------

        // 只查询已审核的专题
        $where['topic_status'] = ['eq',1];

        // 等级筛选
        if(!empty($level)) {
            $where['topic_level'] = ['in',explode(',',$level)];
        }

        // ID筛选
        if(!empty($ids)) {
            if($ids!='all'){
                $where['topic_id'] = ['in',explode(',',$ids)];
            }
        }

        // 排除ID
        if(!empty($not)){
            $where['topic_id'] = ['not in',explode(',',$not)];
        }

        // 首字母筛选 (0-9 特殊处理)
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['topic_letter'] = ['in',explode(',',$letter)];
        }

        // 添加时间筛选 (如 '-7 day' 表示7天内添加)
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['topic_time_add'] =['gt',$s];
        }

        // 点击时间筛选
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['topic_time_hits'] =['gt',$s];
        }

        // 更新时间筛选
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['topic_time'] =['gt',$s];
        }

        // 月点击量筛选 (支持 "gt 100" 或单个数字格式)
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['topic_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }

        // 周点击量筛选
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['topic_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }

        // 日点击量筛选
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['topic_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }

        // 总点击量筛选
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['topic_hits'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits'] = [$tmp[0],$tmp[1]];
            }
        }

        // 关键词搜索 (名称/英文标识/副标题)
        if(!empty($wd)) {
            $where['topic_name|topic_en|topic_sub'] = ['like', '%' . $wd . '%'];
        }

        // TAG标签筛选 (模糊匹配)
        if(!empty($tag)) {
            $where['topic_tag'] = ['like', mac_like_arr($tag),'OR'];
        }

        // 分类筛选 (模糊匹配)
        if(!empty($class)) {
            $where['topic_type'] = ['like', mac_like_arr($class),'OR'];
        }

        // --------------------------------------------------------
        // 【随机排序处理】
        // --------------------------------------------------------
        if($by=='rnd'){
            // 计算总页数，随机取一页
            $data_count = $this->countData($where);
            $page_total = floor($data_count / $lp['num']) + 1;
            if($data_count < $lp['num']){
                $lp['num'] = $data_count;
            }
            $randi = @mt_rand(1, $page_total);
            $page = $randi;
            $by = 'hits_week'; // 随机模式下使用周点击排序
            $order = 'desc';
        }

        // --------------------------------------------------------
        // 【排序参数验证】
        // --------------------------------------------------------
        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        // 构建排序字符串
        $order= 'topic_'.$by .' ' . $order;

        // --------------------------------------------------------
        // 【缓存处理】
        // --------------------------------------------------------
        $where_cache = $where;
        if(!empty($randi)){
            // 随机排序时，移除ID条件以保证缓存一致性
            unset($where_cache['topic_id']);
            $where_cache['order'] = 'rnd';
        }

        // 生成缓存键名 (包含所有查询参数的MD5)
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('topic_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);

        // 缓存时间默认值
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }

        // 如果未开启核心缓存或缓存为空，重新查询
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$page,$num,$start,'*',$totalshow);
            // 如果开启了核心缓存，写入缓存
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cachetime);
            }
        }

        // 添加分页URL和半分数量到结果
        $res['pageurl'] = $pageurl;
        $res['half'] = $half;
        return $res;
    }

    /**
     * ============================================================
     * 获取单个专题详情
     * ============================================================
     *
     * 【功能说明】
     * 根据条件获取单个专题的完整信息，包括：
     * - 专题基本信息
     * - 关联的视频列表 (通过 topic_rel_vod)
     * - 关联的文章列表 (通过 topic_rel_art)
     * - 通过 TAG 标签匹配的视频和文章
     *
     * @param array $where 查询条件 (如 ['topic_id'=>['eq',1]])
     * @param string $field 查询字段，默认 '*' 全部
     * @param int $cache 是否使用缓存 (1=使用，0=不使用)
     * @return array 包含 code/msg/info 的结果数组
     *
     * 【返回数据结构】
     * [
     *   'code' => 1,
     *   'msg' => '获取成功',
     *   'info' => [
     *     'topic_id' => 1,
     *     'topic_name' => '年度盘点',
     *     'topic_extend' => ['type'=>'', 'area'=>'', ...],
     *     'vod_list' => [...],  // 关联的视频列表
     *     'art_list' => [...],  // 关联的文章列表
     *     ...
     *   ]
     * ]
     */
    public function infoData($where,$field='*',$cache=0)
    {
        // 参数验证
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // --------------------------------------------------------
        // 【缓存处理】
        // --------------------------------------------------------
        $data_cache = false;
        // 构建缓存键名
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'topic_detail_'.$where['topic_id'][1].'_'.$where['topic_en'][1];

        // 判断是否可以使用缓存 (精确查询时)
        if($where['topic_id'][0]=='eq' || $where['topic_en'][0]=='eq'){
            $data_cache = true;
        }

        // 尝试从缓存获取
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }

        // --------------------------------------------------------
        // 【查询数据】
        // --------------------------------------------------------
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['topic_id']) ) {
            $info = $this->field($field)->where($where)->find();

            // 检查是否查询到数据
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }

            // 转换为数组
            $info = $info->toArray();

            // --------------------------------------------------------
            // 【处理扩展属性】
            // --------------------------------------------------------
            if (!empty($info['topic_extend'])) {
                $info['topic_extend'] = json_decode($info['topic_extend'], true);
            } else {
                // 设置默认扩展结构
                $info['topic_extend'] = json_decode('{"type":"","area":"","lang":"","year":"","star":"","director":"","state":"","version":""}', true);
            }

            // 初始化关联列表
            $info['vod_list'] = [];
            $info['art_list'] = [];

            // --------------------------------------------------------
            // 【获取手动关联的视频】
            // --------------------------------------------------------
            if (!empty($info['topic_rel_vod'])) {
                $where = [];
                $where['vod_id'] = ['in', $info['topic_rel_vod']];
                $where['vod_status'] = ['eq', 1]; // 只获取已审核的视频
                $order = 'vod_time desc';
                $field = '*';
                $res = model('Vod')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    $info['vod_list'] = $res['list'];
                }
            }

            // --------------------------------------------------------
            // 【获取手动关联的文章】
            // --------------------------------------------------------
            if (!empty($info['topic_rel_art'])) {
                $where = [];
                $where['art_id'] = ['in', $info['topic_rel_art']];
                $where['art_status'] = ['eq', 1]; // 只获取已审核的文章
                $order = 'art_time desc';
                $field = '*';
                $res = model('Art')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    $info['art_list'] = $res['list'];
                }
            }

            // --------------------------------------------------------
            // 【通过TAG标签自动关联内容】
            // --------------------------------------------------------
            if (!empty($info['topic_tag'])) {
                // 通过TAG匹配视频
                $where=[];
                $where['vod_tag'] = ['like', mac_like_arr($info['topic_tag']),'OR'];
                $where['vod_status'] = ['eq', 1];
                $order = 'vod_time desc';
                $field = '*';
                $res = model('Vod')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    // 合并到视频列表 (避免重复)
                    $info['vod_list'] = array_merge($info['vod_list'],$res['list']);
                }

                // 通过TAG匹配文章
                $where=[];
                $where['art_tag'] = ['like', mac_like_arr($info['topic_tag']),'OR'];
                $where['art_status'] = ['eq', 1];
                $order = 'art_time desc';
                $field = '*';
                $res = model('Art')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    // 合并到文章列表 (避免重复)
                    $info['art_list'] = array_merge($info['art_list'],$res['list']);
                }
            }

            // --------------------------------------------------------
            // 【写入缓存】
            // --------------------------------------------------------
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * ============================================================
     * 保存专题数据 (新增/更新)
     * ============================================================
     *
     * 【功能说明】
     * 保存专题数据，自动判断是新增还是更新：
     * - 有 topic_id → 更新已有记录
     * - 无 topic_id → 新增记录
     *
     * @param array $data 专题数据
     * @return array 包含 code/msg 的结果数组
     *
     * 【执行流程】
     * 1. 数据验证 (使用 Topic 验证器)
     * 2. 清除相关缓存
     * 3. 自动生成拼音英文标识
     * 4. 处理内容中的图片URL
     * 5. 自动生成简介
     * 6. 处理更新时间
     * 7. XSS过滤
     * 8. 执行新增或更新
     */
    public function saveData($data)
    {
        // --------------------------------------------------------
        // 【数据验证】
        // --------------------------------------------------------
        $validate = \think\Loader::validate('Topic');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // --------------------------------------------------------
        // 【清除缓存】
        // --------------------------------------------------------
        // 更新时需要清除相关的详情缓存
        $key = 'topic_detail_'.$data['topic_id'];
        Cache::rm($key);
        $key = 'topic_detail_'.$data['topic_en'];
        Cache::rm($key);
        $key = 'topic_detail_'.$data['topic_id'].'_'.$data['topic_en'];
        Cache::rm($key);

        // --------------------------------------------------------
        // 【扩展属性处理】
        // --------------------------------------------------------
        if(empty($data['topic_extend'])){
            $data['topic_extend'] = '';
        }

        // --------------------------------------------------------
        // 【自动生成英文标识】
        // --------------------------------------------------------
        if(empty($data['topic_en'])){
            $data['topic_en'] = Pinyin::get($data['topic_name']);
        }

        // --------------------------------------------------------
        // 【处理内容中的图片URL】
        // --------------------------------------------------------
        // 将 http: 或 https: 替换为 mac: 以便于协议自适应
        if(!empty($data['topic_content'])) {
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['topic_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['topic_content'] = str_replace($v1, $v2, $data['topic_content']);
                }
            }
            unset($match_src1);
        }

        // --------------------------------------------------------
        // 【自动生成简介】
        // --------------------------------------------------------
        // 如果简介为空，从内容中截取前100个字符
        if(empty($data['topic_blurb'])){
            $data['topic_blurb'] = mac_substring( strip_tags($data['topic_content']) ,100);
        }

        // --------------------------------------------------------
        // 【处理更新时间】
        // --------------------------------------------------------
        // uptime=1 表示更新时间为当前时间
        if($data['uptime']==1){
            $data['topic_time'] = time();
        }
        unset($data['uptime']);

        // --------------------------------------------------------
        // 【XSS过滤】
        // --------------------------------------------------------
        $filter_fields = [
            'topic_name',       // 专题名称
            'topic_en',         // 英文标识
            'topic_sub',        // 副标题
            'topic_color',      // 标题颜色
            'topic_tpl',        // 模板文件
            'topic_type',       // 分类
            'topic_pic',        // 专题图片
            'topic_pic_thumb',  // 缩略图
            'topic_pic_slide',  // 幻灯片图
            'topic_key',        // SEO关键词
            'topic_des',        // SEO描述
            'topic_title',      // SEO标题
            'topic_blurb',      // 简介
            'topic_remarks',    // 备注
            'topic_tag',        // TAG标签
            'topic_rel_vod',    // 关联视频
            'topic_rel_art',    // 关联文章
            'topic_content',    // 详细内容
            'topic_extend',     // 扩展属性
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        // --------------------------------------------------------
        // 【执行保存】
        // --------------------------------------------------------
        if(!empty($data['topic_id'])){
            // 【更新】有 topic_id，执行更新操作
            $where=[];
            $where['topic_id'] = ['eq',$data['topic_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 【新增】无 topic_id，执行插入操作
            $data['topic_time_add'] = time(); // 设置添加时间
            $data['topic_time'] = time();     // 设置更新时间
            $res = $this->allowField(true)->insert($data);
        }

        // 检查执行结果
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * ============================================================
     * 删除专题
     * ============================================================
     *
     * 【功能说明】
     * 删除专题记录，同时删除相关的图片文件和静态页面
     *
     * @param array $where 查询条件 (如 ['topic_id'=>['in','1,2,3']])
     * @return array 包含 code/msg 的结果数组
     *
     * 【执行流程】
     * 1. 查询要删除的专题列表
     * 2. 删除专题图片 (topic_pic)
     * 3. 删除专题缩略图 (topic_pic_thumb)
     * 4. 删除专题幻灯片图 (topic_pic_slide)
     * 5. 删除静态页面文件 (如果开启了静态化)
     * 6. 删除数据库记录
     */
    public function delData($where)
    {
        // 获取要删除的专题列表
        $list = $this->listData($where,'',1,9999);
        if($list['code'] !==1){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }

        $path = './';
        foreach($list['list'] as $k=>$v){
            // --------------------------------------------------------
            // 【删除专题图片】
            // --------------------------------------------------------
            $pic = $path.$v['topic_pic'];
            // 只删除 upload 目录下的本地图片
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }

            // --------------------------------------------------------
            // 【删除缩略图】
            // --------------------------------------------------------
            $pic = $path.$v['topic_pic_thumb'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }

            // --------------------------------------------------------
            // 【删除幻灯片图】
            // --------------------------------------------------------
            $pic = $path.$v['topic_pic_slide'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }

            // --------------------------------------------------------
            // 【删除静态页面】
            // --------------------------------------------------------
            // 如果开启了专题详情静态化 (配置值=2)
            if($GLOBALS['config']['view']['topic_detail'] ==2 ){
                $lnk = mac_url_topic_detail($v);
                $lnk = reset_html_filename($lnk);
                if(file_exists($lnk)){
                    unlink($lnk);
                }
            }
        }

        // --------------------------------------------------------
        // 【删除数据库记录】
        // --------------------------------------------------------
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * ============================================================
     * 更新单个字段
     * ============================================================
     *
     * 【功能说明】
     * 只更新专题的某一个字段，并清除相关缓存
     *
     * @param array $where 查询条件
     * @param string $col 字段名 (如 'topic_status', 'topic_level')
     * @param mixed $val 字段值
     * @return array 包含 code/msg 的结果数组
     *
     * 【使用场景】
     * - 列表页批量设置状态
     * - 列表页批量设置等级
     * - 单个专题等级点击修改
     */
    public function fieldData($where,$col,$val)
    {
        // 参数验证
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // 构建更新数据
        $data = [];
        $data[$col] = $val;

        // 执行更新
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1002,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        // --------------------------------------------------------
        // 【清除缓存】
        // --------------------------------------------------------
        // 获取受影响的专题列表，逐个清除缓存
        $list = $this->field('topic_id,topic_name,topic_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'topic_detail_'.$v['topic_id'];
            Cache::rm($key);
            $key = 'topic_detail_'.$v['topic_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }



}