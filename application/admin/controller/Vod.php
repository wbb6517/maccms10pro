<?php
/**
 * 视频数据管理控制器 (Vod Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台 "视频 → 视频数据" 功能的控制器
 * MacCMS 最核心的内容管理模块，管理视频/影视数据
 *
 * 【访问路径】
 * admin.php/vod/data      → 视频列表页面
 * admin.php/vod/info      → 添加/编辑视频
 * admin.php/vod/del       → 删除视频
 * admin.php/vod/field     → 批量修改字段
 * admin.php/vod/batch     → 批量操作页面
 * admin.php/vod/iplot     → 剧情简介编辑
 * admin.php/vod/updateToday → 更新今日数据
 *
 * 【方法列表】
 * ┌────────────────────┬──────────────────────────────────────┐
 * │ 方法名              │ 功能说明                              │
 * ├────────────────────┼──────────────────────────────────────┤
 * │ data()             │ 视频列表页面 (支持多条件筛选)          │
 * │ info()             │ 视频添加/编辑页面                      │
 * │ del()              │ 删除视频 (支持批量/重复数据删除)        │
 * │ field()            │ 批量修改字段 (状态/等级/点击量等)       │
 * │ batch()            │ 批量操作页面 (删除/修改/清空播放组)     │
 * │ iplot()            │ 剧情简介编辑页面                       │
 * │ updateToday()      │ 更新今日数据统计                       │
 * └────────────────────┴──────────────────────────────────────┘
 *
 * 【数据表说明】
 * 数据表: mac_vod (通过 Vod 模型操作)
 *
 * 【核心字段说明】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 说明                                     │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ vod_id           │ 视频ID (主键)                            │
 * │ vod_name         │ 视频名称                                 │
 * │ vod_sub          │ 副标题                                   │
 * │ type_id          │ 分类ID                                   │
 * │ type_id_1        │ 一级分类ID                               │
 * │ vod_status       │ 状态: 0=未审核, 1=已审核                  │
 * │ vod_level        │ 推荐等级: 0-9                            │
 * │ vod_lock         │ 锁定: 0=否, 1=是 (锁定后采集不更新)       │
 * │ vod_copyright    │ 版权: 0=关闭, 1=开启                     │
 * │ vod_isend        │ 完结: 0=否, 1=是                         │
 * │ vod_pic          │ 封面图URL                                │
 * │ vod_actor        │ 演员                                     │
 * │ vod_director     │ 导演                                     │
 * │ vod_area         │ 地区                                     │
 * │ vod_lang         │ 语言                                     │
 * │ vod_year         │ 年份                                     │
 * │ vod_play_from    │ 播放来源 (多组用$$$分隔)                  │
 * │ vod_play_url     │ 播放地址 (多组用$$$分隔)                  │
 * │ vod_down_from    │ 下载来源 (多组用$$$分隔)                  │
 * │ vod_down_url     │ 下载地址 (多组用$$$分隔)                  │
 * │ vod_time         │ 更新时间戳                               │
 * │ vod_time_add     │ 添加时间戳                               │
 * │ vod_hits         │ 总点击量                                 │
 * │ vod_plot         │ 是否有剧情: 0=无, 1=有                   │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * 【播放/下载地址格式】
 * 单组: "播放器编码$集名$地址#集名$地址#..."
 * 多组: "编码1$集1$地址#集2$地址$$$编码2$集1$地址#集2$地址"
 *
 * @package     app\admin\controller
 * @author      MacCMS
 * @version     1.0
 */
namespace app\admin\controller;
use think\Cache;
use think\Db;

