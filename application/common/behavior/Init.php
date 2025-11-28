<?php
namespace app\common\behavior;

use think\Cache;
use think\Exception;

class Init
{
    public function run(&$params)
    {
        $config = config('maccms');
        $domain = config('domain');

        $isMobile = 0;
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        $uachar = "/(nokia|sony|ericsson|mot|samsung|sgh|lg|philips|panasonic|alcatel|lenovo|meizu|cldc|midp|iphone|wap|mobile|android)/i";
        if((preg_match($uachar, $ua))) {
            $isMobile = 1;
        }

        $isDomain=0;
        if( is_array($domain) && !empty($domain[$_SERVER['HTTP_HOST']])){
            $config['site'] = array_merge($config['site'],$domain[$_SERVER['HTTP_HOST']]);
            $isDomain=1;
            if(empty($config['site']['mob_template_dir']) || $config['site']['mob_template_dir'] =='no'){
                $config['site']['mob_template_dir'] = $config['site']['template_dir'];
            }
            $config['site']['site_wapurl'] = $config['site']['site_url'];
            $config['site']['mob_html_dir'] = $config['site']['html_dir'];
            $config['site']['mob_ads_dir'] = $config['site']['ads_dir'];
        }
        $TMP_ISWAP = 0;
        $TMP_TEMPLATEDIR = $config['site']['template_dir'];
        $TMP_HTMLDIR = $config['site']['html_dir'];
        $TMP_ADSDIR = $config['site']['ads_dir'];

        if($isMobile && $isDomain==0){
            if( ($config['site']['mob_status']==2 ) || ($config['site']['mob_status']==1 && $_SERVER['HTTP_HOST']==$config['site']['site_wapurl']) || ($config['site']['mob_status']==1 && $isDomain) ) {
                $TMP_ISWAP = 1;
                $TMP_TEMPLATEDIR = $config['site']['mob_template_dir'];
                $TMP_HTMLDIR = $config['site']['mob_html_dir'];
                $TMP_ADSDIR = $config['site']['mob_ads_dir'];
            }
        }

        define('MAC_URL','http'.'://'.'www'.'.'.'maccms'.'.'.'la'.'/');
        define('MAC_NAME','苹果CMS');
        define('MAC_PATH', $config['site']['install_dir'] .'');
        define('MAC_MOB', $TMP_ISWAP);
        define('MAC_ROOT_TEMPLATE', ROOT_PATH .'template/'.$TMP_TEMPLATEDIR.'/'. $TMP_HTMLDIR .'/');
        define('MAC_PATH_TEMPLATE', MAC_PATH.'template/'.$TMP_TEMPLATEDIR.'/');
        define('MAC_PATH_TPL', MAC_PATH_TEMPLATE. $TMP_HTMLDIR  .'/');
        define('MAC_PATH_ADS', MAC_PATH_TEMPLATE. $TMP_ADSDIR  .'/');
        define('MAC_PAGE_SP', $config['path']['page_sp'] .'');
        define('MAC_PLAYER_SORT', $config['app']['player_sort'] );
        define('MAC_ADDON_PATH', ROOT_PATH . 'addons' . '/');
        define('MAC_ADDON_PATH_STATIC', ROOT_PATH . 'static/addons/');

        $GLOBALS['MAC_ROOT_TEMPLATE'] = ROOT_PATH .'template/'.$TMP_TEMPLATEDIR.'/'. $TMP_HTMLDIR .'/';
        $GLOBALS['MAC_PATH_TEMPLATE'] = MAC_PATH.'template/'.$TMP_TEMPLATEDIR.'/';
        $GLOBALS['MAC_PATH_TPL'] = $GLOBALS['MAC_PATH_TEMPLATE']. $TMP_HTMLDIR  .'/';
        $GLOBALS['MAC_PATH_ADS'] = $GLOBALS['MAC_PATH_TEMPLATE']. $TMP_ADSDIR  .'/';

        $GLOBALS['http_type'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

        if(ENTRANCE=='index'){
            config('dispatch_success_tmpl','public/jump');
            config('dispatch_error_tmpl','public/jump');
        }

        // ============================================================
        // 【模板路径配置 - 核心设置】
        // ============================================================
        // 设置 ThinkPHP 模板引擎的基础路径
        //
        // 【路径拼接规则】
        // $TMP_TEMPLATEDIR = maccms.site.template_dir (默认 'default')
        // $TMP_HTMLDIR     = maccms.site.html_dir     (默认 'html')
        // 结果: template/default/html/
        //
        // 【控制器调用流程】
        // Index.php:137  → $this->label_fetch('index/index')
        // All.php:245    → $this->fetch('index/index')
        // Think.php:150  → return view_path + template + '.html'
        //                → template/default/html/index/index.html
        //
        // 【关键代码位置】
        // thinkphp/library/think/view/driver/Think.php:150
        // return $path . ltrim($template, '/') . '.' . $this->config['view_suffix'];
        // ============================================================
        config('template.view_path', 'template/' . $TMP_TEMPLATEDIR .'/' . $TMP_HTMLDIR .'/');

        // ============================================================
        // 【后台模板路径处理】
        // ============================================================
        // 后台入口时，清空 view_path 让 ThinkPHP 使用默认规则
        //
        // 【为什么清空?】
        // Think.php:44-46 构造函数中:
        //   if (empty($this->config['view_path'])) {
        //       $this->config['view_path'] = App::$modulePath . 'view' . DS;
        //   }
        // 清空后，后台模板路径变为: application/admin/view/
        //
        // 【前台 vs 后台对比】
        // ┌──────────┬─────────────────────────────────────────────┐
        // │ 入口      │ 模板路径                                    │
        // ├──────────┼─────────────────────────────────────────────┤
        // │ index.php │ template/default/html/index/index.html     │
        // │ admin.php │ application/admin/view/index/index.html    │
        // └──────────┴─────────────────────────────────────────────┘
        // ============================================================
        if(ENTRANCE=='admin'){
            if(!file_exists('./template/' . $TMP_TEMPLATEDIR .'/' . $TMP_HTMLDIR .'/')){
                config('template.view_path','');
            }
        }
        if(intval($config['app']['search_len'])<1){
            $config['app']['search_len'] = 10;
        }
        config('url_route_on',$config['rewrite']['route_status']);
        if(empty($config['app']['pathinfo_depr'])){
            $config['app']['pathinfo_depr'] = '/';
        }
        config('pathinfo_depr',$config['app']['pathinfo_depr']);

        if(intval($config['app']['cache_time'])<1){
            $config['app']['cache_time'] = 60;
        }
        config('cache.expire', $config['app']['cache_time'] );


        if(!in_array($config['app']['cache_type'],['file','memcache','memcached','redis'])){
            $config['app']['cache_type'] = 'file';
        }
        if(!empty($config['app']['lang'])){
            config('default_lang', $config['app']['lang']);
        }

        config('cache.type', $config['app']['cache_type']);
        config('cache.timeout',1000);
        config('cache.host',$config['app']['cache_host']);
        config('cache.port',$config['app']['cache_port']);
        config('cache.username',$config['app']['cache_username']);
        config('cache.password',$config['app']['cache_password']);
        if($config['app']['cache_type'] == 'redis' && isset($config['app']['cache_db']) && intval($config['app']['cache_db']) > 0){
            config('cache.select', intval($config['app']['cache_db']));
        }
        if($config['app']['cache_type'] != 'file'){
            $opt = config('cache');
            Cache::$handler = null;
        }

        $GLOBALS['config'] = $config;
    }
}