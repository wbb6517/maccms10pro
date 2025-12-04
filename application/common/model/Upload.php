<?php
/**
 * 文件上传模型 (Upload Model)
 * ============================================================
 *
 * 【文件说明】
 * 文件上传核心处理模型
 * 负责处理各种类型文件的上传、验证、存储
 * 支持本地存储和多种第三方云存储服务
 *
 * 【支持的文件类型】
 * - 图片: jpg, jpeg, png, gif, webp
 * - 文档: doc, docx, xls, xlsx, ppt, pptx, pdf, wps, txt, rar, zip, torrent
 * - 媒体: rm, rmvb, avi, mkv, mp4, mp3
 *
 * 【存储模式】
 * - mode=1/local  : 本地存储
 * - mode=2/upyun  : 又拍云
 * - mode=3/qiniu  : 七牛云
 * - mode=4/ftp    : FTP上传
 * - mode=5/weibo  : 微博图床
 * - remote        : 远程存储
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ api()           │ 调用第三方上传扩展                           │
 * │ upload()        │ 主上传方法 (核心)                            │
 * │ upload_return() │ 格式化上传返回结果                           │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【安全检测】
 * - MIME类型检测：禁止PHP文件
 * - 文件扩展名白名单
 * - 文件内容十六进制检测：防止webshell
 *
 * 【相关文件】
 * - application/admin/controller/Upload.php : 上传控制器
 * - application/common/extend/upload/       : 云存储扩展目录
 * - application/common/extend/editor/       : 编辑器扩展目录
 * - application/common/model/Annex.php      : 附件管理模型
 * - application/common/model/Image.php      : 图片处理模型
 *
 * ============================================================
 */
namespace app\common\model;

use app\common\util\Ftp as ftpOper;

class Upload extends Base {

    /**
     * ============================================================
     * 调用第三方上传扩展
     * ============================================================
     *
     * 【功能说明】
     * 根据配置的存储模式调用对应的云存储扩展类
     * 将本地文件上传到云端并返回云端URL
     *
     * 【存储模式映射】
     * - mode=2 → upyun (又拍云)
     * - mode=3 → qiniu (七牛云)
     * - mode=4 → ftp   (FTP上传)
     * - mode=5 → weibo (微博图床)
     *
     * @param string $file_path 本地文件路径
     * @param array $config 上传配置
     * @return string 处理后的文件路径 (本地路径或云端URL)
     */
    public function api($file_path,$config)
    {
        if(empty($config)){
            return $file_path;
        }

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

        if(!in_array($config['mode'],['local','remote'])){
            $cp = 'app\\common\\extend\\upload\\' . ucfirst($config['mode']);
            if (class_exists($cp)) {
                $c = new $cp($config);
                $file_path = $c->submit($file_path);
            }
        }

        // 将协议前缀替换为统一的 mac: 前缀
        return str_replace(['http:','https:'],'mac:',$file_path);
    }

