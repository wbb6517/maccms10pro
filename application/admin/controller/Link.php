<?php
/**
 * ============================================================
 * 友情链接管理控制器 (Link Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台 "基础 → 友情链接" 功能的控制器
 * 管理网站底部或侧边栏显示的友情链接
 *
 * 【访问路径】
 * admin.php/link/index  → 友链列表
 * admin.php/link/info   → 添加/编辑友链
 * admin.php/link/del    → 删除友链
 * admin.php/link/batch  → 批量编辑友链
 *
 * 【方法列表】
 * ┌──────────────┬──────────────────────────────────────┐
 * │ 方法名        │ 功能说明                              │
 * ├──────────────┼──────────────────────────────────────┤
 * │ index()      │ 友链列表页面 (支持分页、搜索)           │
 * │ info()       │ 友链添加/编辑页面                      │
 * │ del()        │ 删除友链 (支持批量)                    │
 * │ batch()      │ 批量更新友链信息                       │
 * └──────────────┴──────────────────────────────────────┘
 *
 * 【数据表字段说明】
 * ┌──────────────┬─────────────────────────────────────────┐
 * │ 字段名        │ 说明                                     │
 * ├──────────────┼─────────────────────────────────────────┤
 * │ link_id      │ 友链ID (主键)                            │
 * │ link_name    │ 友链名称/网站名                          │
 * │ link_url     │ 链接地址 (完整URL)                       │
 * │ link_logo    │ Logo图片地址 (图片链接时使用)             │
 * │ link_type    │ 链接类型: 0=文字链接, 1=图片链接          │
 * │ link_sort    │ 排序值 (数字小的在前)                    │
 * │ link_time    │ 更新时间戳                               │
 * │ link_add_time│ 添加时间戳                               │
 * └──────────────┴─────────────────────────────────────────┘
 *
 * 【友链类型说明】
 * - 文字链接 (link_type=0): 只显示 link_name 文字
 * - 图片链接 (link_type=1): 显示 link_logo 图片，鼠标悬停显示 link_name
 *
 * @package     app\admin\controller
 * @author      MacCMS
 * @version     1.0
 */
namespace app\admin\controller;

class Link extends Base
{
    /**
     * 构造函数
     * 调用父类构造函数完成权限验证等初始化
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 友链列表页面
     *
     * 【功能说明】
     * 显示所有友情链接列表，支持按名称搜索和分页
     * 列表页面支持直接编辑排序、类型、名称、URL、Logo等字段
     *
     * 【请求参数】
     * - wd    : 搜索关键词 (按友链名称模糊搜索)
     * - page  : 当前页码 (默认1)
     * - limit : 每页条数 (默认使用系统配置 _pagesize)
     *
     * 【模板变量】
     * - list  : 友链列表数据
     * - total : 总记录数
     * - page  : 当前页码
     * - limit : 每页条数
     * - param : 搜索参数 (用于分页链接保持搜索状态)
     *
     * @return mixed 渲染后的列表页面
     */
    public function index()
    {
        // 获取请求参数
        $param = input();
        // 处理分页参数，确保为正整数
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=[];

        // 构建搜索条件：按友链名称模糊搜索
        if(!empty($param['wd'])){
            // XSS过滤和URL解码
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['link_name'] = ['like','%'.$param['wd'].'%'];
        }

        // 按ID倒序排列 (最新添加的在前)
        $order='link_id desc';
        // 调用模型获取列表数据
        $res = model('Link')->listData($where,$order,$param['page'],$param['limit']);

        // 分配模板变量
        $this->assign('list',$res['list']);      // 友链列表
        $this->assign('total',$res['total']);    // 总数
        $this->assign('page',$res['page']);      // 当前页
        $this->assign('limit',$res['limit']);    // 每页条数

        // 设置分页占位符，用于前端JS替换
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/link/title'));  // 页面标题
        return $this->fetch('admin@link/index');
    }

    /**
     * 友链添加/编辑页面
     *
     * 【功能说明】
     * - GET 请求: 显示添加/编辑表单
     * - POST 请求: 保存友链数据
     *
     * 【请求参数】
     * GET:
     * - id : 友链ID (编辑时传入，添加时不传)
     *
     * POST:
     * - link_id   : 友链ID (编辑时有值)
     * - link_name : 友链名称 (必填)
     * - link_url  : 链接地址 (必填)
     * - link_logo : Logo图片地址
     * - link_type : 链接类型 (0=文字, 1=图片)
     * - link_sort : 排序值
     *
     * @return mixed POST时返回JSON，GET时返回页面
     */
    public function info()
    {
        // POST请求：保存数据
        if (Request()->isPost()) {
            $param = input();
            // 调用模型保存数据
            $res = model('Link')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // GET请求：显示表单
        $id = input('id');
        $where=[];
        $where['link_id'] = ['eq',$id];
        // 获取友链详情 (编辑时有数据，添加时为空)
        $res = model('Link')->infoData($where);

        $this->assign('info',$res['info']);
        $this->assign('title',lang('admin/link/title'));
        return $this->fetch('admin@link/info');
    }

    /**
     * 删除友链
     *
     * 【功能说明】
     * 根据ID删除一个或多个友情链接
     * 支持批量删除 (ids为数组或逗号分隔的字符串)
     *
     * 【请求参数】
     * - ids : 要删除的友链ID (支持单个ID或数组)
     *
     * @return json 操作结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['link_id'] = ['in',$ids];
            // 调用模型删除数据
            $res = model('Link')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 批量更新友链
     *
     * 【功能说明】
     * 在列表页面直接编辑多条友链信息后批量保存
     * 一次性更新多条记录的排序、名称、URL、类型、Logo等字段
     *
     * 【请求参数】
     * - ids[]       : 友链ID数组
     * - link_name[] : 友链名称数组
     * - link_sort[] : 排序值数组
     * - link_url[]  : URL地址数组
     * - link_type[] : 链接类型数组
     * - link_logo[] : Logo地址数组
     *
     * 【处理逻辑】
     * 遍历所有提交的数据，逐条调用 saveData 保存
     * 如果某条保存失败则立即返回错误
     *
     * @return json 操作结果
     */
    public function batch()
    {
        $param = input();
        $ids = $param['ids'];
        // 遍历每条友链数据
        foreach ($ids as $k=>$id) {
            // 组装单条数据
            $data = [];
            $data['link_id'] = intval($id);
            $data['link_name'] = $param['link_name'][$k];
            $data['link_sort'] = $param['link_sort'][$k];
            $data['link_url'] = $param['link_url'][$k];
            $data['link_type'] = intval($param['link_type'][$k]);
            $data['link_logo'] = $param['link_logo'][$k];

            // 友链名称为空时设置默认值
            if (empty($data['link_name'])) {
                $data['link_name'] = lang('unknown');
            }
            // 调用模型保存单条数据
            $res = model('Link')->saveData($data);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
        }
        $this->success($res['msg']);
    }

}