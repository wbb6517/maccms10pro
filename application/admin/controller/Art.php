<?php
/**
 * 文章管理控制器 (Article Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台文章/资讯内容管理控制器
 * 处理文章的增删改查、批量操作、状态修改等功能
 *
 * 【菜单位置】
 * 后台管理 → 内容 → 文章管理
 *
 * 【数据表】
 * mac_art - 文章数据表
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ data()          │ 文章列表页 (分页查询、条件筛选)              │
 * │ info()          │ 文章详情/编辑页 (新增/修改文章)              │
 * │ del()           │ 删除文章 (单个/批量/重复数据)                │
 * │ field()         │ 批量修改字段 (状态/等级/点击量等)            │
 * │ batch()         │ 批量操作页面 (批量处理大量数据)              │
 * │ updateToday()   │ 更新今日数据统计                            │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/art/data   → 文章列表
 * admin.php/art/info   → 文章编辑
 * admin.php/art/del    → 删除文章
 * admin.php/art/field  → 修改字段
 * admin.php/art/batch  → 批量操作
 *
 * 【相关文件】
 * - application/common/model/Art.php : 文章模型
 * - application/admin/view/art/      : 视图文件目录
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Art extends Base
{
    /**
     * 构造函数
     * 调用父类 Base::__construct() 进行登录检测和权限验证
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 文章列表页
     * ============================================================
     *
     * 【功能说明】
     * 显示文章数据列表，支持多条件筛选和分页
     * 支持查找重复数据功能
     *
     * 【访问路径】
     * GET admin.php/art/data
     *
     * 【筛选参数】
     * - type   : 分类ID (支持一级和二级分类)
     * - level  : 推荐等级 (1-9)
     * - status : 审核状态 (0=未审核, 1=已审核)
     * - lock   : 锁定状态 (0=未锁定, 1=已锁定)
     * - pic    : 图片状态 (1=无图, 2=远程图, 3=错误图)
     * - wd     : 搜索关键词 (按文章名称模糊搜索)
     * - repeat : 是否查找重复数据
     *
     * 【重复数据查找机制】
     * 1. 首次访问创建临时表 mac_tmpart
     * 2. 将重复的文章名称和最小ID写入临时表
     * 3. 通过 listRepeatData() 方法关联查询
     *
     * 【模板变量】
     * $list      - 文章列表数据
     * $total     - 总记录数
     * $page      - 当前页码
     * $limit     - 每页条数
     * $param     - 筛选参数 (用于分页链接)
     * $type_tree - 分类树 (用于下拉筛选)
     *
     * 【模板位置】
     * application/admin/view/art/index.html
     *
     * @return string 渲染后的HTML页面
     */
    public function data()
    {
        $param = input();

        // ============================================================
        // 【分页参数处理】
        // ============================================================
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        // ============================================================
        // 【构建查询条件】
        // ============================================================
        $where = [];

        // 分类筛选 (同时匹配一级分类和二级分类)
        if (!empty($param['type'])) {
            $where['type_id|type_id_1'] = ['eq', $param['type']];
        }

        // 推荐等级筛选
        if (!empty($param['level'])) {
            $where['art_level'] = ['eq', $param['level']];
        }

        // 审核状态筛选 (注意: 0 也是有效值)
        if (in_array($param['status'], ['0', '1'])) {
            $where['art_status'] = ['eq', $param['status']];
        }

        // 锁定状态筛选
        if (!empty($param['lock'])) {
            $where['art_lock'] = ['eq', $param['lock']];
        }

        // 图片状态筛选
        // 1=无图片, 2=远程图片(http开头), 3=错误图片(含#err标记)
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['art_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['art_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['art_pic'] = ['like','%#err%'];
            }
        }

        // 关键词搜索 (按文章名称模糊匹配)
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $param['wd'] = mac_filter_xss($param['wd']);  // XSS过滤
            $where['art_name'] = ['like','%'.$param['wd'].'%'];
        }

        // ============================================================
        // 【查询数据】
        // ============================================================
        if(!empty($param['repeat'])){
            // -------------------- 重复数据查找模式 --------------------
            // 首次访问时创建临时表，存储重复的文章名称
            if($param['page'] ==1){
                // 删除旧的临时表
                Db::execute('DROP TABLE IF EXISTS '.config('database.prefix').'tmpart');
                // 创建临时表
                Db::execute('CREATE TABLE `'.config('database.prefix').'tmpart` (`id1` int unsigned DEFAULT NULL, `name1` varchar(1024) NOT NULL DEFAULT \'\') ENGINE=MyISAM');
                // 插入重复数据 (按名称分组，取最小ID)
                Db::execute('INSERT INTO `'.config('database.prefix').'tmpart` (SELECT min(art_id)as id1,art_name as name1 FROM '.config('database.prefix').'art GROUP BY name1 HAVING COUNT(name1)>1)');
            }
            $order='art_name asc';
            $res = model('Art')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            // -------------------- 普通列表模式 --------------------
            $order='art_time desc';  // 按更新时间倒序
            $res = model('Art')->listData($where,$order,$param['page'],$param['limit']);
        }

        // ============================================================
        // 【数据后处理】检查静态页生成状态
        // ============================================================
        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;  // 默认已生成
            // 如果开启了静态页功能，且生成时间早于更新时间，标记为未生成
            if($GLOBALS['config']['view']['art_detail'] >0 && $v['art_time_make'] < $v['art_time']){
                $v['ismake'] = 0;
            }
        }

        // ============================================================
        // 【分配模板变量】
        // ============================================================
        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        // 分页参数 (用于生成分页链接)
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        // 分类树 (用于下拉筛选框)
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $this->assign('title', lang('admin/art/title'));
        return $this->fetch('admin@art/index');
    }

    /**
     * ============================================================
     * 批量操作页面
     * ============================================================
     *
     * 【功能说明】
     * 对大量文章数据进行批量处理
     * 支持批量删除、修改等级、状态、锁定、点击量等
     * 采用分页处理机制，避免超时
     *
     * 【访问路径】
     * GET  admin.php/art/batch → 显示批量操作页面
     * POST admin.php/art/batch → 执行批量操作
     *
     * 【操作参数】
     * - ck_del    : 批量删除
     * - ck_level  : 批量修改等级 (配合 val_level)
     * - ck_status : 批量修改状态 (配合 val_status)
     * - ck_lock   : 批量修改锁定 (配合 val_lock)
     * - ck_hits   : 批量修改点击量 (配合 val_hits_min/max)
     *
     * 【执行流程】
     * 1. 根据筛选条件统计总数
     * 2. 分页处理 (每页100条)
     * 3. 逐条更新数据
     * 4. 自动跳转到下一页继续处理
     *
     * 【模板位置】
     * application/admin/view/art/batch.html
     *
     * @return string 渲染后的HTML或执行结果
     */
    public function batch()
    {
        $param = input();

        // ============================================================
        // 【POST请求】执行批量操作
        // ============================================================
        if (!empty($param)) {

            // 输出样式 (用于显示执行进度)
            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            // 验证是否选择了操作项
            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits']) ){
                return $this->error(lang('param_err'));
            }

            // -------------------- 构建筛选条件 --------------------
            $where = [];
            if(!empty($param['type'])){
                $where['type_id'] = ['eq',$param['type']];
            }
            if(!empty($param['level'])){
                $where['art_level'] = ['eq',$param['level']];
            }
            if(in_array($param['status'],['0','1'])){
                $where['art_status'] = ['eq',$param['status']];
            }
            if(!empty($param['lock'])){
                $where['art_lock'] = ['eq',$param['lock']];
            }
            if(!empty($param['pic'])){
                if($param['pic'] == '1'){
                    $where['art_pic'] = ['eq',''];
                }
                elseif($param['pic'] == '2'){
                    $where['art_pic'] = ['like','http%'];
                }
                elseif($param['pic'] == '3'){
                    $where['art_pic'] = ['like','%#err%'];
                }
            }
            if(!empty($param['wd'])){
                $param['wd'] = htmlspecialchars(urldecode($param['wd']));
                $where['art_name'] = ['like','%'.$param['wd'].'%'];
            }

            // -------------------- 批量删除模式 --------------------
            if($param['ck_del'] == 1){
                $res = model('Art')->delData($where);
                mac_echo(lang('multi_del_ok'));
                mac_jump( url('art/batch') ,3);
                exit;
            }

            // -------------------- 分页处理初始化 --------------------
            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;  // 每页处理100条
            }
            if(empty($param['total'])) {
                $param['total'] = model('Art')->countData($where);
                $param['page_count'] = ceil($param['total'] / $param['limit']);
            }

            // 所有页处理完成
            if($param['page'] > $param['page_count']) {
                mac_echo(lang('multi_set_ok'));
                mac_jump( url('art/batch') ,3);
                exit;
            }

            // 显示处理进度
            mac_echo( "<font color=red>".lang('admin/batch_tip',[$param['total'],$param['limit'],$param['page_count'],$param['page']])."</font>");

            // -------------------- 查询当前页数据 --------------------
            // 从最后一页开始处理，避免分页偏移问题
            $page = $param['page_count'] - $param['page'] + 1;
            $order='art_id desc';
            $res = model('Art')->listData($where,$order,$page,$param['limit']);

            // -------------------- 逐条更新数据 --------------------
            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['art_id'] = $v['art_id'];

                $update = [];
                $des = $v['art_id'].','.$v['art_name'];

                // 修改等级
                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['art_level'] = $param['val_level'];
                    $des .= '&nbsp;'.lang('level').'：'.$param['val_level'].'；';
                }
                // 修改状态
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['art_status'] = $param['val_status'];
                    $des .= '&nbsp;'.lang('status').'：'.($param['val_status'] ==1 ? '['.lang('reviewed').']':'['.lang('reviewed_not').']') .'；';
                }
                // 修改锁定
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['art_lock'] = $param['val_lock'];
                    $des .= '&nbsp;'.lang('lock').'：'.($param['val_lock']==1 ? '['.lang('lock').']':'['.lang('unlock').']').'；';
                }
                // 修改点击量 (随机范围)
                if(!empty($param['ck_hits']) && !empty($param['val_hits_min']) && !empty($param['val_hits_max']) ){
                    $update['art_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;'.lang('hits').'：'.$update['art_hits'].'；';
                }

                // 输出处理信息并执行更新
                mac_echo($des);
                $res2 = model('Art')->where($where2)->update($update);
            }

            // 跳转到下一页继续处理
            $param['page']++;
            $url = url('art/batch') .'?'. http_build_query($param);
            mac_jump( $url ,3);
            exit;
        }

        // ============================================================
        // 【GET请求】显示批量操作页面
        // ============================================================
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/art/title'));
        return $this->fetch('admin@art/batch');
    }

    /**
     * ============================================================
     * 文章详情/编辑页
     * ============================================================
     *
     * 【功能说明】
     * 新增或编辑单篇文章
     * GET 显示编辑表单，POST 保存数据
     *
     * 【访问路径】
     * GET  admin.php/art/info      → 新增文章
     * GET  admin.php/art/info?id=1 → 编辑文章
     * POST admin.php/art/info      → 保存文章
     *
     * 【模板变量】
     * $info          - 文章详情数据
     * $art_page_list - 分页内容列表 (多页文章)
     * $type_tree     - 分类树
     *
     * 【模板位置】
     * application/admin/view/art/info.html
     *
     * @return mixed 渲染后的HTML或JSON响应
     */
    public function info()
    {
        // ============================================================
        // 【POST请求】保存文章数据
        // ============================================================
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Art')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // ============================================================
        // 【GET请求】显示编辑表单
        // ============================================================
        $id = input('id');
        $where=[];
        $where['art_id'] = ['eq',$id];
        $res = model('Art')->infoData($where);

        $info = $res['info'];
        $this->assign('info',$info);
        // 分页内容列表 (用于多页文章编辑)
        $this->assign('art_page_list',(array)$info['art_page_list']);

        // 分类树 (用于下拉选择)
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/art/title'));
        return $this->fetch('admin@art/info');
    }

    /**
     * ============================================================
     * 删除文章
     * ============================================================
     *
     * 【功能说明】
     * 删除指定的文章数据
     * 支持单个删除、批量删除、删除重复数据
     *
     * 【访问路径】
     * POST admin.php/art/del
     *
     * 【请求参数】
     * - ids    : 文章ID (逗号分隔，用于普通删除)
     * - repeat : 是否删除重复数据
     * - retain : 保留方式 (max=保留最大ID, 其他=保留最小ID)
     *
     * 【重复数据删除机制】
     * 1. 从临时表 mac_tmpart 获取重复的文章名称和最小ID
     * 2. 根据 retain 参数决定保留最大还是最小ID
     * 3. 删除其他重复记录
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        // -------------------- 普通删除模式 --------------------
        if(!empty($ids)){
            $where=[];
            $where['art_id'] = ['in',$ids];
            $res = model('Art')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        // -------------------- 重复数据删除模式 --------------------
        elseif(!empty($param['repeat'])){
            $st = ' not in ';  // 默认保留最小ID
            if($param['retain']=='max'){
                $st=' in ';    // 保留最大ID (删除最小ID)
            }
            // 直接执行SQL删除重复数据
            $sql = 'delete from '.config('database.prefix').'art where art_name in(select name1 from '.config('database.prefix').'tmpart) and art_id '.$st.'(select id1 from '.config('database.prefix').'tmpart)';
            $res = model('Art')->execute($sql);
            if($res===false){
                return $this->success(lang('del_err'));
            }
            return $this->success(lang('del_ok'));
        }

        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 批量修改字段
     * ============================================================
     *
     * 【功能说明】
     * 批量修改指定文章的某个字段值
     * 支持状态、锁定、等级、点击量、分类等字段
     *
     * 【访问路径】
     * POST admin.php/art/field
     *
     * 【请求参数】
     * - ids   : 文章ID (逗号分隔)
     * - col   : 字段名 (art_status/art_lock/art_level/art_hits/type_id)
     * - val   : 字段值
     * - start : 随机范围起始值 (用于点击量)
     * - end   : 随机范围结束值 (用于点击量)
     *
     * 【特殊处理】
     * - type_id: 修改分类时会自动更新 type_id_1 (一级分类)
     * - art_hits: 支持随机范围赋值 (start-end)
     *
     * @return \think\response\Json JSON响应
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];
        $start = $param['start'];
        $end = $param['end'];

        // 分类必须选择
        if ($col == 'type_id' && $val==''){
            return $this->error("请选择分类提交");
        }

        // 验证字段是否在允许修改的白名单中
        if(!empty($ids) && in_array($col,['art_status','art_lock','art_level','art_hits','type_id'])){
            $where=[];
            $where['art_id'] = ['in',$ids];
            $update = [];

            if(empty($start)) {
                // -------------------- 固定值模式 --------------------
                $update[$col] = $val;
                // 修改分类时，自动更新一级分类ID
                if($col == 'type_id'){
                    $type_list = model('Type')->getCache();
                    $id1 = intval($type_list[$val]['type_pid']);
                    $update['type_id_1'] = $id1;
                }
                $res = model('Art')->fieldData($where, $update);
            }
            else{
                // -------------------- 随机范围模式 (用于点击量) --------------------
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);  // 生成随机值
                    $where['art_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Art')->fieldData($where, $update);
                }
            }

            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 更新今日数据统计
     * ============================================================
     *
     * 【功能说明】
     * 更新文章的今日统计数据
     * 用于首页仪表盘显示今日新增文章数等
     *
     * 【访问路径】
     * POST admin.php/art/updateToday
     *
     * 【请求参数】
     * - flag : 操作标识
     *
     * @return \think\response\Json JSON响应
     */
    public function updateToday()
    {
        $param = input();
        $flag = $param['flag'];
        $res = model('Art')->updateToday($flag);
        return json($res);
    }

}