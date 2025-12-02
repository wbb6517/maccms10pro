<?php
/**
 * 充值卡管理控制器 (Card Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 充值卡" 菜单对应的控制器
 * 负责管理积分充值卡的生成、查询、删除和导出
 *
 * 【菜单路径】
 * 后台 → 用户 → 充值卡
 *
 * 【核心功能】
 * 1. index - 充值卡列表（支持筛选、分页、CSV导出）
 * 2. info  - 批量生成充值卡（设置数量、面值、积分）
 * 3. del   - 删除充值卡（支持批量删除、清空全部）
 *
 * 【业务规则】
 * - 充值卡由卡号(16位)和密码(8位)组成
 * - 支持自定义卡号密码生成规则（数字/字母/混合）
 * - 用户在前台使用充值卡获得积分
 * - 支持按销售状态、使用状态、生成时间筛选
 *
 * 【访问路径】
 * admin.php/card/index → 充值卡列表
 * admin.php/card/info  → 批量生成充值卡
 * admin.php/card/del   → 删除充值卡
 *
 * 【相关文件】
 * - application/common/model/Card.php : 充值卡模型
 * - application/admin/validate/Card.php : 验证器
 * - application/admin/view_new/card/index.html : 列表页视图
 * - application/admin/view_new/card/info.html : 生成页视图
 *
 * 【数据表】
 * - mac_card: 充值卡数据表
 * - mac_plog: 积分日志表（使用充值卡时记录）
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Card extends Base
{
    /**
     * 构造函数
     * 调用父类Base的构造函数，完成登录验证和权限检测
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 充值卡列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有充值卡列表，支持多条件筛选、分页和CSV导出
     *
     * 【请求参数】
     * - sale_status : 销售状态筛选（0=未销售, 1=已销售）
     * - use_status  : 使用状态筛选（0=未使用, 1=已使用）
     * - wd          : 卡号搜索关键词
     * - time        : 时间筛选（1=最新批次, 7/30=最近天数）
     * - export      : 导出标记（1=导出CSV）
     * - page        : 当前页码
     * - limit       : 每页条数
     *
     * 【导出功能】
     * 当 export=1 时，导出CSV文件，包含卡号、密码、生成时间
     *
     * 【模板变量】
     * - $list  : 充值卡列表数组
     * - $total : 总记录数
     * - $page  : 当前页码
     * - $limit : 每页条数
     * - $param : 请求参数
     *
     * @return mixed 渲染模板或CSV下载
     */
    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        if(in_array($param['sale_status'],['0','1'],true)){
            $where['card_sale_status'] = ['eq',$param['sale_status']];
        }
        if(in_array($param['use_status'],['0','1'],true)){
            $where['card_use_status'] = ['eq',$param['use_status']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['card_no'] = ['like','%'.$param['wd'].'%'];
        }
        if(isset($param['time'])){
            $t=0;
            if($param['time']=='1'){
                $t = model('Card')->max('card_add_time');
            }
            else{
                $t = strtotime(date('Y-m-d',strtotime('-'.$param['time'] .' day')));
            }
            $where['card_add_time'] = ['egt', intval($t) ];
        }

        if($param['export'] =='1'){
            $param['page'] = 1;
            $param['limit'] = 9999;
        }

        $order='card_id desc';
        $res = model('Card')->listData($where,$order,$param['page'],$param['limit']);

        if($param['export'] =='1'){
            $filename = 'card_' . date('Y-m-d'). '.csv';
            header("Content-type:text/csv");
            header("Accept-Ranges:bytes");
            header("Content-Disposition:attachment;filename=".$filename."");
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Expires:0');
            header('Pragma:public');

            echo ''.lang('admin/card/import_tip') .  "\n";
            foreach($res['list'] as  $k=>$v){
                echo '="' . $v['card_no'] . '"' . ",=\"" . $v['card_pwd'] . "\"," . date('Y-m-d H:i:s',$v['card_add_time']) . "\n";
            }

            exit;
        }


        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/card/title'));
        return $this->fetch('admin@card/index');
    }

    /**
     * ============================================================
     * 批量生成充值卡
     * ============================================================
     *
     * 【功能说明】
     * 批量生成指定数量的充值卡，支持自定义面值、积分和卡号规则
     *
     * 【GET 请求】
     * 显示批量生成表单页面
     *
     * 【POST 请求参数】
     * - num      : 生成数量（必填，最多一次生成9999张）
     * - money    : 卡面值（必填，显示金额，可用于销售定价）
     * - point    : 积分值（必填，用户使用后获得的积分）
     * - role_no  : 卡号生成规则（1=纯数字, 2=纯字母, 3=数字+字母混合）
     * - role_pwd : 密码生成规则（1=纯数字, 2=纯字母, 3=数字+字母混合）
     *
     * 【生成规则】
     * - 卡号长度：16位
     * - 密码长度：8位
     * - 使用 mac_get_rndstr() 函数生成随机字符串
     *
     * 【业务流程】
     * 1. 验证必填参数（数量、面值、积分）
     * 2. 调用模型的 saveAllData() 批量插入
     * 3. 返回成功或失败信息
     *
     * @return mixed 渲染模板或JSON响应
     */
    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');

            // 验证必填参数
            if(empty($param['num']) || empty($param['money']) || empty($param['point']) ){
                return $this->error(lang('param_err'));
            }

            // 调用模型批量生成充值卡
            $res = model('Card')->saveAllData(intval($param['num']),intval($param['money']),intval($param['point']),$param['role_no'],$param['role_pwd']);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        // GET请求：显示单条充值卡详情（如果有ID参数）
        $id = input('id');
        $where=[];
        $where['card_id'] = ['eq',$id];
        $res = model('Card')->infoData($where);

        $this->assign('info',$res['info']);

        return $this->fetch('admin@card/info');
    }

    /**
     * ============================================================
     * 删除充值卡
     * ============================================================
     *
     * 【功能说明】
     * 删除选中的充值卡，支持批量删除和清空全部
     *
     * 【请求参数】
     * - ids : 要删除的充值卡ID数组（必填）
     * - all : 清空全部标记（1=清空所有充值卡）
     *
     * 【删除逻辑】
     * - 普通删除：根据 ids 参数删除选中的充值卡
     * - 清空全部：当 all=1 时，删除所有充值卡（card_id > 0）
     *
     * 【注意事项】
     * - 已使用的充值卡也会被删除，但不影响用户已获得的积分
     * - 清空操作不可恢复，建议操作前导出备份
     *
     * @return \think\response\Json 删除结果
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];

        if(!empty($ids)){
            $where=[];
            $where['card_id'] = ['in',$ids];
            // 如果 all=1，则清空所有充值卡
            if($all==1){
                $where['card_id'] = ['gt',0];
            }

            $res = model('Card')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
