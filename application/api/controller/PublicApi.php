<?php
/**
 * 公开API认证Trait (Public API Authentication Trait)
 * ============================================================
 *
 * 【文件说明】
 * 提供公开数据API的认证和安全防护功能
 * 通过 Trait 方式被各个数据查询API控制器使用
 *
 * 【使用方式】
 * class Vod extends Base {
 *     use PublicApi;  // 引入认证Trait
 *
 *     public function get_list() {
 *         $this->check_config();  // 调用认证检查
 *         // ... 业务逻辑
 *     }
 * }
 *
 * 【引用此Trait的控制器】
 * - Vod.php      : 视频数据API
 * - Art.php      : 文章数据API
 * - Actor.php    : 演员数据API
 * - User.php     : 用户数据API
 * - Comment.php  : 评论数据API
 * - Topic.php    : 专题数据API
 * - Type.php     : 分类数据API
 * - Gbook.php    : 留言板API
 * - Link.php     : 链接API
 * - Website.php  : 网站API
 * - Manga.php    : 漫画API
 *
 * 【配置位置】
 * application/extra/maccms.php → api.publicapi
 *
 * 【配置项说明】
 * ┌────────────┬──────────────────────────────────────────┐
 * │ 配置项      │ 说明                                      │
 * ├────────────┼──────────────────────────────────────────┤
 * │ status     │ API开关: 0=关闭, 1=开启                   │
 * │ charge     │ 收费模式: 0=免费开放, 1=需要IP认证         │
 * │ auth       │ 授权IP/域名列表 (多个用#分隔)              │
 * │ pagesize   │ 默认分页大小                              │
 * │ cachetime  │ 缓存时间(秒)                              │
 * └────────────┴──────────────────────────────────────────┘
 *
 * 【认证流程】
 * 1. 检查 status 开关，关闭则返回 'closed'
 * 2. 若 charge=1 (收费模式)，验证请求IP
 * 3. IP验证：检查是否在 auth 白名单中
 * 4. 域名自动DNS解析为IP后加入白名单
 *
 * 【安全功能】
 * - IP/域名白名单认证
 * - SQL注入关键字过滤
 * - 特殊字符过滤
 *
 * ============================================================
 */

namespace app\api\controller;

