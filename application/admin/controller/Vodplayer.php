<?php
/**
 * 视频播放器管理控制器 (VodPlayer Management Controller)
 * ============================================================
 *
 * 【文件说明】
 * 后台 "视频 → 播放器管理" 功能的控制器
 * 管理视频播放器配置，包括播放器代码、解析接口等
 *
 * 【访问路径】
 * admin.php/vodplayer/index   → 播放器列表
 * admin.php/vodplayer/info    → 添加/编辑播放器
 * admin.php/vodplayer/del     → 删除播放器
 * admin.php/vodplayer/field   → 批量修改字段
 * admin.php/vodplayer/export  → 导出播放器配置
 * admin.php/vodplayer/import  → 导入播放器配置
 *
 * 【方法列表】
 * ┌──────────────┬──────────────────────────────────────┐
 * │ 方法名        │ 功能说明                              │
 * ├──────────────┼──────────────────────────────────────┤
 * │ index()      │ 播放器列表页面                        │
 * │ info()       │ 播放器添加/编辑页面                    │
 * │ del()        │ 删除播放器                            │
 * │ field()      │ 批量修改状态/解析状态                  │
 * │ export()     │ 导出播放器配置为txt文件                │
 * │ import()     │ 从txt文件导入播放器配置                │
 * └──────────────┴──────────────────────────────────────┘
 *
 * 【数据存储说明】
 * - 配置数据: application/extra/vodplayer.php
 * - 播放器JS代码: static/player/{from}.js
 *
 * 【配置字段说明】
 * ┌──────────────┬─────────────────────────────────────────┐
 * │ 字段名        │ 说明                                     │
 * ├──────────────┼─────────────────────────────────────────┤
 * │ from         │ 播放器编码 (唯一标识，需与服务器组匹配)     │
 * │ show         │ 显示名称 (前台展示)                       │
 * │ des          │ 描述/备注                                │
 * │ tip          │ 提示信息 (鼠标悬停显示)                   │
 * │ sort         │ 排序值 (数字越大越靠前)                   │
 * │ status       │ 状态: 0=禁用, 1=启用                     │
 * │ ps           │ 解析状态: 0=不解析, 1=启用解析            │
 * │ parse        │ 独立解析接口URL (ps=1时使用)              │
 * │ target       │ 打开方式: _self=当前窗口, _blank=新窗口   │
 * └──────────────┴─────────────────────────────────────────┘
 *
 * 【与服务器组的关系】
 * - 播放器的 "from" 编码需与服务器组 (vodserver) 的 "from" 匹配
 * - 视频播放时，根据播放地址的服务器组编码选择对应播放器
 * - 播放地址格式: "服务器组编码$播放URL"
 *
 * 【解析功能说明】
 * - ps=0: 直接使用播放器JS代码播放
 * - ps=1: 先通过解析接口获取真实播放地址，再播放
 * - parse字段: 独立解析接口，优先于全局解析接口
 *
 * @package     app\admin\controller
 * @author      MacCMS
 * @version     1.0
 */
namespace app\admin\controller;
use think\Db;

class VodPlayer extends Base
{
    /**
     * @var string 配置文件名前缀，对应 extra/vodplayer.php
     */
    var $_pre;

    /**
     * 构造函数
     * 初始化配置文件名前缀
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pre = 'vodplayer';
    }

    /**
     * 播放器列表页面
     *
     * 【功能说明】
     * 显示所有播放器配置列表
     * 支持批量修改状态和解析状态
     *
     * 【模板变量】
     * - list  : 播放器列表数据
     * - title : 页面标题
     *
     * @return mixed 渲染后的列表页面
     */
    public function index()
    {
        $list = config($this->_pre);
        $this->assign('list',$list);
        $this->assign('title',lang('admin/vodplayer/title'));
        return $this->fetch('admin@vodplayer/index');
    }

