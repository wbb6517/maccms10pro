<?php
/**
 * 数据接收API控制器 (Data Receive API Controller)
 * ============================================================
 *
 * 【文件说明】
 * 接收外部数据并入库的API接口
 * 是苹果CMS接收第三方推送数据时的核心API
 * 与 Provide.php (数据提供) 相对应，实现双向数据交互
 *
 * 【主要功能】
 * 1. 视频数据接收 (vod)     - 接收视频信息入库
 * 2. 文章数据接收 (art)     - 接收文章信息入库
 * 3. 演员数据接收 (actor)   - 接收演员信息入库
 * 4. 角色数据接收 (role)    - 接收角色信息入库
 * 5. 网站数据接收 (website) - 接收网站信息入库
 * 6. 评论数据接收 (comment) - 接收评论信息入库
 *
 * 【访问路径】
 * ┌──────────────────────────────────────┬─────────────────────────┐
 * │ 路径                                  │ 说明                     │
 * ├──────────────────────────────────────┼─────────────────────────┤
 * │ POST api.php/receive/vod             │ 接收视频数据             │
 * │ POST api.php/receive/art             │ 接收文章数据             │
 * │ POST api.php/receive/actor           │ 接收演员数据             │
 * │ POST api.php/receive/role            │ 接收角色数据             │
 * │ POST api.php/receive/website         │ 接收网站数据             │
 * │ POST api.php/receive/comment         │ 接收评论数据             │
 * └──────────────────────────────────────┴─────────────────────────┘
 *
 * 【认证方式】
 * 使用密码认证，所有请求必须携带 pass 参数
 * - pass: 接口密码，必须与后台配置一致，且长度>=16位
 *
 * 【配置位置】
 * 后台 → 系统 → 接口配置 → 入库接口
 * 配置项存储在: $GLOBALS['config']['interface']
 *
 * 【配置项说明】
 * ┌────────────────┬──────────────────────────────────────────────┐
 * │ 配置项          │ 说明                                          │
 * ├────────────────┼──────────────────────────────────────────────┤
 * │ status         │ 接口开关: 1=开启, 0=关闭                       │
 * │ pass           │ 接口密码: 至少16位字符                         │
 * └────────────────┴──────────────────────────────────────────────┘
 *
 * 【错误码说明】
 * ┌────────┬────────────────────────────────────────────────────┐
 * │ 错误码  │ 说明                                                │
 * ├────────┼────────────────────────────────────────────────────┤
 * │ 3001   │ 接口已关闭                                          │
 * │ 3002   │ 密码错误                                            │
 * │ 3003   │ 密码不安全(长度<16位)                                │
 * │ 2001   │ 缺少必填字段(名称)                                   │
 * │ 2002   │ 缺少必填字段(分类/性别)                              │
 * │ 2003   │ 缺少必填字段(关联数据)                               │
 * │ 2004   │ 缺少必填字段(模块ID)                                 │
 * └────────┴────────────────────────────────────────────────────┘
 *
 * 【数据流程】
 * 1. 外部系统发起POST请求，携带pass和数据字段
 * 2. 构造函数验证接口状态和密码
 * 3. 对应方法验证必填字段
 * 4. 通过分类名称映射分类ID (如果未提供type_id)
 * 5. 调用 Collect 模型的对应方法入库
 * 6. 返回JSON格式的处理结果
 *
 * 【相关文件】
 * - application/common/model/Collect.php : 采集模型，实际入库逻辑
 * - application/common.php : mac_interface_type() 分类映射函数
 * - application/extra/maccms.php : interface 配置项
 *
 * ============================================================
 */
namespace app\api\controller;
use think\Controller;

/**
 * 数据接收API控制器
 * 提供外部数据推送入库功能
 */
class Receive extends Base
{
    /**
     * 请求参数
     * 存储经过 trim 和 urldecode 处理的所有请求参数
     * @var array
     */
    var $_param;

