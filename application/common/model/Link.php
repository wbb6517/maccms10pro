<?php
/**
 * ============================================================
 * 友情链接数据模型 (Link Data Model)
 * ============================================================
 *
 * 【文件说明】
 * 友情链接的数据访问层，处理 mac_link 表的所有数据库操作
 * 提供列表查询、详情获取、保存、删除等功能
 *
 * 【数据表】mac_link
 *
 * 【字段说明】
 * ┌────────────────┬─────────────────────────────────────────┐
 * │ 字段名          │ 说明                                     │
 * ├────────────────┼─────────────────────────────────────────┤
 * │ link_id        │ 友链ID (主键, 自增)                       │
 * │ link_name      │ 友链名称/网站名                          │
 * │ link_url       │ 链接地址 (完整URL，如 https://xxx.com)    │
 * │ link_logo      │ Logo图片URL (图片链接时显示)              │
 * │ link_type      │ 链接类型: 0=文字链接, 1=图片链接          │
 * │ link_sort      │ 排序值 (数字小的在前，默认0)              │
 * │ link_time      │ 更新时间戳                               │
 * │ link_add_time  │ 添加时间戳                               │
 * └────────────────┴─────────────────────────────────────────┘
 *
 * 【方法列表】
 * ┌──────────────────┬──────────────────────────────────────────┐
 * │ 方法名            │ 功能说明                                  │
 * ├──────────────────┼──────────────────────────────────────────┤
 * │ listData()       │ 分页查询友链列表                          │
 * │ listCacheData()  │ 带缓存的友链列表 (前台模板标签调用)        │
 * │ infoData()       │ 获取单条友链详情                          │
 * │ saveData()       │ 保存友链 (新增或更新)                     │
 * │ delData()        │ 删除友链                                  │
 * │ fieldData()      │ 更新单个字段                              │
 * └──────────────────┴──────────────────────────────────────────┘
 *
 * 【缓存机制】
 * - 缓存键: link_listcache_{md5(条件+排序+分页)}
 * - 缓存时间: 由系统配置 cache_time 控制
 * - 缓存开关: 由系统配置 cache_core 控制
 *
 * 【模板标签调用示例】
 * {maccms:link type="font" order="desc" by="sort" num="10"}
 *     <a href="{$vo.link_url}">{$vo.link_name}</a>
 * {/maccms:link}
 *
 * @package     app\common\model
 * @author      MacCMS
 * @version     1.0
 */
namespace app\common\model;
use think\Db;
use think\Cache;

class Link extends Base {
    /**
     * 设置数据表名称（不含前缀）
     * 实际表名: mac_link
     */
    protected $name = 'link';

    /**
     * 定义时间戳字段名
     * 设为空表示不使用自动时间戳
     * 改用手动设置 link_time 和 link_add_time
     */
    protected $createTime = '';
    protected $updateTime = '';

