<?php
/**
 * MacCMS 自定义模板标签库 (Custom Template Taglib)
 * ============================================================
 *
 * 【文件说明】
 * MacCMS 的核心模板标签库，继承 ThinkPHP 的 TagLib 类。
 * 提供 {maccms:xxx} 格式的模板标签，用于在模板中调用数据。
 *
 * 【工作原理】
 * 1. 模板引擎解析 {maccms:xxx} 标签
 * 2. 调用对应的 tagXxx() 方法生成 PHP 代码
 * 3. 生成的代码调用模型的 listCacheData() 方法查询数据库
 * 4. 模板引擎执行生成的 PHP 代码，输出数据
 *
 * 【标签注册】
 * 标签需要在模板配置中注册才能使用:
 * application/config.php:
 *   'template' => [
 *       'taglib_build_in' => 'cx',
 *       'taglib_pre_load' => 'app\common\taglib\Maccms',
 *   ]
 *
 * 【支持的标签】
 * ┌──────────────────┬──────────────────────────────────────────────┐
 * │ 标签名            │ 功能说明                                      │
 * ├──────────────────┼──────────────────────────────────────────────┤
 * │ {maccms:vod}     │ 视频数据列表 → Vod::listCacheData()          │
 * │ {maccms:art}     │ 文章数据列表 → Art::listCacheData()          │
 * │ {maccms:manga}   │ 漫画数据列表 → Manga::listCacheData()        │
 * │ {maccms:actor}   │ 演员数据列表 → Actor::listCacheData()        │
 * │ {maccms:role}    │ 角色数据列表 → Role::listCacheData()         │
 * │ {maccms:website} │ 网址数据列表 → Website::listCacheData()      │
 * │ {maccms:topic}   │ 专题数据列表 → Topic::listCacheData()        │
 * │ {maccms:type}    │ 分类数据列表 → Type::listCacheData()         │
 * │ {maccms:link}    │ 友情链接列表 → Link::listCacheData()         │
 * │ {maccms:gbook}   │ 留言板列表   → Gbook::listCacheData()        │
 * │ {maccms:comment} │ 评论列表     → Comment::listCacheData()      │
 * │ {maccms:area}    │ 地区筛选列表 → Extend::areaData()            │
 * │ {maccms:lang}    │ 语言筛选列表 → Extend::langData()            │
 * │ {maccms:year}    │ 年份筛选列表 → Extend::yearData()            │
 * │ {maccms:letter}  │ 首字母筛选   → Extend::letterData()          │
 * │ {maccms:class}   │ 分类扩展     → Extend::classData()           │
 * │ {maccms:version} │ 版本筛选     → Extend::versionData()         │
 * │ {maccms:state}   │ 状态筛选     → Extend::stateData()           │
 * │ {maccms:for}     │ 循环标签     → 生成 ThinkPHP for 标签         │
 * │ {maccms:foreach} │ 遍历标签     → 生成 ThinkPHP foreach 标签    │
 * └──────────────────┴──────────────────────────────────────────────┘
 *
 * 【标签使用示例】
 *
 * 基本列表:
 * {maccms:vod order="desc" by="time" num="10" type="1"}
 *     {$key}. {$vo.vod_name}
 * {/maccms:vod}
 *
 * 带分页:
 * {maccms:vod order="desc" by="time" num="10" paging="yes"}
 *     {$vo.vod_name}
 * {/maccms:vod}
 * {$__PAGING__}
 *
 * 【通用属性说明】
 * ┌──────────────┬───────────────────────────────────────────────────┐
 * │ 属性名        │ 说明                                               │
 * ├──────────────┼───────────────────────────────────────────────────┤
 * │ order        │ 排序方向: asc=正序, desc=倒序                       │
 * │ by           │ 排序字段: time/time_add/id/hits/level/rnd 等       │
 * │ start        │ 起始位置 (offset)                                   │
 * │ num          │ 数据数量 (limit)                                    │
 * │ id           │ 指定单个ID                                          │
 * │ ids          │ 指定多个ID (逗号分隔)                                │
 * │ not          │ 排除的ID (逗号分隔)                                  │
 * │ type         │ 分类ID                                              │
 * │ typenot      │ 排除的分类ID                                        │
 * │ level        │ 推荐等级                                            │
 * │ paging       │ 是否分页: yes/no                                    │
 * │ pageurl      │ 分页URL模板                                         │
 * │ half         │ 分页显示页码数量                                     │
 * │ cachetime    │ 缓存时间 (秒)                                        │
 * └──────────────┴───────────────────────────────────────────────────┘
 *
 * 【相关文件】
 * - application/common/model/*.php : 各模型的 listCacheData() 方法
 * - application/admin/view_new/template/wizard.html : 标签向导页面
 *
 * ============================================================
 */
