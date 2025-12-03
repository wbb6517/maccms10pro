<?php
/**
 * 用户访问日志模型 (Ulog Model)
 * ============================================================
 *
 * 【功能说明】
 * 记录用户的访问行为日志，包括浏览、收藏、播放、下载等
 * 主要用于用户历史记录、收藏夹和积分消费防重复扣费
 *
 * 【数据表】
 * mac_ulog - 用户访问日志表
 *
 * 【方法列表】
 * ┌─────────────────────────────────┬──────────────────────────────┐
 * │ 方法名                           │ 说明                          │
 * ├─────────────────────────────────┼──────────────────────────────┤
 * │ listData()                      │ 获取日志列表（含关联内容）    │
 * │ infoData()                      │ 获取单条日志信息              │
 * │ saveData()                      │ 保存访问记录                  │
 * │ delData()                       │ 删除日志                      │
 * │ fieldData()                     │ 更新指定字段                  │
 * └─────────────────────────────────┴──────────────────────────────┘
 *
 * 【日志类型 ulog_type】
 * - 1 = 浏览记录（用户查看详情页）
 * - 2 = 收藏记录（用户添加收藏）
 * - 3 = 想看/追剧（用户标记想看）
 * - 4 = 播放记录（用户播放视频）
 * - 5 = 下载记录（用户下载内容）
 *
 * 【模型类型 ulog_mid】
 * - 1 = 视频(vod)
 * - 2 = 文章(art)
 * - 3 = 专题(topic)
 * - 8 = 演员(actor)
 *
 * 【与积分消费的关系】
 * 当视频设置了播放/下载积分时：
 * 1. 用户首次播放/下载时扣除积分
 * 2. 同时写入 ulog 记录（ulog_points > 0）
 * 3. 再次访问时检测到记录则不再扣费
 *
 * 【相关文件】
 * - application/admin/controller/Ulog.php : 后台控制器
 * - application/index/controller/User.php : 前台用户中心
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;

class Ulog extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'ulog';

    // 定义时间戳字段名（不自动处理）
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成字段
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    /**
     * 获取访问日志列表
     *
     * 【功能说明】
     * 分页获取日志列表，支持多条件筛选
     * 自动关联查询内容信息（视频/文章/专题/演员）和用户信息
     *
     * 【参数说明】
     * @param array  $where 查询条件（ulog_mid, ulog_type, user_id等）
     * @param string $order 排序规则（默认 ulog_id desc）
     * @param int    $page  当前页码（默认1）
     * @param int    $limit 每页条数（默认20）
     * @param int    $start 起始偏移量（默认0）
     *
     * 【返回数据】
     * @return array [
     *     'code'      => 1,
     *     'msg'       => '数据列表',
     *     'page'      => 当前页码,
     *     'pagecount' => 总页数,
     *     'limit'     => 每页条数,
     *     'total'     => 总记录数,
     *     'list'      => 日志数组（含 data 关联内容信息, user_name 用户名）
     * ]
     *
     * 【关联内容 data 结构】
     * - id   : 内容ID
     * - name : 内容名称
     * - pic  : 图片地址
     * - link : 详情页链接
     * - type : 分类信息（视频/文章有）
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        $total = $this->where($where)->count();
        $list = Db::name('Ulog')->where($where)->order($order)->limit($limit_str)->select();

        // 收集用户ID用于批量查询用户名
        $user_ids=[];
        foreach($list as $k=>&$v){
            if($v['user_id'] >0){
                $user_ids[$v['user_id']] = $v['user_id'];
            }

            // 根据模型类型关联查询内容信息
            if($v['ulog_mid']==1){
                // 视频内容
                $vod_info = model('Vod')->infoData(['vod_id'=>['eq',$v['ulog_rid']]],'*',1);

                // 生成播放/下载/详情链接
                if($v['ulog_sid']>0 && $v['ulog_nid']>0){
                    if($v['ulog_type']==5){
                        // 下载类型生成下载页链接
                        $vod_info['info']['link'] = mac_url_vod_down($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                    else{
                        // 其他类型生成播放页链接
                        $vod_info['info']['link'] = mac_url_vod_play($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                }
                else{
                    // 无集数信息生成详情页链接
                    $vod_info['info']['link'] = mac_url_vod_detail($vod_info['info']);
                }
                $v['data'] = [
                    'id'=>$vod_info['info']['vod_id'],
                    'name'=>$vod_info['info']['vod_name'],
                    'pic'=>mac_url_img($vod_info['info']['vod_pic']),
                    'link'=>$vod_info['info']['link'],
                    'type'=>[
                        'type_id'=>$vod_info['info']['type']['type_id'],
                        'type_name'=>$vod_info['info']['type']['type_name'],
                        'link'=>mac_url_type($vod_info['info']['type']),
                    ],

                ];
            }
            elseif($v['ulog_mid']==2){
                // 文章内容
                $art_info = model('Art')->infoData(['art_id'=>['eq',$v['ulog_rid']]],'*',1);
                $art_info['info']['link'] = mac_url_art_detail($art_info['info']);
                $v['data'] = [
                    'id'=>$art_info['info']['art_id'],
                    'name'=>$art_info['info']['art_name'],
                    'pic'=>mac_url_img($art_info['info']['art_pic']),
                    'link'=>$art_info['info']['link'],
                    'type'=>[
                        'type_id'=>$art_info['info']['type']['type_id'],
                        'type_name'=>$art_info['info']['type']['type_name'],
                        'link'=>mac_url_type($art_info['info']['type']),
                    ],

                ];
            }
            elseif($v['ulog_mid']==3){
                // 专题内容
                $topic_info = model('Topic')->infoData(['topic_id'=>['eq',$v['ulog_rid']]],'*',1);
                $topic_info['info']['link'] = mac_url_topic_detail($topic_info['info']);
                $v['data'] = [
                    'id'=>$topic_info['info']['topic_id'],
                    'name'=>$topic_info['info']['topic_name'],
                    'pic'=>mac_url_img($topic_info['info']['topic_pic']),
                    'link'=>$topic_info['info']['link'],
                    'type'=>[],
                ];
            }
            elseif($v['ulog_mid']==8){
                // 演员内容
                $actor_info = model('Actor')->infoData(['actor_id'=>['eq',$v['ulog_rid']]],'*',1);
                $actor_info['info']['link'] = mac_url_actor_detail($actor_info['info']);
                $v['data'] = [
                    'id'=>$actor_info['info']['actor_id'],
                    'name'=>$actor_info['info']['actor_name'],
                    'pic'=>mac_url_img($actor_info['info']['actor_pic']),
                    'link'=>$actor_info['info']['link'],
                    'type'=>[],
                ];
            }
        }

        // 批量查询用户名
        if(!empty($user_ids)){
            $where2=[];
            $where['user_id'] = ['in', $user_ids];
            $order='user_id desc';
            $user_list = model('User')->listData($where2,$order,1,999);
            $user_list = mac_array_rekey($user_list['list'],'user_id');

            // 将用户名添加到列表数据中
            foreach($list as $k=>&$v){
                $list[$k]['user_name'] = $user_list[$v['user_id']]['user_name'];
            }
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取单条日志信息
     *
     * 【功能说明】
     * 根据条件获取单条访问日志详情
     *
     * @param array  $where 查询条件（通常是 ulog_id）
     * @param string $field 要查询的字段（默认 '*'）
     *
     * @return array [
     *     'code' => 1/1001/1002,
     *     'msg'  => 提示信息,
     *     'info' => 日志信息数组
     * ]
     */
    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 保存消费记录
     *
     * 【功能说明】
     * 记录用户的积分消费行为，用于:
     * - 防止重复扣费 (已消费过则不再扣积分)
     * - 用户消费历史查询
     *
     * 【调用场景】
     * 前台用户支付积分观看/下载内容时调用
     * 在 index/controller/Vod.php 的 play/down 方法中使用
     *
     * 【参数说明】
     * - ulog_mid   : 模型ID (1=视频, 2=文章, 3=专题, 8=演员)
     * - ulog_rid   : 内容ID
     * - ulog_sid   : 播放组索引
     * - ulog_nid   : 集数索引
     * - ulog_type  : 操作类型 (1=浏览, 2=收藏, 3=播放, 4=下载, 5=消费)
     * - ulog_points: 消费积分数
     *
     * @param array $data 消费记录数据
     * @return array 返回结构: code/msg
     */
    public function saveData($data)
    {
        // 自动获取当前登录用户ID
        $data['user_id'] = intval(cookie('user_id'));
        // 记录消费时间
        $data['ulog_time'] = time();

        // 数据验证
        $validate = \think\Loader::validate('Ulog');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // 参数合法性检查
        // ulog_mid: 1=视频, 2=文章, 3=专题, 8=演员
        // ulog_type: 1=浏览, 2=收藏, 3=播放, 4=下载, 5=消费
        if($data['user_id']==0 || !in_array($data['ulog_mid'],['1','2','3','8']) || !in_array($data['ulog_type'],['1','2','3','4','5']) ) {
            return ['code'=>1002,'msg'=>lang('param_err')];
        }

        // 执行保存
        if(!empty($data['ulog_id'])){
            // 更新已有记录
            $where=[];
            $where['ulog_id'] = ['eq',$data['ulog_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            // 新增消费记录
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1004,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除访问日志
     *
     * 【功能说明】
     * 根据条件删除日志记录
     * 支持单条删除、批量删除和清空全部
     *
     * 【注意事项】
     * 如果删除了付费观看记录，用户再次访问时可能需要重新付费
     *
     * @param array $where 删除条件（如 ulog_id in [1,2,3] 或 ulog_id > 0）
     *
     * @return array ['code' => 1/1001, 'msg' => 提示信息]
     */
    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 更新指定字段
     *
     * 【功能说明】
     * 批量更新日志的指定字段
     *
     * @param array  $where 更新条件
     * @param string $col   字段名
     * @param mixed  $val   字段值
     *
     * @return array ['code' => 1/1001, 'msg' => 提示信息]
     */
    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}