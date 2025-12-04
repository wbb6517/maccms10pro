<?php
/**
 * 图片处理模型 (Image Model)
 * ============================================================
 *
 * 【文件说明】
 * 图片下载、水印、缩略图处理模型
 * 提供远程图片下载到本地，并支持水印添加和缩略图生成
 *
 * 【主要功能】
 * - 远程图片下载到本地
 * - 文字水印添加
 * - 多尺寸缩略图生成
 * - 自动上传到云存储
 *
 * 【支持的图片格式】
 * jpg, jpeg, png, gif, webp
 *
 * 【方法列表】
 * ┌─────────────────┬────────────────────────────────────────────┐
 * │ 方法名           │ 功能说明                                    │
 * ├─────────────────┼────────────────────────────────────────────┤
 * │ down_load()     │ 下载远程图片入口方法                         │
 * │ down_exec()     │ 执行图片下载和处理                           │
 * │ watermark()     │ 添加文字水印                                │
 * │ makethumb()     │ 生成缩略图                                  │
 * └─────────────────┴────────────────────────────────────────────┘
 *
 * 【存储路径规则】
 * upload/{flag}/{Ymd}-{n}/{md5}.{ext}
 * 例: upload/vod/20240101-1/abc123.jpg
 * 每个目录最多存储1000个文件，自动创建新目录
 *
 * 【相关文件】
 * - application/admin/controller/Images.php : 后台图片管理控制器
 * - application/common/model/Upload.php : 上传处理模型
 * - application/common/model/Annex.php : 附件记录模型
 *
 * ============================================================
 */
namespace app\common\model;
use think\image\Exception;

class Image extends Base {

    /**
     * ============================================================
     * 下载远程图片入口方法
     * ============================================================
     *
     * 【功能说明】
     * 判断URL是否为远程图片，是则下载，否则直接返回
     *
     * @param string $url    图片URL
     * @param array  $config 上传配置
     * @param string $flag   内容类型标识 (vod/art/topic/actor/role/website)
     * @return string 本地路径或原URL
     */
    public function down_load($url, $config, $flag = 'vod')
    {
        // 仅处理http开头的远程图片
        if (substr($url, 0, 4) == 'http') {
            return $this->down_exec($url, $config, $flag);
        } else {
            return $url;
        }
    }

    /**
     * ============================================================
     * 执行图片下载和处理
     * ============================================================
     *
     * 【功能说明】
     * 1. 下载远程图片到本地临时目录
     * 2. 验证图片格式有效性
     * 3. 可选：添加水印
     * 4. 可选：生成缩略图
     * 5. 可选：上传到云存储
     * 6. 记录到附件表
     *
     * 【目录分卷规则】
     * 每个日期目录最多存储1000个文件
     * 超过后自动创建 {日期}-2, {日期}-3 等新目录
     *
     * @param string $url    远程图片URL
     * @param array  $config 上传配置数组
     * @param string $flag   内容类型标识
     * @return string 成功返回本地/云存储路径，失败返回原URL加#err标记
     */
    public function down_exec($url, $config, $flag = 'vod')
    {
        // 支持的图片扩展名
        $upload_image_ext = 'jpg,jpeg,png,gif,webp';
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        // 扩展名不在支持列表则默认为jpg
        if (!in_array($ext, explode(',', $upload_image_ext))) {
            $ext = 'jpg';
        }

        // 下载远程图片内容
        $img = mac_curl_get($url);
        // 下载失败或内容过小
        if (empty($img) || strlen($img) < 10) {
            return $url . '#err';
        }

        // 生成唯一文件名
        $file_name = md5(uniqid()) .'.' . $ext;
        // 上传附件路径 (绝对路径)
        $_upload_path = ROOT_PATH . 'upload' . '/' . $flag . '/';
        // 附件访问路径 (相对路径)
        $_save_path = 'upload'. '/' . $flag . '/' ;
        $ymd = date('Ymd');
        $n_dir = $ymd;

        // ==================== 目录分卷逻辑 ====================
        // 每个目录最多存储1000个文件，超过则创建新目录
        for($i=1;$i<=100;$i++){
            $n_dir = $ymd .'-'.$i;
            $path1 = $_upload_path . $n_dir. '/';
            if(file_exists($path1)){
                $farr = glob($path1.'*.*');
                if($farr){
                    $fcount = count($farr);
                    if($fcount>999){
                        continue; // 文件数超过999，尝试下一个目录
                    }
                    else{
                        break; // 找到可用目录
                    }
                }
                else{
                    break; // 空目录可用
                }
            }
            else{
                break; // 目录不存在，将创建新目录
            }
        }

        $_upload_path .= $n_dir . '/';
        $_save_path .= $n_dir . '/';

        // 附件访问地址
        $_file_path = $_save_path.$file_name;
        // 写入文件到本地
        $saved_img_path = $_upload_path . $file_name;
        $r = mac_write_file($saved_img_path, $img);
        if(!$r){
            return $url;
        }

        // ==================== 验证图片格式 ====================
        // 重新获取文件类型，不满足时返回老链接
        $image_info = getimagesize($saved_img_path);
        $extension_hash = [
            '1'  => 'gif',
            '2'  => 'jpg',
            '3'  => 'png',
            '18' => 'webp',
        ];
        if (!isset($image_info[2]) || !isset($extension_hash[$image_info[2]])) {
            return $url . '#err';
        }
        $file_size = filesize($_upload_path.$file_name);

        // ==================== 图片处理 ====================
        // 添加水印
        if ($config['watermark'] == 1) {
            $this->watermark($_file_path,$config,$flag);
        }
        // 生成缩略图
        if ($config['thumb'] == 1) {
            $this->makethumb($_file_path,$config,$flag);
        }

        // ==================== 上传到远程存储 ====================
        $_file_path = model('Upload')->api($_file_path, $config);

        // ==================== 记录到附件表 ====================
        $tmp = $_file_path;
        if (str_starts_with($tmp, '/upload')) {
            $tmp = substr($tmp,1);
        }
        if (str_starts_with($tmp, 'upload')) {
            $annex = [];
            $annex['annex_file'] = $tmp;
            $annex['annex_type'] = 'image';
            $annex['annex_size'] = $file_size;
            model('Annex')->saveData($annex);
        }
        return $_file_path;
    }

