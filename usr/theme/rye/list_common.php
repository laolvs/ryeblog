<?php
/**
 * RyeBlog 官方主题 —— 列表页公共骨架（分类/标签/搜索，企业风）
 * 期望变量：$listTitle $listTotal $listPageUrl(闭包) $listItems $listPages $listPage
 */
$GLOBALS['__rye_seo'] = $GLOBALS['__rye_seo'] ?? ['desc' => '', 'keywords' => ''];
$siteTitle = siteTitle();
$themeCss  = baseUrl('usr/theme/rye/theme.css?v=' . (@filemtime(__DIR__ . '/theme.css') ?: '1'));
$navPages  = getPages();
$btnDownloadUrl = getOption('rye_download_url', baseUrl('download.html'));
$btnDocUrl      = getOption('rye_doc_url', baseUrl('category/docs.html'));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($listTitle . ' - ' . $siteTitle); ?></title>
<meta name="description" content="<?php echo esc($GLOBALS['__rye_seo']['desc'] ?? ''); ?>">
<link rel="icon" href="<?php echo baseUrl('assets/img/logo-512.png'); ?>">
<link rel="stylesheet" href="<?php echo $themeCss; ?>">
<?php echo doHook('header'); ?>
</head>
<body class="theme-rye">

<header class="rye-header">
    <div class="rye-header-inner">
        <a class="rye-brand" href="<?php echo homeUrl(); ?>">
            <img class="rye-brand-logo" src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="RyeBlog">
            <?php echo esc($siteTitle); ?><small>Rye</small>
        </a>
        <nav class="rye-nav">
            <a href="<?php echo homeUrl(); ?>">首页</a>
            <a href="<?php echo esc($btnDownloadUrl); ?>">下载</a>
            <a href="<?php echo esc($btnDocUrl); ?>">文档</a>
            <a href="<?php echo categoryUrl(['slug' => 'knowledge']); ?>">知识库</a>
            <a href="<?php echo categoryUrl(['slug' => 'cases']); ?>">案例展示</a>
            <a href="https://demo.ryeblog.com/" target="_blank" rel="noopener">演示</a>
            <?php echo doHook('nav_top'); ?>
            <?php foreach ($navPages as $pg): ?>
            <a href="<?php echo pageUrl($pg); ?>"><?php echo esc(L($pg, 'title')); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="rye-header-cta">
            <a class="rye-btn rye-btn-primary rye-btn-sm" href="<?php echo esc($btnDownloadUrl); ?>">立即下载</a>
        </div>
    </div>
</header>

<main class="rye-page">
    <h1 class="rye-page-title"><?php echo esc($listTitle); ?></h1>
    <div class="rye-page-meta"><span><?php echo __('共'); ?> <?php echo (int)$listTotal; ?> <?php echo __('篇'); ?></span></div>
    <?php if (empty($listItems)): ?>
        <p style="color:var(--muted)"><?php echo __('没有找到相关内容。'); ?></p>
    <?php else: ?>
    <div class="rye-list">
        <?php foreach ($listItems as $p): ?>
        <a class="rye-list-item" href="<?php echo postUrl($p); ?>">
            <div class="rye-list-main">
                <span class="rye-list-title"><?php echo esc(L($p, 'title')); ?></span>
                <?php if (!empty($p['excerpt'])): ?><span class="rye-list-desc"><?php echo esc(L($p, 'excerpt')); ?></span><?php endif; ?>
            </div>
            <span class="rye-list-date"><?php echo formatDate($p['created_at'], 'Y-m-d'); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($listPages > 1): ?>
        <?php echo renderPager($listPage, $listPages, $listPageUrl); ?>
    <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/inc_footer.php'; ?>
