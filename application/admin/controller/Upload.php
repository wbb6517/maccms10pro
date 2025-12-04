<?php
/**
 * 文件上传管理控制器 (Upload Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台文件上传管理控制器
 * 提供图片、文件、媒体等资源的上传功能入口
 * 实际上传逻辑由 Upload 模型处理
 *
 * 【菜单位置】
 * 功能性控制器，不在后台菜单中直接显示 (show=0)
 * 属于 数据库 模块 (索引10 → 1005)
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数                                    │
 * │ index()         │ 上传页面                                    │
 * │ test()          │ 测试临时目录写入权限                         │
 * │ upload()        │ 执行文件上传                                │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/upload/index  → 显示上传表单页面
 * admin.php/upload/test   → 测试临时目录写入权限
 * admin.php/upload/upload → 执行文件上传
 *
 * 【相关文件】
 * - application/common/model/Upload.php : 上传模型 (核心上传逻辑)
 * - application/common/extend/upload/   : 上传扩展目录 (又拍云/七牛/FTP等)
 * - application/common/extend/editor/   : 编辑器扩展目录
 * - application/admin/view_new/upload/index.html : 上传表单视图
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Upload extends Base
{
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
     * 上传页面
     * ============================================================
     *
     * 【功能说明】
     * 显示文件上传表单页面
     * 用于内嵌在弹窗或iframe中进行文件选择和上传
     *
     * 【请求参数】
     * - path : 上传目标路径
     * - id   : 关联元素ID
     *
     * @return mixed 渲染上传表单页面
     */
    public function index()
    {
        $param = input();
        $this->assign('path',$param['path']);
        $this->assign('id',$param['id']);

        $this->assign('title',lang('upload_pic'));
        return $this->fetch('admin@upload/index');
    }

    /**
     * ============================================================
     * 测试临时目录写入权限
     * ============================================================
     *
     * 【功能说明】
     * 测试系统临时目录是否可写
     * 用于排查上传功能故障
     *
     * @return void 输出测试结果
     */
    public function test()
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'Tux');
        if($temp_file){
            echo lang('admin/upload/test_write_ok').'：' . $temp_file;
        }
        else{
            echo lang('admin/upload/test_write_err').'：' . sys_get_temp_dir() ;
        }
    }

    /**
     * ============================================================
     * 执行文件上传
     * ============================================================
     *
     * 【功能说明】
     * 调用 Upload 模型执行实际的文件上传操作
     * 支持图片、文档、媒体文件等多种类型
     *
     * @param array $p 可选的外部参数 (用于API调用)
     * @return array 上传结果数组
     */
    public function upload($p=[])
    {
        return model('Upload')->upload($p);
    }

}