    /**
     * 播放器添加/编辑页面
     *
     * 【功能说明】
     * GET请求: 显示编辑表单，加载播放器JS代码
     * POST请求: 保存播放器配置和JS代码文件
     *
     * 【请求参数】
     * - id     : 播放器编码 (GET，编辑时传入)
     * - from   : 播放器编码 (POST，唯一标识)
     * - show   : 显示名称
     * - des    : 描述信息
     * - tip    : 提示文字
     * - sort   : 排序值
     * - status : 状态
     * - ps     : 解析状态
     * - parse  : 独立解析接口URL
     * - target : 打开方式
     * - code   : 播放器JS代码
     *
     * 【保存逻辑】
     * 1. 验证Token
     * 2. 编码格式检查 (禁止特殊字符)
     * 3. 更新配置数组并按sort排序
     * 4. 写入配置文件 extra/vodplayer.php
     * 5. 写入JS代码到 static/player/{from}.js
     * 6. 更新缓存标记
     *
     * @return mixed 渲染后的编辑页面或操作结果
     */
    public function info()
    {
        $param = input();
        $list = config($this->_pre);
        if (Request()->isPost()) {
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            unset($param['__token__']);
            unset($param['flag']);
            $code = $param['code'];
            unset($param['code']);
            if(is_numeric($param['from'])){
                $param['from'] .='_';
            }
            if (strpos($param['from'], '.') !== false || strpos($param['from'], '/') !== false || strpos($param['from'], '\\') !== false) {
                $this->error(lang('param_err'));
                return;
            }
            $list[$param['from']] = $param;
            $sort=[];
            foreach ($list as $k=>&$v){
                $sort[] = $v['sort'];
            }
            array_multisort($sort, SORT_DESC, SORT_FLAG_CASE , $list);

            $res = mac_arr2file( APP_PATH .'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->error(lang('write_err_config'));
            }

            // 写入播放器JS代码文件，同时支持 static 和 static_new 两个目录
            $jsDirs = ['./static/player/', './static_new/player/'];
            $writeSuccess = false;
            foreach ($jsDirs as $jsDir) {
                if (!is_dir($jsDir)) {
                    @mkdir($jsDir, 0755, true);
                }
                $jsFile = $jsDir . $param['from'] . '.js';
                $fp = @fopen($jsFile, 'wb');
                if ($fp !== false) {
                    fwrite($fp, $code);
                    fclose($fp);
                    $writeSuccess = true;
                }
            }
            if (!$writeSuccess) {
                return $this->error(lang('wirte_err_codefile'));
            }

            cache('cache_data','1');
            return $this->success(lang('save_ok'));
        }

        $info = $list[$param['id']];
        if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }
        $this->assign('info',$info);
        $this->assign('title',lang('admin/vodplayer/title'));
        return $this->fetch('admin@vodplayer/info');
    }

    /**
     * 删除播放器
     *
     * 【功能说明】
     * 从配置文件中删除指定的播放器配置
     * 注意: 不会删除对应的JS代码文件
     *
     * 【请求参数】
     * - ids : 播放器编码 (from值)
     *
     * 【注意事项】
     * 删除播放器后，使用该编码的视频将无法正常播放
     *
     * @return \think\response\Json 操作结果
     */
    public function del()
    {
        $param = input();
        $list = config($this->_pre);
        unset($list[$param['ids']]);
        $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
        if($res===false){
            return $this->error(lang('del_err'));
        }
        cache('cache_data','1');
        return $this->success(lang('del_ok'));
    }

    /**
     * 批量修改播放器字段
     *
     * 【功能说明】
     * 批量修改选中播放器的指定字段值
     * 支持修改 status(状态)、ps(解析状态)、sort(排序) 字段
     *
     * 【请求参数】
     * - ids : 播放器编码列表 (逗号分隔)
     * - col : 字段名 (status/ps/sort)
     * - val : 字段值
     *
     * 【使用场景】
     * - 批量启用/禁用播放器
     * - 批量开启/关闭解析功能
     * - 快速修改排序值
     *
     * @return \think\response\Json 操作结果
     */
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['ps','status','sort'])){
            $list = config($this->_pre);
            $ids = explode(',',$ids);
            foreach($list as $k=>&$v){
                if(in_array($k,$ids)){
                    $v[$col] = $val;
                }
            }

            // 如果修改的是排序字段，需要重新排序
            if($col == 'sort'){
                $sort = [];
                foreach ($list as $k => &$v) {
                    $sort[] = $v['sort'];
                }
                array_multisort($sort, SORT_DESC, SORT_FLAG_CASE, $list);
            }

            $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 导出播放器配置
     *
     * 【功能说明】
     * 将播放器配置导出为txt文件下载
     * 导出内容包括配置信息和JS代码
     * 数据格式: base64编码的JSON字符串
     *
     * 【请求参数】
     * - id : 播放器编码
     *
     * 【导出格式】
     * 文件名: mac_{from}.txt
     * 内容: base64_encode(json_encode($info))
     *
     * 【使用场景】
     * - 备份播放器配置
     * - 在不同站点间迁移播放器
     *
     * @return void 直接输出文件下载
     */
    public function export()
    {
        $param = input();
        $list = config($this->_pre);
        $info = $list[$param['id']];
        if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }

        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
            header("Content-Disposition: attachment; filename=mac_" . urlencode($info['from']) . '.txt');
        }
        else{
            header("Content-Disposition: attachment; filename=mac_" . $info['from'] . '.txt');
        }
        echo base64_encode(json_encode($info));
    }

    /**
     * 导入播放器配置
     *
     * 【功能说明】
     * GET请求: 显示导入页面
     * POST请求: 上传txt文件并导入播放器配置
     *
     * 【导入格式】
     * - 文件类型: txt
     * - 文件大小: 最大10MB
     * - 数据格式: base64编码的JSON字符串 (由export方法导出)
     *
     * 【导入逻辑】
     * 1. 验证Token和文件
     * 2. 读取并解码文件内容 (base64 → JSON)
     * 3. 验证必要字段 (status, from, sort)
     * 4. 安全检查编码格式
     * 5. 更新配置文件
     * 6. 写入JS代码文件
     * 7. 删除临时上传文件
     *
     * 【注意事项】
     * - 同名播放器将被覆盖
     * - 编码不能包含特殊字符 (. / \)
     *
     * @return mixed 导入页面或操作结果
     */
    public function import()
    {
        if (request()->isPost()) {
            $param = input();
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            unset($param['__token__']);
            $file = $this->request->file('file');
            $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
            if ($info) {
                $data = json_decode(base64_decode(file_get_contents($info->getpathName())), true);
                @unlink($info->getpathName());
                if ($data) {
                    if (empty($data['status']) || empty($data['from']) || empty($data['sort'])) {
                        return $this->error(lang('format_err'));
                    }
                    if (strpos($data['from'], '.') !== false || strpos($data['from'], '/') !== false || strpos($data['from'], '\\') !== false) {
                        $this->error(lang('param_err'));
                        return;
                    }
                    $code = $data['code'];
                    unset($data['code']);

                    $list = config($this->_pre);
                    $list[$data['from']] = $data;
                    $res = mac_arr2file(APP_PATH . 'extra/' . $this->_pre . '.php', $list);
                    if ($res === false) {
                        return $this->error(lang('write_err_config'));
                    }

                    $res = fwrite(fopen('./static/player/' . $data['from'] . '.js', 'wb'), $code);
                    if ($res === false) {
                        return $this->error(lang('wirte_err_codefile'));
                    }
                }
                return $this->success(lang('import_ok'));
            } else {
                return $this->error($file->getError());
            }
        }
        else{
            return $this->fetch('admin@vodplayer/import');
        }
    }

}
