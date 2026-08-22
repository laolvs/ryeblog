<?php
/**
 * RyeBlog 用户注册
 */
require_once __DIR__ . '/../inc/functions.php';

// 维护模式下前台注册一并关闭
enforceMaintenance();

if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
if (isLoggedIn()) { header('Location: ' . baseUrl('user/index.php')); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = '表单已过期，请刷新重试。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if ($password !== $confirm) {
            $err = '两次输入的密码不一致。';
        } else {
            $result = userRegister($username, $email, $password);
            if ($result === true) {
                userLogin($username, $password);
                header('Location: ' . baseUrl('user/index.php'));
                exit;
            } else {
                $err = $result;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 · <?php echo esc(siteTitle()); ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?php echo baseUrl('assets/img/logo-64.png'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/themes.css'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/user.css'); ?>">
</head>
<body class="theme-<?php echo esc(currentTheme()); ?>">
<div class="auth-wrap">
    <form class="auth-box" method="post">
        <div class="auth-logo"><img src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="RyeBlog"></div>
        <h1><?php echo esc(siteTitle()); ?></h1>
        <p class="sub">注册新账号</p>
        <?php if ($err): ?><div class="uc-notice-err"><?php echo esc($err); ?></div><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <label>用户名</label>
        <input type="text" name="username" required autofocus placeholder="2-30 个字符">
        <label>邮箱</label>
        <input type="email" name="email" required placeholder="用于登录和找回密码">
        <label>密码</label>
        <input type="password" name="password" required placeholder="至少 6 位">
        <label>确认密码</label>
        <input type="password" name="confirm" required>
        <p style="margin-top:20px"><button class="btn" type="submit">注 册</button></p>
        <div class="auth-links">
            <a href="<?php echo baseUrl('user/login.php'); ?>">已有账号？去登录</a><br>
            <a href="<?php echo homeUrl(); ?>">← 返回首页</a>
        </div>
    </form>
</div>
</body>
</html>
