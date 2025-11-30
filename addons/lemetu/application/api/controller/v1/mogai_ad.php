<?php
$menus=require './mogai_a.php';
$html_code =$menus['html_code']; //html广告代码
$vod_url =$menus['vod_url'];
$click_url = $menus['click_url'];
$timeout = $menus['timeout']; //html广告播放时间
$ad_select = $menus['ad_select'];
$home_history = true;
if ($menus['home_history'] =='off'){
    $home_history = false;
}
$arr = array (
    'msg'=>'成功',
    'code'=>200,
    'data'=>[
  
        'ad_select'=>$ad_select,
        'html'=>[
            'code'=>$html_code ,
            'timeout'=>intval($timeout)
            
            ],
        'video'=>[
            "url"=>$vod_url,
            "click_url"=> $click_url
            
            ],
        'home_history'=>$home_history
        ],
        
        
    );

echo json_encode($arr);
?>