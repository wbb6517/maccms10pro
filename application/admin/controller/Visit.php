<?php
/**
 * 访客日志管理控制器 (Visit Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台访客访问日志管理控制器
 * 记录用户访问来源（来路URL），用于流量分析和推广效果跟踪
 *
 * 【菜单位置】
 * 后台管理 → 用户 → 访问日志
 *
 * 【数据表】
 * mac_visit - 访客日志表
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数                                    │
 * │ index()         │ 访客日志列表页面                             │
 * │ del()           │ 删除访客日志记录                             │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/visit/index → 访客日志列表
 * admin.php/visit/del   → 删除日志记录
 *
 * 【相关文件】
 * - application/common/model/Visit.php : 访客日志模型
 * - application/admin/view_new/visit/index.html : 列表视图
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Visit extends Base
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
     * 访客日志列表页面
     * ============================================================
     *
     * 【功能说明】
     * 显示访客访问日志列表，支持多条件筛选
     * 记录用户的来路URL，便于分析流量来源
     *
     * 【筛选条件】
     * - uid  : 用户ID，精确匹配
     * - time : 时间范围 (0=当天, 7=一周内, 30=一月内)
     * - wd   : 来路URL关键词，支持http/https自动转换匹配
     *
     * 【来路URL匹配逻辑】
     * 输入URL时会自动同时匹配http和https两种协议
     * 例：输入 example.com 会匹配 http://example.com 和 https://example.com
     *
     * @return mixed 渲染列表页面
     */
    public function index()
    {
        $param = input();
        // 分页参数初始化
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=[];

        // 按用户ID筛选
        if(!empty($param['uid'])){
            $where['user_id'] = ['eq',$param['uid'] ];
        }

        // 按时间范围筛选
        if(isset($param['time'])){
            $t = strtotime(date('Y-m-d',strtotime('-'.$param['time'] .' day')));
            $where['visit_time'] = ['egt', intval($t) ];
        }

        // 按来路URL关键词筛选（自动匹配http/https）
        if(!empty($param['wd'])){
            $a = $param['wd'];
            // 处理协议前缀，同时匹配http和https
            if(substr($a,5)==='http:'){
                $b = str_replace('http:','https:',$a);
            }
            elseif(substr($a,5)==='https'){
                $b = str_replace('https:','http:',$a);
            }
            else{
                // 未带协议则自动补全
                $a = 'http://'.$param['wd'];
                $b  = 'https://'.$param['wd'];
            }
            $where['visit_ly'] = ['like', [$a.'%',$b.'%'],'OR'];
        }

        // 查询数据
        $order='visit_id desc';
        $res = model('Visit')->listData($where,$order,$param['page'],$param['limit']);

        // 分配模板变量
        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        // 分页参数占位符
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/visit/title'));
        return $this->fetch('admin@visit/index');
    }

    /**
     * ============================================================
     * 删除访客日志记录
     * ============================================================
     *
     * 【功能说明】
     * 删除指定的访客日志记录，支持批量删除和清空全部
     *
     * 【请求参数】
     * - ids : 要删除的记录ID，多个用逗号分隔
     * - all : 是否清空全部 (1=清空所有记录)
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];

        if(!empty($ids)){
            $where=[];
            $where['visit_id'] = ['in',$ids];

            // all=1 时清空所有记录
            if($all==1){
                $where['visit_id'] = ['gt',0];
            }

            $res = model('Visit')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
