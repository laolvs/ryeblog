<?php
/**
 * RyeBlog 忘记密码 —— 申请重置
 */
require_once __DIR__ . '/../inc/functions.php';

// 维护模式下前台找回密码一并关闭
enforceMaintenance();

if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
if (isLoggedIn()) { header('Location: ' . baseUrl('user/index.php')); exit; }

$ok = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = '表单已过期，请刷新重试。';
    } else {
        $email = trim($_POST['email'] ?? '');
        $token = requestPasswordReset($email);
        if ($token) {
            // 无邮件服务时直接展示重置链接
            $resetUrl = baseUrl('user/reset.php?token=' . $token);
            $ok = '重置链接已生成。请点击下方链接重置密码：';
            $ok .= '<br><a href="' . esc($resetUrl) . '" style="word-break:break-all">' . esc($resetUrl) . '</a>';
        } else {
            // 不透露邮箱是否存在
            $ok = '如果该邮箱已注册，重置链接已生成。请检查您的邮箱。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘记密码 · <?php echo esc(siteTitle()); ?></title>
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
        <h1>忘记密码</h1>
        <p class="sub">输入注册邮箱以获取重置链接</p>
        <?php if ($err): ?><div class="uc-notice-err"><?php echo esc($err); ?></div><?php endif; ?>
        <?php if ($ok): ?><div class="uc-notice-ok"><?php echo $ok; ?></div><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <label>注册邮箱</label>
        <input type="email" name="email" required autofocus>
        <p style="margin-top:20px"><button class="btn" type="submit">获取重置链接</button></p>
        <div class="auth-links">
            <a href="<?php echo baseUrl('user/login.php'); ?>">← 返回登录</a>
        </div>
    </form>
</div>
</body>
</html>
