<?php
/**
 * 分类管理控制器 (Type Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 处理后台"基础 - 分类管理"功能模块，包括：
 * - 分类列表展示 (树形结构)
 * - 分类添加/编辑/删除
 * - 分类状态切换
 * - 分类数据批量操作
 * - 分类数据转移
 * - 分类扩展属性获取
 *
 * 【菜单位置】
 * 后台菜单 → 基础 → 分类管理
 *
 * 【数据表】
 * mac_type - 分类主表
 *
 * 【分类类型 type_mid 说明】
 * ┌────────┬─────────────────────┐
 * │ type_mid │ 说明               │
 * ├────────┼─────────────────────┤
 * │ 1        │ 视频分类 (vod)     │
 * │ 2        │ 文章分类 (art)     │
 * │ 8        │ 演员分类 (actor)   │
 * │ 11       │ 网址分类 (website) │
 * │ 12       │ 漫画分类 (manga)   │
 * └────────┴─────────────────────┘
 *
 * 【分类层级】
 * - 支持两级分类结构 (一级分类 + 二级子分类)
 * - type_pid = 0 表示一级分类
 * - type_pid > 0 表示二级分类，指向父分类ID
 *
 * 【方法列表】
 * ┌──────────────┬──────────────────────────────────────┐
 * │ 方法名        │ 功能说明                              │
 * ├──────────────┼──────────────────────────────────────┤
 * │ index()      │ 分类列表页面 (树形展示)                │
 * │ info()       │ 分类添加/编辑页面                      │
 * │ del()        │ 删除分类                              │
 * │ field()      │ 更新单个字段 (状态切换)                │
 * │ batch()      │ 批量保存 (列表页直接编辑)              │
 * │ extend()     │ 获取分类扩展属性 (AJAX)                │
 * │ move()       │ 转移分类下的数据                       │
 * └──────────────┴──────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/type/index   → 分类列表
 * admin.php/type/info    → 添加/编辑分类
 * admin.php/type/del     → 删除分类
 *
 * 【相关文件】
 * - application/common/model/Type.php        : 分类模型
 * - application/admin/view_new/type/index.html : 列表模板
 * - application/admin/view_new/type/info.html  : 编辑模板
 *
 * ============================================================
 */

namespace app\admin\controller;
use think\Db;

class Type extends Base
{
    /**
     * 构造函数
     * 调用父类构造函数，设置页面标题
     */
    public function __construct()
    {
        parent::__construct();
        // 设置页面标题为"分类管理"
        $this->assign('title',lang('admin/type/title'));
    }

