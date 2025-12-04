<?php
/**
 * 多域名管理控制器 (Multi-Domain Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台多域名/多站点配置管理控制器
 * 支持为不同域名设置独立的站点名称、模板、SEO信息等
 *
 * 【菜单位置】
 * 后台管理 → 系统 → 多域名管理
 *
 * 【存储方式】
 * 配置保存在 application/extra/domain.php 文件中
 * 不使用数据库存储
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ index()         │ 域名列表/配置页面                            │
 * │ del()           │ 删除域名配置                                 │
 * │ export()        │ 导出域名配置为TXT文件                        │
 * │ import()        │ 从TXT文件导入域名配置                        │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/domain/index  → 域名配置页面
 * admin.php/domain/del    → 删除域名
 * admin.php/domain/export → 导出配置
 * admin.php/domain/import → 导入配置
 *
 * 【配置字段说明】
 * - site_url        : 域名地址
 * - site_name       : 站点名称
 * - site_keywords   : SEO关键词
 * - site_description: SEO描述
 * - template_dir    : 模板目录
 * - html_dir        : 静态页目录
 * - ads_dir         : 广告目录
 * - map_dir         : 地图目录
 *
 * 【相关文件】
 * - application/extra/domain.php : 域名配置存储文件
 * - application/admin/view_new/domain/ : 视图文件目录
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use think\Config;
use think\Cache;

class Domain extends Base
{

    /**
     * ============================================================
     * 域名配置页面
     * ============================================================
     *
     * 【功能说明】
     * GET: 显示域名配置列表
     * POST: 保存域名配置到配置文件
     *
     * 【配置数组结构】
     * [
     *   'www.example.com' => [
     *     'site_url' => 'www.example.com',
     *     'site_name' => '站点名称',
     *     'site_keywords' => 'SEO关键词',
     *     'site_description' => 'SEO描述',
     *     'template_dir' => '模板目录',
     *     'html_dir' => '静态页目录',
     *     'ads_dir' => '广告目录',
     *     'map_dir' => '地图目录',
     *   ],
     * ]
     *
     * @return mixed 渲染页面或JSON响应
     */
    public function index()
    {
        if (Request()->isPost()) {
            $config = input();

            $tmp = $config['domain'];
            $domain=[];



            foreach ($tmp['site_url'] as $k=>$v){

                $domain[$v] =[
                   'site_url'=>$v,
                    'site_name'=>$tmp['site_name'][$k],
                    'site_keywords'=>$tmp['site_keywords'][$k],
                    'site_description'=>$tmp['site_description'][$k],
                    'template_dir'=>$tmp['template_dir'][$k],
                    'html_dir'=>$tmp['html_dir'][$k],
                    'ads_dir'=>$tmp['ads_dir'][$k],
                    'map_dir'=>$tmp['map_dir'][$k],
                ];

            }


            $res = mac_arr2file(APP_PATH . 'extra/domain.php', $domain);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }


        $templates = glob('./template' . '/*', GLOB_ONLYDIR);
        foreach ($templates as $k => &$v) {
            $v = str_replace('./template/', '', $v);
        }
        $this->assign('templates', $templates);

        $config = config('domain');
        $this->assign('domain_list', $config);
        $this->assign('title', lang('admin/domain/title'));
        return $this->fetch('admin@domain/index');
    }

    /**
     * ============================================================
     * 删除域名配置
     * ============================================================
     *
     * 【功能说明】
     * 从配置文件中删除指定的域名配置
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        if(!empty($param['ids'])){
            $list = config('domain');
            unset($list[$param['ids']]);
            $res = mac_arr2file( APP_PATH .'extra/domain.php', $list);
            if($res===false){
                return $this->error(lang('del_err'));
            }
        }
        return $this->success(lang('del_ok'));
    }

    /**
     * ============================================================
     * 导出域名配置
     * ============================================================
     *
     * 【功能说明】
     * 将域名配置导出为TXT文件下载
     * 格式: 域名$站名$关键词$描述$模板目录$静态目录$广告目录$地图目录
     * 每行一条记录，字段用$分隔
     *
     * @return void 直接输出文件下载
     */
    public function export()
    {
        $list = config('domain');
        $html = '';
        foreach($list as $k=>$v){
            $html .= $v['site_url'].'$'.$v['site_name'].'$'.$v['site_keywords'].'$'.$v['site_description'].'$'.$v['template_dir'].'$'.$v['html_dir'].'$'.$v['ads_dir'].'$'.$v['map_dir']."\n";
        }

        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=mac_domains.txt");
        echo $html;
    }

    /**
     * ============================================================
     * 导入域名配置
     * ============================================================
     *
     * 【功能说明】
     * 从上传的TXT文件导入域名配置
     * 文件格式与导出格式相同，每行一条记录
     *
     * 【文件限制】
     * - 大小: 最大10MB
     * - 格式: 仅支持TXT文件
     *
     * @return \think\response\Json JSON响应
     */
    public function import()
    {
        $file = $this->request->file('file');
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
        if ($info) {
            $data = file_get_contents($info->getpathName());
            @unlink($info->getpathName());
            if($data){
                $list = explode(chr(10),$data);

                $domain =[];

                foreach($list as $k=>$v){
                    if(!empty($v)) {
                        $one = explode('$', $v);
                        $domain[$one[0]] = [
                            'site_url' => $one[0],
                            'site_name' => $one[1],
                            'site_keywords' => $one[2],
                            'site_description' => $one[3],
                            'template_dir' => $one[4],
                            'html_dir' => $one[5],
                            'ads_dir'=>$one[6],
                        ];
                    }
                }

                $res = mac_arr2file( APP_PATH .'extra/domain.php', $domain);
                if($res===false){
                    return $this->error(lang('write_err_config'));
                }
            }
            return $this->success(lang('import_err'));
        }
        else{
            return $this->error($file->getError());
        }
    }
}
