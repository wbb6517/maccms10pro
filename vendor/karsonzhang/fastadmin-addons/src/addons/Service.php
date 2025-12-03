<?php
/**
 * 插件服务类 (Addon Service)
 * ============================================================
 *
 * 【功能说明】
 * 提供插件的安装、卸载、启用、禁用、升级等核心操作
 * 是插件系统的核心服务类
 *
 * 【方法列表】
 * ┌────────────────────────┬────────────────────────────────────────────┐
 * │ 方法名                  │ 说明                                        │
 * ├────────────────────────┼────────────────────────────────────────────┤
 * │ download()             │ 从远程服务器下载插件ZIP包                    │
 * │ unzip()                │ 解压插件ZIP包到插件目录                      │
 * │ backup()               │ 备份插件（打包为ZIP）                        │
 * │ check()                │ 检测插件完整性（主类和配置）                 │
 * │ noconflict()           │ 检测插件文件是否与现有文件冲突               │
 * │ importsql()            │ 导入插件的 install.sql 文件                  │
 * │ refresh()              │ 刷新插件缓存（addons.js 和 addons.php）      │
 * │ install()              │ 安装插件（核心方法）                         │
 * │ uninstall()            │ 卸载插件                                    │
 * │ enable()               │ 启用插件                                    │
 * │ disable()              │ 禁用插件                                    │
 * │ upgrade()              │ 升级插件                                    │
 * │ getGlobalFiles()       │ 获取插件的全局文件列表                       │
 * │ getSourceAssetsDir()   │ 获取插件源资源目录                          │
 * │ getDestAssetsDir()     │ 获取插件目标资源目录                        │
 * │ getCheckDirs()         │ 获取需要检查的全局目录列表                   │
 * └────────────────────────┴────────────────────────────────────────────┘
 *
 * 【插件安装流程】
 * 下载ZIP → 解压 → 检查完整性 → 检查冲突 → 复制文件 → 执行install() → 导入SQL → 刷新缓存
 *
 * 【文件复制规则】
 * addons/{name}/assets/       → static/addons/{name}/     （资源文件）
 * addons/{name}/application/  → application/              （应用文件）
 * addons/{name}/static/       → static/                   （静态文件）
 *
 * 【相关文件】
 * - application/admin/controller/Addon.php : 后台控制器
 * - vendor/.../src/common.php : 插件公共函数
 *
 * ============================================================
 */
namespace think\addons;

use fast\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use think\Db;
use think\Exception;
use ZipArchive;

/**
 * 插件服务类
 * @package think\addons
 */
class Service
{

