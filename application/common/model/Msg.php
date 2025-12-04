<?php
/**
 * 验证码消息模型 (Message Model)
 * ============================================================
 *
 * 【文件说明】
 * 验证码消息记录的数据模型
 * 用于存储和管理发送给用户的短信/邮件验证码
 * 支持注册验证、密码找回、账号绑定等场景
 *
 * 【数据表】
 * mac_msg - 验证码消息记录表
 *
 * 【消息类型 msg_type】
 * - 1 : 账号绑定 (绑定邮箱/手机)
 * - 2 : 密码找回
 * - 3 : 用户注册
 *
 * 【方法列表】
 * ┌────────────────────────┬──────────────────────────────────────────────┐
 * │ 方法名                  │ 功能说明                                      │
 * ├────────────────────────┼──────────────────────────────────────────────┤
 * │ getMsgStatusTextAttr() │ 获取器: 状态文本转换                           │
 * │ countData()            │ 统计符合条件的消息数量                         │
 * │ listData()             │ 获取消息列表 (支持分页)                        │
 * │ infoData()             │ 获取单条消息详情                               │
 * │ saveData()             │ 保存消息记录 (新增/更新)                       │
 * │ delData()              │ 删除消息记录                                   │
 * │ fieldData()            │ 更新指定字段值                                 │
 * └────────────────────────┴──────────────────────────────────────────────┘
 *
 * 【使用位置】
 * - application/common/model/User.php
 *   - checkUserMsg()  : 验证用户输入的验证码是否正确
 *   - sendUserMsg()   : 发送验证码并记录到数据库
 *
 * 【业务流程】
 * 1. 用户请求发送验证码 (注册/找回密码/绑定)
 * 2. 系统生成随机验证码，通过短信/邮件发送
 * 3. 调用 saveData() 将验证码记录到 mac_msg 表
 * 4. 用户提交验证码，系统调用 infoData() 验证
 * 5. 验证通过后，可删除或标记已使用
 *
 * 【验证规则】
 * - 验证码有效期: 默认5分钟 (可配置)
 * - 发送频率限制: 防止重复发送
 *
 * 【相关文件】
 * - application/common/validate/Msg.php      : 数据验证器
 * - application/common/extend/sms/Aliyun.php : 阿里云短信扩展
 * - application/common/extend/sms/Qcloud.php : 腾讯云短信扩展
 * - application/common/extend/email/Phpmailer.php : PHPMailer 邮件扩展
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Msg extends Base {
    /**
     * 数据表名 (不含前缀)
     * @var string
     */
    protected $name = 'msg';

    /**
     * 创建时间字段 (留空表示不自动写入)
     * @var string
     */
    protected $createTime = '';

    /**
     * 更新时间字段 (留空表示不自动写入)
     * @var string
     */
    protected $updateTime = '';

    /**
     * 自动完成字段 (通用)
     * @var array
     */
    protected $auto       = [];

    /**
     * 新增时自动完成
     * @var array
     */
    protected $insert     = [];

    /**
     * 更新时自动完成
     * @var array
     */
    protected $update     = [];

    /**
     * 获取器: 消息状态文本
     * 将状态码转换为可读文本
     *
     * @param mixed $val  原始值
     * @param array $data 当前行数据
     * @return string 状态文本: 未审核/已审核
     */
    public function getMsgStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('unverified'),1=>lang('verified')];
        return $arr[$data['msg_status']];
    }

    /**
     * 统计消息数量
     *
     * @param array $where 查询条件
     * @return int 消息数量
     */
    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    /**
     * ============================================================
     * 获取消息列表
     * ============================================================
     *
     * 【功能说明】
     * 分页获取验证码消息记录列表
     *
     * @param array  $where      查询条件
     * @param string $order      排序规则
     * @param int    $page       当前页码
     * @param int    $limit      每页数量
     * @param int    $start      起始偏移
     * @param string $field      查询字段
     * @param int    $addition   附加数据 (预留)
     * @param int    $totalshow  是否统计总数
     * @return array 标准列表返回格式
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1,$totalshow=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;

        // 支持 JSON 字符串格式的查询条件
        if(!is_array($where)){
            $where = json_decode($where,true);
        }

        // 计算分页偏移量
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        // 统计总数
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }

        // 查询列表数据
        $list = Db::name('Msg')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        // 数据后处理 (当前为空，可扩展)
        foreach($list as $k=>$v){

        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * ============================================================
     * 获取单条消息详情
     * ============================================================
     *
     * 【功能说明】
     * 根据条件获取单条验证码消息记录
     * 主要用于验证用户输入的验证码
     *
     * @param array  $where 查询条件 (通常包含 user_id, msg_code, msg_type, msg_time)
     * @param string $field 查询字段
     * @return array 包含 code, msg, info 的结果数组
     */
    public function infoData($where,$field='*')
    {
        // 参数验证
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
     * 保存消息记录
     * ============================================================
     *
     * 【功能说明】
     * 新增或更新验证码消息记录
     * 发送验证码后调用此方法保存记录
     *
     * 【数据字段】
     * - user_id    : 用户ID
     * - msg_type   : 消息类型 (1=绑定/2=找回/3=注册)
     * - msg_status : 状态
     * - msg_to     : 接收地址 (邮箱或手机号)
     * - msg_code   : 验证码
     * - msg_content: 消息内容
     * - msg_time   : 发送时间
     *
     * @param array $data 消息数据
     * @return array 操作结果
     */
    public function saveData($data)
    {
        // 数据验证
        $validate = \think\Loader::validate('Msg');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // 更新操作 (有 msg_id)
        if(!empty($data['msg_id'])){
            $where=[];
            $where['msg_id'] = ['eq',$data['msg_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        // 新增操作
        else{
            $data['msg_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }

        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除消息记录
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
     * 更新指定字段值
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