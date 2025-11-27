<?php
/**
 * ============================================================
 * 验证码控制器 (Captcha Controller)
 * ============================================================
 *
 * 【文件说明】
 * 生成和验证图形验证码，用于登录、注册、评论等场景
 * 基于 ThinkPHP 官方 think-captcha 扩展包
 *
 * 【调用位置】
 * - 后台登录: admin/view/index/login.html → <img src="__ROOT__/index.php/verify/index.html">
 * - 前台登录: index/view/user/login.html
 * - 评论提交: index/controller/Comment.php
 * - 留言板:   index/controller/Gbook.php
 *
 * 【验证码生成流程】
 *
 * ┌────────────────────────────────────────────────────────────────┐
 * │  1. 请求: GET /index.php/verify/index.html                      │
 * ├────────────────────────────────────────────────────────────────┤
 * │  2. 路由解析: index 模块 / Verify 控制器 / index 方法            │
 * ├────────────────────────────────────────────────────────────────┤
 * │  3. 加载配置: Config::get('captcha')                            │
 * │     配置文件: application/extra/captcha.php                     │
 * │     - codeSet  : '1234567890'  字符集(只用数字)                   │
 * │     - length   : 4             验证码长度                        │
 * │     - fontSize : 16            字体大小                          │
 * │     - useNoise : false         是否添加杂点                       │
 * │     - useCurve : false         是否添加干扰线                     │
 * ├────────────────────────────────────────────────────────────────┤
 * │  4. 生成验证码: Captcha->entry($id)                              │
 * │     - 随机生成验证码字符                                          │
 * │     - 绘制验证码图片(GD库)                                        │
 * │     - 加密验证码存入 Session                                      │
 * │       Session Key: md5(seKey) + $id                             │
 * │       Session Value: {verify_code: 加密值, verify_time: 时间戳}  │
 * │     - 输出 PNG 图片流                                            │
 * └────────────────────────────────────────────────────────────────┘
 *
 * 【验证码校验流程】
 *
 * ┌────────────────────────────────────────────────────────────────┐
 * │  1. 用户提交表单，包含 verify 字段                                │
 * ├────────────────────────────────────────────────────────────────┤
 * │  2. 后端调用 captcha_check($verify)                              │
 * │     位置: vendor/topthink/think-captcha/src/helper.php          │
 * ├────────────────────────────────────────────────────────────────┤
 * │  3. Captcha->check($code, $id)                                   │
 * │     - 从 Session 读取存储的验证码                                 │
 * │     - 检查验证码是否过期 (默认1800秒)                             │
 * │     - 将用户输入转大写后加密                                      │
 * │     - 比对加密值是否匹配                                          │
 * │     - 验证成功后删除 Session (防止重复使用)                        │
 * └────────────────────────────────────────────────────────────────┘
 *
 * 【配置文件】
 * - application/extra/captcha.php         : 验证码配置
 * - vendor/topthink/think-captcha/        : 验证码扩展包
 *   - src/Captcha.php                     : 核心类(生成/校验)
 *   - src/helper.php                      : 辅助函数
 *   - assets/ttfs/                        : 字体文件
 *
 * 【安全说明】
 * - 验证码存储在服务端 Session，客户端无法获取真实值
 * - 验证码有过期时间限制 (默认30分钟)
 * - 验证成功后自动销毁，防止重放攻击
 * - 验证码字符经过 MD5 加密存储
 *
 * ============================================================
 */

namespace app\index\controller;

use think\captcha\Captcha;
use think\Config;
use think\Controller;

