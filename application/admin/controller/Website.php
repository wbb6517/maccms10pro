<?php
/**
 * 网址管理控制器 (Website Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台网址/网站导航管理控制器
 * 用于管理网站导航、友情链接等外部网址资源
 * 支持网址分类、等级、审核、锁定等管理功能
 *
 * 【菜单位置】
 * 后台管理 → 网址管理 (顶级菜单，索引12)
 *
 * 【数据表】
 * mac_website - 网址数据表
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数                                    │
 * │ data()          │ 网址列表页面                                │
 * │ batch()         │ 批量操作页面                                │
 * │ info()          │ 网址详情/编辑页面                            │
 * │ del()           │ 删除网址                                    │
 * │ field()         │ 更新单个字段                                │
 * │ updateToday()   │ 获取今日更新数据                            │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/website/data   → 网址列表
 * admin.php/website/batch  → 批量操作
 * admin.php/website/info   → 网址详情/编辑
 * admin.php/website/del    → 删除网址
 * admin.php/website/field  → 字段更新
 *
 * 【相关文件】
 * - application/common/model/Website.php : 网址模型
 * - application/admin/view_new/website/ : 视图文件目录
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Website extends Base
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
     * 网址列表页面
     * ============================================================
     *
     * 【功能说明】
     * 显示网址数据列表，支持多条件筛选和重复数据查询
     *
     * 【筛选条件】
     * - type   : 分类ID
     * - level  : 推荐等级 (1-9)
     * - status : 审核状态 (0=未审核, 1=已审核)
     * - lock   : 锁定状态 (0=未锁定, 1=已锁定)
     * - pic    : 图片状态 (1=无图, 2=远程图, 3=同步失败)
     * - wd     : 名称关键词
     * - repeat : 重复数据模式
     *
     * 【重复数据查询】
     * 当repeat=1时，创建临时表查找同名网址记录
     *
     * @return mixed 渲染列表页面
     */
    public function data()
    {
        $param = input();
        // 分页参数初始化
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        // ==================== 构建查询条件 ====================
        $where = [];
        // 分类筛选
        if (!empty($param['type'])) {
            $where['type_id|type_id_1'] = ['eq', $param['type']];
        }
        // 等级筛选
        if (!empty($param['level'])) {
            $where['website_level'] = ['eq', $param['level']];
        }
        // 审核状态筛选
        if (in_array($param['status'], ['0', '1'])) {
            $where['website_status'] = ['eq', $param['status']];
        }
        // 锁定状态筛选
        if (!empty($param['lock'])) {
            $where['website_lock'] = ['eq', $param['lock']];
        }
        // 图片状态筛选
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['website_pic'] = ['eq',''];  // 无图
            }
            elseif($param['pic'] == '2'){
                $where['website_pic'] = ['like','http%'];  // 远程图
            }
            elseif($param['pic'] == '3'){
                $where['website_pic'] = ['like','%#err%'];  // 同步失败
            }
        }
        // 名称关键词搜索
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['website_name'] = ['like','%'.$param['wd'].'%'];
        }

        // ==================== 查询数据 ====================
        if(!empty($param['repeat'])){
            // 重复数据查询模式：创建临时表存储重复记录
            if($param['page'] ==1){
                Db::execute('DROP TABLE IF EXISTS '.config('database.prefix').'tmpwebsite');
                Db::execute('CREATE TABLE `'.config('database.prefix').'tmpwebsite` (`id1` int unsigned DEFAULT NULL, `name1` varchar(1024) NOT NULL DEFAULT \'\') ENGINE=MyISAM');
                Db::execute('INSERT INTO `'.config('database.prefix').'tmpwebsite` (SELECT min(website_id)as id1,website_name as name1 FROM '.config('database.prefix').'website GROUP BY name1 HAVING COUNT(name1)>1)');
            }
            $order='website_name asc';
            $res = model('Website')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            // 普通查询模式
            $order='website_time desc';
            $res = model('Website')->listData($where,$order,$param['page'],$param['limit']);
        }

        // 标记是否需要生成静态页
        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['website_detail'] >0 && $v['website_time_make'] < $v['website_time']){
                $v['ismake'] = 0;
            }
        }

        // 分配模板变量
        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        // 获取分类树
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $this->assign('title',lang('admin/website/title'));
        return $this->fetch('admin@website/index');
    }

    /**
     * ============================================================
     * 批量操作页面
     * ============================================================
     *
     * 【功能说明】
     * 批量处理网址数据，支持批量删除、修改等级、状态、锁定、点击量
     * 采用分页方式处理，防止超时
     *
     * 【批量操作项】
     * - ck_del    : 批量删除
     * - ck_level  : 批量修改等级
     * - ck_status : 批量修改状态
     * - ck_lock   : 批量修改锁定
     * - ck_hits   : 批量随机点击量
     *
     * @return mixed 渲染批量操作页面或执行批量操作
     */
    public function batch()
    {
        $param = input();
        if (!empty($param)) {

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            // 验证是否选择了操作项
            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits']) ){
                return $this->error(lang('param_err'));
            }

            // 构建筛选条件
            $where = [];
            if(!empty($param['type'])){
                $where['type_id'] = ['eq',$param['type']];
            }
            if(!empty($param['level'])){
                $where['website_level'] = ['eq',$param['level']];
            }
            if(in_array($param['status'],['0','1'])){
                $where['website_status'] = ['eq',$param['status']];
            }
            if(!empty($param['lock'])){
                $where['website_lock'] = ['eq',$param['lock']];
            }
            if(!empty($param['pic'])){
                if($param['pic'] == '1'){
                    $where['website_pic'] = ['eq',''];
                }
                elseif($param['pic'] == '2'){
                    $where['website_pic'] = ['like','http%'];
                }
                elseif($param['pic'] == '3'){
                    $where['website_pic'] = ['like','%#err%'];
                }
            }
            if(!empty($param['wd'])){
                $param['wd'] = htmlspecialchars(urldecode($param['wd']));
                $where['website_name'] = ['like','%'.$param['wd'].'%'];
            }

            // 批量删除
            if($param['ck_del'] == 1){
                $res = model('Website')->delData($where);
                mac_echo(lang('multi_del_ok'));
                mac_jump( url('website/batch') ,3);
                exit;
            }

            // 分页参数初始化
            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;
            }
            if(empty($param['total'])) {
                $param['total'] = model('Website')->countData($where);
                $param['page_count'] = ceil($param['total'] / $param['limit']);
            }

            // 检查是否处理完成
            if($param['page'] > $param['page_count']) {
                mac_echo(lang('multi_opt_ok'));
                mac_jump( url('website/batch') ,3);
                exit;
            }
            mac_echo( "<font color=red>".lang('admin/batch_tip',[$param['total'],$param['limit'],$param['page_count'],$param['page']])."</font>");

            // 获取当前页数据
            $order='website_id desc';
            $res = model('Website')->listData($where,$order,$param['page'],$param['limit']);

            // 逐条处理
            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['website_id'] = $v['website_id'];

                $update = [];
                $des = $v['website_id'].','.$v['website_name'];

                // 修改等级
                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['website_level'] = $param['val_level'];
                    $des .= '&nbsp;'.lang('level').'：'.$param['val_level'].'；';
                }
                // 修改状态
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['website_status'] = $param['val_status'];
                    $des .= '&nbsp;'.lang('status').'：'.($param['val_status'] ==1 ? '['.lang('reviewed').']':'['.lang('reviewed_not').']') .'；';
                }
                // 修改锁定
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['website_lock'] = $param['val_lock'];
                    $des .= '&nbsp;'.lang('lock').'：'.($param['val_lock']==1 ? '['.lang('lock').']':'['.lang('ublock').']').'；';
                }
                // 随机点击量
                if(!empty($param['ck_hits']) && !empty($param['val_hits_min']) && !empty($param['val_hits_max']) ){
                    $update['website_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;'.lang('hits').'：'.$update['website_hits'].'；';
                }
                mac_echo($des);
                $res2 = model('Website')->where($where2)->update($update);

            }
            // 跳转下一页继续处理
            $param['page']++;
            $url = url('website/batch') .'?'. http_build_query($param);
            mac_jump( $url ,3);
            exit;
        }

        // GET请求：显示批量操作页面
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/website/title'));
        return $this->fetch('admin@website/batch');
    }

    /**
     * ============================================================
     * 网址详情/编辑页面
     * ============================================================
     *
     * 【功能说明】
     * GET: 显示网址详情编辑页面
     * POST: 保存网址数据
     *
     * @return mixed 渲染详情页面或JSON响应
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            // 替换协议前缀为mac:
            $param['website_content'] = str_replace( $GLOBALS['config']['upload']['protocol'].':','mac:',$param['website_content']);
            $res = model('Website')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // 获取网址详情
        $id = input('id');
        $where=[];
        $where['website_id'] = ['eq',$id];
        $res = model('Website')->infoData($where);

        $info = $res['info'];
        $this->assign('info',$info);
        $this->assign('website_page_list',$info['website_page_list']);

        // 获取分类树
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/website/title'));
        return $this->fetch('admin@website/info');
    }

    /**
     * ============================================================
     * 删除网址
     * ============================================================
     *
     * 【功能说明】
     * 删除指定网址，支持批量删除和重复数据删除
     *
     * 【删除模式】
     * - ids    : 按ID删除，多个用逗号分隔
     * - repeat : 删除重复数据 (retain=min保留最小ID, retain=max保留最大ID)
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            // 按ID删除
            $where=[];
            $where['website_id'] = ['in',$ids];
            $res = model('Website')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        elseif(!empty($param['repeat'])){
            // 删除重复数据
            $st = ' not in ';
            if($param['retain']=='max'){
                $st=' in ';
            }
            // 删除临时表中记录的重复项（保留最小或最大ID）
            $sql = 'delete from '.config('database.prefix').'website where website_name in(select name1 from '.config('database.prefix').'tmpwebsite) and website_id '.$st.'(select id1 from '.config('database.prefix').'tmpwebsite)';
            $res = model('Website')->execute($sql);
            if($res===false){
                return $this->success(lang('del_err'));
            }
            return $this->success(lang('del_ok'));
        }
        return $this->error(lang('param_err'));
    }

    /**
     * ============================================================
     * 更新单个字段
     * ============================================================
     *
     * 【功能说明】
     * 更新指定网址的单个字段值，支持随机值
     *
     * 【支持的字段】
     * - website_status : 审核状态
     * - website_lock   : 锁定状态
     * - website_level  : 推荐等级
     * - website_hits   : 点击量
     * - type_id        : 分类ID
     *
     * 【随机值模式】
     * 当提供start和end参数时，生成范围内的随机值
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

        if(!empty($ids) && in_array($col,['website_status','website_lock','website_level','website_hits','type_id'])){
            $where=[];
            $where['website_id'] = ['in',$ids];
            $update = [];

            if(empty($start)) {
                // 固定值模式
                $update[$col] = $val;
                // 修改分类时同步更新一级分类ID
                if($col == 'type_id'){
                    $type_list = model('Type')->getCache();
                    $id1 = intval($type_list[$val]['type_pid']);
                    $update['type_id_1'] = $id1;
                }
                $res = model('Website')->fieldData($where, $update);
            }
            else{
                // 随机值模式
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['website_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Website')->fieldData($where, $update);
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
     * 获取今日更新数据
     * ============================================================
     *
     * 【功能说明】
     * 获取今日更新的网址ID列表或分类ID列表
     * 用于前台显示今日更新标记
     *
     * 【请求参数】
     * - flag : 返回类型 (website=网址ID, type=分类ID)
     *
     * @return \think\response\Json JSON响应
     */
    public function updateToday()
    {
        $param = input();
        $flag = $param['flag'];
        $res = model('Website')->updateToday($flag);
        return json($res);
    }

}
