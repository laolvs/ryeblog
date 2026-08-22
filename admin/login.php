<?php
/**
 * RyeBlog 后台登录
 */
require_once __DIR__ . '/../inc/functions.php';
setCurrentLang(adminLang());

if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
if (isAdmin()) { header('Location: ' . baseUrl('admin/index.php')); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已过期，请刷新重试。');
    } elseif (adminLogin($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: ' . baseUrl('admin/index.php'));
        exit;
    } else {
        $err = __('用户名或密码错误。');
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo adminLang()==='en'?'en':'zh-CN'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('登录'); ?> · RyeBlog <?php echo __('后台'); ?></title>
    <link rel="icon" href="<?php echo baseUrl('assets/img/logo-64.png'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/themes.css'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/admin.css'); ?>">
</head>
<body class="theme-<?php echo esc(currentTheme()); ?>">
<div class="login-wrap">
    <form class="login-box" method="post">
        <div style="text-align:center;margin-bottom:6px">
            <img src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="RyeBlog" style="width:56px;height:56px">
        </div>
        <h1>RyeBlog</h1>
        <p class="sub"><?php echo __('博客管理后台登录'); ?></p>
        <?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <label><?php echo __('用户名或邮箱'); ?></label>
        <input type="text" name="username" required autofocus>
        <label><?php echo __('密码'); ?></label>
        <input type="password" name="password" required>
        <p style="margin-top:20px"><button class="btn" style="width:100%" type="submit"><?php echo __('登 录'); ?></button></p>
        <p class="muted" style="text-align:center;margin-top:14px"><a href="<?php echo baseUrl(); ?>">← <?php echo __('返回站点首页'); ?></a></p>
        <p class="muted" style="text-align:center;margin-top:8px">
            <a href="<?php echo baseUrl('docs.php?doc=HELP'); ?>" target="_blank">📖 <?php echo __('帮助文档'); ?></a>
            ·
            <a href="<?php echo baseUrl('docs.php?doc=LICENSE'); ?>" target="_blank">⚖️ <?php echo __('授权协议'); ?></a>
        </p>
    </form>
</div>
</body>
</html>