    /**
     * ============================================================
     * 分类列表页面
     * ============================================================
     *
     * 【访问路径】
     * GET admin.php/type/index
     *
     * 【功能说明】
     * 1. 获取所有分类数据 (树形结构)
     * 2. 统计每个分类下的内容数量 (视频/文章/演员/网址/漫画)
     * 3. 渲染分类列表页面
     *
     * 【页面结构】
     * ┌──────────────────────────────────────────────────────┐
     * │ 工具栏: [添加] [编辑] [删除] [状态] [转移]            │
     * ├──────────────────────────────────────────────────────┤
     * │ 分类列表 (可展开的树形结构)                           │
     * │ ├─ 一级分类1 (状态/类型/排序/名称/模板...)  [操作]    │
     * │ │  ├─ 二级分类1-1 ...                                │
     * │ │  └─ 二级分类1-2 ...                                │
     * │ └─ 一级分类2 ...                                     │
     * └──────────────────────────────────────────────────────┘
     *
     * 【模板位置】
     * application/admin/view_new/type/index.html
     *
     * @return string 渲染后的HTML页面
     */
    public function index()
    {
        // ============================================================
        // 【步骤1】获取分类列表 (树形结构)
        // ============================================================
        // 排序规则: 按 type_sort 升序排列 (数字小的在前)
        $order='type_sort asc';
        $where=[];
        // 调用 Type 模型的 listData 方法
        // 参数 'tree' 表示返回树形结构数据
        $res = model('Type')->listData($where,$order,'tree');

        // ============================================================
        // 【步骤2】统计各分类下的内容数量
        // ============================================================
        // 用于在分类名称旁边显示内容数量徽章
        $list_count =[];

        // --------------------------------------------------------
        // 统计视频数量 (mac_vod 表)
        // --------------------------------------------------------
        // GROUP BY type_id_1, type_id 分别统计一级和二级分类
        // cc = count 数量
        $tmp = model('Vod')->field('type_id_1,type_id,count(vod_id) as cc')->where($where)->group('type_id_1,type_id')->select();
        foreach($tmp as $k=>$v){
            // 累加到一级分类 (type_id_1)
            $list_count[$v['type_id_1']] += $v['cc'];
            // 赋值给二级分类 (type_id)
            $list_count[$v['type_id']] = $v['cc'];
        }

        // --------------------------------------------------------
        // 统计文章数量 (mac_art 表)
        // --------------------------------------------------------
        $tmp = model('Art')->field('type_id_1,type_id,count(art_id) as cc')->where($where)->group('type_id_1,type_id')->select();
        foreach($tmp as $k=>$v){
            $list_count[$v['type_id_1']] += $v['cc'];
            $list_count[$v['type_id']] = $v['cc'];
        }

        // --------------------------------------------------------
        // 统计演员数量 (mac_actor 表)
        // --------------------------------------------------------
        $tmp = model('Actor')->field('type_id_1,type_id,count(actor_id) as cc')->where($where)->group('type_id_1,type_id')->select();
        foreach($tmp as $k=>$v){
            $list_count[$v['type_id_1']] += $v['cc'];
            $list_count[$v['type_id']] = $v['cc'];
        }

        // --------------------------------------------------------
        // 统计网址数量 (mac_website 表)
        // --------------------------------------------------------
        $tmp = model('Website')->field('type_id_1,type_id,count(website_id) as cc')->where($where)->group('type_id_1,type_id')->select();
        foreach($tmp as $k=>$v){
            $list_count[$v['type_id_1']] += $v['cc'];
            $list_count[$v['type_id']] = $v['cc'];
        }

        // --------------------------------------------------------
        // 统计漫画数量 (mac_manga 表)
        // --------------------------------------------------------
        $tmp = model('Manga')->field('type_id_1,type_id,count(manga_id) as cc')->where($where)->group('type_id_1,type_id')->select();
        foreach($tmp as $k=>$v){
            $list_count[$v['type_id_1']] += $v['cc'];
            $list_count[$v['type_id']] = $v['cc'];
        }

        // ============================================================
        // 【步骤3】将统计数量合并到分类列表中
        // ============================================================
        // 遍历树形结构，为每个分类添加 cc 字段 (内容数量)
        foreach($res['list'] as $k=>$v){
            // 一级分类的数量
            $res['list'][$k]['cc'] = intval($list_count[$v['type_id']]);
            // 遍历子分类
            foreach($v['child'] as $k2=>$v2){
                // 二级分类的数量
                $res['list'][$k]['child'][$k2]['cc'] = intval($list_count[$v2['type_id']]);
            }
        }

        // ============================================================
        // 【步骤4】渲染模板
        // ============================================================
        $this->assign('list',$res['list']);   // 分类列表 (树形)
        $this->assign('total',$res['total']); // 分类总数
        return $this->fetch('admin@type/index');
    }

