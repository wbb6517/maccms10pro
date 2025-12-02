<?php
/**
 * 演员数据管理控制器 (Actor Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台 "视频 → 演员库" 功能的控制器
 * 管理演员/明星的基本信息数据
 *
 * 【访问路径】
 * admin.php/actor/data   → 演员列表页面
 * admin.php/actor/info   → 添加/编辑演员
 * admin.php/actor/del    → 删除演员
 * admin.php/actor/field  → 批量修改字段
 *
 * 【方法列表】
 * ┌────────────────────┬──────────────────────────────────────┐
 * │ 方法名              │ 功能说明                              │
 * ├────────────────────┼──────────────────────────────────────┤
 * │ data()             │ 演员列表页面 (支持多条件筛选)          │
 * │ info()             │ 演员添加/编辑页面                      │
 * │ del()              │ 删除演员 (支持批量删除)                │
 * │ field()            │ 批量修改字段 (状态/等级/点击量等)       │
 * └────────────────────┴──────────────────────────────────────┘
 *
 * 【数据表说明】
 * 数据表: mac_actor (通过 Actor 模型操作)
 *
 * 【核心字段说明】
 * ┌──────────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 说明                                     │
 * ├──────────────────┼─────────────────────────────────────────┤
 * │ actor_id         │ 演员ID (主键)                            │
 * │ actor_name       │ 演员姓名                                 │
 * │ actor_en         │ 英文名/拼音 (用于URL)                    │
 * │ actor_alias      │ 别名                                     │
 * │ actor_status     │ 状态: 0=未审核, 1=已审核                  │
 * │ actor_level      │ 推荐等级: 0-9                            │
 * │ actor_lock       │ 锁定: 0=否, 1=是 (锁定后采集不更新)       │
 * │ actor_sex        │ 性别                                     │
 * │ actor_area       │ 地区                                     │
 * │ actor_pic        │ 演员照片URL                              │
 * │ actor_starsign   │ 星座                                     │
 * │ actor_blood      │ 血型                                     │
 * │ actor_birthday   │ 生日                                     │
 * │ actor_height     │ 身高                                     │
 * │ actor_weight     │ 体重                                     │
 * │ actor_content    │ 详细介绍                                 │
 * │ actor_blurb      │ 简介 (100字)                             │
 * │ actor_hits       │ 总点击量                                 │
 * │ actor_time       │ 更新时间戳                               │
 * │ actor_time_add   │ 添加时间戳                               │
 * └──────────────────┴─────────────────────────────────────────┘
 *
 * @package     app\admin\controller
 * @author      MacCMS
 * @version     1.0
 */
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Actor extends Base
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 演员列表页面
     *
     * 【功能说明】
     * 显示演员数据列表，支持多条件筛选
     *
     * 【筛选条件】
     * - type   : 分类ID (关联的视频分类)
     * - level  : 推荐等级
     * - status : 审核状态 (0=未审核, 1=已审核)
     * - pic    : 图片状态 (1=无图, 2=外链图, 3=错误图)
     * - wd     : 关键词搜索 (演员姓名)
     *
     * @return mixed 渲染后的列表页面
     */
    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        $where = [];
        // ========== 分类筛选 ==========
        // 演员可以关联视频分类，用于分类展示
        if (!empty($param['type'])) {
            $where['type_id|type_id_1'] = ['eq', $param['type']];
        }
        // ========== 推荐等级筛选 ==========
        if (!empty($param['level'])) {
            $where['actor_level'] = ['eq', $param['level']];
        }
        // ========== 审核状态筛选 ==========
        // status=0: 未审核 (前台不显示)
        // status=1: 已审核 (前台正常显示)
        if (in_array($param['status'], ['0', '1'])) {
            $where['actor_status'] = ['eq', $param['status']];
        }
        // ========== 图片状态筛选 ==========
        // pic=1: 无图片 (照片为空)
        // pic=2: 外链图片 (以http开头)
        // pic=3: 错误图片 (包含#err标记)
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['actor_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['actor_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['actor_pic'] = ['like','%#err%'];
            }
        }
        // ========== 关键词搜索 ==========
        // 搜索范围: 演员姓名
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['actor_name'] = ['like','%'.$param['wd'].'%'];
        }

        // 按更新时间倒序排列
        $order='actor_time desc';
        // 调用模型获取列表数据
        $res = model('Actor')->listData($where,$order,$param['page'],$param['limit']);

        // 分配模板变量
        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        // 分类树 (用于筛选下拉框)
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $this->assign('title', lang('admin/actor/title'));
        return $this->fetch('admin@actor/index');
    }

    /**
     * 演员添加/编辑页面
     *
     * 【功能说明】
     * GET请求: 显示演员编辑表单
     * POST请求: 保存演员数据
     *
     * 【请求参数】
     * GET:
     * - id : 演员ID (编辑时传入，添加时为空)
     *
     * POST:
     * - 演员所有字段数据
     *
     * @return mixed 渲染后的编辑页面或操作结果
     */
    public function info()
    {
        // ========== POST请求: 保存演员数据 ==========
        if (Request()->isPost()) {
            $param = input('post.');
            // 调用模型的 saveData() 方法保存数据
            $res = model('Actor')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // ========== GET请求: 显示编辑表单 ==========
        $id = input('id');
        $where=[];
        $where['actor_id'] = ['eq',$id];
        // 获取演员详情
        $res = model('Actor')->infoData($where);
        $info = $res['info'];
        $this->assign('info',$info);

        // 分类树 (用于分类选择)
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $this->assign('title',lang('admin/actor/title'));
        return $this->fetch('admin@actor/info');
    }

    /**
     * 删除演员
     *
     * 【功能说明】
     * 删除指定的演员数据
     * 支持批量删除 (多个ID用逗号分隔)
     *
     * 【请求参数】
     * - ids : 演员ID列表 (逗号分隔)
     *
     * 【删除逻辑】
     * 同时清理关联的本地图片和静态HTML文件
     *
     * @return \think\response\Json 操作结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['actor_id'] = ['in',$ids];
            // 调用模型删除方法
            $res = model('Actor')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 批量修改演员字段
     *
     * 【功能说明】
     * 批量修改选中演员的指定字段值
     * 支持随机范围值 (如点击量)
     *
     * 【请求参数】
     * - ids   : 演员ID列表 (逗号分隔)
     * - col   : 字段名
     * - val   : 字段值
     * - start : 随机范围起始值 (点击量用)
     * - end   : 随机范围结束值 (点击量用)
     *
     * 【支持字段】
     * - actor_status : 审核状态
     * - actor_lock   : 锁定状态
     * - actor_level  : 推荐等级
     * - actor_hits   : 点击量 (支持随机范围)
     * - type_id      : 分类ID
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];      // 演员ID列表 (逗号分隔)
        $col = $param['col'];      // 要修改的字段名
        $val = $param['val'];      // 字段新值
        $start = $param['start'];  // 随机范围起始 (点击量用)
        $end = $param['end'];      // 随机范围结束 (点击量用)


        // 批量修改支持的字段白名单
        if(!empty($ids) && in_array($col,['actor_status','actor_lock','actor_level','type_id','actor_hits'])){
            $where=[];
            $update = [];
            $where['actor_id'] = ['in',$ids];
            if(empty($start)){
                // 直接设置值
                $update[$col] = $val;
                // 分类修改时同步更新一级分类ID
                if($col == 'type_id'){
                    $type_list = model('Type')->getCache();
                    $id1 = intval($type_list[$val]['type_pid']);
                    $update['type_id_1'] = $id1;
                }
                $res = model('Actor')->fieldData($where, $update);
            }
            else{
                // 随机范围设置 (用于点击量)
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);  // 生成随机值
                    $where['actor_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Actor')->fieldData($where, $update);
                }
            }
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
