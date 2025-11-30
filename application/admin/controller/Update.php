<?php
/**
 * 系统更新控制器 (System Update Controller)
 * ============================================================
 *
 * 【文件说明】
 * 处理 MacCMS 系统的在线更新和数据库升级功能
 * 包括:
 * - 下载更新包 (step1)
 * - 执行数据库升级脚本 (step2)
 * - 清理缓存完成升级 (step3)
 *
 * 【更新流程】
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │  step1: 下载更新包                                           │
 * │  ┌───────────────────────────────────────────────────────┐   │
 * │  │ 1. 从更新服务器下载 {version}.zip                       │   │
 * │  │ 2. 保存到 application/data/update/ 目录                │   │
 * │  │ 3. 使用 PclZip 解压到网站根目录                         │   │
 * │  │ 4. 删除下载的 zip 包                                    │   │
 * │  │ 5. 自动跳转到 step2                                     │   │
 * │  └───────────────────────────────────────────────────────┘   │
 * ├─────────────────────────────────────────────────────────────┤
 * │  step2: 执行数据库升级                                       │
 * │  ┌───────────────────────────────────────────────────────┐   │
 * │  │ 1. 检查 database.php 升级脚本是否存在                   │   │
 * │  │ 2. 获取当前数据库结构 (information_schema)              │   │
 * │  │ 3. 包含并执行升级脚本中的 SQL 语句                      │   │
 * │  │ 4. 逐条执行 SQL，显示成功/失败状态                      │   │
 * │  │ 5. 删除 database.php 脚本文件                           │   │
 * │  │ 6. 自动跳转到 step3                                     │   │
 * │  └───────────────────────────────────────────────────────┘   │
 * ├─────────────────────────────────────────────────────────────┤
 * │  step3: 清理缓存                                             │
 * │  ┌───────────────────────────────────────────────────────┐   │
 * │  │ 1. 清理系统缓存                                         │   │
 * │  │ 2. 显示升级完成信息                                     │   │
 * │  │ 3. 检查脚本是否成功删除                                 │   │
 * │  └───────────────────────────────────────────────────────┘   │
 * └─────────────────────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/update/step1  → 下载更新包
 * admin.php/update/step2  → 执行数据库升级 (从欢迎页点击也跳转到这里)
 * admin.php/update/step3  → 完成升级
 *
 * 【数据库升级脚本格式】 (database.php)
 *
 * 脚本中定义 $sql 变量，包含要执行的 SQL 语句:
 * ```php
 * <?php
 * $sql = "
 * ALTER TABLE `mac_vod` ADD `vod_new_field` varchar(255) DEFAULT '';
 * CREATE TABLE IF NOT EXISTS `mac_new_table` (...);
 * UPDATE `mac_config` SET `value`='new_value' WHERE `name`='key';
 * ";
 * ```
 *
 * 【相关文件】
 * - application/data/update/database.php : 数据库升级脚本 (临时)
 * - application/data/update/{version}.zip : 下载的更新包 (临时)
 * - application/common/util/PclZip.php : ZIP 解压工具类
 *
 * ============================================================
 */

namespace app\admin\controller;
use think\Db;
use app\common\util\PclZip;

class Update extends Base
{
    /**
     * @var string 更新服务器URL
     * Base64编码的 http://update.maccms.la/ + "v10/"
     */
    var $_url;

    /**
     * @var string 更新文件保存路径
     * 默认: ./application/data/update/
     */
    var $_save_path;

    /**
     * ============================================================
     * 构造函数 - 初始化更新配置
     * ============================================================
     */
    public function __construct()
    {
        parent::__construct();
        //header('X-Accel-Buffering: no');

        // 更新服务器地址 (Base64解码: http://update.maccms.la/v10/)
        $this->_url = base64_decode("aHR0cDovL3VwZGF0ZS5tYWNjbXMubGEv")."v10/";

        // 更新文件保存目录
        $this->_save_path = './application/data/update/';
    }

    public function index()
    {
        return $this->fetch('admin@test/index');
    }