    /**
     * ============================================================
     * 分类添加/编辑页面
     * ============================================================
     *
     * 【访问路径】
     * GET  admin.php/type/info       → 显示添加页面
     * GET  admin.php/type/info?id=X  → 显示编辑页面 (id为分类ID)
     * GET  admin.php/type/info?pid=X → 显示添加子分类页面 (pid为父分类ID)
     * POST admin.php/type/info       → 保存分类数据
     *
     * 【页面字段说明】
     * ┌──────────────────┬───────────────────────────────────┐
     * │ 字段              │ 说明                               │
     * ├──────────────────┼───────────────────────────────────┤
     * │ type_mid         │ 分类类型 (1=视频/2=文章/8=演员等)  │
     * │ type_pid         │ 父分类ID (0=一级分类)              │
     * │ type_status      │ 状态 (0=禁用/1=启用)               │
     * │ type_sort        │ 排序值 (数字小的在前)              │
     * │ type_name        │ 分类名称                          │
     * │ type_en          │ 英文标识 (URL别名)                 │
     * │ type_tpl         │ 分类首页模板                       │
     * │ type_tpl_list    │ 筛选列表模板                       │
     * │ type_tpl_detail  │ 详情页模板                         │
     * │ type_tpl_play    │ 播放页模板 (仅视频分类)            │
     * │ type_tpl_down    │ 下载页模板 (仅视频分类)            │
     * │ type_title       │ SEO标题                           │
     * │ type_key         │ SEO关键词                         │
     * │ type_des         │ SEO描述                           │
     * │ type_logo        │ 分类Logo图片                       │
     * │ type_pic         │ 分类大图                          │
     * │ type_jumpurl     │ 跳转URL                           │
     * │ type_extend      │ 扩展属性 (JSON格式存储)            │
     * └──────────────────┴───────────────────────────────────┘
     *
     * 【扩展属性 type_extend】
     * 存储分类特有的筛选选项，JSON格式:
     * - class: 剧情分类 (如: 动作,喜剧,爱情)
     * - area: 地区 (如: 大陆,香港,台湾)
     * - lang: 语言 (如: 国语,粤语,英语)
     * - year: 年份 (如: 2023,2022,2021)
     * - star: 明星
     * - director: 导演
     * - state: 状态 (如: 正片,预告片)
     * - version: 版本 (如: 高清,蓝光)
     *
     * 【模板位置】
     * application/admin/view_new/type/info.html
     *
     * @return mixed 配置页面HTML 或 JSON响应
     */
    public function info()
    {
        // ============================================================
        // 【POST请求】保存分类数据
        // ============================================================
        if (Request()->isPost()) {
            // 获取所有POST参数
            $param = input('post.');

            // --------------------------------------------------------
            // Token验证 (防止CSRF攻击)
            // --------------------------------------------------------
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            // --------------------------------------------------------
            // 调用模型保存数据
            // --------------------------------------------------------
            // saveData() 方法会自动判断是新增还是更新
            // - 有 type_id → 更新
            // - 无 type_id → 新增
            $res = model('Type')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }

            // --------------------------------------------------------
            // 刷新分类缓存
            // --------------------------------------------------------
            // 分类数据修改后需要重建缓存，前台才能显示最新数据
            model('Type')->setCache();

            return $this->success($res['msg']);
        }

        // ============================================================
        // 【GET请求】显示分类编辑/添加页面
        // ============================================================

        // --------------------------------------------------------
        // 获取当前分类信息 (编辑时)
        // --------------------------------------------------------
        $id = input('id');      // 当前分类ID (编辑时传入)
        $pid = input('pid');    // 父分类ID (添加子分类时传入)

        $where=[];
        $where['type_id'] = ['eq',$id];
        // 获取当前分类详情
        $res = model('Type')->infoData($where);

        // --------------------------------------------------------
        // 获取父分类信息 (添加子分类时)
        // --------------------------------------------------------
        $where=[];
        $where['type_id'] = ['eq',$pid];
        // 获取父分类详情 (用于继承父分类的type_mid等属性)
        $resp = model('Type')->infoData($where);

        // 赋值到模板
        $this->assign('info',$res['info']);   // 当前分类信息
        $this->assign('infop',$resp['info']); // 父分类信息
        $this->assign('pid',$pid);            // 父分类ID

        // --------------------------------------------------------
        // 获取所有一级分类 (用于父分类下拉选择)
        // --------------------------------------------------------
        $where=[];
        $where['type_pid'] = ['eq','0']; // 只获取一级分类 (pid=0)
        $order='type_sort asc';
        $parent = model('Type')->listData($where,$order);
        $this->assign('parent',$parent['list']);