namespace app\common\taglib;
use think\template\TagLib;
use think\Db;

class Maccms extends Taglib {

    /**
     * ============================================================
     * 标签定义 - 定义所有可用标签及其属性
     * ============================================================
     *
     * 【格式说明】
     * '标签名' => ['attr' => '属性1,属性2,属性3,...']
     *
     * 每个标签可接收的属性在 attr 中定义，多个属性用逗号分隔
     */
	protected $tags = [
	    'link'=> ['attr'=>'order,by,type,not,start,num,cachetime'],
        'area'=> ['attr'=>'order,start,num'],
        'lang'=> ['attr'=>'order,start,num'],
        'year'=> ['attr'=>'order,start,num'],
        'class'=> ['attr'=>'order,start,num'],
        'version'=> ['attr'=>'order,start,num'],
        'state'=> ['attr'=>'order,start,num'],
        'letter'=> ['attr'=>'order,start,num'],
        'type' => ['attr' =>'order,by,start,num,id,ids,not,parent,flag,mid,format,cachetime'],
        'comment'=>['attr' =>'order,by,start,num,paging,pageurl,id,pid,rid,mid,uid,half'],
        'gbook'=>['attr' =>'order,by,start,num,paging,pageurl,rid,uid,half'],
        'role'=>['attr' =>'order,by,start,num,paging,pageurl,id,ids,not,rid,actor,name,level,letter,half,timeadd,timehits,time,cachetime'],
        'actor'=>['attr' =>'order,by,start,num,paging,pageurl,id,ids,not,area,sex,name,level,letter,type,typenot,starsign,blood,half,timeadd,timehits,time,cachetime'],
        'topic' => ['attr' =>'order,by,start,num,id,ids,not,paging,pageurl,class,tag,half,timeadd,timehits,time,cachetime'],
        'art' => ['attr' =>'order,by,start,num,id,ids,not,paging,pageurl,type,typenot,class,tag,level,letter,half,rel,timeadd,timehits,time,hitsmonth,hitsweek,hitsday,hits,cachetime'],
        'manga' => ['attr' =>'order,by,start,num,id,ids,not,paging,pageurl,type,typenot,class,tag,area,lang,year,level,letter,half,rel,version,state,tv,weekday,timeadd,timehits,time,hitsmonth,hitsweek,hitsday,hits,isend,cachetime'],
        'vod' => ['attr' =>'order,by,start,num,id,ids,not,paging,pageurl,type,typenot,class,tag,area,lang,year,level,letter,half,rel,version,state,tv,weekday,timeadd,timehits,time,hitsmonth,hitsweek,hitsday,hits,isend,cachetime'],
        'website'=>['attr' =>'order,by,start,num,paging,pageurl,id,ids,not,area,lang,name,level,letter,type,typenot,half,timeadd,timehits,time,cachetime'],
        'foreach' => ['attr'=>'name,id,key'],
        'for' => ['attr'=>'start,end,comparison,step,name'],
    ];

    /**
     * ============================================================
     * for 循环标签
     * ============================================================
     *
     * 【功能说明】
     * 生成 ThinkPHP 的 for 循环标签
     *
     * 【使用示例】
     * {maccms:for start="1" end="10" name="i"}
     *     {$i}
     * {/maccms:for}
     *
     * 【参数说明】
     * - start      : 起始值 (默认: 1)
     * - end        : 结束值 (默认: 5)
     * - comparison : 比较方式 (默认: elt)
     * - step       : 步进值 (默认: 1)
     * - name       : 循环变量名 (默认: i)
     *
     * @param array  $tag     标签属性数组
     * @param string $content 标签内容
     * @return string 生成的 ThinkPHP for 标签代码
     */
    public function tagFor($tag,$content)
    {
        if(empty($tag['start'])){
            $tag['start'] = 1;
        }
        if(empty($tag['end'])){
            $tag['end'] = 5;
        }
        if(empty($tag['comparison'])){
            $tag['comparison'] = 'elt';
        }
        if(empty($tag['step'])){
            $tag['step'] = 1;
        }
        if(empty($tag['name'])){
            $tag['name'] = 'i';
        }

        $parse='';
        $parse .= '{for start="'.$tag['start'].'" end="'.$tag['end'].'" comparison="'.$tag['comparison'].'" step="'.$tag['step'].'" name="'.$tag['name'].'"}';
        $parse .= $content;
        $parse .= '{/for}';

        return $parse;
    }

