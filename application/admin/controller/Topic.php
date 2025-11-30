<?php
/**
 * ============================================================
 * 专题管理控制器 (Topic Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 处理后台"基础 - 专题管理"功能模块，包括：
 * - 专题列表展示 (支持搜索、状态筛选)
 * - 专题添加/编辑/删除
 * - 专题状态和等级设置
 * - 专题关联视频和文章
 *
 * 【菜单位置】
 * 后台菜单 → 基础 → 专题管理
 *
 * 【数据表】
 * mac_topic - 专题主表
 *
 * 【专题特点】
 * 专题是一种内容聚合方式，可以：
 * - 手动关联多个视频 (topic_rel_vod)
 * - 手动关联多个文章 (topic_rel_art)
 * - 通过TAG标签自动关联内容
 * - 设置等级控制推荐位置 (level 9 = 幻灯片)
 *
 * 【方法列表】
 * ┌──────────────┬──────────────────────────────────────┐
 * │ 方法名        │ 功能说明                              │
 * ├──────────────┼──────────────────────────────────────┤
 * │ data()       │ 专题列表页面 (支持分页、搜索)           │
 * │ info()       │ 专题添加/编辑页面                      │
 * │ del()        │ 删除专题                              │
 * │ field()      │ 更新单个字段 (状态/等级)               │
 * └──────────────┴──────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/topic/data   → 专题列表
 * admin.php/topic/info   → 添加/编辑专题
 * admin.php/topic/del    → 删除专题
 * admin.php/topic/field  → 更新字段
 *
 * 【相关文件】
 * - application/common/model/Topic.php        : 专题模型
 * - application/admin/view_new/topic/index.html : 列表模板
 * - application/admin/view_new/topic/info.html  : 编辑模板
 *
 * ============================================================
 */

namespace app\admin\controller;
use think\Db;

