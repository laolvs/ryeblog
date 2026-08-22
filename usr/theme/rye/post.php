<?php
/**
 * RyeBlog 官方主题 —— 文章/独立页模板（企业风居中阅读）
 * 上下文：$post $tags $rendered $tocList $prevPost $nextPost $msg（page 模板复用本文件）
 */
// 下载页（slug=download，经 post.php 路由命中）渲染专属下载页
if (($post['slug'] ?? '') === 'download') {
    require __DIR__ . '/dl_page.php';
    exit;
}
$siteTitle = siteTitle();
$GLOBALS['__rye_seo'] = $GLOBALS['__rye_seo'] ?? ['desc' => '', 'keywords' => ''];
$themeCss  = baseUrl('usr/theme/rye/theme.css?v=' . (@filemtime(__DIR__ . '/theme.css') ?: '1'));
$content   = $rendered['html'] ?? L($post, 'content');
$navPages  = getPages();
$btnDownloadUrl = getOption('rye_download_url', baseUrl('download.html'));
$btnDocUrl      = getOption('rye_doc_url', baseUrl('category/docs.html'));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc(L($post, 'title') . ' - ' . $siteTitle); ?></title>
<meta name="description" content="<?php echo esc(postSeoDescription($post)); ?>">
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
    <h1 class="rye-page-title"><?php echo esc(L($post, 'title')); ?></h1>
    <div class="rye-page-meta">
        <?php if (($post['type'] ?? 'post') === 'post'): ?>
        <span><?php echo formatDate($post['created_at'], 'Y-m-d'); ?></span>
        <?php if (!empty($post['category_name'])): ?>
        <span>· <a href="<?php echo categoryUrl(['slug' => $post['category_slug']]); ?>"><?php echo esc(L($post, 'category_name')); ?></a></span>
        <?php endif; ?>
        <?php if ($tags): foreach ($tags as $t): ?><span>· <a href="<?php echo tagUrl($t); ?>">#<?php echo esc(L($t, 'name')); ?></a></span><?php endforeach; endif; ?>
        <?php endif; ?>
    </div>
    <div class="rye-page-content"><?php echo $content; ?></div>

    <?php if (($post['type'] ?? 'post') === 'post' && ($prevPost || $nextPost)): ?>
    <div style="display:flex;justify-content:space-between;gap:14px;margin-top:44px;padding-top:22px;border-top:1px solid var(--line)">
        <?php if ($prevPost): ?><a href="<?php echo esc(postUrl($prevPost)); ?>" style="font-size:13.5px;color:var(--muted)">← <?php echo esc(L($prevPost, 'title')); ?></a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($nextPost): ?><a href="<?php echo esc(postUrl($nextPost)); ?>" style="font-size:13.5px;color:var(--muted);text-align:right"><?php echo esc(L($nextPost, 'title')); ?> →</a><?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/inc_footer.php'; ?>
