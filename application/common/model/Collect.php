<?php
/**
 * 资源站采集模型 (Resource Site Collection Model)
 * ============================================================
 *
 * 【功能说明】
 * 核心采集业务逻辑，从外部资源站API获取数据并入库到本地数据库
 * 支持视频/文章/演员/角色/网站/漫画六种数据类型的采集
 * 支持 XML 和 JSON 两种接口格式
 *
 * 【数据表】
 * mac_collect - 采集源配置表
 *
 * 【核心方法分类】
 *
 * 基础CRUD:
 * - listData()      : 获取采集源列表
 * - infoData()      : 获取单个采集源信息
 * - saveData()      : 保存采集源配置
 * - delData()       : 删除采集源
 *
 * 视频采集 (mid=1):
 * - vod()           : 视频数据接口调用
 * - vod_data()      : 视频数据入库处理
 * - vod_data_bind() : 视频数据字段绑定
 * - checkParam()    : 视频字段过滤检查
 *
 * 文章采集 (mid=2):
 * - art()           : 文章数据接口调用
 * - art_data()      : 文章数据入库处理
 *
 * 演员采集 (mid=8):
 * - actor()         : 演员数据接口调用
 * - actor_data()    : 演员数据入库处理
 *
 * 角色采集 (mid=9):
 * - role()          : 角色数据接口调用
 * - role_data()     : 角色数据入库处理
 *
 * 网站采集 (mid=11):
 * - website()       : 网站数据接口调用
 * - website_data()  : 网站数据入库处理
 *
 * 漫画采集 (mid=12):
 * - manga()         : 漫画数据接口调用
 * - manga_data()    : 漫画数据入库处理
 *
 * 工具方法:
 * - mac_data_count(): 数据统计
 * - mac_data_check(): 数据检验
 * - get_page_url()  : 分页URL处理
 *
 * 【采集流程】
 * 1. 调用接口获取列表/详情数据 (XML/JSON)
 * 2. 解析数据结构
 * 3. 根据分类绑定映射到本地分类
 * 4. 根据配置过滤和处理数据
 * 5. 写入或更新到本地数据库
 *
 * 【相关文件】
 * - application/admin/controller/Collect.php : 采集控制器
 * - application/extra/bind.php               : 分类绑定配置
 * - application/extra/maccms.php             : 采集参数配置
 *
 * ============================================================
 */
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;
use think\Request;
use app\common\validate\Vod as VodValidate;

class Collect extends Base {

    // 设置数据表（不含前缀）
    protected $name = 'collect';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    /**
     * 获取采集源列表
     * @param array $where 查询条件
     * @param string $order 排序方式
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function listData($where,$order,$page=1,$limit=20,$start=0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        $total = $this->where($where)->count();
        $list = Db::name('Collect')->where($where)->order($order)->page($page)->limit($limit)->select();
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 获取单个采集源详情
     * @param array $where 查询条件 (collect_id)
     * @param string $field 查询字段
     * @return array
     */
    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * ============================================================
     * 保存采集源配置 (Save Collection Source)
     * ============================================================
     *
     * 【功能说明】
     * 保存采集源配置到 mac_collect 表
     * 根据是否有 collect_id 判断是新增还是更新
     *
     * 【保存流程】
     * 1. 验证数据格式 (Collect验证器)
     * 2. 有collect_id → 更新记录
     * 3. 无collect_id → 新增记录
     *
     * 【数据字段】
     * - collect_name     : 采集源名称
     * - collect_url      : 接口地址
     * - collect_type     : 接口类型 (1=xml, 2=json)
     * - collect_mid      : 模块类型 (1=视频, 2=文章...)
     * - collect_opt      : 数据操作 (0=新增+更新, 1=新增, 2=更新)
     * - collect_filter   : 地址过滤模式
     * - collect_filter_from : 过滤代码
     * - collect_param    : 附加参数
     * - collect_sync_pic : 同步图片设置
     *
     * @param array $data 表单提交的数据
     * @return array {code:1, msg:'保存成功'}
     */
    public function saveData($data)
    {
        // 加载Collect验证器
        $validate = \think\Loader::validate('Collect');

        // 判断新增还是更新
        if(!empty($data['collect_id'])){
            // ===== 更新操作 =====
            if(!$validate->scene('edit')->check($data)){
                return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
            }

            $where=[];
            $where['collect_id'] = ['eq',$data['collect_id']];
            $res = $this->where($where)->update($data);
        }
        else{
            // ===== 新增操作 =====
            if(!$validate->scene('edit')->check($data)){
                return ['code'=>1002,'msg'=>lang('param_err').'：'.$validate->getError() ];
            }
            $res = $this->insert($data);
        }

        if(false === $res){
            return ['code'=>1003,'msg'=>''.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    /**
     * 删除采集源
     * @param array $where 删除条件
     * @return array
     */
    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    /**
     * 验证采集标识 (防止伪造请求)
     * cjflag 必须等于 cjurl 的 MD5 值
     */
    public function check_flag($param)
    {
        if($param['cjflag'] != md5($param['cjurl'])){
            return ['code'=>9001, 'msg'=>lang('model/collect/flag_err')];
        }
        return ['code'=>1,'msg'=>'ok'];
    }

    /**
     * ============================================================
     * 视频采集接口调用 (Video Collection API)
     * ============================================================
     *
     * 【功能说明】
     * 调用资源站API获取视频数据，支持XML和JSON两种格式
     * 用于"测试"按钮和实际采集时获取数据
     *
     * 【处理逻辑】
     * - type=1 : 调用 vod_xml() 处理XML格式
     * - type=2 : 调用 vod_json() 处理JSON格式
     * - type为空: 先尝试JSON，失败则尝试XML
     *
     * 【返回结果】
     * 成功: {code:1, msg:'json', page:{}, type:[], data:[]}
     * 失败: {code:1001, msg:'错误信息'}
     *
     * @param array $param 采集参数
     * @return array
     */
    public function vod($param)
    {
        // 根据接口类型选择解析方式
        if($param['type'] == '1'){
            // XML格式
            return $this->vod_xml($param);
        }
        elseif($param['type'] == '2'){
            // JSON格式
            return $this->vod_json($param);
        }
        else{
            // 自动检测: 先尝试JSON，失败则尝试XML
            $data = $this->vod_json($param);

            if($data['code'] == 1){
                return $data;
            }
            else{
                return $this->vod_xml($param);
            }
        }
    }

    public function art($param)
    {
        return $this->art_json($param);
    }

    public function actor($param)
    {
        return $this->actor_json($param);
    }

    public function role($param)
    {
        return $this->role_json($param);
    }

    public function website($param)
    {
        return $this->website_json($param);
    }

    public function manga($param)
    {
        if($param['type'] == '1'){
            return $this->manga_xml($param);
        }
        elseif($param['type'] == '2'){
            return $this->manga_json($param);
        }
        else{
            $data = $this->manga_json($param);

            if($data['code'] == 1){
                return $data;
            }
            else{
                return $this->manga_xml($param);
            }
        }
    }

    /**
     * ============================================================
     * 格式化播放地址 (Format Play URL)
     * ============================================================
     *
     * 【功能说明】
     * 清理和格式化从XML中提取的播放地址字符串
     * 处理 "||" 转义符号和 "$" 分隔的 "名称$地址" 格式
     *
     * 【输入格式】
     * 原始格式: "第1集$http://a.com||第2集$http://b.com#第3集$http://c.com"
     * - "#" 分隔不同剧集
     * - "$" 分隔剧集名称和地址
     * - "||" 是资源站对 "//" 的转义 (避免XML解析问题)
     *
     * 【输出格式】
     * 处理后: "第1集$http://a.com#第2集$http://b.com#第3集$http://c.com"
     *
     * 【调用位置】
     * vod_xml() 方法中处理 <dl><dd> 标签的播放地址
     *
     * @param string $url 原始播放地址字符串 (# 分隔多集)
     * @return string 格式化后的播放地址
     */
    public function vod_xml_replace($url)
    {
        $array_url = array();
        // 将 "||" 还原为 "//" (资源站的URL转义处理)
        $arr_ji = explode('#',str_replace('||','//',$url));
        foreach($arr_ji as $key=>$value){
            $urlji = explode('$',$value);
            if( count($urlji) > 1 ){
                $array_url[$key] = $urlji[0].'$'.trim($urlji[1]);
            }else{
                $array_url[$key] = trim($urlji[0]);
            }
        }
        return implode('#',$array_url);
    }

    /**
     * ============================================================
     * XML格式API采集 (XML API Collection)
     * ============================================================
     *
     * 【功能说明】
     * 从XML格式的资源站API获取视频数据，解析并标准化为系统格式
     * 这是苹果CMS标准的XML采集接口格式
     *
     * 【XML接口格式】(标准苹果CMS接口)
     * ```xml
     * <rss>
     *   <list page="1" pagecount="100" pagesize="20" recordcount="2000">
     *     <video>
     *       <id>123</id>
     *       <tid>1</tid>           <!-- 资源站分类ID -->
     *       <name>视频名称</name>
     *       <pic>封面地址</pic>
     *       <note>更新至第10集</note>
     *       <actor>演员列表</actor>
     *       <director>导演</director>
     *       <des>简介内容</des>
     *       <dl>                   <!-- 播放列表 -->
     *         <dd flag="m3u8">第1集$url1#第2集$url2</dd>
     *         <dd flag="qq">第1集$url1#第2集$url2</dd>
     *       </dl>
     *     </video>
     *   </list>
     *   <class>                    <!-- 分类列表 (ac=list时) -->
     *     <ty id="1">电影</ty>
     *     <ty id="2">电视剧</ty>
     *   </class>
     * </rss>
     * ```
     *
     * 【请求参数】
     * - ac: videolist(视频详情) | list(分类列表)
     * - t: 分类ID
     * - pg: 页码
     * - h: 小时内更新 (如 h=24 表示24小时内)
     * - ids: 指定ID列表
     * - wd: 搜索关键词
     *
     * 【分类绑定】
     * 通过 config('bind') 将资源站分类ID映射到本站分类ID
     * 绑定键格式: "{cjflag}_{资源站分类ID}" => 本站分类ID
     *
     * 【调用链路】
     * vod() → vod_xml() → checkCjUrl() → mac_curl_get() → simplexml_load_string()
     *
     * @param array $param 采集参数
     *   - cjurl: 接口地址
     *   - ac: 操作类型
     *   - t: 分类ID
     *   - page: 页码
     *   - h: 时间范围
     *   - ids: 指定ID
     *   - wd: 搜索词
     *   - param: 附加参数(base64编码)
     *   - cjflag: 采集标识
     * @param string $html 预获取的HTML内容(可选)
     * @return array 标准化返回格式
     *   - code: 1=成功, 1001=请求失败, 1002=解析失败
     *   - msg: 提示信息
     *   - page: 分页信息 {page,pagecount,pagesize,recordcount,url}
     *   - type: 分类列表 (ac=list时)
     *   - data: 视频数据数组
     */
    public function vod_xml($param,$html='')
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];
        if(empty($param['h']) && !empty($param['rday'])){
            $url_param['h'] = $param['rday'];
        }

        if($param['ac']!='list'){
            $url_param['ac'] = 'videolist';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }
        $url .= http_build_query($url_param). base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $xml = @simplexml_load_string($html);
        if(empty($xml)){
            $labelRule = '<pic>'."(.*?)".'</pic>';
            $labelRule = mac_buildregx($labelRule,"is");
            preg_match_all($labelRule,$html,$tmparr);
            $ec=false;
            foreach($tmparr[1] as $tt){
                if(strpos($tt,'[CDATA')===false){
                    $ec=true;
                    $ne = '<pic>'.'<![CDATA['.$tt .']]>'.'</pic>';
                    $html = str_replace('<pic>'.$tt.'</pic>',$ne,$html);
                }
            }
            if($ec) {
                $xml = @simplexml_load_string($html);
            }
            if(empty($xml)) {
                return ['code' => 1002, 'msg'=>lang('model/collect/xml_err')];
            }
        }

        $array_page = [];
        $array_page['page'] = (string)$xml->list->attributes()->page;
        $array_page['pagecount'] = (string)$xml->list->attributes()->pagecount;
        $array_page['pagesize'] = (string)$xml->list->attributes()->pagesize;
        $array_page['recordcount'] = (string)$xml->list->attributes()->recordcount;
        $array_page['url'] = $url;

        $type_list = model('Type')->getCache('type_list');
        $bind_list = config('bind');


        $key = 0;
        $array_data = [];
        foreach($xml->list->video as $video){
            $bind_key = $param['cjflag'] .'_'.(string)$video->tid;
            if($bind_list[$bind_key] >0){
                $array_data[$key]['type_id'] = $bind_list[$bind_key];
            }
            else{
                $array_data[$key]['type_id'] = 0;
            }
            $array_data[$key]['vod_id'] = (string)$video->id;
            //$array_data[$key]['type_id'] = (string)$video->tid;
            $array_data[$key]['vod_name'] = (string)$video->name;
            $array_data[$key]['vod_sub'] = (string)$video->subname;
            $array_data[$key]['vod_remarks'] = (string)$video->note;
            $array_data[$key]['type_name'] = (string)$video->type;
            $array_data[$key]['vod_pic'] = (string)$video->pic;
            $array_data[$key]['vod_lang'] = (string)$video->lang;
            $array_data[$key]['vod_area'] = (string)$video->area;
            $array_data[$key]['vod_year'] = (string)$video->year;
            $array_data[$key]['vod_serial'] = (string)$video->state;
            $array_data[$key]['vod_actor'] = (string)$video->actor;
            $array_data[$key]['vod_director'] = (string)$video->director;
            $array_data[$key]['vod_content'] = (string)$video->des;

            $array_data[$key]['vod_status'] = 1;
            $array_data[$key]['vod_type'] = $array_data[$key]['list_name'];
            $array_data[$key]['vod_time'] = (string)$video->last;
            $array_data[$key]['vod_total'] = 0;
            $array_data[$key]['vod_isend'] = 1;
            if($array_data[$key]['vod_serial']){
                $array_data[$key]['vod_isend'] = 0;
            }
            //格式化地址与播放器
            $array_from = [];
            $array_url = [];
            $array_server=[];
            $array_note=[];
            //videolist|list播放列表不同
            if(isset($video->dl->dd) && $count=count($video->dl->dd)){
                for($i=0; $i<$count; $i++){
                    $array_from[$i] = (string)$video->dl->dd[$i]['flag'];
                    $urls = explode('#', $this->vod_xml_replace((string)$video->dl->dd[$i]));
                    $sorted_urls = $this->sortPlayUrls($urls);
                    $array_url[$i] = implode('#', $sorted_urls);
                    $array_server[$i] = 'no';
                    $array_note[$i] = '';
                }
            }else{
                $array_from[]=(string)$video->dt;
                $array_url[] ='';
                $array_server[]='';
                $array_note[]='';
            }

            if(strpos(base64_decode($param['param']),'ct=1')!==false){
                $array_data[$key]['vod_down_from'] = implode('$$$', $array_from);
                $array_data[$key]['vod_down_url'] = implode('$$$', $array_url);
                $array_data[$key]['vod_down_server'] = implode('$$$', $array_server);
                $array_data[$key]['vod_down_note'] = implode('$$$', $array_note);
            }
            else{
                $array_data[$key]['vod_play_from'] = implode('$$$', $array_from);
                $array_data[$key]['vod_play_url'] = implode('$$$', $array_url);
                $array_data[$key]['vod_play_server'] = implode('$$$', $array_server);
                $array_data[$key]['vod_play_note'] = implode('$$$', $array_note);
            }

            $key++;
        }

        $array_type = [];
        $key=0;
        //分类列表
        if($param['ac'] == 'list'){
            foreach($xml->class->ty as $ty){
                $array_type[$key]['type_id'] = (string)$ty->attributes()->id;
                $array_type[$key]['type_name'] = (string)$ty;
                $key++;
            }
        }

        $res = ['code'=>1, 'msg'=>'xml', 'page'=>$array_page, 'type'=>$array_type, 'data'=>$array_data ];
        return $res;
    }

    /**
     * ============================================================
     * JSON格式API采集 (JSON API Collection)
     * ============================================================
     *
     * 【功能说明】
     * 从JSON格式的资源站API获取视频数据，解析并标准化为系统格式
     * 这是苹果CMS新版API接口格式，比XML格式更简洁
     *
     * 【JSON接口格式】
     * ```json
     * {
     *   "page": 1,
     *   "pagecount": 100,
     *   "limit": 20,
     *   "total": 2000,
     *   "list": [
     *     {
     *       "vod_id": 123,
     *       "type_id": 1,
     *       "vod_name": "视频名称",
     *       "vod_pic": "封面地址",
     *       "vod_remarks": "更新至第10集",
     *       "vod_actor": "演员",
     *       "vod_director": "导演",
     *       "vod_content": "简介",
     *       "dl": {"m3u8": "第1集$url1#第2集$url2"}
     *     }
     *   ],
     *   "class": [{"type_id":1, "type_name":"电影"}]
     * }
     * ```
     *
     * 【与XML格式的区别】
     * - JSON直接使用键值对，无需XML解析
     * - 播放列表dl使用对象格式: {"播放源名": "地址"}
     * - 分类绑定逻辑相同
     *
     * 【调用链路】
     * vod() → vod_json() → checkCjUrl() → mac_curl_get() → json_decode()
     *
     * @param array $param 采集参数 (同vod_xml)
     * @return array 标准化返回格式 (同vod_xml)
     */
    public function vod_json($param)
    {
        // ========== 第一步：构建请求URL ==========
        // 将采集参数转换为资源站API的URL参数
        $url_param = [];
        $url_param['ac'] = $param['ac'];        // 接口动作: list=分类列表, videolist=视频列表
        $url_param['t'] = $param['t'];          // type_id 分类ID
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';  // 页码
        $url_param['h'] = $param['h'];          // 时间范围 (24小时内更新)
        $url_param['ids'] = $param['ids'];      // 指定采集的视频ID，逗号分隔
        $url_param['wd'] = $param['wd'];        // 关键词搜索

        // 确保 ac 参数正确 (非 list 时统一为 videolist)
        if($param['ac']!='list'){
            $url_param['ac'] = 'videolist';
        }

        // 拼接完整的请求URL
        $url = $param['cjurl'];  // 资源站API基础地址
        if(strpos($url,'?')===false){
            $url .='?';  // 添加查询字符串开始符
        }
        else{
            $url .='&';  // 已有参数，用 & 连接
        }
        // http_build_query: 标准参数
        // base64_decode($param['param']): 自定义附加参数 (在采集源配置中设置)
        $url .= http_build_query($url_param). base64_decode($param['param']);

        // ========== 第二步：URL安全检查 ==========
        // 防止采集本地地址 (127.0.0.1, localhost)
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;  // 检查失败，返回错误
        }
        // todo 多线程调用点
        // ========== 第三步：发起HTTP请求 ==========
        $html = mac_curl_get($url);  // 使用 CURL 获取JSON响应
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }

        // ========== 第四步：解析JSON数据 ==========
        $html = mac_filter_tags($html);  // 过滤特殊字符
        $json = json_decode($html,true);  // 解析为关联数组
        if(!$json){
            // JSON解析失败，返回前15个字符用于调试
            return ['code'=>1002, 'msg'=>lang('model/collect/json_err') . ', url: ' . $url . ', response: ' . mb_substr($html, 0, 15)];
        }

        // ========== 第五步：提取分页信息 ==========
        $array_page = [];
        $array_page['page'] = $json['page'];           // 当前页码
        $array_page['pagecount'] = $json['pagecount']; // 总页数
        $array_page['pagesize'] = $json['limit'];      // 每页数量
        $array_page['recordcount'] = $json['total'];   // 总记录数
        $array_page['url'] = $url;                     // 请求的完整URL

        // 加载本地分类和绑定配置
        $type_list = model('Type')->getCache('type_list');  // 本地分类列表
        $bind_list = config('bind');  // 分类绑定配置 (资源站分类ID → 本站分类ID)

        // ========== 第六步：遍历视频列表数据 ==========
        $key = 0;
        $array_data = [];
        foreach($json['list'] as $key=>$v){
            // 复制所有字段到结果数组
            $array_data[$key] = $v;

            // ========== 分类绑定处理 ==========
            // 根据 "资源站标识_分类ID" 查找本站绑定的分类ID
            // 例如: "ckzy_1" → 本站分类ID 6
            $bind_key = $param['cjflag'] .'_'.$v['type_id'];
            if($bind_list[$bind_key] >0){
                $array_data[$key]['type_id'] = $bind_list[$bind_key];  // 使用绑定的分类ID
            }
            else{
                $array_data[$key]['type_id'] = 0;  // 未绑定，设为0 (后续会跳过)
            }

            // ========== 播放地址处理 ==========
            // JSON格式的播放列表: {"m3u8": "第1集$url#第2集$url", "mp4": "..."}
            if(!empty($v['dl'])) {
                $array_from = [];    // 播放源名称数组 (m3u8, mp4 等)
                $array_url = [];     // 播放地址数组
                $array_server = [];  // 服务器标识数组
                $array_note = [];    // 备注数组

                // 遍历每个播放源
                foreach ($v['dl'] as $k2 => $v2) {
                    $array_from[] = $k2;  // 播放源名称 (如: m3u8)

                    // 处理播放地址: "第1集$url#第2集$url#第3集$url"
                    $urls = explode('#', $v2);  // 按 # 分割每集
                    $sorted_urls = $this->sortPlayUrls($urls);  // 智能排序 (第1集, 第2集...)
                    $array_url[] = implode('#', $sorted_urls);  // 重新用 # 连接

                    $array_server[] = 'no';  // 服务器标识 (默认 no)
                    $array_note[] = '';      // 备注为空
                }

                // 拼接多个播放源，使用 $$$ 分隔
                // 结果格式: "m3u8$$$mp4"
                $array_data[$key]['vod_play_from'] = implode('$$$', $array_from);
                $array_data[$key]['vod_play_url'] = implode('$$$', $array_url);
                $array_data[$key]['vod_play_server'] = implode('$$$', $array_server);
                $array_data[$key]['vod_play_note'] = implode('$$$', $array_note);
            }
        }

        // ========== 第七步：提取分类列表 (仅当 ac=list 时) ==========
        $array_type = [];
        $key=0;
        if($param['ac'] == 'list'){
            // 遍历资源站提供的分类数据
            foreach($json['class'] as $k=>$v){
                $array_type[$key]['type_id'] = $v['type_id'];     // 资源站分类ID
                $array_type[$key]['type_name'] = $v['type_name']; // 资源站分类名称
                $key++;
            }
        }

