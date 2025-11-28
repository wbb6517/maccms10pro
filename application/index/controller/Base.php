<?php
/**
 * ============================================================
 * 前台基础控制器 (Frontend Base Controller)
 * ============================================================
 *
 * 【文件说明】
 * 前台所有控制器的基类，负责:
 * - 访问权限检查 (IP限制、网站状态、浏览器检测)
 * - 模板变量初始化 (站点信息、用户信息)
 * - 用户权限验证 (观看、下载、积分扣除)
 *
 * 【继承关系】
 * 前台控制器 → Base → All → think\Controller
 *
 * 【构造函数执行流程】
 *
 * ┌────────────────────────────────────────────────────────────────┐
 * │  Base::__construct()                                           │
 * ├────────────────────────────────────────────────────────────────┤
 * │  1. parent::__construct() - 调用 All 控制器构造函数             │
 * │     - 获取来源URL: $_ref                                       │
 * │     - 获取控制器名: $_cl                                        │
 * │     - 获取方法名: $_ac                                          │
 * │     - 设置日期戳: $_tsp                                         │
 * ├────────────────────────────────────────────────────────────────┤
 * │  2. check_ip_limit() - IP地域限制                              │
 * │     配置: site.mainland_ip_limit                               │
 * │     - 0: 不限制                                                 │
 * │     - 1: 只允许中国大陆IP                                       │
 * │     - 2: 不允许中国大陆IP                                       │
 * ├────────────────────────────────────────────────────────────────┤
 * │  3. check_site_status() - 网站开关检查                          │
 * │     配置: site.site_status                                     │
 * │     - 0: 网站关闭，显示维护页面                                  │
 * │     - 1: 网站正常运行                                           │
 * ├────────────────────────────────────────────────────────────────┤
 * │  4. label_maccms() - 初始化模板全局变量                          │
 * │     设置 $maccms 变量供模板使用                                  │
 * │     包含: 站点名称、LOGO、SEO、路径等                            │
 * ├────────────────────────────────────────────────────────────────┤
 * │  5. check_browser_jump() - 浏览器检测                           │
 * │     配置: app.browser_junmp                                    │
 * │     检测微信/QQ内置浏览器，提示用户使用外部浏览器                  │
 * ├────────────────────────────────────────────────────────────────┤
 * │  6. label_user() - 用户信息初始化                               │
 * │     - 检查Cookie登录状态                                        │
 * │     - 设置 $user 变量供模板使用                                  │
 * │     - 设置 $GLOBALS['user'] 供全局使用                          │
 * └────────────────────────────────────────────────────────────────┘
 *
 * 【用户权限体系】
 *
 * ┌───────────┬─────────────────────────────────────────────────────┐
 * │ 权限类型   │ 说明                                                │
 * ├───────────┼─────────────────────────────────────────────────────┤
 * │ popedom=1 │ 浏览权限 - 是否能查看分类列表                         │
 * │ popedom=2 │ 详情权限 - 是否能查看详情页                           │
 * │ popedom=3 │ 播放权限 - 是否能观看视频                             │
 * │ popedom=4 │ 下载权限 - 是否能下载视频                             │
 * │ popedom=5 │ 试看权限 - 是否有试看资格                             │
 * └───────────┴─────────────────────────────────────────────────────┘
 *
 * 【用户组体系】
 *
 * ┌────────────┬─────────────────────────────────────────────────────┐
 * │ group_id   │ 说明                                                │
 * ├────────────┼─────────────────────────────────────────────────────┤
 * │     1      │ 游客 - 未登录用户                                    │
 * │     2      │ 普通会员 - 已注册登录用户                             │
 * │   >=3      │ VIP会员 - 付费用户，享有更多权限                      │
 * └────────────┴─────────────────────────────────────────────────────┘
 *
 * 【相关文件】
 * - application/common/controller/All.php   : 公共控制器父类
 * - application/common/model/Group.php      : 用户组模型
 * - application/common/model/Ulog.php       : 用户日志模型 (积分记录)
 * - extend/ip_limit/IpLocationQuery.php     : IP地域查询类
 * - template/{tpl}/public/close.html        : 网站关闭页面
 * - template/{tpl}/public/browser.html      : 浏览器提示页面
 *
 * ============================================================
 */

namespace app\index\controller;
use think\Controller;
use app\common\controller\All;
use ip_limit\IpLocationQuery;

