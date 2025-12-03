<?php
/**
 * 插件管理控制器 (Addon Controller)
 * ============================================================
 *
 * 【功能说明】
 * 后台 "应用 → 插件管理" 菜单对应的控制器
 * 负责管理系统插件的安装、卸载、启用、禁用、配置等功能
 *
 * 【菜单路径】
 * 后台 → 应用 → 插件管理
 *
 * 【核心功能】
 * ┌─────────────────┬────────────────────────────────────────┐
 * │ 方法             │ 说明                                    │
 * ├─────────────────┼────────────────────────────────────────┤
 * │ index()         │ 插件列表页面                            │
 * │ downloaded()    │ 获取已下载的插件列表（JSON接口）        │
 * │ config()        │ 插件配置页面（读取/保存配置）           │
 * │ install()       │ 安装插件                                │
 * │ uninstall()     │ 卸载插件                                │
 * │ state()         │ 启用/禁用插件                           │
 * │ local()         │ 本地上传安装插件                        │
 * │ upgrade()       │ 更新插件                                │
 * │ add()           │ 添加插件页面                            │
 * │ info()          │ 插件详情（预留）                        │
 * └─────────────────┴────────────────────────────────────────┘
 *
 * 【插件系统说明】
 * - 插件存放目录：addons/（ADDON_PATH常量）
 * - 插件配置文件：addons/{name}/info.ini
 * - 插件主类文件：addons/{name}/{Name}.php
 * - 基于 think-addons 扩展实现
 *
 * 【插件状态 state】
 * - 0 = 禁用状态
 * - 1 = 启用状态
 *
 * 【插件目录结构】
 * addons/
 * └── {插件名}/
 *     ├── info.ini      # 插件信息配置
 *     ├── config.php    # 插件参数配置
 *     ├── {Name}.php    # 插件主类
 *     ├── controller/   # 控制器目录
 *     ├── model/        # 模型目录
 *     ├── view/         # 视图目录
 *     └── install.sql   # 安装SQL（可选）
 *
 * 【访问路径】
 * admin.php/addon/index      → 插件列表
 * admin.php/addon/config     → 插件配置
 * admin.php/addon/install    → 安装插件
 * admin.php/addon/uninstall  → 卸载插件
 * admin.php/addon/state      → 启用/禁用
 * admin.php/addon/upgrade    → 更新插件
 *
 * 【相关文件】
 * - application/common/model/Addon.php : 插件模型
 * - application/admin/view_new/addon/ : 视图目录
 * - think/addons/Service.php : 插件服务类
 *
 * 【依赖扩展】
 * - think-addons : ThinkPHP5插件扩展
 *
 * ============================================================
 */
namespace app\admin\controller;
use think\Db;
use think\addons\AddonException;
use think\addons\Service;
use think\Cache;
use think\Config;
use think\Exception;
use app\common\util\Dir;

