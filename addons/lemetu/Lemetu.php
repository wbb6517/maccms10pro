<?php
/**
 * 乐美兔插件主类 (Lemetu Addon)
 * ============================================================
 *
 * 【功能说明】
 * 乐美兔 APP API 插件的主类文件
 * 处理插件的安装、卸载、启用、禁用等生命周期事件
 *
 * 【生命周期方法】
 * - install()   : 安装插件时执行
 * - uninstall() : 卸载插件时执行
 * - enable()    : 启用插件时执行（复制文件、导入SQL、添加菜单）
 * - disable()   : 禁用插件时执行（删除菜单）
 *
 * 【文件复制规则】（在 enable() 中执行）
 * addons/lemetu/install/extend/       → extend/
 * addons/lemetu/install/application/  → application/
 * addons/lemetu/install/extra/        → application/extra/
 * addons/lemetu/install/static/       → 根目录（部分文件）
 *
 * 【官方信息】
 * 官网地址：https://www.lemetu.com
 * QQ交流群：583188776
 *
 * ============================================================
 */

namespace addons\Lemetu;

use think\Addons;
use think\Db;

class Lemetu extends Addons
{
    /**
     * 插件安装目录（相对于插件根目录）
     */
    protected $installDir = 'install';

    /**
     * 备份目录（相对于插件根目录）
     */
    protected $backupDir = 'backup';

    /**
     * 需要备份的文件列表（相对于ROOT_PATH）
     * 这些文件会被插件修改，卸载时需要恢复
     */
    protected $backupFiles = [
        'application/admin/view/extend/pay/epay.html',
        'application/common/extend/pay/Epay.php',
        'application/common/model/Card.php',
        'application/common/model/Plog.php',
        'application/common/model/Ulog.php',
    ];

    /**
     * 插件新增的文件列表（相对于ROOT_PATH）
     * 卸载时需要删除
     */
    protected $newFiles = [
        'application/admin/view/extend/pay/qqepay.html',
        'application/common/extend/pay/Qqepay.php',
        'application/extra/app.php',
        'lvdou_api.php',
        'icciu_api.php',
        'mogai_api.php',
        'jiami.php',
        'mkey.txt',
        'update.php',
        'application/data/update/database.php',
    ];

    /**
     * 安装插件
     */
    public function install()
    {
        return true;
    }

    /**
     * 卸载插件 - 清理插件创建的文件
     */
    public function uninstall()
    {
        // 1. 删除插件新增的文件
        foreach ($this->newFiles as $file) {
            $filePath = ROOT_PATH . str_replace('/', DS, $file);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // 2. 恢复被覆盖的原始文件
        $this->restoreOriginalFiles();

        // 3. 根据配置决定是否删除数据表
        if (!$this->isKeepDataEnabled()) {
            $this->dropPluginTables();
        }

        // 4. 删除备份目录
        $backupPath = ADDON_PATH . 'lemetu' . DS . $this->backupDir;
        if (is_dir($backupPath)) {
            $this->removeDir($backupPath);
        }

        // 5. 删除锁文件
        $lockFile = ADDON_PATH . 'lemetu' . DS . 'install.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }

        return true;
    }

    /**
     * ============================================================
     * 启用插件
     * ============================================================
     *
     * 【功能说明】
     * 启用插件时执行以下操作：
     * 1. 复制扩展文件到项目目录
     * 2. 导入数据库SQL
     * 3. 添加快捷菜单
     *
     * 【文件复制清单】
     * install/extend/           → extend/
     * install/application/      → application/
     * install/extra/*.php       → application/extra/
     * install/static/*.php      → 根目录
     * install/static/database.php → application/data/update/database.php
     */
    public function enable()
    {
        $addonPath = ADDON_PATH . 'lemetu' . DS;
        $installPath = $addonPath . $this->installDir . DS;
        $lockFile = $addonPath . 'install.lock';

        // 检查是否已初始化
        $isFirstInstall = !file_exists($lockFile);

        // 0. 首次安装时备份将被覆盖的文件
        // 或者备份目录不存在时也执行备份（防止之前版本没备份）
        $backupPath = $addonPath . $this->backupDir;
        if ($isFirstInstall || !is_dir($backupPath)) {
            $this->backupOriginalFiles();
        }

        // 1. 复制 extend 目录
        $extendSource = $installPath . 'extend' . DS;
        $extendDest = ROOT_PATH . 'extend' . DS;
        if (is_dir($extendSource)) {
            $this->copyDir($extendSource, $extendDest);
        }

        // 2. 复制 application 目录下的文件
        $appSource = $installPath . 'application' . DS;
        $appDest = ROOT_PATH . 'application' . DS;
        if (is_dir($appSource)) {
            $this->copyDir($appSource, $appDest);
        }

        // 3. 复制 extra 配置文件
        $extraFiles = ['app.php', 'vodplayer.php'];
        foreach ($extraFiles as $file) {
            $source = $installPath . 'extra' . DS . $file;
            $dest = ROOT_PATH . 'application' . DS . 'extra' . DS . $file;
            if (file_exists($source) && !file_exists($dest)) {
                @copy($source, $dest);
            }
        }

        // 4. 复制静态文件到根目录
        $staticFiles = [
            'lvdou_api.php'  => ROOT_PATH . 'lvdou_api.php',
            'icciu_api.php'  => ROOT_PATH . 'icciu_api.php',
            'mogai_api.php'  => ROOT_PATH . 'mogai_api.php',
            'jiami.php'      => ROOT_PATH . 'jiami.php',
            'mkey.txt'       => ROOT_PATH . 'mkey.txt',
            'update.php'     => ROOT_PATH . 'update.php',
        ];
        foreach ($staticFiles as $file => $dest) {
            $source = $installPath . 'static' . DS . $file;
            if (file_exists($source) && !file_exists($dest)) {
                @copy($source, $dest);
            }
        }

        // 5. 复制 database.php
        $dbSource = $installPath . 'static' . DS . 'database.php';
        $dbDest = ROOT_PATH . 'application' . DS . 'data' . DS . 'update' . DS . 'database.php';
        if (file_exists($dbSource)) {
            $dbDir = dirname($dbDest);
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0755, true);
            }
            @copy($dbSource, $dbDest);
        }

