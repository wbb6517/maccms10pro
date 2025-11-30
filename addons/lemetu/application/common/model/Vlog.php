<?php

namespace app\common\model;

use think\Db;
class Vlog extends Base
{
    protected $table = 'mac_vlog';
    
    
    
    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$totalshow=1)
    {
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        $tmp = Db::name('Vlog')
            ->where($where)
            ->field('max(last_view_time) last_view_time,vod_id,percent,source,nid,user_id')
            ->group('vod_id,user_id')
            ->order($order)
            ->limit($limit_str)
            ->select();
        $total = Db::name('Vlog')
            ->where($where)
            ->field('max(last_view_time) last_view_time,vod_id,percent,source,nid,user_id')
            ->group('vod_id,user_id')
            ->count('vod_id');

        $list = [];
        foreach($tmp as $k=>$v){
            $vod_id = $v['vod_id'];
            $vod = Db::name('Vod')->where('vod_id',$vod_id)
            ->field(['type_id,vod_name,vod_pic,vod_pic_thumb'])
            ->find();
            if(!empty($vod)){
                $vlog = Db::name('Vlog')
                    ->where('user_id',$v['user_id'])
                    ->where('vod_id',$v['vod_id'])
                    ->where('last_view_time',$v['last_view_time'])
                    ->field('nid,curProgress,percent,source,urlIndex,playSourceIndex,id')
                    ->find();
                $v['type_id'] = $vod['type_id'];
                $v['vod_name'] = $vod['vod_name'];
                $v['vod_pic'] = $vod['vod_pic'];
                $v['pic_thumb'] = $vod['vod_pic_thumb'];
                $v['nid'] = $vlog['nid'];
                $v['curProgress'] = $vlog['curProgress'];
                $v['percent'] = $vlog['percent'];
                $v['source'] = $vlog['source'];
                $v['urlIndex'] = $vlog['urlIndex'];
                $v['playSourceIndex'] = $vlog['playSourceIndex'];
                $v['id'] = $vlog['id'];
            	$list[] = $v;
            }else{
            	$v['type_id'] = '';
                $v['vod_name'] = '';
                $v['vod_pic'] = '';
            }
        }

        return ['code'=>1,'msg'=>'数据列表','page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function saveData($data)
    {
        $user_id = $GLOBALS['user']['user_id'];
        $data['user_id'] = $user_id;

        $res = $this->allowField(true)->insert($data);

        if(false === $res){
            return ['code'=>1004,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }
}