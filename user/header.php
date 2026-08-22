<?php
/**
 * RyeBlog 用户中心 —— 共用骨架
 */
require_once __DIR__ . '/../inc/functions.php';

// 维护模式下用户中心一并关闭（与前台各入口一致）
enforceMaintenance();

if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }

/**
 * 用户中心页面顶部
 */
function userHeader($title = '', $active = '')
{
    $user = currentUser();
    $nav = [
        'index.php'       => '面板',
        'fav.php'         => '收藏',
        'annotations.php' => '划线',
        'corrections.php' => '纠错',
        'trail.php'       => '轨迹',
        'profile.php'     => '资料',
    ];
    $avatar = $user ? userAvatar($user, 64) : '';
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc($title); ?> · 用户中心 · <?php echo esc(siteTitle()); ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?php echo baseUrl('assets/img/logo-64.png'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/themes.css'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/user.css'); ?>">
</head>
<body class="theme-<?php echo esc(currentTheme()); ?>">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?php echo homeUrl(); ?>">
            <img src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="logo" class="brand-logo">
            <span class="brand-name"><?php echo esc(siteTitle()); ?></span>
        </a>
        <nav class="site-nav">
            <a href="<?php echo homeUrl(); ?>">← 返回首页</a>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo baseUrl('user/logout.php'); ?>">退出</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="container">
<div class="user-layout">
    <aside class="user-side">
        <?php if ($user): ?>
        <div class="user-card">
            <img class="uc-avatar" src="<?php echo esc($avatar); ?>" alt="<?php echo esc($user['username']); ?>">
            <h3><?php echo esc($user['username']); ?></h3>
            <p class="uc-email"><?php echo esc($user['email']); ?></p>
            <?php if (!empty($user['bio'])): ?><p class="uc-bio"><?php echo esc($user['bio']); ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
        <nav class="user-nav">
            <?php foreach ($nav as $file => $label): ?>
                <a href="<?php echo baseUrl('user/' . $file); ?>" class="<?php echo $active === $file ? 'active' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="user-main">
<?php
}

function userFooter()
{
    ?>
    </main>
</div>
</div>
<footer class="site-footer">
    <div class="container footer-inner">
        <p class="footer-copy"><?php echo footerCopyright(); ?></p>
        <p class="footer-support"><?php echo footerSupport(); ?></p>
    </div>
</footer>
</body>
</html>
<?php
}
