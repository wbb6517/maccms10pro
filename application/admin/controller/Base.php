<?php
/**
 * 后台控制器基类 (Admin Base Controller)
 * ============================================================
 *
 * 【文件说明】
 * 所有后台控制器都继承此基类，提供统一的：
 * - 登录状态检测
 * - 权限验证
 * - 公共属性和方法
 *
 * 【继承链】
 * Base → All → Controller (ThinkPHP)
 *
 * 【调用时机】
 * 当路由解析完成后，ThinkPHP 实例化控制器时会调用 __construct()
 * 例如: admin.php/vod/data → 实例化 Vod 控制器 → 先执行 Base::__construct()
 *
 * ============================================================
 */

namespace app\admin\controller;
use think\Controller;
use app\common\controller\All;
use think\Cache;
use app\common\util\Dir;

class Base extends All
{
    /**
     * 当前登录的管理员信息
     * 包含: admin_id, admin_name, admin_pwd, admin_auth 等字段
     * @var array
     */
    var $_admin;

    /**
     * 列表页分页大小
     * 来源: maccms.php → app.pagesize
     * @var int
     */
    var $_pagesize;

    /**
     * 生成静态页时每批处理数量
     * 来源: maccms.php → app.makesize
     * @var int
     */
    var $_makesize;

    /**
     * ============================================================
     * 构造函数 - 后台请求的第一道关卡
     * ============================================================
     *
     * 【执行时机】
     * 路由解析后，控制器实例化时自动调用
     *
     * 【执行流程】
     * 1. 调用父类构造函数 (All::__construct)
     * 2. 判断当前请求是否需要登录检测
     * 3. 未登录 → 重定向到登录页
     * 4. 已登录 → 权限检测
     * 5. 分配公共模板变量
     *
     * 【请求来源示例】
     * admin.php              → Index/index  (需要登录)
     * admin.php/index/login  → Index/login  (不需要登录)
     * admin.php/vod/data     → Vod/data     (需要登录+权限)
     *
     */
    public function __construct()
    {
        // ============================================================
        // 【第1步】调用父类构造函数
        // ============================================================
        // All::__construct() 会执行:
        // - 初始化 $this->_cl (当前控制器名，如 'Index', 'Vod')
        // - 初始化 $this->_ac (当前方法名，如 'login', 'data')
        // - 加载公共配置到 $GLOBALS['config']
        parent::__construct();

        // ============================================================
        // 【第2步】登录状态检测 - 白名单机制
        // ============================================================
        // 某些页面不需要登录就能访问，这里进行白名单判断

        // 【白名单1】登录页面本身
        // 控制器=Index 且 方法=login 时，跳过登录检测
        // 否则会造成死循环：未登录→跳转登录页→检测未登录→跳转登录页...
        if(in_array($this->_cl,['Index']) && in_array($this->_ac,['login'])) {
            // 访问登录页，不需要检测登录状态
            // 直接放行，继续执行 Index::login() 方法
        }
        // 【白名单2】API入口的定时任务
        // 定时任务通过 api.php 入口调用，不需要登录验证
        // ENTRANCE 常量在入口文件中定义 (admin.php 定义为 'admin')
        elseif(ENTRANCE=='api' && in_array($this->_cl,['Timming']) && in_array($this->_ac,['index'])){
            // API定时任务，不需要检测登录状态
        }
        // 【其他情况】需要登录验证
        else {
            // --------------------------------------------------------
            // 【第3步】检测登录状态
            // --------------------------------------------------------
            // 调用 Admin 模型的 checkLogin() 方法
            // 该方法检查 Session 中是否有有效的管理员登录信息
            //
            // 返回值格式:
            // - 已登录: ['code' => 1, 'msg' => 'ok', 'info' => 管理员信息数组]
            // - 未登录: ['code' => 1001, 'msg' => '请先登录']
            $res = model('Admin')->checkLogin();

            if ($res['code'] > 1) {
                // ★ 未登录 → 重定向到登录页面
                // redirect() 会发送 302 跳转响应
                // 跳转目标: admin.php/index/login
                return $this->redirect('index/login');
            }

            // --------------------------------------------------------
            // 【第4步】登录成功，保存管理员信息
            // --------------------------------------------------------
            // 将管理员信息保存到类属性，供子类控制器使用
            // $this->_admin 包含:
            // - admin_id: 管理员ID
            // - admin_name: 管理员账号
            // - admin_pwd: 密码(MD5)
            // - admin_auth: 权限列表 (如 'vod/data,vod/add,art/data')
            // - admin_status: 状态
            $this->_admin = $res['info'];

            // 从全局配置中获取分页设置
            // $GLOBALS['config'] 在 All::__construct() 中初始化
            $this->_pagesize = $GLOBALS['config']['app']['pagesize'];  // 默认20
            $this->_makesize = $GLOBALS['config']['app']['makesize'];  // 默认30

            // --------------------------------------------------------
            // 【第5步】权限检测
            // --------------------------------------------------------
            // 检查当前管理员是否有权限访问当前控制器/方法
            // Update 控制器跳过权限检测 (系统升级功能)
            //
            // check_auth() 方法逻辑:
            // - admin_id=1 (超级管理员) → 拥有所有权限
            // - 其他管理员 → 检查 admin_auth 字段是否包含当前路由
            if($this->_cl!='Update' && !$this->check_auth($this->_cl,$this->_ac)){
                // 无权限 → 返回错误提示
                return $this->error(lang('permission_denied'));
            }
        }

        // ============================================================
        // 【第6步】分配公共模板变量
        // ============================================================
        // 这些变量在所有后台模板中都可以使用

        // 当前控制器名，用于菜单高亮等
        $this->assign('cl',$this->_cl);

        // MacCMS 版本号，用于页面底部显示
        $this->assign('MAC_VERSION',config('version')['code']);
    }

