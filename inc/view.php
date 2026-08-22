<?php
/**
 * RyeBlog —— 前台页面骨架（响应式：PC 左右栏，移动端仅左栏）
 * 头部无搜索框，登录链接置于导航最右。
 * 侧边栏模块由后台「侧边栏管理」配置，动态渲染。
 */

// 文章页 TOC：由 post.php 设置
$GLOBALS['__rye_toc_html'] = '';
// 文章页 SEO（description / keywords）：由 post.php 设置
$GLOBALS['__rye_seo'] = ['desc' => '', 'keywords' => ''];

function publicHeader($title = '')
{
    $pageTitle = $title ? esc($title) . ' · ' . esc(siteTitle()) : esc(siteTitle());
    $navCats   = getCategories();
    $navPages  = getPages();
    $topMenus  = getMenus('top');
    $seo       = $GLOBALS['__rye_seo'];
    $desc      = $seo['desc'] !== '' ? esc($seo['desc']) : esc(siteSlogan());
    $keywords  = $seo['keywords'] !== '' ? esc($seo['keywords']) : '';

    // 过滤掉菜单中的 "首页"（顶部已硬编码）
    $topMenus = array_values(array_filter($topMenus, function ($m) {
        return trim($m['title']) !== '首页';
    }));
    ?>
<!DOCTYPE html>
<html lang="<?php echo currentLang() === 'en' ? 'en' : 'zh-CN'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $desc; ?>">
    <?php if ($keywords): ?><meta name="keywords" content="<?php echo $keywords; ?>"><?php endif; ?>
    <?php if (bilingualEnabled()): $alt = altLangUrls(); ?>
    <link rel="alternate" hreflang="zh-CN" href="<?php echo esc($alt['zh']); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo esc($alt['en']); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo esc($alt['zh']); ?>">
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo esc(siteTitle()); ?>" href="<?php echo feedUrl(); ?>">
    <link rel="icon" href="<?php echo baseUrl('assets/img/logo-64.png'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/themes.css?v=' . (@filemtime(RYEBLOG_ROOT . '/assets/css/themes.css') ?: '1')); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/style.css?v=' . (@filemtime(RYEBLOG_ROOT . '/assets/css/style.css') ?: '1')); ?>">
    <?php $themeCss = getThemeCssUrl(currentTheme()); if ($themeCss): ?>
    <link rel="stylesheet" href="<?php echo $themeCss; ?>">
    <?php endif; ?>
    <?php $themeJs = getThemeJsUrl(currentTheme()); if ($themeJs): ?>
    <script src="<?php echo $themeJs; ?>" defer></script>
    <?php endif; ?>
    <?php echo doHook('header'); ?>
</head>
<body class="theme-<?php echo esc(currentTheme()); ?><?php echo !empty($GLOBALS['__rye_body_class']) ? ' ' . esc($GLOBALS['__rye_body_class']) : ''; ?>">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?php echo homeUrl(); ?>">
            <img src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="logo" class="brand-logo">
            <span class="brand-name"><?php echo esc(siteTitle()); ?></span>
        </a>
        <button class="nav-toggle" aria-label="<?php echo __('菜单'); ?>" onclick="document.body.classList.toggle('nav-open')">☰</button>
        <nav class="site-nav">
            <a href="<?php echo homeUrl(); ?>"><?php echo __('首页'); ?></a>
            <?php foreach ($topMenus as $m): ?>
                <?php if (isCategoryTreeMenu($m)): ?>
                    <div class="nav-drop">
                        <span class="nav-drop-btn"><?php echo esc(L($m, 'title')); ?><i class="nav-drop-arrow">▾</i></span>
                        <div class="nav-drop-menu">
                            <?php echo renderCategoryNavTree($navCats); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo esc($m['resolved_url']); ?>"<?php echo $m['target']==='_blank'?' target="_blank" rel="noopener"':''; ?>><?php echo esc(L($m, 'title')); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$navPages && !$topMenus): foreach ($navCats as $c): ?>
                <a href="<?php echo categoryUrl($c); ?>"><?php echo esc(L($c, 'name')); ?></a>
            <?php endforeach; endif; ?>
            <?php echo doHook('nav_top'); ?>
            <?php
                // 顶部登录按钮：带 redirect 继承（在哪页登录，登录后回哪页）
                $loginHref = isLoggedIn()
                    ? baseUrl('user/')
                    : baseUrl('user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
                $loginText = isLoggedIn() ? (currentUser()['username'] ?? __('用户中心')) : __('登录');
            ?>
            <a class="nav-login" href="<?php echo $loginHref; ?>"><?php echo esc($loginText); ?></a>
            <?php echo langSwitchHtml(); ?>
        </nav>
    </div>
</header>

<div class="container layout">
<main class="content-col">
<?php
}

function publicFooter($customSidebar = null)
{
    $year = date('Y');
    $footMenus = getMenus('footer');
    $pageType = detectPageType();
    // 插件可传入自定义侧栏（如论坛版块导航）；默认渲染博客侧栏
    $sidebar = $customSidebar !== null ? $customSidebar : renderSidebar($pageType);
    ?>
</main>

<aside class="sidebar<?php echo getOption('sidebar_sticky', '0') === '1' ? ' sidebar-sticky' : ''; ?>">
    <?php echo $sidebar; ?>
</aside>

</div><!-- /.layout -->

<footer class="site-footer">
    <div class="container footer-top">
        <div class="footer-brand">
            <a class="footer-logo" href="<?php echo homeUrl(); ?>">
                <img src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="logo" class="footer-logo-mark">
                <span><?php echo esc(siteTitle()); ?></span>
            </a>
            <p class="footer-slogan"><?php echo esc(siteSlogan()); ?></p>
        </div>
        <div class="footer-nav">
            <?php if ($footMenus): ?>
            <div class="footer-col">
                <h4 class="footer-col-title"><?php echo __('快速导航'); ?></h4>
                <nav class="footer-links">
                    <?php foreach ($footMenus as $m): ?>
                        <a href="<?php echo esc($m['resolved_url']); ?>"<?php echo $m['target']==='_blank'?' target="_blank" rel="noopener"':''; ?>><?php echo esc(L($m, 'title')); ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php endif; ?>
            <div class="footer-col">
                <h4 class="footer-col-title"><?php echo __('关于本站'); ?></h4>
                <nav class="footer-links">
                    <a href="<?php echo homeUrl(); ?>"><?php echo __('首页'); ?></a>
                    <a href="<?php echo feedUrl(); ?>"><?php echo __('RSS 订阅'); ?></a>
                    <a href="<?php echo baseUrl('sitemap.xml'); ?>"><?php echo __('网站地图'); ?></a>
                    <a href="<?php echo baseUrl('docs.php?doc=HELP'); ?>"><?php echo __('帮助文档'); ?></a>
                </nav>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <p class="footer-copy"><?php echo footerCopyright(); ?></p>
            <p class="footer-support"><?php echo footerSupport(); ?></p>
            <?php if (footerIcp()): ?><p class="footer-icp"><?php echo esc(footerIcp()); ?></p><?php endif; ?>
        </div>
    </div>
</footer>
<?php if (footerStats()): echo footerStats(); endif; ?>
<?php echo doHook('footer'); ?>
</body>
</html>
<?php
    // 重置：下一个页面使用同一进程时清空
    $GLOBALS['__rye_toc_html'] = '';
    $GLOBALS['__rye_seo'] = ['desc' => '', 'keywords' => ''];
}