    /**
     * ============================================================
     * 主上传方法
     * ============================================================
     *
     * 【功能说明】
     * 处理文件上传的核心方法，支持：
     * - 普通文件上传 (通过表单)
     * - Base64图片上传 (通过imgdata参数)
     * - 用户头像上传 (flag=user)
     *
     * 【执行流程】
     * 1. 参数初始化和编辑器扩展处理
     * 2. 生成上传目录和文件名
     * 3. 处理Base64图片或普通文件上传
     * 4. 安全检测 (MIME类型、扩展名、内容检测)
     * 5. 处理水印和缩略图
     * 6. 上传到云存储 (如配置)
     * 7. 记录到附件表
     *
     * 【请求参数】
     * - from       : 来源编辑器类型 (用于返回格式)
     * - input      : 文件input名称，默认'file'
     * - flag       : 上传类型标识，默认'vod'
     * - thumb      : 是否生成缩略图，0/1
     * - thumb_class: 缩略图分类
     * - user_id    : 用户ID (用户头像上传时使用)
     * - imgdata    : Base64图片数据
     *
     * 【目录结构】
     * upload/{flag}/{日期-序号}/{文件名}
     * 每个目录最多1000个文件，超过自动创建新目录
     *
     * @param array $p 可选的外部参数
     * @return array 上传结果
     */
    public function upload($p=[])
    {
        // ==================== 参数初始化 ====================
        $param = input();
        if(!empty($p)){
            $param = array_merge($param,$p);
        }

        // 设置默认参数值
        $param['from'] = empty($param['from']) ? '' : $param['from'];
        $param['input'] = empty($param['input']) ? 'file' : $param['input'];
        $param['flag'] = empty($param['flag']) ? 'vod' : $param['flag'];
        $param['thumb'] = empty($param['thumb']) ? '0' : $param['thumb'];
        $param['thumb_class'] = empty($param['thumb_class']) ? '' : $param['thumb_class'];
        $param['user_id'] = empty($param['user_id']) ? '0' : $param['user_id'];
        $base64_img = $param['imgdata'];
        $data = [];
        $config = (array)config('maccms.site');
        $pre= $config['install_dir'];

        // 允许上传的文件扩展名
        $upload_image_ext = 'jpg,jpeg,png,gif,webp';
        $upload_file_ext = 'doc,docx,xls,xlsx,ppt,pptx,pdf,wps,txt,rar,zip,torrent';
        $upload_media_ext = 'rm,rmvb,avi,mkv,mp4,mp3';
        $add_rnd = false;
        $config = (array)config('maccms.upload');

        // ==================== 编辑器扩展处理 ====================
        if(!empty($param['from'])){
            // 根据编辑器类型调用对应的扩展进行前置处理
            $cp = 'app\\common\\extend\\editor\\' . ucfirst($param['from']);
            if (class_exists($cp)) {
                $c = new $cp;
                $c->front($param);
            }
            else{
                return self::upload_return(lang('admin/upload/not_find_extend'), '');
            }
        }
        else{
            $pre='';
        }

        // ==================== 生成上传路径 ====================
        // 上传附件物理路径
        $_upload_path = ROOT_PATH . 'upload' . '/' . $param['flag'] . '/' ;
        // 附件访问路径
        $_save_path = 'upload'. '/' . $param['flag'] . '/';

        if($param['flag']=='user'){
            // 用户头像：按用户ID取模分目录
            $uniq = $param['user_id'] % 10;
            $_upload_path .= $uniq .'/';
            $_save_path .= $uniq .'/';
            $_save_name = $param['user_id'] . '.jpg';

            if(!file_exists($_save_path)){
                mac_mkdirss($_save_path);
            }
        }
        else{
            // 普通上传：按日期+序号分目录，每目录最多1000文件
            $ymd = date('Ymd');
            $n_dir = $ymd;
            for($i=1;$i<=100;$i++){
                $n_dir = $ymd .'-'.$i;
                $path1 = $_upload_path . $n_dir. '/';
                if(file_exists($path1)){
                    $farr = glob($path1.'*.*');
                    if($farr){
                        $fcount = count($farr);
                        if($fcount>999){
                            continue;  // 超过1000个文件，使用下一个序号目录
                        }
                        else{
                            break;
                        }
                    }
                    else{
                        break;
                    }
                }
                else{
                    break;
                }
            }
            $_save_name = $n_dir . '/' . md5(microtime(true));
        }

        // ==================== 处理上传文件 ====================
        if(!empty($base64_img)){
            // Base64图片上传处理
            if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
                $type = $result[2];
                if(in_array($type, explode(',', $upload_image_ext))){
                    if(!file_put_contents($_save_path.$_save_name, base64_decode(str_replace($result[1], '', $base64_img)))){
                        return self::upload_return(lang('admin/upload/upload_faild'), $param['from']);
                    }
                    $file_size = round(filesize('./'.$_save_path.$_save_name)/1024, 2);
                }
                else {
                    return self::upload_return(lang('admin/upload/forbidden_ext'), $param['from']);
                }
            }
            else{
                return self::upload_return(lang('admin/upload/no_input_file'), $param['from']);
            }
        }
        else {
            // 普通文件上传处理
            $file = request()->file($param['input']);
            if (empty($file)) {
                return self::upload_return(lang('admin/upload/no_input_file'), $param['from']);
            }
            // 安全检测：禁止PHP文件
            if ($file->getMime() == 'text/x-php') {
                return self::upload_return(lang('admin/upload/forbidden_ext'), $param['from']);
            }

            // 判断文件类型
            if ($file->checkExt($upload_image_ext)) {
                $type = 'image';
            } elseif ($file->checkExt($upload_file_ext)) {
                $type = 'file';
            } elseif ($file->checkExt($upload_media_ext)) {
                $type = 'media';
            } else {
                return self::upload_return(lang('admin/upload/forbidden_ext'), $param['from']);
            }

            // 移动上传文件
            $upfile = $file->move($_upload_path,$_save_name);
            if (!is_file($_upload_path.$upfile->getSaveName())) {
                return self::upload_return(lang('admin/upload/upload_faild'), $param['from']);
            }
            $file_size = round($upfile->getInfo('size')/1024, 2);
            $_save_name = str_replace('\\', '/', $upfile->getSaveName());
        }

