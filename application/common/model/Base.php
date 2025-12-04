<?php
/**
 * 模型基类 (Model Base Class)
 * ============================================================
 *
 * 【文件说明】
 * 所有业务模型的公共基类
 * 继承 ThinkPHP 的 Model 类，提供通用的数据库操作方法
 * 支持主从数据库读写分离、自动表前缀、动态主键等功能
 *
 * 【继承关系】
 * think\Model → app\common\model\Base → 各业务模型 (Vod/Art/Actor等)
 *
 * 【属性列表】
 * ┌────────────────────┬──────────────────────────────────────────────────┐
 * │ 属性名              │ 说明                                              │
 * ├────────────────────┼──────────────────────────────────────────────────┤
 * │ $tablePrefix       │ 表前缀，默认从数据库配置读取                        │
 * │ $primaryId         │ 主键字段名，默认为 {模型名}_id                      │
 * │ $readFromMaster    │ 是否强制从主库读取，用于主从分离场景                 │
 * └────────────────────┴──────────────────────────────────────────────────┘
 *
 * 【方法列表】
 * ┌────────────────────┬──────────────────────────────────────────────────┐
 * │ 方法名              │ 功能说明                                          │
 * ├────────────────────┼──────────────────────────────────────────────────┤
 * │ initialize()       │ 模型初始化，设置表前缀、主键、主从配置               │
 * │ getCountByCond()   │ 根据条件统计记录数量                               │
 * │ getListByCond()    │ 根据条件获取分页列表数据                           │
 * │ transformRow()     │ 行数据转换钩子，子类可覆盖实现自定义转换             │
 * └────────────────────┴──────────────────────────────────────────────────┘
 *
 * 【子类模型】
 * - Vod.php      : 视频数据模型
 * - Art.php      : 文章数据模型
 * - Actor.php    : 演员数据模型
 * - Type.php     : 分类数据模型
 * - User.php     : 用户数据模型
 * - Topic.php    : 专题数据模型
 * - Comment.php  : 评论数据模型
 * - Collect.php  : 采集数据模型
 * - 等30+个业务模型...
 *
 * 【使用示例】
 * // 子类继承
 * class Vod extends Base {
 *     protected $primaryId = 'vod_id';
 * }
 *
 * // 使用通用方法
 * $count = model('Vod')->getCountByCond(['vod_status'=>1]);
 * $list = model('Vod')->getListByCond(0, 10, ['type_id'=>1], 'vod_time desc');
 *
 * ============================================================
 */
namespace app\common\model;

use think\Config as ThinkConfig;
use think\Model;
use think\Db;
use think\Cache;

class Base extends Model
{
    /**
     * 数据表前缀
     * 默认从数据库配置 database.prefix 读取，通常为 'mac_'
     * @var string
     */
    protected $tablePrefix;

    /**
     * 主键字段名
     * 默认格式为 {模型名小写}_id，如 vod_id, art_id
     * 子类可覆盖此属性自定义主键名
     * @var string
     */
    protected $primaryId;

    /**
     * 是否强制从主库读取
     * 用于主从分离架构，设为 true 时查询走主库
     * 适用于需要读取最新数据的场景 (如刚写入后立即读取)
     * @var bool
     */
    protected $readFromMaster;

    /**
     * ============================================================
     * 模型初始化方法
     * ============================================================
     *
     * 【功能说明】
     * ThinkPHP 模型初始化钩子，在模型实例化时自动调用
     * 负责设置表前缀、主键名、主从配置等基础属性
     * 并检查是否需要自动创建数据表
     *
     * 【初始化流程】
     * 1. 调用父类 initialize() 方法
     * 2. 设置表前缀 (优先使用子类定义，否则读取配置)
     * 3. 设置主键名 (优先使用子类定义，否则使用 {模型名}_id)
     * 4. 设置主从读取策略
     * 5. 如果子类定义了 createTableIfNotExists()，则调用创建表
     *
     * @return void
     */
    protected function initialize()
    {
        // 调用父类 Model 的初始化方法
        parent::initialize();

        // 设置表前缀：优先使用子类定义的值，否则从数据库配置读取
        $this->tablePrefix = isset($this->tablePrefix) ? $this->tablePrefix : ThinkConfig::get('database.prefix');

        // 设置主键字段名：优先使用子类定义的值，否则使用 {模型名}_id 格式
        $this->primaryId = isset($this->primaryId) ? $this->primaryId : $this->name . '_id';

        // 设置是否从主库读取：默认为 false (从库读取)
        $this->readFromMaster = isset($this->readFromMaster) ? $this->readFromMaster : false;

        // 自动建表：如果子类实现了 createTableIfNotExists 方法，则调用
        // 用于首次使用时自动创建数据表结构
        if (method_exists($this, 'createTableIfNotExists')) {
            $this->createTableIfNotExists();
        }
    }

