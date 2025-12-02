<?php
/**
 * 用户消费记录模型 (User Log Model)
 * ============================================================
 *
 * 【文件说明】
 * 记录用户的积分消费记录，用于积分付费功能
 * 防止用户重复支付同一内容的积分
 *
 * 【数据表】
 * mac_ulog - 用户消费日志表
 *
 * 【核心功能】
 * - 记录积分消费: 用户支付积分后记录到此表
 * - 防止重复扣费: 检查是否已支付过该内容积分
 * - 消费历史查询: 用户中心可查看消费记录
 *
 * 【与需积分视频的关系】
 * 后台菜单 "视频 → 需积分视频" 显示设置了积分的视频
 * 用户播放/下载这些视频时:
 * 1. 检查 ulog 表是否有消费记录
 * 2. 无记录则提示支付积分
 * 3. 支付成功后写入 ulog 记录
 * 4. 下次访问检测到记录则不再扣费
 *
 * 【字段说明】
 * - ulog_mid   : 模型ID (1=视频, 2=文章, 3=专题, 8=演员)
 * - ulog_rid   : 关联内容ID
 * - ulog_sid   : 播放组/下载组索引
 * - ulog_nid   : 集数索引
 * - ulog_type  : 类型 (1=浏览, 2=收藏, 3=播放, 4=下载, 5=消费)
 * - ulog_points: 消费积分数
 *
 * @package     app\common\model
 * @author      MacCMS
 * @version     1.0
 */
namespace app\common\model;
use think\Db;

class Ulog extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'ulog';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


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

        $user_ids=[];
        foreach($list as $k=>&$v){
            if($v['user_id'] >0){
                $user_ids[$v['user_id']] = $v['user_id'];
            }

            if($v['ulog_mid']==1){
                $vod_info = model('Vod')->infoData(['vod_id'=>['eq',$v['ulog_rid']]],'*',1);

                if($v['ulog_sid']>0 && $v['ulog_nid']>0){
                    if($v['ulog_type']==5){
                        $vod_info['info']['link'] = mac_url_vod_down($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                    else{
                        $vod_info['info']['link'] = mac_url_vod_play($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                }
                else{
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

        if(!empty($user_ids)){
            $where2=[];
            $where['user_id'] = ['in', $user_ids];
            $order='user_id desc';
            $user_list = model('User')->listData($where2,$order,1,999);
            $user_list = mac_array_rekey($user_list['list'],'user_id');

            foreach($list as $k=>&$v){
                $list[$k]['user_name'] = $user_list[$v['user_id']]['user_name'];
            }
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

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

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

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