    /**
     * ============================================================
     * foreach 遍历标签
     * ============================================================
     *
     * 【功能说明】
     * 生成 ThinkPHP 的 foreach 遍历标签
     * 用于遍历自定义数组变量
     *
     * 【使用示例】
     * {maccms:foreach name="list" id="vo" key="k"}
     *     {$k}: {$vo.name}
     * {/maccms:foreach}
     *
     * 【参数说明】
     * - name   : 要遍历的数组变量名 (必填)
     * - id     : 每次循环的当前元素变量名 (默认: vo)
     * - key    : 当前元素的键名变量 (默认: key)
     * - offset : 起始位置偏移 (可选)
     * - length : 遍历的长度限制 (可选)
     * - mod    : 对key取模 (可选)
     * - empty  : 数组为空时显示的内容 (可选)
     *
     * @param array  $tag     标签属性数组
     * @param string $content 标签内容
     * @return string 生成的 ThinkPHP foreach 标签代码
     */
    public function tagForeach($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }
        // foreach标签强化
        // https://github.com/magicblack/maccms10/issues/984
        $parse_addon = '';
        if(!empty($tag['offset'])){
            $parse_addon .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse_addon .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse_addon .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse_addon .= ' empty="'.$tag['empty'].'"';
        }
        $parse='';
        $parse .= '{foreach name="'.$tag['name'].'" id="'.$tag['id'].'" key="'.$tag['key'].'"' . $parse_addon . '}';
        $parse .= $content;
        $parse .= '{/foreach}';
        
