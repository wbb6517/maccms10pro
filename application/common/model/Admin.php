<?php
/**
 * ============================================================
 * 管理员模型 (Admin Model)
 * ============================================================
 *
 * 【文件说明】
 * 处理后台管理员相关的数据操作，包括:
 * - 管理员登录/登出
 * - 登录状态检测
 * - 管理员CRUD操作
 *
 * 【数据表】
 * mac_admin (管理员表)
 *
 * 【主要方法】
 * - login()      : 管理员登录
 * - logout()     : 管理员登出
 * - checkLogin() : 检查登录状态
 * - saveData()   : 新增/编辑管理员
 * - delData()    : 删除管理员
 *
 * ============================================================
 */

namespace app\common\model;
use think\Db;

class Admin extends Base {
    // ============================================================
    // 【模型配置】
    // ============================================================

    // 设置数据表名（不含前缀）
    // 实际表名: mac_admin (前缀在 database.php 中配置)
    protected $name = 'admin';

    // 禁用自动时间戳 (该表不使用 create_time/update_time)
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成配置 (该模型未使用)
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 获取器: 管理员状态文本
     * 将 admin_status 数字转换为文字显示
     *
     * @param mixed $val 原始值
     * @param array $data 当前行数据
     * @return string '禁用' 或 '启用'
     */
    public function getAdminStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['admin_status']];
    }

    /**
     * 获取管理员列表
     *
     * @param array $where 查询条件
     * @param string $order 排序规则
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function listData($where,$order,$page,$limit=20)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $total = $this->where($where)->count();
        $list = Db::name('Admin')->where($where)->order($order)->page($page)->limit($limit)->select();
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取单个管理员信息
     *
     * @param array $where 查询条件
     * @param string $field 查询字段
     * @return array
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

        // 出于安全考虑，清空密码字段
        $info['admin_pwd'] = '';
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 保存管理员数据 (新增/编辑)
     *
     * @param array $data 表单数据
     * @return array
     */
    public function saveData($data)
    {
        // 处理权限数组，转换为逗号分隔的字符串
        // 例如: ['vod/data', 'art/data'] → ',vod/data,art/data,'
        if(!empty($data['admin_auth'])){
            $data['admin_auth'] = ','.join(',',$data['admin_auth']).',';
        }
        else{
            $data['admin_auth'] = '';
        }

        // 加载验证器
        $validate = \think\Loader::validate('Admin');

        // 编辑模式 (有 admin_id)
        if(!empty($data['admin_id'])){
            if(!$validate->scene('edit')->check($data)){
                return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
            }

            // 密码为空时不更新密码字段
            if(empty($data['admin_pwd'])){
                unset($data['admin_pwd']);
            }
            else{
                // 密码 MD5 加密存储
                $data['admin_pwd'] = md5($data['admin_pwd']);
            }
            $where=[];
            $where['admin_id'] = ['eq',$data['admin_id']];
            $res = $this->where($where)->update($data);
        }
        // 新增模式
        else{
            if(!$validate->scene('edit')->check($data)){
                return ['code'=>1002,'msg'=>lang('param_err').'：'.$validate->getError() ];
            }

            // 新增时密码必须 MD5 加密
            $data['admin_pwd'] = md5($data['admin_pwd']);
            $res = $this->insert($data);
        }

        if(false === $res){
            return ['code'=>1003,'msg'=>''.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除管理员
     *
     * @param array $where 删除条件
     * @return array
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
     * @param array $where 更新条件
     * @param string $col 字段名
     * @param mixed $val 字段值
     * @return array
     */
    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->where($where)->update($data);
        if($res===false){
            return ['code'=>1002,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    /**
     * ============================================================
     * 管理员登录方法
     * ============================================================
     *
     * 【调用位置】
     * Index.php:106 → model('Admin')->login($data)
     *
     * 【执行流程】
     *
     * ┌──────────────────────────────────────────────────────────────────┐
     * │  1. 参数校验                                                      │
     * │     检查 admin_name 和 admin_pwd 是否为空                          │
     * ├──────────────────────────────────────────────────────────────────┤
     * │  2. 验证码校验 (如果后台开启了验证码)                                │
     * │     配置项: maccms.app.admin_login_verify                         │
     * │     0=关闭验证码, 1=开启验证码                                      │
     * ├──────────────────────────────────────────────────────────────────┤
     * │  3. 数据库查询                                                     │
     * │     条件: admin_name + MD5(admin_pwd) + admin_status=1            │
     * │     同时验证账号、密码、状态                                         │
     * ├──────────────────────────────────────────────────────────────────┤
     * │  4. 更新登录信息                                                   │
     * │     - admin_login_ip: 本次登录IP                                  │
     * │     - admin_login_time: 本次登录时间                               │
     * │     - admin_login_num: 登录次数+1                                 │
     * │     - admin_random: 随机令牌 (用于cookie验证)                       │
     * │     - admin_last_login_time: 记录上次登录时间                       │
     * │     - admin_last_login_ip: 记录上次登录IP                          │
     * ├──────────────────────────────────────────────────────────────────┤
     * │  5. 写入 Session                                                  │
     * │     session('admin_auth', '1')  → 登录标识                         │
     * │     session('admin_info', $row) → 管理员完整信息                    │
     * ├──────────────────────────────────────────────────────────────────┤
     * │  6. 返回结果                                                       │
     * │     成功: ['code' => 1, 'msg' => '登录成功']                        │
     * │     失败: ['code' => 1001-1004, 'msg' => '错误信息']               │
     * └──────────────────────────────────────────────────────────────────┘
     *
     * 【错误码说明】
     * - 1001: 参数错误 (账号或密码为空)
     * - 1002: 验证码错误
     * - 1003: 账号或密码错误 (或账号被禁用)
     * - 1004: 更新登录信息失败
     *
     * 【安全机制】
     * - 密码使用 MD5 加密存储和比对
     * - 登录成功后生成随机令牌 admin_random
     * - 记录登录IP和时间，便于审计
     * - Session 存储登录状态，服务端验证
     *
     * @param array $data 登录表单数据
     *   - admin_name: 管理员账号
     *   - admin_pwd: 管理员密码 (明文)
     *   - verify: 验证码 (如果开启)
     * @return array 登录结果
     *   - code: 状态码 (1=成功, >1=失败)
     *   - msg: 提示信息
     */
    public function login($data)
    {
        // ============================================================
        // 【第1步】参数校验
        // ============================================================
        // 检查账号和密码是否为空
        if(empty($data['admin_name']) || empty($data['admin_pwd'])  ) {
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        // ============================================================
        // 【第2步】验证码校验
        // ============================================================
        // 配置项: maccms.php → app.admin_login_verify
        // '0' = 关闭验证码, '1' = 开启验证码
        //
        // captcha_check() 是 ThinkPHP 内置的验证码检测函数
        // 验证码图片由 \think\captcha\CaptchaController 生成
        if($GLOBALS['config']['app']['admin_login_verify'] !='0'){
            if(!captcha_check($data['verify'])){
                return ['code'=>1002,'msg'=>lang('verify_err')];
            }
        }

        // ============================================================
        // 【第3步】数据库查询 - 验证账号密码
        // ============================================================
        // 构建查询条件:
        // - admin_name: 账号匹配
        // - admin_pwd: 密码 MD5 后匹配
        // - admin_status: 账号状态必须为 1 (启用)
        //
        // 【安全说明】
        // 密码使用 MD5 加密，虽然 MD5 不够安全，但这是历史遗留设计
        // 生产环境建议升级为 password_hash() / password_verify()
        $where=[];
        $where['admin_name'] = ['eq',$data['admin_name']];
        $where['admin_pwd'] = ['eq',md5($data['admin_pwd'])];
        $where['admin_status'] = ['eq',1];

        // 执行查询
        // 如果账号不存在、密码错误、或账号被禁用，都会返回空
        $row = $this->where($where)->find();

        // 验证失败
        if(empty($row)){
            return ['code'=>1003,'msg'=>lang('access_or_pass_err')];
        }

        // ============================================================
        // 【第4步】更新登录信息
        // ============================================================
        // 生成随机令牌 (用于 Cookie 登录验证，当前未启用)
        $random = md5(rand(10000000,99999999));

        // 构建更新数据
        $update['admin_login_ip'] = mac_get_ip_long();      // 当前登录IP (长整型)
        $update['admin_login_time'] = time();               // 当前登录时间戳
        $update['admin_login_num'] = $row['admin_login_num'] + 1;  // 登录次数累加
        $update['admin_random'] = $random;                  // 随机令牌

        // 记录上次登录信息 (用于后台显示)
        $update['admin_last_login_time'] = $row['admin_login_time'];
        $update['admin_last_login_ip'] = $row['admin_login_ip'];

        // 执行更新
        $res = $this->where($where)->update($update);
        if($res===false){
            return ['code'=>1004,'msg'=>lang('model/admin/update_login_err')];
        }

        // ============================================================
        // 【第5步】写入 Session - 保存登录状态
        // ============================================================
        // admin_auth: 登录标识，用于快速判断是否已登录
        // admin_info: 管理员完整信息，包含:
        //   - admin_id: 管理员ID
        //   - admin_name: 账号
        //   - admin_pwd: 密码(MD5)
        //   - admin_auth: 权限列表
        //   - admin_status: 状态
        //   - admin_login_ip: 登录IP
        //   - admin_login_time: 登录时间
        //   - ... 等其他字段
        session('admin_auth','1');
        session('admin_info',$row->toArray());

        // ============================================================
        // 【备用方案】Cookie 登录 (当前已注释)
        // ============================================================
        // 如果需要"记住登录状态"功能，可以启用 Cookie 存储
        // Cookie 验证逻辑在 checkLogin2() 方法中
        //
        // cookie('admin_id',$row['admin_id']);
        // cookie('admin_name',$row['admin_name']);
        // cookie('admin_check',md5($random .'-'. $row['admin_name'] .'-'.$row['admin_id'] .'-'.mac_get_client_ip() ) );

        // ============================================================
        // 【第6步】返回登录成功
        // ============================================================
        return ['code'=>1,'msg'=>lang('model/admin/login_ok')];
    }

    /**
     * ============================================================
     * 管理员登出方法
     * ============================================================
     *
     * 【功能说明】
     * 清除 Session 中的登录信息，实现退出登录
     *
     * 【调用位置】
     * Index.php:logout() → model('Admin')->logout()
     *
     * @return array 登出结果
     */
    public function logout()
    {
        // 清除 Session 中的登录信息
        session('admin_auth',null);   // 清除登录标识
        session('admin_info',null);   // 清除管理员信息

        // Cookie 方式的登出 (当前已注释)
        //cookie('admin_id',null);
        //cookie('admin_name',null);
        //cookie('admin_check',null);

        return ['code'=>1,'msg'=>lang('model/admin/logout_ok')];
    }

    /**
     * ============================================================
     * 检查登录状态 (Session 方式)
     * ============================================================
     *
     * 【功能说明】
     * 检查当前用户是否已登录，返回管理员信息
     *
     * 【调用位置】
     * Base.php:__construct() → model('Admin')->checkLogin()
     *
     * 【检测逻辑】
     * 1. 检查 session('admin_auth') 是否为 '1'
     * 2. 检查 session('admin_info') 是否存在
     * 3. 都通过则返回管理员信息
     *
     * @return array 检测结果
     *   - code: 1=已登录, >1=未登录
     *   - msg: 提示信息
     *   - info: 管理员信息 (已登录时返回)
     */
    public function checkLogin()
    {
        // 检查登录标识
        if(session('admin_auth')!=='1'){
            return ['code'=>1009,'msg'=>lang('model/admin/not_login')];
        }

        // 获取管理员信息
        $info = session('admin_info');
        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('model/admin/not_login')];
        }

        // 返回已登录状态和管理员信息
        return ['code'=>1,'msg'=>lang('model/admin/haved_login'),'info'=>$info];
    }

    /**
     * ============================================================
     * 检查登录状态 (Cookie 方式) - 备用方案
     * ============================================================
     *
     * 【功能说明】
     * 通过 Cookie 检查登录状态，用于"记住登录"功能
     * 当前默认使用 Session 方式，此方法为备用
     *
     * 【安全机制】
     * Cookie 中存储:
     * - admin_id: 管理员ID
     * - admin_name: 管理员账号
     * - admin_check: 验证签名 = MD5(random + name + id + ip)
     *
     * 验证时重新计算签名并比对，防止 Cookie 被篡改
     * 同时绑定客户端IP，换IP需重新登录
     *
     * @return array 检测结果
     */
    public function checkLogin2()
    {
        // 获取 Cookie 中的登录信息
        $admin_id = cookie('admin_id');
        $admin_name = cookie('admin_name');
        $admin_check = cookie('admin_check');

        // Cookie 为空，未登录
        if(empty($admin_id) || empty($admin_name) || empty($admin_check)){
            return ['code'=>1001, 'msg'=>lang('model/admin/not_login')];
        }

        // 查询数据库验证账号
        $where = [];
        $where['admin_id'] = $admin_id;
        $where['admin_name'] = $admin_name;
        $where['admin_status'] =1 ;

        $info = $this->where($where)->find();
        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('model/admin/not_login')];
        }
        $info = $info->toArray();

        // 验证签名
        // 签名算法: MD5(admin_random + admin_name + admin_id + client_ip)
        // 这确保 Cookie 与登录时的状态一致，且绑定客户端IP
        $login_check = md5($info['admin_random'] .'-'. $info['admin_name'] .'-'.$info['admin_id'] .'-'.mac_get_client_ip() ) ;
        if($login_check != $admin_check){
            return ['code'=>1003,'msg'=>lang('model/admin/not_login')];
        }

        return ['code'=>1,'msg'=>lang('model/admin/haved_login'),'info'=>$info];
    }

}