    /**
     * 远程下载插件
     *
     * @param string $name   插件名称
     * @param array  $extend 扩展参数
     * @return  string
     * @throws  AddonException
     * @throws  Exception
     */
    public static function download($name, $extend = [])
    {
        $addonTmpDir = RUNTIME_PATH . 'addons' . DS;
        if (!is_dir($addonTmpDir)) {
            @mkdir($addonTmpDir, 0755, true);
        }
        $tmpFile = $addonTmpDir . $name . ".zip";
        $options = [
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'X-REQUESTED-WITH: XMLHttpRequest'
            ]
        ];
        $ret = Http::sendRequest(self::getServerUrl() . '/addon/download', array_merge(['name' => $name], $extend), 'GET', $options);
        if ($ret['ret']) {
            if (substr($ret['msg'], 0, 1) == '{') {
                $json = (array)json_decode($ret['msg'], true);
                //如果传回的是一个下载链接,则再次下载
                if ($json['data'] && isset($json['data']['url'])) {
                    array_pop($options);
                    $ret = Http::sendRequest($json['data']['url'], [], 'GET', $options);
                    if (!$ret['ret']) {
                        //下载返回错误，抛出异常
                        throw new AddonException($json['msg'], $json['code'], $json['data']);
                    }
                } else {
                    //下载返回错误，抛出异常
                    throw new AddonException($json['msg'], $json['code'], $json['data']);
                }
            }
            if ($write = fopen($tmpFile, 'w')) {
                fwrite($write, $ret['msg']);
                fclose($write);
                return $tmpFile;
            }
            throw new Exception("没有权限写入临时文件");
        }
        throw new Exception("无法下载远程文件");
    }

    /**
     * 解压插件
     *
     * @param string $name 插件名称
     * @return  string
     * @throws  Exception
     */
    public static function unzip($name)
    {
        $file = RUNTIME_PATH . 'addons' . DS . $name . '.zip';
        $dir = ADDON_PATH . $name . DS;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($file) !== true) {
                throw new Exception('Unable to open the zip file');
            }
            if (!$zip->extractTo($dir)) {
                $zip->close();
                throw new Exception('Unable to extract the file');
            }
            $zip->close();
            return $dir;
        }
        throw new Exception("无法执行解压操作，请确保ZipArchive安装正确");
    }

    /**
     * 备份插件
     * @param string $name 插件名称
     * @return bool
     * @throws Exception
     */
    public static function backup($name)
    {
        $file = RUNTIME_PATH . 'addons' . DS . $name . '-backup-' . date("YmdHis") . '.zip';
        $dir = ADDON_PATH . $name . DS;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            $zip->open($file, ZipArchive::CREATE);
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $filePath = $fileinfo->getPathName();
                $localName = str_replace($dir, '', $filePath);
                if ($fileinfo->isFile()) {
                    $zip->addFile($filePath, $localName);
                } elseif ($fileinfo->isDir()) {
                    $zip->addEmptyDir($localName);
                }
            }
            $zip->close();
            return true;
        }
        throw new Exception("无法执行压缩操作，请确保ZipArchive安装正确");
    }

    /**
     * 检测插件是否完整
     *
     * @param string $name 插件名称
     * @return  boolean
     * @throws  Exception
     */
    public static function check($name)
    {
        if (!$name || !is_dir(ADDON_PATH . $name)) {
            throw new Exception('Addon not exists');
        }
        $addonClass = get_addon_class($name);
        if (!$addonClass) {
            throw new Exception("插件主启动程序不存在");
        }
        $addon = new $addonClass();
        if (!$addon->checkInfo()) {
            throw new Exception("配置文件不完整");
        }
        return true;
    }

    /**
     * 是否有冲突
     *
     * @param string $name 插件名称
     * @return  boolean
     * @throws  AddonException
     */
    public static function noconflict($name)
    {
        // 检测冲突文件
        $list = self::getGlobalFiles($name, true);
        if ($list) {
            //发现冲突文件，抛出异常
            throw new AddonException("发现冲突文件", -3, ['conflictlist' => $list]);
        }
        return true;
    }

    /**
     * 导入SQL
     *
     * @param string $name 插件名称
     * @return  boolean
     */
    public static function importsql($name)
    {
        $sqlFile = ADDON_PATH . $name . DS . 'install.sql';
        if (is_file($sqlFile)) {
            $lines = file($sqlFile);
            $templine = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*') {
                    continue;
                }

                $templine .= $line;
                if (substr(trim($line), -1, 1) == ';') {
                    $templine = str_ireplace('__PREFIX__', config('database.prefix'), $templine);
                    $templine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $templine);
                    try {
                        Db::getPdo()->exec($templine);
                    } catch (\PDOException $e) {
                        //$e->getMessage();
                    }
                    $templine = '';
                }
            }
        }
        return true;
    }

    /**
     * ============================================================
     * 刷新插件缓存文件
     * ============================================================
     *
     * 【功能说明】
     * 在插件安装/卸载/启用/禁用后调用
     * 重新生成插件相关的缓存文件
     *
     * 【刷新流程】
     * ┌─────────────────────────────────────────────────────────────┐
     * │ 1. 获取插件列表  →  调用 get_addon_list() 获取所有插件      │
     * │ 2. 合并JS文件   →  合并所有启用插件的 bootstrap.js          │
     * │ 3. 写入addons.js →  写入到 static/js/addons.js             │
     * │ 4. 生成配置     →  调用 get_addon_autoload_config()        │
     * │ 5. 写入配置文件  →  写入到 application/extra/addons.php    │
     * └─────────────────────────────────────────────────────────────┘
     *
     * 【生成的文件】
     * 1. static/js/addons.js
     *    - 合并所有启用插件的 bootstrap.js 文件
     *    - 用于前端 RequireJS 模块加载
     *    - 格式：define([], function () { ... });
     *
     * 2. application/extra/addons.php
     *    - 插件自动加载配置
     *    - 包含路由规则和钩子配置
     *
     * 【注意事项】
     * - 需要 static/js/ 目录存在且可写
     * - 需要 application/extra/addons.php 文件可写
     * - 如果目录/文件不存在会抛出异常
     *
     * @return  boolean
     * @throws  Exception
     */
    public static function refresh()
    {
        // ========== 第1步：获取所有插件列表 ==========
        // get_addon_list() 扫描 addons/ 目录获取所有已安装插件
        $addons = get_addon_list();

        // ========== 第2步：收集所有启用插件的 bootstrap.js ==========
        // bootstrap.js 是插件的前端初始化脚本
        // 只有启用状态(state=1)的插件才会被合并
        $bootstrapArr = [];
        foreach ($addons as $name => $addon) {
            // 构建 bootstrap.js 文件路径
            $bootstrapFile = ADDON_PATH . $name . DS . 'bootstrap.js';

            // 检查插件是否启用 且 bootstrap.js 文件存在
            if ($addon['state'] && is_file($bootstrapFile)) {
                // 读取文件内容添加到数组
                $bootstrapArr[] = file_get_contents($bootstrapFile);
            }
        }

        // ========== 第3步：写入 addons.js 文件 ==========
        // 目标文件：static/js/addons.js 和 static_new/js/addons.js（如果存在）
        // 用于前端 RequireJS 模块加载
        // 定义 AMD 模块模板
        $tpl = <<<EOD
define([], function () {
    {__JS__}
});
EOD;
        $jsContent = str_replace("{__JS__}", implode("\n", $bootstrapArr), $tpl);

        // 动态写入到所有存在的静态目录
        $staticDirs = self::getStaticDirs();
        $writeSuccess = false;

        foreach ($staticDirs as $staticDir) {
            $addonsFile = ROOT_PATH . $staticDir . DS . 'js' . DS . 'addons.js';
            // 检查 js 目录是否存在
            $jsDir = dirname($addonsFile);
            if (!is_dir($jsDir)) {
                continue; // js 目录不存在则跳过
            }

            if ($handle = fopen($addonsFile, 'w')) {
                fwrite($handle, $jsContent);
                fclose($handle);
                $writeSuccess = true;
            }
        }

        // 如果没有任何目录可写，抛出异常
        if (!$writeSuccess && !empty($staticDirs)) {
            throw new Exception("addons.js文件没有写入权限，或者不存在！");
        }

        // ========== 第4步：生成插件自动加载配置 ==========
        // 配置文件路径：application/extra/addons.php
        $file = APP_PATH . 'extra' . DS . 'addons.php';

        // 获取插件自动加载配置
        // 参数 true 表示清空手动配置的钩子，重新生成
        // 返回数组包含：hooks(钩子)、route(路由)、autoload(自动加载开关)
        $config = get_addon_autoload_config(true);

        // 如果开启了自动加载模式，则不需要写入配置文件
        // 自动加载模式下，配置会被缓存
        if ($config['autoload']) {
            return;
        }

        // ========== 第5步：写入配置文件 ==========
        // 检查文件是否可写
        if (!is_really_writable($file)) {
            throw new Exception("addons.php文件没有写入权限");
        }

        if ($handle = fopen($file, 'w')) {
            // 将配置数组导出为 PHP 代码
            // 格式：<?php return [...];
            fwrite($handle, "<?php\n\n" . "return " . var_export($config, true) . ";");
            fclose($handle);
        } else {
            throw new Exception("文件没有写入权限");
        }
        return true;
    }

    /**
     * ============================================================
     * 安装插件（核心方法）
     * ============================================================
     *
     * 【功能说明】
     * 完整的插件安装流程，包括下载、解压、检查、复制文件、执行安装脚本等
     *
     * 【安装流程】
     * ┌─────────────────────────────────────────────────────────────┐
     * │ 1. 下载ZIP  →  从远程服务器下载插件压缩包                    │
     * │ 2. 解压文件  →  解压到 addons/{name}/ 目录                  │
     * │ 3. 完整性检查 →  检查主类和配置文件是否存在                   │
     * │ 4. 冲突检查  →  检查是否与现有文件冲突                       │
     * │ 5. 复制文件  →  复制 assets/application/static 到目标位置   │
     * │ 6. 启用插件  →  设置 state=1                                │
     * │ 7. 执行脚本  →  调用插件的 install() 方法                   │
     * │ 8. 导入SQL   →  执行 install.sql 文件                       │
     * │ 9. 刷新缓存  →  更新 addons.js 和 addons.php                │
     * └─────────────────────────────────────────────────────────────┘
     *
     * 【文件复制规则】
     * addons/{name}/assets/       → static/addons/{name}/
     * addons/{name}/application/  → application/
     * addons/{name}/static/       → static/
     *
     * @param string  $name   插件名称
     * @param boolean $force  是否覆盖（强制安装，忽略冲突）
     * @param array   $extend 扩展参数（uid/token/version等）
     * @return  boolean
     * @throws  Exception
     * @throws  AddonException
     */
    public static function install($name, $force = false, $extend = [])
    {
        // ========== 第1步：验证插件是否已存在 ==========
        // 如果插件目录已存在且非强制安装，则抛出异常
        if (!$name || (is_dir(ADDON_PATH . $name) && !$force)) {
            throw new Exception('Addon already exists');
        }

        // ========== 第2步：远程下载插件 ==========
        // 从远程服务器下载插件 ZIP 包
        // 保存到临时目录：runtime/addons/{name}.zip
        $tmpFile = Service::download($name, $extend);

        // ========== 第3步：解压插件 ==========
        // 使用 ZipArchive 解压到插件目录：addons/{name}/
        $addonDir = Service::unzip($name);

        // 移除临时ZIP文件，释放磁盘空间
        @unlink($tmpFile);

        try {
            // ========== 第4步：检查插件完整性 ==========
            // 检查项目：
            // - 插件目录是否存在
            // - 插件主类 {Name}.php 是否存在
            // - info.ini 配置是否完整
            Service::check($name);

            // ========== 第5步：检查文件冲突 ==========
            // 扫描 application/ 和 static/ 目录
            // 检查是否与现有文件冲突（大小或MD5不同）
            // force=true 时跳过此检查
            if (!$force) {
                Service::noconflict($name);
            }
        } catch (AddonException $e) {
            // 检查失败，删除已解压的插件目录
            @rmdirs($addonDir);
            throw new AddonException($e->getMessage(), $e->getCode(), $e->getData());
        } catch (Exception $e) {
            @rmdirs($addonDir);
            throw new Exception($e->getMessage());
        }

        // ========== 第6步：复制文件 ⭐ 关键步骤 ==========
        // 【复制资源文件】
        // 源目录：addons/{name}/assets/
        // 目标目录：static/addons/{name}/ 和 static_new/addons/{name}/（如果存在）
        $sourceAssetsDir = self::getSourceAssetsDir($name);
        if (is_dir($sourceAssetsDir)) {
            // 复制到所有存在的静态目录
            foreach (self::getDestAssetsDirs($name, true) as $destAssetsDir) {
                copydirs($sourceAssetsDir, $destAssetsDir);
            }
        }

        // 【复制全局文件】
        // 遍历 getCheckDirs() 返回的目录列表：['application', 'static']
        // 如果插件中存在这些目录，则复制到项目根目录
        // addons/{name}/application/ → ROOT_PATH/application/
        // addons/{name}/static/      → ROOT_PATH/static/
        foreach (self::getCheckDirs() as $k => $dir) {
            if (is_dir($addonDir . $dir)) {
                copydirs($addonDir . $dir, ROOT_PATH . $dir);
            }
        }

        try {
            // ========== 第7步：启用插件 ==========
            // 读取插件 info.ini，设置 state=1（启用状态）
            $info = get_addon_info($name);
            if (!$info['state']) {
                $info['state'] = 1;
                set_addon_info($name, $info);
            }

            // ========== 第8步：执行插件安装脚本 ==========
            // 获取插件主类的完整类名：\addons\{name}\{Name}
            // 实例化插件类并调用 install() 方法
            // 插件可以在 install() 中执行自定义安装逻辑：
            // - 创建数据表
            // - 初始化配置
            // - 注册菜单
            // - 复制额外文件等
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();
                $addon->install();
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // ========== 第9步：导入SQL ==========
        // 读取并执行 addons/{name}/install.sql 文件
        // 自动替换 __PREFIX__ 为实际数据库前缀
        Service::importsql($name);

        // ========== 第10步：刷新插件缓存 ==========
        // - 合并所有启用插件的 bootstrap.js → static/js/addons.js
        // - 重新生成 application/extra/addons.php 配置
        Service::refresh();
        return true;
    }

    /**
     * ============================================================
     * 卸载插件
     * ============================================================
     *
     * 【功能说明】
     * 完整卸载插件，包括删除资源文件、执行卸载脚本、删除插件目录
     *
     * 【卸载流程】
     * ┌─────────────────────────────────────────────────────────────┐
     * │ 1. 验证插件  →  检查插件目录是否存在                         │
     * │ 2. 冲突检查  →  检查文件冲突（非强制时）                     │
     * │ 3. 删除资源  →  删除 static/addons/{name}/ 目录             │
     * │ 4. 删除全局  →  删除复制到 application/ static/ 的文件      │
     * │ 5. 执行脚本  →  调用插件的 uninstall() 方法                 │
     * │ 6. 删除目录  →  删除整个 addons/{name}/ 目录                │
     * │ 7. 刷新缓存  →  更新 addons.js 和 addons.php                │
     * └─────────────────────────────────────────────────────────────┘
     *
     * @param string  $name   插件名称
     * @param boolean $force  是否强制卸载（忽略冲突，删除全局文件）
     * @return  boolean
     * @throws  Exception
     */
    public static function uninstall($name, $force = false)
    {
        // ========== 第1步：验证插件是否存在 ==========
        if (!$name || !is_dir(ADDON_PATH . $name)) {
            throw new Exception('Addon not exists');
        }

        // ========== 第2步：检查文件冲突 ==========
        // 非强制卸载时，检查是否有文件冲突
        if (!$force) {
            Service::noconflict($name);
        }

        // ========== 第3步：移除插件基础资源目录 ==========
        // 删除 static/addons/{name}/ 和 static_new/addons/{name}/（如果存在）
        // 这是安装时从 addons/{name}/assets/ 复制过来的
        foreach (self::getDestAssetsDirs($name) as $destAssetsDir) {
            if (is_dir($destAssetsDir)) {
                rmdirs($destAssetsDir);
            }
        }

        // ========== 第4步：移除插件全局资源文件 ==========
        // 仅在强制卸载时执行
        // 删除安装时复制到 application/ 和 static/ 的文件
        if ($force) {
            $list = Service::getGlobalFiles($name);
            foreach ($list as $k => $v) {
                @unlink(ROOT_PATH . $v);
            }
        }

        // ========== 第5步：执行插件卸载脚本 ==========
        // 获取插件主类并调用 uninstall() 方法
        // 插件可以在此方法中执行清理操作：
        // - 删除自定义数据表
        // - 清理配置
        // - 移除菜单等
        try {
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();
                $addon->uninstall();
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // ========== 第6步：移除插件目录 ==========
        // 删除整个 addons/{name}/ 目录
        rmdirs(ADDON_PATH . $name);

        // ========== 第7步：刷新插件缓存 ==========
        Service::refresh();
        return true;
    }

    /**
     * ============================================================
     * 启用插件
     * ============================================================
     *
     * 【功能说明】
     * 启用已安装但处于禁用状态的插件
     * 重新复制资源文件并设置状态为启用
     *
     * 【启用流程】
     * ┌─────────────────────────────────────────────────────────────┐
     * │ 1. 验证插件  →  检查插件目录是否存在                         │
     * │ 2. 冲突检查  →  检查文件冲突（非强制时）                     │
     * │ 3. 复制文件  →  复制 static/assets/view/application 等     │
     * │ 4. 执行脚本  →  调用插件的 enable() 方法                    │
     * │ 5. 更新状态  →  设置 info.ini 中 state=1                    │
     * │ 6. 刷新缓存  →  更新 addons.js 和 addons.php                │
     * └─────────────────────────────────────────────────────────────┘
     *
     * 【文件复制规则】（与安装类似但有扩展）
     * addons/{name}/static/       → static_new/{name}/
     * addons/{name}/assets/       → static_new/addons/{name}/ 和 static/addons/{name}/
     * addons/{name}/view/         → application/admin/view_new/{name}/
     * addons/{name}/application/  → application/
     * addons/{name}/static/       → static/
     *
     * @param string  $name  插件名称
     * @param boolean $force 是否强制启用（忽略冲突）
     * @return  boolean
     * @throws  Exception
     */
    public static function enable($name, $force = false)
    {
        // ========== 第1步：验证插件是否存在 ==========
        if (!$name || !is_dir(ADDON_PATH . $name)) {
            throw new Exception('Addon not exists');
        }

        // ========== 第2步：检查文件冲突 ==========
        if (!$force) {
            Service::noconflict($name);
        }

        $addonDir = ADDON_PATH . $name . DS;

        // ========== 第3步：复制文件 ⭐ 关键步骤 ==========
        $sourceAssetsDir = self::getSourceAssetsDir($name);
        $destAssetsDir = self::getDestAssetsDir($name);

        // 【复制静态资源到 static_new】
        // addons/{name}/static/ → static_new/{name}/
        $staticSource = $addonDir . 'static/';
        $staticDest = ROOT_PATH . 'static_new/' . $name . '/';
        if (is_dir($staticSource)) {
            copydirs($staticSource, $staticDest);
        }

        // 【复制 assets 到 static_new/addons】
        // addons/{name}/assets/ → static_new/addons/{name}/
        $staticSourceAsset = $addonDir . 'assets/';
        $staticAssetDest = ROOT_PATH . 'static_new/addons/' . $name . '/';
        if (is_dir($staticSourceAsset)) {
            copydirs($staticSourceAsset, $staticAssetDest);
        }

        // 【复制视图文件】
        // addons/{name}/view/ → application/admin/view_new/{name}/
        $viewSource = $addonDir . 'view/';
        $viewDest = APP_PATH . 'admin/view_new/' . $name . '/';
        if (is_dir($viewSource)) {
            copydirs($viewSource, $viewDest);
        }

        // 【复制 assets 到 static/addons】
        // addons/{name}/assets/ → static/addons/{name}/
        if (is_dir($sourceAssetsDir)) {
            copydirs($sourceAssetsDir, $destAssetsDir);
        }

        // 【复制全局文件】
        // addons/{name}/application/ → application/
        // addons/{name}/static/      → static/
        foreach (self::getCheckDirs() as $k => $dir) {
            if (is_dir($addonDir . $dir)) {
                copydirs($addonDir . $dir, ROOT_PATH . $dir);
            }
        }

        // ========== 第4步：执行插件启用脚本 ==========
        // 调用插件的 enable() 方法（如果存在）
        // 插件可以在此方法中执行启用时的初始化操作
        try {
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();
                if (method_exists($class, "enable")) {
                    $addon->enable();
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // ========== 第5步：更新插件状态 ==========
        // 读取 info.ini，设置 state=1（启用）
        // 移除动态生成的 url 字段后写回文件
        $info = get_addon_info($name);
        $info['state'] = 1;
        unset($info['url']);

        set_addon_info($name, $info);

        // ========== 第6步：刷新插件缓存 ==========
        Service::refresh();
        return true;
    }

    /**
     * ============================================================
     * 禁用插件
     * ============================================================
     *
     * 【功能说明】
     * 禁用已启用的插件
     * 删除资源文件但保留插件目录（可重新启用）
     *
     * 【禁用流程】
     * ┌─────────────────────────────────────────────────────────────┐
     * │ 1. 验证插件  →  检查插件目录是否存在                         │
     * │ 2. 冲突检查  →  检查文件冲突（非强制时）                     │
     * │ 3. 删除资源  →  删除 static/addons/{name}/ 目录             │
     * │ 4. 删除全局  →  删除复制到 application/ static/ 的文件      │
     * │ 5. 清理目录  →  删除空目录                                  │
     * │ 6. 删除扩展  →  删除 static_new/ view_new/ 中的文件         │
     * │ 7. 更新状态  →  设置 info.ini 中 state=0                    │
     * │ 8. 执行脚本  →  调用插件的 disable() 方法                   │
     * │ 9. 刷新缓存  →  更新 addons.js 和 addons.php                │
     * └─────────────────────────────────────────────────────────────┘
     *
     * 【与卸载的区别】
     * - 禁用：保留 addons/{name}/ 目录，可重新启用
     * - 卸载：删除整个插件目录，需要重新安装
     *
     * @param string  $name  插件名称
     * @param boolean $force 是否强制禁用（忽略冲突）
     * @return  boolean
     * @throws  Exception
     */
    public static function disable($name, $force = false)
    {
        // ========== 第1步：验证插件是否存在 ==========
        if (!$name || !is_dir(ADDON_PATH . $name)) {
            throw new Exception('Addon not exists');
        }

        // ========== 第2步：检查文件冲突 ==========
        if (!$force) {
            Service::noconflict($name);
        }

        // ========== 第3步：移除插件基础资源目录 ==========
        // 删除 static/addons/{name}/ 和 static_new/addons/{name}/（如果存在）
        foreach (self::getDestAssetsDirs($name) as $destAssetsDir) {
            if (is_dir($destAssetsDir)) {
                rmdirs($destAssetsDir);
            }
        }

        $dirs = [];

        // ========== 第4步：移除插件全局资源文件 ==========
        // 删除复制到 application/ 和 static/ 的文件
        // 同时记录这些文件所在的目录，用于后续清理空目录
        $list = Service::getGlobalFiles($name);
        foreach ($list as $k => $v) {
            $dirs[] = dirname(ROOT_PATH . $v);
            @unlink(ROOT_PATH . $v);
        }

        // ========== 第5步：移除空目录 ==========
        // 删除文件后，清理可能产生的空目录
        // remove_empty_folder() 会递归向上删除空目录
        $dirs = array_filter(array_unique($dirs));
        foreach ($dirs as $k => $v) {
            remove_empty_folder($v);
        }

        // ========== 第6步：删除扩展目录中的文件 ==========
        // 删除启用时复制到 static_new/ 和 view_new/ 的文件

        // 删除 static_new/{name}/
        $staticDest = ROOT_PATH . 'static_new/' . $name . '/';
        if (is_dir($staticDest)) {
            rmdirs($staticDest);
        }

        // 删除 static_new/addons/{name}/
        $staticAssetDest = ROOT_PATH . 'static_new/addons/' . $name . '/';
        if (is_dir($staticAssetDest)) {
            rmdirs($staticAssetDest);
        }

        // 删除 application/admin/view_new/{name}/
        $viewDest = APP_PATH . 'admin/view_new/' . $name . '/';
        if (is_dir($viewDest)) {
            rmdirs($viewDest);
        }

        // ========== 第7步：更新插件状态 ==========
        // 读取 info.ini，设置 state=0（禁用）
        $info = get_addon_info($name);
        $info['state'] = 0;
        unset($info['url']);

        set_addon_info($name, $info);

        // ========== 第8步：执行插件禁用脚本 ==========
        // 调用插件的 disable() 方法（如果存在）
        // 插件可以在此方法中执行禁用时的清理操作
        try {
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();

                if (method_exists($class, "disable")) {
                    $addon->disable();
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // ========== 第9步：刷新插件缓存 ==========
        Service::refresh();
        return true;
    }

    /**
     * 升级插件
     *
     * @param string $name   插件名称
     * @param array  $extend 扩展参数
     */
    public static function upgrade($name, $extend = [])
    {
        $info = get_addon_info($name);
        if ($info['state']) {
            throw new Exception(__('Please disable addon first'));
        }
        $config = get_addon_config($name);
        if ($config) {
            //备份配置
        }

        // 备份插件文件
        Service::backup($name);

        // 远程下载插件
        $tmpFile = Service::download($name, $extend);

        // 解压插件
        $addonDir = Service::unzip($name);

        // 移除临时文件
        @unlink($tmpFile);

        if ($config) {
            // 还原配置
            set_addon_config($name, $config);
        }

        // 导入
        Service::importsql($name);

        // 执行升级脚本
        try {
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();

                if (method_exists($class, "upgrade")) {
                    $addon->upgrade();
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // 刷新
        Service::refresh();

        return true;
    }

    /**
     * 获取插件在全局的文件
     *
     * @param string $name 插件名称
     * @return  array
     */
    public static function getGlobalFiles($name, $onlyconflict = false)
    {
        $list = [];
        $addonDir = ADDON_PATH . $name . DS;
        // 扫描插件目录是否有覆盖的文件
        foreach (self::getCheckDirs() as $k => $dir) {
            $checkDir = ROOT_PATH . DS . $dir . DS;
            if (!is_dir($checkDir)) {
                continue;
            }
            //检测到存在插件外目录
            if (is_dir($addonDir . $dir)) {
                //匹配出所有的文件
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($addonDir . $dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    if ($fileinfo->isFile()) {
                        $filePath = $fileinfo->getPathName();
                        $path = str_replace($addonDir, '', $filePath);
                        if ($onlyconflict) {
                            $destPath = ROOT_PATH . $path;
                            if (is_file($destPath)) {
                                if (filesize($filePath) != filesize($destPath) || md5_file($filePath) != md5_file($destPath)) {
                                    $list[] = $path;
                                }
                            }
                        } else {
                            $list[] = $path;
                        }
                    }
                }
            }
        }
        return $list;
    }

    /**
     * 获取插件源资源文件夹
     * @param string $name 插件名称
     * @return  string
     */
    protected static function getSourceAssetsDir($name)
    {
        return ADDON_PATH . $name . DS . 'assets' . DS;
    }

    /**
     * 获取插件目标资源文件夹（单个，向后兼容）
     * @param string $name 插件名称
     * @return  string
     */
    protected static function getDestAssetsDir($name)
    {
        $assetsDir = ROOT_PATH . str_replace("/", DS, "static/addons/{$name}/");
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }
        return $assetsDir;
    }

    /**
     * ============================================================
     * 获取插件目标资源文件夹列表（多个静态目录）
     * ============================================================
     *
     * 【功能说明】
     * 返回所有存在的静态目录下的插件资源目录
     * 用于同时复制/删除 static/addons/{name}/ 和 static_new/addons/{name}/
     *
     * 【返回规则】
     * - static 存在：包含 static/addons/{name}/
     * - static_new 存在：包含 static_new/addons/{name}/
     * - 目录不存在时不会自动创建
     *
     * @param string $name 插件名称
     * @param bool $autoCreate 是否自动创建目录
     * @return array 目标资源目录列表
     */
    protected static function getDestAssetsDirs($name, $autoCreate = false)
    {
        $dirs = [];
        foreach (self::getStaticDirs() as $staticDir) {
            $assetsDir = ROOT_PATH . $staticDir . DS . 'addons' . DS . $name . DS;
            if ($autoCreate && !is_dir($assetsDir)) {
                mkdir($assetsDir, 0755, true);
            }
            $dirs[] = $assetsDir;
        }
        return $dirs;
    }

    /**
     * 获取远程服务器
     * @return  string
     */
    protected static function getServerUrl()
    {
        return config('fastadmin.api_url');
    }

    /**
     * 获取检测的全局文件夹目录
     * 动态检测 static 和 static_new 目录
     * @return  array
     */
    protected static function getCheckDirs()
    {
        $dirs = ['application'];

        // 动态检测静态目录
        foreach (self::getStaticDirs() as $staticDir) {
            $dirs[] = $staticDir;
        }

        return $dirs;
    }

    /**
     * ============================================================
     * 获取存在的静态资源目录列表
     * ============================================================
     *
     * 【功能说明】
     * 动态检测项目中存在的静态资源目录
     * 支持 static 和 static_new 两个目录
     *
     * 【返回规则】
     * - 两个目录都存在：返回 ['static', 'static_new']
     * - 只有 static 存在：返回 ['static']
     * - 只有 static_new 存在：返回 ['static_new']
     * - 都不存在：返回空数组
     *
     * @return array 存在的静态目录列表
     */
    protected static function getStaticDirs()
    {
        $staticDirs = [];
        $possibleDirs = ['static', 'static_new'];

        foreach ($possibleDirs as $dir) {
            if (is_dir(ROOT_PATH . $dir)) {
                $staticDirs[] = $dir;
            }
        }

        return $staticDirs;
    }

}