class Vod extends Base
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 视频列表页面
     *
     * 【功能说明】
     * 显示视频数据列表，支持多条件筛选和排序
     * 支持重复数据筛选模式
     *
     * 【筛选条件】
     * - type      : 分类ID
     * - level     : 推荐等级
     * - status    : 审核状态 (0/1)
     * - copyright : 版权状态 (0/1)
     * - isend     : 完结状态 (0/1)
     * - lock      : 锁定状态
     * - state     : 资源状态
     * - area      : 地区
     * - lang      : 语言
     * - plot      : 剧情状态 (0/1)
     * - role      : 是否有角色数据 (0/1)
     * - url       : 播放地址状态 (1=无地址)
     * - points    : 是否付费
     * - pic       : 图片状态 (1=无图/2=外链图/3=错误图)
     * - weekday   : 更新日期
     * - wd        : 关键词搜索 (名称/演员/副标题)
     * - player    : 播放器筛选
     * - downer    : 下载器筛选
     * - server    : 服务器筛选
     * - repeat    : 重复数据模式
     *
     * 【排序选项】
     * - vod_time       : 更新时间 (默认)
     * - vod_id         : ID
     * - vod_hits       : 总点击量
     * - vod_hits_month : 月点击量
     * - vod_hits_week  : 周点击量
     * - vod_hits_day   : 日点击量
     *
     * @return mixed 渲染后的列表页面
     */
    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where = [];
        if(!empty($param['type'])){
            $where['type_id|type_id_1'] = ['eq',$param['type']];
        }
        if(!empty($param['level'])){
            $where['vod_level'] = ['eq',$param['level']];
        }
        // ========== 审核状态筛选 ==========
        // status=0: 未审核, status=1: 已审核
        if(in_array($param['status'],['0','1'])){
            $where['vod_status'] = ['eq',$param['status']];
        }
        // ========== 版权状态筛选 ==========
        // copyright=0: 无版权, copyright=1: 有版权
        if(in_array($param['copyright'],['0','1'])){
            $where['vod_copyright'] = ['eq',$param['copyright']];
        }
        // ========== 完结状态筛选 ==========
        // isend=0: 连载中, isend=1: 已完结
        if(in_array($param['isend'],['0','1'])){
            $where['vod_isend'] = ['eq',$param['isend']];
        }
        // ========== 已锁定视频筛选 (菜单: 视频-已锁定视频) ==========
        // 访问方式: vod/data?lock=1
        // 锁定状态: vod_lock=1 表示视频被锁定，禁止采集更新覆盖
        // 用途: 保护重要视频数据不被采集程序误覆盖
        if(!empty($param['lock'])){
            $where['vod_lock'] = ['eq',$param['lock']];
        }
        // ========== 资源状态筛选 ==========
        // 自定义状态字段，如: 正片、预告、花絮等
        if(!empty($param['state'])){
            $where['vod_state'] = ['eq',$param['state']];
        }
        // ========== 地区筛选 ==========
        if(!empty($param['area'])){
            $where['vod_area'] = ['eq',$param['area']];
        }
        // ========== 语言筛选 ==========
        if(!empty($param['lang'])){
            $where['vod_lang'] = ['eq',$param['lang']];
        }
        // ========== 剧情状态筛选 ==========
        // plot=0: 无分集剧情, plot=1: 有分集剧情
        if(in_array($param['plot'],['0','1'])){
            $where['vod_plot'] = ['eq',$param['plot']];
        }

        // 处理角色筛选 - 通过查询角色表判断是否有角色数据
        if(in_array($param['role'],['0','1'])){
            $roleVodIds = Db::name('role')->where('role_rid', '>', 0)->group('role_rid')->column('role_rid');
            if($param['role'] == '1'){
                // 有角色数据
                if(!empty($roleVodIds)){
                    $where['vod_id'] = ['in', $roleVodIds];
                } else {
                    // 如果没有任何角色数据，设置一个不可能匹配的条件
                    $where['vod_id'] = ['eq', 0];
                }
            } else {
                // 无角色数据
                if(!empty($roleVodIds)){
                    $where['vod_id'] = ['not in', $roleVodIds];
                }
            }
        }

        // ========== 无地址视频筛选 (菜单: 视频-无地址视频) ==========
        // 访问方式: vod/data?url=1
        // 筛选条件: vod_play_url 为空字符串
        // 用途: 查找没有播放地址的视频，便于批量补充地址或删除无效数据
        if(!empty($param['url'])){
            if($param['url']==1){
                // 播放地址为空的视频 (无地址视频)
                $where['vod_play_url'] = '';
            }
        }

        // ========== 需积分视频筛选 (菜单: 视频-需积分视频) ==========
        // 访问方式: vod/data?points=1
        // 筛选条件: vod_points_play > 0 或 vod_points_down > 0
        // 用途: 查看设置了播放积分或下载积分的视频
        // 积分扣除: 用户播放/下载时扣除对应积分，积分不足则无法观看/下载
        if(!empty($param['points'])){
            $where['vod_points_play|vod_points_down'] = ['gt', 0];
        }

        // ========== 图片状态筛选 ==========
        // pic=1: 无图片 (封面图为空)
        // pic=2: 外链图片 (以http开头)
        // pic=3: 错误图片 (包含#err标记)
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['vod_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['vod_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['vod_pic'] = ['like','%#err%'];
            }
        }

        // ========== 更新日期筛选 ==========
        // 按周几更新的连载剧筛选
        if(!empty($param['weekday'])){
            $where['vod_weekday'] = ['like','%'.$param['weekday'].'%'];
        }

        // ========== 关键词搜索 ==========
        // 搜索范围: 视频名称、演员、副标题
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $param['wd'] = mac_filter_xss($param['wd']);
            $where['vod_name|vod_actor|vod_sub'] = ['like','%'.$param['wd'].'%'];
        }

        // ========== 播放器来源筛选 ==========
        // player=no: 无播放来源
        // player=xxx: 指定播放器编码
        if(!empty($param['player'])){
            if($param['player']=='no'){
                $where['vod_play_from'] = [['eq', ''], ['eq', 'no'], 'or'];
            }
            else {
                $where['vod_play_from'] = ['like', '%' . $param['player'] . '%'];
            }
        }

        // ========== 下载器来源筛选 ==========
        // downer=no: 无下载来源
        // downer=xxx: 指定下载器编码
        if(!empty($param['downer'])){
            if($param['downer']=='no'){
                $where['vod_down_from'] = [['eq', ''], ['eq', 'no'], 'or'];
            }
            else {
                $where['vod_down_from'] = ['like', '%' . $param['downer'] . '%'];
            }
        }
        if(!empty($param['server'])){
            $where['vod_play_server|vod_down_server'] = ['like','%'.$param['server'].'%'];
        }
        $order='vod_time desc';
        if(in_array($param['order'],['vod_id','vod_hits','vod_hits_month','vod_hits_week','vod_hits_day'])){
            $order = $param['order'] .' desc';
        }

        if(!empty($param['repeat'])){
            if(!empty($param['cache'])){
                model('Vod')->createRepeatCache();
                return $this->success(lang('update_ok'));
            }

            if($param['page'] ==1){
                //使用缓存查看是否创建过缓存表
                $cacheResult = Cache::get('vod_repeat_table_created_time',0);
                //缓存时间超过7天和没有创建过缓存都会重建缓存
                if( $cacheResult == 0 || time() - $cacheResult > 604800){
                    model('Vod')->createRepeatCache();
                }
            }
            $order='vod_name asc';
            $res = model('Vod')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            $res = model('Vod')->listData($where,$order,$param['page'],$param['limit']);
        }


        // 批量查询哪些视频有角色数据
        $vodIds = array_column($res['list'], 'vod_id');
        $vodIdsWithRole = [];
        if (!empty($vodIds)) {
            $roleData = Db::name('role')
                ->where('role_rid', 'in', $vodIds)
                ->where('role_rid', '>', 0)
                ->group('role_rid')
                ->column('role_rid');
            $vodIdsWithRole = array_flip($roleData); // 转为键值对，方便快速查找
        }

        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['vod_detail'] >0 && $v['vod_time_make'] < $v['vod_time']){
                $v['ismake'] = 0;
            }
            // 标记是否有角色数据
            $v['vod_role'] = isset($vodIdsWithRole[$v['vod_id']]) ? 1 : 0;
        }

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $queryString = '?' . http_build_query($param);
        $this->assign('query_string',$queryString);
        //分类
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        //播放器
        $this->assignBaseListByConfig();
        $this->assign('title',lang('admin/vod/title'));
        return $this->fetch('admin@vod/index');
    }

    /**
     * 批量操作页面
     *
     * 【功能说明】
     * 对符合筛选条件的视频进行批量操作
     * 支持分页批量处理，避免超时
     *
     * 【操作类型】
     * - ck_del=1     : 批量删除
     * - ck_del=2     : 删除指定播放组
     * - ck_del=3     : 删除指定下载组
     * - ck_level     : 批量修改等级
     * - ck_status    : 批量修改状态
     * - ck_copyright : 批量修改版权
     * - ck_lock      : 批量修改锁定
     * - ck_hits      : 批量修改点击量 (随机范围)
     * - ck_points    : 批量修改积分
     *
     * 【筛选条件】
     * 同 data() 方法的筛选条件
     *
     * 【处理机制】
     * - 每次处理 limit 条数据 (默认100)
     * - 自动分页循环处理
     * - 显示实时处理进度
     *
     * @return mixed 渲染后的批量操作页面或处理结果
     */
    public function batch()
    {
        $param = input();
        // ========== POST请求: 执行批量操作 ==========
        if (!empty($param)) {

            // 输出样式，用于实时显示处理进度
            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            // ========== 参数验证: 必须选择至少一个操作类型 ==========
            // ck_del     : 删除操作 (1=删除数据, 2=清空播放组, 3=清空下载组)
            // ck_level   : 修改等级
            // ck_status  : 修改状态
            // ck_lock    : 修改锁定
            // ck_hits    : 修改点击量
            // ck_points  : 修改积分
            // ck_copyright: 修改版权
            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits'])
                && empty($param['ck_points']) && empty($param['ck_copyright'])
            ){
                return $this->error(lang('param_err'));
            }

            // 清空播放组必须选择播放器
            if($param['ck_del']==2 && empty($param['player'])){
                return $this->error(lang('admin/vod/del_play_must_select_play'));
            }
            // 清空下载组必须选择下载器
            if($param['ck_del']==3 && empty($param['downer'])){
                return $this->error(lang('admin/vod/del_down_must_select_down'));
            }

            // ========== 构建筛选条件 (与列表页相同) ==========
            $where = [];
            // 分类筛选
            if(!empty($param['type'])){
                $where['type_id'] = ['eq',$param['type']];
            }
            // 等级筛选
            if(!empty($param['level'])){
                $where['vod_level'] = ['eq',$param['level']];
            }
            // 状态筛选 (0=未审核, 1=已审核)
            if(in_array($param['status'],['0','1'])){
                $where['vod_status'] = ['eq',$param['status']];
            }
            // 版权筛选 (0=关闭, 1=开启)
            if(in_array($param['copyright'],['0','1'])){
                $where['vod_copyright'] = ['eq',$param['copyright']];
            }
            // 完结筛选 (0=连载中, 1=已完结)
            if(in_array($param['isend'],['0','1'])){
                $where['vod_isend'] = ['eq',$param['isend']];
            }
            // 锁定筛选
            if(!empty($param['lock'])){
                $where['vod_lock'] = ['eq',$param['lock']];
            }
            // 资源状态筛选
            if(!empty($param['state'])){
                $where['vod_state'] = ['eq',$param['state']];
            }
            // 地区筛选
            if(!empty($param['area'])){
                $where['vod_area'] = ['eq',$param['area']];
            }
            // 语言筛选
            if(!empty($param['lang'])){
                $where['vod_lang'] = ['eq',$param['lang']];
            }
            // 剧情筛选 (0=无剧情, 1=有剧情)
            if(in_array($param['plot'],['0','1'])){
                $where['vod_plot'] = ['eq',$param['plot']];
            }

            // 处理角色筛选 - 通过查询角色表判断是否有角色数据
            if(in_array($param['role'],['0','1'])){
                $roleVodIds = Db::name('role')->where('role_rid', '>', 0)->group('role_rid')->column('role_rid');
                if($param['role'] == '1'){
                    // 有角色数据
                    if(!empty($roleVodIds)){
                        $where['vod_id'] = ['in', $roleVodIds];
                    } else {
                        // 如果没有任何角色数据，设置一个不可能匹配的条件
                        $where['vod_id'] = ['eq', 0];
                    }
                } else {
                    // 无角色数据
                    if(!empty($roleVodIds)){
                        $where['vod_id'] = ['not in', $roleVodIds];
                    }
                }
            }
            // 无地址视频筛选
            if(!empty($param['url'])){
                if($param['url']==1){
                    $where['vod_play_url'] = '';
                }
            }
            // 图片状态筛选 (1=无图, 2=外链图, 3=错误图)
            if(!empty($param['pic'])){
                if($param['pic'] == '1'){
                    $where['vod_pic'] = ['eq',''];
                }
                elseif($param['pic'] == '2'){
                    $where['vod_pic'] = ['like','http%'];
                }
                elseif($param['pic'] == '3'){
                    $where['vod_pic'] = ['like','%#err%'];
                }
            }
            // 关键词搜索
            if(!empty($param['wd'])){
                $param['wd'] = htmlspecialchars(urldecode($param['wd']));
                $where['vod_name'] = ['like','%'.$param['wd'].'%'];
            }
            // 更新日期筛选
            if(!empty($param['weekday'])){
                $where['vod_weekday'] = ['like','%'.$param['weekday'].'%'];
            }
            // 播放器筛选
            if(!empty($param['player'])){
                if($param['player']=='no'){
                    $where['vod_play_from'] = [['eq', ''], ['eq', 'no'], 'or'];
                }
                else {
                    $where['vod_play_from'] = ['like', '%' . $param['player'] . '%'];
                }
            }
            // 下载器筛选
            if(!empty($param['downer'])){
                if($param['downer']=='no'){
                    $where['vod_down_from'] = [['eq', ''], ['eq', 'no'], 'or'];
                }
                else {
                    $where['vod_down_from'] = ['like', '%' . $param['downer'] . '%'];
                }
            }

            // ========== 操作类型1: 批量删除数据 ==========
            // 直接调用模型删除方法，删除所有符合条件的视频
            if($param['ck_del'] == 1){
                $res = model('Vod')->delData($where);
                mac_echo(lang('multi_del_ok'));
                mac_jump( url('vod/batch') ,3);
                exit;
            }


            // ========== 分页批量处理初始化 ==========
            // 批量修改字段和清空播放/下载组采用分页处理，避免超时
            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;  // 每次处理100条
            }
            // 首次执行时统计总数和计算总页数
            if(empty($param['total'])) {
                $param['total'] = model('Vod')->countData($where);
                $param['page_count'] = ceil($param['total'] / $param['limit']);
            }

            // 所有页处理完成，跳转回批量操作页面
            if($param['page'] > $param['page_count']) {
                mac_echo(lang('multi_opt_ok'));
                mac_jump( url('vod/batch') ,3);
                exit;
            }
            // 输出当前处理进度: 总数/每页/总页数/当前页
            mac_echo( "<font color=red>".lang('admin/batch_tip',[$param['total'],$param['limit'],$param['page_count'],$param['page']])."</font>");

            // ========== 获取当前页数据 ==========
            // 使用倒序分页，从最后一页开始处理
            // 这样可以避免删除/修改数据后影响分页偏移
            $page = $param['page_count'] - $param['page'] + 1;
            $order='vod_id desc';
            $res = model('Vod')->listData($where,$order,$page,$param['limit']);

            // ========== 遍历处理每条视频 ==========
            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['vod_id'] = $v['vod_id'];

                $update = [];  // 待更新的字段
                $des = $v['vod_id'].','.$v['vod_name'];  // 处理日志描述

                // ----- 修改等级 -----
                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['vod_level'] = $param['val_level'];
                    $des .= '&nbsp;'.lang('level').'：'.$param['val_level'].'；';
                }
                // ----- 修改状态 (审核) -----
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['vod_status'] = $param['val_status'];
                    $des .= '&nbsp;'.lang('status').'：'.($param['val_status'] ==1 ? '['.lang('reviewed').']':'['.lang('reviewed_not').']') .'；';
                }
                // ----- 修改版权 -----
                if(!empty($param['ck_copyright']) && isset($param['val_copyright'])){
                    $update['vod_copyright'] = $param['val_copyright'];
                    $des .= '&nbsp;'.lang('copyright').'：'.($param['val_copyright'] ==1 ? '['.lang('open').']':'['.lang('close').'') .'；';
                }
                // ----- 修改锁定 -----
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['vod_lock'] = $param['val_lock'];
                    $des .= '&nbsp;'.lang('lock').'：'.($param['val_lock']==1 ? '['.lang('lock').']':'['.lang('unlock').']').'；';
                }
                // ----- 修改点击量 (随机范围) -----
                if(!empty($param['ck_hits']) && $param['val_hits_min']!='' && $param['val_hits_max']!='' ){
                    $update['vod_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;'.lang('hits').'：'.$update['vod_hits'].'；';
                }
                // ----- 修改播放积分 -----
                if(!empty($param['ck_points']) && $param['val_points_play']!=''  ){
                    $update['vod_points_play'] = $param['val_points_play'];
                    $des .= '&nbsp;'.lang('points_play').'：'.$param['val_points_play'].'；';
                }
                // ----- 修改下载积分 -----
                if(!empty($param['ck_points']) && $param['val_points_down']!='' ){
                    $update['vod_points_down'] = $param['val_points_down'];
                    $des .= '&nbsp;'.lang('points_down').'：'.$param['val_points_down'].'；';
                }

                // ========== 操作类型2/3: 清空播放组或下载组 ==========
                // ck_del=2: 清空指定播放器的播放地址
                // ck_del=3: 清空指定下载器的下载地址
                if($param['ck_del'] == 2 || $param['ck_del'] ==3){
                    // 根据操作类型确定字段前缀
                    if($param['ck_del']==2) {
                        $pre = 'vod_play';   // 播放相关字段前缀
                        $par = 'player';     // 参数名
                        $des .= '&nbsp;'.lang('play_group').'：';
                    }
                    elseif($param['ck_del']==3){
                        $pre = 'vod_down';   // 下载相关字段前缀
                        $par='downer';       // 参数名
                        $des .= '&nbsp;'.lang('down_group').'：';
                    }

                    // 情况1: 视频只有这一个播放/下载组，直接清空
                    if($param[$par] == $v[$pre.'_from']){
                        $update[$pre.'_from'] = '';    // 来源
                        $update[$pre.'_server'] = '';  // 服务器
                        $update[$pre.'_note'] = '';    // 备注
                        $update[$pre.'_url'] = '';     // 地址
                        $des .= lang('del_empty').'；';
                    }
                    // 情况2: 视频有多个播放/下载组，删除指定的一组
                    else{
                        // 将 $$$ 分隔的字符串拆分为数组
                        $vod_from_arr = explode('$$$',$v[$pre.'_from']);
                        $vod_server_arr = explode('$$$',$v[$pre.'_server']);
                        $vod_note_arr = explode('$$$',$v[$pre.'_note']);
                        $vod_url_arr = explode('$$$',$v[$pre.'_url']);

                        // 查找要删除的播放器/下载器在数组中的位置
                        $key = array_search($param[$par],$vod_from_arr);
                        if($key!==false){
                            // 删除对应索引的元素
                            unset($vod_from_arr[$key]);
                            unset($vod_server_arr[$key]);
                            unset($vod_note_arr[$key]);
                            unset($vod_url_arr[$key]);

                            // 重新组合为 $$$ 分隔的字符串
                            $update[$pre.'_from'] = join('$$$',$vod_from_arr);
                            $update[$pre.'_server'] = join('$$$',$vod_server_arr);
                            $update[$pre.'_note'] = join('$$$',$vod_note_arr);
                            $update[$pre.'_url'] = join('$$$',$vod_url_arr);
                            $des .= lang('del'). '；';
                        }
                        else{
                            // 该视频不包含指定的播放器/下载器，跳过
                            $des .= lang('jump_over').'；';
                        }
                    }
                }

                // 输出处理日志并执行数据库更新
                mac_echo($des);
                $res2 = model('Vod')->where($where2)->update($update);

            }
            // 处理下一页
            $param['page']++;
            $url = url('vod/batch') .'?'. http_build_query($param);
            mac_jump( $url ,3);  // 3秒后自动跳转
            exit;
        }

        // ========== GET请求: 显示批量操作表单 ==========
        //分类
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        //播放器
        $this->assignBaseListByConfig();
        $this->assign('title',lang('admin/vod/title'));
        return $this->fetch('admin@vod/batch');
    }

    /**
     * 视频添加/编辑页面
     *
     * 【功能说明】
     * GET请求: 显示视频编辑表单
     * POST请求: 保存视频数据
     *
     * 【请求参数】
     * GET:
     * - id : 视频ID (编辑时传入，添加时为空)
     *
     * POST:
     * - 视频所有字段数据
     * - 播放组数据 (vod_play_list)
     * - 下载组数据 (vod_down_list)
     *
     * 【模板变量】
     * - info       : 视频信息
     * - type_tree  : 分类树
     * - area_list  : 地区列表
     * - lang_list  : 语言列表
     * - group_list : 用户组列表
     * - player_list: 播放器列表
     * - downer_list: 下载器列表
     * - server_list: 服务器组列表
     * - vod_play_list : 播放组数据
     * - vod_down_list : 下载组数据
     * - vod_plot_list : 剧情数据
     *
     * @return mixed 渲染后的编辑页面或操作结果
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Vod')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['vod_id'] = $id;
        $res = model('Vod')->infoData($where);


        $info = $res['info'];
        $this->assign('info',$info);

        //分类
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        //地区、语言
        $config = config('maccms.app');
        $area_list = explode(',',$config['vod_area']);
        $lang_list = explode(',',$config['vod_lang']);
        $this->assign('area_list',$area_list);
        $this->assign('lang_list',$lang_list);

        //用户组
        $group_list = model('Group')->getCache('group_list');
        $this->assign('group_list',$group_list);

        //播放器
        $this->assignBaseListByConfig();

        //播放组、下载租
        $this->assign('vod_play_list',(array)$info['vod_play_list']);
        $this->assign('vod_down_list',(array)$info['vod_down_list']);
        $this->assign('vod_plot_list',(array)$info['vod_plot_list']);


        $this->assign('title',lang('admin/vod/title'));
        return $this->fetch('admin@vod/info');
    }

    /**
     * 剧情简介编辑页面
     *
     * 【功能说明】
     * GET请求: 显示剧情编辑表单
     * POST请求: 保存剧情数据
     *
     * 【请求参数】
     * - id : 视频ID
     *
     * 【剧情数据格式】
     * vod_plot_detail 字段存储 JSON 格式的分集剧情
     *
     * @return mixed 渲染后的剧情编辑页面或操作结果
     */
    public function iplot()
    {
        // ========== POST请求: 保存剧情数据 ==========
        if (Request()->isPost()) {
            $param = input('post.');
            // 调用模型的 savePlot() 方法保存剧情
            // 表单数据格式:
            // - vod_plot_name[]   : 剧情标题数组
            // - vod_plot_detail[] : 剧情内容数组 (支持富文本HTML)
            // 存储时转换为 $$$ 分隔的字符串
            $res = model('Vod')->savePlot($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // ========== GET请求: 显示剧情编辑表单 ==========
        $id = input('id');
        $where=[];
        $where['vod_id'] = $id;
        // 获取视频详情，包含已解析的剧情列表
        $res = model('Vod')->infoData($where);

        // 分配模板变量
        // info: 视频基本信息 (vod_id, vod_name, vod_en, type_id)
        $info = $res['info'];
        $this->assign('info',$info);
        // vod_plot_list: 已解析的剧情数组
        // 结构: [ 1 => ['name'=>'第1集标题', 'detail'=>'第1集内容'], ... ]
        // 由 mac_plot_list() 函数解析 vod_plot_name 和 vod_plot_detail 生成
        $this->assign('vod_plot_list',$info['vod_plot_list']);


        $this->assign('title',lang('admin/vod/plot/title'));
        return $this->fetch('admin@vod/iplot');
    }

    /**
     * 删除视频
     *
     * 【功能说明】
     * 删除指定的视频数据
     * 支持批量删除和重复数据删除
     *
     * 【请求参数】
     * - ids    : 视频ID列表 (逗号分隔，批量删除)
     * - repeat : 删除重复数据模式
     * - retain : 保留策略 (max=保留最大ID, min=保留最小ID)
     *
     * 【删除逻辑】
     * 1. 批量删除: 根据 ids 参数删除
     * 2. 重复删除: 通过 SQL 删除同名视频，保留指定ID
     *
     * 【注意事项】
     * 删除后会清除重复数据缓存
     *
     * @return \think\response\Json 操作结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['vod_id'] = ['in',$ids];
            $res = model('Vod')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            Cache::rm('vod_repeat_table_created_time');
            return $this->success($res['msg']);
        }
        elseif(!empty($param['repeat'])){
            if($param['retain']=='max') {
                // 保留最大ID - 先用子查询找出要保留的ID
                $sql = 'DELETE FROM '.config('database.prefix').'vod WHERE vod_id IN (
                SELECT * FROM (
                    SELECT v1.vod_id
                    FROM '.config('database.prefix').'vod v1
                    INNER JOIN '.config('database.prefix').'vod v2 
                    ON v1.vod_name = v2.vod_name AND v1.vod_id < v2.vod_id
                ) tmp
            )';
            } else {
                // 保留最小ID - 先用子查询找出要保留的ID
                $sql = 'DELETE FROM '.config('database.prefix').'vod WHERE vod_id IN (
                SELECT * FROM (
                    SELECT v1.vod_id
                    FROM '.config('database.prefix').'vod v1
                    INNER JOIN '.config('database.prefix').'vod v2 
                    ON v1.vod_name = v2.vod_name AND v1.vod_id > v2.vod_id
                ) tmp
            )';
            }

            $res = model('Vod')->execute($sql);
            if($res===false){
                return $this->success(lang('del_err'));
            }
            Cache::rm('vod_repeat_table_created_time');
            return $this->success(lang('del_ok'));
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 批量修改视频字段
     *
     * 【功能说明】
     * 批量修改选中视频的指定字段值
     * 支持随机范围值 (如点击量)
     *
     * 【请求参数】
     * - ids   : 视频ID列表 (逗号分隔)
     * - col   : 字段名
     * - val   : 字段值
     * - start : 随机范围起始值 (点击量用)
     * - end   : 随机范围结束值 (点击量用)
     *
     * 【支持字段】
     * - vod_status    : 审核状态
     * - vod_lock      : 锁定状态
     * - vod_level     : 推荐等级
     * - vod_hits      : 点击量 (支持随机范围)
     * - type_id       : 分类ID
     * - vod_copyright : 版权状态
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];      // 视频ID列表 (逗号分隔)
        $col = $param['col'];      // 要修改的字段名
        $val = $param['val'];      // 字段新值
        $start = $param['start'];  // 随机范围起始 (点击量用)
        $end = $param['end'];      // 随机范围结束 (点击量用)

        // 分类修改必须选择目标分类
        if ($col == 'type_id' && $val==''){
            return $this->error("请选择分类提交");
        }

        // 批量修改支持的字段白名单:
        // - vod_status    : 审核状态 (0=未审核, 1=已审核)
        // - vod_lock      : 锁定状态 (0=未锁定, 1=已锁定) - 锁定后采集不会覆盖
        // - vod_level     : 推荐等级 (0-9, 9=幻灯片)
        // - vod_hits      : 点击量 (支持随机范围)
        // - type_id       : 分类ID
        // - vod_copyright : 版权状态 (0=无版权, 1=有版权)
        if(!empty($ids) && in_array($col,['vod_status','vod_lock','vod_level','vod_hits','type_id','vod_copyright'])){
            $where=[];
            $where['vod_id'] = ['in',$ids];
            $update = [];
            if(empty($start)) {
                $update[$col] = $val;
                if($col == 'type_id'){
                    $type_list = model('Type')->getCache();
                    $id1 = intval($type_list[$val]['type_pid']);
                    $update['type_id_1'] = $id1;
                }
                $res = model('Vod')->fieldData($where, $update);
            }
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['vod_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Vod')->fieldData($where, $update);
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
     * 更新今日数据
     *
     * 【功能说明】
     * 更新首页今日更新统计数据
     *
     * 【请求参数】
     * - flag : 更新标识
     *
     * @return \think\response\Json 更新结果
     */
    public function updateToday()
    {
        $param = input();
        $flag = $param['flag'];
        $res = model('Vod')->updateToday($flag);
        return json($res);
    }

    /**
     * 分配播放器/下载器/服务器列表到模板
     *
     * 【功能说明】
     * 私有方法，从配置文件读取并分配到模板
     * 按 sort 降序排序，只返回启用状态的
     *
     * 【分配变量】
     * - player_list : 播放器列表
     * - downer_list : 下载器列表
     * - server_list : 服务器组列表
     */
    private function assignBaseListByConfig() {
        $player_list = config('vodplayer');
        $downer_list = config('voddowner');
        $server_list = config('vodserver');
        $player_list = mac_multisort($player_list,'sort',SORT_DESC,'status','1');
        $downer_list = mac_multisort($downer_list,'sort',SORT_DESC,'status','1');
        $server_list = mac_multisort($server_list,'sort',SORT_DESC,'status','1');
        $this->assign('player_list',$player_list);
        $this->assign('downer_list',$downer_list);
        $this->assign('server_list',$server_list);
    }
}
