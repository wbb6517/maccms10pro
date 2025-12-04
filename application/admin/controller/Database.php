<?php
/**
 * 数据库管理控制器 (Database Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台数据库维护管理控制器
 * 处理数据库备份、恢复、优化、修复、SQL执行等功能
 *
 * 【菜单位置】
 * 后台管理 → 系统 → 数据库管理
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ index()         │ 数据表列表/备份文件列表                       │
 * │ export()        │ 导出/备份数据库                              │
 * │ import()        │ 导入/恢复数据库                              │
 * │ optimize()      │ 优化数据表                                   │
 * │ repair()        │ 修复数据表                                   │
 * │ del()           │ 删除备份文件                                 │
 * │ sql()           │ SQL语句执行                                  │
 * │ columns()       │ 获取表字段列表                               │
 * │ rep()           │ 数据批量替换                                 │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/database/index         → 数据表列表
 * admin.php/database/index?group=import → 备份文件列表
 * admin.php/database/export        → 备份数据库
 * admin.php/database/import        → 恢复数据库
 * admin.php/database/sql           → SQL执行
 * admin.php/database/rep           → 数据替换
 *
 * 【相关文件】
 * - application/common/util/Database.php : 数据库备份/恢复工具类
 * - application/admin/view_new/database/ : 视图文件目录
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use app\common\util\Dir;
use app\common\util\Database as dbOper;

class Database extends Base
{
    /** @var array 数据库配置 */
    var $_db_config;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 数据表/备份文件列表
     * ============================================================
     *
     * 【功能说明】
     * - group=export: 显示数据库所有表 (默认)
     * - group=import: 显示备份文件列表
     *
     * 【备份文件命名规则】
     * 格式: YYYYMMDD-HHMMSS-N.sql[.gz]
     * 例如: 20231201-143052-1.sql.gz
     *
     * @return string 渲染后的HTML页面
     */
    public function index()
    {
        $group = input('group');
        if($group=='import'){
            //列出备份文件列表
            $path = trim( $GLOBALS['config']['db']['backup_path'], '/').DS;
            if (!is_dir($path)) {
                Dir::create($path);
            }
            $flag = \FilesystemIterator::KEY_AS_FILENAME;
            $glob = new \FilesystemIterator($path,  $flag);

            $list = [];
            foreach ($glob as $name => $file) {
                if(preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql(?:\.gz)?$/', $name)){
                    $name = sscanf($name, '%4s%2s%2s-%2s%2s%2s-%d');
                    $date = "{$name[0]}-{$name[1]}-{$name[2]}";
                    $time = "{$name[3]}:{$name[4]}:{$name[5]}";
                    $part = $name[6];

                    if(isset($list["{$date} {$time}"])){
                        $info = $list["{$date} {$time}"];
                        $info['part'] = max($info['part'], $part);
                        $info['size'] = $info['size'] + $file->getSize();
                    } else {
                        $info['part'] = $part;
                        $info['size'] = $file->getSize();
                    }

                    $extension        = strtoupper($file->getExtension());
                    $info['compress'] = ($extension === 'SQL') ? '无' : $extension;
                    $info['time']     = strtotime("{$date} {$time}");

                    $list["{$date} {$time}"] = $info;
                }
            }
        }
        else{
            $group='export';
            $list = Db::query("SHOW TABLE STATUS");
        }

        $this->assign('list',$list);
        $this->assign('title',lang('admin/database/title'));
        return $this->fetch('admin@database/'.$group);
    }

    /**
     * ============================================================
     * 导出/备份数据库
     * ============================================================
     *
     * 【功能说明】
     * 备份选中的数据表到SQL文件
     * 支持分卷备份和GZIP压缩
     *
     * 【备份机制】
     * 1. 创建锁文件防止并发备份
     * 2. 逐表备份结构和数据
     * 3. admin表放到最后备份
     * 4. 支持大数据分批处理
     *
     * @param string|array $ids   要备份的表名
     * @param int          $start 起始行数
     * @return \think\response\Json JSON响应
     */
    public function export($ids = '', $start = 0)
    {
        if ($this->request->isPost()) {
            if (empty($ids)) {
                return $this->error(lang('admin/database/select_export_table'));
            }

            if (!is_array($ids)) {
                $tables[] = $ids;
            } else {
                $tables = $ids;
            }
            $have_admin = false;
            $admin_table='';
            foreach($tables as $k=>$v){
                if(strpos($v,'_admin')!==false){
                    $have_admin=true;
                    $admin_table = $v;
                    unset($tables[$k]);
                }
            }
            if($have_admin){
                $tables[] = $admin_table;
            }

            //读取备份配置
            $config = array(
                'path'     => $GLOBALS['config']['db']['backup_path'] .DS,
                'part'     => $GLOBALS['config']['db']['part_size'] ,
                'compress' => $GLOBALS['config']['db']['compress'] ,
                'level'    => $GLOBALS['config']['db']['compress_level'] ,
            );

            //检查是否有正在执行的任务
            $lock = "{$config['path']}backup.lock";
            if(is_file($lock)){
                return $this->error(lang('admin/database/lock_check'));
            } else {
                if (!is_dir($config['path'])) {
                    Dir::create($config['path'], 0755, true);
                }
                //创建锁文件
                file_put_contents($lock, $this->request->time());
            }

            //生成备份文件信息
            $file = [
                'name' => date('Ymd-His', $this->request->time()),
                'part' => 1,
            ];

            // 创建备份文件
            $database = new dbOper($file, $config);
            if($database->create() !== false) {
                // 备份指定表
                foreach ($tables as $table) {
                    $start = $database->backup($table, $start);
                    while (0 !== $start) {
                        if (false === $start) {
                            return $this->error(lang('admin/database/backup_err'));
                        }
                        $start = $database->backup($table, $start[0]);
                    }
                }
                // 备份完成，删除锁定文件
                unlink($lock);
            }
            return $this->success(lang('admin/database/backup_ok'));
        }
        return $this->error(lang('admin/database/backup_err'));
    }

    /**
     * ============================================================
     * 导入/恢复数据库
     * ============================================================
     *
     * 【功能说明】
     * 从备份文件恢复数据库
     * 支持分卷文件和GZIP压缩文件
     *
     * 【恢复流程】
     * 1. 根据时间戳ID找到所有分卷文件
     * 2. 按卷号顺序逐个导入
     * 3. 每1000行SQL为一批次执行
     *
     * @param string $id 备份时间戳
     * @return \think\response\Json JSON响应
     */
    public function import($id = '')
    {
        if (empty($id)) {
            return $this->error(lang('admin/database/select_file'));
        }

        $name  = date('Ymd-His', $id) . '-*.sql*';
        $path  = trim( $GLOBALS['config']['db']['backup_path'] , '/').DS.$name;
        $files = glob($path);
        $list  = array();
        foreach($files as $name){
            $basename = basename($name);
            $match    = sscanf($basename, '%4s%2s%2s-%2s%2s%2s-%d');
            $gz       = preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql.gz$/', $basename);
            $list[$match[6]] = array($match[6], $name, $gz);
        }
        ksort($list);

        // 检测文件正确性
        $last = end($list);
        if(count($list) === $last[0]){
            foreach ($list as $item) {
                $config = [
                    'path'     => trim($GLOBALS['config']['db']['backup_path'], '/').DS,
                    'compress' => $item[2]
                ];
                $database = new dbOper($item, $config);
                $start = $database->import(0);
                // 导入所有数据
                while (0 !== $start) {
                    if (false === $start) {
                        return $this->error(lang('admin/database/import_err'));
                    }
                    $start = $database->import($start[0]);
                }
            }
            return $this->success(lang('admin/database/import_ok'));
        }
        return $this->error(lang('admin/database/file_damage'));
    }

    /**
     * ============================================================
     * 优化数据表
     * ============================================================
     *
     * 【功能说明】
     * 执行 OPTIMIZE TABLE 优化选中的表
     * 可回收空间、整理碎片、提高性能
     *
     * @param string|array $ids 要优化的表名
     * @return \think\response\Json JSON响应
     */
    public function optimize($ids = '')
    {
        if (empty($ids)) {
            return $this->error(lang('admin/database/select_optimize_table'));
        }

        if (!is_array($ids)) {
            $table[] = $ids;
        } else {
            $table = $ids;
        }

        $tables = implode('`,`', $table);
        $res = Db::query("OPTIMIZE TABLE `{$tables}`");
        if ($res) {
            return $this->success(lang('admin/database/optimize_ok'));
        }
        return $this->error(lang('admin/database/optimize_err'));
    }

    /**
     * ============================================================
     * 修复数据表
     * ============================================================
     *
     * 【功能说明】
     * 执行 REPAIR TABLE 修复选中的表
     * 用于修复损坏的MyISAM表
     *
     * @param string|array $ids 要修复的表名
     * @return \think\response\Json JSON响应
     */
    public function repair($ids = '')
    {
        if (empty($ids)) {
            return $this->error(lang('admin/database/select_repair_table'));
        }

        if (!is_array($ids)) {
            $table[] = $ids;
        } else {
            $table = $ids;
        }

        $tables = implode('`,`', $table);
        $res = Db::query("REPAIR TABLE `{$tables}`");
        if ($res) {
            return $this->success(lang('admin/database/repair_ok'));
        }
        return $this->error(lang('admin/database/repair_ok'));
    }

    /**
     * ============================================================
     * 删除备份文件
     * ============================================================
     *
     * 【功能说明】
     * 根据时间戳删除对应的所有备份分卷文件
     *
     * @param string $id 备份时间戳
     * @return \think\response\Json JSON响应
     */
    public function del($id = '')
    {
        if (empty($id)) {
            return $this->error(lang('admin/database/select_del_file'));
        }

        $name  = date('Ymd-His', $id) . '-*.sql*';
        $path = trim($GLOBALS['config']['db']['backup_path']).DS.$name;
        array_map("unlink", glob($path));
        if(count(glob($path)) && glob($path)){
            return $this->error(lang('del_err'));
        }
        return $this->success(lang('del_ok'));
    }

    /**
     * ============================================================
     * SQL语句执行
     * ============================================================
     *
     * 【功能说明】
     * 执行用户输入的SQL语句
     * 支持 {pre} 占位符替换为表前缀
     *
     * 【安全限制】
     * 禁止的关键词: into dumpfile, into outfile, char(, load_file
     * SELECT 语句不返回结果集
     *
     * @return mixed 渲染页面或JSON响应
     */
    public function sql()
    {
        if($this->request->isPost()){
            $param=input();
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            $sql = trim($param['sql']);

            if(!empty($sql)){
                $forbidden_keywords = ['into dumpfile', 'into outfile', 'char(', 'load_file'];
                foreach ($forbidden_keywords as $keyword) {
                    if (stripos($sql, $keyword) !== false) {
                        return $this->error(lang('format_err'));
                    }
                }
                $sql = str_replace('{pre}',config('database.prefix'),$sql);
                //查询语句返回结果集
                if(
                    strtolower(substr($sql,0,6))=="select" || 
                    stripos($sql, ' outfile') !== false
                ){

                }
                else{
                    Db::execute($sql);
                }
            }
            $this->success(lang('run_ok'));
        }
        return $this->fetch('admin@database/sql');
    }

    /**
     * ============================================================
     * 获取表字段列表
     * ============================================================
     *
     * 【功能说明】
     * AJAX接口，返回指定表的所有字段信息
     * 用于数据替换功能的字段选择
     *
     * @return \think\response\Json JSON响应
     */
    public function columns()
    {
        $param = input();
        $table = $param['table'];
        if (!empty($table) && !$this->isValidTable($table)) {
            return $this->error('Table is invalid.');
        }
        if (!empty($table)) {
            $list = Db::query('SHOW COLUMNS FROM ' . $table);
            $this->success(lang('obtain_ok'),null, $list);
        }
        $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 数据批量替换
     * ============================================================
     *
     * 【功能说明】
     * 批量替换指定表中指定字段的内容
     * 支持附加WHERE条件
     *
     * 【SQL生成】
     * UPDATE table SET field=Replace(field,'查找','替换') WHERE 1=1 附加条件
     *
     * @return mixed 渲染页面或JSON响应
     */
    public function rep()
    {
        if($this->request->isPost()){
            $param = input();
            $table = $param['table'];
            $field = $param['field'];
            $findstr = $param['findstr'];
            $tostr = $param['tostr'];
            $where = $param['where'];

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            if (!empty($table) && !$this->isValidTable($table)) {
                return $this->error('Table is invalid.');
            }
            if(!empty($field) && !empty($findstr) && !empty($tostr)){
                $sql = "UPDATE ".$table." set ".$field."=Replace(".$field.",'".$findstr."','".$tostr."') where 1=1 ". $where;
                Db::execute($sql);
                return $this->success(lang('run_ok'));
            }

            return $this->error(lang('param_err'));
        }
        $list = Db::query("SHOW TABLE STATUS");
        $this->assign('list',$list);
        return $this->fetch('admin@database/rep');
    }

    /**
     * 验证表名是否合法
     * 防止SQL注入，只允许操作数据库中存在的表
     *
     * @param string $table 表名
     * @return bool 是否合法
     */
    private function isValidTable($table) {
        $list = Db::query("SHOW TABLE STATUS");
        foreach ($list as $table_raw) {
            if ($table_raw['Name'] == $table) {
                return true;
            }
        }
        return false;
    }
}
