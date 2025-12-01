<?php
/**
 * 视频数据验证器 (Vod Validate)
 * ============================================================
 *
 * 【文件说明】
 * 视频数据保存前的验证和格式化处理
 * 在 Vod 模型的 saveData() 方法中调用
 *
 * 【验证规则】
 * - vod_name: 视频名称 (必填)
 * - type_id: 分类ID (必填)
 *
 * 【验证场景】
 * - add: 新增视频时验证
 * - edit: 编辑视频时验证
 *
 * 【数据格式化】
 * formatDataBeforeDb() 方法:
 * - XSS过滤: 防止跨站脚本攻击
 * - 长度裁剪: 确保数据不超过数据库字段长度
 *
 * @package     app\common\validate
 * @author      MacCMS
 * @version     1.0
 */
namespace app\common\validate;
use think\Validate;

class Vod extends Validate
{
    /**
     * 验证规则
     *
     * 定义必填字段和验证规则
     * 规则格式: '字段名' => '规则1|规则2'
     *
     * @var array
     */
    protected $rule =   [
        'vod_name'  => 'require',   // 视频名称必填
        'type_id'  => 'require',    // 分类ID必填
    ];

    /**
     * 验证错误消息
     *
     * 自定义错误提示，使用语言包键名
     * 格式: '字段名.规则名' => '语言包键名'
     *
     * @var array
     */
    protected $message  =   [
        'vod_name.require' => 'validate/require_name',  // 请填写名称
        'type_id.require' => 'validate/require_type',   // 请选择分类
    ];

    /**
     * 验证场景
     *
     * 不同操作使用不同的验证字段组合
     * 格式: '场景名' => ['字段1', '字段2']
     *
     * @var array
     */
    protected $scene = [
        'add'  =>  ['vod_name','type_id'],   // 新增场景
        'edit'  =>  ['vod_name','type_id'],  // 编辑场景
    ];


    /**
     * 数据格式化处理 (保存前)
     *
     * 【功能说明】
     * 在数据写入数据库前进行安全处理:
     * 1. XSS过滤 - 使用 mac_filter_xss() 清除恶意脚本
     * 2. 长度裁剪 - 按数据库字段长度截取，防止数据溢出
     *
     * 【处理字段】
     * 仅处理字符串类型字段，数值/文本类型不在此处理
     *
     * 【调用位置】
     * Vod 模型 saveData() 方法中:
     * $data = VodValidate::formatDataBeforeDb($data);
     *
     * @param array $data 待处理的视频数据
     * @return array 处理后的数据
     */
    public static function formatDataBeforeDb($data)
    {
        /**
         * 需要过滤和裁剪的字段配置
         * 格式: '字段名' => 最大长度(字节)
         *
         * 字段分类:
         * - 基本信息: vod_name, vod_sub, vod_en, vod_color, vod_tag, vod_class
         * - 图片字段: vod_pic, vod_pic_thumb, vod_pic_slide, vod_pic_screenshot
         * - 人员字段: vod_actor, vod_director, vod_writer, vod_behind
         * - 描述字段: vod_blurb, vod_remarks, vod_pubdate
         * - 剧集字段: vod_serial, vod_tv, vod_weekday
         * - 地区语言: vod_area, vod_lang, vod_year, vod_version, vod_state
         * - 其他字段: vod_author, vod_jumpurl, vod_tpl, vod_tpl_play, vod_tpl_down
         * - 关联字段: vod_reurl, vod_rel_vod, vod_rel_art
         * - 密码字段: vod_pwd, vod_pwd_url, vod_pwd_play, vod_pwd_play_url, vod_pwd_down, vod_pwd_down_url
         * - 播放/下载: vod_play_from, vod_play_server, vod_play_note, vod_down_from, vod_down_server, vod_down_note
         */
        $filter_fields = [
            // ===== 基本信息字段 =====
            'vod_name'           => 255,    // 视频名称
            'vod_sub'            => 255,    // 副标题
            'vod_en'             => 255,    // 英文名/拼音
            'vod_color'          => 6,      // 标题颜色 (十六进制)
            'vod_tag'            => 100,    // TAG标签
            'vod_class'          => 255,    // 扩展分类

            // ===== 图片字段 =====
            'vod_pic'            => 1024,   // 封面图URL
            'vod_pic_thumb'      => 1024,   // 缩略图URL
            'vod_pic_slide'      => 1024,   // 幻灯片图URL
            'vod_pic_screenshot' => 65535,  // 截图 (多张)

            // ===== 演职人员字段 =====
            'vod_actor'          => 255,    // 演员
            'vod_director'       => 255,    // 导演
            'vod_writer'         => 100,    // 编剧
            'vod_behind'         => 100,    // 幕后人员

            // ===== 描述字段 =====
            'vod_blurb'          => 255,    // 简介
            'vod_remarks'        => 100,    // 备注
            'vod_pubdate'        => 100,    // 上映日期

            // ===== 剧集信息字段 =====
            'vod_serial'         => 20,     // 连载集数
            'vod_tv'             => 30,     // 播出平台
            'vod_weekday'        => 30,     // 更新日期

            // ===== 地区语言年份字段 =====
            'vod_area'           => 20,     // 地区
            'vod_lang'           => 10,     // 语言
            'vod_year'           => 10,     // 年份
            'vod_version'        => 30,     // 版本
            'vod_state'          => 30,     // 状态

            // ===== 其他信息字段 =====
            'vod_author'         => 60,     // 编辑/发布者
            'vod_jumpurl'        => 150,    // 跳转URL
            'vod_tpl'            => 30,     // 详情页模板
            'vod_tpl_play'       => 30,     // 播放页模板
            'vod_tpl_down'       => 30,     // 下载页模板
            'vod_duration'       => 10,     // 时长

            // ===== 关联字段 =====
            'vod_reurl'          => 255,    // 来源URL
            'vod_rel_vod'        => 255,    // 关联视频
            'vod_rel_art'        => 255,    // 关联文章

            // ===== 密码保护字段 =====
            'vod_pwd'            => 10,     // 详情页密码
            'vod_pwd_url'        => 255,    // 详情页密码获取URL
            'vod_pwd_play'       => 10,     // 播放页密码
            'vod_pwd_play_url'   => 255,    // 播放页密码获取URL
            'vod_pwd_down'       => 10,     // 下载页密码
            'vod_pwd_down_url'   => 255,    // 下载页密码获取URL

            // ===== 播放/下载来源字段 =====
            'vod_play_from'      => 255,    // 播放来源编码
            'vod_play_server'    => 255,    // 播放服务器组
            'vod_play_note'      => 255,    // 播放备注
            'vod_down_from'      => 255,    // 下载来源编码
            'vod_down_server'    => 255,    // 下载服务器组
            'vod_down_note'      => 255,    // 下载备注
        ];

        // 遍历字段配置，进行过滤和裁剪
        foreach ($filter_fields as $field => $length) {
            // 跳过未提交的字段
            if (!isset($data[$field])) {
                continue;
            }
            // XSS过滤: 清除潜在的恶意脚本代码
            $data[$field] = mac_filter_xss($data[$field]);
            // 长度裁剪: 按数据库字段长度截取，使用 mb_substr 支持多字节字符
            $data[$field] = mb_substr($data[$field], 0, $length);
        }
        return $data;
    }

}