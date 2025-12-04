<?php
/**
 * 定时任务管理控制器 (Timming Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台定时任务管理控制器
 * 用于配置和管理系统定时执行的任务
 * 支持采集、生成、自定义脚本等多种任务类型
 *
 * 【菜单位置】
 * 后台管理 → 系统 → 定时任务 (索引2 → 2910)
 *
 * 【数据存储】
 * 配置文件: application/extra/timming.php (非数据库存储)
 *
 * 【支持的任务类型】
 * - collect  : 采集任务 (调用采集接口)
 * - make     : 生成任务 (生成静态页面)
 * - cj       : 自定义采集脚本
 * - cache    : 缓存任务 (清理/更新缓存)
 * - urlsend  : URL推送任务 (SEO推送)
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数                                    │
 * │ index()         │ 定时任务列表                                │
 * │ info()          │ 任务详情/编辑                               │
 * │ del()           │ 删除任务                                    │
 * │ field()         │ 更新任务状态                                │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/timming/index → 任务列表页面
 * admin.php/timming/info  → 任务详情/编辑页面
 * admin.php/timming/del   → 删除任务
 * admin.php/timming/field → 更新任务状态
 *
 * 【执行方式】
 * 通过 API 接口触发: api.php/timming/index?name={任务名}
 * 可配合系统 crontab 或外部监控服务定时调用
 *
 * 【相关文件】
 * - application/extra/timming.php : 定时任务配置存储
 * - application/api/controller/Timming.php : API执行接口
 * - application/admin/view_new/timming/ : 视图文件目录
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Timming extends Base
{
    /**
     * 数据表前缀 (未使用，保留字段)
     * @var string
     */
    var $_pre;

    /**
     * 构造函数
     * 调用父类构造函数进行初始化
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 定时任务列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有已配置的定时任务列表
     * 从配置文件读取任务数据
     *
     * @return mixed 渲染列表页面
     */
    public function index()
    {
        // 从配置文件读取任务列表
        $list = config('timming');
        $this->assign('list',$list);
        $this->assign('title',lang('admin/timming/title'));
        return $this->fetch('admin@timming/index');
    }

    /**
     * ============================================================
     * 任务详情/编辑
     * ============================================================
     *
     * 【功能说明】
     * GET: 显示任务详情编辑页面
     * POST: 保存任务配置到配置文件
     *
     * 【配置项说明】
     * - name   : 任务唯一标识 (不可修改)
     * - des    : 任务描述/备注
     * - file   : 执行文件类型 (collect/make/cj/cache/urlsend)
     * - param  : 附加参数 (传递给执行脚本)
     * - weeks  : 执行周期-星期 (0-6, 逗号分隔)
     * - hours  : 执行周期-小时 (00-23, 逗号分隔)
     * - status : 启用状态 (0=禁用, 1=启用)
     *
     * @return mixed 渲染详情页面或JSON响应
     */
    public function info()
    {
        $param = input();
        $list = config('timming');

        if (Request()->isPost()) {
            // Token验证防止CSRF
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            // 将数组转换为逗号分隔字符串
            $param['weeks'] = join(',',$param['weeks']);
            $param['hours'] = join(',',$param['hours']);

            // 更新任务配置
            $list[$param['name']] = $param;

            // 写入配置文件
            $res = mac_arr2file( APP_PATH .'extra/timming.php', $list);
            if($res===false){
                return $this->error(lang('write_err_config'));
            }

            return $this->success(lang('save_ok'));
        }

        // 获取指定任务信息
        $info = $list[$param['id']];

        $this->assign('info',$info);
        $this->assign('title',lang('admin/timming/title'));
        return $this->fetch('admin@timming/info');
    }

    /**
     * ============================================================
     * 删除任务
     * ============================================================
     *
     * 【功能说明】
     * 从配置文件中删除指定的定时任务
     *
     * 【请求参数】
     * - ids : 任务名称 (唯一标识)
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        $list = config('timming');

        // 从配置数组中移除指定任务
        unset($list[$param['ids']]);

        // 写入配置文件
        $res = mac_arr2file(APP_PATH. 'extra/timming.php', $list);
        if($res===false){
            return $this->error(lang('del_err'));
        }

        return $this->success(lang('del_ok'));
    }

    /**
     * ============================================================
     * 更新任务状态
     * ============================================================
     *
     * 【功能说明】
     * 批量更新定时任务的状态字段
     * 目前仅支持 status 字段的更新
     *
     * 【请求参数】
     * - ids : 任务名称列表 (逗号分隔)
     * - col : 字段名 (目前仅支持 'status')
     * - val : 字段值 (0=禁用, 1=启用)
     *
     * @return \think\response\Json JSON响应
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        // 验证参数和字段白名单
        if(!empty($ids) && in_array($col,['status'])){
            $list = config('timming');
            $ids = explode(',',$ids);

            // 遍历更新匹配的任务
            foreach($list as $k=>&$v){
                if(in_array($k,$ids)){
                    $v[$col] = $val;
                }
            }

            // 写入配置文件
            $res = mac_arr2file(APP_PATH. 'extra/timming.php', $list);
            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        return $this->error(lang('param_err'));
    }
}