        return $parse;
    }

    /**
     * ============================================================
     * 地区筛选标签
     * ============================================================
     *
     * 【功能说明】
     * 生成地区筛选列表，数据来自 Extend::areaData()
     * 通常用于视频/文章的地区筛选导航
     *
     * 【使用示例】
     * {maccms:area order="asc"}
     *     <a href="{:mac_url_vod_search(['area'=>$vo.area_name])}">{$vo.area_name}</a>
     * {/maccms:area}
     *
     * 【可用字段】
     * - {$vo.area_name} : 地区名称
     *
     * @param array  $tag     标签属性数组
     * @param string $content 标签内容
     * @return string 生成的 PHP 代码
     */
    public function tagArea($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Extend")->areaData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 语言筛选标签 - 生成语言筛选列表
     * 数据来自 Extend::langData()
     * 可用字段: {$vo.lang_name}
     */
    public function tagLang($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Extend")->langData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 扩展分类标签 - 生成分类扩展列表
     * 数据来自 Extend::classData()
     * 可用字段: {$vo.class_name}
     */
    public function tagClass($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Extend")->classData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 年份筛选标签 - 生成年份筛选列表
     * 数据来自 Extend::YearData()
     * 可用字段: {$vo.year_name}
     */
    public function tagYear($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Extend")->YearData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 版本筛选标签 - 生成版本筛选列表
     * 数据来自 Extend::versionData()
     * 可用字段: {$vo.version_name}
     */
    public function tagVersion($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Extend")->versionData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 状态筛选标签 - 生成状态筛选列表
     * 数据来自 Extend::stateData()
     * 可用字段: {$vo.state_name}
     */
    public function tagState($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Extend")->stateData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 首字母筛选标签 - 生成A-Z首字母筛选列表
     * 数据来自 Extend::letterData()
     * 可用字段: {$vo.letter_name}
     * 常用于视频/演员等的首字母索引导航
     */
    public function tagLetter($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Extend")->letterData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 友情链接标签 - 查询友情链接列表
     * 数据来自 Link::listCacheData() → mac_link 表
     * 可用字段: {$vo.link_id}, {$vo.link_name}, {$vo.link_url}, {$vo.link_logo}
     */
    public function tagLink($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Link")->listCacheData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 分类标签 - 查询分类列表
     * 数据来自 Type::listCacheData() → mac_type 表
     * 可用字段: {$vo.type_id}, {$vo.type_name}, {$vo.type_en}, {$vo.type_pid}
     * 支持属性: mid(模型ID), parent(父级), flag(标志), format(格式化)
     */
    public function tagType($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Type")->listCacheData($__TAG__);';
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 评论标签 - 查询评论列表
     * 数据来自 Comment::listCacheData() → mac_comment 表
     * 可用字段: {$vo.comment_id}, {$vo.comment_name}, {$vo.comment_content}, {$vo.comment_time}
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagComment($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Comment")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 留言板标签 - 查询留言列表
     * 数据来自 Gbook::listCacheData() → mac_gbook 表
     * 可用字段: {$vo.gbook_id}, {$vo.gbook_name}, {$vo.gbook_content}, {$vo.gbook_reply}
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagGbook($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Gbook")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 专题标签 - 查询专题列表
     * 数据来自 Topic::listCacheData() → mac_topic 表
     * 可用字段: {$vo.topic_id}, {$vo.topic_name}, {$vo.topic_pic}, {$vo.topic_content}
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagTopic($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Topic")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 演员标签 - 查询演员列表
     * 数据来自 Actor::listCacheData() → mac_actor 表
     * 可用字段: {$vo.actor_id}, {$vo.actor_name}, {$vo.actor_pic}, {$vo.actor_sex}, {$vo.actor_area}
     * 支持属性: sex(性别), area(地区), blood(血型), starsign(星座), letter(首字母)
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagActor($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Actor")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 角色标签 - 查询角色列表
     * 数据来自 Role::listCacheData() → mac_role 表
     * 可用字段: {$vo.role_id}, {$vo.role_name}, {$vo.role_pic}, {$vo.role_actor}
     * 支持属性: rid(角色ID), actor(关联演员), name(角色名)
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagRole($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Role")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 文章标签 - 查询文章列表
     * 数据来自 Art::listCacheData() → mac_art 表
     * 可用字段: {$vo.art_id}, {$vo.art_name}, {$vo.art_pic}, {$vo.art_content}, {$vo.art_blurb}
     * 支持属性: type(分类), class(扩展分类), tag(标签), level(推荐级别), letter(首字母)
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagArt($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Art")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 漫画标签 - 查询漫画列表
     * 数据来自 Manga::listCacheData() → mac_manga 表
     * 可用字段: {$vo.manga_id}, {$vo.manga_name}, {$vo.manga_pic}, {$vo.manga_content}
     * 支持属性: type(分类), area(地区), lang(语言), year(年份), version(版本), state(状态)
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagManga($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Manga")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * ============================================================
     * 视频标签 - 查询视频列表 (核心标签)
     * ============================================================
     *
     * 【功能说明】
     * 最常用的标签，用于在模板中查询并显示视频数据
     * 数据来自 Vod::listCacheData() → mac_vod 表
     *
     * 【可用字段】
     * {$vo.vod_id}       视频ID
     * {$vo.vod_name}     视频名称
     * {$vo.vod_pic}      视频封面图
     * {$vo.vod_blurb}    视频简介
     * {$vo.vod_content}  视频详情
     * {$vo.vod_actor}    演员列表
     * {$vo.vod_director} 导演
     * {$vo.vod_area}     地区
     * {$vo.vod_year}     年份
     * {$vo.vod_hits}     点击量
     * {:mac_url_vod_detail($vo)} 详情页URL
     *
     * 【支持属性】
     * type/typenot : 分类筛选/排除
     * area/lang/year : 地区/语言/年份筛选
     * level : 推荐级别
     * letter : 首字母
     * state/version : 状态/版本
     * isend : 是否完结
     * tv/weekday : 连载日期
     *
     * 【使用示例】
     * {maccms:vod order="desc" by="time" num="10" type="1"}
     *     {$vo.vod_name}
     * {/maccms:vod}
     *
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagVod($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Vod")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }

    /**
     * 网址标签 - 查询网址/网站列表
     * 数据来自 Website::listCacheData() → mac_website 表
     * 可用字段: {$vo.website_id}, {$vo.website_name}, {$vo.website_url}, {$vo.website_logo}
     * 支持属性: type(分类), area(地区), lang(语言), level(级别), letter(首字母)
     * 支持分页: paging="yes" 时生成 $__PAGING__ 变量
     */
    public function tagWebsite($tag,$content)
    {
        if(empty($tag['id'])){
            $tag['id'] = 'vo';
        }
        if(empty($tag['key'])){
            $tag['key'] = 'key';
        }

        $parse = '<?php ';
        $parse .= '$__TAG__ = \'' . json_encode($tag) . '\';';
        $parse .= '$__LIST__ = model("Website")->listCacheData($__TAG__);';
        if($tag['paging']=='yes'){
            $parse .= '$__PAGING__ = mac_page_param($__LIST__[\'total\'],$__LIST__[\'limit\'],$__LIST__[\'page\'],$__LIST__[\'pageurl\'],$__LIST__[\'half\']);';
        }
        $parse .= ' ?>';
        $parse .= '{volist name="__LIST__[\'list\']" id="'.$tag['id'].'" key="'.$tag['key'].'"';
        if(!empty($tag['offset'])){
            $parse .= ' offset="'.$tag['offset'].'"';
        }
        if(!empty($tag['length'])){
            $parse .= ' length="'.$tag['length'].'"';
        }
        if(!empty($tag['mod'])){
            $parse .= ' mod="'.$tag['mod'].'"';
        }
        if(!empty($tag['empty'])){
            $parse .= ' empty="'.$tag['empty'].'"';
        }
        $parse .= '}';
        $parse .= $content;
        $parse .= '{/volist}';

        return $parse;
    }
}
