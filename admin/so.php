
<?php
session_start();

// 检查是否已经通过密码验证
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // 未通过密码验证，重定向到密码访问页面
    header('Location: index.php');
    exit;
}

// 处理退出登录
if (isset($_POST['logout'])) {
    // 清除验证状态
    unset($_SESSION['authenticated']);
    unset($_SESSION['validated_time']);

    // 重定向到密码访问页面
    header('Location: index.php');
    exit;
}
?>

<meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,minimum-scale=1,maximum-scale=1,initial-scale=1,user-scalable=no">
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
    <meta name="keywords" content="流浪猫,API,随机图,随机,随机图片,随机壁纸,ACG,美女,写真,图集,动漫">
    <meta name="description" content="陈小儒的流浪猫API集合">
    <meta name="author" content="陈小儒">
    <title>流浪猫API数据</title>
    <link rel="stylesheet" href="https://cdn.staticfile.org/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
<style>
body{padding-top:50px;font-family: "Source Sans Pro","Hiragino Sans GB","Microsoft Yahei",SimSun,Helvetica,Arial,Sans-serif,monospace;}
.alert{padding-top:5px;padding-bottom:5px;margin-top:10px;margin-bottom:10px;background-color:rgb(217 237 247 / 40%);}
pre{background-color:rgba(255,255,255,.4);}
.bgw{background-color:rgb(255,255,255,.4);border: 1px solid rgb(16 22 26 / 40%);}
.tbody{border-radius: 10px;}
.bg{background:url(https://zaim.cn/0.php) center 0px / cover;background-attachment:fixed;}
.navbar-inverse {background-color: #ffffff91;border-color: #08080891;}
.navbar-inverse .navbar-brand {color: #000000;}
.navbar-inverse .navbar-nav>li>a {color: #000000;}
::-webkit-scrollbar{width: 0px;}

.table {width: 100%;max-width: 100%;margin-bottom: 0px;}
    /* 分页链接样式 */
    .pagination {
        margin-top: 10px;
    }

    .pagination a,
    .pagination span {
        display: inline-block;
        padding: 5px 10px;
        border: 1px solid #ccc;
        margin-right: 5px;
        text-decoration: none;
        color: #333;
        background-color: #f9f9f9;
        border-radius: 3px;
    }

    .pagination a:hover {
        background-color: #e0e0e0;
    }

    .pagination .current {
        font-weight: bold;
        background-color: #ccc;
    }

    /* 表格样式 */

       .logout-form {
           /* position: absolute;*/
            bottom: 5px;
        }

        .logout-form input[type="submit"] {
            background: #ff0000;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
</style>

<?php
// 读取调用记录
$callLog = 'log/img-log.txt';
$callData = file($callLog, FILE_IGNORE_NEW_LINES);

// 过滤掉没有 IP 地址的记录
$callData = array_filter($callData, function($line) {
    return strpos($line, ' - ') !== false;
});

// 反转调用记录数组，使最新的记录排在数组的开头
$callData = array_reverse($callData);

// 每页展示的记录数量
$perPage = 20;

// 总记录数量
$totalCount = count($callData);

// 当前页码
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 计算总页数
$totalPages = ceil($totalCount / $perPage);

// 限制页码范围
$page = max(1, min($page, $totalPages));

// 计算起始索引
$startIndex = ($page - 1) * $perPage;

// 获取当前页的记录
$pageData = array_slice($callData, $startIndex, $perPage);

// 显示调用记录表格
echo '<body class="bg">
<div class="container bgw">
    <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <a class="navbar-brand" href="/">流浪猫API</a>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
                    <li><a href="https://cxr.cool/" target="_blank">流浪猫</a></li>
                    <li><a href="https://zaim.cn" target="_blank">爱猫书签</a></li>
                    <li><a href="https://w.cxr.cool" target="_blank">服务监控</a></li>
                </ul>
            </div>
        </div>
    </nav>';
echo '<br><table class="table table-bordered bgw">';
echo '<tr><th>API名称</th><th>IP地址</th><th>调用域名</th><th>记录时间</th></tr>';

foreach ($pageData as $callLine) {
    list($APIname, $ip, $domain, $timestamp) = explode(' - ', $callLine);
    echo '<tr>';
	echo '<td>' . $APIname . '</td>';
    echo '<td>' . htmlspecialchars($ip) . '</td>';
    echo '<td>' . htmlspecialchars($domain) . '</td>';
    echo '<td>' . date('Y-m-d H:i:s', intval($timestamp)) . '</td>';
    echo '</tr>';
}

echo '</table>';

// 显示分页链接
echo '<div class="pagination">';

// Display first page and previous page links
if ($page > 1) {
    echo '<a href="?page=1">第一页</a>';
    echo '<a href="?page=' . ($page - 1) . '">上一页</a>';
}

// Display numbered page links
$startPage = max(1, $page - 20);
$endPage = min($startPage + 19, $totalPages);

for ($i = $startPage; $i <= $endPage; $i++) {
    if ($i === $page) {
        echo '<span class="current">' . $i . '</span>';
    } else {
        echo '<a href="?page=' . $i . '">' . $i . '</a>';
    }
}

// Display next page and last page links
if ($page < $totalPages) {
    echo '<a href="?page=' . ($page + 1) . '">下一页</a>';
    echo '<a href="?page=' . $totalPages . '">最后一页</a>';
}
// Display next page and last page links
if ($page < $totalPages) {
    echo '<a href="?page=' . $totalPages . '">合计' . $totalPages . '页</a>';
}

?>

    <!-- 退出登录按钮 -->
    <form class="logout-form" method="post" action=""></p>
        <input type="submit" name="logout" value="退出登录" />
    </form>