        // ==================== 安全内容检测 ====================
        // 读取文件首尾各512字节进行webshell检测
        $resource = fopen($_save_path.$_save_name, 'rb');
        $fileSize = filesize($_save_path.$_save_name);
        fseek($resource, 0);
        if ($fileSize>512){
            $hexCode = bin2hex(fread($resource, 512));
            fseek($resource, $fileSize - 512);
            $hexCode .= bin2hex(fread($resource, 512));
        } else {
            $hexCode = bin2hex(fread($resource, $fileSize));
        }
        fclose($resource);
        // 检测PHP/ASP/JS等恶意代码特征
        if(preg_match("/(3c25.*?28.*?29.*?253e)|(3c3f.*?28.*?29.*?3f3e)|(3C534352495054)|(2F5343524950543E)|(3C736372697074)|(2F7363726970743E)/is", $hexCode)){
            return self::upload_return(lang('admin/upload/upload_safe'), $param['from']);
        }

        // ==================== 构建返回数据 ====================
        $file_count = 1;
        $data = [
            'file'  => $_save_path.$_save_name,
            'type'  => $type,
            'size'  => $file_size,
            'flag' => $param['flag'],
            'ctime' => request()->time(),
            'thumb_class'=>$param['thumb_class'],
        ];

        $data['thumb'] = [];

        // ==================== 用户头像处理 ====================
        if($param['flag']=='user'){
            $add_rnd=true;
            $file = $_save_path.str_replace('\\', '/', $_save_name);
            $new_thumb = $param['user_id'] .'.jpg';
            $new_file = $_save_path . $new_thumb;
            try {
                // 生成用户头像缩略图
                $image = \think\Image::open('./' . $file);
                $t_size = explode('x', strtolower($GLOBALS['config']['user']['portrait_size']));
                if (!isset($t_size[1])) {
                    $t_size[1] = $t_size[0];
                }
                $image->thumb($t_size[0], $t_size[1], 6)->save('./' . $new_file);
                $file_size = round(filesize('./' .$new_file)/1024, 2);
            }
            catch(\Exception $e){
                return self::upload_return(lang('admin/upload/make_thumb_faild'), $param['from']);
            }
            // 更新用户头像字段
            $update = [];
            $update['user_portrait'] = $new_file;
            $where = [];
            $where['user_id'] = $GLOBALS['user']['user_id'];
            $res = model('User')->where($where)->update($update);
            if ($res === false) {
                return self::upload_return(lang('index/portrait_err'), $param['from']);
            }
        }
        else {
            // ==================== 图片处理 (水印/缩略图) ====================
            if ($type == 'image') {
                // 添加水印
                if ($config['watermark'] == 1) {
                    model('Image')->watermark($data['file'], $config, $param['flag']);
                }
                // 生成缩略图
                if ($param['thumb'] == 1 && $config['thumb'] == 1) {
                    $dd = model('Image')->makethumb($data['file'], $config, $param['flag']);
                    if (is_array($dd)) {
                        $data = array_merge($data, $dd);
                    }
                }
            }
        }
        unset($upfile);

