<?php
/**
 * 会员组模型 (Group Model)
 * ============================================================
 *
 * 【文件说明】
 * 会员组数据模型，负责会员等级分组的数据操作
 * 包括会员组的增删改查、权限管理和缓存处理
 *
 * 【数据表】
 * mac_group - 会员组数据表
 *
 * 【数据表字段说明】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 说明                                     │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ group_id         │ 会员组ID (主键)                          │
 * │ group_name       │ 会员组名称 (如:游客、VIP会员)             │
 * │ group_status     │ 状态 (0=禁用, 1=启用)                    │
 * │ group_type       │ 可观看分类ID (逗号分隔,如:,1,2,3,)       │
 * │ group_popedom    │ 权限配置 (JSON格式存储各项权限)           │
 * │ group_points_day │ 每日积分上限                             │
 * │ group_points_week│ 每周积分上限                             │
 * │ group_points_month│ 每月积分上限                            │
 * │ group_price_day  │ 包天价格 (元)                            │
 * │ group_price_week │ 包周价格 (元)                            │
 * │ group_price_month│ 包月价格 (元)                            │
 * │ group_price_year │ 包年价格 (元)                            │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * 【方法列表】
 * ┌──────────────────┬──────────────────────────────────────────┐
 * │ 方法名            │ 功能说明                                  │
 * ├──────────────────┼──────────────────────────────────────────┤
 * │ listData()       │ 获取会员组列表                            │
 * │ infoData()       │ 获取单个会员组详情                        │
 * │ saveData()       │ 保存会员组数据 (新增/编辑)                │
 * │ delData()        │ 删除会员组                                │
 * │ fieldData()      │ 批量更新指定字段                          │
 * │ setCache()       │ 设置会员组缓存                            │
 * │ getCache()       │ 获取会员组缓存                            │
 * └──────────────────┴──────────────────────────────────────────┘
 *
 * 【缓存键说明】
 * - {cache_flag}_group_list : 会员组列表缓存
 *
 * 【相关文件】
 * - application/admin/controller/Group.php : 后台控制器
 * - application/admin/validate/Group.php : 验证器
 *
 * ============================================================
 */
namespace app\common\model;
use think\Cache;
use think\Db;

class Group extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'group';

    // 定义时间戳字段名（本表不使用自动时间戳）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成配置（本表未使用）
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * ============================================================
     * 获取器：会员组状态文本
     * ============================================================
     *
     * 【功能说明】
     * 将数字状态转换为可读文本
     * 0 → 禁用, 1 → 启用
     *
     * @param mixed $val 原始值（未使用）
     * @param array $data 当前行数据
     * @return string 状态文本
     */
    public function getGroupStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['group_status']];
    }

    /**
     * ============================================================
     * 获取会员组列表
     * ============================================================
     *
     * 【功能说明】
     * 查询会员组列表，并解析权限JSON数据
     *
     * @param array $where 查询条件
     * @param string $order 排序规则
     * @return array 返回结果 ['code'=>1, 'msg'=>'', 'total'=>总数, 'list'=>列表]
     *
     * 【使用示例】
     * $res = model('Group')->listData(['group_status'=>1], 'group_id asc');
     */
    public function listData($where,$order)
    {
        $total = $this->where($where)->count();
        $tmp = Db::name('Group')->where($where)->order($order)->select();

        $list = [];
        foreach($tmp as $k=>$v){
            $v['group_popedom'] = json_decode($v['group_popedom'],true);
            $list[$v['group_id']] = $v;
        }

        return ['code'=>1,'msg'=>lang('data_list'),'total'=>$total,'list'=>$list];
    }

    /**
     * ============================================================
     * 获取单个会员组详情
     * ============================================================
     *
     * 【功能说明】
     * 根据条件获取单个会员组的详细信息
     * 自动解析 group_popedom 字段的JSON数据
     *
     * @param array $where 查询条件
     * @param string $field 查询字段，默认全部
     * @return array 返回结果 ['code'=>1, 'msg'=>'', 'info'=>会员组信息]
     *
     * 【使用示例】
     * $res = model('Group')->infoData(['group_id'=>['eq', 1]]);
     */
    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();
        $info['group_popedom'] = json_decode($info['group_popedom'],true);
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * ============================================================
     * 保存会员组数据 (新增/编辑)
     * ============================================================
     *
     * 【功能说明】
     * 保存会员组数据，支持新增和编辑操作
     *
     * 【处理流程】
     * 1. 处理 group_type 数组转逗号分隔字符串
     * 2. 处理 group_popedom 权限数组转JSON
     * 3. 验证数据格式
     * 4. 根据是否有 group_id 判断新增或更新
     * 5. 刷新缓存
     *
     * @param array $data 表单数据
     * @return array 返回结果 ['code'=>1, 'msg'=>'']
     */
    public function saveData($data)
    {
        if(!empty($data['group_type'])){
            $data['group_type'] = ','.join(',',$data['group_type']) .',';
        }else{
            $data['group_type'] = '';
        }

        if(!empty($data['group_popedom'])){
            $data['group_popedom'] = json_encode($data['group_popedom']);
        }
        else{
            $data['group_popedom'] ='';
        }

        $validate = \think\Loader::validate('Group');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }
        if(!empty($data['group_id'])){
            $where=[];
            $where['group_id'] = ['eq',$data['group_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        $this->setCache();
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * ============================================================
     * 删除会员组
     * ============================================================
     *
     * 【功能说明】
     * 删除指定的会员组
     *
     * 【保护机制】
     * 如果该会员组下还有用户，则不允许删除
     * 删除后自动刷新缓存
     *
     * @param array $where 删除条件
     * @return array 返回结果 ['code'=>1, 'msg'=>'']
     */
    public function delData($where)
    {
        $cc = model('User')->countData($where);
        if($cc>0){
            return ['code'=>1002,'msg'=>lang('del_err').'：'.lang('model/group/have_user') ];
        }
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        $this->setCache();
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * ============================================================
     * 批量更新指定字段
     * ============================================================
     *
     * 【功能说明】
     * 批量更新会员组的单个字段值
     * 更新后自动刷新缓存
     *
     * @param array $where 更新条件
     * @param string $col 字段名
     * @param mixed $val 字段值
     * @return array 返回结果 ['code'=>1, 'msg'=>'']
     */
    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        $this->setCache();
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    /**
     * ============================================================
     * 设置会员组缓存
     * ============================================================
     *
     * 【功能说明】
     * 将所有会员组数据写入缓存
     * 在增删改操作后自动调用
     *
     * 【缓存键】
     * {cache_flag}_group_list
     */
    public function setCache()
    {
        // 获取所有会员组列表
        $res = $this->listData([],'group_id asc');
        $list = $res['list'];
        // 构建缓存键名
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'group_list';
        // 写入缓存
        Cache::set($key,$list);

    }

    /**
     * ============================================================
     * 获取会员组缓存
     * ============================================================
     *
     * 【功能说明】
     * 从缓存获取会员组数据
     * 如果缓存不存在则自动刷新
     *
     * @param string $flag 缓存标识，默认 group_list
     * @return array 会员组列表（以group_id为键）
     *
     * 【使用示例】
     * $groups = model('Group')->getCache();
     */
    public function getCache($flag='group_list')
    {
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.$flag;
        $cache = Cache::get($key);
        if(empty($cache)){
            $this->setCache();
            $cache = Cache::get($key);
        }
        return $cache;
    }

}