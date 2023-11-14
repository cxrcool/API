<?php
$APIname = basename(getcwd());
$image_file = $APIname.'.txt';
$PV = '../pv'.$APIname.'.txt';
$PVIP = '../PVIP.txt';
//调用统计
if(is_file($PVIP)){
    if($_SERVER['HTTP_REFERER']!=NULL){
    $count=$_SERVER['HTTP_REFERER']."\n";
    file_put_contents($PVIP, $count, FILE_APPEND);
    $file=file($PVIP);
    $file2=preg_replace('/(http(s)?:\/\/)|(\/.*)/', '',$file);
    $array=array_values(array_unique($file2));
    file_put_contents($PVIP,$array);
    }
} else {
    file_put_contents($PVIP,$_SERVER['SERVER_NAME']);
}
if(is_file($PV)){
    $count=file_get_contents($PV);
    $count++;
    file_put_contents($PV, $count);
} else {
    file_put_contents($PV,1);
}
$logFile = '../admin/log/img-log.txt';// 存储访问日志和限制日志的文件
$clientIP = $_SERVER['REMOTE_ADDR'];// 获取客户端IP地址
$limit = 10; // 限制的访问次数
$interval = 1; // 限制的时间间隔（秒）
// 检测调用域名
$callingDomain = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($callingDomain)) {
    $callingDomain = parse_url($callingDomain, PHP_URL_HOST);
    $timestamp = time();
    file_put_contents($logFile, $APIname . ' - ' .  $clientIP . ' - ' . $callingDomain . ' - ' . $timestamp . "\n", FILE_APPEND);
}
// 检查访问频率
$accessCount = 0;
$accessTime = time() - $interval;
if (file_exists($logFile)) {
    $accessData = file($logFile, FILE_IGNORE_NEW_LINES);
    foreach ($accessData as $accessLine) {
        if ($accessLine >= $accessTime) {
            $accessCount++;
        }
    }
}
// 记录当前访问时间
file_put_contents($logFile, time() . "\n", FILE_APPEND);

/*把返回404改成返回图片*/
if ($accessCount > $limit) {
    header('Content-Type: image/jpeg');
    readfile('警告.jpg');
    exit;
}
//调用统计结束
if(is_file($image_file)){
$data = file($image_file);
$num = count($data);
$id = mt_rand(0,$num-1);
$url = chop($data[$id]);
header("location:$url");
} else {
    file_put_contents($image_file,'https://api.cxr.cool/bing');
    $data = file($image_file);
    $num = count($data);
    $id = mt_rand(0,$num-1);
    $url = chop($data[$id]);
    header("location:$url");
}
?>