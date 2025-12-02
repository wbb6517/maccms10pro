<?php
/**
 * 模板管理控制器 (Template Controller)
 * ============================================================
 *
 * 【文件说明】
 * 管理网站前台模板文件的控制器，提供模板文件的浏览、编辑、新增和删除功能。
 * 支持在线编辑 HTML/JS/XML 等模板文件，同时具备安全过滤机制防止注入恶意代码。
 *
 * 【菜单位置】
 * 后台管理 → 模板 → 模板管理
 *
 * 【主要功能】
 * - 模板文件列表浏览 (支持目录层级导航)
 * - 模板文件在线编辑 (支持 html/htm/js/xml 格式)
 * - 模板文件新增和删除
 * - 广告代码管理 (ads 目录下的 JS 文件)
 * - 模板标签生成向导
 *
 * 【方法列表】
 * ┌──────────────────┬──────────────────────────────────────────────────────────┐
 * │ 方法名            │ 功能说明                                                  │
 * ├──────────────────┼──────────────────────────────────────────────────────────┤
 * │ __construct()    │ 构造函数，调用父类构造函数                                  │
 * │ index()          │ 模板文件列表页，显示目录和文件列表                          │
 * │ ads()            │ 广告代码管理页，管理广告 JS 文件                            │
 * │ info()           │ 模板编辑页，支持查看和保存模板文件内容                       │
 * │ del()            │ 删除模板文件                                              │
 * │ wizard()         │ 模板标签向导页，辅助生成模板标签代码                         │
 * └──────────────────┴──────────────────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/template/index       → 模板文件列表
 * admin.php/template/ads         → 广告代码管理
 * admin.php/template/info        → 模板文件编辑
 * admin.php/template/del         → 删除模板文件
 * admin.php/template/wizard      → 模板标签向导
 *
 * 【安全机制】
 * - 路径安全检测：限制只能操作 ./template 目录下的文件
 * - 文件类型限制：只允许编辑 html/htm/js/xml 格式
 * - 内容过滤：禁止包含 PHP 代码、eval、shell 等危险字符串
 *
 * 【相关文件】
 * - application/common/validate/Template.php : 模板验证器
 * - application/admin/view_new/template/index.html : 模板列表视图
 * - application/admin/view_new/template/info.html : 模板编辑视图
 * - application/admin/view_new/template/ads.html : 广告管理视图
 * - application/admin/view_new/template/wizard.html : 标签向导视图
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Template extends Base
{
    /**
     * 构造函数
     * 调用父类构造函数，初始化控制器
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 模板文件列表页
     * ============================================================
     *
     * 【功能说明】
     * 显示模板目录下的所有文件和子目录列表，支持目录层级导航。
     * 提供三种快捷视图：当前模板、标签文件、广告文件。
     *
     * 【页面结构】
     * ┌──────────────────────────────────────────────────────┐
     * │ 工具栏：[新增文件]                                    │
     * ├──────────────────────────────────────────────────────┤
     * │ 文件列表表格                                          │
     * │ - 文件名 / 目录名                                     │
     * │ - 类型（文件/目录）                                   │
     * │ - 文件大小                                            │
     * │ - 修改时间                                            │
     * │ - 操作按钮（编辑/删除）                               │
     * ├──────────────────────────────────────────────────────┤
     * │ 页脚统计：当前目录、目录数、文件数、占用空间           │
     * └──────────────────────────────────────────────────────┘
     *
     * 【请求参数】
     * - path    : 当前浏览的目录路径，格式为 ".@template@xxx" (@ 分隔符)
     * - current : 是否跳转到当前使用的模板目录
     * - label   : 是否跳转到 label 标签目录
     * - ads     : 是否跳转到 ads 广告目录
     *
     * 【安全检测】
     * - 路径必须以 ".@template" 开头
     * - 路径中不能包含多个 ".@" (防止目录遍历)
     *
     * 【模板变量】
     * - $curpath  : 当前目录路径
     * - $uppath   : 上级目录路径
     * - $ischild  : 是否是子目录 (1=是, 0=否)
     * - $files    : 文件列表数组
     * - $num_path : 目录数量
     * - $num_file : 文件数量
     * - $sum_size : 总文件大小
     *
     * @return mixed 渲染模板页面
     */
    public function index()
    {
        $param = input();
        $path = $param['path'];
        $path = str_replace('\\','',$path);
        $path = str_replace('/','',$path);

        if(empty($path)){
            $path = '.@template';
        }

        if(substr($path,0,10) != ".@template") { $path = ".@template"; }
        if(count( explode(".@",$path) ) > 2) {
            $this->error(lang('illegal_request'));
            return;
        }

        $uppath = substr($path,0,strrpos($path,"@"));
        $ischild = 0;
        if ($path !=".@template"){
            $ischild = 1;
        }

        $config = config('maccms.site');
        if($param['current']==1){
            $path = '.@template@' . $config['template_dir'] .'@' . $config['html_dir'] ;
            $ischild = 0;
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/*';
        }
        elseif($param['label']==1){
            $path = '.@template@' . $config['template_dir'] .'@' . $config['html_dir'] ;
            $ischild = 0;
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/label/*';
        }
        elseif($param['ads']==1){
            $path = '.@template@' . $config['template_dir'] .'@' . $config['html_dir'] ;
            $ischild = 0;
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/ads/*';
        }
        else{
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/*';
        }

        $this->assign('curpath',$path);
        $this->assign('uppath',$uppath);
        $this->assign('ischild',$ischild);

        $num_path = 0;
        $num_file = 0;
        $sum_size = 0;
        $files = [];

        if(is_dir($pp)) {
            $farr = glob($filters);
            if ($farr) {
                foreach ($farr as $f) {

                    if(is_dir($f)) {
                            $num_path++;
                            $tmp_path = str_replace('./template/', '.@template/', $f);
                            $tmp_path = str_replace('/', '@', $tmp_path);
                            $tmp_name = str_replace($path . '@', '', $tmp_path);
                            $ftime = filemtime($f);

                            $files[] = ['isfile' => 0, 'name' => $tmp_name, 'path' => $tmp_path, 'note'=>lang('dir'), 'time' => $ftime];
                    }
                    elseif(is_file($f)) {
                        $num_file++;
                        $fsize = filesize($f);
                        $sum_size += $fsize;
                        $fsize = mac_format_size($fsize);
                        $ftime = filemtime($f);
                        $tmp_path = mac_convert_encoding($f, "UTF-8", "GB2312");

                        $path_info = @pathinfo($f);
                        $tmp_path = $path_info['dirname'];
                        $tmp_name = $path_info['basename'];

                        $files[] = ['isfile' => 1, 'name' => $tmp_name, 'path' => $tmp_path, 'fullname'=> $tmp_path.'/'.$tmp_name, 'size' => $fsize,'note'=>lang('file'), 'time' => $ftime];
                    }
                }
            }
        }
        $this->assign('sum_size',mac_format_size($sum_size));
        $this->assign('num_file',$num_file);
        $this->assign('num_path',$num_path);
        $this->assign('files',$files);

        $this->assign('title',lang('admin/template/title'));
        return $this->fetch('admin@template/index');
    }

    /**
     * ============================================================
     * 广告代码管理页
     * ============================================================
     *
     * 【功能说明】
     * 管理当前模板下的广告 JS 文件，提供广告代码的新增、编辑、删除和复制调用代码功能。
     * 广告文件存放在 ./template/{模板目录}/{广告目录}/ 下，默认为 ads 目录。
     *
     * 【页面结构】
     * ┌──────────────────────────────────────────────────────┐
     * │ 工具栏：[新增广告]                                    │
     * ├──────────────────────────────────────────────────────┤
     * │ 广告文件列表表格                                      │
     * │ - 文件名                                              │
     * │ - 类型（文件）                                        │
     * │ - 文件大小                                            │
     * │ - 修改时间                                            │
     * │ - 调用代码（可复制）                                  │
     * │ - 操作按钮（复制/编辑/删除）                          │
     * ├──────────────────────────────────────────────────────┤
     * │ 页脚统计：当前目录、文件数、占用空间                   │
     * └──────────────────────────────────────────────────────┘
     *
     * 【模板变量】
     * - $curpath  : 当前广告目录路径
     * - $files    : 广告文件列表数组 (仅 .js 文件)
     * - $num_file : 文件数量
     * - $sum_size : 总文件大小
     *
     * @return mixed 渲染模板页面
     */
    public function ads()
    {
        $adsdir = $GLOBALS['config']['site']['ads_dir'];
        if(empty($adsdir)){
            $adsdir='ads';
        }
        $path = './template/'.$GLOBALS['config']['site']['template_dir'].'/'.$adsdir ;
        if(!file_exists($path)){
            mac_mkdirss($path);
        }

        $filters = $path.'/*.js';
        $num_file=0;
        $sum_size=0;
        $farr = glob($filters);
        if ($farr) {
            foreach ($farr as $f) {
                if(is_file($f)) {
                    $num_file++;
                    $fsize = filesize($f);
                    $sum_size += $fsize;
                    $fsize = mac_format_size($fsize);
                    $ftime = filemtime($f);
                    $tmp_path = mac_convert_encoding($f, "UTF-8", "GB2312");

                    $path_info = @pathinfo($f);
                    $tmp_path = $path_info['dirname'];
                    $tmp_name = $path_info['basename'];

                    $files[] = ['isfile' => 1, 'name' => $tmp_name, 'path' => $tmp_path, 'fullname'=> $tmp_path.'/'.$tmp_name, 'size' => $fsize,'note'=>lang('file'), 'time' => $ftime];
                }
            }
        }
        $this->assign('curpath',$path);
        $this->assign('sum_size',mac_format_size($sum_size));
        $this->assign('num_file',$num_file);
        $this->assign('files',$files);
        $this->assign('title',lang('admin/template/ads/title'));
        return $this->fetch('admin@template/ads');
    }

    /**
     * ============================================================
     * 模板文件编辑页
     * ============================================================
     *
     * 【功能说明】
     * 提供模板文件的查看和编辑功能，支持新增文件。
     * 为防止安全风险，对文件内容进行危险字符串过滤。
     *
     * 【安全机制】
     * 1. 路径安全检测：
     *    - 文件必须在 ./template 目录下
     *    - 路径中不能包含多个 "./" (防止目录遍历)
     *
     * 2. 文件类型限制：
     *    - 只允许编辑 html/htm/js/xml 格式文件
     *
     * 3. 内容过滤（保存时检测）：
     *    - 禁止 PHP 标签: <?、php
     *    - 禁止危险函数: eval、assert、system、shell、exec 等
     *    - 禁止文件操作: fopen、fputs、file、proc 等
     *    - 禁止超全局变量: $_GET、$_POST、$_REQUEST 等
     *    - 禁止 ThinkPHP 模板注入: {:、{$、{~、{-、{+、{/ 等
     *
     * 【请求方法】
     * GET  - 显示文件内容供编辑
     * POST - 保存编辑后的文件内容
     *
     * 【请求参数】
     * - fpath    : 文件所在目录路径 (使用 @ 分隔符)
     * - fname    : 文件名
     * - fcontent : 文件内容 (POST 保存时)
     *
     * 【模板变量】
     * - $filter   : 危险字符串过滤规则
     * - $fpath    : 文件路径
     * - $fname    : 文件名
     * - $fcontent : 文件内容
     *
     * @return mixed 渲染模板页面或返回操作结果
     */
    public function info()
    {
        $param = input();

        $fname = $param['fname'];
        $fpath = $param['fpath'];

        if( empty($fpath)){
            $this->error(lang('param_err').'1');
            return;
        }
        $fpath = str_replace('@','/',$fpath);
        $fullname = $fpath .'/' .$fname;
        $fullname = str_replace('\\','/',$fullname);

        if( (substr($fullname,0,10) != "./template") || count( explode("./",$fullname) ) > 2) {
            $this->error(lang('param_err').'2');
            return;
        }
        $path = pathinfo($fullname);
        if(!empty($fname)) {
            $extarr = array('html', 'htm', 'js', 'xml');
            if (!in_array($path['extension'], $extarr)) {
                $this->error(lang('admin/template/ext_safe_tip'));
                return;
            }
        }

        $filter = '<\?|php|eval|server|assert|get|post|request|cookie|session|input|env|config|call|global|dump|print|phpinfo|fputs|fopen|global|chr|strtr|pack|system|gzuncompress|shell|base64|file|proc|preg|call|ini|{:|{$|{~|{-|{+|{/';
        $this->assign('filter',$filter);

        if (Request()->isPost()) {
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            $validate = \think\Loader::validate('Template');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            $fcontent = $param['fcontent'];
            $r = mac_reg_replace($fcontent,$filter,"*");
            if($fcontent !== $r){
                $this->error(lang('admin/template/php_safe_tip'));
                return;
            }
            $res = @fwrite(fopen($fullname,'wb'),$fcontent);

            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $fcontent = @file_get_contents($fullname);
        $fcontent = str_replace('</textarea>','<&#47textarea>',$fcontent);
        $this->assign('fname',$fname);
        $this->assign('fpath',$fpath);
        $this->assign('fcontent',$fcontent);

        return $this->fetch('admin@template/info');
    }

    /**
     * ============================================================
     * 删除模板文件
     * ============================================================
     *
     * 【功能说明】
     * 删除指定的模板文件，支持批量删除。
     * 为确保安全，只能删除 ./template 目录下的文件。
     *
     * 【安全机制】
     * - 路径必须以 "./template" 开头
     * - 路径中不能包含多个 "./" (防止目录遍历攻击)
     * - 文件编码转换后检查存在性再删除
     *
     * 【请求参数】
     * - fname : 要删除的文件路径，可以是字符串或数组（批量删除）
     *
     * @return mixed JSON 格式的操作结果
     */
    public function del()
    {
        $param = input();
        $fname = $param['fname'];
        if(!empty($fname)){
            if(!is_array($fname)){
                $fname = [$fname];
            }
            foreach($fname as $a){
                $a = str_replace('\\','/',$a);

                if( (substr($a,0,10) != "./template") || count( explode("./",$a) ) > 2) {

                }
                else{
                    $a = mac_convert_encoding($a,"UTF-8","GB2312");
                    if(file_exists($a)){ @unlink($a); }
                }
            }
        }
        return $this->success(lang('del_ok'));
    }

    /**
     * ============================================================
     * 模板标签向导页
     * ============================================================
     *
     * 【功能说明】
     * 提供可视化的模板标签生成工具，帮助用户快速生成 maccms 模板标签代码。
     * 支持生成多种类型的标签，包括链接、分类、专题、文章、视频、留言、评论等。
     *
     * 【支持的标签类型】
     * - link     : 友情链接标签
     * - type     : 分类标签
     * - topic    : 专题标签
     * - art      : 文章标签
     * - vod      : 视频标签
     * - area     : 地区筛选标签
     * - lang     : 语言筛选标签
     * - year     : 年份筛选标签
     * - letter   : 首字母筛选标签
     * - tag      : 自定义标签
     * - gbook    : 留言板标签
     * - comment  : 评论标签
     *
     * 【页面结构】
     * ┌──────────────────────────────────────────────────────┐
     * │ 标签类别选择按钮组                                    │
     * ├──────────────────────────────────────────────────────┤
     * │ 标签参数设置区域                                      │
     * │ - 排序方式（正序/倒序）                               │
     * │ - 排序字段（时间/ID/点击量等）                        │
     * │ - 数量限制                                            │
     * │ - 分页设置                                            │
     * │ - 数据筛选（分类ID/等级等）                           │
     * ├──────────────────────────────────────────────────────┤
     * │ 标签代码生成结果（可复制）                            │
     * └──────────────────────────────────────────────────────┘
     *
     * @return mixed 渲染模板页面
     */
    public function wizard()
    {
        $this->assign('title',lang('admin/template/wizard/title'));
        return $this->fetch('admin@template/wizard');
    }

}
