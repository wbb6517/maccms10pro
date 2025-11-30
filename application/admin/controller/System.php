<?php
/**
 * ============================================================
 * 系统配置控制器 (System Configuration Controller)
 * ============================================================
 *
 * 【文件说明】
 * 处理后台所有系统配置相关的功能，包括：
 * - 网站参数配置 (基本设置、性能优化、预留参数、后台设置)
 * - SEO参数配置
 * - 会员参数配置
 * - 评论留言配置
 * - 附件参数配置
 * - URL地址配置
 * - 播放器参数配置
 * - 采集参数配置
 * - 站外入库配置
 * - 开放API配置
 * - 整合登录配置
 * - 在线支付配置
 * - 微信对接配置
 * - 邮件发送配置
 * - 短信发送配置
 *
 * 【配置存储位置】
 * 所有配置统一存储在: application/extra/maccms.php
 * 使用 mac_arr2file() 函数将数组写入PHP配置文件
 *
 * 【配置读取方式】
 * $config = config('maccms');  // 读取所有配置
 * $config['site']['site_name'] // 读取具体配置项
 *
 * 【方法列表】
 * ┌─────────────────────┬────────────────────────────────────────┐
 * │ 方法名               │ 功能说明                                │
 * ├─────────────────────┼────────────────────────────────────────┤
 * │ config()            │ 网站参数配置 (核心配置页面)              │
 * │ configurl()         │ URL地址配置 (路由规则)                   │
 * │ configuser()        │ 会员参数配置                             │
 * │ configupload()      │ 附件参数配置 (上传设置)                  │
 * │ configcomment()     │ 评论留言配置                             │
 * │ configweixin()      │ 微信对接配置                             │
 * │ configpay()         │ 在线支付配置                             │
 * │ configconnect()     │ 整合登录配置 (QQ/微信登录)               │
 * │ configemail()       │ 邮件发送配置                             │
 * │ configsms()         │ 短信发送配置                             │
 * │ configapi()         │ 开放API配置                              │
 * │ configinterface()   │ 站外入库配置                             │
 * │ configcollect()     │ 采集参数配置                             │
 * │ configplay()        │ 播放器参数配置                           │
 * │ configseo()         │ SEO参数配置                              │
 * │ configlang()        │ 语言切换 (AJAX)                          │
 * │ configVersion()     │ 版本切换 (AJAX)                          │
 * │ test_email()        │ 测试邮件发送                             │
 * │ test_cache()        │ 测试缓存连接                             │
 * └─────────────────────┴────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/system/config      → 网站参数配置
 * admin.php/system/configurl   → URL地址配置
 * admin.php/system/configuser  → 会员参数配置
 * ... (其他配置页面类似)
 *
 * 【相关文件】
 * - application/extra/maccms.php      : 配置存储文件
 * - application/admin/view_new/system/: 配置页面视图模板
 * - application/common.php            : mac_arr2file() 等公共函数
 *
 * ============================================================
 */

namespace app\admin\controller;
use http\Cookie;
use think\Db;
use think\Config;
use think\Cache;
use think\View;

class System extends Base
{
    /**
     * ============================================================
     * 测试邮件发送
     * ============================================================
     *
     * 【功能说明】
     * 测试邮件配置是否正确，发送测试邮件到指定邮箱
     *
     * 【请求方式】
     * POST admin.php/system/test_email
     *
     * 【请求参数】
     * - nick    : 发件人昵称
     * - type    : 邮件类型 (phpmailer等)
     * - test    : 测试接收邮箱地址
     *
     * @return \think\response\Json 测试结果
     */
    public function test_email()
    {
        $post = input();
        $conf = [
            'nick' => $post['nick'],
        ];
        $type = strtolower($post['type']);
        $to = $post['test'];
        $conf['host'] = $GLOBALS['config']['email'][$type]['host'];
        $conf['port'] = $GLOBALS['config']['email'][$type]['port'];
        $conf['username'] = $GLOBALS['config']['email'][$type]['username'];
        $conf['password'] = $GLOBALS['config']['email'][$type]['password'];
        $conf['secure'] = $GLOBALS['config']['email'][$type]['secure'];
        $this->label_maccms();

        $title = $GLOBALS['config']['email']['tpl']['test_title'];
        $msg = $GLOBALS['config']['email']['tpl']['test_body'];
        $code = mac_get_rndstr(6,'num');
        View::instance()->assign(['code'=>$code,'time'=>$GLOBALS['config']['email']['time']]);
        $title =  View::instance()->display($title);
        $msg =  View::instance()->display($msg);
        $msg = htmlspecialchars_decode($msg);
        $res = mac_send_mail($to, $title, $msg, $conf);
        if ($res['code']==1) {
            return json(['code' => 1, 'msg' => lang('test_ok')]);
        }
        return json(['code' => 1001, 'msg' => lang('test_err').'：'.$res['msg']]);
    }