    /**
     * ============================================================
     * 根据条件统计记录数量
     * ============================================================
     *
     * 【功能说明】
     * 统计符合条件的记录总数
     * 支持主从分离，可配置从主库读取
     *
     * @param array $cond 查询条件数组，ThinkPHP where 格式
     * @return int 符合条件的记录数量
     *
     * 【使用示例】
     * $count = model('Vod')->getCountByCond(['vod_status'=>1, 'type_id'=>5]);
     */
    public function getCountByCond($cond)
    {
        $query_object = $this;

        // 如果配置了强制从主库读取，则切换到主库连接
        if ($this->readFromMaster === true) {
            $query_object = $query_object->master();
        }

        // 执行 COUNT 查询并返回整数结果
        return (int)$query_object->where($cond)->count();
    }

    /**
     * ============================================================
     * 根据条件获取分页列表数据
     * ============================================================
     *
     * 【功能说明】
     * 根据条件查询分页数据列表
     * 支持自定义排序、字段选择、数据转换等功能
     * 支持主从分离配置
     *
     * 【排序规则】
     * - 如果未指定排序，默认按主键降序
     * - 如果指定了排序但不包含主键，会自动追加主键降序作为次排序
     *   确保分页数据的稳定性
     *
     * @param int    $offset    偏移量，从0开始
     * @param int    $limit     每页数量，最小为1
     * @param array  $cond      查询条件数组
     * @param string $orderby   排序规则，如 'vod_time desc'
     * @param string $fields    查询字段，默认 '*' 全部字段
     * @param bool   $transform 是否进行数据转换，true时调用 transformRow()
     * @return array 数据列表数组，每项为一条记录的关联数组
     *
     * 【使用示例】
     * // 基础查询
     * $list = model('Vod')->getListByCond(0, 10, ['vod_status'=>1]);
     *
     * // 带排序和字段选择
     * $list = model('Vod')->getListByCond(0, 20, ['type_id'=>1], 'vod_hits desc', 'vod_id,vod_name');
     *
     * // 带数据转换
     * $list = model('Vod')->getListByCond(0, 10, [], '', '*', true);
     */
    public function getListByCond($offset, $limit, $cond, $orderby = '', $fields = "*", $transform = false)
    {
        // 参数安全处理：确保偏移量非负，每页数量至少为1
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);

        // 排序规则处理
        if (empty($orderby)) {
            // 未指定排序时，默认按主键降序
            $orderby = $this->primaryId . " DESC";
        } else {
            // 如果指定的排序规则不包含主键，追加主键降序作为次排序
            // 这样可以确保分页查询结果的稳定性和一致性
            if (strpos($orderby, $this->primaryId) === false) {
                $orderby .= ", " . $this->primaryId . " DESC";
            }
        }

        $query_object = $this;

        // 如果配置了强制从主库读取，则切换到主库连接
        if ($this->readFromMaster === true) {
            $query_object = $query_object->master();
        }

        // 执行分页查询
        $list = $query_object->where($cond)->field($fields)->order($orderby)->limit($offset, $limit)->select();

        // 查询结果为空时返回空数组
        if (!$list) {
            return [];
        }

        // 遍历结果集，转换为纯数组格式
        $final = [];
        foreach ($list as $row) {
            // getData() 获取原始数据数组，不经过获取器处理
            $row_array = $row->getData();

            // 如果开启了数据转换，调用 transformRow 方法处理
            if ($transform !== false) {
                $row_array = $this->transformRow($row_array, $transform);
            }

            $final[] = $row_array;
        }

        return $final;
    }

    /**
     * ============================================================
     * 行数据转换钩子方法
     * ============================================================
     *
     * 【功能说明】
     * 数据转换的钩子方法，用于对查询结果的每一行进行自定义处理
     * 基类提供默认实现（直接返回原数据），子类可覆盖实现具体转换逻辑
     *
     * 【典型用途】
     * - 格式化时间戳为可读日期
     * - 添加计算字段 (如播放列表解析)
     * - 关联数据的附加 (如分类信息)
     * - 字段值的映射转换 (如状态码转文本)
     *
     * @param array $row     单行数据的关联数组
     * @param array $extends 扩展参数，可传递额外的转换配置
     * @return array 转换后的数据数组
     *
     * 【子类覆盖示例】
     * public function transformRow($row, $extends = []) {
     *     // 添加分类信息
     *     $row['type_info'] = model('Type')->getCache('type_list')[$row['type_id']] ?? [];
     *     // 格式化时间
     *     $row['time_format'] = date('Y-m-d H:i:s', $row['vod_time']);
     *     return $row;
     * }
     */
    public function transformRow($row, $extends = []) {
        // 基类默认实现：直接返回原始数据，不做任何转换
        return $row;
    }
}