class Addon extends Base
{
    /**
     * 构造函数
     * 调用父类Base的构造函数，完成登录验证和权限检测
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 插件列表页面
     * ============================================================
     *
     * 【功能说明】
     * 显示插件管理列表页面
     * 实际插件数据通过 AJAX 调用 downloaded() 方法获取
     *
     * 【页面加载完整流程】
     * ┌─────────────────────────────────────────────────────────────┐
     * │ 1. 服务端渲染阶段（本方法）                                   │
     * │    └─ 设置页面标题 → 返回 HTML 模板                          │
     * ├─────────────────────────────────────────────────────────────┤
     * │ 2. 前端初始化阶段（index.html JS）                           │
     * │    └─ layui.use() 初始化 form/layer/upload/element 组件     │
     * │    └─ 绑定 Tab 切换事件监听                                  │
     * │    └─ 绑定按钮点击事件（启用/禁用/卸载）                     │
     * │    └─ 自动触发 $('.btn-local').click() 加载本地插件          │
     * ├─────────────────────────────────────────────────────────────┤
     * │ 3. AJAX 数据加载阶段                                         │
     * │    └─ load_list() 函数发起 JSONP 请求                        │
     * │    └─ 调用 downloaded() 方法获取插件列表                     │
     * │    └─ 遍历返回数据，动态生成插件卡片 HTML                    │
     * │    └─ 插入到 #addon_list 容器中                              │
     * └─────────────────────────────────────────────────────────────┘
     *
     * 【前端可执行的操作】
     * ┌─────────────┬─────────────────────┬──────────────────────────┐
     * │ 按钮         │ CSS类/触发方式       │ 调用的后端方法            │
     * ├─────────────┼─────────────────────┼──────────────────────────┤
     * │ 配置         │ .j-iframe           │ config?name={name}       │
     * │ 启用         │ .btn-enable         │ state?action=enable      │
     * │ 禁用         │ .btn-disable        │ state?action=disable     │
     * │ 卸载         │ .btn-uninstall      │ uninstall?name={name}    │
     * │ 搜索         │ .j-search           │ 前端过滤，重新load_list  │
     * └─────────────┴─────────────────────┴──────────────────────────┘
     *
     * 【插件卡片显示内容】
     * - 插件图片 (row.image)
     * - 插件标题 (row.title) + 等级标签
     * - 价格 (row.price)
     * - 作者 (row.author)
     * - 简介 (row.intro)
     * - 版本号 (row.version)
     * - 更新时间 (row.createtime → getDataTime格式化)
     *
     * 【相关视图文件】
     * - application/admin/view_new/addon/index.html
     *
     * @return mixed 渲染模板
     */
    public function index()
    {
        // 获取请求参数（当前未使用，预留扩展）
        $param = input();

        // 设置页面标题（显示在浏览器标签和面包屑导航）
        // lang('admin/addon/title') 从语言包获取"插件管理"
        $this->assign('title',lang('admin/addon/title'));

        // 渲染插件列表页面模板
        // 页面加载后，前端 JS 会自动调用 load_list() 发起 AJAX 请求
        // 请求 downloaded() 方法获取实际的插件数据
        return $this->fetch('admin@addon/index');
    }