        // ==================== 云存储上传 ====================
        // 存储模式映射
        if ($config['mode'] == 2) {
            $config['mode'] = 'upyun';
        }
        elseif ($config['mode'] == 3){
            $config['mode'] = 'qiniu';
        }
        elseif ($config['mode'] == 4) {
            $config['mode'] = 'ftp';
        }
        elseif ($config['mode'] == 5) {
            $config['mode'] = 'weibo';
        }

        $config['mode'] = strtolower($config['mode']);

        // 非本地/远程模式时，上传到云存储
        if(!in_array($config['mode'],['local','remote'])){
            $data['file'] = model('Upload')->api($data['file'],$config);
            if(!empty($data['thumb'])){
                $data['thumb'][0]['file'] = model('Upload')->api($data['thumb'][0]['file'],$config);
            }
        }

        // 处理编辑器返回路径
        if(!empty($param['from'])){
            if(substr($data['file'],0,4)!='http' && substr($data['file'],0,4)!='mac:'){
                $data['file']  =  $pre. $data['file'];
            }
            else{
                $data['file']  = mac_url_content_img($data['file']);
            }
        }

        // ==================== 记录到附件表 ====================
        $tmp = $data['file'];
        if((substr($tmp,0,7) == "/upload")){
            $tmp = substr($tmp,1);
        }
        if((substr($tmp,0,6) == "upload")){
            $annex = [];
            $annex['annex_file'] = $tmp;
            $r = model('Annex')->infoData($annex);
            if($r['code']!==1){
                // 记录主文件
                $annex['annex_type'] = $type;
                $annex['annex_size'] = $file_size;
                model('Annex')->saveData($annex);
                // 记录缩略图
                $tmp = $data['thumb'][0]['file'];
                if(!empty($tmp)){
                    $file_size = filesize($tmp);
                    $annex = [];
                    $annex['annex_file'] = $tmp;
                    $r = model('Annex')->infoData($annex);
                    if($r['code']!==1){
                        $annex['annex_type'] = $type;
                        $annex['annex_size'] = $file_size;
                        model('Annex')->saveData($annex);
                    }
                }
            }
        }
        return self::upload_return(lang('admin/upload/upload_success'), $param['from'], 1, $data);
    }


    /**
     * ============================================================
     * 格式化上传返回结果
     * ============================================================
     *
     * 【功能说明】
     * 根据上传来源格式化返回数据
     * 不同的编辑器需要不同的返回格式
     *
     * 【返回格式】
     * - 有from参数：调用对应编辑器扩展的back方法返回
     * - 前台入口：返回包含file字段的数组
     * - 后台入口：返回包含data字段的数组
     *
     * @param string $info 返回消息
     * @param string $from 来源编辑器类型
     * @param int $status 状态码: 0=失败, 1=成功
     * @param array $data 上传结果数据
     * @return array 格式化后的返回数组
     */
    private function upload_return($info='',$from='',$status=0,$data=[])
    {
        $arr = [];
        if(!empty($from)){
            $cp = 'app\\common\\extend\\editor\\' . ucfirst($from);
            if (class_exists($cp)) {
                $c = new $cp;
                $arr = $c->back($info,$status,$data);
            }
        }
        elseif(ENTRANCE=='index'){
            $arr['msg'] = $info;
            $arr['code'] = $status;
            $arr['file'] = MAC_PATH .  $data['file'] . '?'. mt_rand(1000, 9999);
        }
        else{
            $arr['msg'] = $info;
            $arr['code'] = $status;
            $arr['data'] = $data;
        }
        return $arr;
    }

}