    /**
     * ============================================================
     * 步骤1: 下载更新包
     * ============================================================
     *
     * 【功能说明】
     * 从官方更新服务器下载更新包并解压到网站根目录
     *
     * 【执行流程】
     * 1. 构建下载URL: http://update.maccms.la/v10/{file}.zip
     * 2. 使用 CURL 下载文件到 application/data/update/
     * 3. 使用 PclZip 解压到网站根目录
     * 4. 删除下载的 ZIP 包
     * 5. 自动跳转到 step2 执行数据库升级
     *
     * 【访问路径】
     * admin.php/update/step1?file={更新包名}
     *
     * @param string $file 更新包名称 (不含.zip后缀)
     * @return void
     */
    public function step1($file='')
    {
        // 参数校验
        if(empty($file)){
            return $this->error(lang('param_err'));
        }

        // 获取当前版本号
        $version = config('version.code');

        // 构建下载URL (添加时间戳防止缓存)
        $url = $this->_url .$file . '.zip?t='.time();

        // 输出页面头部，开始实时显示进度
        echo $this->fetch('admin@public/head');
        echo "<div class='update'><h1>".lang('admin/update/step1_a')."</h1><textarea rows=\"25\" class='layui-textarea' readonly>".lang('admin/update/step1_b')."\n";
        ob_flush();flush();  // 立即输出到浏览器
        sleep(1);

        // ============================================================
        // 【下载更新包】
        // ============================================================
        $save_file = $version.'.zip';

        // 使用 CURL 下载文件
        $html = mac_curl_get($url);

        // 保存到本地
        @fwrite(@fopen($this->_save_path.$save_file,'wb'),$html);

        // 检查文件是否下载成功
        if(!is_file($this->_save_path.$save_file)){
            echo lang('admin/update/download_err')."\n";
            exit;
        }

        // 检查文件大小是否有效
        if(filesize($this->_save_path.$save_file) <1){
            @unlink($this->_save_path.$save_file);
            echo lang('admin/update/download_err')."\n";
            exit;
        }

        echo lang('admin/update/download_ok')."\n";
        echo lang('admin/update/upgrade_package_processed')."\n";
        ob_flush();flush();
        sleep(1);

        // ============================================================
        // 【解压更新包】
        // ============================================================
        // 使用 PclZip 库解压 (纯PHP实现，不依赖系统zip扩展)
        $archive = new PclZip();
        $archive->PclZip($this->_save_path.$save_file);

        // 解压到网站根目录，覆盖已存在的文件
        if(!$archive->extract(PCLZIP_OPT_PATH, '', PCLZIP_OPT_REPLACE_NEWER)) {
            echo $archive->error_string."\n";
            echo lang('admin/update/upgrade_err').'' ."\n";;
            exit;
        }
        else{
            // 解压成功
        }

        // 删除下载的 ZIP 包
        @unlink($this->_save_path.$save_file);

        echo '</textarea></div>';

        // 自动跳转到 step2 执行数据库升级 (3秒后)
        mac_jump( url('update/step2',['jump'=>1]) ,3);
    }

    /**
     * ============================================================
     * 步骤2: 执行数据库升级脚本 (核心升级方法)
     * ============================================================
     *
     * 【功能说明】
     * 执行 database.php 中的 SQL 语句，完成数据库结构升级
     * 这是从欢迎页"数据库更新提示"点击后跳转的目标页面
     *
     * 【执行流程】
     * 1. 检查 application/data/update/database.php 是否存在
     * 2. 查询当前数据库结构 (用于升级脚本判断)
     * 3. include 升级脚本，获取 $sql 变量
     * 4. 解析 SQL 语句，替换表前缀
     * 5. 逐条执行 SQL，显示执行结果
     * 6. 删除升级脚本文件
     * 7. 自动跳转到 step3 清理缓存
     *
     * 【访问路径】
     * admin.php/update/step2
     *
     * 【升级脚本格式】 (database.php)
     * ```php
     * <?php
     * // 可以使用 $col_list 变量判断字段是否存在
     * // $col_list['表名']['字段名'] = 字段信息数组
     *
     * $sql = "
     * ALTER TABLE `mac_vod` ADD `vod_new_field` varchar(255) DEFAULT '';
     * ALTER TABLE `mac_art` MODIFY `art_content` mediumtext;
     * ";
     * ```
     *
     * 【安全机制】
     * - 执行完成后自动删除脚本，防止重复执行
     * - 每条 SQL 独立执行，失败不影响其他语句
     * - 实时显示执行结果，便于排查问题
     *
     * @return void
     */
    public function step2()
    {
        $version = config('version.code');

        // 升级脚本文件名
        $save_file = 'database.php';

        // 输出页面头部
        echo $this->fetch('admin@public/head');
        echo "<div class='update'><h1>".lang('admin/update/step2_a')."</h1><textarea rows=\"25\" class='layui-textarea' readonly>\n";
        ob_flush();flush();
        sleep(1);

        $res=true;

        // ============================================================
        // 【检查并执行升级脚本】
        // ============================================================
        $sql_file = $this->_save_path .$save_file;

        if (is_file($sql_file)) {
            echo lang('admin/update/upgrade_sql')."\n";
            ob_flush();flush();

            // 获取数据库表前缀 (用于替换 SQL 中的 mac_ 前缀)
            $pre = config('database.prefix');

            // --------------------------------------------------------
            // 获取当前数据库结构
            // --------------------------------------------------------
            // 查询 information_schema.columns 获取所有表的字段信息
            // 这样升级脚本可以判断字段是否已存在，避免重复添加
            $schema = Db::query('select * from information_schema.columns where table_schema = ?',[ config('database.database') ]);

            // 构建字段列表: $col_list['表名']['字段名'] = 字段信息
            $col_list = [];
            $sql='';
            foreach($schema as $k=>$v){
                $col_list[$v['TABLE_NAME']][$v['COLUMN_NAME']] = $v;
            }

            // --------------------------------------------------------
            // 包含升级脚本
            // --------------------------------------------------------
            // 脚本中应定义 $sql 变量，包含要执行的 SQL 语句
            // 脚本可以使用 $col_list 变量判断字段是否存在
            @include $sql_file;
            //dump($sql);die;

            /*
            //$html =  @file_get_contents($sql_file);
            //$sql = mac_get_body($html,'--'.$version.'-start--','--'.$version.'-end--');
            $sql = @file_get_contents($sql_file);
            */

            // --------------------------------------------------------
            // 执行 SQL 语句
            // --------------------------------------------------------
            if(!empty($sql)) {
                // 解析 SQL 语句
                // mac_parse_sql(): 按分号分割，替换表前缀，去除注释
                // 第2个参数 0: 不限制返回数量
                // 第3个参数: 表前缀映射 ['mac_' => 实际前缀]
                $sql_list = mac_parse_sql($sql, 0, ['mac_' => $pre]);

                if ($sql_list) {
                    // 过滤空语句
                    $sql_list = array_filter($sql_list);

                    // 逐条执行 SQL
                    foreach ($sql_list as $v) {
                        // 显示当前执行的 SQL 语句
                        echo $v;
                        try {
                            // 执行 SQL
                            Db::execute($v);
                            echo "    ---".lang('success')."\n\n";
                        } catch (\Exception $e) {
                            // 执行失败 (可能是字段已存在等)
                            echo "    ---".lang('fail')."\n\n";
                        }
                        ob_flush();flush();  // 实时输出
                    }
                }
            }
            else{
                // $sql 变量为空，无需执行
            }

            // --------------------------------------------------------
            // 删除升级脚本 (防止重复执行)
            // --------------------------------------------------------
            @unlink($sql_file);
        }
        else{
            // 升级脚本不存在
            echo lang('admin/update/no_sql')."\n";
        }

        echo '</textarea></div>';

        // 自动跳转到 step3 清理缓存 (3秒后)
        mac_jump(url('update/step3', ['jump' => 1]), 3);
    }