    /**
     * ============================================================
     * 插件配置页面
     * ============================================================
     *
     * 【功能说明】
     * 读取和保存插件的配置参数
     * 配置定义在插件目录下的 config.php 文件中
     *
     * 【请求参数】
     * - name : 插件名称（必填）
     *
     * 【GET请求】
     * 读取插件信息和配置，渲染配置表单
     *
     * 【POST请求】
     * 保存插件配置到 config.php 文件
     *
     * 【配置类型支持】
     * - string   : 文本输入
     * - text     : 多行文本
     * - number   : 数字输入
     * - bool     : 是/否选择
     * - radio    : 单选
     * - checkbox : 多选
     * - select   : 下拉选择
     * - selects  : 多选下拉
     * - image    : 图片上传
     * - file     : 文件上传
     * - array    : 数组类型
     * - datetime : 日期时间
     *
     * @return mixed 渲染模板或JSON响应
     */
    public function config()
    {
        // ========== 第一步：获取并验证参数 ==========
        $param = input();
        $name = $param['name'];

        // 检查插件名称是否为空
        if(empty($name)){
            return $this->error(lang('param_err'));
        }

        // 检查插件目录是否存在
        // ADDON_PATH 常量定义了插件根目录，通常为 addons/
        if (!is_dir(ADDON_PATH . $name)) {
            return $this->error(lang('get_dir_err'));
        }

        // ========== 第二步：读取插件信息和配置 ==========
        // get_addon_info() 读取插件目录下的 info.ini 文件
        // 返回包含 name, title, intro, author, version, state 等信息的数组
        $info = get_addon_info($name);

        // get_addon_fullconfig() 读取插件目录下的 config.php 文件
        // 返回完整配置数组，每个配置项包含：
        // - name    : 配置项名称（作为表单字段名）
        // - title   : 配置项标题（显示给用户）
        // - type    : 输入类型（string/text/number/bool/radio/checkbox/select等）
        // - value   : 当前值
        // - content : 选项内容（用于select/radio/checkbox类型）
        // - rule    : 验证规则
        // - tip     : 提示说明
        $config = get_addon_fullconfig($name);

        // 验证插件信息是否成功读取
        if (!$info){
            return $this->error(lang('get_addon_info_err'));
        }

        // ========== 第三步：处理POST请求（保存配置） ==========
        if ($this->request->isPost()) {
            // 获取表单提交的配置数据
            // "row/a" 表示获取 row 数组参数
            // 表单字段格式：row[配置名] = 配置值
            $params = $this->request->post("row/a");

            if(empty($params)){
                return $this->error(lang('param_err'));
            }

            // 遍历原配置数组，更新配置值
            // 使用引用 &$v 以便直接修改原数组
            foreach ($config as $k => &$v) {
                // 检查提交的参数中是否包含此配置项
                if (isset($params[$v['name']])) {
                    // 根据配置类型处理值
                    if ($v['type'] == 'array') {
                        // array 类型：如果是数组直接使用，否则尝试JSON解码
                        // 用于处理键值对形式的配置，如：['key1'=>'value1', 'key2'=>'value2']
                        $params[$v['name']] = is_array($params[$v['name']]) ? $params[$v['name']] : (array)json_decode($params[$v['name']], true);
                        $value = $params[$v['name']];
                    } else {
                        // 其他类型：如果是数组则用逗号连接，否则直接使用
                        // checkbox 等多选类型会提交数组，需要转为逗号分隔的字符串
                        $value = is_array($params[$v['name']]) ? implode(',', $params[$v['name']]) : $params[$v['name']];
                    }
                    // 更新配置项的值
                    $v['value'] = $value;
                }
            }

            try {
                // ========== 第四步：保存配置到文件 ==========
                // set_addon_fullconfig() 将配置数组写入插件的 config.php 文件
                // 文件格式为返回数组的PHP文件：return [...];
                set_addon_fullconfig($name, $config);

                // 刷新插件服务缓存
                // 确保新配置立即生效
                Service::refresh();

                return $this->success(lang('save_ok'));
            } catch (Exception $e) {
                return $this->error($e->getMessage());
            }
        }

        // ========== 第五步：GET请求渲染配置页面 ==========
        // 将插件信息传递给模板（用于显示插件名称等）
        $this->assign('info',$info);
        // 将配置数组传递给模板（用于动态生成表单）
        $this->assign('config',$config);

        return $this->fetch('admin@addon/config');
    }

    /**
     * 插件详情（预留方法）
     */
    public function info()
    {

    }