    /**
     * ============================================================
     * 权限检测方法
     * ============================================================
     *
     * 【功能说明】
     * 检查当前管理员是否有权限访问指定的控制器/方法
     *
     * 【权限存储格式】
     * admin_auth 字段存储格式: 'vod/data,vod/add,vod/edit,art/data,...'
     * 多个权限用逗号分隔，格式为 '控制器/方法'
     *
     * 【默认放行的路由】
     * - index/index  (后台首页框架)
     * - index/welcome (欢迎页/仪表盘)
     * - index/logout (退出登录)
     *
     * @param string $c 控制器名 (如 'Vod', 'Art')
     * @param string $a 方法名 (如 'data', 'add', 'edit')
     * @return bool true=有权限, false=无权限
     */
    public function check_auth($c,$a)
    {
        // 统一转小写，确保匹配不区分大小写
        $c = strtolower($c);
        $a = strtolower($a);

        // 构建权限字符串
        // 追加默认放行的路由 (首页、欢迎页、退出)
        $auths = $this->_admin['admin_auth'] . ',index/index,index/welcome,index/logout,';

        // 构建当前请求的路由标识
        // 前后加逗号是为了精确匹配，避免 'vod/data' 匹配到 'vod/data2'
        $cur = ','.$c.'/'.$a.',';

        // 【超级管理员】admin_id=1 拥有所有权限
        if($this->_admin['admin_id'] =='1'){
            return true;
        }
        // 【普通管理员】检查权限列表中是否包含当前路由
        elseif(strpos($auths,$cur)===false){
            return false;  // 未找到 → 无权限
        }
        else{
            return true;   // 找到 → 有权限
        }
    }

    /**
     * ============================================================
     * 清除缓存方法
     * ============================================================
     *
     * 【功能说明】
     * 清除系统缓存，包括:
     * 1. 播放器配置缓存 (playerconfig.js)
     * 2. 运行时缓存目录 (runtime/cache, log, temp)
     * 3. ThinkPHP Cache 缓存
     *
     * 【调用时机】
     * - 后台点击"清除缓存"按钮
     * - 修改播放器配置后
     * - 系统升级后
     *
     * @return bool 始终返回 true
     */
    public function _cache_clear()
    {
        // ============================================================
        // 【仅后台入口】更新播放器配置缓存
        // ============================================================
        if(ENTRANCE=='admin') {
            // 读取播放器相关配置
            // vodplayer: 播放器配置 (application/extra/vodplayer.php)
            // voddowner: 下载器配置 (application/extra/voddowner.php)
            // vodserver: 视频源配置 (application/extra/vodserver.php)
            $vodplayer = config('vodplayer');
            $voddowner = config('voddowner');
            $vodserver = config('vodserver');

            // 构建播放器配置数组
            $player = [];
            foreach ($vodplayer as $k => $v) {
                $player[$k] = [
                    'show' => (string)$v['show'],    // 是否显示
                    'des' => (string)$v['des'],      // 描述
                    'ps' => (string)$v['ps'],        // 解析状态
                    'parse' => (string)$v['parse'], // 解析地址
                ];
            }

            // 构建下载器配置数组
            $downer = [];
            foreach ($voddowner as $k => $v) {
                $downer[$k] = [
                    'show' => (string)$v['show'],
                    'des' => (string)$v['des'],
                    'ps' => (string)$v['ps'],
                    'parse' => (string)$v['parse'],
                ];
            }

            // 构建视频源配置数组
            $server = [];
            foreach ($vodserver as $k => $v) {
                $server[$k] = [
                    'show' => (string)$v['show'],
                    'des' => (string)$v['des']
                ];
            }

            // 生成 JavaScript 配置内容
            // 这些配置会被前台播放器页面使用
            $content = 'MacPlayerConfig.player_list=' . json_encode($player) . ',MacPlayerConfig.downer_list=' . json_encode($downer) . ',MacPlayerConfig.server_list=' . json_encode($server) . ';';

            // 更新 playerconfig.js 文件
            $path = './static/js/playerconfig.js';
            if (!file_exists($path)) {
                $path .= '.bak';  // 文件不存在时使用备份文件
            }
            $fc = @file_get_contents($path);
            if(!empty($fc)){
                // 替换缓存区域的内容
                // 文件中有 //缓存开始 和 //缓存结束 标记
	            $jsb = mac_get_body($fc, '//缓存开始', '//缓存结束');
	            $fc = str_replace($jsb, "\r\n" . $content . "\r\n", $fc);
	            @fwrite(fopen('./static/js/playerconfig.js', 'wb'), $fc);
            }
        }

        // ============================================================
        // 【清除运行时目录】
        // ============================================================
        // RUNTIME_PATH 默认为 ./runtime/
        Dir::delDir(RUNTIME_PATH.'cache/');  // 数据缓存
        Dir::delDir(RUNTIME_PATH.'log/');    // 日志文件
        Dir::delDir(RUNTIME_PATH.'temp/');   // 临时文件

        // ============================================================
        // 【清除 ThinkPHP 缓存】
        // ============================================================
        // 清除通过 Cache 类存储的缓存数据
        Cache::clear();

        return true;
    }

}
