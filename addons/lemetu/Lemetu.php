<?php
/*!
* @author:乐美兔
* 官网地址：https://www.lemetu.com
*/
namespace addons\Lemetu;
use think\Addons;
use think\Db;
class Lemetu extends Addons
{
   
public function install()
{
  return true;
}

public function disable()
{
$this->delquick();
return true;
}

public function uninstall()
{
$file_path = 'mkey.txt';
if (file_exists($file_path)) {unlink($file_path);}
$file_path = 'jiami.php';
if (file_exists($file_path)) {unlink($file_path);}
$file_path = 'update.php';
if (file_exists($file_path)) {unlink($file_path);}
$file_path = 'lvdou_api.php';
if (file_exists($file_path)) {unlink($file_path);}
$file_path = 'icciu_api.php';
if (file_exists($file_path)) {unlink($file_path);}
$file_path = 'mogai_api.php';
if (file_exists($file_path)) {unlink($file_path);}
$file_path = 'application/data/update/database.php';
if (file_exists($file_path)) {unlink($file_path);}
return true;
}

public function enable()
{
function importsql($name)
{
$sqlFile = ADDON_PATH . $name;
if (is_file($sqlFile)) {
$lines = file($sqlFile);
$templine = '';
foreach ($lines as $line) {
if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*')
continue;
$templine .= $line;
if (substr(trim($line), -1, 1) == ';') {
$templine = str_ireplace('__PREFIX__', config('database.prefix'), $templine);
$templine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $templine);
try {
Db::execute($templine);
} catch (\Exception $e) {
//$e->getMessage();
}
$templine = '';
}
}
}
return true;
}
if (file_exists($_SERVER['DOCUMENT_ROOT']."/addons/lemetu/install.lock")) {
$file_path = 'addons/lemetu/install/mysql.sql';
if (file_exists($file_path)) {unlink($file_path);}
}else{
copy($_SERVER['DOCUMENT_ROOT'] . '/addons/lemetu/install/static/mysql.sql', $_SERVER['DOCUMENT_ROOT'] . "/addons/lemetu/install/mysql.sql");
importsql('lemetu/install/mysql.sql');
}
$quickmenu = APP_PATH .'extra/quickmenu.php';
$lod_menu = '乐美兔API,app/index';
$menu = '乐美兔API,app/index';
if(file_exists($quickmenu)){
$menu_lod = config('quickmenu');
if(in_array($menu,$menu_lod)){
return true;
}
if(in_array($lod_menu,$menu_lod)){
foreach($menu_lod as $v){
if($v!=$lod_menu){
$menu_lod2[] = $v;
}
}
$menu_lod = $menu_lod2;
}
$menu_new[] = $menu;
$new_menu = array_merge($menu_lod, $menu_new);
$res = mac_arr2file( APP_PATH .'extra/quickmenu.php', $new_menu);			
}
$quickmenu = APP_PATH .'data/config/quickmenu.txt';
if(file_exists($quickmenu)){
$menu_lod = @file_get_contents($quickmenu);
if(strpos($menu_lod,$lod_menu) !==false){
$menu_lod = str_replace(PHP_EOL .$lod_menu,"",$menu_lod);
}
if(strpos($menu_lod,$menu) !==false){
return true;
}else{
$new_menu = $menu_lod . PHP_EOL . $menu;
@fwrite(fopen($quickmenu,'wb'),$new_menu);
}
}
return true;
}

public function delquick()
{
$del_menu = '乐美兔API,app/index';
$quickmenu = APP_PATH .'extra/quickmenu.php';
if(file_exists($quickmenu)){
$menu_lod = config('quickmenu');
if(in_array($del_menu,$menu_lod)){
foreach($menu_lod as $v){
if($v!=$del_menu){
$new_menu[] = $v;
}
}
$res = mac_arr2file( APP_PATH .'extra/quickmenu.php', $new_menu);
}
}
$quickmenu = APP_PATH .'data/config/quickmenu.txt';
if(file_exists($quickmenu)){
$menu_lod = @file_get_contents($quickmenu); 
if(strpos($menu_lod,$del_menu) !==false){
$menu_lod = str_replace(PHP_EOL .$del_menu,"",$menu_lod);
@fwrite(fopen($quickmenu,'wb'),$menu_lod);
}
}
return true;
}
}