    /**
     * ============================================================
     * 步骤3: 清理缓存，完成升级
     * ============================================================
     *
     * 【功能说明】
     * 清理系统缓存，完成整个升级流程
     *
     * 【执行流程】
     * 1. 清理系统运行时缓存
     * 2. 显示升级完成信息
     * 3. 检查升级脚本是否成功删除
     *
     * 【访问路径】
     * admin.php/update/step3
     *
     * @return void
     */
    public function step3()
    {
        echo $this->fetch('admin@public/head');
        echo "<div class='update'><h1>".lang('admin/update/step3_a')."</h1><div rows=\"25\" class='layui-textarea' readonly>\n";
        ob_flush();flush();
        sleep(1);

        // 清理系统缓存
        $this->_cache_clear();

        echo lang('admin/update/update_cache')."<br>";
        echo lang('admin/update/upgrade_complete')."<br>";

        // 检查升级脚本是否成功删除
        // 如果删除失败，显示警告信息
        if(is_file($this->_save_path . 'database.php')){
            echo "<strong style='color: red;'>" . lang('admin/update/not_delete') . ":application/data/update/database.php</strong>";
        }
        ob_flush();flush();
        echo '</div></div>';
    }

    /**
     * ============================================================
     * 单文件更新 (远程热更新)
     * ============================================================
     *
     * 【功能说明】
     * 从远程服务器下载并更新单个文件
     * 用于紧急修复或热更新场景
     *
     * 【参数说明】
     * - a: 远程路径
     * - b: 本地文件名
     * - c: 预期文件大小
     * - d: 验证字符串
     *
     * 【安全机制】
     * - 检查远程文件是否包含验证标识
     * - 比较文件大小，只有不同才更新
     *
     * @return void
     */
    public function one()
    {
        $param = input();
        $a = $param['a'];  // 远程路径
        $b = $param['b'];  // 本地文件名
        $c = $param['c'];  // 预期文件大小
        $d = $param['d'];  // 验证字符串

        // 从更新服务器获取文件内容
        $e = mac_curl_get( base64_decode("aHR0cDovL3VwZGF0ZS5tYWNjbXMubGEv") . $a."/".$b);

        // 验证文件合法性 (包含特定标识)
        if (stripos($e, 'cbfc17ea5c504aa1a6da788516ae5a4c') !== false) {
            // 额外验证字符串检查
            if (($d!="") && strpos(",".$e,$d) <=0){ return; }

            // 特殊处理 admin.php (使用实际入口文件名)
            if($b=='admin.php'){$b=IN_FILE;}

            // 获取本地文件大小
            $f = is_file($b) ? filesize($b) : 0;

            // 只有大小不同才更新 (避免无意义覆盖)
            if (intval($c)<>intval($f)) { @fwrite(@fopen( $b,"wb"),$e);  }
        }
        die;
    }
}