class Base extends All
{
    // ============================================================
    // 【成员变量】
    // ============================================================

    /**
     * @var array 当前用户所属用户组信息
     * 在 check_user_popedom() 中使用
     */
    var $_group;

    /**
     * @var array 当前登录用户信息
     * 由 label_user() 初始化，存储在 $GLOBALS['user']
     */
    var $_user;

    /**
     * ============================================================
     * 构造函数 - 前台页面初始化入口
     * ============================================================
     *
     * 【执行顺序】
     * 1. 调用父类 All::__construct() 初始化基础变量
     * 2. 执行各种访问检查和变量初始化
     *
     * 【检查流程说明】
     * - 任何检查失败都会直接 die() 终止请求
     * - 检查顺序经过优化，先执行成本低的检查
     */
    public function __construct()
    {
        // 调用 All 控制器构造函数
        // 初始化: $_ref, $_cl, $_ac, $_tsp
        parent::__construct();

        // ① IP地域限制检查 - 根据配置限制特定地区访问
        $this->check_ip_limit();

        // ② 网站状态检查 - 网站关闭时显示维护页面
        $this->check_site_status();

        // ③ 初始化模板全局变量 $maccms - 包含站点信息、SEO配置等
        $this->label_maccms();

        // ④ 浏览器检测 - 检测微信/QQ内置浏览器并提示
        $this->check_browser_jump();

        // ⑤ 用户信息初始化 - 检查登录状态，设置 $user 变量
        $this->label_user();
    }

    /**
     * ============================================================
     * IP地域限制检查
     * ============================================================
     *
     * 【功能说明】
     * 根据配置限制特定地区的用户访问
     * 使用 GeoIP2 库进行IP地理位置查询
     *
     * 【配置项】
     * site.mainland_ip_limit:
     * - "0": 不限制 (默认)
     * - "1": 只允许中国大陆IP访问
     * - "2": 不允许中国大陆IP访问
     *
     * 【使用场景】
     * - 版权限制: 某些内容只能在特定地区访问
     * - 合规要求: 根据法规限制访问地区
     *
     * 【依赖】
     * - extend/ip_limit/IpLocationQuery.php : IP查询类
     * - GeoIP2 数据库
     */
    protected function check_ip_limit()
    {
        // 获取IP限制配置
        // 配置路径: maccms.site.mainland_ip_limit
        $mainland_ip_limit = $GLOBALS['config']['site']['mainland_ip_limit'] ?? "0";

        // 如果为0，不限制，直接通过
        if ($mainland_ip_limit == "0") {
            return;
        }

        // 获取用户真实IP
        // mac_get_client_ip() 会尝试获取代理后的真实IP
        $user_ip = mac_get_client_ip();

        try {
            // 创建IP查询实例
            $ipQuery = new IpLocationQuery();

            // 查询IP所属省份/地区
            // 返回空字符串表示非中国大陆IP
            $country_code = $ipQuery->queryProvince($user_ip);

            // 根据配置进行限制
            if ($mainland_ip_limit == "1") {
                // 只允许中国大陆IP
                // country_code 为空表示非大陆IP，拒绝访问
                if ($country_code === "") {
                    echo $this->fetch('public/close');
                    die;
                }
            } elseif ($mainland_ip_limit == "2") {
                // 不允许中国大陆IP
                // country_code 非空表示大陆IP，拒绝访问
                if ($country_code !== "") {
                    echo $this->fetch('public/close');
                    die;
                }
            }

        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            // 局域网IP或无效IP，直接通过
            // 例如: 127.0.0.1, 192.168.x.x 等
            return;
        } catch (\Exception $e) {
            // 其他异常 (如数据库文件缺失)，直接通过
            // 避免因IP查询失败导致网站不可用
            return;
        }
    }

    /**
     * ============================================================
     * 空方法处理 - 404页面
     * ============================================================
     *
     * 【功能说明】
     * 当请求的方法不存在时，ThinkPHP会自动调用此方法
     * 返回404错误页面，2秒后跳转到首页
     *
     * 【触发条件】
     * 访问不存在的URL，如: /index/xxx/yyy
     */
    public function _empty()
    {
        // 设置HTTP状态码为404
        header("HTTP/1.0 404 Not Found");

        // 2秒后自动跳转到首页
        echo  '<script>setTimeout(function (){location.href="'.MAC_PATH.'";},'.(2000).');</script>';

        // 获取404提示文字
        $msg = lang('page_not_found');

        // 抛出404异常，显示错误页面
        abort(404,$msg);
        exit;
    }