        // ========== 第八步：返回标准化结果 ==========
        // code=1: 成功
        // msg: 数据格式标识 (json)
        // page: 分页信息
        // type: 分类列表 (仅 ac=list 时有值)
        // data: 视频数据列表
        $res = ['code'=>1, 'msg'=>'json', 'page'=>$array_page, 'type'=>$array_type, 'data'=>$array_data ];
        return $res;
    }

    /**
     * ============================================================
     * 剧集URL排序 (Sort Episode URLs)
     * ============================================================
     *
     * 【功能说明】
     * 对采集的播放地址按剧集编号排序
     * 支持识别多种剧集命名格式：第1集、EP1、E1、1集、1话、1回
     *
     * 【处理逻辑】
     * 1. 使用正则提取剧集编号
     * 2. 以编号为key存入数组
     * 3. 按key排序 (ksort)
     * 4. 返回排序后的数组
     *
     * 【输入输出示例】
     * 输入: ["第3集$url3", "第1集$url1", "第2集$url2"]
     * 输出: ["第1集$url1", "第2集$url2", "第3集$url3"]
     *
     * 【调用位置】
     * vod_xml() 和 vod_json() 中对播放地址列表排序
     *
     * @param array $urls 待排序的URL数组
     * @return array 排序后的URL数组
     */
    private function sortPlayUrls($urls) {
        $sorted = [];
        foreach ($urls as $url) {
            if (preg_match('/(?:第|EP|E)?(\d+)(?:集|话|回)?/', $url, $matches)) {
                $episode = (int)$matches[1];
                $sorted[$episode] = $url;
            } else {
                $sorted[] = $url;
            }
        }
        ksort($sorted);
        return array_values($sorted);
    }

    /**
     * 同步图片
     *
     * @param $pic_status int 是否同步。为1时，同步图片
     * @param $pic_url
     * @param string $flag
     * @return array
     */
    private function syncImages($pic_status, $pic_url, $flag = 'vod')
    {
        $img_url_downloaded = $pic_url;
        if ($pic_status == 1) {
            // 清理失败标记，获取真实URL
            $clean_url = str_replace('#err', '', $pic_url);
            $config = (array)config('maccms.upload');
            $img_url_downloaded = model('Image')->down_load($clean_url, $config, $flag);
            if ($img_url_downloaded == $clean_url || strpos($img_url_downloaded, '#err') !== false) {
                // 下载失败，显示老图信息
                $des = '<a href="' . $clean_url . '" target="_blank">' . $clean_url . '</a><font color=red>'.lang('download_err').'!</font>';
            } else {
                // 下载成功，显示新图信息
                if (str_starts_with($img_url_downloaded, 'upload/')) {
                    $link = MAC_PATH . $img_url_downloaded;
                } else {
                    $link = str_replace('mac:', $config['protocol'] . ':', $img_url_downloaded);
                }
                $des = '<a href="' . $link . '" target="_blank">' . $link . '</a><font color=green>'.lang('download_ok').'!</font>';
            }
        }
        return ['pic' => $img_url_downloaded, 'msg' => $des];
    }

    /**
     * ============================================================
     * 视频数据入库方法 (Video Data Insert/Update)
     * ============================================================
     *
     * 【功能说明】
     * 采集模块的核心数据入库方法，负责将从资源站获取的视频数据处理后入库
     * 支持新增和更新两种操作，根据配置规则(inrule/uprule)自动判断和处理
     *
     * 【核心功能】
     * 1. 数据验证与清洗 (过滤、格式化、伪原创)
     * 2. 播放地址处理 (验证播放器、过滤地址、合并更新)
     * 3. 图片同步下载 (支持本地化)
     * 4. 重复检测 (根据inrule规则查找已存在数据)
     * 5. 新增/更新操作 (根据opt参数和uprule规则)
     * 6. 自动翻页采集 (完成后自动跳转下一页)
     *
     * 【请求参数】
     * @param array $param 采集参数
     *   - opt              : 数据操作模式 (0=新增+更新, 1=仅新增, 2=仅更新)
     *   - filter           : 地址过滤模式 (0=全部, 1=播放源, 2=下载源, 3=播放+下载)
     *   - filter_from      : 过滤的播放源代码 (逗号分隔)
     *   - filter_year      : 过滤年份 (逗号分隔)
     *   - sync_pic_opt     : 图片同步选项 (0=全局, 1=开启, 2=关闭)
     *   - ac               : 操作类型 (videolist=列表采集, cjsel=选中采集)
     *   - page             : 当前页码
     *
     * @param array $data 资源站返回的数据 (由 vod_xml() 或 vod_json() 解析)
     *   - page             : 分页信息 {page, pagecount, url}
     *   - data             : 视频数据列表
     *
     * @param int $show 是否实时输出 (1=输出进度信息, 0=静默模式返回结果)
     *
     * 【配置文件】
     * application/extra/maccms.php → collect.vod
     * 关键配置：inrule(重复检测), uprule(更新字段), urlrole(地址合并)
     *
     * 【核心数据库操作】
     * - 新增: model('Vod')->insert($v) → 第1145行
     * - 更新: model('Vod')->where($where)->update($update) → 第1402行
     *
     * 【调用位置】
     * application/admin/controller/Collect.php:844
     *
     * @return mixed
     */
    public function vod_data($param,$data,$show=1)
    {
        // ========== 第一步：输出进度信息 ==========
        // 显示当前采集进度: 第X页/共Y页
        if($show==1) {
            mac_echo('[' . __FUNCTION__ . '] ' . lang('model/collect/data_tip1', [$data['page']['page'],$data['page']['pagecount'],$data['page']['url']]));
        }

        // ========== 第二步：加载配置信息 ==========
        // 加载采集配置 (来自 application/extra/maccms.php)
        $config = config('maccms.collect');
        $config = $config['vod'];  // 视频采集配置

        // 图片同步配置 (优先使用采集源配置，否则使用全局配置)
        $config_sync_pic = $param['sync_pic_opt'] > 0 ? $param['sync_pic_opt'] : $config['pic'];
        $filter_year = !empty($param['filter_year']) ? $param['filter_year'] : '';
        $filter_year_list = $filter_year ? get_array_unique_id_list(explode(',', $filter_year)) : [];
        $players = config('vodplayer');
        $downers = config('voddowner');
        $vod_search = model('VodSearch');
        $vod_search_enabled = $vod_search->isCollectEnabled();
        $vs_max_id_count = $vod_search->maxIdCount;

        $type_list = model('Type')->getCache('type_list');
        $filter_arr = explode(',',$config['filter']);
        $filter_arr = array_filter($filter_arr);
        $pse_rnd = explode('#',$config['words']);
        $pse_rnd = array_filter($pse_rnd);
        $pse_name = mac_txt_explain($config['namewords'], true);
        $pse_syn = mac_txt_explain($config['thesaurus'], true);
        $pse_player = mac_txt_explain($config['playerwords'], true);
        $pse_area = mac_txt_explain($config['areawords'], true);
        $pse_lang = mac_txt_explain($config['langwords'], true);

        // ========== 第三步：遍历每条视频数据 ==========
        // 逐条处理从资源站获取的视频列表
        foreach($data['data'] as $k=>$v){
            // 初始化结果变量
            $color='red';      // 输出颜色 (red=失败, green=成功, orange=警告)
            $des='';           // 描述信息
            $msg='';           // 消息内容 (如图片下载结果)
            $tmp='';           // 临时变量

            // ========== 数据验证：分类ID检查 ==========
            // type_id=0 表示该视频的分类未在 bind.php 中绑定到本地分类
            // 未绑定的数据将被跳过，不会入库
            if ($v['type_id'] ==0) {
                $des = lang('model/collect/type_err');
            }
            // ========== 数据验证：视频名称检查 ==========
            // 视频名称为空，无法入库
            elseif (empty($v['vod_name'])) {
                $des = lang('model/collect/name_err');
            }
            // ========== 数据验证：关键词过滤检查 ==========
            // 检查视频名称是否包含配置的过滤关键词
            // 过滤词配置位置: 采集参数配置 → 过滤关键字 (逗号分隔)
            elseif (mac_array_filter($filter_arr,$v['vod_name']) !==false) {
                $des = lang('model/collect/name_in_filter_err');
            }
            // ========== 数据验证：年份过滤检查 ==========
            // 如果配置了年份过滤 (如只采集2020-2023年的视频)
            // 不在指定年份范围内的视频将被跳过
            elseif ($filter_year_list && !in_array(intval($v['vod_year']), $filter_year_list)) {
                // 采集时，过滤年份
                // https://github.com/magicblack/maccms10/issues/1057
                $color = 'orange';
                $des = 'year [' . intval($v['vod_year']) . '] not in: ' . join(',', $filter_year_list);
            }
            // ========== 数据验证通过，开始处理 ==========
            else {
                // 移除资源站的ID，使用本地自增ID
                unset($v['vod_id']);

                // ========== 数据清洗：过滤HTML标签 ==========
                // 遍历所有字段，清理HTML标签(XSS防护)
                // 排除: vod_content(简介内容)、vod_plot_detail(剧情内容)
                foreach($v as $k2=>$v2){
                    if(strpos($k2,'_content')===false && $k2!=='vod_plot_detail') {
                        $v[$k2] = strip_tags($v2);  // 移除HTML标签
                    }
                }

                // ========== 数据补全：父级分类 ==========
                // 设置type_id_1为该分类的父分类ID
                // 例如: 分类"动作片"(id=6)的父分类是"电影"(id=1)
                $v['type_id_1'] = intval($type_list[$v['type_id']]['type_pid']);

                // ========== 数据补全：拼音和首字母 ==========
                // 如果资源站未提供拼音，则自动生成
                if(empty($v['vod_en'])){
                    $v['vod_en'] = Pinyin::get($v['vod_name']);  // "肖申克的救赎" → "xiaoshenkdejiushu"
                }
                // 如果资源站未提供首字母，则取拼音首字母
                if(empty($v['vod_letter'])){
                    $v['vod_letter'] = strtoupper(substr($v['vod_en'],0,1));  // "xiaoshenkdejiushu" → "X"
                }

                // ========== 时间戳处理 ==========
                // 使用资源站的添加时间，更新时间保持当前
                // https://github.com/magicblack/maccms10/issues/780
                if (empty($v['vod_time_add']) || strlen($v['vod_time_add']) != 10) {
                    $v['vod_time_add'] = time();  // 添加时间：首次入库时间
                }
                // 支持外部自定义修改时间
                // https://github.com/magicblack/maccms10/issues/862
                $v['vod_time'] = time();  // 更新时间：每次入库/更新时间
                if (!empty($v['vod_time_update']) && strlen($v['vod_time_update']) == 10) {
                    $v['vod_time'] = (int)$v['vod_time_update'];  // 使用资源站指定的更新时间
                }

                // ========== 审核状态设置 (菜单: 视频-未审核视频) ==========
                // config['status'] 来自采集配置，决定采集数据的初始审核状态
                // 0=未审核 (需人工审核后才在前台显示)
                // 1=已审核 (采集后直接在前台显示)
                // 用途: 控制采集内容是否需要人工审核后才发布
                // 查看未审核视频: 后台菜单 → 视频 → 未审核视频 (vod/data?status=0)
                $v['vod_status'] = intval($config['status']);
                $v['vod_lock'] = intval($v['vod_lock']);
                // 如果数据源提供了审核状态，优先使用数据源的值
                if(!empty($v['vod_status'])) {
                    $v['vod_status'] = intval($v['vod_status']);
                }

                // ========== 数据类型转换：整型字段 ==========
                // 确保所有数值型字段为整数，防止类型错误
                $v['vod_year'] = intval($v['vod_year']);              // 年份
                $v['vod_level'] = intval($v['vod_level']);            // 推荐级别
                $v['vod_hits'] = intval($v['vod_hits']);              // 总点击数
                $v['vod_hits_day'] = intval($v['vod_hits_day']);      // 日点击数
                $v['vod_hits_week'] = intval($v['vod_hits_week']);    // 周点击数
                $v['vod_hits_month'] = intval($v['vod_hits_month']);  // 月点击数
                $v['vod_stint_play'] = intval($v['vod_stint_play']);  // 播放限制
                $v['vod_stint_down'] = intval($v['vod_stint_down']);  // 下载限制

                $v['vod_total'] = intval($v['vod_total']);            // 总集数
                $v['vod_serial'] = intval($v['vod_serial']);          // 连载数 (更新到第几集)
                $v['vod_isend'] = intval($v['vod_isend']);            // 是否完结 (0=连载中, 1=已完结)
                $v['vod_up'] = intval($v['vod_up']);                  // 顶数量
                $v['vod_down'] = intval($v['vod_down']);              // 踩数量

                // ========== 数据类型转换：浮点型字段 ==========
                $v['vod_score'] = floatval($v['vod_score']);          // 平均评分
                $v['vod_score_all'] = intval($v['vod_score_all']);    // 总评分
                $v['vod_score_num'] = intval($v['vod_score_num']);    // 评分人数

                // ========== 文本字段格式化 ==========
                // 合并视频分类和类型名称
                $v['vod_class'] = mac_txt_merge($v['vod_class'],$v['type_name']);

                // 格式化文本字段：去除多余空格和逗号，统一分隔符
                $v['vod_actor'] = mac_format_text($v['vod_actor'], true);       // 演员列表
                $v['vod_director'] = mac_format_text($v['vod_director'], true); // 导演列表
                $v['vod_class'] = mac_format_text($v['vod_class'], true);       // 分类标签
                $v['vod_tag'] = mac_format_text($v['vod_tag'], true);           // 标签列表

                // ========== 分集剧情数据处理 (菜单: 视频-有分集剧情) ==========
                // 采集的剧情数据格式: "第1集标题$$$第2集标题$$$第3集标题"
                // vod_plot_name  : 剧情标题 ($$$ 分隔)
                // vod_plot_detail: 剧情内容 ($$$ 分隔)
                // vod_plot: 剧情标记 (0=无剧情, 1=有剧情)
                $v['vod_plot_name'] = (string)$v['vod_plot_name'];
                $v['vod_plot_detail'] = (string)$v['vod_plot_detail'];

                // 如果有剧情标题，设置剧情标记为1，并清理首尾的分隔符
                if(!empty($v['vod_plot_name'])){
                    $v['vod_plot'] = 1;  // 标记该视频有分集剧情
                    $v['vod_plot_name'] = trim($v['vod_plot_name'],'$$$');
                }
                if(!empty($v['vod_plot_detail'])){
                    $v['vod_plot_detail'] = trim($v['vod_plot_detail'],'$$$');
                }
                // 如果有连载数但未设置完结状态，则标记为连载中
                if(empty($v['vod_isend']) && !empty($v['vod_serial'])){
                    $v['vod_isend'] = 0;
                }

                // ========== 随机数据生成：点击量 ==========
                // 配置位置: 采集参数配置 → 点击初始值
                // 为采集的视频生成随机初始点击量，让新数据看起来更真实
                if($config['hits_start']>0 && $config['hits_end']>0) {
                    $v['vod_hits'] = rand($config['hits_start'], $config['hits_end']);
                    $v['vod_hits_day'] = rand($config['hits_start'], $config['hits_end']);
                    $v['vod_hits_week'] = rand($config['hits_start'], $config['hits_end']);
                    $v['vod_hits_month'] = rand($config['hits_start'], $config['hits_end']);
                }

                // ========== 随机数据生成：顶踩数量 ==========
                // 配置位置: 采集参数配置 → 顶踩初始值
                // 为采集的视频生成随机顶踩数量
                if($config['updown_start']>0 && $config['updown_end']){
                    $v['vod_up'] = rand($config['updown_start'], $config['updown_end']);
                    $v['vod_down'] = rand($config['updown_start'], $config['updown_end']);
                }

                // ========== 随机数据生成：评分 ==========
                // 配置位置: 采集参数配置 → 是否生成评分
                // 自动生成随机评分数据，让视频看起来更可信
                if($config['score']==1) {
                    $v['vod_score_num'] = rand(1, 1000);               // 评分人数
                    $v['vod_score_all'] = $v['vod_score_num'] * rand(1, 10);  // 总评分
                    $v['vod_score'] = round($v['vod_score_all'] / $v['vod_score_num'], 1);  // 平均分
                }

                // ========== 伪原创处理：视频名称 (配置: 采集参数配置 → 伪原创) ==========
                // psename=1 时，对视频名称进行同义词替换
                // 例如: "肖申克的救赎" → "肖申克的救赎[高清版]"
                if ($config['psename'] == 1) {
                    $v['vod_name'] = mac_rep_pse_syn($pse_name, $v['vod_name']);
                }

                // ========== 伪原创处理：随机插入词 ==========
                // psernd=1 时，在内容中随机插入干扰词
                // 用途: 避免内容完全相同导致搜索引擎判定为重复
                if ($config['psernd'] == 1) {
                    $v['vod_content'] = mac_rep_pse_rnd($pse_rnd, $v['vod_content']);
                }

                // ========== 伪原创处理：同义词替换 ==========
                // psesyn=1 时，使用同义词替换内容
                // 例如: "非常好看" → "特别精彩"
                if ($config['psesyn'] == 1) {
                    $v['vod_content'] = mac_rep_pse_syn($pse_syn, $v['vod_content']);
                }

                // ========== 伪原创处理：播放源名称 ==========
                // pseplayer=1 时，替换播放源名称
                // 例如: "m3u8" → "高清播放"
                if ($config['pseplayer'] == 1) {
                    $v['vod_play_from'] = mac_rep_pse_syn($pse_player, $v['vod_play_from']);
                }

                // ========== 伪原创处理：地区名称 ==========
                // 例如: "美国" → "USA"
                if ($config['psearea'] == 1) {
                    $v['vod_area'] = mac_rep_pse_syn($pse_area, $v['vod_area']);
                }

                // ========== 伪原创处理：语言名称 ==========
                // 例如: "中文" → "汉语"
                if ($config['pselang'] == 1) {
                    $v['vod_lang'] = mac_rep_pse_syn($pse_lang, $v['vod_lang']);
                }

                // ========== 自动生成简介 ==========
                // 如果资源站未提供简介，则从内容中截取前100字作为简介
                if(empty($v['vod_blurb'])){
                    $v['vod_blurb'] = mac_substring( strip_tags($v['vod_content']) ,100);
                }

                // ========== 重复检测规则配置 (inrule - Insert Rule) ==========
                // 配置位置: 采集参数配置 → 入库重复规则
                // 功能说明: 根据 inrule 配置构建查询条件，检测数据库中是否已存在相同视频
                // 规则字母说明:
                //   a = 视频名称 (vod_name)
                //   b = 分类ID (type_id)
                //   c = 年份 (vod_year)
                //   d = 地区 (vod_area)
                //   e = 语言 (vod_lang)
                //   f = 演员 (vod_actor) - 使用模糊匹配
                //   g = 导演 (vod_director)
                //   h = 豆瓣ID (vod_douban_id)
                // 例如: inrule="a,b,c" 表示同时满足 名称+分类+年份 相同才判定为重复
                $where = [];

                // inrule 包含 'a' - 按视频名称检测重复
                if (strpos($config['inrule'], 'a')!==false) {
                    $where['vod_name'] = mac_filter_xss($v['vod_name']);
                }
                // blend 标记: 演员+导演混合查询标记 (后续特殊处理)
                $blend=false;
                // inrule 包含 'b' - 按分类ID检测重复
                if (strpos($config['inrule'], 'b')!==false) {
                    $where['type_id'] = $v['type_id'];
                }
                // inrule 包含 'c' - 按年份检测重复
                if (strpos($config['inrule'], 'c')!==false) {
                    $where['vod_year'] = $v['vod_year'];
                }
                // inrule 包含 'd' - 按地区检测重复
                if (strpos($config['inrule'], 'd')!==false) {
                    $where['vod_area'] = $v['vod_area'];
                }
                // inrule 包含 'e' - 按语言检测重复
                if (strpos($config['inrule'], 'e')!==false) {
                    $where['vod_lang'] = $v['vod_lang'];
                }
                // inrule 包含 'f' - 按演员检测重复 (使用模糊匹配)
                $search_actor_id_list = [];
                if (strpos($config['inrule'], 'f')!==false) {
                    // 使用 LIKE 模糊匹配演员名称 (支持多个演员用逗号分隔)
                    $where['vod_actor'] = ['like', mac_like_arr(mac_filter_xss($v['vod_actor'])), 'OR'];
                    // 使用 VodSearch 搜索索引优化查询性能
                    if ($vod_search_enabled) {
                        $search_actor_id_list = $vod_search->getResultIdList(mac_filter_xss($v['vod_actor']), 'vod_actor', true);
                        $search_actor_id_list = empty($search_actor_id_list) ? [0] : $search_actor_id_list;
                    }
                }
                // inrule 包含 'g' - 按导演检测重复
                if (strpos($config['inrule'], 'g')!==false) {
                    $where['vod_director'] = mac_filter_xss($v['vod_director']);
                }
                // inrule 包含 'h' - 按豆瓣ID检测重复 (最精准的匹配方式)
                if (strpos($config['inrule'], 'h')!==false) {
                    $where['vod_douban_id'] = intval($v['vod_douban_id']);
                }

                // ========== 演员+导演混合查询优化 (blend) ==========
                // 当同时配置了演员和导演检测时，使用特殊的 OR 查询逻辑
                // 匹配条件: (导演匹配) OR (演员匹配) - 满足其一即可
                // 用途: 避免因演员/导演名称差异导致漏检重复数据
                if(!empty($where['vod_actor']) && !empty($where['vod_director'])){
                    $blend = true;
                    // 将演员和导演条件保存到全局变量，后续构建复杂查询
                    $GLOBALS['blend'] = [
                        'vod_actor'    => $where['vod_actor'],
                        'vod_director' => $where['vod_director'],
                    ];
                    // ===== VodSearch 性能优化 =====
                    // 结果太大时，筛选更耗时。仅在结果数量较小时，才加入 IN 条件
                    // 原理: 使用搜索索引预筛选，减少数据库全表扫描
                    $GLOBALS['blend']['vod_id'] = null;
                    if ($vod_search_enabled && count($search_actor_id_list) <= $vs_max_id_count) {
                        $GLOBALS['blend']['vod_id'] = ['IN', $search_actor_id_list];
                    }
                    // 从 $where 中移除演员和导演条件 (后续在 blend 查询中处理)
                    unset($where['vod_actor'],$where['vod_director']);
                }

                // ========== 播放/下载地址验证和过滤 ==========
                // 功能说明: 验证播放源是否在系统配置中存在，过滤无效数据
                // 配置文件: application/extra/vodplayer.php (播放器配置)
                //          application/extra/voddowner.php (下载工具配置)

                // 初始化播放/下载地址为空字符串 (防止undefined)
                if(empty($v['vod_play_url'])){
                    $v['vod_play_url'] = '';
                }
                if(empty($v['vod_down_url'])){
                    $v['vod_down_url'] = '';
                }

                // ===== 解析播放/下载地址数据结构 =====
                // 数据格式: 多个播放源用 $$$ 分隔
                // 例如: vod_play_from = "m3u8$$$mp4$$$flv"
                //      vod_play_url  = "第1集$url1#第2集$url2$$$第1集$url3#第2集$url4$$$..."
                $cj_play_from_arr = explode('$$$',$v['vod_play_from'] );     // 播放源名称数组
                $cj_play_url_arr = explode('$$$',$v['vod_play_url']);        // 播放地址数组
                $cj_play_server_arr = explode('$$$',$v['vod_play_server']);  // 服务器标识数组
                $cj_play_note_arr = explode('$$$',$v['vod_play_note']);      // 备注数组
                $cj_down_from_arr = explode('$$$',$v['vod_down_from'] );     // 下载源名称数组
                $cj_down_url_arr = explode('$$$',$v['vod_down_url']);        // 下载地址数组
                $cj_down_server_arr = explode('$$$',$v['vod_down_server']);  // 下载服务器数组
                $cj_down_note_arr = explode('$$$',$v['vod_down_note']);      // 下载备注数组

                // ===== 播放地址验证和过滤 =====
                // $collect_filter 用于保存符合 filter_from 参数的播放源数据
                // 用途: 当需要只采集特定播放源时 (如只采集m3u8)，将数据保存到此数组
                $collect_filter=[];

                // 遍历所有播放源，逐个验证
                foreach($cj_play_from_arr as $kk=>$vv){
                    // ===== 验证1: 播放源名称是否为空 =====
                    if(empty($vv)){
                        // 播放源名称为空，移除该组数据
                        unset($cj_play_from_arr[$kk]);
                        unset($cj_play_url_arr[$kk]);
                        unset($cj_play_server_arr[$kk]);
                        unset($cj_play_note_arr[$kk]);
                        continue;
                    }

                    // ===== 验证2: 播放源是否在系统配置中存在 =====
                    // $players 来自 config('vodplayer')，包含系统支持的所有播放器
                    // 如果播放源不在配置中，说明系统无法播放，需要移除
                    if(empty($players[$vv])){
                        unset($cj_play_from_arr[$kk]);
                        unset($cj_play_url_arr[$kk]);
                        unset($cj_play_server_arr[$kk]);
                        unset($cj_play_note_arr[$kk]);
                        continue;
                    }

                    // ===== 数据清理：移除地址末尾的 # 符号 =====
                    $cj_play_url_arr[$kk] = rtrim($cj_play_url_arr[$kk],'#');
                    $cj_play_server_arr[$kk] = $cj_play_server_arr[$kk];
                    $cj_play_note_arr[$kk] = $cj_play_note_arr[$kk];

                    // ===== 播放源过滤 (可选功能) =====
                    // 配置位置: 采集参数 → 地址过滤模式 + 过滤代码
                    // filter > 0 表示启用过滤功能
                    // filter_from 包含需要过滤的播放源代码 (逗号分隔)
                    // 例如: filter_from="m3u8,mp4" 表示只采集这两种播放源
                    if($param['filter'] > 0){
                        // 检查当前播放源是否在过滤列表中
                        if(strpos(','.$param['filter_from'].',',$vv)!==false) {
                            // 符合过滤条件，保存到 collect_filter 数组
                            $collect_filter['play'][$param['filter']]['cj_play_from_arr'][$kk] = $vv;
                            $collect_filter['play'][$param['filter']]['cj_play_url_arr'][$kk] = $cj_play_url_arr[$kk];
                            $collect_filter['play'][$param['filter']]['cj_play_server_arr'][$kk] = $cj_play_server_arr[$kk];
                            $collect_filter['play'][$param['filter']]['cj_play_note_arr'][$kk] = $cj_play_note_arr[$kk];
                        }
                    }
                }

                // ===== 下载地址验证和过滤 (逻辑同播放地址) =====
                foreach($cj_down_from_arr as $kk=>$vv){
                    // 验证下载源名称是否为空
                    if(empty($vv)){
                        unset($cj_down_from_arr[$kk]);
                        unset($cj_down_url_arr[$kk]);
                        unset($cj_down_server_arr[$kk]);
                        unset($cj_down_note_arr[$kk]);
                        continue;
                    }
                    // 验证下载源是否在系统配置中存在
                    // $downers 来自 config('voddowner')
                    if(empty($downers[$vv])){
                        unset($cj_down_from_arr[$kk]);
                        unset($cj_down_url_arr[$kk]);
                        unset($cj_down_server_arr[$kk]);
                        unset($cj_down_note_arr[$kk]);
                        continue;
                    }

                    // 数据清理
                    $cj_down_url_arr[$kk] = rtrim($cj_down_url_arr[$kk]);
                    $cj_down_server_arr[$kk] = $cj_down_server_arr[$kk];
                    $cj_down_note_arr[$kk] = $cj_down_note_arr[$kk];

                    // 下载源过滤 (与播放源过滤逻辑相同)
                    if($param['filter'] > 0){
                        if(strpos(','.$param['filter_from'].',',$vv)!==false) {
                            $collect_filter['down'][$param['filter']]['cj_down_from_arr'][$kk] = $vv;
                            $collect_filter['down'][$param['filter']]['cj_down_url_arr'][$kk] = $cj_down_url_arr[$kk];
                            $collect_filter['down'][$param['filter']]['cj_down_server_arr'][$kk] = $cj_down_server_arr[$kk];
                            $collect_filter['down'][$param['filter']]['cj_down_note_arr'][$kk] = $cj_down_note_arr[$kk];
                        }
                    }
                }

                // ===== 重新组装播放/下载地址数据 =====
                // 将验证后的数组重新用 $$$ 连接，更新到 $v 数组
                // 过滤掉的无效播放源已被 unset，这里只保留有效数据
                $v['vod_play_from'] = (string)join('$$$', (array)$cj_play_from_arr);
                $v['vod_play_url'] = (string)join('$$$', (array)$cj_play_url_arr);
                $v['vod_play_server'] = (string)join('$$$', (array)$cj_play_server_arr);
                $v['vod_play_note'] = (string)join('$$$', (array)$cj_play_note_arr);
                $v['vod_down_from'] = (string)join('$$$', (array)$cj_down_from_arr);
                $v['vod_down_url'] = (string)join('$$$', (array)$cj_down_url_arr);
                $v['vod_down_server'] = (string)join('$$$', (array)$cj_down_server_arr);
                $v['vod_down_note'] = (string)join('$$$', (array)$cj_down_note_arr);

                // ========== 第八步：查询数据库判断是新增还是更新 ==========
                // 根据前面构建的 $where 条件和 inrule 规则查询数据库
                // 如果找到匹配的记录，则为更新操作；否则为新增操作
                if($blend===false){
                    // ===== 普通查询 =====
                    // blend=false 表示没有使用演员+导演混合查询
                    // 直接使用 $where 条件查询
                    $info = model('Vod')->where($where)->find();
                }
                else{
                    // ===== 演员+导演混合查询 (blend) =====
                    // blend=true 表示同时配置了演员(f)和导演(g)检测
                    // 使用复杂的 OR 查询逻辑: (导演匹配) OR (演员匹配)
                    // $GLOBALS['blend'] 在第1162行设置
                    $info = model('Vod')->where($where)
                        ->where(function($query) {
                            // 导演匹配条件
                            $query->where('vod_director',$GLOBALS['blend']['vod_director']);
                            // 演员匹配条件 (使用 whereOr)
                            if (!empty($GLOBALS['blend']['vod_id'])) {
                                // 使用 VodSearch 搜索索引优化: IN (id1, id2, id3)
                                $query->whereOr('vod_id', $GLOBALS['blend']['vod_id']);
                            } else {
                                // 使用 LIKE 模糊匹配: vod_actor LIKE '%演员1%' OR vod_actor LIKE '%演员2%'
                                $query->whereOr('vod_actor', $GLOBALS['blend']['vod_actor']);
                            }
                        })
                        ->find();
                }

                // ========== 自动生成TAG标签 ==========
                // 配置位置: 采集参数配置 → 自动生成TAG
                // 条件: 1) 配置开启 2) 采集数据无TAG 3) 数据库记录无TAG
                // 从视频名称和简介中提取关键词作为TAG
                // 优化自动生成TAG https://github.com/magicblack/maccms10/issues/1178
                if ($config['tag'] == 1 && empty($v['vod_tag']) && empty($info['vod_tag'])) {
                    $v['vod_tag'] = mac_filter_xss(mac_get_tag($v['vod_name'], $v['vod_content']));
                }

                // ========== 第九步：执行新增或更新操作 ==========
                if (!$info) {
                    // ========== 数据库中不存在，执行新增操作 ==========
                    if ($param['opt'] == 2) {
                        $des= lang('model/collect/not_check_add');
                    } else {
                        if ($param['filter'] == 1 || $param['filter'] == 2) {
                            $v['vod_play_from'] = (string)join('$$$', (array)$collect_filter['play'][$param['filter']]['cj_play_from_arr']);
                            $v['vod_play_url'] = (string)join('$$$', (array)$collect_filter['play'][$param['filter']]['cj_play_url_arr']);
                            $v['vod_play_server'] = (string)join('$$$', (array)$collect_filter['play'][$param['filter']]['cj_play_server_arr']);
                            $v['vod_play_note'] = (string)join('$$$', (array)$collect_filter['play'][$param['filter']]['cj_play_note_arr']);
                            $v['vod_down_from'] = (string)join('$$$', (array)$collect_filter['down'][$param['filter']]['cj_down_from_arr']);
                            $v['vod_down_url'] = (string)join('$$$', (array)$collect_filter['down'][$param['filter']]['cj_down_url_arr']);
                            $v['vod_down_server'] = (string)join('$$$', (array)$collect_filter['down'][$param['filter']]['cj_down_server_arr']);
                            $v['vod_down_note'] = (string)join('$$$', (array)$collect_filter['down'][$param['filter']]['cj_down_note_arr']);
                        }
                        $tmp = $this->syncImages($config_sync_pic,  $v['vod_pic'], 'vod');
                        $v['vod_pic'] = (string)$tmp['pic'];
                        $msg = $tmp['msg'];
                        $v = VodValidate::formatDataBeforeDb($v);
                        $vod_id = model('Vod')->insert($v, false, true);
                        if ($vod_id > 0) {
                            $vod_search_enabled && $vod_search->checkAndUpdateTopResults(['vod_id' => $vod_id] + $v, true);
                            $color = 'green';
                            $des = lang('model/collect/add_ok');
                        } else {
                            $color = 'red';
                            $des = 'vod insert failed';
                        }
                    }
                } else {
                    // ========== 更新已存在的视频 ==========
                    // 检查是否允许更新
                    if(empty($config['uprule'])){
                        // 未设置更新规则，跳过更新
                        $des = lang('model/collect/uprule_empty');
                    }
                    // ===== 锁定检测 (核心保护机制) =====
                    // vod_lock=1 表示视频被锁定，禁止采集更新
                    // 用途: 保护手动编辑的重要视频数据不被采集覆盖
                    // 设置方式: 后台视频列表 → 批量操作 → 锁定
                    elseif ($info['vod_lock'] == 1) {
                        // 已锁定的视频跳过更新，返回锁定提示
                        $des = lang('model/collect/data_lock');
                    }
                    elseif($param['opt'] == 1){
                        // opt=1 表示只采集新数据，不更新已存在的
                        $des = lang('model/collect/not_check_update');
                    }
                    else {
                        // ========== 执行更新操作准备 ==========
                        // 移除添加时间字段，更新操作不应修改此字段
                        unset($v['vod_time_add']);

                        $update = [];  // 待更新的字段数组
                        $ec=false;     // 更新标记：是否有字段需要更新

                        // ========== 播放源过滤处理 ==========
                        // filter=1: 仅播放源过滤
                        // filter=3: 播放源+下载源过滤
                        // 使用过滤后的数据替换原始采集数据
                        if($param['filter'] ==1 || $param['filter']==3){
                            $cj_play_from_arr = $collect_filter['play'][$param['filter']]['cj_play_from_arr'];
                            $cj_play_url_arr = $collect_filter['play'][$param['filter']]['cj_play_url_arr'];
                            $cj_play_server_arr = $collect_filter['play'][$param['filter']]['cj_play_server_arr'];
                            $cj_play_note_arr = $collect_filter['play'][$param['filter']]['cj_play_note_arr'];
                            $cj_down_from_arr = $collect_filter['down'][$param['filter']]['cj_down_from_arr'];
                            $cj_down_url_arr = $collect_filter['down'][$param['filter']]['cj_down_url_arr'];
                            $cj_down_server_arr = $collect_filter['down'][$param['filter']]['cj_down_server_arr'];
                            $cj_down_note_arr = $collect_filter['down'][$param['filter']]['cj_down_note_arr'];
                        }

                        // ========== 更新规则 uprule='a' : 更新播放地址 ==========
                        // 配置位置: 采集参数配置 → 数据更新规则 → 播放地址
                        // 功能: 将采集的新播放地址合并到现有数据中
                        if (strpos(',' . $config['uprule'], 'a')!==false && !empty($v['vod_play_from'])) {
                            $old_play_from = $info['vod_play_from'];
                            $old_play_url = $info['vod_play_url'];
                            $old_play_server = $info['vod_play_server'];
                            $old_play_note = $info['vod_play_note'];
                            foreach ($cj_play_from_arr as $k2 => $v2) {
                                $cj_play_from = $v2;
                                $cj_play_url = $cj_play_url_arr[$k2];
                                $cj_play_server = $cj_play_server_arr[$k2];
                                $cj_play_note = $cj_play_note_arr[$k2];
                                if ($cj_play_url == $info['vod_play_url']) {
                                    $des .= lang('model/collect/playurl_same');
                                } elseif (empty($cj_play_from)) {
                                    $des .= lang('model/collect/playfrom_empty');
                                } elseif (strpos('$$$'.$info['vod_play_from'].'$$$', '$$$'.$cj_play_from.'$$$') === false) {
                                    // 新类型播放组，加入
                                    $color = 'green';
                                    $des .= lang('model/collect/playgroup_add_ok',[$cj_play_from]);
                                    if(!empty($old_play_from)){
                                        $old_play_url .="$$$";
                                        $old_play_from .= "$$$" ;
                                        $old_play_server .= "$$$" ;
                                        $old_play_note .= "$$$" ;
                                    }
                                    $old_play_url .= "" . $cj_play_url;
                                    $old_play_from .= "" . $cj_play_from;
                                    $old_play_server .= "" . $cj_play_server;
                                    $old_play_note .= "" . $cj_play_note;
                                    $ec=true;
                                }  elseif (!empty($cj_play_url)) {
                                    // 同类型播放组
                                    $arr1 = explode("$$$", $old_play_url);
                                    $arr2 = explode("$$$", $old_play_from);
                                    $play_key = array_search($cj_play_from, $arr2);
                                    if ($arr1[$play_key] == $cj_play_url) {
                                        $des .= lang('model/collect/playgroup_same',[$cj_play_from]);;
                                    } else {
                                        $color = 'green';
                                        $des .= lang('model/collect/playgroup_update_ok',[$cj_play_from]);
                                        // 根据「地址二更规则」配置，替换或合并
                                        if ($config['urlrole'] == 1) {
                                            $tmp1 = explode('#',$arr1[$play_key]);
                                            $tmp2 = explode('#',$cj_play_url);
                                            $tmp1 = array_merge($tmp1,$tmp2);
                                            $tmp1 = array_unique($tmp1);
                                            $cj_play_url = join('#', (array)$tmp1);
                                            unset($tmp1,$tmp2);
                                        }
                                        $arr1[$play_key] = $cj_play_url;
                                        $ec=true;
                                    }
                                    $old_play_url = join('$$$', (array)$arr1);
                                }
                            }
                            if($ec) {
                                $update['vod_play_from'] = $old_play_from;
                                $update['vod_play_url'] = $old_play_url;
                                $update['vod_play_server'] = $old_play_server;
                                $update['vod_play_note'] = $old_play_note;
                            }
                        }

                        // ========== 更新规则 uprule='b' : 更新下载地址 ==========
                        // 配置位置: 采集参数配置 → 数据更新规则 → 下载地址
                        // 功能: 将采集的新下载地址合并到现有数据中
                        // 处理逻辑与播放地址相同
                        $ec=false;
                        if (strpos(',' . $config['uprule'], 'b')!==false && !empty($v['vod_down_from'])) {
                            $old_down_from = $info['vod_down_from'];
                            $old_down_url = $info['vod_down_url'];
                            $old_down_server = $info['vod_down_server'];
                            $old_down_note = $info['vod_down_note'];

                            foreach ($cj_down_from_arr as $k2 => $v2) {
                                $cj_down_from = $v2;
                                $cj_down_url = $cj_down_url_arr[$k2];
                                $cj_down_server = $cj_down_server_arr[$k2];
                                $cj_down_note = $cj_down_note_arr[$k2];


                                if ($cj_down_url == $info['vod_down_url']) {
                                    $des .= lang('model/collect/downurl_same');
                                } elseif (empty($cj_down_from)) {
                                    $des .= lang('model/collect/downfrom_empty');
                                } elseif (strpos('$$$'.$info['vod_down_from'].'$$$', '$$$'.$cj_down_from.'$$$')===false) {
                                    $color = 'green';
                                    $des .= lang('model/collect/downgroup_add_ok',[$cj_down_from]);
                                    if(!empty($old_down_from)){
                                        $old_down_url .="$$$";
                                        $old_down_from .= "$$$" ;
                                        $old_down_server .= "$$$" ;
                                        $old_down_note .= "$$$" ;
                                    }

                                    $old_down_url .= "" .$cj_down_url;
                                    $old_down_from .= "" .$cj_down_from;
                                    $old_down_server .= "" .$cj_down_server;
                                    $old_down_note .= "" .$cj_down_note;
                                    $ec=true;
                                } elseif (!empty($cj_down_url)) {
                                    $arr1 = explode("$$$", $old_down_url);
                                    $arr2 = explode("$$$", $old_down_from);
                                    $down_key = array_search($cj_down_from, $arr2);
                                    if ($arr1[$down_key] == $cj_down_url) {
                                        $des .= lang('model/collect/downgroup_same',[$cj_down_from]);
                                    } else {
                                        $color = 'green';
                                        $des .= lang('model/collect/downgroup_update_ok',[$cj_down_from]);
                                        // 根据「地址二更规则」配置，替换或合并
                                        // “采集参数配置--地址二更规则”配置需要对下载地址生效
                                        // https://github.com/magicblack/maccms10/issues/893
                                        if ($config['urlrole'] == 1) {
                                            $tmp1 = explode('#',$arr1[$down_key]);
                                            $tmp2 = explode('#',$cj_down_url);
                                            $tmp1 = array_merge($tmp1,$tmp2);
                                            $tmp1 = array_unique($tmp1);
                                            $cj_down_url = join('#', (array)$tmp1);
                                            unset($tmp1,$tmp2);
                                        }
                                        $arr1[$down_key] = $cj_down_url;
                                        $ec=true;
                                    }
                                    $old_down_url = join('$$$', (array)$arr1);
                                }
                            }

                            if($ec) {
                                $update['vod_down_from'] = $old_down_from;
                                $update['vod_down_url'] = $old_down_url;
                                $update['vod_down_server'] = $old_down_server;
                                $update['vod_down_note'] = $old_down_note;
                            }
                        }

                        if (strpos(',' . $config['uprule'], 'c')!==false && !empty($v['vod_serial']) && $v['vod_serial']!=$info['vod_serial']) {
                            $update['vod_serial'] = $v['vod_serial'];
                            // 连载数如果均为整数，则取较大值
                            // https://github.com/magicblack/maccms10/issues/878
                            if (floor($v['vod_serial']) == $v['vod_serial'] && floor($info['vod_serial']) == $info['vod_serial']) {
                                $update['vod_serial'] = max($v['vod_serial'], $info['vod_serial']);
                            }
                        }
                        if (strpos(',' . $config['uprule'], 'd')!==false && !empty($v['vod_remarks']) && $v['vod_remarks']!=$info['vod_remarks']) {
                            $update['vod_remarks'] = $v['vod_remarks'];
                        }
                        if (strpos(',' . $config['uprule'], 'e')!==false && !empty($v['vod_director']) && $v['vod_director']!=$info['vod_director']) {
                            $update['vod_director'] = $v['vod_director'];
                        }
                        if (strpos(',' . $config['uprule'], 'f')!==false && !empty($v['vod_actor']) && $v['vod_actor']!=$info['vod_actor']) {
                            $update['vod_actor'] = $v['vod_actor'];
                        }
                        if (strpos(',' . $config['uprule'], 'g')!==false && !empty($v['vod_year']) && $v['vod_year']!=$info['vod_year']) {
                            $update['vod_year'] = $v['vod_year'];
                        }
                        if (strpos(',' . $config['uprule'], 'h')!==false && !empty($v['vod_area']) && $v['vod_area']!=$info['vod_area']) {
                            $update['vod_area'] = $v['vod_area'];
                        }
                        if (strpos(',' . $config['uprule'], 'i')!==false && !empty($v['vod_lang']) && $v['vod_lang']!=$info['vod_lang']) {
                            $update['vod_lang'] = $v['vod_lang'];
                        }
                        if (strpos(',' . $config['uprule'], 'j')!==false && (substr($info["vod_pic"], 0, 4) == "http" || empty($info['vod_pic']) ) && ($v['vod_pic']!=$info['vod_pic'] || strpos($info['vod_pic'], '#err') !== false) ) {
                            $tmp = $this->syncImages($config_sync_pic, $v['vod_pic'],'vod');
                            $update['vod_pic'] = (string)$tmp['pic'];
                            $msg =$tmp['msg'];
                        }
                        if (strpos(',' . $config['uprule'], 'k')!==false && !empty($v['vod_content']) && $v['vod_content']!=$info['vod_content']) {
                            $update['vod_content'] = $v['vod_content'];
                        }
                        if (strpos(',' . $config['uprule'], 'l')!==false && !empty($v['vod_tag']) && $v['vod_tag']!=$info['vod_tag']) {
                            $update['vod_tag'] = $v['vod_tag'];
                        }
                        if (strpos(',' . $config['uprule'], 'm')!==false && !empty($v['vod_sub']) && $v['vod_sub']!=$info['vod_sub']) {
                            $update['vod_sub'] = $v['vod_sub'];
                        }
                        if (strpos(',' . $config['uprule'], 'n')!==false && !empty($v['vod_class']) && $v['vod_class']!=$info['vod_class']) {
                            $update['vod_class'] = mac_txt_merge($info['vod_class'], $v['vod_class']);
                        }
                        if (strpos(',' . $config['uprule'], 'o')!==false && !empty($v['vod_writer']) && $v['vod_writer']!=$info['vod_writer']) {
                            $update['vod_writer'] = $v['vod_writer'];
                        }
                        if (strpos(',' . $config['uprule'], 'p')!==false && !empty($v['vod_version']) && $v['vod_version']!=$info['vod_version']) {
                            $update['vod_version'] = $v['vod_version'];
                        }
                        if (strpos(',' . $config['uprule'], 'q')!==false && !empty($v['vod_state']) && $v['vod_state']!=$info['vod_state']) {
                            $update['vod_state'] = $v['vod_state'];
                        }
                        if (strpos(',' . $config['uprule'], 'r')!==false && !empty($v['vod_blurb']) && $v['vod_blurb']!=$info['vod_blurb']) {
                            $update['vod_blurb'] = $v['vod_blurb'];
                        }
                        if (strpos(',' . $config['uprule'], 's')!==false && !empty($v['vod_tv']) && $v['vod_tv']!=$info['vod_tv']) {
                            $update['vod_tv'] = $v['vod_tv'];
                        }
                        if (strpos(',' . $config['uprule'], 't')!==false && !empty($v['vod_weekday']) && $v['vod_weekday']!=$info['vod_weekday']) {
                            $update['vod_weekday'] = $v['vod_weekday'];
                        }
                        if (strpos(',' . $config['uprule'], 'u')!==false && !empty($v['vod_total']) && $v['vod_total']!=$info['vod_total']) {
                            $update['vod_total'] = $v['vod_total'];
                        }
                        if (strpos(',' . $config['uprule'], 'v')!==false && (isset($v['vod_isend']) && $v['vod_isend'] !== '') && $v['vod_isend']!=$info['vod_isend']) {
                            $update['vod_isend'] = $v['vod_isend'];
                        }
                        // ========== 采集更新剧情数据 (菜单: 视频-有分集剧情) ==========
                        // uprule 包含 'w' 时更新剧情数据
                        // 仅当采集数据有剧情且与现有数据不同时才更新
                        if (strpos(',' . $config['uprule'], 'w')!==false && !empty($v['vod_plot_name']) && $v['vod_plot_name']!=$info['vod_plot_name']) {
                            $update['vod_plot'] = 1;  // 标记有剧情
                            $update['vod_plot_name'] = $v['vod_plot_name'];    // 剧情标题
                            $update['vod_plot_detail'] = $v['vod_plot_detail']; // 剧情内容
                        }

                        if(count($update)>0){
                            $update['vod_time'] = time();
                            $where = [];
                            $where['vod_id'] = $info['vod_id'];
                            $update = VodValidate::formatDataBeforeDb($update);
                            $res = model('Vod')->where($where)->update($update);
                            $color = 'green';
                            if ($res === false) {

                            }
                        }
                        else{
                            $des = lang('model/collect/not_need_update');
                        }

                    }
                }
                if(Cache::has('vod_repeat_table_created_time')){
                    Cache::rm('vod_repeat_table_created_time');
                }
            }
            if($show==1) {
                mac_echo( ($k + 1) .'、'. $v['vod_name'] . " <font color='{$color}'>" .$des .'</font>'. $msg.'' );
            }
            else{
                return ['code'=>($color=='red' ? 1001 : 1),'msg'=>$des ];
            }
        }

        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_vod';
        if(ENTRANCE=='api'){
            Cache::rm($key);
            if ($data['page']['page'] < $data['page']['pagecount']) {
                $param['page'] = intval($data['page']['page']) + 1;
                $res = $this->vod($param);
                if($res['code']>1){
                    return $this->error($res['msg']);
                }
                $this->vod_data($param,$res );
            }
            mac_echo(lang('model/collect/is_over'));
            die;
        }

        if(empty($GLOBALS['config']['app']['collect_timespan'])){
            $GLOBALS['config']['app']['collect_timespan'] = 3;
        }
        if($show==1) {
            if ($param['ac'] == 'cjsel') {
                Cache::rm($key);
                mac_echo(lang('model/collect/is_over'));
                unset($param['ids']);
                $param['ac'] = 'list';
                $url = url('api') . '?' . http_build_query($param);
                $ref = $_SERVER["HTTP_REFERER"];
                if(!empty($ref)){
                   $url = $ref;
                }

                mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
            } else {
                if ($data['page']['page'] >= $data['page']['pagecount']) {
                    Cache::rm($key);
                    mac_echo(lang('model/collect/is_over'));
                    unset($param['page'],$param['ids']);
                    $param['ac'] = 'list';
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                } else {
                    $param['page'] = intval($data['page']['page']) + 1;
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan'] );
                }
            }
        }
    }

    public function art_json($param)
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];

        if($param['ac']!='list'){
            $url_param['ac'] = 'detail';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }

        $url .= http_build_query($url_param). base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $json = json_decode($html,true);
        if(!$json){
            return ['code'=>1002, 'msg'=>lang('model/collect/json_err') . ': ' . mb_substr($html, 0, 15)];
        }

        $array_page = [];
        $array_page['page'] = $json['page'];
        $array_page['pagecount'] = $json['pagecount'];
        $array_page['pagesize'] = $json['limit'];
        $array_page['recordcount'] = $json['total'];
        $array_page['url'] = $url;

        $type_list = model('Type')->getCache('type_list');
        $bind_list = config('bind');

        $key = 0;
        $array_data = [];
        foreach($json['list'] as $key=>$v){
            $array_data[$key] = $v;
            $bind_key = $param['cjflag'] .'_'.$v['type_id'];
            if($bind_list[$bind_key] >0){
                $array_data[$key]['type_id'] = $bind_list[$bind_key];
            }
            else{
                $array_data[$key]['type_id'] = 0;
            }
        }

        $array_type = [];
        $key=0;
        //分类列表
        if($param['ac'] == 'list'){
            foreach($json['class'] as $k=>$v){
                $array_type[$key]['type_id'] = $v['type_id'];
                $array_type[$key]['type_name'] = $v['type_name'];
                $key++;
            }
        }

        $res = ['code'=>1, 'msg'=>'ok', 'page'=>$array_page, 'type'=>$array_type, 'data'=>$array_data ];
        return $res;
    }

    public function manga_json($param)
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];

        if($param['ac']!='list'){
            $url_param['ac'] = 'detail';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }

        $url .= http_build_query($url_param). base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $json = json_decode($html,true);
        if(!$json){
            return ['code'=>1002, 'msg'=>lang('model/collect/json_err') . ': ' . mb_substr($html, 0, 15)];
        }

        $array_page = [];
        $array_page['page'] = $json['page'];
        $array_page['pagecount'] = $json['pagecount'];
        $array_page['pagesize'] = $json['limit'];
        $array_page['recordcount'] = $json['total'];
        $array_page['url'] = $url;

        $type_list = model('Type')->getCache('type_list');
        $bind_list = config('bind');

        $key = 0;
        $array_data = [];
        foreach($json['list'] as $key=>$v){
            $array_data[$key] = $v;
            $bind_key = $param['cjflag'] .'_'.$v['type_id'];
            if($bind_list[$bind_key] >0){
                $array_data[$key]['type_id'] = $bind_list[$bind_key];
            }
            else{
                $array_data[$key]['type_id'] = 0;
            }
        }

        $array_type = [];
        $key=0;
        //分类列表
        if($param['ac'] == 'list'){
            foreach($json['class'] as $k=>$v){
                $array_type[$key]['type_id'] = $v['type_id'];
                $array_type[$key]['type_name'] = $v['type_name'];
                $key++;
            }
        }

        $res = ['code'=>1, 'msg'=>'ok', 'page'=>$array_page, 'type'=>$array_type, 'data'=>$array_data ];
        return $res;
    }

    public function art_data($param,$data,$show=1)
    {
        if($show==1) {
            mac_echo('[' . __FUNCTION__ . '] ' . lang('model/collect/data_tip1',[$data['page']['page'],$data['page']['pagecount'],$data['page']['url']]));
        }

        $config = config('maccms.collect');
        $config = $config['art'];
        $config_sync_pic = $param['sync_pic_opt'] > 0 ? $param['sync_pic_opt'] : $config['pic'];

        $type_list = model('Type')->getCache('type_list');
        $filter_arr = explode(',',$config['filter']); $filter_arr = array_filter($filter_arr);
        $pse_rnd = explode('#',$config['words']); $pse_rnd = array_filter($pse_rnd);
        $pse_syn = mac_txt_explain($config['thesaurus'], true);


        foreach($data['data'] as $k=>$v){
            $color='red';
            $des='';
            $msg='';
            $tmp='';

            if($v['type_id'] ==0){
                $des = lang('model/collect/type_err');
            }
            elseif(empty($v['art_name'])) {
                $des = lang('model/collect/name_err');
            }
            elseif( mac_array_filter($filter_arr,$v['art_name']) !==false) {
                $des = lang('model/collect/name_in_filter_err');
            }
            else {
                unset($v['art_id']);

                foreach($v as $k2=>$v2){
                    if(strpos($k2,'_content')===false) {
                        $v[$k2] = strip_tags($v2);
                    }
                }
                $v['art_name'] = trim($v['art_name']);
                $v['type_id_1'] = intval($type_list[$v['type_id']]['type_pid']);
                $v['art_en'] = Pinyin::get($v['art_name']);
                $v['art_letter'] = strtoupper(substr($v['art_en'],0,1));
                $v['art_time_add'] = time();
                $v['art_time'] = time();
                $v['art_status'] = intval($config['status']);
                $v['art_lock'] = intval($v['art_lock']);
                if(!empty($v['art_status'])) {
                    $v['art_status'] = intval($v['art_status']);
                }
                $v['art_level'] = intval($v['art_level']);
                $v['art_hits'] = intval($v['art_hits']);
                $v['art_hits_day'] = intval($v['art_hits_day']);
                $v['art_hits_week'] = intval($v['art_hits_week']);
                $v['art_hits_month'] = intval($v['art_hits_month']);
                $v['art_stint'] = intval($v['art_stint']);

                $v['art_up'] = intval($v['art_up']);
                $v['art_down'] = intval($v['art_down']);


                $v['art_score'] = floatval($v['art_score']);
                $v['art_score_all'] = intval($v['art_score_all']);
                $v['art_score_num'] = intval($v['art_score_num']);

                if($config['hits_start']>0 && $config['hits_end']>0) {
                    $v['art_hits'] = rand($config['hits_start'], $config['hits_end']);
                    $v['art_hits_day'] = rand($config['hits_start'], $config['hits_end']);
                    $v['art_hits_week'] = rand($config['hits_start'], $config['hits_end']);
                    $v['art_hits_month'] = rand($config['hits_start'], $config['hits_end']);
                }

                if($config['updown_start']>0 && $config['updown_end']){
                    $v['art_up'] = rand($config['updown_start'], $config['updown_end']);
                    $v['art_down'] = rand($config['updown_start'], $config['updown_end']);
                }

                if($config['score']==1) {
                    $v['art_score_num'] = rand(1, 1000);
                    $v['art_score_all'] = $v['art_score_num'] * rand(1, 10);
                    $v['art_score'] = round($v['art_score_all'] / $v['art_score_num'], 1);
                }

                if ($config['psernd'] == 1) {
                    $v['art_content'] = mac_rep_pse_rnd($pse_rnd, $v['art_content']);
                }
                if ($config['psesyn'] == 1) {
                    $v['art_content'] = mac_rep_pse_syn($pse_syn, $v['art_content']);
                }

                if(empty($v['art_blurb'])){
                    $v['art_blurb'] = mac_substring( strip_tags( str_replace('$$$','',$v['art_content']) ) ,100);
                }

                if ($config['tag'] == 1) {
                    $v['art_tag'] = mac_filter_xss(mac_get_tag($v['art_name'], $v['art_content']));
                }

                $where = [];
                $where['art_name'] = $v['art_name'];
                if (strpos($config['inrule'], 'b')!==false) {
                    $where['type_id'] = $v['type_id'];
                }

                //验证地址
                $cj_title_arr = explode('$$$',$v['art_title'] );
                $cj_note_arr = explode('$$$',$v['art_note']);
                $cj_content_arr = explode('$$$',$v['art_content']);

                $tmp_title_arr=[];
                $tmp_note_arr=[];
                $tmp_content_arr=[];
                foreach($cj_content_arr as $kk=>$vv){
                    $tmp_content_arr[] = $vv;
                    $tmp_title_arr[] = $cj_title_arr[$kk];
                    $tmp_note_arr[] = $cj_note_arr[$kk];
                }
                $v['art_title'] = join('$$$', (array)$tmp_title_arr);
                $v['art_note'] = join('$$$', (array)$tmp_note_arr);
                $v['art_content'] = join('$$$', (array)$tmp_content_arr);


                $info = model('Art')->where($where)->find();
                if (!$info) {
                    $tmp = $this->syncImages($config_sync_pic, $v['art_pic'],'art');
                    $v['art_pic'] = (string)$tmp['pic'];

                    $msg = $tmp['msg'];
                    $res = model('Art')->insert($v);
                    if($res===false){

                    }
                    $color ='green';
                    $des= lang('model/collect/add_ok');
                }
                else {


                    if(empty($config['uprule'])){
                        $des = lang('model/collect/uprule_empty');
                    }
                    elseif($info['art_lock'] == 1) {
                        $des = lang('model/collect/data_lock');
                    }
                    else {
                        unset($v['art_time_add']);

                        $old_art_title = $info['art_title'];
                        $old_art_note = $info['art_note'];
                        $old_art_content = $info['art_content'];

                        $cj_art_title = $v['art_title'];
                        $cj_art_note = $v['art_note'];
                        $cj_art_content = $v['art_content'];

                        $rc=true;

                        if($rc){
                            $update=[];

                            if(strpos(','.$config['uprule'],'a')!==false && !empty($v['art_content']) && $v['art_content']!=$info['art_content']){
                                $update['art_content'] = $v['art_content'];
                            }
                            if(strpos(','.$config['uprule'],'b')!==false && !empty($v['art_author']) && $v['art_author']!=$info['art_author']){
                                $update['art_author'] = $v['art_author'];
                            }
                            if(strpos(','.$config['uprule'],'c')!==false && !empty($v['art_from']) && $v['art_from']!=$info['art_from']){
                                $update['art_from'] = $v['art_from'];
                            }

                            if(strpos(','.$config['uprule'],'d')!==false && (substr($info["art_pic"], 0, 4) == "http" || empty($info['art_pic']))  && ($v['art_pic']!=$info['art_pic'] || strpos($info['art_pic'], '#err') !== false) ){
                                $tmp = $this->syncImages($config_sync_pic, $v['art_pic'],'art');
                                $update['art_pic'] = (string)$tmp['pic'];
                                $msg =$tmp['msg'];
                            }
                            if(strpos(','.$config['uprule'],'e')!==false && !empty($v['art_tag']) && $v['art_tag']!=$info['art_tag']){
                                $update['art_tag'] = $v['art_tag'];
                            }
                            if(strpos(','.$config['uprule'],'f')!==false && !empty($v['art_blurb']) && $v['art_blurb']!=$info['art_blurb']){
                                $update['art_blurb'] = $v['art_blurb'];
                            }


                            if(count($update)>0){
                                $update['art_time'] = time();
                                $where = [];
                                $where['art_id'] = $info['art_id'];
                                $res = model('Art')->where($where)->update($update);
                                $color = 'green';
                                if($res===false){

                                }
                            }
                            else{
                                $des = lang('model/collect/not_need_update');
                            }
                        }

                    }
                }
            }
            if($show==1) {
                mac_echo( ($k + 1) . $v['art_name'] . "<font color=$color>" .$des .'</font>'. $msg . '');
            }
            else{
                return ['code'=>($color=='red' ? 1001 : 1),'msg'=> $v['art_name'] .' '.$des ];
            }
        }

        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_art';
        if(ENTRANCE=='api'){
            Cache::rm($key);
            if ($data['page']['page'] < $data['page']['pagecount']) {
                $param['page'] = intval($data['page']['page']) + 1;
                $res = $this->art($param);
                if($res['code']>1){
                    return $this->error($res['msg']);
                }
                $this->art_data($param,$res );
            }
            mac_echo(lang('model/collect/is_over'));
            die;
        }

        if(empty($GLOBALS['config']['app']['collect_timespan'])){
            $GLOBALS['config']['app']['collect_timespan'] = 3;
        }

        if($show==1) {
            if ($param['ac'] == 'cjsel') {
                Cache::rm($key);
                mac_echo(lang('model/collect/is_over'));
                unset($param['ids']);
                $param['ac'] = 'list';
                $url = url('api') . '?' . http_build_query($param);
                $ref = $_SERVER["HTTP_REFERER"];
                if(!empty($ref)){
                    $url = $ref;
                }
                mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
            } else {
                if ($data['page']['page'] >= $data['page']['pagecount']) {
                    Cache::rm($key);
                    mac_echo(lang('model/collect/is_over'));
                    unset($param['page']);
                    $param['ac'] = 'list';
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                } else {
                    $param['page'] = intval($data['page']['page']) + 1;
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                }
            }
        }
    }

    public function actor_json($param)
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];

        if($param['ac']!='list'){
            $url_param['ac'] = 'detail';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }
        $url .= http_build_query($url_param).base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $json = json_decode($html,true);
        if(!$json){
            return ['code'=>1002, 'msg'=>lang('model/collect/json_err') . ': ' . mb_substr($html, 0, 15)];
        }

        $array_page = [];
        $array_page['page'] = $json['page'];
        $array_page['pagecount'] = $json['pagecount'];
        $array_page['pagesize'] = $json['limit'];
        $array_page['recordcount'] = $json['total'];
        $array_page['url'] = $url;

        $type_list = model('Type')->getCache('type_list');
        $bind_list = config('bind');

        $key = 0;
        $array_data = [];
        foreach($json['list'] as $key=>$v){
            $array_data[$key] = $v;
            $bind_key = $param['cjflag'] .'_'.$v['type_id'];
            if($bind_list[$bind_key] >0){
                $array_data[$key]['type_id'] = $bind_list[$bind_key];
            }
            else{
                $array_data[$key]['type_id'] = 0;
            }
        }

        $array_type = [];
        $key=0;
        //分类列表
        if($param['ac'] == 'list'){
            foreach($json['class'] as $k=>$v){
                $array_type[$key]['type_id'] = $v['type_id'];
                $array_type[$key]['type_name'] = $v['type_name'];
                $key++;
            }
        }

        $res = ['code'=>1, 'msg'=>'ok', 'page'=>$array_page, 'type'=>$array_type, 'data'=>$array_data ];
        return $res;
    }

    public function actor_data($param,$data,$show=1)
    {
        if($show==1) {
            mac_echo('[' . __FUNCTION__ . '] ' . lang('model/collect/data_tip1',[$data['page']['page'],$data['page']['pagecount'],$data['page']['url']]));
        }

        $config = config('maccms.collect');
        $config = $config['actor'];
        $config_sync_pic = $param['sync_pic_opt'] > 0 ? $param['sync_pic_opt'] : $config['pic'];

        $type_list = model('Type')->getCache('type_list');
        $filter_arr = explode(',',$config['filter']); $filter_arr = array_filter($filter_arr);
        $pse_rnd = explode('#',$config['words']); $pse_rnd = array_filter($pse_rnd);
        $pse_syn = mac_txt_explain($config['thesaurus'], true);

        foreach($data['data'] as $k=>$v){

            $color='red';
            $des='';
            $msg='';
            $tmp='';

            if($v['type_id'] ==0){
                $des = lang('model/collect/type_err');
            }
            elseif(empty($v['actor_name']) || empty($v['actor_sex'])) {
                $des = lang('odel/collect/actor_data_require');
            }
            elseif( mac_array_filter($filter_arr,$v['actor_name'])!==false) {
                $des = lang('model/collect/name_in_filter_err');
            }
            else {
                unset($v['actor_id']);

                foreach($v as $k2=>$v2){
                    if(strpos($k2,'_content')===false) {
                        $v[$k2] = strip_tags($v2);
                    }
                }
                $v['actor_name'] = trim($v['actor_name']);
                $v['type_id_1'] = intval($type_list[$v['type_id']]['type_pid']);
                $v['actor_en'] = Pinyin::get($v['actor_name']);
                $v['actor_letter'] = strtoupper(substr($v['actor_en'],0,1));
                $v['actor_time_add'] = time();
                $v['actor_time'] = time();
                $v['actor_status'] = intval($config['status']);
                $v['actor_lock'] = intval($v['actor_lock']);
                if(!empty($v['actor_status'])) {
                    $v['actor_status'] = intval($v['actor_status']);
                }
                $v['actor_level'] = intval($v['actor_level']);
                $v['actor_hits'] = intval($v['actor_hits']);
                $v['actor_hits_day'] = intval($v['actor_hits_day']);
                $v['actor_hits_week'] = intval($v['actor_hits_week']);
                $v['actor_hits_month'] = intval($v['actor_hits_month']);

                $v['actor_up'] = intval($v['actor_up']);
                $v['actor_down'] = intval($v['actor_down']);

                $v['actor_score'] = floatval($v['actor_score']);
                $v['actor_score_all'] = intval($v['actor_score_all']);
                $v['actor_score_num'] = intval($v['actor_score_num']);

                if($config['hits_start']>0 && $config['hits_end']>0) {
                    $v['actor_hits'] = rand($config['hits_start'], $config['hits_end']);
                    $v['actor_hits_day'] = rand($config['hits_start'], $config['hits_end']);
                    $v['actor_hits_week'] = rand($config['hits_start'], $config['hits_end']);
                    $v['actor_hits_month'] = rand($config['hits_start'], $config['hits_end']);
                }

                if($config['updown_start']>0 && $config['updown_end']){
                    $v['actor_up'] = rand($config['updown_start'], $config['updown_end']);
                    $v['actor_down'] = rand($config['updown_start'], $config['updown_end']);
                }

                if($config['score']==1) {
                    $v['actor_score_num'] = rand(1, 1000);
                    $v['actor_score_all'] = $v['actor_score_num'] * rand(1, 10);
                    $v['actor_score'] = round($v['actor_score_all'] / $v['actor_score_num'], 1);
                }

                if ($config['psernd'] == 1) {
                    $v['actor_content'] = mac_rep_pse_rnd($pse_rnd, $v['actor_content']);
                }
                if ($config['psesyn'] == 1) {
                    $v['actor_content'] = mac_rep_pse_syn($pse_syn, $v['actor_content']);
                }

                if(empty($v['actor_blurb'])){
                    $v['actor_blurb'] = mac_substring( strip_tags($v['actor_content']) ,100);
                }

                $where = [];
                $where['actor_name'] = $v['actor_name'];
                if (strpos($config['inrule'], 'b')!==false) {
                    $where['actor_sex'] = $v['actor_sex'];
                }
                if (strpos($config['inrule'], 'c')!==false) {
                    $where['type_id'] = $v['type_id'];
                }

                $info = model('Actor')->where($where)->find();
                if (!$info) {
                    $tmp = $this->syncImages($config_sync_pic, $v['actor_pic'],'actor');
                    $v['actor_pic'] = $tmp['pic'];
                    $msg = $tmp['msg'];
                    $res = model('Actor')->insert($v);
                    if($res===false){

                    }
                    $color ='green';
                    $des= lang('model/collect/add_ok');
                } else {

                    if(empty($config['uprule'])){
                        $des = lang('model/collect/uprule_empty');
                    }
                    elseif ($info['actor_lock'] == 1) {
                        $des = lang('model/collect/data_lock');
                    }
                    else {
                        unset($v['actor_time_add']);
                        $rc=true;
                        if($rc){
                            $update=[];

                            if(strpos(','.$config['uprule'],'a')!==false && !empty($v['actor_content']) && $v['actor_content']!=$info['actor_content']){
                                $update['actor_content'] = $v['actor_content'];
                            }
                            if(strpos(','.$config['uprule'],'b')!==false && !empty($v['actor_blurb']) && $v['actor_blurb']!=$info['actor_blurb']){
                                $update['actor_blurb'] = $v['actor_blurb'];
                            }
                            if(strpos(','.$config['uprule'],'c')!==false && !empty($v['actor_remarks']) && $v['actor_remarks']!=$info['actor_remarks']){
                                $update['actor_remarks'] = $v['actor_remarks'];
                            }
                            if(strpos(','.$config['uprule'],'d')!==false && !empty($v['actor_works']) && $v['actor_works']!=$info['actor_works']){
                                $update['actor_works'] = $v['actor_works'];
                            }
                            if(strpos(','.$config['uprule'],'e')!==false && (substr($info["actor_pic"], 0, 4) == "http" ||empty($info['actor_pic']) ) && ($v['actor_pic']!=$info['actor_pic'] || strpos($info['actor_pic'], '#err') !== false) ){
                                $tmp = $this->syncImages($config_sync_pic, $v['actor_pic'],'actor');
                                $update['actor_pic'] =$tmp['pic'];
                                $msg =$tmp['msg'];
                            }

                            if(count($update)>0){
                                $update['actor_time'] = time();
                                $where = [];
                                $where['actor_id'] = $info['actor_id'];
                                $res = model('Actor')->where($where)->update($update);
                                $color = 'green';
                                if($res===false){

                                }
                            }
                            else{
                                $des = lang('model/collect/not_need_update');
                            }
                        }

                    }
                }
            }
            if($show==1) {
                mac_echo( ($k + 1) . $v['actor_name'] . "<font color=$color>" .$des .'</font>'. $msg . '');
            }
            else{
                return ['code'=>($color=='red' ? 1001 : 1),'msg'=> $v['actor_name'] .' '.$des ];
            }
        }

        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_actor';
        if(ENTRANCE=='api'){
            Cache::rm($key);
            if ($data['page']['page'] < $data['page']['pagecount']) {
                $param['page'] = intval($data['page']['page']) + 1;
                $res = $this->actor($param);
                if($res['code']>1){
                    return $this->error($res['msg']);
                }
                $this->actor_data($param,$res );
            }
            mac_echo(lang('model/collect/is_over'));
            die;
        }

        if(empty($GLOBALS['config']['app']['collect_timespan'])){
            $GLOBALS['config']['app']['collect_timespan'] = 3;
        }

        if($show==1) {
            if ($param['ac'] == 'cjsel') {
                Cache::rm($key);
                mac_echo(lang('model/collect/is_over'));
                unset($param['ids']);
                $param['ac'] = 'list';
                $url = url('api') . '?' . http_build_query($param);
                $ref = $_SERVER["HTTP_REFERER"];
                if(!empty($ref)){
                    $url = $ref;
                }
                mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
            } else {
                if ($data['page']['page'] >= $data['page']['pagecount']) {
                    Cache::rm($key);
                    mac_echo(lang('model/collect/is_over'));
                    unset($param['page']);
                    $param['ac'] = 'list';
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                } else {
                    $param['page'] = intval($data['page']['page']) + 1;
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                }
            }
        }
    }

    public function role_json($param)
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];

        if($param['ac']!='list'){
            $url_param['ac'] = 'detail';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }
        $url .= http_build_query($url_param).base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $json = json_decode($html,true);
        if(!$json){
            return ['code'=>1002, 'msg'=>lang('model/collect/json_err') . ': ' . mb_substr($html, 0, 15)];
        }

        $array_page = [];
        $array_page['page'] = $json['page'];
        $array_page['pagecount'] = $json['pagecount'];
        $array_page['pagesize'] = $json['limit'];
        $array_page['recordcount'] = $json['total'];
        $array_page['url'] = $url;

        $key = 0;
        $array_data = [];
        foreach($json['list'] as $key=>$v){
            $array_data[$key] = $v;
        }


        $res = ['code'=>1, 'msg'=>'ok', 'page'=>$array_page, 'data'=>$array_data ];
        return $res;
    }

    public function role_data($param,$data,$show=1)
    {
        if($show==1) {
            mac_echo('[' . __FUNCTION__ . '] ' . lang('model/collect/data_tip1',[$data['page']['page'],$data['page']['pagecount'],$data['page']['url']]));
        }

        $config = config('maccms.collect');
        $config = $config['role'];
        $config_sync_pic = $param['sync_pic_opt'] > 0 ? $param['sync_pic_opt'] : $config['pic'];

        $filter_arr = explode(',',$config['filter']); $filter_arr = array_filter($filter_arr);
        $pse_rnd = explode('#',$config['words']); $pse_rnd = array_filter($pse_rnd);
        $pse_syn = mac_txt_explain($config['thesaurus'], true);

        foreach($data['data'] as $k=>$v){

            $color='red';
            $des='';
            $msg='';
            $tmp='';

            if(empty($v['role_name']) || empty($v['role_actor']) || empty($v['vod_name']) ) {
                $des = lang('model/collect/role_data_require');
            }
            elseif( mac_array_filter($filter_arr,$v['role_name']) !==false) {
                $des = lang('model/collect/name_in_filter_err');
            }
            else {
                unset($v['role_id']);

                foreach($v as $k2=>$v2){
                    if(strpos($k2,'_content')===false) {
                        $v[$k2] = strip_tags($v2);
                    }
                }

                $v['role_en'] = Pinyin::get($v['role_name']);
                $v['role_letter'] = strtoupper(substr($v['role_en'],0,1));
                $v['role_time_add'] = time();
                $v['role_time'] = time();
                $v['role_status'] = intval($config['status']);
                $v['role_lock'] = intval($v['role_lock']);
                if(!empty($v['role_status'])) {
                    $v['role_status'] = intval($v['role_status']);
                }
                $v['role_level'] = intval($v['role_level']);
                $v['role_hits'] = intval($v['role_hits']);
                $v['role_hits_day'] = intval($v['role_hits_day']);
                $v['role_hits_week'] = intval($v['role_hits_week']);
                $v['role_hits_month'] = intval($v['role_hits_month']);

                $v['role_up'] = intval($v['role_up']);
                $v['role_down'] = intval($v['role_down']);

                $v['role_score'] = floatval($v['role_score']);
                $v['role_score_all'] = intval($v['role_score_all']);
                $v['role_score_num'] = intval($v['role_score_num']);

                if($config['hits_start']>0 && $config['hits_end']>0) {
                    $v['role_hits'] = rand($config['hits_start'], $config['hits_end']);
                    $v['role_hits_day'] = rand($config['hits_start'], $config['hits_end']);
                    $v['role_hits_week'] = rand($config['hits_start'], $config['hits_end']);
                    $v['role_hits_month'] = rand($config['hits_start'], $config['hits_end']);
                }

                if($config['updown_start']>0 && $config['updown_end']){
                    $v['role_up'] = rand($config['updown_start'], $config['updown_end']);
                    $v['role_down'] = rand($config['updown_start'], $config['updown_end']);
                }

                if($config['score']==1) {
                    $v['role_score_num'] = rand(1, 1000);
                    $v['role_score_all'] = $v['role_score_num'] * rand(1, 10);
                    $v['role_score'] = round($v['role_score_all'] / $v['role_score_num'], 1);
                }

                if ($config['psernd'] == 1) {
                    $v['role_content'] = mac_rep_pse_rnd($pse_rnd, $v['role_content']);
                }
                if ($config['psesyn'] == 1) {
                    $v['role_content'] = mac_rep_pse_syn($pse_syn, $v['role_content']);
                }

                $where = [];
                $where['role_name'] = $v['role_name'];
                $where['role_actor'] = $v['role_actor'];

                $where2 = [];
                $blend = false;

                if(!empty($v['douban_id'])){
                    $where2['vod_douban_id'] = ['eq',$v['douban_id']];
                    unset($v['douban_id']);
                }
                else{
                    $where2['vod_name'] = ['eq',$v['vod_name']];
                }

                if (strpos($config['inrule'], 'c')!==false) {
                    $where2['vod_actor'] = ['like', mac_like_arr($v['role_actor']), 'OR'];
                }
                if (strpos($config['inrule'], 'd')!==false) {
                    $where2['vod_director'] = ['like', mac_like_arr($v['role_actor']), 'OR'];
                }
                if(!empty($where2['vod_actor']) && !empty($where2['vod_director'])){
                    $blend = true;
                    $GLOBALS['blend'] = [
                        'vod_actor' => $where2['vod_actor'],
                        'vod_director' => $where2['vod_director']
                    ];
                    unset($where2['vod_actor'],$where2['vod_director']);
                }

                if($blend===false){
                    $vod_info = model('Vod')->where($where2)->find();

                }
                else{
                    $vod_info = model('Vod')->where($where2)
                        ->where(function($query){
                            $query->where('vod_director',$GLOBALS['blend']['vod_director'])
                                ->whereOr('vod_actor',$GLOBALS['blend']['vod_actor']);
                        })
                        ->find();
                }

                if (!$vod_info) {
                    $des = lang('model/collect/not_found_rel_vod');
                }
                else {
                    $v['role_rid'] = $vod_info['vod_id'];
                    $where['role_rid'] = $vod_info['vod_id'];
                    $info = model('Role')->where($where)->find();
                    if (!$info) {
                        $tmp = $this->syncImages($config_sync_pic,  $v['role_pic'], 'role');
                        $v['role_pic'] = $tmp['pic'];
                        $msg = $tmp['msg'];
                        $res = model('Role')->insert($v);
                        if ($res === false) {

                        }
                        $color = 'green';
                        $des = lang('model/collect/add_ok');
                    } else {

                        if(empty($config['uprule'])){
                            $des = lang('model/collect/uprule_empty');
                        }
                        elseif ($info['role_lock'] == 1) {
                            $des = lang('model/collect/data_lock');
                        }
                        else {
                            unset($v['role_time_add']);
                            $rc = true;
                            if ($rc) {
                                $update = [];

                                if (strpos(',' . $config['uprule'], 'a') !== false && !empty($v['role_content']) && $v['role_content'] != $info['role_content']) {
                                    $update['role_content'] = $v['role_content'];
                                }
                                if (strpos(',' . $config['uprule'], 'b') !== false && !empty($v['role_remarks']) && $v['role_remarks'] != $info['role_remarks']) {
                                    $update['role_remarks'] = $v['role_remarks'];
                                }
                                if (strpos(',' . $config['uprule'], 'c') !== false && (substr($info["role_pic"], 0, 4) == "http" || empty($info['role_pic'])) && ($v['role_pic'] != $info['role_pic'] || strpos($info['role_pic'], '#err') !== false)) {
                                    $tmp = $this->syncImages($config_sync_pic,  $v['role_pic'], 'role');
                                    $update['role_pic'] = $tmp['pic'];
                                    $msg = $tmp['msg'];
                                }

                                if(count($update)>0){
                                    $update['role_time'] = time();
                                    $where = [];
                                    $where['role_id'] = $info['role_id'];
                                    $res = model('Role')->where($where)->update($update);
                                    $color = 'green';
                                    if ($res === false) {

                                    }
                                }
                                else{
                                    $des = lang('model/collect/not_need_update');
                                }
                            }

                        }
                    }
                }

            }
            if($show==1) {
                mac_echo( ($k + 1) . $v['role_name'] . "<font color=$color>" .$des .'</font>'. $msg . '');
            }
            else{
                return ['code'=>($color=='red' ? 1001 : 1),'msg'=> $v['role_name'] .' '.$des ];
            }
        }

        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_role';
        if(ENTRANCE=='api'){
            Cache::rm($key);
            if ($data['page']['page'] < $data['page']['pagecount']) {
                $param['page'] = intval($data['page']['page']) + 1;
                $res = $this->role($param);
                if($res['code']>1){
                    return $this->error($res['msg']);
                }
                $this->role_data($param,$res );
            }
            mac_echo(lang('model/collect/is_over'));
            die;
        }

        if(empty($GLOBALS['config']['app']['collect_timespan'])){
            $GLOBALS['config']['app']['collect_timespan'] = 3;
        }

        if($show==1) {
            if ($param['ac'] == 'cjsel') {
                Cache::rm($key);
                mac_echo(lang('model/collect/is_over'));
                unset($param['ids']);
                $param['ac'] = 'list';
                $url = url('api') . '?' . http_build_query($param);
                $ref = $_SERVER["HTTP_REFERER"];
                if(!empty($ref)){
                    $url = $ref;
                }
                mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
            } else {
                if ($data['page']['page'] >= $data['page']['pagecount']) {
                    Cache::rm($key);
                    mac_echo(lang('model/collect/is_over'));
                    unset($param['page']);
                    $param['ac'] = 'list';
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                } else {
                    $param['page'] = intval($data['page']['page']) + 1;
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                }
            }
        }
    }

    public function website_json($param)
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];

        if($param['ac']!='list'){
            $url_param['ac'] = 'detail';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }
        $url .= http_build_query($url_param).base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $json = json_decode($html,true);
        if(!$json){
            return ['code'=>1002, 'msg'=>lang('model/collect/json_err') . ': ' . mb_substr($html, 0, 15)];
        }

        $array_page = [];
        $array_page['page'] = $json['page'];
        $array_page['pagecount'] = $json['pagecount'];
        $array_page['pagesize'] = $json['limit'];
        $array_page['recordcount'] = $json['total'];
        $array_page['url'] = $url;

        $type_list = model('Type')->getCache('type_list');
        $bind_list = config('bind');

        $key = 0;
        $array_data = [];
        foreach($json['list'] as $key=>$v){
            $array_data[$key] = $v;
            $bind_key = $param['cjflag'] .'_'.$v['type_id'];
            if($bind_list[$bind_key] >0){
                $array_data[$key]['type_id'] = $bind_list[$bind_key];
            }
            else{
                $array_data[$key]['type_id'] = 0;
            }
        }

        $array_type = [];
        $key=0;
        //分类列表
        if($param['ac'] == 'list'){
            foreach($json['class'] as $k=>$v){
                $array_type[$key]['type_id'] = $v['type_id'];
                $array_type[$key]['type_name'] = $v['type_name'];
                $key++;
            }
        }

        $res = ['code'=>1, 'msg'=>'ok', 'page'=>$array_page, 'type'=>$array_type, 'data'=>$array_data ];
        return $res;
    }

    public function website_data($param,$data,$show=1)
    {
        if($show==1) {
            mac_echo('[' . __FUNCTION__ . '] ' . lang('model/collect/data_tip1',[$data['page']['page'],$data['page']['pagecount'],$data['page']['url']]));
        }

        $config = config('maccms.collect');
        $config = $config['website'];
        $config_sync_pic = $param['sync_pic_opt'] > 0 ? $param['sync_pic_opt'] : $config['pic'];

        $type_list = model('Type')->getCache('type_list');
        $filter_arr = explode(',',$config['filter']); $filter_arr = array_filter($filter_arr);
        $pse_rnd = explode('#',$config['words']); $pse_rnd = array_filter($pse_rnd);
        $pse_syn = mac_txt_explain($config['thesaurus'], true);

        foreach($data['data'] as $k=>$v){

            $color='red';
            $des='';
            $msg='';
            $tmp='';

            if($v['type_id'] ==0){
                $des = lang('model/collect/type_err');
            }
            elseif(empty($v['website_name'])) {
                $des = lang('model/collect/name_err');
            }
            elseif( mac_array_filter($filter_arr,$v['website_name'])!==false) {
                $des = lang('model/collect/name_in_filter_err');
            }
            else {
                unset($v['website_id']);

                foreach($v as $k2=>$v2){
                    if(strpos($k2,'_content')===false) {
                        $v[$k2] = strip_tags($v2);
                    }
                }
                $v['website_name'] = trim($v['website_name']);
                $v['type_id_1'] = intval($type_list[$v['type_id']]['type_pid']);
                $v['website_en'] = Pinyin::get($v['website_name']);
                $v['website_letter'] = strtoupper(substr($v['website_en'],0,1));
                $v['website_time_add'] = time();
                $v['website_time'] = time();
                $v['website_status'] = intval($config['status']);
                $v['website_lock'] = intval($v['website_lock']);
                if(!empty($v['website_status'])) {
                    $v['website_status'] = intval($v['website_status']);
                }
                $v['website_level'] = intval($v['website_level']);
                $v['website_hits'] = intval($v['website_hits']);
                $v['website_hits_day'] = intval($v['website_hits_day']);
                $v['website_hits_week'] = intval($v['website_hits_week']);
                $v['website_hits_month'] = intval($v['website_hits_month']);

                $v['website_up'] = intval($v['website_up']);
                $v['website_down'] = intval($v['website_down']);

                $v['website_score'] = floatval($v['website_score']);
                $v['website_score_all'] = intval($v['website_score_all']);
                $v['website_score_num'] = intval($v['website_score_num']);

                if($config['hits_start']>0 && $config['hits_end']>0) {
                    $v['website_hits'] = rand($config['hits_start'], $config['hits_end']);
                    $v['website_hits_day'] = rand($config['hits_start'], $config['hits_end']);
                    $v['website_hits_week'] = rand($config['hits_start'], $config['hits_end']);
                    $v['website_hits_month'] = rand($config['hits_start'], $config['hits_end']);
                }

                if($config['updown_start']>0 && $config['updown_end']){
                    $v['website_up'] = rand($config['updown_start'], $config['updown_end']);
                    $v['website_down'] = rand($config['updown_start'], $config['updown_end']);
                }

                if($config['score']==1) {
                    $v['website_score_num'] = rand(1, 1000);
                    $v['website_score_all'] = $v['website_score_num'] * rand(1, 10);
                    $v['website_score'] = round($v['website_score_all'] / $v['website_score_num'], 1);
                }

                if ($config['psernd'] == 1) {
                    $v['website_content'] = mac_rep_pse_rnd($pse_rnd, $v['website_content']);
                }
                if ($config['psesyn'] == 1) {
                    $v['website_content'] = mac_rep_pse_syn($pse_syn, $v['website_content']);
                }

                if(empty($v['website_blurb'])){
                    $v['website_blurb'] = mac_substring( strip_tags($v['website_content']) ,100);
                }

                $where = [];
                $where['website_name'] = $v['website_name'];

                if (strpos($config['inrule'], 'b')!==false) {
                    $where['type_id'] = $v['type_id'];
                }
                // 采集网址入库重复规则建议增加跳转url
                // https://github.com/magicblack/maccms10/issues/1071
                if (strpos($config['inrule'], 'c')!==false) {
                    $where['website_jumpurl'] = $v['website_jumpurl'];
                }

                $info = model('Website')->where($where)->find();
                if (!$info) {
                    $tmp = $this->syncImages($config_sync_pic, $v['website_pic'],'website');
                    $v['website_pic'] = $tmp['pic'];
                    $msg = $tmp['msg'];
                    $res = model('Website')->insert($v);
                    if($res===false){

                    }
                    $color ='green';
                    $des= lang('model/collect/add_ok');
                } else {

                    if(empty($config['uprule'])){
                        $des = lang('model/collect/uprule_empty');
                    }
                    elseif ($info['website_lock'] == 1) {
                        $des = lang('model/collect/data_lock');
                    }
                    else {
                        unset($v['website_time_add']);
                        $rc=true;
                        if($rc){
                            $update=[];

                            if(strpos(','.$config['uprule'],'a')!==false && !empty($v['website_content']) && $v['website_content']!=$info['website_content']){
                                $update['website_content'] = $v['website_content'];
                            }
                            if(strpos(','.$config['uprule'],'b')!==false && !empty($v['website_blurb']) && $v['website_blurb']!=$info['website_blurb']){
                                $update['website_blurb'] = $v['website_blurb'];
                            }
                            if(strpos(','.$config['uprule'],'c')!==false && !empty($v['website_remarks']) && $v['website_remarks']!=$info['website_remarks']){
                                $update['website_remarks'] = $v['website_remarks'];
                            }
                            if(strpos(','.$config['uprule'],'d')!==false && !empty($v['website_jumpurl']) && $v['website_jumpurl']!=$info['website_jumpurl']){
                                $update['website_jumpurl'] = $v['website_jumpurl'];
                            }
                            if(strpos(','.$config['uprule'],'e')!==false && (substr($info["website_pic"], 0, 4) == "http" ||empty($info['website_pic']) ) && ($v['website_pic']!=$info['website_pic'] || strpos($info['website_pic'], '#err') !== false) ){
                                $tmp = $this->syncImages($config_sync_pic, $v['website_pic'],'website');
                                $update['website_pic'] =$tmp['pic'];
                                $msg =$tmp['msg'];
                            }

                            if(count($update)>0){
                                $update['website_time'] = time();
                                $where = [];
                                $where['website_id'] = $info['website_id'];
                                $res = model('Website')->where($where)->update($update);
                                $color = 'green';
                                if($res===false){

                                }
                            }
                            else{
                                $des = lang('model/collect/not_need_update');
                            }
                        }

                    }
                }
            }
            if($show==1) {
                mac_echo( ($k + 1) . $v['website_name'] . "<font color=$color>" .$des .'</font>'. $msg . '');
            }
            else{
                return ['code'=>($color=='red' ? 1001 : 1),'msg'=> $v['website_name'] .' '.$des ];
            }
        }

        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_website';
        if(ENTRANCE=='api'){
            Cache::rm($key);
            if ($data['page']['page'] < $data['page']['pagecount']) {
                $param['page'] = intval($data['page']['page']) + 1;
                $res = $this->actor($param);
                if($res['code']>1){
                    return $this->error($res['msg']);
                }
                $this->website_data($param,$res );
            }
            mac_echo(lang('model/collect/is_over'));
            die;
        }

        if(empty($GLOBALS['config']['app']['collect_timespan'])){
            $GLOBALS['config']['app']['collect_timespan'] = 3;
        }

        if($show==1) {
            if ($param['ac'] == 'cjsel') {
                Cache::rm($key);
                mac_echo(lang('model/collect/is_over'));
                unset($param['ids']);
                $param['ac'] = 'list';
                $url = url('api') . '?' . http_build_query($param);
                $ref = $_SERVER["HTTP_REFERER"];
                if(!empty($ref)){
                    $url = $ref;
                }
                mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
            } else {
                if ($data['page']['page'] >= $data['page']['pagecount']) {
                    Cache::rm($key);
                    mac_echo(lang('model/collect/is_over'));
                    unset($param['page']);
                    $param['ac'] = 'list';
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                } else {
                    $param['page'] = intval($data['page']['page']) + 1;
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                }
            }
        }
    }

    public function comment_json($param)
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];

        if($param['ac']!='list'){
            $url_param['ac'] = 'detail';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }
        $url .= http_build_query($url_param).base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $json = json_decode($html,true);
        if(!$json){
            return ['code'=>1002, 'msg'=>lang('model/collect/json_err') . ': ' . mb_substr($html, 0, 15)];
        }

        $array_page = [];
        $array_page['page'] = $json['page'];
        $array_page['pagecount'] = $json['pagecount'];
        $array_page['pagesize'] = $json['limit'];
        $array_page['recordcount'] = $json['total'];
        $array_page['url'] = $url;

        $key = 0;
        $array_data = [];
        foreach($json['list'] as $key=>$v){
            $array_data[$key] = $v;
        }


        $res = ['code'=>1, 'msg'=>'ok', 'page'=>$array_page, 'data'=>$array_data ];
        return $res;
    }

    public function comment_data($param,$data,$show=1)
    {
        if($show==1) {
            mac_echo('[' . __FUNCTION__ . '] ' . lang('model/collect/data_tip1',[$data['page']['page'],$data['page']['pagecount'],$data['page']['url']]));
        }

        $config = config('maccms.collect');
        $config = $config['comment'];
        $config_sync_pic = $param['sync_pic_opt'] > 0 ? $param['sync_pic_opt'] : $config['pic'];

        $filter_arr = explode(',',$config['filter']); $filter_arr = array_filter($filter_arr);
        $pse_rnd = explode('#',$config['words']); $pse_rnd = array_filter($pse_rnd);
        $pse_syn = mac_txt_explain($config['thesaurus'], true);

        foreach($data['data'] as $k=>$v){

            $color='red';
            $des='';
            $msg='';
            $tmp='';

            if(empty($v['comment_name']) || empty($v['comment_content']) || empty($v['rel_name']) ) {
                $des = lang('model/collect/comment_data_require');
            }
            elseif( mac_array_filter($filter_arr,$v['comment_content']) !==false) {
                $des = lang('model/collect/name_in_filter_err');
            }
            else {
                unset($v['comment_id']);

                foreach($v as $k2=>$v2){
                    if(strpos($k2,'_content')===false) {
                        $v[$k2] = strip_tags($v2);
                    }
                }

                $v['comment_time'] = time();
                $v['comment_status'] = intval($config['status']);
                $v['comment_up'] = intval($v['comment_up']);
                $v['comment_down'] = intval($v['comment_down']);
                $v['comment_mid'] = intval($v['comment_mid']);
                if(!empty($v['comment_ip']) && !is_numeric($v['comment_ip'])){
                    $v['comment_ip'] = mac_get_ip_long($v['comment_ip']);
                }

                if($config['updown_start']>0 && $config['updown_end']){
                    $v['comment_up'] = rand($config['updown_start'], $config['updown_end']);
                    $v['comment_down'] = rand($config['updown_start'], $config['updown_end']);
                }
                if ($config['psernd'] == 1) {
                    $v['comment_content'] = mac_rep_pse_rnd($pse_rnd, $v['comment_content']);
                }
                if ($config['psesyn'] == 1) {
                    $v['comment_content'] = mac_rep_pse_syn($pse_syn, $v['comment_content']);
                }

                $where = [];
                $where2 = [];
                $blend = false;

                if (strpos($config['inrule'], 'b')!==false) {
                    $where['comment_content'] = ['eq', $v['comment_content']];
                }
                if (strpos($config['inrule'], 'c')!==false) {
                    $where['comment_name'] = ['eq', $v['comment_name']];
                }

                if(empty($v['rel_id'])){
                    if($v['comment_mid']==1){
                        if(!empty($v['douban_id'])){
                            $where2['vod_douban_id'] = ['eq',$v['douban_id']];
                            unset($v['douban_id']);
                        }
                        else{
                            $where2['vod_name'] = ['eq',$v['rel_name']];
                        }
                        $rel_info = model('Vod')->where($where2)->find();
                    }
                    elseif($v['comment_mid']==2){
                        $where2['art_name'] = ['eq',$v['rel_name']];
                        $rel_info = model('Art')->where($where2)->find();
                    }
                    elseif($v['comment_mid']==3){
                        $where2['topic_name'] = ['eq',$v['rel_name']];
                        $rel_info = model('Topic')->where($where2)->find();
                    }
                    elseif($v['comment_mid']==8){
                        $where2['actor_name'] = ['eq',$v['rel_name']];
                        $rel_info = model('Actor')->where($where2)->find();
                    }
                    elseif($v['comment_mid']==9){
                        $where2['role_name'] = ['eq',$v['rel_name']];
                        $rel_info = model('Role')->where($where2)->find();
                    }
                    elseif($v['comment_mid']==11){
                        $where2['website_name'] = ['eq',$v['rel_name']];
                        $rel_info = model('Website')->where($where2)->find();
                    }

                    $rel_id = $rel_info[mac_get_mid_code($v['comment_mid']).'_id'];
                }
                else{
                    $rel_id = $v['rel_id'];
                }

                if(empty($rel_id)){
                    $des = lang('model/collect/not_found_rel_data');
                }
                else {

                    $v['comment_rid'] = $rel_id;
                    $info=false;

                    if(!empty($where)) {
                        $where['comment_rid'] = $rel_id;
                        $info = model('Comment')->where($where)->find();
                    }
                    if (!$info) {
                        $msg = isset($tmp['msg']) ? $tmp['msg'] : '';
                        $res = model('Comment')->insert($v);
                        if ($res === false) {

                        }
                        $color = 'green';
                        $des = lang('model/collect/add_ok');
                    } else {

                        if(empty($config['uprule'])){
                            $des = lang('model/collect/uprule_empty');
                        }
                        else {
                            $rc = true;
                            if ($rc) {
                                $update = [];

                                if (strpos(',' . $config['uprule'], 'a') !== false && !empty($v['comment_time']) && $v['comment_time'] != $info['comment_time']) {
                                    $update['comment_time'] = $v['comment_time'];
                                }

                                if(count($update)>0){
                                    $update['comment_time'] = time();
                                    $where = [];
                                    $where['comment_id'] = $info['comment_id'];
                                    $res = model('Comment')->where($where)->update($update);
                                    $color = 'green';
                                    if ($res === false) {

                                    }
                                }
                                else{
                                    $des = lang('model/collect/not_need_update');
                                }
                            }

                        }
                    }
                }

            }
            if($show==1) {
                mac_echo( ($k + 1) . $v['comment_content'] . "<font color=$color>" .$des .'</font>'. $msg . '');
            }
            else{
                return ['code'=>($color=='red' ? 1001 : 1),'msg'=> $v['comment_content'] .' '.$des ];
            }
        }

        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_comment';
        if(ENTRANCE=='api'){
            Cache::rm($key);
            if ($data['page']['page'] < $data['page']['pagecount']) {
                $param['page'] = intval($data['page']['page']) + 1;
                $res = $this->role($param);
                if($res['code']>1){
                    return $this->error($res['msg']);
                }
                $this->actor_data($param,$res );
            }
            mac_echo(lang('model/collect/is_over'));
            die;
        }

        if(empty($GLOBALS['config']['app']['collect_timespan'])){
            $GLOBALS['config']['app']['collect_timespan'] = 3;
        }

        if($show==1) {
            if ($param['ac'] == 'cjsel') {
                Cache::rm($key);
                mac_echo(lang('model/collect/is_over'));
                unset($param['ids']);
                $param['ac'] = 'list';
                $url = url('api') . '?' . http_build_query($param);
                $ref = $_SERVER["HTTP_REFERER"];
                if(!empty($ref)){
                    $url = $ref;
                }
                mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
            } else {
                if ($data['page']['page'] >= $data['page']['pagecount']) {
                    Cache::rm($key);
                    mac_echo(lang('model/collect/is_over'));
                    unset($param['page']);
                    $param['ac'] = 'list';
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                } else {
                    $param['page'] = intval($data['page']['page']) + 1;
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                }
            }
        }
    }

    /**
     * 检查url合法性
     * https://github.com/magicblack/maccms10/issues/763
     */
    private function checkCjUrl($url)
    {
        $result = parse_url($url);
        if (empty($result['host']) || in_array($result['host'], ['127.0.0.1', 'localhost'])) {
            return ['code' => 1001, 'msg' => lang('model/collect/cjurl_err') . ': ' . $url];
        }
        return ['code' => 1];
    }

    public function manga_xml($param,$html='')
    {
        $url_param = [];
        $url_param['ac'] = $param['ac'];
        $url_param['t'] = $param['t'];
        $url_param['pg'] = is_numeric($param['page']) ? $param['page'] : '';
        $url_param['h'] = $param['h'];
        $url_param['ids'] = $param['ids'];
        $url_param['wd'] = $param['wd'];
        if(empty($param['h']) && !empty($param['rday'])){
            $url_param['h'] = $param['rday'];
        }

        if($param['ac']!='list'){
            $url_param['ac'] = 'mangalist';
        }

        $url = $param['cjurl'];
        if(strpos($url,'?')===false){
            $url .='?';
        }
        else{
            $url .='&';
        }
        $url .= http_build_query($url_param). base64_decode($param['param']);
        $result = $this->checkCjUrl($url);
        if ($result['code'] > 1) {
            return $result;
        }
        $html = mac_curl_get($url);
        if(empty($html)){
            return ['code'=>1001, 'msg'=>lang('model/collect/get_html_err') . ', url: ' . $url];
        }
        $html = mac_filter_tags($html);
        $xml = @simplexml_load_string($html);
        if(empty($xml)){
            $labelRule = '<pic>'."(.*?)".'</pic>';
            $labelRule = mac_buildregx($labelRule,"is");
            preg_match_all($labelRule,$html,$tmparr);
            $ec=false;
            foreach($tmparr[1] as $tt){
                if(strpos($tt,'[CDATA')===false){
                    $ec=true;
                    $ne = '<pic>'.'<![CDATA['.$tt .']]>'.'</pic>';
                    $html = str_replace('<pic>'.$tt.'</pic>',$ne,$html);
                }
            }
            if($ec) {
                $xml = @simplexml_load_string($html);
            }
            if(empty($xml)) {
                return ['code' => 1002, 'msg'=>lang('model/collect/xml_err')];
            }
        }

        $array_page = [];
        $array_page['page'] = (string)$xml->list->attributes()->page;
        $array_page['pagecount'] = (string)$xml->list->attributes()->pagecount;
        $array_page['pagesize'] = (string)$xml->list->attributes()->pagesize;
        $array_page['recordcount'] = (string)$xml->list->attributes()->recordcount;
        $array_page['url'] = $url;

        $type_list = model('Type')->getCache('type_list');
        $bind_list = config('bind');

        $key = 0;
        $array_data = [];
        foreach($xml->list->manga as $manga){
            $bind_key = $param['cjflag'] .'_'.(string)$manga->tid;
            if($bind_list[$bind_key] >0){
                $array_data[$key]['type_id'] = $bind_list[$bind_key];
            }
            else{
                $array_data[$key]['type_id'] = 0;
            }
            $array_data[$key]['manga_id'] = (string)$manga->id;
            $array_data[$key]['manga_name'] = (string)$manga->name;
            $array_data[$key]['manga_sub'] = (string)$manga->sub;
            $array_data[$key]['manga_remarks'] = (string)$manga->remarks;
            $array_data[$key]['type_name'] = (string)$manga->type;
            $array_data[$key]['manga_pic'] = (string)$manga->pic;
            $array_data[$key]['manga_lang'] = (string)$manga->lang;
            $array_data[$key]['manga_area'] = (string)$manga->area;
            $array_data[$key]['manga_year'] = (string)$manga->year;
            $array_data[$key]['manga_serial'] = (string)$manga->serial;
            $array_data[$key]['manga_author'] = (string)$manga->author;
            $array_data[$key]['manga_artist'] = (string)$manga->artist;
            $array_data[$key]['manga_content'] = (string)$manga->content;

            $array_data[$key]['manga_status'] = 1;
            $array_data[$key]['manga_time'] = (string)$manga->last;
            $array_data[$key]['manga_total'] = 0;
            $array_data[$key]['manga_isend'] = 1;
            if($array_data[$key]['manga_serial']){
                $array_data[$key]['manga_isend'] = 0;
            }
            
            // 格式化章節
            $array_from = [];
            $array_url = [];
            $array_server=[];
            $array_note=[];

            if(isset($manga->dl->dd) && count($manga->dl->dd)){
                for($i=0; $i<count($manga->dl->dd); $i++){
                    $array_from[$i] = (string)$manga->dl->dd[$i]['flag'];
                    $urls = explode('#', $this->vod_xml_replace((string)$manga->dl->dd[$i]));
                    $sorted_urls = $this->sortPlayUrls($urls);
                    $array_url[$i] = implode('#', $sorted_urls);
                    $array_server[$i] = 'no';
                    $array_note[$i] = '';
                }
            }else{
                $array_from[]=(string)$manga->dt;
                $array_url[] ='';
                $array_server[]='';
                $array_note[]='';
            }

            $array_data[$key]['manga_play_from'] = implode('$$$', $array_from);
            $array_data[$key]['manga_play_url'] = implode('$$$', $array_url);
            $array_data[$key]['manga_play_server'] = implode('$$$', $array_server);
            $array_data[$key]['manga_play_note'] = implode('$$$', $array_note);

            $key++;
        }

        $array_type = [];
        $key=0;
        //分类列表
        if($param['ac'] == 'list'){
            foreach($xml->class->ty as $ty){
                $array_type[$key]['type_id'] = (string)$ty->attributes()->id;
                $array_type[$key]['type_name'] = (string)$ty;
                $key++;
            }
        }

        $res = ['code'=>1, 'msg'=>'xml', 'page'=>$array_page, 'type'=>$array_type, 'data'=>$array_data ];
        return $res;
    }

    public function manga_data($param,$data,$show=1)
    {
        if($show==1) {
            mac_echo('[' . __FUNCTION__ . '] ' . lang('model/collect/data_tip1', [$data['page']['page'],$data['page']['pagecount'],$data['page']['url']]));
        }

        $config = config('maccms.collect');
        $config = $config['manga'];
        $config_sync_pic = $param['sync_pic_opt'] > 0 ? $param['sync_pic_opt'] : $config['pic'];

        $type_list = model('Type')->getCache('type_list');
        $filter_arr = explode(',',$config['filter']);
        $filter_arr = array_filter($filter_arr);
        
        foreach($data['data'] as $k=>$v){
            $color='red';
            $des='';
            $msg='';
            $tmp='';

            if ($v['type_id'] ==0) {
                $des = lang('model/collect/type_err');
            } elseif (empty($v['manga_name'])) {
                $des = lang('model/collect/name_err');
            } elseif (mac_array_filter($filter_arr,$v['manga_name']) !==false) {
                $des = lang('model/collect/name_in_filter_err');
            } else {
                unset($v['manga_id']);

                foreach($v as $k2=>$v2){
                    if(strpos($k2,'_content')===false) {
                        $v[$k2] = strip_tags($v2);
                    }
                }

                $v['type_id_1'] = intval($type_list[$v['type_id']]['type_pid']);
                $v['manga_en'] = Pinyin::get($v['manga_name']);
                $v['manga_letter'] = strtoupper(substr($v['manga_en'],0,1));
                $v['manga_time_add'] = time();
                $v['manga_time'] = time();
                $v['manga_status'] = intval($config['status']);
                
                $where = [];
                $where['manga_name'] = $v['manga_name'];
                if (strpos($config['inrule'], 'b')!==false) {
                    $where['type_id'] = $v['type_id'];
                }

                $info = model('Manga')->where($where)->find();
                if (!$info) {
                    $tmp = $this->syncImages($config_sync_pic, $v['manga_pic'],'manga');
                    $v['manga_pic'] = (string)$tmp['pic'];
                    $msg = $tmp['msg'];
                    
                    $v['manga_chapter_from'] = $v['manga_play_from'];
                    $v['manga_chapter_url'] = $v['manga_play_url'];
                    
                    $res = model('Manga')->insert($v);
                    if($res===false){

                    }
                    $color ='green';
                    $des= lang('model/collect/add_ok');
                }
                else{
                    if(empty($config['uprule'])){
                        $des = lang('model/collect/uprule_empty');
                    }
                    elseif ($info['manga_lock'] == 1) {
                        $des = lang('model/collect/data_lock');
                    }
                    else {
                        $update = [];
                        $ec=false;

                        if (strpos(',' . $config['uprule'], 'a')!==false && !empty($v['manga_play_from'])) {
                            $old_play_from = $info['manga_chapter_from'];
                            $old_play_url = $info['manga_chapter_url'];
                            
                            $cj_play_from_arr = explode('$$$',$v['manga_play_from'] );
                            $cj_play_url_arr = explode('$$$',$v['manga_play_url']);

                            foreach ($cj_play_from_arr as $k2 => $v2) {
                                $cj_play_from = $v2;
                                $cj_play_url = $cj_play_url_arr[$k2];
                                if (strpos('$$$'.$info['manga_chapter_from'].'$$$', '$$$'.$cj_play_from.'$$$') === false) {
                                    if(!empty($old_play_from)){
                                        $old_play_url .="$$$";
                                        $old_play_from .= "$$$" ;
                                    }
                                    $old_play_url .= "" . $cj_play_url;
                                    $old_play_from .= "" . $cj_play_from;
                                    $ec=true;
                                }
                            }
                            if($ec) {
                                $update['manga_chapter_from'] = $old_play_from;
                                $update['manga_chapter_url'] = $old_play_url;
                            }
                        }

                        if(count($update)>0){
                            $update['manga_time'] = time();
                            $where = [];
                            $where['manga_id'] = $info['manga_id'];
                            $res = model('Manga')->where($where)->update($update);
                            $color = 'green';
                        }
                        else{
                            $des = lang('model/collect/not_need_update');
                        }
                    }
                }
            }
            if($show==1) {
                mac_echo( ($k + 1) .'、'. $v['manga_name'] . " <font color='{$color}'>" .$des .'</font>'. $msg.'' );
            }
            else{
                return ['code'=>($color=='red' ? 1001 : 1),'msg'=>$des ];
            }
        }

        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_manga';
        if(ENTRANCE=='api'){
            Cache::rm($key);
            if ($data['page']['page'] < $data['page']['pagecount']) {
                $param['page'] = intval($data['page']['page']) + 1;
                $res = $this->manga($param);
                if($res['code']>1){
                    return $this->error($res['msg']);
                }
                $this->manga_data($param,$res );
            }
            mac_echo(lang('model/collect/is_over'));
            die;
        }

        if(empty($GLOBALS['config']['app']['collect_timespan'])){
            $GLOBALS['config']['app']['collect_timespan'] = 3;
        }
        if($show==1) {
            if ($param['ac'] == 'cjsel') {
                Cache::rm($key);
                mac_echo(lang('model/collect/is_over'));
                unset($param['ids']);
                $param['ac'] = 'list';
                $url = url('api') . '?' . http_build_query($param);
                $ref = $_SERVER["HTTP_REFERER"];
                if(!empty($ref)){
                   $url = $ref;
                }

                mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
            } else {
                if ($data['page']['page'] >= $data['page']['pagecount']) {
                    Cache::rm($key);
                    mac_echo(lang('model/collect/is_over'));
                    unset($param['page'],$param['ids']);
                    $param['ac'] = 'list';
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan']);
                } else {
                    $param['page'] = intval($data['page']['page']) + 1;
                    $url = url('api') . '?' . http_build_query($param);
                    mac_jump($url, $GLOBALS['config']['app']['collect_timespan'] );
                }
            }
        }
    }
}