class Topic extends Base
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
     * 专题列表页面
     * ============================================================
     *
     * 【访问路径】
     * GET admin.php/topic/data
     *
     * 【请求参数】
     * - page   : 当前页码 (默认1)
     * - limit  : 每页数量 (默认后台配置的pagesize)
     * - status : 状态筛选 (0=未审核, 1=已审核)
     * - wd     : 搜索关键词 (按专题名称模糊搜索)
     *
     * 【功能说明】
     * 1. 支持按状态筛选专题
     * 2. 支持按名称关键词搜索
     * 3. 检测专题是否需要重新生成静态页面
     * 4. 分页展示专题列表
     *
     * 【页面结构】
     * ┌──────────────────────────────────────────────────────┐
     * │ 搜索栏: [状态下拉] [关键词输入] [搜索按钮]            │
     * ├──────────────────────────────────────────────────────┤
     * │ 工具栏: [添加] [删除] [等级] [状态] [图片同步]        │
     * ├──────────────────────────────────────────────────────┤
     * │ 专题列表 (ID/名称/点击/评分/等级/浏览/更新时间/操作)  │
     * ├──────────────────────────────────────────────────────┤
     * │ 分页导航                                              │
     * └──────────────────────────────────────────────────────┘
     *
     * 【模板位置】
     * application/admin/view_new/topic/index.html
     *
     * @return string 渲染后的HTML页面
     */
    public function data()
    {
        // --------------------------------------------------------
        // 【获取请求参数】
        // --------------------------------------------------------
        $param = input();
        // 页码处理：小于1时默认为1
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        // 每页数量：默认使用后台配置的分页数
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        // --------------------------------------------------------
        // 【构建查询条件】
        // --------------------------------------------------------
        $where=[];

        // 状态筛选：0=未审核, 1=已审核
        if(in_array($param['status'],['0','1'],true)){
            $where['topic_status'] = ['eq',$param['status']];
        }

        // 关键词搜索：按专题名称模糊匹配
        if(!empty($param['wd'])){
            // 对搜索词进行XSS过滤
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['topic_name'] = ['like','%'.$param['wd'].'%'];
        }

        // --------------------------------------------------------
        // 【查询专题数据】
        // --------------------------------------------------------
        // 按更新时间倒序排列
        $order='topic_time desc';
        $res = model('Topic')->listData($where,$order,$param['page'],$param['limit']);

        // --------------------------------------------------------
        // 【检测静态页面生成状态】
        // --------------------------------------------------------
        // ismake 标记专题是否需要重新生成静态页面
        // 当开启专题详情静态化且生成时间早于更新时间时，需要重新生成
        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1; // 默认已生成
            // 检查配置是否开启专题详情静态化 且 生成时间早于更新时间
            if($GLOBALS['config']['view']['topic_detail'] >0 && $v['topic_time_make'] < $v['topic_time']){
                $v['ismake'] = 0; // 需要重新生成
            }
        }

        // --------------------------------------------------------
        // 【渲染模板】
        // --------------------------------------------------------
        $this->assign('list',$res['list']);     // 专题列表
        $this->assign('total',$res['total']);   // 总记录数
        $this->assign('page',$res['page']);     // 当前页码
        $this->assign('limit',$res['limit']);   // 每页数量

        // 设置分页URL占位符，用于JS动态生成分页链接
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/topic/title'));
        return $this->fetch('admin@topic/index');
    }

    /**
     * ============================================================
     * 专题添加/编辑页面
     * ============================================================
     *
     * 【访问路径】
     * GET  admin.php/topic/info       → 显示添加页面
     * GET  admin.php/topic/info?id=X  → 显示编辑页面 (id为专题ID)
     * POST admin.php/topic/info       → 保存专题数据
     *
     * 【页面字段说明 - 基本信息Tab】
     * ┌──────────────────┬───────────────────────────────────┐
     * │ 字段              │ 说明                               │
     * ├──────────────────┼───────────────────────────────────┤
     * │ topic_status     │ 状态 (0=未审核/1=已审核)           │
     * │ topic_level      │ 等级 (1-8普通, 9=幻灯片)           │
     * │ topic_name       │ 专题名称 (必填)                    │
     * │ topic_sub        │ 副标题                             │
     * │ topic_en         │ 英文标识 (URL别名)                 │
     * │ topic_letter     │ 首字母                             │
     * │ topic_color      │ 标题颜色 (颜色选择器)              │
     * │ topic_remarks    │ 备注信息                           │
     * │ topic_sort       │ 排序值                             │
     * │ topic_type       │ 分类 (逗号分隔)                    │
     * │ topic_tpl        │ 模板文件 (默认detail.html)         │
     * │ topic_tag        │ TAG标签 (用于自动关联内容)         │
     * │ topic_rel_vod    │ 关联视频ID (逗号分隔)              │
     * │ topic_rel_art    │ 关联文章ID (逗号分隔)              │
     * │ topic_pic        │ 专题图片                           │
     * │ topic_pic_thumb  │ 缩略图                             │
     * │ topic_pic_slide  │ 幻灯片图                           │
     * │ topic_blurb      │ 简介 (自动从内容提取)              │
     * │ topic_content    │ 详细内容 (富文本编辑器)            │
     * └──────────────────┴───────────────────────────────────┘
     *
     * 【页面字段说明 - 其他信息Tab】
     * ┌──────────────────┬───────────────────────────────────┐
     * │ 字段              │ 说明                               │
     * ├──────────────────┼───────────────────────────────────┤
     * │ topic_key        │ SEO关键词                          │
     * │ topic_des        │ SEO描述                            │
     * │ topic_title      │ SEO标题                            │
     * │ topic_up         │ 顶次数                             │
     * │ topic_down       │ 踩次数                             │
     * │ topic_hits       │ 总点击量                           │
     * │ topic_hits_month │ 月点击量                           │
     * │ topic_hits_week  │ 周点击量                           │
     * │ topic_hits_day   │ 日点击量                           │
     * │ topic_score      │ 评分                               │
     * │ topic_score_all  │ 评分总数                           │
     * │ topic_score_num  │ 评分人数                           │
     * └──────────────────┴───────────────────────────────────┘
     *
     * 【模板位置】
     * application/admin/view_new/topic/info.html
     *
     * @return mixed 配置页面HTML 或 JSON响应
     */
    public function info()
    {
        // ============================================================
        // 【POST请求】保存专题数据
        // ============================================================
        if (Request()->isPost()) {
            $param = input('post.');
            // 调用模型保存数据
            // saveData() 方法会自动判断是新增还是更新
            $res = model('Topic')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // ============================================================
        // 【GET请求】显示专题编辑/添加页面
        // ============================================================

        // 获取专题ID (编辑时传入)
        $id = input('id');
        $where=[];
        $where['topic_id'] = ['eq',$id];
        // 获取专题详情
        $res = model('Topic')->infoData($where);

        // 赋值到模板
        $this->assign('info',$res['info']);

        // 获取网站配置中的安装目录 (用于图片路径处理)
        $config = config('maccms.site');
        $this->assign('install_dir',$config['install_dir']);
        $this->assign('title',lang('admin/topic/title'));
        return $this->fetch('admin@topic/info');
    }

    /**
     * ============================================================
     * 删除专题
     * ============================================================
     *
     * 【访问路径】
     * POST admin.php/topic/del
     *
     * 【请求参数】
     * - ids: 要删除的专题ID (支持数组，批量删除)
     *
     * 【执行流程】
     * 1. 验证参数
     * 2. 删除专题图片文件 (在模型中处理)
     * 3. 删除静态页面文件 (在模型中处理)
     * 4. 删除数据库记录
     *
     * @return \think\response\Json 操作结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['topic_id'] = ['in',$ids];
            // 调用模型删除方法
            // delData() 会同时删除相关的图片和静态文件
            $res = model('Topic')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 更新单个字段 (状态/等级)
     * ============================================================
     *
     * 【访问路径】
     * POST admin.php/topic/field
     *
     * 【请求参数】
     * - ids: 专题ID (支持数组)
     * - col: 字段名 (topic_status 或 topic_level)
     * - val: 字段值
     *
     * 【使用场景】
     * - 批量设置专题状态 (审核/未审核)
     * - 批量设置专题等级 (1-9)
     * - 列表页单个专题等级点击修改
     *
     * 【等级说明】
     * - 1-8: 普通等级，用于推荐排序
     * - 9: 幻灯片位，用于首页轮播
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];   // 专题ID
        $col = $param['col'];   // 字段名
        $val = $param['val'];   // 字段值

        // 验证参数
        // - ids 不为空
        // - col 只能是 topic_status 或 topic_level (安全限制)
        if(!empty($ids) && in_array($col,['topic_status','topic_level']) ){
            $where=[];
            $where['topic_id'] = ['in',$ids];

            // 调用模型更新字段
            $res = model('Topic')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}