<?php
/**
 * RyeBlog 用户登录
 */
require_once __DIR__ . '/../inc/functions.php';

// 维护模式下前台登录一并关闭（后台登录不受影响）
enforceMaintenance();

if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }

/** 登录后跳转白名单：仅允许站内路径（单个 / 开头，拒绝 // 协议相对与外部 URL），防 open redirect */
function safeLoginRedirect($url)
{
    $url = trim((string)$url);
    if ($url === '' || $url === '/user/login.php') return '';
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    $base = rtrim(baseUrl(''), '/');
    if ($base !== '' && str_starts_with($url, $base . '/')) return $url;
    return '';
}

$loginRedirect = safeLoginRedirect($_GET['redirect'] ?? '');
if (isLoggedIn()) {
    header('Location: ' . ($loginRedirect ?: baseUrl('user/index.php')));
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = '表单已过期，请刷新重试。';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($login === '' || $password === '') {
            $err = '请填写用户名/邮箱和密码。';
        } elseif (userLogin($login, $password)) {
            // 优先 GET redirect（各页面登录按钮自带当前页），其次 session（require_login 跳转），默认用户中心
            $redirect = $loginRedirect ?: ($_SESSION['rye_user_redirect'] ?? '');
            unset($_SESSION['rye_user_redirect']);
            header('Location: ' . ($redirect ?: baseUrl('user/index.php')));
            exit;
        } else {
            $err = '用户名/邮箱或密码错误。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 · <?php echo esc(siteTitle()); ?></title>
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
        <p class="sub">用户登录</p>
        <?php if ($err): ?><div class="uc-notice-err"><?php echo esc($err); ?></div><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <label>用户名或邮箱</label>
        <input type="text" name="login" required autofocus>
        <label>密码</label>
        <input type="password" name="password" required>
        <p style="margin-top:20px"><button class="btn" type="submit">登 录</button></p>
        <div class="auth-links">
            <a href="<?php echo baseUrl('user/register.php'); ?>">注册新账号</a> ·
            <a href="<?php echo baseUrl('user/forgot.php'); ?>">忘记密码？</a><br>
            <a href="<?php echo homeUrl(); ?>">← 返回首页</a>
        </div>
    </form>
</div>
</body>
</html>
