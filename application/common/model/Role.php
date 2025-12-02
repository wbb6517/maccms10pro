<?php
/**
 * 角色数据模型 (Role Model)
 * ============================================================
 *
 * 【文件说明】
 * 管理视频角色数据的模型，角色是视频内容的扩展信息
 * 对应数据表: mac_role
 *
 * 【业务场景】
 * - 电视剧/电影中的角色信息管理
 * - 角色与视频是多对一关系 (一个视频可有多个角色)
 * - 前台可展示角色列表、角色详情、按演员筛选等
 *
 * 【方法列表】
 * ┌─────────────────────────┬────────────────────────────────────────────┐
 * │ 方法名                   │ 功能说明                                    │
 * ├─────────────────────────┼────────────────────────────────────────────┤
 * │ countData()             │ 统计符合条件的角色数量                       │
 * │ listData()              │ 获取角色列表 (分页查询，含关联视频)          │
 * │ listCacheData()         │ 前台调用的缓存列表 (模板标签使用)            │
 * │ infoData()              │ 获取角色详情 (含关联视频信息)                │
 * │ saveData()              │ 保存角色数据 (新增/编辑)                     │
 * │ delData()               │ 删除角色数据 (含清理关联图片)                │
 * │ fieldData()             │ 批量更新指定字段                            │
 * └─────────────────────────┴────────────────────────────────────────────┘
 *
 * 【核心字段说明】
 * ┌──────────────────┬─────────────────────────────────────────────────┐
 * │ 字段名            │ 说明                                             │
 * ├──────────────────┼─────────────────────────────────────────────────┤
 * │ role_id          │ 角色ID (主键，自增)                              │
 * │ role_rid         │ 关联视频ID (外键 → mac_vod.vod_id)              │
 * │ role_name        │ 角色名称 (如: 孙悟空)                            │
 * │ role_en          │ 英文名/拼音 (用于URL友好)                        │
 * │ role_actor       │ 扮演者/演员名 (如: 章金莱)                       │
 * │ role_status      │ 状态: 0=未审核, 1=已审核                          │
 * │ role_level       │ 推荐等级: 0-9                                    │
 * │ role_lock        │ 锁定: 0=否, 1=是                                 │
 * │ role_pic         │ 角色图片URL                                      │
 * │ role_sort        │ 排序值 (用于同一视频内角色排序)                   │
 * │ role_remarks     │ 角色备注/简介                                    │
 * │ role_content     │ 角色详细介绍 (富文本)                            │
 * │ role_time        │ 更新时间戳                                       │
 * │ role_time_add    │ 添加时间戳                                       │
 * │ role_hits        │ 总点击量                                         │
 * │ role_hits_day    │ 日点击量                                         │
 * │ role_hits_week   │ 周点击量                                         │
 * │ role_hits_month  │ 月点击量                                         │
 * └──────────────────┴─────────────────────────────────────────────────┘
 *
 * 【关联查询】
 * listData() 和 infoData() 会自动关联查询 mac_vod 表
 * 返回的 data 字段包含关联视频的完整信息
 *
 * 【缓存机制】
 * - 详情页缓存: role_detail_{id}、role_detail_{en}
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

class Role extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'role';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 获取角色状态文本 (获取器)
     *
     * @param mixed $val  原始值
     * @param array $data 当前行数据
     * @return string 状态文本 (禁用/启用)
     */
    public function getRoleStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['role_status']];
    }

    /**
     * 统计角色数量
     *
     * @param array $where 查询条件
     * @return int 符合条件的角色数量
     */
    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    /**
     * 获取角色列表 (分页查询)
     *
     * 【功能说明】
     * 后台和API调用的核心列表方法
     * 自动关联查询视频信息
     *
     * 【关联查询】
     * 当 addition=1 时，会批量查询角色关联的视频信息
     * 每条角色记录的 data 字段包含关联视频信息
     *
     * @param array|string $where    查询条件 (数组或JSON字符串)
     * @param string       $order    排序方式 (如 "role_time desc")
     * @param int          $page     页码 (默认1)
     * @param int          $limit    每页条数 (默认20)
     * @param int          $start    偏移量 (默认0)
     * @param string       $field    查询字段 (默认*)
     * @param int          $addition 是否附加视频信息 (1=是)
     * @param int          $totalshow 是否统计总数 (1=是)
     * @return array 返回结构:
     *               - code: 状态码
     *               - msg: 消息
     *               - page: 当前页
     *               - pagecount: 总页数
     *               - limit: 每页条数
     *               - total: 总记录数
     *               - list: 角色列表 (含 data 字段为关联视频)
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
        // 查询角色列表
        $list = Db::name('Role')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        // ========== 批量关联查询视频信息 ==========
        // 优化: 先收集所有视频ID，一次性查询，避免N+1问题
        $vod_list=[];
        if($addition==1){
            // 收集所有关联的视频ID
            $vod_ids=[];
            foreach($list as $k=>$v){
                $vod_ids[$v['role_rid']] = $v['role_rid'];
            }
            // 批量查询视频信息
            $where2=[];
            $where2['vod_id'] = ['in', implode(',',$vod_ids)];
            $tmp_list = model('Vod')->listData($where2,'vod_id desc',1,999,0);
            // 构建视频ID=>视频信息的映射
            foreach($tmp_list['list'] as $k=>$v){
                $vod_list[$v['vod_id']] = $v;
            }
        }
        // 将视频信息附加到角色记录
        foreach($list as $k=>$v){
            if($addition==1){
                $list[$k]['data'] = $vod_list[$v['role_rid']];
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 前台缓存列表数据 (模板标签调用)
     *
     * 【功能说明】
     * 前台模板标签 {maccms:role} 调用的核心方法
     * 支持多种筛选条件和缓存机制
     *
     * 【主要参数】 (通过 $lp 数组传入)
     * - order    : 排序字段
     * - by       : 排序方式 (asc/desc/rnd随机)
     * - ids      : 指定角色ID (逗号分隔)
     * - rid      : 关联视频ID (筛选某视频的所有角色)
     * - level    : 推荐等级筛选
     * - letter   : 首字母筛选
     * - actor    : 演员筛选
     * - name     : 角色名称筛选
     * - wd       : 关键词搜索
     * - paging   : 是否分页 (yes/no)
     * - num      : 每页数量
     * - start    : 起始位置
     * - cachetime: 缓存时间
     *
     * @param array|string $lp 参数数组或JSON字符串
     * @return array 返回结构: code/msg/page/pagecount/limit/total/list/pageurl/half
     */
    public function listCacheData($lp)
    {
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        // 解析参数
        $order = $lp['order'];
        $by = $lp['by'];
        $ids = $lp['ids'];
        $paging = $lp['paging'];
        $pageurl = $lp['pageurl'];
        $level = $lp['level'];
        $wd = $lp['wd'];
        $actor = $lp['actor'];
        $name = $lp['name'];
        $rid = $lp['rid'];           // 关联视频ID
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
        $page = 1;
        $where = [];
        $totalshow=0;

        // 默认每页20条
        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }
        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }

        // ========== 分页模式参数处理 ==========
        $param = mac_param_url();
        if($paging=='yes') {
            $param = mac_search_len_check($param);
            $totalshow = 1;
            // 从URL获取筛选参数
            if(!empty($param['rid'])) {
                $rid = intval($param['rid']);
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
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
                $pageurl = 'role/index';
            }
            $param['page'] = 'PAGELINK';
            $pageurl = mac_url($pageurl,$param);

        }

        // ========== 构建查询条件 ==========
        // 前台只显示已审核的角色
        $where['role_status'] = ['eq',1];

        // 推荐等级筛选
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['role_level'] = ['in',explode(',',$level)];
        }
        // 指定ID筛选
        if(!empty($ids)) {
            if($ids!='all'){
                $where['role_id'] = ['in',explode(',',$ids)];
            }
        }
        // 排除ID筛选
        if(!empty($not)){
            $where['role_id'] = ['not in',explode(',',$not)];
        }
        // 首字母筛选
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['role_letter'] = ['in',explode(',',$letter)];
        }
        // 关联视频筛选 (筛选某视频的所有角色)
        if(!empty($rid)) {
            $where['role_rid'] = ['eq',$rid];
        }
        // 时间筛选
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['role_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['role_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['role_time'] =['gt',$s];
        }
        // 点击量筛选
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['role_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['role_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['role_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['role_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['role_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['role_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['role_hits'] = ['gt', $tmp];
            }
            else{
                $where['role_hits'] = [$tmp[0],$tmp[1]];
            }
        }
        // 演员筛选
        if(!empty($actor)){
            $where['role_actor'] = ['in',explode(',',$actor) ];
        }
        // 角色名称筛选
        if(!empty($name)){
            $where['role_name'] = ['in',explode(',',$name) ];
        }
        // 关键词搜索 (名称或英文名)
        if(!empty($wd)) {
            $where['role_name|role_en'] = ['like', '%' . $wd . '%'];
        }

        // ========== 随机排序处理 ==========
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

        // 排序字段验证
        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd','sort'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        $order= 'role_'.$by .' ' . $order;

        // 缓存键处理
        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['role_id']);
            $where_cache['order'] = 'rnd';
        }

        // ========== 缓存查询 ==========
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('role_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
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
     * 获取角色详情
     *
     * 【功能说明】
     * 获取单条角色的完整信息
     * 自动关联查询所属视频信息
     *
     * 【返回数据】
     * - 角色所有字段
     * - data: 关联的视频信息 (通过 Vod->infoData 获取)
     *
     * 【缓存键】
     * - role_detail_{role_id}_{role_en}
     *
     * @param array  $where 查询条件 (必须包含 role_id 或 role_en)
     * @param string $field 查询字段
     * @param int    $cache 是否使用缓存 (0=不使用, 1=使用)
     * @return array 返回结构:
     *               - code: 1=成功, 1001=参数错误, 1002=数据不存在
     *               - msg: 消息
     *               - info: 角色详情数组 (含 data 为关联视频)
     */
    public function infoData($where,$field='*',$cache=0)
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        // 缓存键
        $data_cache = false;
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'role_detail_'.$where['role_id'][1].'_'.$where['role_en'][1];
        if($where['role_id'][0]=='eq' || $where['role_en'][0]=='eq'){
            $data_cache = true;
        }
        // 尝试从缓存获取
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }
        // 缓存未命中，查询数据库
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['role_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();

            // ========== 关联查询视频信息 ==========
            $info['data'] = [];
            if(!empty($info['role_rid'])){
                $where2=[];
                $where2['vod_id'] = ['eq', $info['role_rid']];
                $vod_info = model('Vod')->infoData($where2);
                if($vod_info['code'] == 1){
                    $info['data'] = $vod_info['info'];
                }
            }
            // 写入缓存
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 保存角色数据 (新增/编辑)
     *
     * 【功能说明】
     * 保存角色数据到数据库
     * 自动处理拼音生成、图片URL处理、缓存清理等
     *
     * 【数据处理流程】
     * 1. 验证数据格式 (通过 RoleValidate)
     * 2. 清除相关缓存
     * 3. 自动生成 role_en (拼音)
     * 4. 自动生成 role_letter (首字母)
     * 5. 处理内容中的图片协议
     * 6. 可选更新时间
     * 7. 保存到数据库
     *
     * @param array $data 角色数据数组
     *                    - role_id: 有值则编辑，无值则新增
     *                    - role_rid: 关联视频ID (必填)
     *                    - uptime: 1=更新时间
     * @return array 返回结构: code/msg
     */
    public function saveData($data)
    {
        // ========== 步骤1: 数据验证 ==========
        $validate = \think\Loader::validate('Role');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // ========== 步骤2: 清除相关缓存 ==========
        $key = 'role_detail_'.$data['role_id'];
        Cache::rm($key);
        $key = 'role_detail_'.$data['role_en'];
        Cache::rm($key);
        $key = 'role_detail_'.$data['role_id'].'_'.$data['role_en'];
        Cache::rm($key);

        // ========== 步骤3: 自动生成拼音 ==========
        if(empty($data['role_en'])){
            $data['role_en'] = Pinyin::get($data['role_name']);
        }

        // ========== 步骤4: 自动生成首字母 ==========
        if(empty($data['role_letter'])){
            $data['role_letter'] = strtoupper(substr($data['role_en'],0,1));
        }

        // ========== 步骤5: 处理内容中的图片URL ==========
        // 将图片URL中的协议替换为 mac: 前缀
        if(!empty($data['role_content'])) {
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['role_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['role_content'] = str_replace($v1, $v2, $data['role_content']);
                }
            }
            unset($match_src1);
        }

        // ========== 步骤6: 更新时间处理 ==========
        if($data['uptime']==1){
            $data['role_time'] = time();
        }
        unset($data['uptime']);

        // ========== 步骤7: 执行数据库操作 ==========
        if(!empty($data['role_id'])){
            // 编辑模式
            $where=[];
            $where['role_id'] = ['eq',$data['role_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增模式
            $data['role_time_add'] = time();
            $data['role_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除角色数据
     *
     * 【功能说明】
     * 删除符合条件的角色数据
     * 同时清理关联的本地图片文件
     *
     * 【清理内容】
     * - role_pic: 角色图片
     *
     * 【安全检查】
     * 只删除 ./upload 目录下的图片文件
     *
     * @param array $where 删除条件
     * @return array 返回结构: code/msg
     */
    public function delData($where)
    {
        // 先删除数据库记录
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        // 清理关联的本地图片文件
        $list = $this->where($where)->select();
        $path = './';
        foreach($list as $k=>$v){
            $pic = $path.$v['role_pic'];
            // 只删除 upload 目录下的图片，防止误删
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 批量更新指定字段
     *
     * 【功能说明】
     * 批量更新符合条件的角色的指定字段
     * 更新后自动清除相关缓存
     *
     * 【使用场景】
     * - 批量修改审核状态
     * - 批量修改推荐等级
     * - 批量修改点击量
     *
     * @param array  $where 更新条件
     * @param string $col   字段名
     * @param mixed  $val   字段值
     * @return array 返回结构: code/msg
     */
    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // 执行更新
        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        // 清除相关缓存
        $list = $this->field('role_id,role_name,role_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'role_detail_'.$v['role_id'];
            Cache::rm($key);
            $key = 'role_detail_'.$v['role_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}