    /**
     * 测试缓存连接
     * POST admin.php/system/test_cache
     * 测试 Redis/Memcache 连接是否正常
     * @return \think\response\Json 测试结果
     */
    public function test_cache()
    {
        $param = input();

        if (!isset($param['type']) || empty($param['host']) || empty($param['port'])) {
            return $this->error(lang('param_err'));
        }

        $options = [
            'type' => $param['type'],
            'host' => $param['host'],
            'port' => $param['port'],
            'username' => $param['username'],
            'password' => $param['password']
        ];

        if ($param['type'] == 'redis' && isset($param['db']) && intval($param['db']) > 0) {
            $options['select'] = intval($param['db']);
        }

        $hd = Cache::connect($options);
        $hd->set('test', 'test');

        return json(['code' => 1, 'msg' => lang('test_ok')]);
    }

    /**
     * ============================================================
     * 网站参数配置 (核心配置页面)
     * ============================================================
     *
     * 【功能说明】
     * 这是后台最重要的配置页面，包含4个Tab标签页：
     * - 基本设置: 网站名称、域名、关键词、模板选择等
     * - 性能优化: 缓存类型(file/redis/memcache)、压缩、搜索设置等
     * - 预留参数: 自定义扩展参数 (key$$$value格式)
     * - 后台设置: 验证码、编辑器、分页大小等
     *
     * 【访问路径】
     * GET  admin.php/system/config  → 显示配置表单
     * POST admin.php/system/config  → 保存配置
     *
     * 【配置存储】
     * 保存到: application/extra/maccms.php
     * 主要配置节点: site(网站)、app(应用)、extra(扩展)
     *
     * 【模板位置】
     * application/admin/view_new/system/config.html
     *
     * @return mixed 配置页面HTML 或 JSON响应
     */
    public function config()
    {
        // ============================================================
        // 【POST请求】保存网站配置
        // ============================================================
        if (Request()->isPost()) {
            // 获取所有POST参数，使用htmlentities过滤防止XSS
            $config = input('','','htmlentities');

            // --------------------------------------------------------
            // Token验证 (防止CSRF跨站请求伪造攻击)
            // --------------------------------------------------------
            // 表单中包含隐藏字段 __token__，每次提交时验证有效性
            // 验证器定义位置: application/admin/validate/Token.php
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            // 验证通过后移除token，不需要保存到配置中
            unset($config['__token__']);

            // --------------------------------------------------------
            // 【模板广告目录检测】
            // --------------------------------------------------------
            // 每个模板可以在 info.ini 中自定义广告目录
            // 如果模板没有指定，则使用默认的 'ads' 目录
            //
            // info.ini 文件位置: template/{模板名}/info.ini
            // 配置格式: adsdir=myads
            //
            $ads_dir='ads';
            $mob_ads_dir='ads';

            // 读取PC端模板的广告目录配置
            $path = ROOT_PATH .'template/'.$config['site']['template_dir'].'/info.ini';
            $cc = Config::load($path,'ini');
            if(!empty($cc['adsdir'])){
                $ads_dir = $cc['adsdir'];
            }

            // 读取移动端模板的广告目录配置
            $path = ROOT_PATH .'template/'.$config['site']['mob_template_dir'].'/info.ini';
            $cc = Config::load($path,'ini');
            if(!empty($cc['adsdir'])){
                $mob_ads_dir = $cc['adsdir'];
            }
            $config['site']['ads_dir'] = $ads_dir;
            $config['site']['mob_ads_dir'] = $mob_ads_dir;

            // --------------------------------------------------------
            // 【缓存标识生成】
            // --------------------------------------------------------
            // cache_flag 用于前端静态资源版本控制
            // 当更改此标识时，浏览器会重新加载CSS/JS文件
            // 如果未设置，自动生成一个10位的MD5随机串
            if(empty($config['app']['cache_flag'])){
                $config['app']['cache_flag'] = substr(md5(time()),0,10);
            }

            // --------------------------------------------------------
            // 【搜索规则处理】
            // --------------------------------------------------------
            // 将前端多选框的数组值转换为管道符分隔的字符串
            // 例如: ['vod_en', 'vod_sub'] → 'vod_en|vod_sub'
            // 用于控制搜索时匹配哪些字段
            $config['app']['search_vod_rule'] = join('|', !empty($config['app']['search_vod_rule']) ? (array)$config['app']['search_vod_rule'] : []);
            $config['app']['search_art_rule'] = join('|', !empty($config['app']['search_art_rule']) ? (array)$config['app']['search_art_rule'] : []);
            $config['app']['vod_search_optimise'] = join('|', !empty($config['app']['vod_search_optimise']) ? (array)$config['app']['vod_search_optimise'] : []);
            $config['app']['vod_search_optimise_cache_minutes'] = (int)$config['app']['vod_search_optimise_cache_minutes'];

            // --------------------------------------------------------
            // 【预留参数解析】
            // --------------------------------------------------------
            // 将文本框中的自定义参数解析为关联数组
            //
            // 输入格式 (每行一个):
            //   key1$$$value1
            //   key2$$$value2
            //
            // 输出格式:
            //   ['key1' => 'value1', 'key2' => 'value2']
            //
            // 使用方式: 在模板中通过 {$maccms.extra.key1} 访问
            //
            $config['extra'] = [];
            if(!empty($config['app']['extra_var'])){
                // 将换行符统一转换为 # 作为分隔符
                $extra_var = str_replace(array(chr(10),chr(13)), array('','#'),$config['app']['extra_var']);
                $tmp = explode('#',$extra_var);
                foreach($tmp as $a){
                    // 只处理包含 $$$ 分隔符的行
                    if(strpos($a,'$$$')!==false){
                        $tmp2 = explode('$$$',$a);
                        $config['extra'][$tmp2[0]] = $tmp2[1];
                    }
                }
                unset($tmp,$tmp2);
            }

            // --------------------------------------------------------
            // 【统计代码处理】
            // --------------------------------------------------------
            // 将HTML实体还原为原始字符 (因为input时做了htmlentities)
            $config['site']['site_tj'] = html_entity_decode($config['site']['site_tj']);

            // 组装新配置数组 (只包含本页面修改的配置节点)
            $config_new['site'] = $config['site'];
            $config_new['app'] = $config['app'];
            $config_new['extra'] = $config['extra'];

            // 读取旧配置，合并新配置 (保留其他页面的配置不被覆盖)
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            // --------------------------------------------------------
            // 【统计代码写入JS文件】
            // --------------------------------------------------------
            // 将统计代码写入 static/js/tj.js
            // 如果不是 document.write 格式，自动包装
            // 前端页面通过 <script src="tj.js"> 加载统计代码
            $tj = $config_new['site']['site_tj'];
            if(strpos($tj,'document.w') ===false){
                $tj = 'document.write(\'' . str_replace("'","\'",$tj) . '\')';
            }
            $res = @fwrite(fopen('./static/js/tj.js', 'wb'), $tj);

            // --------------------------------------------------------
            // 【保存配置到文件】
            // --------------------------------------------------------
            // 使用 mac_arr2file() 将数组写入 PHP 配置文件
            // 文件路径: application/extra/maccms.php
            // 格式: return array(...);
            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        // ============================================================
        // 【GET请求】显示网站配置表单
        // ============================================================

        // --------------------------------------------------------
        // 获取可用模板列表
        // --------------------------------------------------------
        // 扫描 template 目录下的所有子目录作为模板选项
        $templates = glob('./template' . '/*', GLOB_ONLYDIR);
        foreach ($templates as $k => &$v) {
            $v = str_replace('./template/', '', $v);
        }
        $this->assign('templates', $templates);

        // --------------------------------------------------------
        // 获取可用语言列表
        // --------------------------------------------------------
        // 扫描 application/lang 目录下的 PHP 文件
        // 例如: zh-cn.php, en-us.php
        $langs = glob('./application/lang/*.php');
        foreach ($langs as $k => &$v) {
            $v = str_replace(['./application/lang/','.php'],['',''],$v);
        }
        $this->assign('langs', $langs);

        // --------------------------------------------------------
        // 获取用户组列表
        // --------------------------------------------------------
        // 用于"新用户默认组"下拉选项
        $usergroup = Db::name('group')->select();
        $this->assign('usergroup', $usergroup);

        // --------------------------------------------------------
        // 获取可用编辑器列表
        // --------------------------------------------------------
        // 从 extend/editor 目录获取编辑器扩展
        // 例如: Ueditor, Ckeditor, Markdown 等
        $editors = mac_extends_list('editor');
        $this->assign('editors',$editors);

        // --------------------------------------------------------
        // 读取并处理当前配置
        // --------------------------------------------------------
        $config = config('maccms');
        // 设置默认输入类型 (1=get+post)
        if (!isset($config['app']['input_type'])) {
            $config['app']['input_type'] = 1;
        }
        // 获取搜索优化缓存时间
        $config['app']['vod_search_optimise_cache_minutes'] = model('VodSearch')->getResultCacheMinutes($config);

        $this->assign('config', $config);
        $this->assign('title', lang('admin/system/config/title'));

        // 渲染配置表单页面
        return $this->fetch('admin@system/config');
    }


    /**
     * URL地址配置 - 路由规则设置
     * 配置伪静态规则、URL重写、静态化路径等
     * 保存时会同时写入 route.php 路由规则文件
     * 配置节点: view, path, rewrite
     */
    public function configurl()
    {
        if (Request()->isPost()) {
            $config = input();

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['view'] = $config['view'];
            $config_new['path'] = $config['path'];
            $config_new['rewrite'] = $config['rewrite'];

            //写路由规则文件
            $route = [];
            $route['__pattern__'] = [

                'id'=>'[\s\S]*?',
                'ids'=>'[\s\S]*?',
                'wd' => '[\s\S]*',
                'en'=>'[\s\S]*?',
                'state' => '[\s\S]*?',
                'area' => '[\s\S]*',
                'year'=>'[\s\S]*?',
                'lang' => '[\s\S]*?',
                'letter'=>'[\s\S]*?',
                'actor' => '[\s\S]*?',
                'director' => '[\s\S]*?',
                'tag' => '[\s\S]*?',
                'class' => '[\s\S]*?',
                'order'=>'[\s\S]*?',
                'by'=>'[\s\S]*?',
                'file'=>'[\s\S]*?',
                'name'=>'[\s\S]*?',
                'url'=>'[\s\S]*?',
                'type'=>'[\s\S]*?',
                'sex' => '[\s\S]*?',
                'version' => '[\s\S]*?',
                'blood' => '[\s\S]*?',
                'starsign' => '[\s\S]*?',
                'page'=>'\d+',
                'ajax'=>'\d+',
                'tid'=>'\d+',
                'mid'=>'\d+',
                'rid'=>'\d+',
                'pid'=>'\d+',
                'sid'=>'\d+',
                'nid'=>'\d+',
                'uid'=>'\d+',
                'level'=>'\d+',
                'score'=>'\d+',
                'limit'=>'\d+',
            ];
            $rows = explode(chr(13), str_replace(chr(10), '', $config['rewrite']['route']));
            foreach ($rows as $r) {
                if (strpos($r, '=>') !== false) {
                    $a = explode('=>', $r);
                    $rule = [];
//                    if (strpos($a, ':id') !== false) {
                        //$rule['id'] = '\w+';
//                    }
                    $route[trim($a[0])] = [trim($a[1]), [], $rule];
                }
            }

            $res = mac_arr2file(APP_PATH . 'route.php', $route);
            if ($res === false) {
                return $this->error(lang('write_err_route'));
            }

            //写扩展配置
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);
            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('write_err_config'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configurl/title'));
        return $this->fetch('admin@system/configurl');
    }

    /**
     * 会员参数配置 - 用户系统设置
     * 配置会员注册、登录、积分、提现等规则
     * 配置节点: user
     *
     * 【主要配置项】
     * - 注册开关、审核方式、验证码
     * - 手机/邮箱验证
     * - 积分奖励规则 (注册、邀请)
     * - 提现设置 (比例、最低金额)
     * - 头像上传设置
     */
    public function configuser()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['user'] = $config['user'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configuser/title'));
        return $this->fetch('admin@system/configuser');
    }

    /**
     * 附件参数配置 - 文件上传设置
     * 配置图片上传、远程存储 (FTP/七牛云/又拍云/微博/AWS S3)
     * 配置节点: upload
     *
     * 【主要配置项】
     * - 本地存储/远程存储模式切换
     * - 缩略图、水印设置
     * - FTP/七牛云/又拍云/微博等第三方存储配置
     * - AWS S3 存储需要 aws.phar 扩展支持
     */
    public function configupload()
    {
        $phar_status = file_exists(ROOT_PATH . 'extend/aws/src/Aws/aws.phar');
        if (Request()->isPost()){
            $config = input('','','htmlentities');
            if($config['upload']['mode'] == 'S3' && $phar_status == false){
                return $this->error(lang('save_err'));
            }

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['upload'] = $config['upload'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        if ($phar_status) {
            $aws_phar = 'Yes';
        }else{
            $aws_phar = 'No';
        }
        $this->assign('aws_phar',$aws_phar);
        $extends = mac_extends_list('upload');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configupload/title'));
        return $this->fetch('admin@system/configupload');
    }

    /**
     * 评论留言配置 - 互动功能设置
     * 配置评论和留言板的审核、验证码、分页等规则
     * 配置节点: gbook(留言板), comment(评论)
     */
    public function configcomment()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['gbook'] = $config['gbook'];
            $config_new['comment'] = $config['comment'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configcomment/title'));
        return $this->fetch('admin@system/configcomment');
    }

    /**
     * 微信对接配置 - 公众号设置
     * 配置微信公众号Token、关键词回复、搜索对接等
     * 配置节点: weixin
     */
    public function configweixin()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['weixin'] = $config['weixin'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configweixin/title'));
        return $this->fetch('admin@system/configweixin');
    }

    /**
     * 在线支付配置 - 支付方式设置
     * 配置支付宝、微信支付、第三方支付接口等
     * 配置节点: pay
     *
     * 【主要配置项】
     * - 充值最低金额、积分兑换比例
     * - 支付宝支付 (账号、AppID、AppKey)
     * - 微信支付 (AppID、商户号、密钥)
     * - 第三方支付扩展 (CodePay、ZhaPay等)
     *
     * 【扩展支付】
     * mac_extends_list('pay') 获取 extend/pay 目录下的支付扩展
     */
    public function configpay()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['pay'] = $config['pay'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('http_type',$GLOBALS['http_type']);
        $this->assign('config', config('maccms'));

        $extends = mac_extends_list('pay');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configpay/title'));
        return $this->fetch('admin@system/configpay');
    }

    /**
     * 整合登录配置 - 第三方登录设置
     * 配置QQ登录、微信登录等OAuth第三方授权登录
     * 配置节点: connect
     *
     * 【主要配置项】
     * - QQ登录 (AppKey、AppSecret)
     * - 微信登录 (AppKey、AppSecret)
     *
     * 【使用说明】
     * 需要先在各平台创建应用获取密钥
     * QQ互联: https://connect.qq.com/
     * 微信开放平台: https://open.weixin.qq.com/
     */
    public function configconnect()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['connect'] = $config['connect'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configconnect/title'));
        return $this->fetch('admin@system/configconnect');
    }

    /**
     * 邮件发送配置 - SMTP邮件设置
     * 配置邮件服务器、邮件模板等
     * 配置节点: email
     *
     * 【主要配置项】
     * - 发送方式 (PHPMailer等)
     * - SMTP服务器 (host、port、secure)
     * - 发件人账号密码
     * - 邮件模板 (注册、找回密码、绑定等)
     *
     * 【扩展邮件】
     * mac_extends_list('email') 获取 extend/email 目录下的邮件扩展
     */
    public function configemail()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['email'] = $config['email'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        $this->assign('config', config('maccms'));

        $extends = mac_extends_list('email');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configemail/title'));
        return $this->fetch('admin@system/configemail');
    }

    /**
     * 短信发送配置 - 短信通道设置
     * 配置阿里云、腾讯云等短信服务
     * 配置节点: sms
     *
     * 【主要配置项】
     * - 短信签名
     * - 短信模板ID (注册、绑定、找回密码)
     * - 阿里云短信 (AppID、AppKey)
     * - 腾讯云短信 (AppID、AppKey)
     *
     * 【扩展短信】
     * mac_extends_list('sms') 获取 extend/sms 目录下的短信扩展
     */
    public function configsms()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);


            $config_new['sms'] = $config['sms'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        $this->assign('config', config('maccms'));

        $extends = mac_extends_list('sms');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configsms/title'));
        return $this->fetch('admin@system/configsms');
    }

    /**
     * 开放API配置 - 数据接口设置
     * 配置对外提供的数据API接口权限
     * 配置节点: api
     *
     * 【主要配置项】
     * - 视频API (vod) - 开关、分页、授权域名
     * - 文章API (art) - 开关、分页、授权域名
     * - 演员API (actor) - 开关、分页、授权域名
     * - 漫画API (manga) - 开关、分页、授权域名
     * - 公共API (publicapi) - 开关、授权域名
     *
     * 【授权说明】
     * auth字段使用 # 分隔授权域名列表
     * mac_replace_text() 函数用于文本格式转换
     */
    public function configapi()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['api'] = $config['api'];

            $config_new['api']['vod']['auth'] = mac_replace_text($config_new['api']['vod']['auth'], 2);
            $config_new['api']['art']['auth'] = mac_replace_text($config_new['api']['art']['auth'], 2);
            $config_new['api']['actor']['auth'] = mac_replace_text($config_new['api']['actor']['auth'], 2);
            $config_new['api']['manga']['auth'] = mac_replace_text($config_new['api']['manga']['auth'], 2);
            $config_new['api']['publicapi']['auth'] = mac_replace_text($config_new['api']['publicapi']['auth'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        $config = config('maccms');
        if(!isset($config['api']['publicapi'])){
            $config['api']['publicapi'] = [
                'status' => '0',
                'charge' => '0',
                'auth' => '',
            ];
        }
        if(!isset($config['api']['manga'])){
            $config['api']['manga'] = [
                'status' => '0',
                'charge' => '0',
                'pagesize' => '20',
                'imgurl' => '',
                'typefilter' => '',
                'datafilter' => 'manga_status=1',
                'cachetime' => '',
                'auth' => '',
            ];
        }
        $this->assign('config',$config );
        $this->assign('title', lang('admin/system/configapi/title'));
        return $this->fetch('admin@system/configapi');
    }

    /**
     * 站外入库配置 - 外部数据推送设置
     * 配置接收外部站点数据推送的接口
     * 配置节点: interface
     *
     * 【主要配置项】
     * - 开关状态
     * - 通信密钥 (至少16位)
     * - 分类映射 (外部分类 => 本站分类)
     *
     * 【使用场景】
     * 允许外部站点通过API向本站推送视频/文章数据
     * 需要配置分类对应关系
     */
    public function configinterface()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            if($config['interface']['status']==1 && strlen($config['interface']['pass']) < 16){
                return $this->error(lang('admin/system/configinterface/pass_check'));
            }

            $config_new['interface'] = $config['interface'];
            $config_new['interface']['vodtype'] = mac_replace_text($config_new['interface']['vodtype'], 2);
            $config_new['interface']['arttype'] = mac_replace_text($config_new['interface']['arttype'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }

            //保存缓存
            mac_interface_type();

            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configinterface/title'));
        return $this->fetch('admin@system/configinterface');
    }

    /**
     * 采集参数配置 - 资源采集设置
     * 配置采集入库规则、数据过滤、同义词等
     * 配置节点: collect
     *
     * 【支持的内容类型】
     * - vod (视频) - 入库规则、更新规则、过滤词等
     * - art (文章) - 入库规则、更新规则、过滤词等
     * - actor (演员) - 入库规则、更新规则
     * - role (角色) - 入库规则、更新规则
     * - website (网站) - 入库规则、更新规则
     * - comment (评论) - 入库规则、更新规则
     * - manga (漫画) - 入库规则、更新规则
     *
     * 【规则说明】
     * - inrule: 入库时执行的规则
     * - uprule: 更新时执行的规则
     * - thesaurus: 同义词替换
     * - words: 违禁词过滤
     */
    public function configcollect()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['collect'] = $config['collect'];
            if (empty($config_new['collect']['vod']['inrule'])) {
                $config_new['collect']['vod']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['vod']['uprule'])) {
                $config_new['collect']['vod']['uprule'] = [];
            }
            if (empty($config_new['collect']['art']['inrule'])) {
                $config_new['collect']['art']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['art']['uprule'])) {
                $config_new['collect']['art']['uprule'] = [];
            }
            if (empty($config_new['collect']['actor']['inrule'])) {
                $config_new['collect']['actor']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['actor']['uprule'])) {
                $config_new['collect']['actor']['uprule'] = [];
            }
            if (empty($config_new['collect']['role']['inrule'])) {
                $config_new['collect']['role']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['role']['uprule'])) {
                $config_new['collect']['role']['uprule'] = [];
            }
            if (empty($config_new['collect']['website']['inrule'])) {
                $config_new['collect']['website']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['website']['uprule'])) {
                $config_new['collect']['website']['uprule'] = [];
            }
            if (empty($config_new['collect']['comment']['inrule'])) {
                $config_new['collect']['comment']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['comment']['uprule'])) {
                $config_new['collect']['comment']['uprule'] = [];
            }
            if (empty($config_new['collect']['manga']['inrule'])) {
                $config_new['collect']['manga']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['manga']['uprule'])) {
                $config_new['collect']['manga']['uprule'] = [];
            }

            $config_new['collect']['vod']['inrule'] = ',' . join(',', $config_new['collect']['vod']['inrule']);
            $config_new['collect']['vod']['uprule'] = ',' . join(',', $config_new['collect']['vod']['uprule']);
            $config_new['collect']['art']['inrule'] = ',' . join(',', $config_new['collect']['art']['inrule']);
            $config_new['collect']['art']['uprule'] = ',' . join(',', $config_new['collect']['art']['uprule']);
            $config_new['collect']['actor']['inrule'] = ',' . join(',', $config_new['collect']['actor']['inrule']);
            $config_new['collect']['actor']['uprule'] = ',' . join(',', $config_new['collect']['actor']['uprule']);
            $config_new['collect']['role']['inrule'] = ',' . join(',', $config_new['collect']['role']['inrule']);
            $config_new['collect']['role']['uprule'] = ',' . join(',', $config_new['collect']['role']['uprule']);
            $config_new['collect']['website']['inrule'] = ',' . join(',', $config_new['collect']['website']['inrule']);
            $config_new['collect']['website']['uprule'] = ',' . join(',', $config_new['collect']['website']['uprule']);
            $config_new['collect']['comment']['inrule'] = ',' . join(',', $config_new['collect']['comment']['inrule']);
            $config_new['collect']['comment']['uprule'] = ',' . join(',', $config_new['collect']['comment']['uprule']);
            $config_new['collect']['manga']['inrule'] = ',' . join(',', $config_new['collect']['manga']['inrule']);
            $config_new['collect']['manga']['uprule'] = ',' . join(',', $config_new['collect']['manga']['uprule']);

            $config_new['collect']['vod']['namewords'] = mac_replace_text($config_new['collect']['vod']['namewords'], 2);
            $config_new['collect']['vod']['thesaurus'] = mac_replace_text($config_new['collect']['vod']['thesaurus'], 2);
            $config_new['collect']['vod']['playerwords'] = mac_replace_text($config_new['collect']['vod']['playerwords'], 2);
            $config_new['collect']['vod']['areawords'] = mac_replace_text($config_new['collect']['vod']['areawords'], 2);
            $config_new['collect']['vod']['langwords'] = mac_replace_text($config_new['collect']['vod']['langwords'], 2);
            $config_new['collect']['vod']['words'] = mac_replace_text($config_new['collect']['vod']['words'], 2);
            $config_new['collect']['art']['thesaurus'] = mac_replace_text($config_new['collect']['art']['thesaurus'], 2);
            $config_new['collect']['art']['words'] = mac_replace_text($config_new['collect']['art']['words'], 2);
            $config_new['collect']['actor']['thesaurus'] = mac_replace_text($config_new['collect']['actor']['thesaurus'], 2);
            $config_new['collect']['actor']['words'] = mac_replace_text($config_new['collect']['actor']['words'], 2);
            $config_new['collect']['role']['thesaurus'] = mac_replace_text($config_new['collect']['role']['thesaurus'], 2);
            $config_new['collect']['role']['words'] = mac_replace_text($config_new['collect']['role']['words'], 2);
            $config_new['collect']['website']['thesaurus'] = mac_replace_text($config_new['collect']['website']['thesaurus'], 2);
            $config_new['collect']['website']['words'] = mac_replace_text($config_new['collect']['website']['words'], 2);
            $config_new['collect']['comment']['thesaurus'] = mac_replace_text($config_new['collect']['comment']['thesaurus'], 2);
            $config_new['collect']['comment']['words'] = mac_replace_text($config_new['collect']['comment']['words'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }


        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configcollect/title'));
        return $this->fetch('admin@system/configcollect');
    }

    /**
     * 播放器参数配置 - 播放器样式设置
     * 配置前端播放器的显示和行为
     * 配置节点: play
     *
     * 【主要配置项】
     * - 播放器尺寸 (PC/移动端/弹窗)
     * - 广告设置 (前贴片、缓冲广告)
     * - 自动全屏、显示选集、水印等
     * - 播放器颜色主题
     *
     * 【特殊处理】
     * 保存时同时更新 static/js/playerconfig.js 文件
     * 前端播放器通过此JS文件获取配置
     */
    public function configplay()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['play'] = $config['play'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }

            $path = './static/js/playerconfig.js';
            if (!file_exists($path)) {
                $path .= '.bak';
            }
            $fc = @file_get_contents($path);
            $jsb = mac_get_body($fc, '//参数开始', '//参数结束');
            $content = 'MacPlayerConfig=' . json_encode($config['play']) . ';';
            $fc = str_replace($jsb, "\r\n" . $content . "\r\n", $fc);
            $res = @fwrite(fopen('./static/js/playerconfig.js', 'wb'), $fc);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $fp = './static/js/playerconfig.js';
        if (!file_exists($fp)) {
            $fp .= '.bak';
        }
        $fc = file_get_contents($fp);
        $jsb = trim(mac_get_body($fc, '//参数开始', '//参数结束'));
        $jsb = substr($jsb, 16, strlen($jsb) - 17);

        $play = json_decode($jsb, true);
        $this->assign('play', $play);
        $this->assign('title', lang('admin/system/configplay/title'));
        return $this->fetch('admin@system/configplay');
    }

    /**
     * SEO参数配置 - 搜索引擎优化设置
     * 配置各模块页面的SEO标题、关键词、描述
     * 配置节点: seo
     *
     * 【支持的模块】
     * - vod (视频) - 视频首页SEO
     * - art (文章) - 文章首页SEO
     * - actor (演员) - 演员首页SEO
     * - role (角色) - 角色首页SEO
     * - plot (剧情) - 剧情首页SEO
     */
    public function configseo()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['seo'] = $config['seo'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configseo/title'));
        return $this->fetch('admin@system/configseo');
    }

    /**
     * 语言切换 (AJAX接口)
     * 切换后台界面语言
     * POST admin.php/system/configlang
     * @param string lang 语言标识 (zh-cn, en-us等)
     */
    public function configlang(){
        $param = input();
        $config = config('maccms');
        if (!isset($config['app'])) {
            $config['app'] = [];
        }
        $config['app']['lang'] = $param['lang'];
        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
        if ($res === false) {
            return $this->error(lang('save_err'));
        }
        return json(['code' => 1, 'msg' => 'ok']);
    }

    /**
     * 版本切换 (AJAX接口)
     * 切换后台界面版本 (新版/旧版)
     * POST admin.php/system/configVersion
     * @param int version 版本标识 (0=旧版, 1=新版)
     */
    public function configVersion(){
        $param = input();
        $config = config('maccms');
        if (!isset($config['site'])) {
            $config['site'] = [];
        }
        $config['site']['new_version'] = $param['version'];
        if (!is_writable(APP_PATH . 'extra/maccms.php')) {
            return $this->error(APP_PATH . 'extra/maccms.php' . lang('install/write_read_err'));
        }
        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
        if ($res === false) {
            return $this->error(lang('save_err'));
        }
        return json(['code' => 1, 'msg' => 'ok']);
    }

}
