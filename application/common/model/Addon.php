<?php
/**
 * 插件模型 (Addon Model)
 * ============================================================
 *
 * 【功能说明】
 * 管理插件数据的获取
 * 包括在线插件列表和本地已安装插件列表
 *
 * 【特点】
 * 此模型不操作数据库表，插件系统基于文件管理
 * 插件存放在 addons/ 目录（ADDON_PATH常量）
 *
 * 【方法列表】
 * ┌─────────────────────────────────┬──────────────────────────────┐
 * │ 方法名                           │ 说明                          │
 * ├─────────────────────────────────┼──────────────────────────────┤
 * │ onlineData()                    │ 获取在线插件列表              │
 * │ localData()                     │ 获取本地已安装插件列表        │
 * └─────────────────────────────────┴──────────────────────────────┘
 *
 * 【插件目录结构】
 * addons/
 * └── {插件名}/
 *     ├── info.ini      # 插件信息配置（必须）
 *     ├── config.php    # 插件参数配置
 *     ├── {Name}.php    # 插件主类
 *     └── ...
 *
 * 【info.ini 配置项】
 * - name    : 插件标识（英文）
 * - title   : 插件名称
 * - intro   : 简介说明
 * - author  : 作者
 * - website : 作者网站
 * - version : 版本号
 * - state   : 状态（0=禁用, 1=启用）
 *
 * 【相关文件】
 * - application/admin/controller/Addon.php : 后台控制器
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;
use think\Config;

class Addon extends Base {

    /**
     * 获取在线插件列表
     *
     * 【功能说明】
     * 从远程服务器获取可用插件列表
     * 用于在线安装功能（预留功能）
     *
     * @param int $page 页码
     * @return array ['code' => 状态码, 'msg' => 提示信息, ...]
     */
    public function onlineData($page=1)
    {
        $html = mac_curl_get( base64_decode('6aKE55WZ5Yqf6IO9').'store/?page=' . $page);
        $json = json_decode($html, true);
        if (!$json) {
            return ['code' => 1001, 'msg' => lang('obtain_err')];
        }
        return $json;
    }

    /**
     * 获取本地已安装插件列表
     *
     * 【功能说明】
     * 扫描 addons/ 目录，读取所有已安装插件信息
     *
     * 【处理流程】
     * 1. 使用 glob() 扫描 ADDON_PATH 目录
     * 2. 跳过非目录和特殊目录（. 和 ..）
     * 3. 读取每个插件的 info.ini 配置文件
     * 4. 解析配置并添加到列表
     *
     * 【返回数据】
     * @return array [
     *     'code' => 1,
     *     'list' => [
     *         '插件名' => [
     *             'name'    => '插件标识',
     *             'title'   => '插件名称',
     *             'intro'   => '简介',
     *             'author'  => '作者',
     *             'version' => '版本',
     *             'state'   => '状态',
     *             'url'     => '访问地址',
     *             'install' => 1,
     *         ],
     *         ...
     *     ]
     * ]
     */
    public function localData()
    {
        $results = glob(ADDON_PATH.'*');

        $list = [];
        foreach ($results as $addonDir) {
            // 跳过特殊目录
            if ($addonDir === '.' or $addonDir === '..')
                continue;

            // 跳过非目录
            if (!is_dir($addonDir))
                continue;

            // 检查 info.ini 配置文件是否存在
            $info_file = $addonDir .DS. 'info.ini';
            if (!is_file($info_file))
                continue;

            // 解析插件配置
            $name = str_replace(ADDON_PATH,'',$addonDir);
            $info = Config::parse($info_file, '', "addon-info-{$name}");
            $info['url'] = mac_url($name);
            $info['install'] = 1;
            $list[$name] = $info;
        }
        return ['code'=>1,'list'=>$list];
    }



}