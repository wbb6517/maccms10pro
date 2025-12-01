<?php
/**
 * 视频服务器组管理控制器 (VodServer Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台 "视频 → 服务器组管理" 功能的控制器
 * 管理视频播放源的分组，用于匹配对应播放器
 *
 * 【访问路径】
 * admin.php/vodserver/index  → 服务器组列表
 * admin.php/vodserver/info   → 添加/编辑服务器组
 * admin.php/vodserver/del    → 删除服务器组
 * admin.php/vodserver/field  → 批量修改字段
 *
 * 【方法列表】
 * ┌──────────────┬──────────────────────────────────────┐
 * │ 方法名        │ 功能说明                              │
 * ├──────────────┼──────────────────────────────────────┤
 * │ index()      │ 服务器组列表页面                       │
 * │ info()       │ 服务器组添加/编辑页面                   │
 * │ del()        │ 删除服务器组                          │
 * │ field()      │ 批量修改状态等字段                     │
 * └──────────────┴──────────────────────────────────────┘
 *
 * 【数据存储说明】
 * 服务器组数据存储在配置文件: application/extra/vodserver.php
 * - 使用 config('vodserver') 读取配置
 * - 使用 mac_arr2file() 写入配置文件
 *
 * 【配置字段说明】
 * ┌──────────────┬─────────────────────────────────────────┐
 * │ 字段名        │ 说明                                     │
 * ├──────────────┼─────────────────────────────────────────┤
 * │ from         │ 服务器组编码 (唯一标识，用于匹配播放器)      │
 * │ show         │ 显示名称 (前台展示，如"高清线路1")         │
 * │ des          │ 描述/URL说明                             │
 * │ tip          │ 提示信息 (鼠标悬停显示)                   │
 * │ sort         │ 排序值 (数字越大越靠前)                   │
 * │ status       │ 状态: 0=禁用, 1=启用                     │
 * └──────────────┴─────────────────────────────────────────┘
 *
 * 【与播放器的关系】
 * - 播放器(vodplayer)的 "from" 字段需要与服务器组的 "from" 匹配
 * - 采集的播放地址格式: "服务器组编码$播放URL"
 * - 例如: "hnm3u8$https://xxx.m3u8" 使用 hnm3u8 服务器组
 *
 * @package     app\admin\controller
 * @author      MacCMS
 * @version     1.0
 */
namespace app\admin\controller;
use think\Db;

class VodServer extends Base
{
    /**
     * @var string 配置文件名前缀，对应 extra/vodserver.php
     */
    var $_pre;

    /**
     * 构造函数
     * 初始化配置文件名前缀
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pre = 'vodserver';
    }

    /**
     * 服务器组列表页面
     *
     * 【功能说明】
     * 显示所有服务器组配置列表
     * 从配置文件读取数据，不使用数据库
     *
     * 【模板变量】
     * - list  : 服务器组列表数据
     * - title : 页面标题
     *
     * @return mixed 渲染后的列表页面
     */
    public function index()
    {
        $list = config($this->_pre);
        $this->assign('list',$list);
        $this->assign('title',lang('admin/vodserver/title'));
        return $this->fetch('admin@vodserver/index');
    }

    /**
     * 服务器组添加/编辑页面
     *
     * 【功能说明】
     * GET请求: 显示编辑表单
     * POST请求: 保存服务器组配置到文件
     *
     * 【请求参数】
     * - id     : 服务器组编码 (GET，编辑时传入)
     * - from   : 服务器组编码 (POST，唯一标识)
     * - show   : 显示名称
     * - des    : 描述信息
     * - tip    : 提示文字
     * - sort   : 排序值
     * - status : 状态
     *
     * 【安全处理】
     * - 验证Token防止CSRF攻击
     * - 编码为纯数字时自动追加下划线
     * - 禁止编码包含特殊字符 (. / \)
     *
     * 【保存逻辑】
     * 1. 验证Token
     * 2. 编码格式检查
     * 3. 更新到配置数组
     * 4. 按sort字段降序排序
     * 5. 写入配置文件
     * 6. 更新缓存标记
     *
     * @return mixed 渲染后的编辑页面或操作结果
     */
    public function info()
    {
        $param = input();
        $list = config($this->_pre);
        if (Request()->isPost()) {
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            unset($param['__token__']);
            unset($param['flag']);
            if(is_numeric($param['from'])){
                $param['from'] .='_';
            }
            if (strpos($param['from'], '.') !== false || strpos($param['from'], '/') !== false || strpos($param['from'], '\\') !== false) {
                $this->error(lang('param_err'));
                return;
            }
            $list[$param['from']] = $param;
            $sort=[];
            foreach ($list as $k=>&$v){
                $sort[] = $v['sort'];
            }
            array_multisort($sort, SORT_DESC, SORT_FLAG_CASE , $list);
            $res = mac_arr2file( APP_PATH .'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->error(lang('save_err'));
            }
            cache('cache_data','1');
            return $this->success(lang('save_ok'));
        }

        $info = $list[$param['id']];
        $this->assign('info',$info);
        $this->assign('title',lang('admin/vodserver/title'));
        return $this->fetch('admin@vodserver/info');
    }

    /**
     * 删除服务器组
     *
     * 【功能说明】
     * 从配置文件中删除指定的服务器组
     *
     * 【请求参数】
     * - ids : 服务器组编码 (from值)
     *
     * 【注意事项】
     * 删除服务器组后，使用该编码的视频播放源将无法正常匹配播放器
     *
     * @return \think\response\Json 操作结果
     */
    public function del()
    {
        $param = input();
        $list = config($this->_pre);
        unset($list[$param['ids']]);
        $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
        if($res===false){
            return $this->error(lang('del_err'));
        }
        cache('cache_data','1');
        return $this->success(lang('del_ok'));
    }

    /**
     * 批量修改服务器组字段
     *
     * 【功能说明】
     * 批量修改所有服务器组的指定字段值
     * 支持修改 status(状态) 和 parse_status(解析状态) 字段
     *
     * 【请求参数】
     * - ids : 标识 (非空即可触发)
     * - col : 字段名 (status/parse_status)
     * - val : 字段值
     *
     * 【使用场景】
     * - 一键启用/禁用所有服务器组
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['parse_status','status'])){
            $list = config($this->_pre);

            foreach($list as $k=>&$v){
                $v[$col] = $val;
            }
            $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        return $this->error(lang('param_err'));
    }

}