        // 6. 导入数据库（首次安装或覆盖重装）
        $needImportSql = $isFirstInstall || $this->isReinstallEnabled();

        if ($needImportSql) {
            // 如果是覆盖重装，先删除旧表
            if (!$isFirstInstall && $this->isReinstallEnabled()) {
                $this->dropPluginTables();
            }

            $sqlSource = $installPath . 'static' . DS . 'mysql.sql';
            if (file_exists($sqlSource)) {
                $sqlDest = $installPath . 'mysql.sql';
                @copy($sqlSource, $sqlDest);
                $this->importSql('lemetu/install/mysql.sql');
                @unlink($sqlDest);
            }

            // 创建/更新锁文件
            @file_put_contents($lockFile, date('Y-m-d H:i:s'));

            // 重置覆盖重装配置
            $this->resetReinstallConfig();
        }

        // 7. 添加快捷菜单
        $this->addQuickMenu();

        return true;
    }

    /**
     * 禁用插件 - 移除快捷菜单
     */
    public function disable()
    {
        $this->delQuickMenu();
        return true;
    }

    /**
     * 递归复制目录
     */
    protected function copyDir($src, $dst)
    {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }

        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $src . $file;
            $dstPath = $dst . $file;

            if (is_dir($srcPath)) {
                $this->copyDir($srcPath . DS, $dstPath . DS);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * 获取配置的表前缀
     *
     * @return string 表前缀，默认 mac_
     */
    protected function getTablePrefix()
    {
        $config = get_addon_config('lemetu');
        return isset($config['table_prefix']) && $config['table_prefix']
            ? $config['table_prefix']
            : 'mac_';
    }

    /**
     * 导入SQL文件
     *
     * 【表前缀替换规则】
     * 1. 优先使用配置的 table_prefix
     * 2. 替换 SQL 中的默认前缀 mac_ 为配置的前缀
     * 3. 同时支持 __PREFIX__ 占位符（使用系统数据库前缀）
     */
    protected function importSql($sqlFile)
    {
        $filePath = ADDON_PATH . $sqlFile;
        if (!is_file($filePath)) {
            return false;
        }

        // 获取配置的表前缀
        $tablePrefix = $this->getTablePrefix();

        $lines = file($filePath);
        $templine = '';

        foreach ($lines as $line) {
            if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*') {
                continue;
            }
            $templine .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                // 替换 __PREFIX__ 占位符为系统数据库前缀
                $templine = str_ireplace('__PREFIX__', config('database.prefix'), $templine);
                // 替换默认的 mac_ 前缀为配置的前缀
                $templine = str_replace('`mac_', '`' . $tablePrefix, $templine);
                $templine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $templine);
                try {
                    Db::execute($templine);
                } catch (\Exception $e) {
                    // 忽略SQL执行错误
                }
                $templine = '';
            }
        }
        return true;
    }

    /**
     * 添加快捷菜单
     */
    protected function addQuickMenu()
    {
        $menu = '乐美兔API,app/index';

        // PHP 配置文件格式
        $quickmenuFile = APP_PATH . 'extra/quickmenu.php';
        if (file_exists($quickmenuFile)) {
            $menuList = config('quickmenu') ?: [];
            if (!in_array($menu, $menuList)) {
                $menuList[] = $menu;
                mac_arr2file($quickmenuFile, $menuList);
            }
        }

        // TXT 格式
        $quickmenuTxt = APP_PATH . 'data/config/quickmenu.txt';
        if (file_exists($quickmenuTxt)) {
            $content = @file_get_contents($quickmenuTxt);
            if (strpos($content, $menu) === false) {
                $content .= PHP_EOL . $menu;
                @file_put_contents($quickmenuTxt, $content);
            }
        }

        return true;
    }

    /**
     * 删除快捷菜单
     */
    protected function delQuickMenu()
    {
        $menu = '乐美兔API,app/index';

        // PHP 配置文件格式
        $quickmenuFile = APP_PATH . 'extra/quickmenu.php';
        if (file_exists($quickmenuFile)) {
            $menuList = config('quickmenu') ?: [];
            if (in_array($menu, $menuList)) {
                $menuList = array_filter($menuList, function($item) use ($menu) {
                    return $item !== $menu;
                });
                mac_arr2file($quickmenuFile, array_values($menuList));
            }
        }

        // TXT 格式
        $quickmenuTxt = APP_PATH . 'data/config/quickmenu.txt';
        if (file_exists($quickmenuTxt)) {
            $content = @file_get_contents($quickmenuTxt);
            if (strpos($content, $menu) !== false) {
                $content = str_replace(PHP_EOL . $menu, '', $content);
                $content = str_replace($menu . PHP_EOL, '', $content);
                $content = str_replace($menu, '', $content);
                @file_put_contents($quickmenuTxt, $content);
            }
        }

        return true;
    }

    /**
     * 检查是否启用覆盖重装
     *
     * @return bool
     */
    protected function isReinstallEnabled()
    {
        $config = get_addon_config('lemetu');
        return isset($config['reinstall_tables']) && $config['reinstall_tables'] == '1';
    }

    /**
     * 获取插件数据表列表
     *
     * @return array
     */
    protected function getPluginTables()
    {
        return [
            'adtype',
            'app_install_record',
            'app_version',
            'category',
            'danmu',
            'glog',
            'gold_withdraw_apply',
            'gonggao',
            'groupchat',
            'message',
            'sign',
            'tmpvod',
            'tvdata',
            'umeng',
            'view30m',
            'vlog',
            'youxi',
            'zhibo',
        ];
    }

    /**
     * 删除插件数据表
     */
    protected function dropPluginTables()
    {
        $tablePrefix = $this->getTablePrefix();
        $tables = $this->getPluginTables();

        foreach ($tables as $table) {
            $tableName = $tablePrefix . $table;
            try {
                Db::execute("DROP TABLE IF EXISTS `{$tableName}`");
            } catch (\Exception $e) {
                // 忽略错误
            }
        }

        return true;
    }

    /**
     * 重置覆盖重装配置
     */
    protected function resetReinstallConfig()
    {
        $config = get_addon_config('lemetu');
        if (isset($config['reinstall_tables'])) {
            $config['reinstall_tables'] = '0';
            set_addon_config('lemetu', $config);
        }
    }

    /**
     * 检查是否保留数据表
     *
     * @return bool
     */
    protected function isKeepDataEnabled()
    {
        $config = get_addon_config('lemetu');
        return isset($config['keep_data_on_uninstall']) && $config['keep_data_on_uninstall'] == '1';
    }

    /**
     * 备份原始文件
     * 在首次安装时调用，备份将被插件覆盖的文件
     */
    protected function backupOriginalFiles()
    {
        $backupPath = ADDON_PATH . 'lemetu' . DS . $this->backupDir . DS;

        // 创建备份目录
        if (!is_dir($backupPath)) {
            @mkdir($backupPath, 0755, true);
        }

        foreach ($this->backupFiles as $file) {
            $sourcePath = ROOT_PATH . str_replace('/', DS, $file);
            if (file_exists($sourcePath)) {
                // 创建备份文件的目录结构
                $backupFile = $backupPath . str_replace('/', DS, $file);
                $backupDir = dirname($backupFile);
                if (!is_dir($backupDir)) {
                    @mkdir($backupDir, 0755, true);
                }
                // 复制文件到备份目录
                @copy($sourcePath, $backupFile);
            }
        }

        return true;
    }

    /**
     * 恢复原始文件
     * 在卸载时调用，从备份目录恢复原始文件
     */
    protected function restoreOriginalFiles()
    {
        $backupPath = ADDON_PATH . 'lemetu' . DS . $this->backupDir . DS;

        foreach ($this->backupFiles as $file) {
            $backupFile = $backupPath . str_replace('/', DS, $file);
            $targetPath = ROOT_PATH . str_replace('/', DS, $file);

            if (file_exists($backupFile)) {
                // 确保目标目录存在
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                // 从备份恢复文件
                @copy($backupFile, $targetPath);
            }
        }

        return true;
    }

    /**
     * 递归删除目录
     */
    protected function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return true;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DS . $file;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }
}