    /**
     * ============================================================
     * 添加文字水印
     * ============================================================
     *
     * 【功能说明】
     * 在图片上添加文字水印
     *
     * 【配置参数】
     * - watermark_font     : 字体文件路径
     * - watermark_content  : 水印文字内容
     * - watermark_size     : 字体大小
     * - watermark_color    : 字体颜色
     * - watermark_location : 水印位置
     *
     * @param string $file_path 图片相对路径
     * @param array  $config    水印配置
     * @param string $flag      内容类型标识
     */
    public function watermark($file_path,$config,$flag='vod')
    {
        // 默认字体文件
        if(empty($config['watermark_font'])){
            $config['watermark_font'] = './static/font/test.ttf';
        }
        try {
            $image = \think\Image::open('./' . $file_path);
            $image->text($config['watermark_content']."", $config['watermark_font'], $config['watermark_size'], $config['watermark_color'],$config['watermark_location'])->save('./' . $file_path);
        }
        catch(\Exception $e){
            // 水印添加失败静默处理
        }
    }

    /**
     * ============================================================
     * 生成缩略图
     * ============================================================
     *
     * 【功能说明】
     * 根据配置生成多种尺寸的缩略图
     * 支持同时生成多个尺寸，用逗号分隔
     *
     * 【配置参数】
     * - thumb_type : 缩略图类型 (ThinkPHP Image类常量)
     * - thumb_size : 缩略图尺寸，如 "200x300,100x150"
     *
     * 【缩略图命名规则】
     * 原文件名_宽x高.扩展名
     * 例: abc123.jpg_200x300.jpg
     *
     * @param string $file_path 图片相对路径
     * @param array  $config    缩略图配置
     * @param string $flag      内容类型标识
     * @param int    $new       是否生成新文件(1=新文件,0=覆盖原文件)
     * @return array 缩略图信息数组
     */
    public function makethumb($file_path,$config,$flag='vod',$new=1)
    {
        $thumb_type = $config['thumb_type'];
        $data['thumb'] = [];
        if (!empty($config['thumb_size'])) {
            try {
                $image = \think\Image::open('./' . $file_path);
                // 支持多种尺寸的缩略图，逗号分隔
                $thumbs = explode(',', $config['thumb_size']);
                foreach ($thumbs as $k => $v) {
                    // 解析尺寸 (如: 200x300)
                    $t_size = explode('x', strtolower($v));
                    if (!isset($t_size[1])) {
                        $t_size[1] = $t_size[0]; // 未指定高度则等于宽度
                    }
                    // 生成缩略图文件名
                    $new_thumb = $file_path . '_' . $t_size[0] . 'x' . $t_size[1] . '.' . strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                    if($new==0){
                        $new_thumb = $file_path; // 覆盖原文件
                    }
                    // 生成缩略图
                    $image->thumb($t_size[0], $t_size[1], $thumb_type)->save('./' . $new_thumb);
                    $thumb_size = round(filesize('./' . $new_thumb) / 1024, 2);

                    // 记录缩略图信息
                    $data['thumb'][$k]['type'] = 'image';
                    $data['thumb'][$k]['flag'] = $flag;
                    $data['thumb'][$k]['file'] = $new_thumb;
                    $data['thumb'][$k]['size'] = $thumb_size;
                    $data['thumb'][$k]['ctime'] = request()->time();

                    // 缩略图也添加水印
                    if ($config['watermark'] == 1) {
                        $image = \think\Image::open('./' . $new_thumb);
                        $image->text($config['watermark_content'], $config['watermark_font'], $config['watermark_size'], $config['watermark_color'])->save('./' . $new_thumb);
                    }
                }
            }
            catch(\Exception $e){
                // 缩略图生成失败静默处理
            }
        }
        return $data;
    }




}