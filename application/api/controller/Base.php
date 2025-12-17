<?php
/**
 * API模块基础控制器 (API Base Controller)
 * ============================================================
 *
 * 【文件说明】
 * API 模块的基础控制器类，所有 API 控制器都继承此类
 * 提供基础的初始化功能和公共配置
 *
 * 【继承关系】
 * Base → All (app\common\controller\All) → Controller (think\Controller)
 *
 * 【主要功能】
 * 1. 继承公共控制器 All 的基础功能
 * 2. 加载站点配置到模板变量
 * 3. 站点关闭状态检测 (预留)
 *
 * 【配置来源】
 * $GLOBALS['config']['site'] - 站点基础配置
 * 配置文件: application/extra/maccms.php → site
 *
 * 【子类列表】
 * - Vod.php      : 视频数据API
 * - Art.php      : 文章数据API
 * - Actor.php    : 演员数据API
 * - User.php     : 用户数据API
 * - Comment.php  : 评论数据API
 * - Provide.php  : 数据提供API (采集源)
 * - Receive.php  : 数据接收API (入库)
 * - Timming.php  : 定时任务API
 * - 等...
 *
 * 【访问入口】
 * api.php → application/api/controller/*
 *
 * ============================================================
 */
namespace app\api\controller;

use think\Controller;
use app\common\controller\All;

class Base extends All
{
    /**
     * 构造函数
     *
     * 【功能说明】
     * 1. 调用父类构造函数完成基础初始化
     * 2. 加载站点配置到模板变量 (用于API返回站点信息)
     * 3. 检测站点关闭状态 (预留功能)
     *
     * 【初始化流程】
     * parent::__construct()
     *   → All::__construct()
     *     → 加载全局配置
     *     → 初始化常量 (MAC_*)
     *     → 加载语言包
     */
    public function __construct()
    {
        // 调用父类构造函数，完成基础初始化
        parent::__construct();

        // 获取站点配置并赋值给模板
        // 配置项包括: site_name, site_url, site_logo 等
        $config = $GLOBALS['config']['site'];
        $this->assign($config);

        // 站点关闭状态检测 (预留功能)
        // site_status: 0=关闭, 1=开启
        // TODO: 可在此处添加站点关闭时的API响应处理
        if($config['site_status'] == 0){
            // 预留: 站点关闭时的处理逻辑
            // 可返回特定错误码或提示信息
        }
    }

}