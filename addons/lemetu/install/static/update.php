<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<title>乐美兔API</title>


</head>
<body>
<div class = subtitle>乐美兔API</div>
<?php
error_reporting(0);
header("Content-type: text/html; charset=utf-8");
	ignore_user_abort(true);
if(!class_exists('ZipArchive')) {
	die("调用ZipArchive类失败,你的空间不支持本服务" );
	}
function zipExtract ($src, $dest)
    {
        $zip = new ZipArchive();
        if ($zip->open($src)===true)
        {
            $zip->extractTo($dest);
            $zip->close();
            return true;
        }
        return false;
      }
if (!isset($_GET['mzip'])) {
echo '<b><div id="mod-news" class="module">
<div class="title"><h3 data-iarea="132,89">版权申明:</div></b>
<br>';


echo '<div class="bq">本程序由乐美兔制作，未经允许禁止二次开发和倒卖(￣-￣)。<br>一定要记得哦！<br/><div class=foot>萌萌的客服小二愿意为你永远服务！</div>';
exit;
}
$RemoteFile = rawurldecode($_GET["mzip"]);
$ZipFile = "default.zip";
$Dir = "./";
copy($RemoteFile,$ZipFile) or die("很抱歉，客服小二不能帮你了，原因是服务器源出问题了，请耐心等待修复哦，感谢你一直以来对客服小二的肯定！故障很快就能修好哦，⊙﹏⊙<br>相信客服小二一定会努力为你更好的服务！<br><br><b>");
if (zipExtract($ZipFile,$Dir)) {
echo "<b>恭喜你，</b>萌萌的客服小二已经帮你更新好了，赶快刷新查看吧，~(≧▽≦)/~<br/>";
unlink($ZipFile);
	}
else {
echo "抱歉，程序无法完成运行，客服小二面壁思过去≧﹏≦！<b>";
if (file_exists($ZipFile)) {
unlink($ZipFile);
	}
}
?>
</div>
</body>
</html>
<!--DEFAULT_WELCOME_PAGE-->
<!--DEFAULT_WELCOME_PAGE-->
