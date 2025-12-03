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
        // 删除根目录下的 API 文件
        $rootFiles = ['mkey.txt', 'jiami.php', 'update.php', 'lvdou_api.php', 'icciu_api.php', 'mogai_api.php'];
        foreach ($rootFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // 删除 database.php
        $databaseFile = 'application/data/update/database.php';
        if (file_exists($databaseFile)) {
            @unlink($databaseFile);
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

        // 6. 导入数据库（仅首次安装）
        if ($isFirstInstall) {
            $sqlSource = $installPath . 'static' . DS . 'mysql.sql';
            if (file_exists($sqlSource)) {
                $sqlDest = $installPath . 'mysql.sql';
                @copy($sqlSource, $sqlDest);
                $this->importSql('lemetu/install/mysql.sql');
                @unlink($sqlDest);
            }

            // 创建锁文件
            $lockSource = $installPath . 'static' . DS . 'install.lock';
            if (file_exists($lockSource)) {
                @copy($lockSource, $lockFile);
            } else {
                @file_put_contents($lockFile, date('Y-m-d H:i:s'));
            }
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
     * 导入SQL文件
     */
    protected function importSql($sqlFile)
    {
        $filePath = ADDON_PATH . $sqlFile;
        if (!is_file($filePath)) {
            return false;
        }

        $lines = file($filePath);
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
}