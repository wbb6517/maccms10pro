<?php
/**
 * 安全检测控制器 (Safety Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台安全检测管理控制器
 * 提供系统文件完整性检测和数据库恶意代码清理功能
 * 用于发现被篡改的文件和被注入的恶意脚本
 *
 * 【菜单位置】
 * 后台管理 → 应用 → 文件校验 (索引11 → 113)
 * 后台管理 → 应用 → 数据校验 (索引11 → 114)
 *
 * 【安全功能】
 * 1. 文件校验: 对比官方MD5值，检测新增/被篡改文件
 * 2. 数据校验: 扫描数据库中的XSS/模板注入代码并清理
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数                                    │
 * │ index()         │ 首页 (未使用)                               │
 * │ listDir()       │ 递归遍历目录获取文件列表                     │
 * │ file()          │ 文件完整性检测                              │
 * │ data()          │ 数据库恶意代码检测与清理                     │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/safety/file → 文件完整性检测
 * admin.php/safety/data → 数据库安全检测
 *
 * 【相关文件】
 * - application/admin/view_new/safety/file.html : 文件检测视图
 * - application/admin/view_new/safety/data.html : 数据检测视图
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Safety extends Base
{
    /**
     * 文件列表缓存
     * 存储递归扫描的所有文件及其MD5值
     * @var array
     */
    var $_files;

    /**
     * 构造函数
     * 调用父类构造函数进行初始化
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 首页 (未使用)
     */
    public function index()
    {

    }

    /**
     * ============================================================
     * 递归遍历目录获取文件列表
     * ============================================================
     *
     * 【功能说明】
     * 递归扫描指定目录下的所有文件
     * 计算每个文件的MD5值并存入 $_files 数组
     *
     * @param string $dir 要扫描的目录路径
     * @return void 结果存储在 $this->_files 中
     */
    protected function listDir($dir){
        if(is_dir($dir)){
            if ($dh = opendir($dir)) {
                while (($file= readdir($dh)) !== false){
                    // 转换编码并规范化路径
                    $tmp = str_replace('//','/',mac_convert_encoding($dir.$file, "UTF-8", "GB2312"));
                    if((is_dir($dir."/".$file)) && $file!="." && $file!=".."){
                        // 递归扫描子目录
                        $this->listDir($dir."/".$file."/");
                    } else{
                        if($file!="." && $file!=".."){
                            // 记录文件及其MD5值
                            $this->_files[$tmp] = ['md5'=>md5_file($dir.$file)];
                        }
                    }
                }
                closedir($dh);
            }
        }
    }

    /**
     * ============================================================
     * 文件完整性检测
     * ============================================================
     *
     * 【功能说明】
     * 检测系统文件是否被篡改或新增可疑文件
     * 通过对比官方发布的文件MD5值来判断
     *
     * 【检测类型】
     * - ft[]=1 : 检测新增文件 (官方不存在的文件)
     * - ft[]=2 : 检测被修改文件 (MD5值不匹配)
     *
     * 【执行流程】
     * 1. 从官方服务器获取当前版本的文件MD5列表
     * 2. 递归扫描本地所有文件
     * 3. 对比MD5值，标记异常文件
     *
     * 【颜色标识】
     * - BlueViolet(紫色): 新增文件
     * - Red(红色): 被修改的文件
     *
     * @return mixed 渲染页面或输出检测结果
     */
    public function file()
    {
        $param = input();

        if($param['ck']){
            // ==================== 执行文件检测 ====================
            $ft = $param['ft'];
            if(empty($ft)){
                $ft = ['1','2'];  // 默认检测全部类型
            }

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            // 从官方服务器获取文件MD5列表
            $url = base64_decode("aHR0cDovL3VwZGF0ZS5tYWNjbXMubGEv") . "v10/mac_files_".config('version')['code'].'.html';
            $html = mac_curl_get($url);
            $json = json_decode($html,true);
            if(!$json){
                return $this->error(lang('admin/safety/file_msg1'));
            }

            // 递归扫描本地文件
            $this->listDir('./');
            if(!is_array($this->_files)){
                return $this->error(lang('admin/safety/file_msg2'));
            }

            // ==================== 对比检测 ====================
            foreach($this->_files as $k=>$v){
                $color = '';
                $msg = 'ok';

                // 检测新增文件 (官方不存在)
                if(empty($json[$k]) && in_array('1',$ft)){
                    $color = 'BlueViolet';
                    $msg = lang('admin/safety/file_msg3');  // 新增文件
                }
                // 检测被修改文件 (MD5不匹配)
                elseif(!empty($json[$k]) && $v['md5'] != $json[$k]['md5'] && in_array('2',$ft)){
                    $color = 'red';
                    $msg = lang('admin/safety/file_msg4');  // 被修改文件
                }

                // 输出异常文件
                if($color!='') {
                    mac_echo($k . '---' . "<font color=$color>" . $msg . '</font>');
                }
            }
            exit;
        }

        // GET请求: 显示检测页面
        return $this->fetch('admin@safety/file');
    }

    /**
     * ============================================================
     * 数据库恶意代码检测与清理
     * ============================================================
     *
     * 【功能说明】
     * 扫描数据库中的恶意代码并自动清理
     * 主要针对XSS攻击、模板注入等安全威胁
     *
     * 【检测的恶意代码类型】
     * - <script>...</script> : JavaScript注入
     * - <iframe>...</iframe> : 框架注入
     * - {php}...{/php}       : ThinkPHP模板PHP代码
     * - {:...}               : ThinkPHP模板函数调用
     *
     * 【扫描的数据表】
     * actor, art, gbook, link, topic, type, vod
     *
     * 【执行流程】
     * 1. 获取数据表结构信息
     * 2. 对非整型字段进行恶意代码检测
     * 3. 使用正则表达式清理匹配的恶意内容
     * 4. 分表处理，自动跳转下一张表
     *
     * @return mixed 渲染页面或输出处理结果
     */
    public function data()
    {
        $param = input();

        if ($param['ck']) {
            // ==================== 获取数据库结构 ====================
            $pre = config('database.prefix');
            $schema = Db::query('select * from information_schema.columns where table_schema = ?', [config('database.database')]);

            // 按表名组织字段信息
            $col_list = [];
            $sql = '';
            foreach ($schema as $k => $v) {
                $col_list[$v['TABLE_NAME']][$v['COLUMN_NAME']] = $v;
            }

            // 需要扫描的数据表列表
            $tables = ['actor', 'art', 'gbook', 'link', 'topic', 'type', 'vod'];
            $param['tbi'] = intval($param['tbi']);

            // 检查是否已完成所有表
            if ($param['tbi'] >= count($tables)) {
                mac_echo(lang('admin/safety/data_clear_ok'));
                die;
            }

            // ==================== 定义恶意代码特征 ====================
            // 需要检测的关键字
            $check_arr = ["<script","<iframe","{php}","{:"];

            // 对应的清理正则表达式
            $rel_val = [
                [
                    "/<script[\s\S]*?<\/(.*)>/is",   // script标签 (闭合)
                    "/<script[\s\S]*?>/is",          // script标签 (未闭合)
                ],
                [
                    "/<iframe[\s\S]*?<\/(.*)>/is",   // iframe标签 (闭合)
                    "/<iframe[\s\S]*?>/is",          // iframe标签 (未闭合)
                ],
                [
                    "/{php}[\s\S]*?{\/php}/is",      // ThinkPHP PHP代码块
                ],
                [
                    "/{:[\s\S]*?}/is",               // ThinkPHP 模板函数
                ]
            ];

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            // ==================== 扫描并清理数据 ====================
            foreach ($col_list as $k1 => $v1) {
                $pre_tb = str_replace($pre, '', $k1);
                $si = array_search($pre_tb, $tables);

                // 只处理当前索引的表
                if ($pre_tb !== $tables[$param['tbi']]) {
                    continue;
                }

                mac_echo(lang('admin/safety/data_check_tip1',[$k1]));

                // 构建查询条件 (只查询非整型字段)
                $where = [];
                foreach ($v1 as $k2 => $v2) {
                    if (strpos($v2['DATA_TYPE'], 'int') === false) {
                        $where[$k2] = ['like', mac_like_arr(join(',', $check_arr)), 'OR'];
                    }
                }

                if (!empty($where)) {
                    // 查询包含可疑内容的记录
                    $field = array_keys($where);
                    $field[] = $tables[$si] . '_id';
                    $list = Db::name($pre_tb)->field($field)->whereOr($where)->fetchSql(false)->select();

                    mac_echo(lang('admin/safety/data_check_tip2',[count($list)]));

                    // 逐条清理恶意代码
                    foreach ($list as $k3 => $v3) {
                        $update = [];
                        $col_id = $tables[$si] . '_id';
                        $col_name = $tables[$si] . '_name';
                        $val_id = $v3[$col_id];;
                        $val_name = strip_tags($v3[$col_name]);
                        $ck = false;
                        $where2 = [];
                        $where2[$col_id] = $val_id;

                        // 遍历字段进行清理
                        foreach ($v3 as $k4 => $v4) {
                            if ($k4 != $col_id) {
                                $val = $v4;
                                // 应用所有清理规则
                                foreach ($check_arr as $kk => $vv) {
                                    foreach($rel_val[$kk] as $k5=>$v5){
                                        $val = preg_replace($v5, "", $val);
                                    }
                                }
                                // 如果内容有变化，标记需要更新
                                if ($val !== $v4) {
                                    $update[$k4] = $val;
                                    $ck = true;
                                }
                            }
                        }

                        // 执行更新
                        if ($ck) {
                            $r = Db::name($pre_tb)->where($where2)->update($update);
                            mac_echo($val_id . '、' . $val_name . ' ok');
                        }
                    }
                }
            }

            // 跳转到下一张表继续处理
            $param['tbi']++;
            $url = url('safety/data') . '?' . http_build_query($param);
            mac_jump($url, 3);
            exit;
        }

        // GET请求: 显示检测页面
        return $this->fetch('admin@safety/data');
    }
}