    /**
     * ============================================================
     * 展示页访问检查
     * ============================================================
     *
     * 【功能说明】
     * 检查展示功能是否开启，以及是否需要验证码
     *
     * 【配置项】
     * - app.show: 展示功能开关 (0=关闭, 1=开启)
     * - app.show_verify: 展示页验证码 (0=不需要, 1=需要)
     *
     * @param int $aj AJAX请求标识 (1=AJAX, 0=普通请求)
     */
    protected function check_show($aj=0)
    {
        // 检查展示功能是否开启
        if($GLOBALS['config']['app']['show'] ==0){
            echo $this->error(lang('show_close'));
            exit;
        }

        // 检查是否需要验证码 (非AJAX请求时)
        if($GLOBALS['config']['app']['show_verify'] ==1 && $aj==0){
            // 检查Session中是否已验证
            if(empty(session('show_verify'))){
                // 未验证，显示验证码页面
                mac_no_cahche();  // 禁用页面缓存
                $this->assign('type','show');
                echo $this->label_fetch('public/verify');
                exit;
            }
        }
    }

    /**
     * ============================================================
     * AJAX页面访问检查
     * ============================================================
     *
     * 【功能说明】
     * 检查AJAX分页功能是否开启
     *
     * 【配置项】
     * app.ajax_page: AJAX分页开关 (0=关闭, 1=开启)
     */
    protected function check_ajax()
    {
        if($GLOBALS['config']['app']['ajax_page'] ==0){
            echo $this->error(lang('ajax_close'));
            exit;
        }
    }

    /**
     * ============================================================
     * 搜索功能检查
     * ============================================================
     *
     * 【功能说明】
     * 检查搜索功能是否开启，以及搜索频率限制和验证码
     *
     * 【配置项】
     * - app.search: 搜索功能开关 (0=关闭, 1=开启)
     * - app.search_timespan: 搜索间隔时间 (秒)
     * - app.search_verify: 搜索验证码 (0=不需要, 1=需要)
     *
     * 【防护措施】
     * - 搜索频率限制: 防止恶意刷搜索
     * - 搜索验证码: 防止机器人爬取
     *
     * @param array $param 搜索参数 (包含page等)
     * @param int $aj AJAX请求标识
     */
    protected function check_search($param,$aj=0)
    {
        // 检查搜索功能是否开启
        if($GLOBALS['config']['app']['search'] ==0){
            echo $this->error(lang('search_close'));
            exit;
        }

        // 搜索频率限制 (仅第一页生效)
        // 防止短时间内大量搜索请求
        if($param['page']==1 && mac_get_time_span("last_searchtime") < $GLOBALS['config']['app']['search_timespan']){
            echo $this->error(lang('search_frequently')."".$GLOBALS['config']['app']['search_timespan']."".lang('seconds'));
            exit;
        }

        // 搜索验证码检查 (非AJAX请求时)
        if($GLOBALS['config']['app']['search_verify'] ==1 && $aj ==0){
            if(empty(session('search_verify'))){
                mac_no_cahche();
                $this->assign('type','search');
                echo $this->label_fetch('public/verify');
                exit;
            }
        }
    }

    /**
     * ============================================================
     * 网站状态检查
     * ============================================================
     *
     * 【功能说明】
     * 检查网站是否处于关闭/维护状态
     *
     * 【配置项】
     * - site.site_status: 网站状态 (0=关闭, 1=开启)
     * - site.site_close_tip: 关闭时的提示文字
     *
     * 【使用场景】
     * - 网站维护升级时临时关闭
     * - 紧急情况下关闭网站
     */
    protected function check_site_status()
    {
        // 网站状态为0时显示关闭页面
        if ($GLOBALS['config']['site']['site_status'] == 0) {
            // 传递关闭提示文字到模板
            $this->assign('close_tip',$GLOBALS['config']['site']['site_close_tip']);
            // 渲染关闭页面
            echo $this->fetch('public/close');
            die;
        }
    }

