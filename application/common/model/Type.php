<?php
/**
 * 分类模型 (Type Model)
 * ============================================================
 *
 * 【文件说明】
 * 处理分类数据的增删改查、缓存管理等核心业务逻辑
 * 控制器通过 model('Type') 调用本模型的方法
 *
 * 【数据表】
 * mac_type - 分类主表
 *
 * 【数据表字段说明】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 说明                                     │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ type_id          │ 分类ID (主键自增)                        │
 * │ type_name        │ 分类名称                                 │
 * │ type_en          │ 英文标识 (URL别名，自动生成拼音)          │
 * │ type_sort        │ 排序值 (数字小的在前)                    │
 * │ type_mid         │ 所属模块 (1=视频/2=文章/8=演员/11=网址/12=漫画) │
 * │ type_pid         │ 父分类ID (0=一级分类)                    │
 * │ type_status      │ 状态 (0=禁用/1=启用)                     │
 * │ type_tpl         │ 分类首页模板                             │
 * │ type_tpl_list    │ 筛选列表模板                             │
 * │ type_tpl_detail  │ 详情页模板                               │
 * │ type_tpl_play    │ 播放页模板 (仅视频分类)                  │
 * │ type_tpl_down    │ 下载页模板 (仅视频分类)                  │
 * │ type_key         │ SEO关键词                                │
 * │ type_des         │ SEO描述                                  │
 * │ type_title       │ SEO标题                                  │
 * │ type_union       │ 联盟分类ID                               │
 * │ type_extend      │ 扩展属性 (JSON格式存储筛选选项)          │
 * │ type_logo        │ 分类Logo图片                             │
 * │ type_pic         │ 分类大图                                 │
 * │ type_jumpurl     │ 跳转URL                                  │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * 【扩展属性 type_extend JSON结构】
 * {
 *   "class": "动作,喜剧,爱情",     // 剧情分类
 *   "area": "大陆,香港,台湾",      // 地区
 *   "lang": "国语,粤语,英语",      // 语言
 *   "year": "2024,2023,2022",     // 年份
 *   "star": "",                   // 明星 (已废弃)
 *   "director": "",               // 导演 (已废弃)
 *   "state": "正片,预告片",        // 状态
 *   "version": "高清版,蓝光"       // 版本
 * }
 *
 * 【缓存键说明】
 * - {cache_flag}_type_list : 分类平铺列表 (key为type_id)
 * - {cache_flag}_type_tree : 分类树形结构
 *
 * 【方法列表】
 * ┌─────────────────┬──────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                  │
 * ├─────────────────┼──────────────────────────────────────────┤
 * │ countData()     │ 统计分类数量                              │
 * │ listData()      │ 获取分类列表 (支持树形/平铺格式)           │
 * │ listCacheData() │ 获取分类列表 (带缓存，供模板标签使用)      │
 * │ infoData()      │ 获取单个分类详情                          │
 * │ saveData()      │ 保存分类数据 (新增/更新)                   │
 * │ delData()       │ 删除分类 (需检查是否有关联数据)            │
 * │ fieldData()     │ 更新单个字段                              │
 * │ moveData()      │ 转移分类下的数据到另一分类                 │
 * │ setCache()      │ 重建分类缓存                              │
 * │ getCache()      │ 获取分类缓存                              │
 * │ getCacheInfo()  │ 从缓存获取单个分类信息                    │
 * └─────────────────┴──────────────────────────────────────────┘
 *
 * 【相关文件】
 * - application/admin/controller/Type.php : 后台分类控制器
 * - application/admin/validate/Type.php   : 分类数据验证器
 * - application/admin/view_new/type/      : 分类管理视图模板
 *
 * ============================================================
 */

namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Type extends Base {
    // ============================================================
    // 【模型配置】
    // ============================================================

    /**
     * 设置数据表名 (不含前缀)
     * 实际表名: mac_type (前缀在 database.php 中配置)
     */
    protected $name = 'type';

    /**
     * 定义时间戳字段名
     * 分类表不使用自动时间戳，所以设置为空
     */
    protected $createTime = '';
    protected $updateTime = '';

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
     * 自定义初始化
     * ============================================================
     *
     * 【功能说明】
     * 模型初始化方法，在模型实例化时自动调用
     * 可以在这里进行一些初始化操作
     *
     * 【注意事项】
     * 必须先调用父类的 initialize() 方法
     */
    protected function initialize()
    {
        // 调用父类的初始化方法 (必须)
        parent::initialize();
        // TODO: 在这里添加自定义的初始化逻辑
    }

    /**
     * ============================================================
     * 统计分类数量
     * ============================================================
     *
     * 【功能说明】
     * 根据条件统计分类记录数量
     *
     * @param array $where 查询条件数组
     * @return int 符合条件的记录数量
     *
     * 【使用示例】
     * $count = model('Type')->countData(['type_mid'=>1]); // 统计视频分类数量
     */
    public function countData($where)
    {
        // 执行 COUNT 查询并返回结果
        $total = $this->where($where)->count();
        return $total;
    }

    /**
     * ============================================================
     * 获取分类列表
     * ============================================================
     *
     * 【功能说明】
     * 获取分类数据列表，支持多种格式输出：
     * - def (默认): 平铺列表，以 type_id 为键
     * - tree: 树形结构，带 child 子节点
     *
     * @param array|string $where 查询条件 (数组或JSON字符串)
     * @param string $order 排序规则 (如 'type_sort asc')
     * @param string $format 输出格式 ('def'=平铺/'tree'=树形)
     * @param int $mid 模块ID过滤 (0=不过滤，1=视频，2=文章等)
     * @param int $limit 获取数量限制，默认999
     * @param int $start 起始偏移量，默认0
     * @param int $totalshow 是否统计总数 (1=统计，0=不统计)
     * @return array 包含 code/msg/total/list 的结果数组
     *
     * 【返回数据结构 - 平铺格式】
     * [
     *   'code' => 1,
     *   'msg' => '数据列表',
     *   'total' => 20,
     *   'list' => [
     *     1 => ['type_id'=>1, 'type_name'=>'电影', 'type_pid'=>0, 'childids'=>'2,3,4', ...],
     *     2 => ['type_id'=>2, 'type_name'=>'动作片', 'type_pid'=>1, 'type_1'=>[父分类信息], ...],
     *     ...
     *   ]
     * ]
     *
     * 【返回数据结构 - 树形格式】
     * [
     *   'list' => [
     *     ['type_id'=>1, 'type_name'=>'电影', 'child'=>[
     *       ['type_id'=>2, 'type_name'=>'动作片', ...],
     *       ['type_id'=>3, 'type_name'=>'喜剧片', ...],
     *     ]],
     *     ...
     *   ]
     * ]
     */
    public function listData($where,$order,$format='def',$mid=0,$limit=999,$start=0,$totalshow=1)
    {
        // --------------------------------------------------------
        // 【参数处理】
        // --------------------------------------------------------
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;

        // 如果 where 是 JSON 字符串，转换为数组
        if(!is_array($where)){
            $where = json_decode($where,true);
        }

        // 构建 LIMIT 子句: "起始位置,数量"
        $limit_str = ($limit * (1-1) + $start) .",".$limit;

        // --------------------------------------------------------
        // 【统计总数】
        // --------------------------------------------------------
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        else{
            // 不统计总数时，total 保持未定义
        }

        // --------------------------------------------------------
        // 【查询分类数据】
        // --------------------------------------------------------
        $tmp = Db::name('Type')->where($where)->order($order)->limit($limit_str)->select();

        // --------------------------------------------------------
        // 【数据处理】构建以 type_id 为键的列表
        // --------------------------------------------------------
        $list = [];      // 最终列表 (以type_id为键)
        $childs = [];    // 子分类ID映射 (以type_pid为键)

        foreach($tmp as $k=>$v){
            // 解析扩展属性 JSON
            $v['type_extend'] = json_decode($v['type_extend'],true);
            // 以 type_id 为键存入列表
            $list[$v['type_id']] = $v;
            // 记录父子关系: childs[父ID][] = 子ID
            $childs[$v['type_pid']][] = $v['type_id'];
        }

        // --------------------------------------------------------
        // 【补充关联信息】
        // --------------------------------------------------------
        $rc=false; // 标记是否已读取缓存
        foreach($list as $k=>$v){
            if($v['type_pid']==0){
                // 【一级分类】添加 childids (子分类ID列表)
                if(!empty($where)){
                    // 有查询条件时，从缓存获取完整的 childids
                    if(!$rc){
                        $type_list = model('Type')->getCache('type_list');
                        $rc=true;
                    }
                    $list[$k]['childids'] = $type_list[$v['type_id']]['childids'];
                }
                else {
                    // 无查询条件时，用当前查询结果构建 childids
                    $list[$k]['childids'] = join(',', (array)$childs[$v['type_id']]);
                }
            }
            else {
                // 【二级分类】添加 type_1 (父分类完整信息)
                $list[$k]['type_1'] = $list[$v['type_pid']];
            }
        }

        // --------------------------------------------------------
        // 【模块过滤】
        // --------------------------------------------------------
        // 如果指定了 mid，只保留该模块的分类
        if($mid>0){
            foreach($list as $k=>$v){
                if($v['type_mid'] !=$mid) {
                    unset($list[$k]);
                }
            }
        }

        // --------------------------------------------------------
        // 【格式转换】
        // --------------------------------------------------------
        // 如果需要树形格式，调用 mac_list_to_tree 转换
        if($format=='tree'){
            $list = mac_list_to_tree($list,'type_id','type_pid');
        }

        return ['code'=>1,'msg'=>lang('data_list'),'total'=>$total,'list'=>$list];
    }

    /**
     * ============================================================
     * 获取分类列表 (带缓存，供模板标签使用)
     * ============================================================
     *
     * 【功能说明】
     * 为前台模板标签 {maccms:type} 提供数据
     * 支持缓存、权限过滤、多种筛选条件
     *
     * @param array|string $lp 标签参数数组或JSON字符串
     *
     * 【参数说明】
     * ┌──────────┬──────────────────────────────────────────────┐
     * │ 参数名    │ 说明                                          │
     * ├──────────┼──────────────────────────────────────────────┤
     * │ order    │ 排序方向 (asc/desc)                           │
     * │ by       │ 排序字段 (id/sort)                            │
     * │ mid      │ 模块ID (1=视频/2=文章/8=演员/11=网址)         │
     * │ ids      │ 分类ID筛选 (parent/child/current/具体ID)      │
     * │ parent   │ 父分类ID (current=当前页面分类)               │
     * │ format   │ 输出格式 (def/tree)                           │
     * │ flag     │ 模块标识 (vod/art，与mid二选一)               │
     * │ start    │ 起始位置                                      │
     * │ num      │ 获取数量                                      │
     * │ cachetime│ 缓存时间 (秒)                                 │
     * │ not      │ 排除的分类ID                                  │
     * └──────────┴──────────────────────────────────────────────┘
     *
     * 【ids 特殊值说明】
     * - parent: 只获取一级分类 (type_pid=0)
     * - child: 只获取二级分类 (type_pid>0)
     * - current: 获取当前页面分类的同级分类
     *
     * @return array 分类列表结果
     *
     * 【模板使用示例】
     * {maccms:type flag="vod" ids="parent" order="asc" by="sort"}
     *   <a href="{:mac_url_type($vo)}">{$vo.type_name}</a>
     * {/maccms:type}
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
        $mid = $lp['mid'];               // 模块ID
        $ids = $lp['ids'];               // 分类ID筛选
        $parent = $lp['parent'];         // 父分类ID
        $format = $lp['format'];         // 输出格式
        $flag = $lp['flag'];             // 模块标识
        $start = intval(abs($lp['start'])); // 起始位置
        $num = intval(abs($lp['num']));  // 获取数量
        $cachetime = $lp['cachetime'];   // 缓存时间
        $not = $lp['not'];               // 排除ID
        $page=1;
        $where = [];


        // --------------------------------------------------------
        // 【参数验证和默认值】
        // --------------------------------------------------------
        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--; // start从1开始计数，转换为从0开始的偏移量
        }
        // 排序方向只允许 asc/desc
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        // 排序字段只允许 id/sort
        if (!in_array($by, ['id', 'sort'])) {
            $by = 'id';
        }
        // 输出格式只允许 def/tree
        if (!in_array($format, ['def', 'tree'])) {
            $format = 'def';
        }

        // --------------------------------------------------------
        // 【构建查询条件 - 模块过滤】
        // --------------------------------------------------------
        // 通过 mid 参数过滤
        if (in_array($mid, ['1', '2','8','11'])) {
            $where['type_mid'] = ['eq',$mid];
        }
        // 通过 flag 参数过滤 (与 mid 二选一)
        if(!empty($flag)){
            if($flag=='vod'){
                $where['type_mid'] = ['eq',1]; // 视频
            }
            elseif($flag=='art'){
                $where['type_mid'] = ['eq',2]; // 文章
            }
        }

        // 获取当前URL参数 (用于 current 类型的筛选)
        $param = mac_param_url();

        // --------------------------------------------------------
        // 【构建查询条件 - IDS筛选】
        // --------------------------------------------------------
        if (!empty($ids)) {
            if($ids=='parent'){
                // 只获取一级分类
                $where['type_pid'] = ['eq',0];
            }
            elseif($ids=='child'){
                // 只获取二级分类
                $where['type_pid'] = ['gt',0];
            }
            elseif($ids=='current'){
                // 获取当前分类的同级分类 (兄弟分类)
                $type_info = $this->getCacheInfo($param['id']);
                $doid = $param['id'];
                $childs = $type_info['childids'];
                if($type_info['type_pid']>0){
                    // 如果当前是二级分类，获取其父分类的所有子分类
                    $doid = $type_info['type_pid'];
                    $type_info1 = $this->getCacheInfo($doid);
                    $childs = $type_info1['childids'];
                }

                $where['type_id'] = ['in',$childs];
            }
            else{
                // 指定具体的分类ID列表
                $where['type_id'] = ['in',$ids];
            }
        }

        // --------------------------------------------------------
        // 【构建查询条件 - 父分类过滤】
        // --------------------------------------------------------
        if(!empty($parent)){
            if($parent=='current'){
                // 获取当前页面分类作为父分类
                $type_info = $this->getCacheInfo($param['id']);
                $parent = intval($type_info['type_id']);
                if($type_info['type_pid'] !=0){
                    // 注释掉的代码: 如果是二级分类，可以取其父分类
                    //$parent = $type_info['type_pid'];
                }
            }
            $where['type_pid'] = ['in',$parent];
        }

        // --------------------------------------------------------
        // 【构建查询条件 - 排除分类】
        // --------------------------------------------------------
        if(!empty($not)){
            $where['type_id'] = ['not in',$not];
        }

        // --------------------------------------------------------
        // 【权限过滤】
        // --------------------------------------------------------
        // 如果是前台访问且开启了权限过滤
        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['popedom_filter'] ==1){
            // 获取用户组被限制的分类ID
            $type_ids = mac_get_popedom_filter($GLOBALS['user']['group']['group_type']);
            if(!empty($type_ids)){
                // 如果已有 type_id 条件，合并排除条件
                if(!empty($where['type_id'])){
                    $where['type_id'] = [ $where['type_id'],['not in', explode(',',$type_ids)] ];
                }
                else{
                    $where['type_id'] = ['not in', explode(',',$type_ids)];
                }
            }
        }

        // --------------------------------------------------------
        // 【强制条件 - 只获取启用的分类】
        // --------------------------------------------------------
        $where['type_status'] = ['eq',1];

        // --------------------------------------------------------
        // 【构建排序规则】
        // --------------------------------------------------------
        // 字段名添加 type_ 前缀
        $by = 'type_'.$by;
        // 先按 type_pid 升序 (保证父分类在前)，再按指定字段排序
        $order = 'type_pid asc,'. $by . ' ' . $order;

        // --------------------------------------------------------
        // 【缓存处理】
        // --------------------------------------------------------
        // 生成缓存键名 (包含所有查询参数的MD5)
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('type_listcache_'.http_build_query($where).'_'.$order.'_'.$num.'_'.$start);
        $res = Cache::get($cach_name);

        // 缓存时间默认值
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }

        // 如果未开启核心缓存或缓存为空，重新查询
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$format,$mid,$num,$start,0);
            // 将关联数组转为索引数组 (模板遍历需要)
            $res['list'] = array_values($res['list']);
            // 如果开启了核心缓存，写入缓存
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cachetime);
            }
        }

        return $res;
    }

    /**
     * ============================================================
     * 获取单个分类详情
     * ============================================================
     *
     * 【功能说明】
     * 根据条件获取单个分类的完整信息
     *
     * @param array $where 查询条件 (如 ['type_id'=>1])
     * @param string $field 查询字段，默认 '*' 全部
     * @return array 包含 code/msg/info 的结果数组
     *
     * 【返回数据结构】
     * [
     *   'code' => 1,
     *   'msg' => '获取成功',
     *   'info' => [
     *     'type_id' => 1,
     *     'type_name' => '电影',
     *     'type_extend' => ['class'=>'动作,喜剧', 'area'=>'大陆,香港', ...],
     *     ...
     *   ]
     * ]
     */
    public function infoData($where,$field='*')
    {
        // 参数验证
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // 执行查询
        $info = $this->field($field)->where($where)->find();

        // 检查是否查询到数据
        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }

        // 转换为数组
        $info = $info->toArray();

        // --------------------------------------------------------
        // 【处理扩展属性】
        // --------------------------------------------------------
        if(!empty($info['type_extend'])){
            // 解析 JSON 为数组
            $info['type_extend'] = json_decode($info['type_extend'],true);
        }
        else{
            // 如果为空，设置默认结构
            $info['type_extend'] = json_decode('{"type":"","area":"","lang":"","year":"","star":"","director":"","state":"","version":""}',true);
        }


        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * ============================================================
     * 保存分类数据 (新增/更新)
     * ============================================================
     *
     * 【功能说明】
     * 保存分类数据，自动判断是新增还是更新：
     * - 有 type_id → 更新已有记录
     * - 无 type_id → 新增记录
     *
     * @param array $data 分类数据
     * @return array 包含 code/msg 的结果数组
     *
     * 【执行流程】
     * 1. 数据验证 (使用 Type 验证器)
     * 2. 扩展属性 JSON 编码
     * 3. 自动生成拼音英文标识
     * 4. XSS 过滤
     * 5. 执行新增或更新
     * 6. 刷新分类缓存
     */
    public function saveData($data)
    {
        // --------------------------------------------------------
        // 【数据验证】
        // --------------------------------------------------------
        // 使用 application/admin/validate/Type.php 验证器
        $validate = \think\Loader::validate('Type');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // --------------------------------------------------------
        // 【扩展属性处理】
        // --------------------------------------------------------
        // 将扩展属性数组转为 JSON 字符串存储
        if(!empty($data['type_extend'])){
            $data['type_extend'] = json_encode($data['type_extend']);
        }

        // --------------------------------------------------------
        // 【自动生成英文标识】
        // --------------------------------------------------------
        // 如果没有填写英文标识，自动将分类名称转为拼音
        if(empty($data['type_en'])){
            $data['type_en'] = Pinyin::get($data['type_name']);
        }

        // --------------------------------------------------------
        // 【XSS过滤】
        // --------------------------------------------------------
        // 对用户输入的文本字段进行 XSS 过滤，防止脚本注入
        $filter_fields = [
            'type_name',        // 分类名称
            'type_en',          // 英文标识
            'type_tpl',         // 分类模板
            'type_tpl_list',    // 列表模板
            'type_tpl_detail',  // 详情模板
            'type_tpl_play',    // 播放模板
            'type_tpl_down',    // 下载模板
            'type_key',         // SEO关键词
            'type_des',         // SEO描述
            'type_title',       // SEO标题
            'type_union',       // 联盟分类
            'type_logo',        // Logo图片
            'type_pic',         // 分类大图
            'type_jumpurl',     // 跳转URL
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
        if(!empty($data['type_id'])){
            // 【更新】有 type_id，执行更新操作
            $where=[];
            $where['type_id'] = ['eq',$data['type_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 【新增】无 type_id，执行插入操作
            $res = $this->allowField(true)->insert($data);
        }

        // 检查执行结果
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }

        // --------------------------------------------------------
        // 【刷新缓存】
        // --------------------------------------------------------
        // 分类数据变更后，必须重建缓存
        $this->setCache();
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * ============================================================
     * 删除分类
     * ============================================================
     *
     * 【功能说明】
     * 删除分类记录，删除前会检查是否有关联数据
     * 如果分类下还有视频/文章等内容，则禁止删除
     *
     * @param array $where 查询条件 (如 ['type_id'=>['in','1,2,3']])
     * @return array 包含 code/msg 的结果数组
     *
     * 【执行流程】
     * 1. 查询要删除的分类列表
     * 2. 检查每个分类下是否有关联数据
     * 3. 有数据则返回错误，提示先删除或转移
     * 4. 无数据则执行删除
     * 5. 刷新分类缓存
     */
    public function delData($where)
    {
        // 获取要删除的分类列表
        $list = $this->where($where)->select();

        // --------------------------------------------------------
        // 【关联数据检查】
        // --------------------------------------------------------
        foreach($list as $k=>$v){
            $where2=[];
            // 查询条件: type_id 或 type_id_1 等于当前分类ID
            // type_id: 直接分类
            // type_id_1: 一级分类 (视频/文章表中记录一级和二级分类)
            $where2['type_id|type_id_1'] = ['eq',$v['type_id']];

            // 根据分类类型确定查询的模型
            // type_mid=1 查视频表，否则查文章表
            $flag = $v['type_mid'] == 1 ? 'Vod' : 'Art';
            $cc = model($flag)->where($where2)->count();

            // 如果有关联数据，返回错误
            if($cc > 0){
                return ['code'=>1021,'msg'=>lang('del_err').'：'. $v['type_name'].'还有'.$cc.'条数据，请先删除或转移' ];
            }
        }

        // --------------------------------------------------------
        // 【执行删除】
        // --------------------------------------------------------
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }

        // --------------------------------------------------------
        // 【刷新缓存】
        // --------------------------------------------------------
        $this->setCache();
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * ============================================================
     * 更新单个字段
     * ============================================================
     *
     * 【功能说明】
     * 只更新分类的某一个字段，通常用于状态切换等场景
     *
     * @param array $where 查询条件
     * @param string $col 字段名 (如 'type_status')
     * @param mixed $val 字段值 (如 0 或 1)
     * @return array 包含 code/msg 的结果数组
     *
     * 【使用场景】
     * - 列表页的状态开关切换
     * - 批量启用/禁用分类
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

        // 刷新缓存
        $this->setCache();
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    /**
     * ============================================================
     * 转移分类数据
     * ============================================================
     *
     * 【功能说明】
     * 将某些分类下的所有内容数据转移到另一个目标分类
     * 转移后，原分类下将没有任何内容，可以安全删除
     *
     * @param array $where 源分类查询条件
     * @param int $val 目标分类ID
     * @return array 包含 code/msg 的结果数组
     *
     * 【执行流程】
     * 1. 获取目标分类信息
     * 2. 遍历源分类列表
     * 3. 更新每个源分类下的内容数据的 type_id 和 type_id_1
     *
     * 【注意事项】
     * - 只转移内容数据，不删除源分类本身
     * - 内容的 type_id (当前分类) 和 type_id_1 (一级分类) 都会更新
     */
    public function moveData($where,$val)
    {
        // 获取源分类列表
        $list = $this->where($where)->select();

        // 获取目标分类信息
        $type_info = $this->getCacheInfo($val);
        if(empty($type_info)){
            return ['code'=>1011,'msg'=>lang('model/type/to_info_err')];
        }

        // --------------------------------------------------------
        // 【遍历转移数据】
        // --------------------------------------------------------
        foreach($list as $k=>$v){
            $where2=[];
            // 查询条件: type_id 或 type_id_1 等于源分类ID
            $where2['type_id|type_id_1'] = ['eq',$v['type_id']];

            // 构建更新数据
            $update=[];
            $update['type_id'] = $val;                    // 新的分类ID
            $update['type_id_1'] = $type_info['type_pid']; // 新的一级分类ID

            // 根据分类类型确定更新的模型
            $flag = $v['type_mid'] == 1 ? 'Vod' : 'Art';
            $cc = model($flag)->where($where2)->update($update);

            if($cc ===false){
                return ['code'=>1012,'msg'=>lang('model/type/move_err').'：'. $v['type_name'].''.$this->getError()  ];
            }
        }
        return ['code'=>1,'msg'=>lang('model/type/move_ok')];
    }

    /**
     * ============================================================
     * 重建分类缓存
     * ============================================================
     *
     * 【功能说明】
     * 重新生成分类缓存，包括：
     * - type_list: 平铺列表 (以 type_id 为键)
     * - type_tree: 树形结构 (带 child 子节点)
     *
     * 【调用时机】
     * - 分类数据新增/更新/删除后自动调用
     * - 后台手动清理缓存时调用
     *
     * 【缓存键格式】
     * {cache_flag}_type_list
     * {cache_flag}_type_tree
     */
    public function setCache()
    {
        // 获取所有分类数据 (按ID升序)
        $res = $this->listData([],'type_id asc');
        $list = $res['list'];

        // --------------------------------------------------------
        // 【缓存平铺列表】
        // --------------------------------------------------------
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'type_list';
        Cache::set($key,$list);

        // --------------------------------------------------------
        // 【缓存树形结构】
        // --------------------------------------------------------
        // 将平铺列表转换为树形结构
        $type_tree = mac_list_to_tree($list,'type_id','type_pid');
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'type_tree';
        Cache::set($key,$type_tree);
    }

    /**
     * ============================================================
     * 获取分类缓存
     * ============================================================
     *
     * 【功能说明】
     * 从缓存获取分类数据，如果缓存不存在则自动重建
     *
     * @param string $flag 缓存类型 ('type_list'=平铺/'type_tree'=树形)
     * @return array 分类数据
     *
     * 【使用示例】
     * $type_list = model('Type')->getCache('type_list');
     * $type_tree = model('Type')->getCache('type_tree');
     */
    public function getCache($flag='type_list')
    {
        // 构建缓存键名
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.$flag;
        // 读取缓存
        $cache = Cache::get($key);

        // 如果缓存为空，重建缓存后再获取
        if(empty($cache)){
            $this->setCache();
            $cache = Cache::get($key);
        }
        return $cache;
    }

    /**
     * ============================================================
     * 从缓存获取单个分类信息
     * ============================================================
     *
     * 【功能说明】
     * 根据分类ID或英文标识从缓存中获取单个分类的完整信息
     * 不直接查询数据库，性能更好
     *
     * @param int|string $id 分类ID (数字) 或 英文标识 (字符串)
     * @return array|null 分类信息数组，不存在返回null
     *
     * 【使用示例】
     * // 通过ID获取
     * $type = model('Type')->getCacheInfo(1);
     *
     * // 通过英文标识获取
     * $type = model('Type')->getCacheInfo('dianying');
     */
    public function getCacheInfo($id)
    {
        // 获取分类平铺列表缓存
        $type_list = $this->getCache('type_list');

        if(is_numeric($id)) {
            // 【数字ID】直接通过键获取
            return $type_list[$id];
        }
        else{
            // 【英文标识】遍历查找
            foreach($type_list as $k=>$v){
                if($v['type_en'] == $id){
                    return $type_list[$k];
                }
            }
        }
    }



}