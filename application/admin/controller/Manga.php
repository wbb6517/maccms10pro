<?php
/**
 * 漫画管理控制器 (Manga Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台漫画内容管理控制器
 * 提供漫画列表、添加/编辑、批量操作、删除等功能
 * 支持重复数据检测、今日更新统计、Tag自动生成
 *
 * 【菜单位置】
 * 后台管理 → 漫画 (顶级菜单, 索引13)
 *
 * 【数据表】
 * mac_manga - 漫画数据表
 *
 * 【方法列表】
 * ┌─────────────────────┬────────────────────────────────────────────┐
 * │ 方法名               │ 功能说明                                    │
 * ├─────────────────────┼────────────────────────────────────────────┤
 * │ __construct()       │ 构造函数                                    │
 * │ data()              │ 漫画列表页 (筛选、分页、重复检测)              │
 * │ batch()             │ 批量操作 (删除、设置等级/状态/锁定/点击)       │
 * │ info()              │ 漫画详情/添加/编辑                           │
 * │ del()               │ 删除漫画 (单条/批量/重复)                     │
 * │ field()             │ 字段快捷修改 (状态/锁定/等级/点击/分类)        │
 * │ updateToday()       │ 获取今日更新ID列表                           │
 * │ batchGenerateTag()  │ 批量生成Tag标签                              │
 * └─────────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/manga/data  → 漫画列表
 * admin.php/manga/info  → 漫画详情
 * admin.php/manga/batch → 批量操作
 *
 * 【相关文件】
 * - application/common/model/Manga.php : 漫画模型
 * - application/admin/view_new/manga/index.html : 漫画列表视图
 * - application/admin/view_new/manga/info.html : 漫画详情视图
 * - application/admin/view_new/manga/batch.html : 批量操作视图
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Manga extends Base
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
     * 漫画列表页
     * ============================================================
     *
     * 【功能说明】
     * 显示漫画数据列表，支持多条件筛选和分页
     * 支持重复数据检测模式，用于发现同名漫画
     *
     * 【筛选参数】
     * - type   : 分类ID
     * - level  : 推荐等级
     * - status : 审核状态 (0=未审/1=已审)
     * - lock   : 锁定状态
     * - pic    : 图片状态 (1=无图/2=外链/3=错误)
     * - wd     : 关键词搜索 (漫画名称)
     * - url    : 章节URL (1=无URL)
     * - points : 积分 (1=有积分)
     * - repeat : 重复模式 (查找同名漫画)
     *
     * @return mixed 渲染漫画列表视图
     */
    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        // ==================== 构建查询条件 ====================
        $where = [];
        if (!empty($param['type'])) {
            $where['type_id|type_id_1'] = ['eq', $param['type']];
        }
        if (!empty($param['level'])) {
            $where['manga_level'] = ['eq', $param['level']];
        }
        if (in_array($param['status'], ['0', '1'])) {
            $where['manga_status'] = ['eq', $param['status']];
        }
        if (!empty($param['lock'])) {
            $where['manga_lock'] = ['eq', $param['lock']];
        }
        // 图片筛选: 1=无图, 2=外链图片, 3=图片错误
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['manga_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['manga_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['manga_pic'] = ['like','%#err%'];
            }
        }
        // 关键词搜索 (XSS过滤)
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $param['wd'] = mac_filter_xss($param['wd']);
            $where['manga_name'] = ['like','%'.$param['wd'].'%'];
        }

        // 无章节URL筛选
        if(!empty($param['url'])){
            if($param['url'] == '1'){
                $where['manga_chapter_url'] = ['eq',''];
            }
        }
        // 有积分漫画筛选
        if(!empty($param['points'])){
            if($param['points'] == '1'){
                $where['manga_points'] = ['gt',0];
            }
        }

        // ==================== 查询数据 ====================
        if(!empty($param['repeat'])){
            // 重复数据检测模式: 创建临时表存储重复名称
            if($param['page'] ==1){
                Db::execute('DROP TABLE IF EXISTS '.config('database.prefix').'tmpmanga');
                Db::execute('CREATE TABLE `'.config('database.prefix').'tmpmanga` (`id1` int unsigned DEFAULT NULL, `name1` varchar(1024) NOT NULL DEFAULT \'\') ENGINE=MyISAM');
                Db::execute('INSERT INTO `'.config('database.prefix').'tmpmanga` (SELECT min(manga_id)as id1,manga_name as name1 FROM '.config('database.prefix').'manga GROUP BY name1 HAVING COUNT(name1)>1)');
            }
            $order='manga_name asc';
            $res = model('Manga')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            // 正常列表模式
            $order='manga_time desc';
            $res = model('Manga')->listData($where,$order,$param['page'],$param['limit']);
        }

        // 检查静态页生成状态
        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['manga_detail'] >0 && $v['manga_time_make'] < $v['manga_time']){
                $v['ismake'] = 0;  // 需要重新生成
            }
        }

        // ==================== 分配模板变量 ====================
        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        // 分类树
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $this->assign('title', lang('admin/manga/title'));
        return $this->fetch('admin@manga/index');
    }

    /**
     * ============================================================
     * 批量操作
     * ============================================================
     *
     * 【功能说明】
     * 对筛选出的漫画进行批量操作
     * 支持批量删除、设置等级/状态/锁定/点击量
     * 使用分页递归处理大量数据，每页100条
     *
     * 【批量操作类型】
     * - ck_del    : 批量删除
     * - ck_level  : 批量设置推荐等级
     * - ck_status : 批量设置审核状态
     * - ck_lock   : 批量设置锁定状态
     * - ck_hits   : 批量随机点击量
     *
     * @return mixed 渲染批量操作视图或输出处理结果
     */
    public function batch()
    {
        $param = input();
        if (!empty($param)) {

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            // 至少选择一种操作类型
            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits']) ){
                return $this->error(lang('param_err'));
            }

            // ==================== 构建筛选条件 ====================
            $where = [];
            if(!empty($param['type'])){
                $where['type_id'] = ['eq',$param['type']];
            }
            if(!empty($param['level'])){
                $where['manga_level'] = ['eq',$param['level']];
            }
            if(in_array($param['status'],['0','1'])){
                $where['manga_status'] = ['eq',$param['status']];
            }
            if(!empty($param['lock'])){
                $where['manga_lock'] = ['eq',$param['lock']];
            }
            // 图片筛选条件
            if(!empty($param['pic'])){
                if($param['pic'] == '1'){
                    $where['manga_pic'] = ['eq',''];
                }
                elseif($param['pic'] == '2'){
                    $where['manga_pic'] = ['like','http%'];
                }
                elseif($param['pic'] == '3'){
                    $where['manga_pic'] = ['like','%#err%'];
                }
            }
            if(!empty($param['wd'])){
                $param['wd'] = htmlspecialchars(urldecode($param['wd']));
                $where['manga_name'] = ['like','%'.$param['wd'].'%'];
            }

            // ==================== 批量删除模式 ====================
            if($param['ck_del'] == 1){
                $res = model('Manga')->delData($where);
                mac_echo(lang('multi_del_ok'));
                mac_jump( url('manga/batch') ,3);
                exit;
            }

            // ==================== 分页处理初始化 ====================
            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;  // 每页处理100条
            }
            if(empty($param['total'])) {
                $param['total'] = model('Manga')->countData($where);
                $param['page_count'] = ceil($param['total'] / $param['limit']);
            }

            // 检查是否已处理完毕
            if($param['page'] > $param['page_count']) {
                mac_echo(lang('multi_set_ok'));
                mac_jump( url('manga/batch') ,3);
                exit;
            }
            mac_echo( "<font color=red>".lang('admin/batch_tip',[$param['total'],$param['limit'],$param['page_count'],$param['page']])."</font>");

            // 倒序分页处理 (避免更新影响分页)
            $page = $param['page_count'] - $param['page'] + 1;
            $order='manga_id desc';
            $res = model('Manga')->listData($where,$order,$page,$param['limit']);

            // ==================== 逐条更新数据 ====================
            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['manga_id'] = $v['manga_id'];

                $update = [];
                $des = $v['manga_id'].','.$v['manga_name'];

                // 设置推荐等级
                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['manga_level'] = $param['val_level'];
                    $des .= '&nbsp;'.lang('level').'：'.$param['val_level'].'；';
                }
                // 设置审核状态
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['manga_status'] = $param['val_status'];
                    $des .= '&nbsp;'.lang('status').'：'.($param['val_status'] ==1 ? '['.lang('reviewed').']':'['.lang('reviewed_not').']') .'；';
                }
                // 设置锁定状态
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['manga_lock'] = $param['val_lock'];
                    $des .= '&nbsp;'.lang('lock').'：'.($param['val_lock']==1 ? '['.lang('lock').']':'['.lang('unlock').']').'；';
                }
                // 设置随机点击量
                if(!empty($param['ck_hits']) && !empty($param['val_hits_min']) && !empty($param['val_hits_max']) ){
                    $update['manga_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;'.lang('hits').'：'.$update['manga_hits'].'；';
                }
                mac_echo($des);
                $res2 = model('Manga')->where($where2)->update($update);

            }
            // 跳转下一页继续处理
            $param['page']++;
            $url = url('manga/batch') .'?'. http_build_query($param);
            mac_jump( $url ,3);
            exit;
        }

        // GET请求: 显示批量操作页面
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/manga/title'));
        return $this->fetch('admin@manga/batch');
    }

    /**
     * ============================================================
     * 漫画详情/添加/编辑
     * ============================================================
     *
     * 【功能说明】
     * GET请求: 显示漫画详情编辑页面
     * POST请求: 保存漫画数据 (新增或更新)
     *
     * 【表单字段】
     * - 基本信息: 名称、副标题、英文名、分类
     * - 属性: 等级、状态、锁定、积分
     * - 内容: 章节列表、简介、备注
     * - 图片: 封面、缩略图、截图
     * - 关联: 关联漫画、关联视频
     *
     * @return mixed 渲染详情视图或返回保存结果
     */
    public function info()
    {
        if (Request()->isPost()) {
            // POST请求: 保存漫画数据
            $param = input('post.');
            $res = model('Manga')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // GET请求: 获取漫画详情
        $id = input('id');
        $where=[];
        $where['manga_id'] = ['eq',$id];
        $res = model('Manga')->infoData($where);

        $info = $res['info'];
        if (empty($info)) {
            $info = [];
        }
        $this->assign('info',$info);
        // 章节列表数据
        $this->assign('manga_page_list', !empty($info['manga_page_list']) ? (array)$info['manga_page_list'] : []);

        // 分类树
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/manga/title'));
        return $this->fetch('admin@manga/info');
    }

    /**
     * ============================================================
     * 删除漫画
     * ============================================================
     *
     * 【功能说明】
     * 删除指定的漫画数据
     * 支持单条删除、批量删除、重复数据删除
     *
     * 【删除模式】
     * - ids : 按ID删除 (支持逗号分隔的多个ID)
     * - repeat : 删除重复数据
     *   - retain=min : 保留ID最小的记录
     *   - retain=max : 保留ID最大的记录
     *
     * @return \think\response\Json 删除结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            // 按ID删除
            $where=[];
            $where['manga_id'] = ['in',$ids];
            $res = model('Manga')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        elseif(!empty($param['repeat'])){
            // 删除重复数据 (基于临时表)
            $st = ' not in ';
            if($param['retain']=='max'){
                $st=' in ';  // 保留最大ID = 删除在临时表中的记录
            }
            // 删除同名漫画中不需要保留的记录
            $sql = 'delete from '.config('database.prefix').'manga where manga_name in(select name1 from '.config('database.prefix').'tmpmanga) and manga_id '.$st.'(select id1 from '.config('database.prefix').'tmpmanga)';
            $res = model('Manga')->execute($sql);
            if($res===false){
                return $this->success(lang('del_err'));
            }
            return $this->success(lang('del_ok'));
        }
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 字段快捷修改
     * ============================================================
     *
     * 【功能说明】
     * 快速修改漫画的单个字段值
     * 常用于列表页的状态切换、等级调整等
     *
     * 【支持的字段】
     * - manga_status : 审核状态
     * - manga_lock   : 锁定状态
     * - manga_level  : 推荐等级
     * - manga_hits   : 点击量 (支持随机范围)
     * - type_id      : 分类ID (同时更新一级分类)
     *
     * @return \think\response\Json 修改结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];     // 字段名
        $val = $param['val'];     // 字段值
        $start = $param['start']; // 随机范围起始
        $end = $param['end'];     // 随机范围结束

        // 分类字段必须有值
        if ($col == 'type_id' && $val==''){
            return $this->error("请选择分类提交");
        }

        if(!empty($ids) && in_array($col,['manga_status','manga_lock','manga_level','manga_hits','type_id'])){
            $where=[];
            $where['manga_id'] = ['in',$ids];
            $update = [];

            if(empty($start)) {
                // 直接设置值
                $update[$col] = $val;
                // 如果修改分类，同时更新一级分类ID
                if($col == 'type_id'){
                    $type_list = model('Type')->getCache();
                    $id1 = intval($type_list[$val]['type_pid']);
                    $update['type_id_1'] = $id1;
                }
                $res = model('Manga')->fieldData($where, $update);
            }
            else{
                // 随机范围设置 (用于点击量)
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['manga_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Manga')->fieldData($where, $update);
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
     * 获取今日更新ID列表
     * ============================================================
     *
     * 【功能说明】
     * 获取今天更新的漫画ID或分类ID列表
     * 用于前台今日更新展示
     *
     * @param string flag 返回类型: manga=漫画ID, type=分类ID
     * @return \think\response\Json 包含ID列表的JSON
     */
    public function updateToday()
    {
        $param = input();
        $flag = $param['flag'];
        $res = model('Manga')->updateToday($flag);
        return json($res);
    }

    /**
     * ============================================================
     * 批量生成Tag标签
     * ============================================================
     *
     * 【功能说明】
     * 为选中的漫画自动生成Tag标签
     * 基于漫画名称和内容进行智能分词提取
     *
     * @return \think\response\Json 生成结果统计
     */
    public function batchGenerateTag()
    {
        $ids = input('post.ids/a');
        if(empty($ids)){
            return json(['code'=>0,'msg'=>lang('admin/tag/select_manga_tag')]);
        }

        $success = 0;
        $fail = 0;
        foreach($ids as $id){
            // 获取漫画信息
            $info = model('Manga')->where('manga_id',$id)->find();
            if($info){
                // 基于名称和内容生成Tag
                $tag = mac_get_tag($info['manga_name'], $info['manga_content']);
                if($tag !== false){
                    $res = model('Manga')->where('manga_id',$id)->update(['manga_tag'=>$tag]);
                    if($res){
                        $success++;
                    }else{
                        $fail++;
                    }
                }else{
                    $fail++;
                }
            }
        }

        return json(['code'=>1,'msg'=>sprintf(lang('admin/tag/generate_tag_result'), $success, $fail)]);
    }

}
