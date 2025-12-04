<?php
/**
 * 扩展数据模型 (Extend Data Model)
 * ============================================================
 *
 * 【文件说明】
 * 提供视频扩展筛选条件和统计数据的模型类
 * 主要用于前台筛选功能 (地区、语言、年份、分类等) 和后台仪表盘统计
 *
 * 【核心功能】
 * 1. 数据统计 - 各模块的总数和今日新增统计
 * 2. 筛选列表 - 提供标准化的筛选条件数据
 *
 * 【方法列表】
 * ┌────────────────────┬──────────────────────────────────────────────────┐
 * │ 方法名              │ 功能说明                                          │
 * ├────────────────────┼──────────────────────────────────────────────────┤
 * │ dataCount()        │ 获取各模块数据统计 (仪表盘使用)                     │
 * │ areaData()         │ 获取地区筛选列表                                   │
 * │ langData()         │ 获取语言筛选列表                                   │
 * │ classData()        │ 获取剧情分类筛选列表                               │
 * │ yearData()         │ 获取年份筛选列表                                   │
 * │ versionData()      │ 获取版本筛选列表                                   │
 * │ stateData()        │ 获取状态筛选列表                                   │
 * │ letterData()       │ 获取字母筛选列表 (A-Z, 0-9)                        │
 * └────────────────────┴──────────────────────────────────────────────────┘
 *
 * 【数据来源】
 * - 全局配置: config('maccms.app') 中的 vod_extend_xxx 配置
 * - 分类配置: mac_type.type_extend JSON字段中的分类专属配置
 *
 * 【模板标签调用】
 * {maccms:area num="20" order="asc"}
 *     {$vo.area_name}
 * {/maccms:area}
 *
 * 【相关配置】
 * - vod_extend_area    : 地区列表，逗号分隔
 * - vod_extend_lang    : 语言列表
 * - vod_extend_class   : 剧情分类列表
 * - vod_extend_year    : 年份列表
 * - vod_extend_version : 版本列表
 * - vod_extend_state   : 状态列表
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;
use think\Cache;

class Extend extends Base {

    /**
     * ============================================================
     * 获取各模块数据统计
     * ============================================================
     *
     * 【功能说明】
     * 统计各内容模块的数据总量和今日新增量
     * 主要用于后台仪表盘数据展示
     * 数据会缓存以提升性能
     *
     * 【统计内容】
     * - 视频: vod_all, vod_today, vod_min (最小ID)
     * - 文章: art_all, art_today, art_min
     * - 专题: topic_all, topic_today, topic_min
     * - 演员: actor_all, actor_today, actor_min
     * - 角色: role_all, role_today, role_min
     * - 网址: website_all, website_today, website_min
     * - 分类统计: type_all_{type_id}, type_today_{type_id}
     *
     * 【缓存机制】
     * 缓存键: {cache_flag}_data_count
     * 缓存时间: config['app']['cache_time']
     *
     * @return array 统计数据数组
     */
    public function dataCount()
    {
        // 构建缓存键
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'data_count';
        $data = Cache::get($key);

        // 缓存未命中时，重新统计
        if(empty($data)){
            // 今日零点时间戳，用于统计今日新增
            $totay = strtotime(date('Y-m-d'));

            // ==================== 视频统计 ====================
            $where = [];
            $where['vod_status'] = ['eq',1];
            // 按分类分组统计视频总数
            $tmp = model('Vod')->field('type_id_1,type_id,count(vod_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['vod_all'] += intval($v['cc']);
                $list['type_all_'.$v['type_id']] = $v->toArray();
            }

            // 统计今日新增视频
            $where['vod_time'] = ['egt',$totay];
            $tmp = model('Vod')->field('type_id_1,type_id,count(vod_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['vod_today'] += intval($v['cc']);
                $list['type_today_'.$v['type_id']] = $v->toArray();
            }
            // 获取最小视频ID (用于判断是否有数据)
            $data['vod_min'] = model('Vod')->min('vod_id');

            // ==================== 文章统计 ====================
            $where = [];
            $where['art_status'] = ['eq',1];
            $tmp = model('Art')->field('type_id_1,type_id,count(art_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['art_all'] += intval($v['cc']);
                $list['type_all_'.$v['type_id']] = $v->toArray();
            }
            // 统计今日新增文章
            $where['art_time'] = ['egt',$totay];
            $tmp = model('Art')->field('type_id_1,type_id,count(art_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['art_today'] += intval($v['cc']);
                $list['type_today_'.$v['type_id']] = $v->toArray();
            }
            $data['art_min'] = model('Art')->min('art_id');

            // ==================== 分类汇总统计 ====================
            // 将子分类数据汇总到父分类
            foreach($list as $k=>$v) {
                $data[$k]=$v['cc'];

                // 汇总到一级分类的总数
                if(strpos($k,'type_all')!==false){
                    $data['type_all_' . $v['type_id_1']] += $v['cc'];
                }
                // 汇总到一级分类的今日新增
                if(strpos($k,'type_today')!==false){
                    $data['type_today_' . $v['type_id_1']] += $v['cc'];
                }
            }

            // ==================== 专题统计 ====================
            $where = [];
            $where['topic_status'] = ['eq',1];
            $tmp = model('Topic')->where($where)->count();
            $data['topic_all'] = $tmp;
            $where['topic_time'] = ['egt',$totay];
            $tmp = model('Topic')->where($where)->count();
            $data['topic_today'] = $tmp;
            $data['tpoic_min'] = model('Topic')->min('topic_id');


            // ==================== 演员库统计 ====================
            $where = [];
            $where['actor_status'] = ['eq',1];
            $tmp = model('Actor')->where($where)->count();
            $data['actor_all'] = $tmp;
            $where['actor_time'] = ['egt',$totay];
            $tmp = model('Actor')->where($where)->count();
            $data['actor_today'] = $tmp;
            $data['actor_min'] = model('Actor')->min('actor_id');

            // ==================== 角色库统计 ====================
            $where = [];
            $where['role_status'] = ['eq',1];
            $tmp = model('Role')->where($where)->count();
            $data['role_all'] = $tmp;
            $where['role_time'] = ['egt',$totay];
            $tmp = model('Role')->where($where)->count();
            $data['role_today'] = $tmp;
            $data['role_min'] = model('Role')->min('role_id');

            // ==================== 网址库统计 ====================
            $where = [];
            $where['website_status'] = ['eq',1];
            $tmp = model('Website')->where($where)->count();
            $data['website_all'] = $tmp;
            $where['website_time'] = ['egt',$totay];
            $tmp = model('Website')->where($where)->count();
            $data['website_today'] = $tmp;
            $data['website_min'] = model('Website')->min('website_id');

            // 写入缓存
            Cache::set($key,$data,$GLOBALS['config']['app']['cache_time']);
        }
        return $data;
    }

    /**
     * ============================================================
     * 获取地区筛选列表
     * ============================================================
     *
     * 【功能说明】
     * 返回地区筛选条件列表，用于前台视频筛选页面
     * 优先使用分类专属配置，否则使用全局配置
     *
     * 【参数说明】
     * $lp 数组包含:
     * - order : 排序方式 (asc/desc)
     * - start : 起始位置
     * - num   : 获取数量
     * - tid   : 分类ID (可选，用于获取分类专属配置)
     *
     * 【数据来源】
     * - 全局: config('maccms.app.vod_extend_area')
     * - 分类: mac_type.type_extend['area']
     *
     * @param array $lp 标签参数数组
     * @return array 标准列表返回格式 ['code'=>1, 'list'=>[...]]
     *
     * 【返回数据格式】
     * [
     *     ['area_name' => '中国大陆'],
     *     ['area_name' => '中国香港'],
     *     ...
     * ]
     */
    public function areaData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        // 获取地区配置字符串
        $config = config('maccms.app');
        $data_str = $config['vod_extend_area'];

        // 如果指定了分类ID，尝试获取分类专属配置
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['area'];
            }
        }

        // 默认获取20条
        if(empty($num)){
            $num = 20;
        }
        // start 从1开始，转换为数组索引
        if($start>1){
            $start--;
        }

        // 解析配置字符串为数组
        $tmp = explode(',',$data_str);

        // 倒序处理
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }

        // 截取指定范围的数据
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['area_name' => $v];
            }
        }

        // 过滤空值
        $list = array_filter($list);
        $total = count($list);

        // 缓存键 (当前未使用)
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('area_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取语言筛选列表
     * 数据来源: vod_extend_lang 配置或分类专属配置
     * 返回格式: ['lang_name' => '国语'], ['lang_name' => '英语']...
     *
     * @param array $lp 标签参数 (order/start/num/tid)
     * @return array 标准列表返回格式
     */
    public function langData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        // 获取语言配置
        $config = config('maccms.app');
        $data_str = $config['vod_extend_lang'];

        // 分类专属配置覆盖
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['lang'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['lang_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('lang_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取剧情分类筛选列表
     * 数据来源: vod_extend_class 配置或分类专属配置
     * 返回格式: ['class_name' => '喜剧'], ['class_name' => '动作']...
     *
     * @param array $lp 标签参数 (order/start/num/tid)
     * @return array 标准列表返回格式
     */
    public function classData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        // 获取剧情分类配置
        $config = config('maccms.app');
        $data_str = $config['vod_extend_class'];

        // 分类专属配置覆盖
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['class'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['class_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('class_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取年份筛选列表
     * 数据来源: vod_extend_year 配置或分类专属配置
     * 返回格式: ['year_name' => '2024'], ['year_name' => '2023']...
     *
     * @param array $lp 标签参数 (order/start/num/tid)
     * @return array 标准列表返回格式
     */
    public function yearData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        // 获取年份配置
        $config = config('maccms.app');
        $data_str = $config['vod_extend_year'];

        // 分类专属配置覆盖
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['year'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['year_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('year_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取版本筛选列表 (如: 高清版/剧场版/TV版等)
     * 数据来源: vod_extend_version 配置或分类专属配置
     * 返回格式: ['version_name' => '高清版'], ['version_name' => '剧场版']...
     *
     * @param array $lp 标签参数 (order/start/num/tid)
     * @return array 标准列表返回格式
     */
    public function versionData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        // 获取版本配置
        $config = config('maccms.app');
        $data_str = $config['vod_extend_version'];

        // 分类专属配置覆盖
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['version'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['version_name' => $v];
            }
        }

        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('version_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取状态筛选列表 (如: 连载中/已完结等)
     * 数据来源: vod_extend_state 配置或分类专属配置
     * 返回格式: ['state_name' => '连载中'], ['state_name' => '已完结']...
     *
     * @param array $lp 标签参数 (order/start/num/tid)
     * @return array 标准列表返回格式
     */
    public function stateData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        // 获取状态配置
        $config = config('maccms.app');
        $data_str = $config['vod_extend_state'];

        // 分类专属配置覆盖
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['state'];
            }
        }

        // 排序参数安全校验
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['state_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('state_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取字母筛选列表 (A-Z, 0-9)
     * 固定列表，不依赖配置文件
     * 返回格式: ['letter_name' => 'A'], ['letter_name' => 'B']...
     *
     * 【特殊说明】
     * 与其他筛选方法不同，字母列表是固定的 A-Z + 0-9
     * 不支持分类专属配置覆盖
     *
     * @param array $lp 标签参数 (order/start/num/tid)
     * @return array 标准列表返回格式
     */
    public function letterData($lp)
    {
        // 固定字母列表: A-Z 加 0-9
        $data_str = 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,0-9';
        $tmp = explode(',',$data_str);

        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        // 排序参数安全校验
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        // tid 参数预留，当前未使用
        if($tid>0){

        }

        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['letter_name' => $v];
            }
        }

        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('letter_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }


}