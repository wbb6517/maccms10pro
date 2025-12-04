<?php
/**
 * 访客日志模型 (Visit Model)
 * ============================================================
 *
 * 【文件说明】
 * 访客访问日志数据模型
 * 记录用户访问来源（来路URL），用于流量统计和推广分析
 *
 * 【数据表】
 * mac_visit - 访客日志表
 *
 * 【数据表字段说明】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 说明                                     │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ visit_id         │ 记录ID (主键自增)                         │
 * │ user_id          │ 用户ID (0=游客)                          │
 * │ user_name        │ 用户名                                   │
 * │ visit_ly         │ 来路URL (访问来源地址)                    │
 * │ visit_time       │ 访问时间 (时间戳)                         │
 * │ visit_ip         │ 访问IP地址                               │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ countData()     │ 统计记录数量                                │
 * │ listData()      │ 获取日志列表                                │
 * │ infoData()      │ 获取单条日志详情                            │
 * │ saveData()      │ 保存日志记录                                │
 * │ delData()       │ 删除日志记录                                │
 * │ fieldData()     │ 更新单个字段                                │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【相关文件】
 * - application/admin/controller/Visit.php : 后台管理控制器
 * - application/common/validate/Visit.php : 数据验证器
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;

class Visit extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'visit';

    // 定义时间戳字段名（不使用自动时间戳）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成配置
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * ============================================================
     * 统计记录数量
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
     * 获取日志列表
     * ============================================================
     *
     * 【功能说明】
     * 分页获取访客日志列表，并为每条记录添加模块标识
     * visit_mid: 6=注册用户访问, 11=游客访问
     *
     * @param array|string $where     查询条件
     * @param string       $order     排序规则
     * @param int          $page      当前页码
     * @param int          $limit     每页数量
     * @param int          $start     起始偏移
     * @param string       $field     查询字段
     * @param int          $addition  附加处理标识
     * @param int          $totalshow 是否统计总数
     * @return array 包含列表数据和分页信息的数组
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

        // 计算分页偏移
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        // 统计总数
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }

        // 查询列表
        $list = Db::name('Visit')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        // 添加模块标识：区分注册用户和游客
        foreach($list as $k=>$v){
            $visit_mid = 6;  // 默认为注册用户
            if($v['user_id']==0){
                $visit_mid = 11; // 游客
            }
            $list[$k]['visit_mid'] = $visit_mid;
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * ============================================================
     * 获取单条日志详情
     * ============================================================
     *
     * @param array  $where 查询条件
     * @param string $field 查询字段
     * @return array 包含日志详情的数组
     */
    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 1002, 'msg' => lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * ============================================================
     * 保存日志记录
     * ============================================================
     *
     * 【功能说明】
     * 新增或更新访客日志记录
     * 有visit_id则更新，无则新增
     *
     * @param array $data 日志数据
     * @return array 操作结果
     */
    public function saveData($data)
    {
        // 数据验证
        $validate = \think\Loader::validate('Visit');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if(!empty($data['visit_id'])){
            // 更新已有记录
            $where=[];
            $where['visit_id'] = ['eq',$data['visit_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增记录，自动设置访问时间
            $data['visit_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }

        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * ============================================================
     * 删除日志记录
     * ============================================================
     *
     * @param array $where 删除条件
     * @return array 操作结果
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
     * ============================================================
     * 更新单个字段
     * ============================================================
     *
     * @param array  $where 更新条件
     * @param string $col   字段名
     * @param mixed  $val   字段值
     * @return array 操作结果
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
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}