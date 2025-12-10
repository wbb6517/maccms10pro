<?php
/**
 * 自定义采集数据模型 (Custom Collection Model)
 * ============================================================
 *
 * 【文件说明】
 * 自定义采集模块的数据模型
 * 提供采集节点配置和采集内容的增删改查操作
 * 支持操作多个采集相关数据表
 *
 * 【数据表】
 * - mac_cj_node    : 采集节点配置表
 * - mac_cj_content : 采集内容暂存表
 * - mac_cj_history : 采集历史记录表 (URL去重)
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                        │
 * ├─────────────────┼────────────────────────────────────────────────┤
 * │ listData()      │ 获取数据列表 (通用，支持指定表名)                 │
 * │ infoData()      │ 获取单条数据 (通用，支持指定表名)                 │
 * │ saveData()      │ 保存节点配置 (新增/更新)                         │
 * │ delData()       │ 删除节点及其关联数据                             │
 * └─────────────────┴────────────────────────────────────────────────┘
 *
 * 【控制器调用】
 * - application/admin/controller/Cj.php : 后台采集管理
 *
 * 【特殊说明】
 * 本模型的 listData/infoData 方法接受表名参数
 * 可以操作 cj_node、cj_content、cj_history 任意表
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;
use app\common\util\Pinyin;

class Cj extends Base {

    /**
     * 获取数据列表 (通用)
     * 可操作任意采集相关数据表
     *
     * @param string $tab   表名 (不含前缀，如 cj_node)
     * @param array  $where 查询条件
     * @param string $order 排序规则
     * @param int    $page  当前页码
     * @param int    $limit 每页数量
     * @return array 标准列表返回格式
     */
    public function listData($tab,$where,$order,$page,$limit=20)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $total = Db::name($tab)->where($where)->count();
        $list = Db::name($tab)->where($where)->order($order)->page($page)->limit($limit)->select();
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取单条数据 (通用)
     * 可操作任意采集相关数据表
     *
     * @param string $tab   表名 (不含前缀)
     * @param array  $where 查询条件
     * @param string $field 查询字段
     * @return array 包含 code, msg, info 的结果数组
     */
    public function infoData($tab,$where=[],$field='*')
    {
        if(empty($tab) || empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = Db::name($tab)->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 保存采集节点配置
     * 有 nodeid 时更新，无则新增
     *
     * 【默认字段值】(新增时)
     * - urlpage         : 空字符串
     * - page_base       : 空字符串
     * - sourcecharset   : utf-8
     * - customize_config: 空字符串
     * - program_config  : 空字符串
     *
     * @param array $data 节点配置数据
     * @return array 操作结果
     */
    public function saveData($data)
    {
        $data['lastdate'] = time();
        if(!empty($data['nodeid'])){
            $where=[];
            $where['nodeid'] = ['eq',$data['nodeid']];
            $res = Db::name('cj_node')->where($where)->update($data);
        }
        else{
            $data['urlpage'] = isset($data['urlpage']) ? (string)$data['urlpage'] : '';
            $data['page_base'] = isset($data['page_base']) ? (string)$data['page_base'] : '';
            $data['sourcecharset'] = isset($data['sourcecharset']) ? (string)$data['sourcecharset'] : 'utf-8';
            $data['customize_config'] = isset($data['customize_config']) ? (string)$data['customize_config'] : '';
            $data['program_config'] = isset($data['program_config']) ? (string)$data['program_config'] : '';
            $res = Db::name('cj_node')->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除采集节点及关联数据
     *
     * 【删除顺序】
     * 1. 删除 cj_node 节点记录
     * 2. 查询 cj_content 获取所有 URL
     * 3. 删除 cj_history 中对应的 MD5 记录
     * 4. 删除 cj_content 内容记录
     *
     * @param array $where 删除条件 (通常为 nodeid)
     * @return array 操作结果
     */
    public function delData($where)
    {
        //删除node
        $res = Db::name('cj_node')->where($where)->delete();
        //删除history
        $list = Db::name('cj_content')->field('url')->where($where)->select();
        foreach ($list as $k => $v) {
            $md5 = md5($v['url']);
            Db::name('cj_history')->where('md5',$md5)->delete();
        }
        //删除content
        $res = Db::name('cj_content')->where($where)->delete();

        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }


}