    /**
     * ============================================================
     * 浏览器检测与跳转提示
     * ============================================================
     *
     * 【功能说明】
     * 检测微信/QQ内置浏览器，提示用户使用外部浏览器打开
     * 因为内置浏览器可能有播放限制或功能不全
     *
     * 【配置项】
     * app.browser_junmp: 浏览器跳转检测 (0=不检测, 1=检测)
     *
     * 【检测方式】
     * 通过 User-Agent 判断:
     * - QQ/: QQ内置浏览器
     * - MicroMessenger: 微信内置浏览器
     */
    protected function check_browser_jump()
    {
        // 仅前台入口(index)且开启检测时执行
        if (ENTRANCE=='index' && $GLOBALS['config']['app']['browser_junmp'] == 1) {
            // 获取浏览器User-Agent
            $agent = $_SERVER['HTTP_USER_AGENT'];

            // 检测QQ或微信内置浏览器
            if(strpos($agent, 'QQ/')||strpos($agent, 'MicroMessenger')!==false){
                // 显示浏览器提示页面
                // 提示用户点击右上角用浏览器打开
                echo $this->fetch('public/browser');
                die;
            }
        }
    }

    /**
     * ============================================================
     * 用户权限检查 (核心权限方法)
     * ============================================================
     *
     * 【功能说明】
     * 检查当前用户是否有权限执行特定操作
     * 涉及: 浏览、详情、播放、下载、试看等权限
     *
     * 【权限检查流程】
     *
     * ┌────────────────────────────────────────────────────────────┐
     * │  1. 获取用户所属用户组                                       │
     * │     用户可属于多个组，取权限最高的                            │
     * ├────────────────────────────────────────────────────────────┤
     * │  2. 检查用户组对该分类+操作的权限                             │
     * │     group_popedom[type_id][popedom]                        │
     * ├────────────────────────────────────────────────────────────┤
     * │  3. 检查是否需要积分                                         │
     * │     - VIP用户(group_id>=3): 通常免积分                      │
     * │     - 普通用户: 需要检查积分消费记录                          │
     * ├────────────────────────────────────────────────────────────┤
     * │  4. 检查积分消费记录 (Ulog表)                                │
     * │     如果已消费过，则不再扣积分                                │
     * │     如果未消费，提示需要支付积分                              │
     * └────────────────────────────────────────────────────────────┘
     *
     * 【权限类型说明】
     * - popedom=1: 浏览权限 (分类列表页)
     * - popedom=2: 详情权限 (详情页)
     * - popedom=3: 播放权限 (播放页)
     * - popedom=4: 下载权限 (下载页)
     * - popedom=5: 试看权限 (试看功能)
     *
     * 【返回码说明】
     * - code=1: 有权限
     * - code=1001: 无浏览权限
     * - code=3001: 无播放权限
     * - code=3002: 试看中
     * - code=3003: 需支付积分
     * - code=4001: 付费内容
     * - code=4003: 需支付下载积分
     * - code=5001: 试看结束，需支付积分
     * - code=5002: 积分不足
     *
     * @param int $type_id 分类ID
     * @param int $popedom 权限类型 (1-5)
     * @param array $param URL参数 (id, sid, nid, page等)
     * @param string $flag 操作标识 (play/down/art/actor/website)
     * @param array $info 内容详情 (用于获取积分配置)
     * @param int $trysee 试看时长 (秒)
     * @return array ['code'=>状态码, 'msg'=>提示信息, ...]
     */
    protected function check_user_popedom($type_id,$popedom,$param=[],$flag='',$info=[],$trysee=0)
    {
        // 获取当前用户信息
        $user = $GLOBALS['user'];

        // 用户可能属于多个用户组，获取所有组ID
        $group_ids = explode(',', $user['group_id']);

        // 获取所有用户组的缓存数据
        $group_list = model('Group')->getCache();

        // ============================================================
        // 【第1步】检查用户组是否有该分类+操作的权限
        // ============================================================
        $res = false;
        foreach($group_ids as $group_id) {
            if(!isset($group_list[$group_id])) {
                continue;
            }
            $group = $group_list[$group_id];

            // 检查权限配置
            // group_type: 组可访问的分类列表 (逗号分隔)
            // group_popedom[type_id][popedom]: 对特定分类的特定操作权限
            if(strpos(','.$group['group_type'],','.$type_id.',')!==false && !empty($group['group_popedom'][$type_id][$popedom])!==false){
                $res = true;
                break;
            }
        }

        // ============================================================
        // 【第2步】确定内容类型和积分字段
        // ============================================================
        $pre = $flag;
        $col = 'detail';
        if($flag=='play' || $flag=='down'){
            $pre = 'vod';
            $col = $flag;
        }

        // 获取该内容的积分设置
        // 例如: vod_points_play, vod_points_down, art_points_detail
        if(in_array($pre,['art','vod','actor','website'])){
            $points = $info[$pre.'_points_'.$col];
            // 如果配置为按内容收费(type=1)，使用通用积分字段
            if($GLOBALS['config']['user'][$pre.'_points_type']=='1'){
                $points = $info[$pre.'_points'];
            }
        }

        // ============================================================
        // 【第3步】根据配置和权限类型进行详细检查
        // ============================================================

        // 用户系统关闭时，跳过所有权限检查
        if($GLOBALS['config']['user']['status']==0){
            // 不做任何处理，直接通过
        }
        // 详情页权限检查 (文章、演员、网站)
        elseif($popedom==2 && in_array($pre,['art','actor','website'])){
            $has_permission = false;
            $has_trysee = false;

            // 检查用户组权限
            foreach($group_ids as $group_id) {
                if(!isset($group_list[$group_id])) {
                    continue;
                }
                $group = $group_list[$group_id];
                if(!empty($group['group_popedom'][$type_id][2])) {
                    $has_permission = true;
                }
                if($trysee > 0) {
                    $has_trysee = true;
                }
            }

            // 无权限处理
            if($res===false){
                if($has_trysee){
                    return ['code'=>1,'msg'=>lang('controller/in_try_see'),'trysee'=>$trysee];
                }
                return ['code'=>3001,'msg'=>lang('controller/no_popedom'),'trysee'=>0];
            }

            // 普通用户(group_id<3)且需要积分时，检查是否已支付
            if(max($group_ids)<3 && $points>0){
                $mid = mac_get_mid($pre);  // 获取模块ID
                $where=[];
                $where['ulog_mid'] = $mid;
                $where['ulog_type'] = 1;  // 类型1=详情页访问
                $where['ulog_rid'] = $param['id'];
                $where['ulog_sid'] = $param['page'];
                $where['ulog_nid'] = 0;
                $where['user_id'] = $user['user_id'];
                $where['ulog_points'] = $points;

                // 按内容收费时不区分页码
                if($GLOBALS['config']['user'][$pre.'_points_type']=='1'){
                    $where['ulog_sid'] = 0;
                }

                // 查询消费记录
                $res = model('Ulog')->infoData($where);

                // 未支付，需要确认支付
                if($res['code'] > 1) {
                    return ['code'=>3003,'msg'=>lang('controller/pay_play_points',[$points]),'points'=>$points,'confirm'=>1,'trysee'=>0];
                }
            }
        }
        // 播放权限检查
        elseif($popedom==3){
            $has_permission = false;

            // 检查是否有试看权限 (popedom=5)
            foreach($group_ids as $group_id) {
                if(!isset($group_list[$group_id])) {
                    continue;
                }
                $group = $group_list[$group_id];
                if(!empty($group['group_popedom'][$type_id][5])) {
                    $has_permission = true;
                    break;
                }
            }

            // 无播放权限处理
            if ($res === false) {
                if ($has_permission && max($group_ids) < 3) {
                    // 有试看权限，进入试看模式
                    return ['code'=>3002,'msg'=>lang('controller/in_try_see'),'trysee'=>$trysee];
                }
                else {
                    // 完全无权限
                    return ['code'=>3001,'msg'=>lang('controller/no_popedom'),'trysee'=>0];
                }
            }

            // 普通用户且需要积分时，检查支付记录
            if(max($group_ids)<3 && $points>0){
                $where=[];
                $where['ulog_mid'] = 1;  // 视频模块
                $where['ulog_type'] = $flag=='play' ? 4 : 5;  // 4=播放, 5=下载
                $where['ulog_rid'] = $param['id'];
                $where['ulog_sid'] = $param['sid'];
                $where['ulog_nid'] = $param['nid'];
                $where['user_id'] = $user['user_id'];
                $where['ulog_points'] = $points;

                // 按内容收费时不区分集数
                if($GLOBALS['config']['user']['vod_points_type']=='1'){
                    $where['ulog_sid'] = 0;
                    $where['ulog_nid'] = 0;
                }

                $res_ulog = model('Ulog')->infoData($where);

                if($res_ulog['code'] > 1) {
                    return ['code'=>3003,'msg'=>lang('controller/pay_play_points',[$points]),'points'=>$points,'confirm'=>1,'trysee'=>0];
                }
            }
        }
        else{
            // 其他权限类型的处理
            if($res===false){
                return ['code'=>1001,'msg'=>lang('controller/no_popedom')];
            }

            // 下载权限检查 (popedom=4)
            if($popedom == 4){
                if(max($group_ids)==1 && $points>0){
                    // 游客不能下载付费内容
                    return ['code'=>4001,'msg'=>lang('controller/charge_data'),'trysee'=>0];
                }
                elseif(max($group_ids)==2 && $points>0){
                    // 普通会员检查是否已支付
                    $where=[];
                    $where['ulog_mid'] = 1;
                    $where['ulog_type'] = $flag=='play' ? 4 : 5;
                    $where['ulog_rid'] = $param['id'];
                    $where['ulog_sid'] = $param['sid'];
                    $where['ulog_nid'] = $param['nid'];
                    $where['user_id'] = $user['user_id'];
                    $where['ulog_points'] = $points;
                    if($GLOBALS['config']['user']['vod_points_type']=='1'){
                        $where['ulog_sid'] = 0;
                        $where['ulog_nid'] = 0;
                    }
                    $res = model('Ulog')->infoData($where);

                    if($res['code'] > 1) {
                        return ['code'=>4003,'msg'=>lang('controller/pay_down_points',[$points]),'points'=>$points,'confirm'=>1,'trysee'=>0];
                    }
                }
            }
            // 试看权限检查 (popedom=5)
            elseif($popedom==5){
                $has_permission = false;
                $has_trysee = false;

                foreach($group_ids as $group_id) {
                    if(!isset($group_list[$group_id])) {
                        continue;
                    }
                    $group = $group_list[$group_id];
                    if(!empty($group['group_popedom'][$type_id][3])) {
                        $has_permission = true;
                    }
                    if(!empty($group['group_popedom'][$type_id][5])) {
                        $has_trysee = true;
                    }
                }

                // 无播放权限但有试看权限的情况
                if(!$has_permission && $has_trysee && max($group_ids) < 3){
                    $where=[];
                    $where['ulog_mid'] = 1;
                    $where['ulog_type'] = $flag=='play' ? 4 : 5;
                    $where['ulog_rid'] = $param['id'];
                    $where['ulog_sid'] = $param['sid'];
                    $where['ulog_nid'] = $param['nid'];
                    $where['user_id'] = $user['user_id'];
                    $where['ulog_points'] = $points;
                    if($GLOBALS['config']['user']['vod_points_type']=='1'){
                        $where['ulog_sid'] = 0;
                        $where['ulog_nid'] = 0;
                    }
                    $res = model('Ulog')->infoData($where);

                    // 已支付积分，有完整权限
                    if($points>0 && $res['code'] == 1) {
                        return ['code'=>5001,'msg'=>lang('controller/popedom_ok')];
                    }

                    // 未支付，根据登录状态和积分余额返回不同提示
                    if ($user['user_id'] > 0) {
                        if ($points > intval($user['user_points'])) {
                            // 积分不足
                            return ['code'=>5002,'msg'=>lang('controller/not_enough_points',[$points,$user['user_points'] ]),'trysee'=>$trysee];
                        }
                        else {
                            // 积分充足，提示试看结束需支付
                            return ['code'=>5001,'msg'=>lang('controller/try_see_end',[$points, $user['user_points']]),'trysee'=>$trysee];
                        }
                    }
                    else {
                        // 未登录用户
                        if ($points > 0) {
                            return ['code'=>5002,'msg'=>lang('controller/not_enough_points',[$points,$user['user_points'] ]),'trysee'=>$trysee];
                        }
                        else {
                            return ['code'=>5001,'msg'=>lang('controller/try_see_end',[$points, $user['user_points']]),'trysee'=>$trysee];
                        }
                    }
                }
            }
        }

        // 所有检查通过，返回有权限
        return ['code'=>1,'msg'=>lang('controller/popedom_ok')];
    }
}