<?php
/**
 * 图片管理控制器 (Images Admin Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台图片同步管理控制器
 * 用于将远程图片批量下载同步到本地或云存储
 * 支持多种内容类型的图片同步处理
 *
 * 【菜单位置】
 * 后台管理 → 视频/文章/专题等内容管理 → 图片同步入口
 *
 * 【支持的内容类型】
 * - vod     : 视频封面图/内容图
 * - art     : 文章封面图/内容图
 * - topic   : 专题封面图/内容图
 * - actor   : 演员封面图/内容图
 * - role    : 角色封面图/内容图
 * - website : 网址封面图/内容图
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ __construct()   │ 构造函数                                    │
 * │ data()          │ 预留方法                                    │
 * │ opt()           │ 图片同步设置页面                             │
 * │ del()           │ 删除本地图片文件                             │
 * │ sync()          │ 执行远程图片同步下载                          │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【访问路径】
 * admin.php/images/opt?tab=vod   → 视频图片同步设置
 * admin.php/images/opt?tab=art   → 文章图片同步设置
 * admin.php/images/sync          → 执行图片同步
 * admin.php/images/del           → 删除图片文件
 *
 * 【相关文件】
 * - application/common/model/Image.php : 图片下载/水印/缩略图模型
 * - application/admin/view_new/images/opt.html : 同步设置视图
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;

class Images extends Base
{
    /**
     * 构造函数
     * 调用父类构造函数进行初始化
     */
    public function __construct()
    {
        parent::__construct();
        //header('X-Accel-Buffering: no');
    }

    /**
     * ============================================================
     * 预留方法
     * ============================================================
     *
     * 【功能说明】
     * 预留的数据处理方法，暂未实现具体功能
     */
    public function data()
    {

    }

    /**
     * ============================================================
     * 图片同步设置页面
     * ============================================================
     *
     * 【功能说明】
     * 显示图片同步配置界面，根据tab参数显示不同内容类型的同步设置
     *
     * 【请求参数】
     * - tab : 内容类型 (vod/art/topic/actor/role/website)
     *
     * @return mixed 渲染设置页面
     */
    public function opt()
    {
        $param = input();
        $this->assign('tab',$param['tab']);
        return $this->fetch('admin@images/opt');
    }

    /**
     * ============================================================
     * 删除本地图片文件
     * ============================================================
     *
     * 【功能说明】
     * 批量删除本地上传目录下的图片文件
     * 仅允许删除 ./upload 目录下的文件，防止目录穿越攻击
     *
     * 【安全检查】
     * - 路径必须以 ./upload 开头
     * - 路径中不能包含多个 ./ (防止目录穿越)
     *
     * 【请求参数】
     * - ids : 要删除的文件路径数组
     *
     * @return \think\response\Json JSON响应
     */
    public function del()
    {
        $param = input();
        $fname = $param['ids'];
        if(!empty($fname)){
            foreach($fname as $a){
                // 统一路径分隔符
                $a = str_replace('\\','/',$a);

                // 安全检查：仅允许删除upload目录下的文件
                if( (substr($a,0,8) != "./upload") || count( explode("./",$a) ) > 2) {
                    // 非法路径，跳过不处理
                }
                else{
                    // 转换编码并删除文件
                    $a = mac_convert_encoding($a,"UTF-8","GB2312");
                    if(file_exists($a)){ @unlink($a); }
                }
            }
        }
        return $this->success(lang('del_ok'));
    }

    /**
     * ============================================================
     * 执行远程图片同步下载
     * ============================================================
     *
     * 【功能说明】
     * 将远程HTTP图片批量下载到本地或上传到云存储
     * 支持封面图同步和内容图（富文本中的img标签）同步
     * 采用分页方式处理，支持断点续传
     *
     * 【请求参数】
     * - tab   : 内容类型 (vod/art/topic/actor/role/website)
     * - col   : 同步字段 (1=封面图, 2=内容图)
     * - range : 同步范围 (1=全部, 2=指定日期)
     * - date  : 指定日期 (range=2时生效)
     * - opt   : 同步选项 (0=全部, 1=排除已错误, 2=排除今日错误, 3=仅错误)
     * - page  : 当前页码
     * - limit : 每页处理数量
     *
     * 【错误标记机制】
     * 下载失败的图片URL会添加 #err+日期 标记
     * 如: http://xxx.jpg#err2024-01-01
     * 用于区分已处理失败的图片，避免重复下载
     *
     * 【支持的上传模式】
     * - 1/local : 本地存储
     * - 2/upyun : 又拍云
     * - 3/qiniu : 七牛云
     * - 4/ftp   : FTP服务器
     * - 5/weibo : 微博图床
     *
     * @return void 直接输出HTML进度信息
     */
    public function sync()
    {
        $param = input();

        // 参数初始化
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? 10 : $param['limit'];
        // 错误标记格式: #err2024-01-01
        $flag = "#err". date('Y-m-d',time());

        // ==================== 根据tab确定表名和字段 ====================
        if($param['tab']=='vod'){
            $tab='vod';
            $col_id ='vod_id';
            $col_name ='vod_name';
            // col=2时同步内容图，否则同步封面图
            $col_pic= $param['col']==2 ? 'vod_content' : 'vod_pic';
            $col_time='vod_time';

        }
        elseif($param['tab']=='art'){
            $tab='art';
            $col_id ='art_id';
            $col_name ='art_name';
            $col_pic= $param['col']==2 ? 'art_content' :'art_pic';
            $col_time='art_time';
        }
        elseif($param['tab']=='topic'){
            $tab='topic';
            $col_id ='topic_id';
            $col_name ='topic_name';
            $col_pic=$param['col']==2 ? 'topic_content' :'topic_pic';
            $col_time='topic_time';
        }
        elseif($param['tab']=='actor'){
            $tab='actor';
            $col_id ='actor_id';
            $col_name ='actor_name';
            $col_pic=$param['col']==2 ? 'actor_content' :'actor_pic';
            $col_time='actor_time';
        }
        elseif($param['tab']=='role'){
            $tab='role';
            $col_id ='role_id';
            $col_name ='role_name';
            $col_pic=$param['col']==2 ? 'role_content' :'role_pic';
            $col_time='role_time';
        }
        elseif($param['tab']=='website'){
            $tab='website';
            $col_id ='website_id';
            $col_name ='website_name';
            $col_pic=$param['col']==2 ? 'website_content' :'website_pic';
            $col_time='website_time';
        }
        else{
            return $this->error(lang('param_err'));
        }

        // ==================== 构建查询条件 ====================
        $where = ' 1=1 ';
        // 按日期范围筛选
        if ($param['range'] =="2" && $param['date']!=""){
            $pic_fwdate = str_replace('|','-',$param['date']);
            $todayunix1 = strtotime($pic_fwdate);
            $todayunix2 = $todayunix1 +  86400;
            $where .= ' AND ('.$col_time.'>= '. $todayunix1 . ' AND '.$col_time.'<='. $todayunix2 .') ';
        }
        // 内容图模式：查找包含远程img标签的内容
        if($param['col'] == 2){
            $where .= ' and '. $col_pic . " like '%<img%src=\"http%' ";
        }
        else {
            // 封面图模式：根据opt参数筛选
            if ($param['opt'] == 1) {
                // 排除所有错误记录
                $where .= " AND instr(" . $col_pic . ",'#err')=0 ";
            } elseif ($param['opt'] == 2) {
                // 排除今日错误记录
                $where .= " AND instr(" . $col_pic . ",'" . $flag . "')=0 ";
            } elseif ($param['opt'] == 3) {
                // 仅处理错误记录
                $where .= " AND instr(" . $col_pic . ",'#err')>0 ";
            }
            // 仅处理远程图片
            $where .= " AND instr(" . $col_pic . ",'http')>0  ";
        }

        // ==================== 分页查询 ====================
        $total = Db::name($tab)->where($where)->count();
        $page_count = ceil($total / $param['limit']);

        if($total==0){
            mac_echo(lang('admin/images/sync_complete'));
            exit;
        }

        // 输出处理进度样式和提示
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        mac_echo(lang('admin/images/sync_tip',[$total,$param['limit'],$page_count,$param['page']]));

        $list = Db::name($tab)->where($where)->page($page_count-1,$param['limit'])->select();
        $config = config('maccms.upload');

        // ==================== 转换上传模式标识 ====================
        if ($config['mode'] == '2') {
            $config['mode'] = 'upyun';
        }
        elseif ($config['mode'] == '3'){
            $config['mode'] = 'qiniu';
        }
        elseif ($config['mode'] == '4') {
            $config['mode'] = 'ftp';
        }
        elseif ($config['mode'] == '5') {
            $config['mode'] = 'weibo';
        }

        // ==================== 遍历处理每条记录 ====================
        foreach($list as $k=>$v){

            mac_echo($v[$col_id].'、'.$v[$col_name]);

            // 内容图同步：提取并替换内容中的所有img标签
            if($param['col'] == 2){
                $content = $v[$col_pic];
                // 正则匹配所有img标签
                $rule = mac_buildregx('<img[^>]*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>',"is");
                preg_match_all($rule,$content,$matches);
                $matchfieldarr=$matches[1];
                $matchfieldstrarr=$matches[0];
                $matchfieldvalue="";
                // 逐个下载并替换图片
                foreach($matchfieldarr as $f=>$matchfieldstr)
                {
                    $matchfieldvalue=$matchfieldstrarr[$f];
                    $img_old = trim(preg_replace("/[ \r\n\t\f]{1,}/"," ",$matchfieldstr));
                    // 调用Image模型下载图片
                    $img_url = model('Image')->down_load($img_old, $config, $param['tab']);

                    $des = '';
                    // 处理本地存储路径
                    if(in_array($config['mode'],['local']) || substr($img_url,0,7)=='upload/'){
                        $img_url = MAC_PATH . $img_url;
                        $link = $img_url;
                        $link = str_replace('//', '/', $link);
                    }
                    else{
                        // 处理云存储路径
                        $link = str_replace('mac:', $config['protocol'].':', $img_url);
                    }
                    // 判断下载结果
                    if ($img_url == $img_old) {
                        // 下载失败，添加错误标记
                        $des = '<a href="' . $link . '" target="_blank">' . $link . '</a><font color=red>'.lang('download_err').'!</font>';
                        $img_url .= $flag;
                        $content = str_replace($img_old,"",$content);
                    } else {
                        // 下载成功，替换为新路径
                        $des = '<a href="' . $link . '" target="_blank">' . $link . '</a><font color=green>'.lang('download_ok').'!</font>';
                        $content = str_replace($img_old, $img_url, $content );
                    }
                    mac_echo($des);
                }

                // 更新内容字段
                $where = [];
                $where[$col_id] = $v[$col_id];
                $update = [];
                $update[$col_pic] = $content;
                $st = Db::name($tab)->where($where)->update($update);
            }
            else {
                // 封面图同步：直接下载封面图
                $img_old = $v[$col_pic];
                // 去除之前的错误标记
                if (strpos($img_old, "#err")) {
                    $picarr = explode("#err", $img_old);
                    $img_old = $picarr[0];
                }

                // 调用Image模型下载图片
                $img_url = model('Image')->down_load($img_old, $config, $param['tab']);
                $des = '';
                // 处理存储路径
                if(in_array($config['mode'],['local']) || substr($img_url,0,7)=='upload/'){
                    $link = MAC_PATH . $img_url;
                    $link = str_replace('//', '/', $link);
                }
                else{
                    $link = str_replace('mac:', $config['protocol'].':', $img_url);
                }

                // 判断下载结果
                if ($img_url == $img_old) {
                    // 下载失败
                    $des = '<a href="' . $img_old . '" target="_blank">' . $img_old . '</a><font color=red>'.lang('download_err').'!</font>';
                    $img_url .= $flag;
                } else {
                    // 下载成功
                    $des = '<a href="' . $link . '" target="_blank">' . $link . '</a><font color=green>'.lang('download_ok').'!</font>';
                }
                mac_echo($des);

                // 更新封面图字段
                $where = [];
                $where[$col_id] = $v[$col_id];
                $update = [];
                $update[$col_pic] = $img_url;
                $st = Db::name($tab)->where($where)->update($update);
            }
        }

        // 自动跳转到下一页继续处理
        $url = url('images/sync') .'?'. http_build_query($param);
        mac_jump( $url ,3);
    }


}
