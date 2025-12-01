<?php
/**
 * 视频下载器管理控制器 (VodDowner Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台 "视频 → 下载器管理" 功能的控制器
 * 管理视频下载器配置，与播放器类似但用于下载功能
 *
 * 【访问路径】
 * admin.php/voddowner/index  → 下载器列表
 * admin.php/voddowner/info   → 添加/编辑下载器
 * admin.php/voddowner/del    → 删除下载器
 * admin.php/voddowner/field  → 批量修改字段
 *
 * 【方法列表】
 * ┌──────────────┬──────────────────────────────────────┐
 * │ 方法名        │ 功能说明                              │
 * ├──────────────┼──────────────────────────────────────┤
 * │ index()      │ 下载器列表页面                        │
 * │ info()       │ 下载器添加/编辑页面                    │
 * │ del()        │ 删除下载器                            │
 * │ field()      │ 批量修改状态/解析状态                  │
 * └──────────────┴──────────────────────────────────────┘
 *
 * 【数据存储说明】
 * 下载器数据存储在配置文件: application/extra/voddowner.php
 * - 使用 config('voddowner') 读取配置
 * - 使用 mac_arr2file() 写入配置文件
 *
 * 【配置字段说明】
 * ┌──────────────┬─────────────────────────────────────────┐
 * │ 字段名        │ 说明                                     │
 * ├──────────────┼─────────────────────────────────────────┤
 * │ from         │ 下载器编码 (唯一标识，如 http/xunlei)     │
 * │ show         │ 显示名称 (前台展示，如"迅雷下载")         │
 * │ des          │ 描述/备注                                │
 * │ tip          │ 提示信息 (鼠标悬停显示)                   │
 * │ sort         │ 排序值 (数字越大越靠前)                   │
 * │ status       │ 状态: 0=禁用, 1=启用                     │
 * │ ps           │ 解析状态: 0=不解析, 1=启用解析            │
 * │ parse        │ 独立解析接口URL (ps=1时使用)              │
 * │ target       │ 打开方式: _self=当前窗口, _blank=新窗口   │
 * └──────────────┴─────────────────────────────────────────┘
 *
 * 【与播放器的区别】
 * - 播放器(vodplayer): 用于在线播放视频
 * - 下载器(voddowner): 用于提供下载链接
 * - 视频的 vod_down 字段存储下载地址，格式同播放地址
 *
 * @package     app\admin\controller
 * @author      MacCMS
 * @version     1.0
 */
namespace app\admin\controller;
use think\Db;

class VodDowner extends Base
{
    /**
     * @var string 配置文件名前缀，对应 extra/voddowner.php
     */
    var $_pre;

    /**
     * 构造函数
     * 初始化配置文件名前缀
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pre = 'voddowner';
    }

    /**
     * 下载器列表页面
     *
     * 【功能说明】
     * 显示所有下载器配置列表
     * 支持批量修改状态和解析状态
     *
     * 【模板变量】
     * - list  : 下载器列表数据
     * - title : 页面标题
     *
     * @return mixed 渲染后的列表页面
     */
    public function index()
    {
        $list = config($this->_pre);
        $this->assign('list',$list);
        $this->assign('title',lang('admin/voddowner/title'));
        return $this->fetch('admin@voddowner/index');
    }

    /**
     * 下载器添加/编辑页面
     *
     * 【功能说明】
     * GET请求: 显示编辑表单
     * POST请求: 保存下载器配置到文件
     *
     * 【请求参数】
     * - id     : 下载器编码 (GET，编辑时传入)
     * - from   : 下载器编码 (POST，唯一标识)
     * - show   : 显示名称
     * - des    : 描述信息
     * - tip    : 提示文字
     * - sort   : 排序值
     * - status : 状态
     * - ps     : 解析状态
     * - parse  : 独立解析接口URL
     * - target : 打开方式
     *
     * 【安全处理】
     * - 验证Token防止CSRF攻击
     * - 编码为纯数字时自动追加下划线
     * - 禁止编码包含特殊字符 (. / \)
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
        $this->assign('title',lang('admin/voddowner/title'));
        return $this->fetch('admin@voddowner/info');
    }

    /**
     * 删除下载器
     *
     * 【功能说明】
     * 从配置文件中删除指定的下载器配置
     *
     * 【请求参数】
     * - ids : 下载器编码 (from值)
     *
     * 【注意事项】
     * 删除下载器后，使用该编码的视频下载链接将无法正常显示
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
     * 批量修改下载器字段
     *
     * 【功能说明】
     * 批量修改选中下载器的指定字段值
     * 支持修改 status(状态) 和 ps(解析状态) 字段
     *
     * 【请求参数】
     * - ids : 下载器编码列表 (逗号分隔)
     * - col : 字段名 (status/ps)
     * - val : 字段值
     *
     * 【使用场景】
     * - 批量启用/禁用下载器
     * - 批量开启/关闭解析功能
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['ps','status'])){
            $list = config($this->_pre);
            $ids = explode(',',$ids);
            foreach($list as $k=>&$v){
                if(in_array($k,$ids)){
                    $v[$col] = $val;
                }
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