    /**
     * ============================================================
     * 获取已下载的插件列表（JSON接口）
     * ============================================================
     *
     * 【功能说明】
     * 获取本地已安装的插件列表，返回JSON格式
     * 前端通过AJAX调用此接口渲染插件卡片
     *
     * 【请求参数】
     * - offset : 分页偏移量
     * - limit  : 每页数量
     * - filter : 筛选条件（JSON格式，如 category_id）
     * - search : 搜索关键词（插件名称或简介）
     *
     * 【返回数据】
     * JSON/JSONP格式：
     * {
     *     "total": 总数,
     *     "rows": [
     *         {
     *             "name": "插件名",
     *             "title": "插件标题",
     *             "intro": "简介",
     *             "author": "作者",
     *             "version": "版本",
     *             "state": 状态(0/1),
     *             "url": "访问地址",
     *             "install": "1",
     *             "createtime": 创建时间戳,
     *             ...
     *         }
     *     ]
     * }
     *
     * 【处理逻辑】
     * 1. 从缓存获取在线插件信息（用于合并显示）
     * 2. 读取本地 ADDON_PATH 目录下所有插件
     * 3. 合并在线信息和本地信息
     * 4. 根据筛选条件过滤
     * 5. 分页返回结果
     *
     * @return \think\response\Json|\think\response\Jsonp
     */
    public function downloaded()
    {
        // ========== 第一步：获取分页和筛选参数 ==========
        // offset : 分页偏移量（从第几条开始）
        // limit  : 每页显示数量
        $offset = (int)$this->request->get("offset");
        $limit = (int)$this->request->get("limit");

        // filter : 筛选条件（JSON格式字符串，如 {"category_id":1}）
        $filter = $this->request->get("filter");

        // search : 搜索关键词（用于搜索插件名称或简介）
        $search = $this->request->get("search");

        // 防XSS攻击：过滤搜索关键词中的HTML标签和特殊字符
        $search = htmlspecialchars(strip_tags($search));

        // ========== 第二步：获取在线插件信息（用于合并显示） ==========
        // 构建缓存键名：{站点缓存标识}_onlineaddons
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'onlineaddons';

        // 尝试从缓存获取在线插件列表
        $onlineaddons = Cache::get($key);

        // 如果缓存不存在或已过期，则从远程服务器获取
        if (!is_array($onlineaddons)) {
            $onlineaddons = [];

            // 请求 maccms 官方API获取在线插件列表
            // URL被拆分是为了避免被安全扫描误报
            // 实际URL: http://api.maccms.com/addon/index
            $response = mac_curl_get( "h"."t"."t"."p:/"."/a"."p"."i"."."."m"."a"."c"."c"."m"."s."."c"."o"."m"."/" . 'addon/index');

            // 解析JSON响应
            $json = !empty($response) ? json_decode($response, true) : [];

            // 将在线插件数据转换为以插件名为键的关联数组
            // 便于后续根据插件名快速查找合并
            if (!empty($json['rows'])) {
                foreach ($json['rows'] as $row) {
                    $onlineaddons[$row['name']] = $row;
                }
            }

            // 缓存在线插件数据，有效期600秒（10分钟）
            // 减少频繁请求远程服务器
            Cache::set($key, $onlineaddons, 600);
        }

        // ========== 第三步：获取本地已安装插件列表 ==========
        // 解析筛选条件JSON字符串为数组
        $filter = (array)json_decode($filter, true);

        // get_addon_list() 扫描 addons/ 目录获取所有已安装插件
        // 返回数组，每个元素包含插件的 info.ini 配置信息
        $addons = get_addon_list();

        $list = [];

        // ========== 第四步：遍历处理每个插件 ==========
        foreach ($addons as $k => $v) {
            // 搜索过滤：如果有搜索词，检查插件名和简介是否包含该词
            // stripos() 不区分大小写的查找
            if ($search && stripos($v['name'], $search) === FALSE && stripos($v['intro'], $search) === FALSE)
                continue;

            // 合并在线插件信息
            // 如果在线插件列表中存在该插件，合并在线信息（如图片、价格等）
            if (isset($onlineaddons[$v['name']])) {
                // array_merge: 在线信息在前，本地信息在后
                // 本地信息会覆盖在线信息中的同名字段（如version、state）
                $v = array_merge($onlineaddons[$v['name']], $v);
            } else {
                // 如果在线列表中没有该插件，设置默认值
                // 这些字段通常由在线API提供，本地插件可能没有
                if(!isset($v['category_id'])) {
                    $v['category_id'] = 0;       // 分类ID
                }
                if(!isset($v['flag'])) {
                    $v['flag'] = '';             // 标签/旗帜
                }
                if(!isset($v['banner'])) {
                    $v['banner'] = '';           // 横幅图片
                }
                if(!isset($v['image'])) {
                    $v['image'] = '';            // 插件图标/封面图
                }
                if(!isset($v['donateimage'])) {
                    $v['donateimage'] = '';      // 捐赠二维码图片
                }
                if(!isset($v['demourl'])) {
                    $v['demourl'] = '';          // 演示地址
                }
                if(!isset($v['price'])) {
                    $v['price'] = '0.00';        // 价格（免费）
                }
            }

            // 生成插件访问URL
            // addon_url() 根据插件名生成前台访问地址
            $v['url'] = addon_url($v['name']);

            // 获取插件目录的修改时间作为"创建时间"
            // filemtime() 返回文件最后修改时间的Unix时间戳
            $v['createtime'] = filemtime(ADDON_PATH . $v['name']);

            // 标记为已安装（用于前端判断显示"安装"还是"配置"按钮）
            $v['install'] = '1';

            // 分类筛选：如果指定了category_id，则过滤不匹配的插件
            if ($filter && isset($filter['category_id']) && is_numeric($filter['category_id']) && $filter['category_id'] != $v['category_id']) {
                continue;
            }

            // 添加到结果列表
            $list[] = $v;
        }

        // ========== 第五步：分页处理 ==========
        // 计算总数（分页前）
        $total = count($list);

        // 如果指定了每页数量，进行数组切片分页
        if ($limit) {
            // array_slice($list, $offset, $limit)
            // 从 $offset 位置开始，取 $limit 条数据
            $list = array_slice($list, $offset, $limit);
        }

        // 构建返回结果
        $result = array("total" => $total, "rows" => $list);

        // ========== 第六步：返回JSON/JSONP响应 ==========
        // 判断是否为JSONP请求（callback参数存在则为JSONP）
        // JSONP用于跨域请求，前端通过<script>标签加载
        $callback = $this->request->get('callback') ? "jsonp" : "json";

        // 返回JSON或JSONP格式数据
        // jsonp() 会自动包装为 callback({...}) 格式
        return $callback($result);
    }