trait PublicApi
{
    /**
     * ============================================================
     * 检查API配置和认证
     * ============================================================
     *
     * 【功能说明】
     * 公开API的入口认证检查，在每个API方法开始时调用
     * 验证API是否开启，以及请求者是否有权限访问
     *
     * 【认证流程】
     * ┌─────────────────────────────────────────────────────────┐
     * │ 1. 检查 publicapi.status                                │
     * │    ├─ status != 1 → 输出 'closed' 并终止               │
     * │    └─ status == 1 → 继续                               │
     * │                                                         │
     * │ 2. 检查 publicapi.charge (收费模式)                     │
     * │    ├─ charge == 0 → 免费模式，直接通过                  │
     * │    └─ charge == 1 → 需要IP认证                         │
     * │        ├─ 无法获取IP → 输出认证错误                     │
     * │        └─ 有IP → 调用 checkDomainAuth() 验证            │
     * └─────────────────────────────────────────────────────────┘
     *
     * 【配置示例】
     * 'publicapi' => [
     *     'status'  => '1',                    // 开启API
     *     'charge'  => '1',                    // 收费模式
     *     'auth'    => '192.168.1.100#example.com',  // IP和域名
     * ]
     *
     * @return void 验证失败时直接 die 终止
     */
    public function check_config()
    {
        // ========== 第一步：检查API开关 ==========
        // status: 0=关闭, 1=开启
        if ($GLOBALS['config']['api']['publicapi']['status'] != 1) {
            echo 'closed';
            die;
        }

        // ========== 第二步：检查收费模式 ==========
        // charge: 0=免费开放, 1=需要IP认证
        if ($GLOBALS['config']['api']['publicapi']['charge'] == 1) {
            // 获取请求者IP
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                // 无法获取IP，拒绝访问
                echo lang('api/auth_err');
                exit;
            } else {
                // 获取授权列表并验证
                $auth = $GLOBALS['config']['api']['publicapi']['auth'];
                $this->checkDomainAuth($auth);
            }
        }
    }

    /**
     * ============================================================
     * 验证IP/域名白名单
     * ============================================================
     *
     * 【功能说明】
     * 检查请求IP是否在授权白名单中
     * 支持IP地址和域名两种授权方式
     * 域名会自动DNS解析为IP后加入白名单
     *
     * 【白名单构建流程】
     * 1. 初始化白名单，默认包含 127.0.0.1 (本机)
     * 2. 解析 auth 配置 (用#分隔的列表)
     * 3. 每个条目判断:
     *    - 是IP地址 → 直接加入白名单
     *    - 是域名 → 加入域名 + DNS解析后的IP
     * 4. 去重和过滤空值
     * 5. 检查请求IP是否在白名单中
     *
     * 【配置格式】
     * auth = "192.168.1.100#10.0.0.1#example.com#api.test.com"
     *       └── 多个IP/域名用 # 分隔
     *
     * 【示例】
     * auth = "192.168.1.100#example.com"
     * 白名单 = ['127.0.0.1', '192.168.1.100', 'example.com', '93.184.216.34']
     *                                                         └── DNS解析结果
     *
     * @param string $auth 授权IP/域名列表，用#分隔
     * @return void 验证失败时 exit 终止
     */
    private function checkDomainAuth($auth)
    {
        // 获取请求者的真实IP
        $ip = mac_get_client_ip();

        // 初始化白名单，默认允许本机访问
        $auth_list = ['127.0.0.1'];

        // 解析授权配置
        if (!empty($auth)) {
            // 按#分隔，遍历每个授权条目
            foreach (explode('#', $auth) as $domain) {
                $domain = trim($domain);

                // 将原始值加入白名单 (可能是IP或域名)
                $auth_list[] = $domain;

                // 如果是域名（非IP），进行DNS解析
                // mac_string_is_ip() 判断是否为IP格式
                if (!mac_string_is_ip($domain)) {
                    // gethostbyname() 将域名解析为IP
                    // 解析失败时返回原域名
                    $auth_list[] = gethostbyname($domain);
                }
            }

            // 去除重复值
            $auth_list = array_unique($auth_list);
            // 过滤空值
            $auth_list = array_filter($auth_list);
        }

        // 验证请求IP是否在白名单中
        if (!in_array($ip, $auth_list)) {
            // 不在白名单，输出认证错误并终止
            echo lang('api/auth_err');
            exit;
        }
    }

    /**
     * ============================================================
     * SQL字符串安全过滤
     * ============================================================
     *
     * 【功能说明】
     * 过滤SQL注入关键字和特殊字符
     * 用于处理用户输入的排序字段、筛选条件等
     *
     * 【过滤规则】
     * 1. 移除SQL关键字 (大小写不敏感):
     *    SELECT, INSERT, UPDATE, DELETE, DROP, UNION,
     *    WHERE, FROM, JOIN, INTO, VALUES, SET,
     *    AND, OR, NOT, EXISTS, HAVING,
     *    GROUP BY, ORDER BY, LIMIT, OFFSET
     *
     * 2. 移除特殊字符，只保留:
     *    - \w : 字母、数字、下划线
     *    - \s : 空白字符
     *    - \- : 连字符
     *    - \. : 点号
     *
     * 3. 规范化空白:
     *    - 多个连续空白合并为单个空格
     *    - 去除首尾空白
     *
     * 【使用示例】
     * $orderby = $this->format_sql_string($param['orderby']);
     * // 输入: "vod_time DESC; DROP TABLE--"
     * // 输出: "vod_time"
     *
     * 【安全说明】
     * 此方法提供基础的SQL注入防护，但不能完全替代参数绑定
     * 建议配合 ThinkPHP 的查询构造器使用
     *
     * @param string $str 需要过滤的字符串
     * @return string 过滤后的安全字符串
     */
    protected function format_sql_string($str)
    {
        // 第一步：移除SQL关键字
        // \b 表示单词边界，确保只匹配完整单词
        // i 修饰符表示大小写不敏感
        $str = preg_replace('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|WHERE|FROM|JOIN|INTO|VALUES|SET|AND|OR|NOT|EXISTS|HAVING|GROUP BY|ORDER BY|LIMIT|OFFSET)\b/i', '', $str);

        // 第二步：移除特殊字符
        // 只保留: 字母数字下划线(\w)、空白(\s)、连字符(-)、点号(.)
        $str = preg_replace('/[^\w\s\-\.]/', '', $str);

        // 第三步：规范化空白
        // 多个空白合并为单个空格，并去除首尾空白
        $str = trim(preg_replace('/\s+/', ' ', $str));

        return $str;
    }
}