    /**
     * 构造函数 - 接口认证
     *
     * 【认证流程】
     * 1. 检查接口是否开启 (interface.status)
     * 2. 验证接口密码 (interface.pass)
     * 3. 检查密码安全性 (长度>=16位)
     *
     * 任一验证失败将直接输出JSON错误并终止
     */
    public function __construct()
    {
        parent::__construct();
        // 获取并处理所有请求参数
        $this->_param = input('','','trim,urldecode');

        // 检查接口开关
        if($GLOBALS['config']['interface']['status'] != 1){
            echo json_encode(['code'=>3001,'msg'=>lang('api/close_err')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证接口密码
        if($GLOBALS['config']['interface']['pass'] != $this->_param['pass']){
            echo json_encode(['code'=>3002,'msg'=>lang('api/pass_err')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 检查密码安全性
        if( strlen($GLOBALS['config']['interface']['pass']) <16){
            echo json_encode(['code'=>3003,'msg'=>lang('api/pass_safe_err')],JSON_UNESCAPED_UNICODE);
            exit;
        }

    }

    /**
     * 默认入口 (空方法)
     */
    public function index()
    {

    }

    /**
     * ============================================================
     * 视频数据接收
     * ============================================================
     *
     * 【功能说明】
     * 接收外部推送的视频数据并入库
     * 是最常用的数据接收接口
     *
     * 【必填参数】
     * - vod_name    : 视频名称
     * - type_id     : 分类ID (与type_name二选一)
     * - type_name   : 分类名称 (与type_id二选一)
     *
     * 【可选参数】
     * 支持 mac_vod 表的所有字段，常用:
     * - vod_sub        : 副标题
     * - vod_en         : 英文名
     * - vod_pic        : 封面图
     * - vod_actor      : 演员
     * - vod_director   : 导演
     * - vod_content    : 简介
     * - vod_play_from  : 播放来源
     * - vod_play_url   : 播放地址
     *
     * 【返回示例】
     * {"code":1,"msg":"数据接收成功","data":{"insert":1,"update":0}}
     */
    public function vod()
    {
        $info = $this->_param;

        // 验证视频名称
        if(empty($info['vod_name'])){
            echo json_encode(['code'=>2001,'msg'=>lang('api/require_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证分类 (type_id 或 type_name 必填其一)
        if(empty($info['type_id']) && empty($info['type_name'])){
            echo json_encode(['code'=>2002,'msg'=>lang('api/require_type')],JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 通过分类名称获取分类ID
        $inter = mac_interface_type();
        if(empty($info['type_id'])) {
            $info['type_id'] = $inter['vodtype'][$info['type_name']];
        }

        // 调用采集模型入库
        $data['data'][] = $info;
        $res = model('Collect')->vod_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    /**
     * ============================================================
     * 文章数据接收
     * ============================================================
     *
     * 【功能说明】
     * 接收外部推送的文章数据并入库
     *
     * 【必填参数】
     * - art_name    : 文章标题
     * - type_id     : 分类ID (与type_name二选一)
     * - type_name   : 分类名称 (与type_id二选一)
     *
     * 【可选参数】
     * 支持 mac_art 表的所有字段，常用:
     * - art_sub        : 副标题
     * - art_pic        : 封面图
     * - art_content    : 文章内容
     * - art_author     : 作者
     */
    public function art()
    {
        $info = $this->_param;

        // 验证文章标题
        if(empty($info['art_name'])){
            echo json_encode(['code'=>2001,'msg'=>lang('api/require_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证分类
        if(empty($info['type_id']) && empty($info['type_name'])){
            echo json_encode(['code'=>2002,'msg'=>lang('api/require_type')],JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 通过分类名称获取分类ID
        $inter = mac_interface_type();
        if(empty($info['type_id'])) {
            $info['type_id'] = $inter['arttype'][$info['type_name']];
        }
        // 调用采集模型入库
        $data['data'][] = $info;
        $res = model('Collect')->art_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    /**
     * ============================================================
     * 演员数据接收
     * ============================================================
     *
     * 【功能说明】
     * 接收外部推送的演员数据并入库
     *
     * 【必填参数】
     * - actor_name  : 演员姓名
     * - actor_sex   : 性别 (男/女)
     * - type_id     : 分类ID (与type_name二选一)
     * - type_name   : 分类名称 (与type_id二选一)
     *
     * 【可选参数】
     * 支持 mac_actor 表的所有字段，常用:
     * - actor_en       : 英文名
     * - actor_pic      : 照片
     * - actor_area     : 地区
     * - actor_blood    : 血型
     * - actor_birthday : 生日
     * - actor_content  : 简介
     */
    public function actor()
    {
        $info = $this->_param;

        // 验证演员姓名
        if(empty($info['actor_name'])){
            echo json_encode(['code'=>2001,'msg'=>lang('api/require_actor_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证性别
        if(empty($info['actor_sex'])){
            echo json_encode(['code'=>2002,'msg'=>lang('api/require_sex')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证分类
        if(empty($info['type_id']) && empty($info['type_name'])){
            echo json_encode(['code'=>2003,'msg'=>lang('api/require_type')],JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 通过分类名称获取分类ID
        $inter = mac_interface_type();
        if(empty($info['type_id'])) {
            $info['type_id'] = $inter['actortype'][$info['type_name']];
        }
        // 调用采集模型入库
        $data['data'][] = $info;
        $res = model('Collect')->actor_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    /**
     * ============================================================
     * 角色数据接收
     * ============================================================
     *
     * 【功能说明】
     * 接收外部推送的角色数据并入库
     * 角色需要关联到具体的视频
     *
     * 【必填参数】
     * - role_name   : 角色名称
     * - role_actor  : 扮演演员
     * - vod_name    : 关联视频名称 (与douban_id二选一)
     * - douban_id   : 关联豆瓣ID (与vod_name二选一)
     *
     * 【可选参数】
     * 支持 mac_role 表的所有字段，常用:
     * - role_en        : 英文名
     * - role_pic       : 角色图片
     * - role_content   : 角色介绍
     */
    public function role()
    {
        $info = $this->_param;

        // 验证角色名称
        if(empty($info['role_name'])){
            echo json_encode(['code'=>2001,'msg'=>lang('api/require_role_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证扮演演员
        if(empty($info['role_actor'])){
            echo json_encode(['code'=>2002,'msg'=>lang('api/require_actor_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证关联视频 (vod_name 或 douban_id 必填其一)
        if(empty($info['vod_name']) && empty($info['douban_id'])){
            echo json_encode(['code'=>2003,'msg'=>lang('api/require_rel_vod')],JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 调用采集模型入库 (角色不需要分类映射)
        $data['data'][] = $info;
        $res = model('Collect')->role_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    /**
     * ============================================================
     * 网站数据接收
     * ============================================================
     *
     * 【功能说明】
     * 接收外部推送的网站/网址数据并入库
     *
     * 【必填参数】
     * - website_name : 网站名称
     * - type_id      : 分类ID (与type_name二选一)
     * - type_name    : 分类名称 (与type_id二选一)
     *
     * 【可选参数】
     * 支持 mac_website 表的所有字段，常用:
     * - website_sub     : 副标题
     * - website_pic     : 网站截图
     * - website_url     : 网站地址
     * - website_content : 网站介绍
     */
    public function website()
    {
        $info = $this->_param;

        // 验证网站名称
        if(empty($info['website_name'])){
            echo json_encode(['code'=>2001,'msg'=>lang('api/require_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证分类
        if(empty($info['type_id']) && empty($info['type_name'])){
            echo json_encode(['code'=>2002,'msg'=>lang('api/require_type')],JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 通过分类名称获取分类ID
        $inter = mac_interface_type();
        if(empty($info['type_id'])) {
            $info['type_id'] = $inter['websitetype'][$info['type_name']];
        }
        // 调用采集模型入库
        $data['data'][] = $info;
        $res = model('Collect')->website_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    /**
     * ============================================================
     * 评论数据接收
     * ============================================================
     *
     * 【功能说明】
     * 接收外部推送的评论数据并入库
     * 评论需要关联到具体的内容(视频/文章等)
     *
     * 【必填参数】
     * - comment_name    : 评论者昵称
     * - comment_content : 评论内容
     * - comment_mid     : 模块ID (1=视频, 2=文章, 3=专题, 8=演员)
     * - rel_name        : 关联内容名称 (与douban_id二选一)
     * - douban_id       : 关联豆瓣ID (与rel_name二选一)
     *
     * 【可选参数】
     * 支持 mac_comment 表的所有字段，常用:
     * - comment_score   : 评分
     * - comment_ip      : 评论IP
     * - comment_time    : 评论时间
     */
    public function comment()
    {
        $info = $this->_param;

        // 验证评论者昵称
        if(empty($info['comment_name'])){
            echo json_encode(['code'=>2001,'msg'=>lang('api/require_comment_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证评论内容
        if(empty($info['comment_content'])){
            echo json_encode(['code'=>2002,'msg'=>lang('api/require_comment_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证模块ID
        if(empty($info['comment_mid'])){
            echo json_encode(['code'=>2004,'msg'=>lang('api/require_mid')],JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 验证关联内容 (rel_name 或 douban_id 必填其一)
        if(empty($info['rel_name']) && empty($info['douban_id'])){
            echo json_encode(['code'=>2003,'msg'=>lang('api/require_rel_name')],JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 调用采集模型入库
        $data['data'][] = $info;
        $res = model('Collect')->comment_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }
}