    /**
     * 自动完成配置
     * 留空表示不使用自动完成
     */
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 分页查询友链列表
     *
     * 【功能说明】
     * 根据条件分页查询友情链接列表
     * 主要供后台管理使用
     *
     * 【参数说明】
     * @param array|string $where  查询条件 (支持数组或JSON字符串)
     * @param string       $order  排序规则 (如 "link_id desc")
     * @param int          $page   当前页码 (默认1)
     * @param int          $limit  每页条数 (默认20)
     * @param int          $start  起始偏移量 (默认0)
     *
     * @return array 返回结构:
     *   - code: 状态码 (1=成功)
     *   - msg: 提示信息
     *   - page: 当前页码
     *   - pagecount: 总页数
     *   - limit: 每页条数
     *   - total: 总记录数
     *   - list: 友链列表数据
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0)
    {
        // 参数处理：确保为正整数
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;

        // 支持JSON格式的查询条件
        if(!is_array($where)){
            $where = json_decode($where,true);
        }

        // 计算分页偏移量: (页码-1)*每页数+起始偏移
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        // 查询总数
        $total = $this->where($where)->count();

        // 查询列表数据
        $list = Db::name('Link')->where($where)->order($order)->limit($limit_str)->select();

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 带缓存的友链列表查询
     *
     * 【功能说明】
     * 供前台模板标签 {maccms:link} 调用
     * 自动处理排序、过滤、缓存等逻辑
     *
     * 【参数说明】
     * @param array|string $lp 查询参数数组，支持以下键:
     *   - order    : 排序方向 (asc/desc，默认asc)
     *   - by       : 排序字段 (id/sort，默认id)
     *   - type     : 链接类型 (font=文字/pic=图片)
     *   - start    : 起始位置 (默认0)
     *   - num      : 获取数量 (默认20)
     *   - cachetime: 缓存时间(秒)，0使用系统默认
     *   - not      : 排除的ID (逗号分隔)
     *
     * 【使用示例】
     * // 获取10条图片链接，按排序值升序
     * $list = model('Link')->listCacheData([
     *     'type' => 'pic',
     *     'order' => 'asc',
     *     'by' => 'sort',
     *     'num' => 10
     * ]);
     *
     * @return array 同 listData() 返回格式
     */
    public function listCacheData($lp)
    {
        // 支持JSON格式参数
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        // 提取参数
        $order = $lp['order'];
        $by = $lp['by'];
        $type = $lp['type'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $cachetime = $lp['cachetime'];
        $not = $lp['not'];
        $page = 1;
        $where = [];

        // 默认数量
        if(empty($num)){
            $num = 20;
        }

        // 起始位置调整 (模板标签从1开始计数)
        if($start>1){
            $start--;
        }

        // 验证排序方向
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        // 验证排序字段 (只允许 id 和 sort)
        if (!in_array($by, ['id', 'sort'])) {
            $by = 'id';
        }

        // 链接类型过滤: font=0(文字), pic=1(图片)
        if (in_array($type, ['font', 'pic'])) {
            $type = ($type === 'font') ? 0 : 1;
            $where['link_type'] = $type;
        }

        // 排除指定ID
        if(!empty($not)){
            $where['link_id'] = ['not in',explode(',',$not)];
        }

        // 构建排序字符串
        $by = 'link_'.$by;
        $order = $by . ' ' . $order;

        // ============================================================
        // 【缓存处理】
        // 缓存键 = 前缀_md5(条件+排序+分页参数)
        // ============================================================
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('link_listcache_'.join('&',$where).'_'.$order.'_'.$page.'_'.$num.'_'.$start);
        $res = Cache::get($cach_name);

        // 缓存时间，默认使用系统配置
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }

        // 缓存未命中或缓存关闭时，查询数据库
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where, $order, $page, $num, $start);
            // 缓存开启时，写入缓存
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cachetime);
            }
        }
        return $res;
    }

    /**
     * 获取单条友链详情
     *
     * 【功能说明】
     * 根据条件获取一条友链的完整信息
     *
     * 【参数说明】
     * @param array  $where 查询条件 (必须是数组)
     * @param string $field 要获取的字段 (默认 '*' 所有字段)
     *
     * @return array 返回结构:
     *   - code: 状态码 (1=成功, 1001=参数错误, 1002=数据不存在)
     *   - msg: 提示信息
     *   - info: 友链详情数据 (成功时)
     */
    public function infoData($where,$field='*')
    {
        // 参数验证
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // 查询单条记录
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }

        // 转换为数组格式
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 保存友链数据
     *
     * 【功能说明】
     * 新增或更新友链记录
     * - 有 link_id: 更新现有记录
     * - 无 link_id: 新增记录
     *
     * 【参数说明】
     * @param array $data 友链数据:
     *   - link_id   : 友链ID (编辑时必填)
     *   - link_name : 友链名称 (必填)
     *   - link_url  : 链接地址 (必填)
     *   - link_logo : Logo图片地址
     *   - link_type : 链接类型 (0=文字, 1=图片)
     *   - link_sort : 排序值
     *
     * @return array 返回结构:
     *   - code: 状态码 (1=成功, 1001=验证失败, 1002=保存失败)
     *   - msg: 提示信息
     */
    public function saveData($data)
    {
        // 调用验证器验证数据
        $validate = \think\Loader::validate('Link');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // 设置更新时间
        $data['link_time'] = time();

        // 根据是否有ID判断新增还是更新
        if(!empty($data['link_id'])){
            // 更新: 有link_id
            $where=[];
            $where['link_id'] = ['eq',$data['link_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增: 无link_id，同时设置添加时间
            $data['link_add_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }

        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除友链
     *
     * 【功能说明】
     * 根据条件删除一条或多条友链记录
     *
     * 【参数说明】
     * @param array $where 删除条件
     *   例如: ['link_id' => ['in', '1,2,3']]
     *
     * @return array 返回结构:
     *   - code: 状态码 (1=成功, 1001=删除失败)
     *   - msg: 提示信息
     */
    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 更新单个字段
     *
     * 【功能说明】
     * 快速更新指定记录的某个字段值
     * 常用于状态切换、排序值修改等场景
     *
     * 【参数说明】
     * @param array  $where 更新条件
     * @param string $col   字段名
     * @param mixed  $val   字段值
     *
     * @return array 返回结构:
     *   - code: 状态码 (1=成功, 1001=失败)
     *   - msg: 提示信息
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
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}