class Verify extends Controller
{
    /**
     * 构造函数
     * 继承父类构造函数，初始化控制器
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * ============================================================
     * 生成验证码图片
     * ============================================================
     *
     * 【功能说明】
     * 根据配置生成验证码图片并输出
     *
     * 【请求方式】
     * GET /index.php/verify/index.html
     * GET /index.php/verify/index/id/xxx.html  (带ID的验证码)
     *
     * 【执行流程】
     * 1. ob_end_clean() - 清空输出缓冲区，防止图片输出前有其他内容
     * 2. 加载验证码配置 (application/extra/captcha.php)
     * 3. 创建 Captcha 实例
     * 4. 调用 entry() 生成并输出验证码图片
     *
     * 【Captcha->entry() 内部流程】
     * 1. 计算图片尺寸 (根据字体大小和长度)
     * 2. 创建 GD 图片资源
     * 3. 设置背景色
     * 4. 随机生成验证码字符
     * 5. 绘制杂点 (如果 useNoise=true)
     * 6. 绘制干扰线 (如果 useCurve=true)
     * 7. 绘制验证码文字
     * 8. 加密验证码存入 Session:
     *    - Key: MD5(seKey) + $id
     *    - Value: {verify_code: 加密后的验证码, verify_time: 生成时间}
     * 9. 输出 PNG 图片流
     *
     * @param string $id 验证码标识 (可选)
     *                   用于区分不同场景的验证码
     *                   - 空字符串: 默认验证码
     *                   - 'admin': 后台专用验证码
     *                   - 'user': 前台用户验证码
     *                   不同 $id 的验证码互不影响
     *
     * @return \think\Response PNG 图片响应
     *
     * 【配置项说明】
     * codeSet    : 验证码字符集 (默认: '1234567890')
     * length     : 验证码位数 (默认: 4)
     * fontSize   : 字体大小 (默认: 16)
     * useNoise   : 是否添加杂点 (默认: false)
     * useCurve   : 是否添加干扰线 (默认: false)
     * expire     : 过期时间/秒 (默认: 1800)
     * secure     : 安全模式 (默认: false)
     */
    public function index($id='')
    {
        // 清空输出缓冲区
        // 防止验证码图片前有其他内容输出导致图片损坏
        // 例如: BOM头、空格、错误信息等都会导致图片无法显示
        ob_end_clean();

        // 创建验证码实例
        // Config::get('captcha') 读取 application/extra/captcha.php 配置
        // 配置会覆盖 Captcha 类的默认配置
        $captcha = new Captcha((array)Config::get('captcha'));

        // 生成并返回验证码图片
        // entry() 会同时:
        // 1. 生成随机验证码
        // 2. 绘制验证码图片
        // 3. 将加密后的验证码存入 Session
        // 4. 返回 PNG 图片响应
        return $captcha->entry($id);
    }

    /**
     * ============================================================
     * 验证验证码 (API方式)
     * ============================================================
     *
     * 【功能说明】
     * 通过 API 方式检查验证码是否正确
     * 一般用于 AJAX 预校验，在表单提交前先验证
     *
     * 【请求方式】
     * GET /index.php/verify/check/verify/1234.html
     *
     * 【注意事项】
     * 1. 此方法会消耗验证码 (验证成功后 Session 中的验证码会被删除)
     * 2. 一般不推荐使用此 API，因为会暴露验证结果
     * 3. 推荐在表单提交时直接调用 captcha_check() 函数验证
     *
     * 【使用场景】
     * - AJAX 预验证 (用户输入完验证码后立即检查)
     * - 客户端验证后再提交表单
     *
     * @param string $verify 用户输入的验证码
     * @param string $id     验证码标识 (可选，需与生成时的 $id 一致)
     *
     * @return int 验证结果
     *             - 0: 验证失败
     *             - 1: 验证成功
     *
     * 【captcha_check() 函数流程】
     * 位置: vendor/topthink/think-captcha/src/helper.php:59
     *
     * 1. 创建 Captcha 实例
     * 2. 调用 Captcha->check($code, $id)
     * 3. 从 Session 获取存储的验证码数据
     * 4. 检查验证码是否为空
     * 5. 检查验证码是否过期 (默认1800秒)
     * 6. 将用户输入转为大写并加密
     * 7. 比对加密值是否匹配
     * 8. 验证成功: 删除 Session 中的验证码，返回 true
     * 9. 验证失败: 返回 false
     */
    public function check($verify,$id='')
    {
        // captcha_check() 是 ThinkPHP 验证码扩展包提供的辅助函数
        // 位置: vendor/topthink/think-captcha/src/helper.php
        //
        // 函数定义:
        // function captcha_check($value, $id = "", $config = []) {
        //     $captcha = new \think\captcha\Captcha($config);
        //     return $captcha->check($value, $id);
        // }
        //
        // Captcha->check() 验证流程:
        // 1. 生成 Session Key: authcode(seKey) + $id
        // 2. 从 Session 获取存储的验证码数据
        // 3. 验证码为空 → 返回 false
        // 4. 验证码过期 (time() - verify_time > expire) → 删除 Session，返回 false
        // 5. 加密用户输入: authcode(strtoupper($code))
        // 6. 比对加密值是否等于 Session 中的 verify_code
        // 7. 匹配成功 → 删除 Session (reset=true时)，返回 true
        // 8. 匹配失败 → 返回 false
        if(!captcha_check($verify)){
            return 0;   // 验证失败
        }
        else{
            return 1;   // 验证成功
        }
    }

}