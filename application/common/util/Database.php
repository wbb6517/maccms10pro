<?php
/**
 * 数据库备份/恢复工具类 (Database Backup/Restore Utility)
 * ============================================================
 *
 * 【文件说明】
 * 数据库备份和恢复的核心操作类
 * 支持分卷备份、GZIP压缩、大数据分批处理
 *
 * 【功能特性】
 * - 自动分卷: 超过设定大小自动创建新文件
 * - 压缩支持: 可选GZIP压缩减小文件体积
 * - 断点续传: 支持大表分批备份/恢复
 * - 完整性: 包含表结构和数据
 *
 * 【使用方式】
 * $db = new Database($file, $config);
 * $db->create();
 * $db->backup($table, 0);
 *
 * 【相关文件】
 * - application/admin/controller/Database.php : 控制器调用
 *
 * ============================================================
 */
namespace app\common\util;
use think\Db;

/**
 * 数据库备份/恢复操作类
 */
class Database {
    /**
     * 文件指针
     * @var resource
     */
    private $fp;

    /**
     * 备份文件信息
     * - part: 当前卷号
     * - name: 文件名前缀
     * @var array
     */
    private $file;

    /**
     * 当前打开文件大小 (字节)
     * @var integer
     */
    private $size = 0;

    /**
     * 备份配置
     * - path: 备份路径
     * - part: 分卷大小
     * - compress: 是否压缩
     * - level: 压缩级别
     * @var array
     */
    private $config;

    /**
     * 构造函数
     *
     * @param array  $file   备份或还原的文件信息
     * @param array  $config 备份配置信息
     * @param string $type   执行类型 (export=备份, import=还原)
     */
    public function __construct($file, $config, $type = 'export'){
        $this->file   = $file;
        $this->config = $config;
    }

    /**
     * 打开一个卷文件用于写入
     * 如果当前卷超过设定大小，自动创建新卷
     *
     * @param integer $size 本次写入数据的大小
     */
    private function open($size){
        if($this->fp){
            $this->size += $size;
            if($this->size > $this->config['part']){
                $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);
                $this->fp = null;
                $this->file['part']++;
                session('backup_file', $this->file);
                $this->create();
            }
        } else {
            $backuppath = $this->config['path'];
            $filename   = "{$backuppath}{$this->file['name']}-{$this->file['part']}.sql";
            if($this->config['compress']){
                $filename = "{$filename}.gz";
                $this->fp = @gzopen($filename, "a{$this->config['level']}");
            } else {
                $this->fp = @fopen($filename, 'a');
            }
            $this->size = filesize($filename) + $size;
        }
    }

    /**
     * 创建备份文件并写入头部信息
     * 包含数据库连接信息、备份时间等
     *
     * @return boolean true=成功, false=失败
     */
    public function create(){
        $sql  = "-- -----------------------------\n";
        $sql .= "-- Think MySQL Data Transfer \n";
        $sql .= "-- \n";
        $sql .= "-- Host     : " . config('database.hostname') . "\n";
        $sql .= "-- Port     : " . config('database.hostport') . "\n";
        $sql .= "-- Database : " . config('database.database') . "\n";
        $sql .= "-- \n";
        $sql .= "-- Part : #{$this->file['part']}\n";
        $sql .= "-- Date : " . date("Y-m-d H:i:s") . "\n";
        $sql .= "-- -----------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        return $this->write($sql);
    }

    /**
     * 写入SQL语句到备份文件
     * 压缩模式下假设50%压缩率计算文件大小
     *
     * @param string $sql 要写入的SQL语句
     * @return boolean true=成功, false=失败
     */
    private function write($sql){
        $size = strlen($sql);
        
        //由于压缩原因，无法计算出压缩后的长度，这里假设压缩率为50%，
        //一般情况压缩率都会高于50%；
        $size = $this->config['compress'] ? $size / 2 : $size;
        
        $this->open($size); 
        return $this->config['compress'] ? @gzwrite($this->fp, $sql) : @fwrite($this->fp, $sql);
    }

    /**
     * 备份单个数据表
     * 包含表结构和数据，支持大表分批备份
     *
     * @param string  $table 表名
     * @param integer $start 起始行数 (0=从头开始,包含表结构)
     * @return mixed 0=完成, array(下一起始行,总数)=继续, false=失败
     */
    public function backup($table, $start){
        //备份表结构
        if(0 == $start){
            $result = Db::query("SHOW CREATE TABLE `{$table}`");
            $sql  = "\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "-- Table structure for `{$table}`\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= trim($result[0]['Create Table']) . ";\n\n";
            if(false === $this->write($sql)){
                return false;
            }
        }

        //数据总数
        $result = Db::query("SELECT COUNT(*) AS count FROM `{$table}`");
        $count  = $result['0']['count'];
            
        //备份表数据
        if($count){
            //写入数据注释
            if(0 == $start){
                $sql  = "-- -----------------------------\n";
                $sql .= "-- Records of `{$table}`\n";
                $sql .= "-- -----------------------------\n";
                $this->write($sql);
            }

            //备份数据记录
            $result = Db::query("SELECT * FROM `{$table}` LIMIT {$start}, 1000");
            foreach ($result as $row) {
                $row = array_map('addslashes', $row);
                $one = implode("', '", $row);
                $one = str_replace([chr(10),chr(13)],'',$one);
                $sql = "INSERT INTO `{$table}` VALUES ('" . $one . "');\n";
                if(false === $this->write($sql)){
                    return false;
                }
            }

            //还有更多数据
            if($count > $start + 1000){
                return array($start + 1000, $count);
            }
        }

        //备份下一表
        return 0;
    }

    /**
     * 从备份文件导入数据
     * 每次处理1000行SQL语句
     *
     * @param integer $start 文件偏移量 (0=从头开始)
     * @return mixed 0=完成, array(下一偏移量,文件大小)=继续, false=失败
     */
    public function import($start){
        //还原数据
        if($this->config['compress']){
            $gz   = gzopen($this->file[1], 'r');
            $size = 0;
        } else {
            $size = filesize($this->file[1]);
            $gz   = fopen($this->file[1], 'r');
        }
        
        $sql  = '';
        if($start){
            $this->config['compress'] ? gzseek($gz, $start) : fseek($gz, $start);
        }
        
        for($i = 0; $i < 1000; $i++){
            $sql .= $this->config['compress'] ? gzgets($gz) : fgets($gz);

            if(preg_match('/.*;$/', trim($sql))){
                if(false !== Db::execute($sql)){
                    $start += strlen($sql);
                } else {
                    return false;
                }
                $sql = '';
            } elseif ($this->config['compress'] ? gzeof($gz) : feof($gz)) {
                return 0;
            }
        }

        return array($start, $size);
    }

    /**
     * 析构函数
     * 关闭文件资源，支持普通文件和GZIP文件
     */
    public function __destruct(){
        $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);
    }
}
