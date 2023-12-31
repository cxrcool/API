<?php
session_start();

// 检查是否已经通过密码验证
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    // 检查上次验证时间是否超过5小时
    $lastValidatedTime = $_SESSION['validated_time'];
    $currentTime = time();
    $hoursPassed = ($currentTime - $lastValidatedTime) / 3600;

    if ($hoursPassed <= 5) {
        // 验证仍然有效，重定向到原有页面
        header('Location: so.php');
        exit;
    } else {
        // 验证过期，清除验证状态
        unset($_SESSION['authenticated']);
        unset($_SESSION['validated_time']);
    }
}

// 处理密码验证
$password = "123"; // 设置密码

if (isset($_POST['password'])) {
    $enteredPassword = $_POST['password'];

    if ($enteredPassword === $password) {
        // 验证成功，设置验证状态和时间
        $_SESSION['authenticated'] = true;
        $_SESSION['validated_time'] = time();

        // 重定向到原有页面
        header('Location: so.php');
        exit;
    } else {
        // 密码错误，显示登录表单
        $error = "密码错误，请重新输入";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,minimum-scale=1,maximum-scale=1,initial-scale=1,user-scalable=no">
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
    <meta name="keywords" content="流浪猫,API,随机图,随机,随机图片,随机壁纸,ACG,美女,写真,图集,动漫">
    <meta name="description" content="陈小儒的流浪猫API集合">
    <meta name="author" content="陈小儒">
    <title>流浪猫API数据</title>
    <link rel="stylesheet" href="https://cdn.staticfile.org/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(to bottom, #371b81, #00163f);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-box {
            width: 300px;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .login-box input[type="password"] {
            width: 93%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .login-box input[type="submit"] {
            width: 100%;
            background: #3f46a1;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body><meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <div class="login-box">
        <h2>登录后台</h2>
        <?php if (isset($error)) { echo "<p style='color: red;'>$error</p>"; } ?>
        <form method="post" action="">
            <input type="password" name="password" placeholder="请输入密码" required />
            <input type="submit" value="登录" />
        </form>
    </div>
</body>
</html>