    /**
     * ============================================================
     * 安装插件
     * ============================================================
     *
     * 【功能说明】
     * 安装指定的插件
     * 调用 Service::install() 执行安装流程
     *
     * 【请求参数】
     * - name      : 插件名称（必填）
     * - force     : 强制安装（覆盖已存在的插件）
     * - uid       : 用户ID（在线安装用）
     * - token     : 用户Token（在线安装用）
     * - version   : 插件版本
     * - faversion : 框架版本
     *
     * 【安装流程】
     * 1. 下载插件包（在线安装）或解压本地包
     * 2. 复制文件到插件目录
     * 3. 执行插件的 install() 方法
     * 4. 导入 install.sql（如果存在）
     * 5. 刷新插件缓存
     *
     * @return \think\response\Json 安装结果
     */
    public function install()
    {
        $param = input();
        $name = $param['name'];
        $force = (int)$param['force'];
        if (!$name) {
            return $this->error(lang('param_err'));
        }
        try {
            $uid = $this->request->post("uid");
            $token = $this->request->post("token");
            $version = $this->request->post("version");
            $faversion = $this->request->post("faversion");
            $extend = [
                'uid'       => $uid,
                'token'     => $token,
                'version'   => $version,
                'faversion' => $faversion
            ];
            Service::install($name, $force, $extend);
            $info = get_addon_info($name);
            $info['config'] = get_addon_config($name) ? 1 : 0;
            $info['state'] = 1;
            return $this->success(lang('install_err'));
        } catch (AddonException $e) {
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }

    /**
     * ============================================================
     * 卸载插件
     * ============================================================
     *
     * 【功能说明】
     * 卸载指定的插件
     * 调用 Service::uninstall() 执行卸载流程
     *
     * 【请求参数】
     * - name  : 插件名称（必填）
     * - force : 强制卸载（忽略依赖检查）
     *
     * 【卸载流程】
     * 1. 执行插件的 uninstall() 方法
     * 2. 删除插件目录
     * 3. 清理相关文件（控制器、模型等）
     * 4. 刷新插件缓存
     *
     * 【安全检查】
     * - 插件名不能包含 ".", "/", "\" 防止目录穿越攻击
     *
     * @return \think\response\Json 卸载结果
     */
    public function uninstall()
    {
        // ========== 第一步：获取并验证参数 ==========
        // input() 获取所有请求参数（GET/POST自动合并）
        $param = input();

        // 获取插件名称（英文标识，如 "example"）
        $name = $param['name'];

        // 获取强制卸载标志
        // force=0 : 正常卸载，如果有依赖会报错
        // force=1 : 强制卸载，忽略依赖关系
        $force = (int)$param['force'];

        // 验证插件名称不能为空
        if (!$name) {
            return $this->error(lang('param_err'));
        }

        try {
            // ========== 第二步：安全检查（防止目录穿越攻击） ==========
            // 检查插件名是否包含危险字符
            // "."  : 防止 "../" 形式的上级目录访问
            // "/"  : 防止绝对路径或跨目录访问（Linux）
            // "\\" : 防止绝对路径或跨目录访问（Windows）
            //
            // 【攻击场景示例】
            // 如果不检查，攻击者可能传入：
            // - name = "../application" → 删除应用目录
            // - name = "../../" → 删除上级目录
            // - name = "/etc" → 删除系统目录（Linux）
            if( strpos($name,".")!==false ||  strpos($name,"/")!==false ||  strpos($name,"\\")!==false  ) {
                $this->error(lang('admin/addon/path_err'));
                return;
            }

            // ========== 第三步：执行卸载操作 ==========
            // Service::uninstall() 是 think-addons 扩展提供的卸载方法
            // 位于 vendor/zzstudio/think-addons/src/addons/Service.php
            //
            // 【卸载流程详解】
            // 1. 检查插件是否存在（目录和info.ini文件）
            // 2. 检查插件状态（启用中的插件需要先禁用）
            // 3. 实例化插件主类，调用 uninstall() 钩子方法
            //    - 插件可以在此方法中执行清理操作
            //    - 如：删除自定义数据表、清理配置等
            // 4. 删除插件目录：addons/{name}/
            // 5. 删除复制到应用目录的文件（如果有的话）
            //    - 可能复制到 application/ 下的控制器、模型
            // 6. 刷新插件缓存
            //
            // 【force 参数作用】
            // - force=0 : 标准卸载，会检查是否有其他插件依赖此插件
            // - force=1 : 强制卸载，跳过依赖检查
            Service::uninstall($name, $force);

            // 返回卸载成功提示
            return $this->success(lang('uninstall_ok'));

        } catch (AddonException $e) {
            // ========== 异常处理：插件专用异常 ==========
            // AddonException 是 think-addons 定义的专用异常
            // 可携带额外数据，用于前端展示更详细的错误信息
            // getData() : 获取异常携带的数据
            // getCode() : 获取错误码
            // getMessage() : 获取错误信息
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            // ========== 异常处理：通用异常 ==========
            // 捕获所有其他异常，返回错误信息
            return $this->error($e->getMessage());
        }
    }

    /**
     * ============================================================
     * 启用/禁用插件
     * ============================================================
     *
     * 【功能说明】
     * 切换插件的启用/禁用状态
     * 调用 Service::enable() 或 Service::disable()
     *
     * 【请求参数】
     * - name   : 插件名称（必填）
     * - action : 操作类型（enable=启用, disable=禁用）
     * - force  : 强制执行（忽略依赖检查）
     *
     * 【状态说明】
     * - enable  : 启用插件，插件功能生效
     * - disable : 禁用插件，插件功能停用但保留文件
     *
     * 【注意事项】
     * - 操作后会清除菜单缓存
     * - 禁用的插件不会执行钩子
     *
     * @return \think\response\Json 操作结果
     */
    public function state()
    {
        // ========== 第一步：获取并验证参数 ==========
        // input() 获取所有请求参数
        $param = input();

        // 获取插件名称（英文标识）
        $name = $param['name'];

        // 获取操作类型
        // action = 'enable'  : 启用插件
        // action = 'disable' : 禁用插件
        $action = $param['action'];

        // 获取强制执行标志
        // force=0 : 正常操作，会检查依赖关系
        // force=1 : 强制操作，忽略依赖检查
        $force = (int)$param['force'];

        // 验证插件名称不能为空
        if (!$name) {
            return $this->error(lang('param_err'));
        }

        try {
            // ========== 第二步：规范化操作类型 ==========
            // 确保 action 只能是 'enable' 或 'disable'
            // 如果传入其他值，默认为 'disable'（安全优先）
            // 这是一种防御性编程，防止非法操作类型
            $action = $action == 'enable' ? $action : 'disable';

            // ========== 第三步：执行启用/禁用操作 ==========
            // 使用 PHP 的可变函数特性调用 Service 的静态方法
            // Service::$action($name, $force) 相当于：
            // - Service::enable($name, $force)  当 $action = 'enable'
            // - Service::disable($name, $force) 当 $action = 'disable'
            //
            // 【Service::enable() 启用流程】
            // 1. 检查插件目录是否存在
            // 2. 读取 info.ini 获取插件信息
            // 3. 将 state 值设置为 1
            // 4. 写回 info.ini 文件
            // 5. 刷新插件缓存，使钩子生效
            //
            // 【Service::disable() 禁用流程】
            // 1. 检查插件目录是否存在
            // 2. 读取 info.ini 获取插件信息
            // 3. 将 state 值设置为 0
            // 4. 写回 info.ini 文件
            // 5. 刷新插件缓存，停止钩子执行
            //
            // 【state 状态在 info.ini 中的格式】
            // state = 1  ; 启用
            // state = 0  ; 禁用
            //
            // 【force 参数说明】
            // - force=0 : 检查依赖，如果有其他插件依赖此插件则不允许禁用
            // - force=1 : 忽略依赖，强制执行操作
            Service::$action($name, $force);

            // ========== 第四步：清除菜单缓存 ==========
            // 插件可能会注册后台菜单项
            // 启用/禁用插件后需要清除菜单缓存
            // 确保下次加载时重新生成菜单（包含或排除该插件的菜单项）
            //
            // '__menu__' 是菜单缓存的键名
            // Cache::rm() 删除指定的缓存项
            Cache::rm('__menu__');

            // 返回操作成功提示
            return $this->success(lang('opt_ok'));

        } catch (AddonException $e) {
            // ========== 异常处理：插件专用异常 ==========
            // AddonException 可能包含额外数据（如冲突的依赖列表）
            // 使用 result() 方法返回完整的异常信息
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            // ========== 异常处理：通用异常 ==========
            // 捕获其他异常，返回错误消息
            return $this->error($e->getMessage());
        }
    }

    /**
     * ============================================================
     * 本地上传安装插件
     * ============================================================
     *
     * 【功能说明】
     * 通过上传ZIP压缩包安装插件
     * 目前此功能已关闭（echo 'closed'; exit;）
     *
     * 【请求参数】
     * - file : 上传的ZIP文件
     *
     * 【安装流程】
     * 1. 验证Token防止CSRF攻击
     * 2. 上传ZIP文件到临时目录
     * 3. 解压到插件目录
     * 4. 读取 info.ini 获取插件信息
     * 5. 重命名插件目录
     * 6. 执行插件的 install() 方法
     * 7. 导入 install.sql
     *
     * 【文件限制】
     * - 最大文件大小：10MB
     * - 文件类型：zip
     *
     * @return \think\response\Json 安装结果
     */
    public function local()
    {
        $param = input();
        $validate = \think\Loader::validate('Token');
        if(!$validate->check($param)){
            return $this->error($validate->getError());
        }
        echo 'closed';exit;
        $file = $this->request->file('file');
        $addonTmpDir = RUNTIME_PATH . 'addons' . DS;
        if (!is_dir($addonTmpDir)) {
            @mkdir($addonTmpDir, 0755, true);
        }
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'zip'])->move($addonTmpDir);
        if ($info) {
            $tmpName = substr($info->getFilename(), 0, stripos($info->getFilename(), '.'));
            $tmpAddonDir = ADDON_PATH . $tmpName . DS;
            $tmpFile = $addonTmpDir . $info->getSaveName();
            try {
                Service::unzip($tmpName);
                @unlink($tmpFile);
                $infoFile = $tmpAddonDir . 'info.ini';
                if (!is_file($infoFile)) {
                    throw new Exception(lang('admin/addon/lack_config_err'));
                }

                $config = Config::parse($infoFile, '', $tmpName);
                $name = isset($config['name']) ? $config['name'] : '';
                if (!$name) {
                    throw new Exception(lang('admin/addon/name_empty_err'));
                }

                $newAddonDir = ADDON_PATH . $name . DS;
                if (is_dir($newAddonDir)) {
                    throw new Exception(lang('admin/addon/haved_err'));
                }

                //重命名插件文件夹
                rename($tmpAddonDir, $newAddonDir);
                try {
                    //默认禁用该插件
                    $info = get_addon_info($name);
                    if ($info['state']) {
                        $info['state'] = 0;
                        set_addon_info($name, $info);
                    }

                    //执行插件的安装方法
                    $class = get_addon_class($name);
                    if (class_exists($class)) {
                        $addon = new $class();
                        $addon->install();
                    }

                    //导入SQL
                    Service::importsql($name);

                    $info['config'] = get_addon_config($name) ? 1 : 0;
                    return $this->success(lang('install_ok'));
                } catch (Exception $e) {
                    if (Dir::delDir($newAddonDir) === false) {

                    }
                    throw new Exception($e->getMessage());
                }
            } catch (Exception $e) {
                @unlink($tmpFile);
                if (Dir::delDir($tmpAddonDir) === false) {

                }
                return $this->error($e->getMessage());
            }
        } else {
            // 上传失败获取错误信息
            return $this->error($file->getError());
        }
    }

    /**
     * 添加插件页面
     *
     * @return mixed 渲染模板
     */
    public function add()
    {
        return $this->fetch('admin@addon/add');
    }

    /**
     * ============================================================
     * 更新插件
     * ============================================================
     *
     * 【功能说明】
     * 更新已安装的插件到新版本
     * 调用 Service::upgrade() 执行更新流程
     *
     * 【请求参数】
     * - name      : 插件名称（必填）
     * - uid       : 用户ID（在线更新用）
     * - token     : 用户Token（在线更新用）
     * - version   : 目标版本
     * - faversion : 框架版本
     *
     * 【更新流程】
     * 1. 备份当前插件
     * 2. 下载新版本
     * 3. 覆盖更新文件
     * 4. 执行插件的 upgrade() 方法
     * 5. 执行增量SQL（如果有）
     * 6. 清除菜单缓存
     *
     * @return \think\response\Json 更新结果
     */
    public function upgrade()
    {
        $name = $this->request->post("name");
        if (!$name) {
            return $this->error(lang('param_err'));
        }
        try {
            $uid = $this->request->post("uid");
            $token = $this->request->post("token");
            $version = $this->request->post("version");
            $faversion = $this->request->post("faversion");
            $extend = [
                'uid'       => $uid,
                'token'     => $token,
                'version'   => $version,
                'faversion' => $faversion
            ];
            //调用更新的方法
            Service::upgrade($name, $extend);
            Cache::rm('__menu__');
            return $this->success(lang('update_ok'));
        } catch (AddonException $e) {
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

}
