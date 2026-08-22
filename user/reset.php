<?php
/**
 * RyeBlog 重置密码
 */
require_once __DIR__ . '/../inc/functions.php';

// 维护模式下前台重置密码一并关闭
enforceMaintenance();

if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }

$token = $_GET['token'] ?? '';
$err = $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = '表单已过期，请刷新重试。';
    } else {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if ($password !== $confirm) {
            $err = '两次输入的密码不一致。';
        } else {
            $result = resetPassword($token, $password);
            if ($result === true) {
                $ok = '密码已成功重置，请使用新密码登录。';
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
    <title>重置密码 · <?php echo esc(siteTitle()); ?></title>
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
        <h1>重置密码</h1>
        <p class="sub">设置您的新密码</p>
        <?php if ($err): ?><div class="uc-notice-err"><?php echo esc($err); ?></div><?php endif; ?>
        <?php if ($ok): ?>
            <div class="uc-notice-ok"><?php echo esc($ok); ?></div>
            <a class="btn" href="<?php echo baseUrl('user/login.php'); ?>" style="display:block;text-align:center;text-decoration:none">去登录</a>
        <?php else: ?>
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="token" value="<?php echo esc($token); ?>">
        <label>新密码</label>
        <input type="password" name="password" required placeholder="至少 6 位" autofocus>
        <label>确认新密码</label>
        <input type="password" name="confirm" required>
        <p style="margin-top:20px"><button class="btn" type="submit">重置密码</button></p>
        <?php endif; ?>
        <div class="auth-links">
            <a href="<?php echo baseUrl('user/login.php'); ?>">← 返回登录</a>
        </div>
    </form>
</div>
</body>
</html>
