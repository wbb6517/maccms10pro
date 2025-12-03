<?php
/**
 * 用户访问日志管理控制器 (Ulog Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "用户 → 访问日志" 菜单对应的控制器
 * 负责管理用户的浏览、收藏、播放、下载等访问记录
 *
 * 【菜单路径】
 * 后台 → 用户 → 访问日志
 *
 * 【核心功能】
 * 1. index - 日志列表（支持按模型、类型、用户筛选）
 * 2. del   - 删除日志（支持批量删除、清空全部）
 *
 * 【日志类型 ulog_type】
 * - 1 = 浏览记录
 * - 2 = 收藏记录
 * - 3 = 想看/追剧
 * - 4 = 播放记录
 * - 5 = 下载记录
 *
 * 【模型类型 ulog_mid】
 * - 1 = 视频(vod)
 * - 2 = 文章(art)
 * - 3 = 专题(topic)
 * - 8 = 演员(actor)
 *
 * 【业务说明】
 * 此日志记录用户在前台的所有访问行为，包括：
 * - 用户浏览内容时记录（可用于历史记录）
 * - 用户收藏内容时记录
 * - 用户播放/下载付费内容时记录（防止重复扣费）
 *
 * 【访问路径】
 * admin.php/ulog/index     → 日志列表
 * admin.php/ulog/index?uid=1 → 查看指定用户的日志
 * admin.php/ulog/del       → 删除日志
 *
 * 【相关文件】
 * - application/common/model/Ulog.php : 日志模型
 * - application/admin/view_new/ulog/index.html : 列表页视图
 *
 * 【数据表】
 * - mac_ulog: 用户访问日志表
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Ulog extends Base
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
     * 访问日志列表
     * ============================================================
     *
     * 【功能说明】
     * 显示所有用户访问日志，支持多条件筛选和分页
     *
     * 【请求参数】
     * - mid   : 模型筛选（1=视频, 2=文章, 3=专题, 8=演员）
     * - type  : 类型筛选（1=浏览, 2=收藏, 3=想看, 4=播放, 5=下载）
     * - uid   : 用户ID筛选（查看指定用户的日志）
     * - page  : 当前页码
     * - limit : 每页条数
     *
     * 【模板变量】
     * - $list  : 日志列表数组（含关联内容信息 data）
     * - $total : 总记录数
     * - $page  : 当前页码
     * - $limit : 每页条数
     * - $param : 请求参数
     *
     * @return mixed 渲染模板
     */
    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        // 按模型类型筛选（1=视频, 2=文章, 3=专题, 8=演员）
        if(!empty($param['mid'])){
            $where['ulog_mid'] = ['eq',$param['mid']];
        }
        // 按日志类型筛选（1=浏览, 2=收藏, 3=想看, 4=播放, 5=下载）
        if(!empty($param['type'])){
            $where['ulog_type'] = ['eq',$param['type']];
        }
        // 按用户ID筛选（从用户列表点击进入时使用）
        if(!empty($param['uid'])){
            $where['user_id'] = ['eq',$param['uid'] ];
        }

        $order='ulog_id desc';
        $res = model('Ulog')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/ulog/title'));
        return $this->fetch('admin@ulog/index');
    }

    /**
     * ============================================================
     * 删除访问日志
     * ============================================================
     *
     * 【功能说明】
     * 删除选中的访问日志，支持批量删除和清空全部
     *
     * 【请求参数】
     * - ids : 要删除的日志ID数组（必填）
     * - all : 清空全部标记（1=清空所有日志）
     *
     * 【删除逻辑】
     * - 普通删除：根据 ids 参数删除选中的日志
     * - 清空全部：当 all=1 时，删除所有日志（ulog_id > 0）
     *
     * 【注意事项】
     * - 删除日志不影响用户积分
     * - 删除收藏/播放记录后，用户需要重新操作
     * - 如果删除了付费记录，用户可能需要重新付费
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
            $where['ulog_id'] = ['in',$ids];
            // 如果 all=1，则清空所有日志
            if($all==1){
                $where['ulog_id'] = ['gt',0];
            }
            $res = model('Ulog')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