        return $this->fetch('admin@type/info');
    }

    /**
     * ============================================================
     * 删除分类
     * ============================================================
     *
     * 【访问路径】
     * POST admin.php/type/del
     *
     * 【请求参数】
     * - ids: 要删除的分类ID (支持数组，批量删除)
     *
     * 【删除限制】
     * 如果分类下还有内容数据 (视频/文章等)，则不允许删除
     * 需要先删除或转移分类下的内容
     *
     * 【执行流程】
     * 1. 验证参数
     * 2. 检查分类下是否有内容 (在模型中处理)
     * 3. 删除分类记录
     * 4. 刷新分类缓存
     *
     * @return \think\response\Json 操作结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['type_id'] = ['in',$ids];
            // 调用模型删除方法
            // delData() 会检查分类下是否有内容
            $res = model('Type')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 更新单个字段 (状态切换)
     * ============================================================
     *
     * 【访问路径】
     * POST admin.php/type/field
     *
     * 【请求参数】
     * - ids: 分类ID (支持数组)
     * - col: 字段名 (目前只支持 type_status)
     * - val: 字段值 (0 或 1)
     *
     * 【使用场景】
     * - 列表页的状态开关切换
     * - 批量启用/禁用分类
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];   // 分类ID
        $col = $param['col'];   // 字段名
        $val = $param['val'];   // 字段值

        // 验证参数
        // - ids 不为空
        // - col 只能是 type_status (安全限制)
        // - val 只能是 0 或 1
        if(!empty($ids) && in_array($col,['type_status']) && in_array($val,['0','1'])){
            $where=[];
            $where['type_id'] = ['in',$ids];

            // 调用模型更新字段
            $res = model('Type')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 批量保存 (列表页直接编辑)
     * ============================================================
     *
     * 【访问路径】
     * POST admin.php/type/batch
     *
     * 【功能说明】
     * 在分类列表页可以直接编辑多个分类的基本信息:
     * - 排序 (type_sort)
     * - 名称 (type_name)
     * - 英文标识 (type_en)
     * - 分类模板 (type_tpl)
     * - 列表模板 (type_tpl_list)
     * - 详情模板 (type_tpl_detail)
     *
     * 【请求参数】
     * - ids[]: 选中的分类ID数组
     * - type_name_{id}: 分类名称
     * - type_sort_{id}: 排序值
     * - type_en_{id}: 英文标识
     * - type_tpl_{id}: 分类模板
     * - type_tpl_list_{id}: 列表模板
     * - type_tpl_detail_{id}: 详情模板
     *
     * @return \think\response\Json 操作结果
     */
    public function batch()
    {
        $param = input();
        $ids = $param['ids'];

        // 遍历选中的分类ID
        foreach ($ids as $k=>$id) {
            // 组装每个分类的更新数据
            $data = [];
            $data['type_id'] = intval($id);
            $data['type_name'] = $param['type_name_'.$id];
            $data['type_sort'] = $param['type_sort_'.$id];
            $data['type_en'] = $param['type_en_'.$id];
            $data['type_tpl'] = $param['type_tpl_'.$id];
            $data['type_tpl_list'] = $param['type_tpl_list_'.$id];
            $data['type_tpl_detail'] = $param['type_tpl_detail_'.$id];

            // 名称不能为空，设置默认值
            if (empty($data['type_name'])) {
                $data['type_name'] = lang('unknown');
            }

            // 逐个保存分类数据
            $res = model('Type')->saveData($data);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
        }
        $this->success($res['msg']);
    }

    /**
     * ============================================================
     * 获取分类扩展属性 (AJAX接口)
     * ============================================================
     *
     * 【访问路径】
     * POST admin.php/type/extend
     *
     * 【请求参数】
     * - id: 分类ID
     *
     * 【功能说明】
     * 当用户选择分类时，通过AJAX获取该分类的扩展属性
     * 用于动态填充筛选条件 (地区/语言/年份/剧情等)
     *
     * 【扩展属性继承规则】
     * 1. 优先使用当前分类的扩展属性
     * 2. 如果为空，继承父分类的扩展属性
     * 3. 如果父分类也为空，使用系统默认配置
     *    (来自 config('maccms.app') 中的 vod_extend_* 或 art_extend_*)
     *
     * 【视频分类 (type_mid=1) 扩展属性】
     * - class: 剧情分类
     * - area: 地区
     * - lang: 语言
     * - year: 年份
     * - state: 状态
     * - version: 版本
     *
     * 【文章分类 (type_mid=2) 扩展属性】
     * - class: 文章分类
     *
     * @return \think\response\Json 扩展属性数据
     */
    public function extend()
    {
        $param = input();
        if(!empty($param['id'])){
            // 从缓存获取分类列表
            $type_list = model('Type')->getCache('type_list');
            // 获取当前分类信息
            $type_info = $type_list[$param['id']];

            if(!empty($type_info)){
                // 分类类型 (1=视频, 2=文章等)
                $type_mid = $type_info['type_mid'];
                // 父分类ID
                $type_pid = $type_info['type_pid'];
                // 父分类信息
                $type_pinfo = $type_list[$type_pid];
                // 当前分类的扩展属性
                $type_extend = $type_info['type_extend'];
                // 父分类的扩展属性
                $type_pextend = $type_pinfo['type_extend'];

                // 获取系统默认扩展配置
                $config = config('maccms.app');

                // --------------------------------------------------------
                // 文章分类 (type_mid=2) 扩展属性处理
                // --------------------------------------------------------
                if($type_mid==2) {
                    // class: 剧情分类
                    if(empty($type_extend['class']) && !empty($type_pextend['class'])){
                        // 继承父分类的class
                        $type_extend['class'] = $type_pextend['class'];
                    }
                    elseif(empty($type_extend['class']) && !empty($config['art_extend_class'])){
                        // 使用系统默认的文章扩展分类
                        $type_extend['class'] = $config['art_extend_class'];
                    }
                }
                // --------------------------------------------------------
                // 视频分类 (type_mid=1) 及其他分类扩展属性处理
                // --------------------------------------------------------
                else{
                    // class: 剧情分类
                    if(empty($type_extend['class']) && !empty($type_pextend['class'])){
                        $type_extend['class'] = $type_pextend['class'];
                    }
                    elseif(empty($type_extend['class']) && !empty($config['vod_extend_class'])){
                        $type_extend['class'] = $config['vod_extend_class'];
                    }

                    // state: 状态 (正片/预告片等)
                    if(empty($type_extend['state']) && !empty($type_pextend['state'])){
                        $type_extend['state'] = $type_pextend['state'];
                    }
                    elseif(empty($type_extend['state']) && !empty($config['vod_extend_state'])){
                        $type_extend['state'] = $config['vod_extend_state'];
                    }

                    // version: 版本 (高清/蓝光等)
                    if(empty($type_extend['version']) && !empty($type_pextend['version'])){
                        $type_extend['version'] = $type_pextend['version'];
                    }
                    elseif(empty($type_extend['version']) && !empty($config['vod_extend_version'])){
                        $type_extend['version'] = $config['vod_extend_version'];
                    }

                    // area: 地区
                    if(empty($type_extend['area']) && !empty($type_pextend['area'])){
                        $type_extend['area'] = $type_pextend['area'];
                    }
                    elseif(empty($type_extend['area']) && !empty($config['vod_extend_area'])){
                        $type_extend['area'] = $config['vod_extend_area'];
                    }

                    // lang: 语言
                    if(empty($type_extend['lang']) && !empty($type_pextend['lang'])){
                        $type_extend['lang'] = $type_pextend['lang'];
                    }
                    elseif(empty($type_extend['lang']) && !empty($config['vod_extend_lang'])){
                        $type_extend['lang'] = $config['vod_extend_lang'];
                    }

                    // year: 年份
                    if(empty($type_extend['year']) && !empty($type_pextend['year'])){
                        $type_extend['year'] = $type_pextend['year'];
                    }
                    elseif(empty($type_extend['year']) && !empty($config['vod_extend_year'])){
                        $type_extend['year'] = $config['vod_extend_year'];
                    }
                }

                // --------------------------------------------------------
                // 将扩展属性字符串转换为数组
                // --------------------------------------------------------
                // 输入格式: "动作,喜剧,爱情"
                // 输出格式: ['动作', '喜剧', '爱情']
                if(!empty($type_extend)){
                    foreach($type_extend as $key=>$value){
                        $options = '';
                        foreach(explode(',',$value) as $option){
                            $extend[$key][] = $option;
                        }
                    }
                }

                return $this->success('ok',null,$extend);
            }
            return $this->error(lang('get_info_err'));

        }
    }

    /**
     * ============================================================
     * 转移分类数据
     * ============================================================
     *
     * 【访问路径】
     * POST admin.php/type/move
     *
     * 【请求参数】
     * - ids: 源分类ID (要被转移走数据的分类)
     * - val: 目标分类ID (数据转移到的分类)
     *
     * 【功能说明】
     * 将某个分类下的所有内容数据 (视频/文章等) 转移到另一个分类
     * 转移后，源分类下将没有任何内容，可以安全删除
     *
     * 【使用场景】
     * - 合并两个分类
     * - 删除分类前先转移数据
     * - 重新组织分类结构
     *
     * 【注意事项】
     * - 只转移内容数据，不删除源分类本身
     * - 内容的 type_id 和 type_id_1 都会更新
     *
     * @return \think\response\Json 操作结果
     */
    public function move()
    {
        $param = input();
        $ids = $param['ids'];   // 源分类ID
        $val = $param['val'];   // 目标分类ID

        if(!empty($ids) && !empty($val)){
            $where=[];
            $where['type_id'] = ['in',$ids];
            // 调用模型转移数据
            $res = model('Type')->moveData($where,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}