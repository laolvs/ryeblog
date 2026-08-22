<?php
/**
 * Vuecho 文档主题 —— 列表页公共骨架（分类/标签/搜索共用，内容页风格）
 * 期望变量：$listTitle $listTotal $listPageUrl(闭包,$i) $listItems $listPages $listPage
 * 布局：fixed 顶栏 → 左文档树 + 右内容列表 → 页脚 → theme.js
 */
$GLOBALS['__rye_seo'] = $GLOBALS['__rye_seo'] ?? ['desc' => '', 'keywords' => ''];
$siteTitle = siteTitle();
$themeCss  = baseUrl('usr/theme/vuecho/theme.css?v=' . (@filemtime(__DIR__ . '/theme.css') ?: '1'));
$themeJs   = baseUrl('usr/theme/vuecho/theme.js?v=' . (@filemtime(__DIR__ . '/theme.js') ?: '1'));
// 站内品牌 logo：后台 hero_logo / site_logo 可配；fallback 通用 RyeBlog logo
$logoBase = getOption('hero_logo', getOption('site_logo', 'assets/img/logo-512.png'));
$brandLogo = baseUrl($logoBase . (strpos($logoBase, '?') !== false ? '&' : '?') . 'v=' . (@filemtime(RYEBLOG_ROOT . '/' . $logoBase) ?: '1'));
$navPages  = getPages();
// 左侧文档树（全部已发布文章按旧→新；分类按后台管理顺序，无分类不进树）
$docPosts  = getPosts(['perPage' => 500, 'withTags' => false, 'orderBy' => 'p.created_at ASC'])['items'] ?? [];
$tmpGroups = [];
foreach ($docPosts as $dp) {
    $cat = $dp['category_name'] ?? '';
    if ($cat === '') continue;
    $tmpGroups[$cat]['posts'][] = $dp;
}
$docGroups = [];
foreach (getCategories() as $c) {
    $name = $c['name'] ?? '';
    if (isset($tmpGroups[$name])) $docGroups[$name] = $tmpGroups[$name];
}
foreach ($tmpGroups as $name => $g) {
    if (!isset($docGroups[$name])) $docGroups[$name] = $g;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($listTitle . ' - ' . $siteTitle); ?></title>
<meta name="description" content="<?php echo esc($GLOBALS['__rye_seo']['desc'] ?? ''); ?>">
<link rel="icon" href="<?php echo $brandLogo; ?>">
<link rel="stylesheet" href="<?php echo $themeCss; ?>">
<?php echo doHook('header'); ?>
</head>
<body class="theme-vuecho doc-post">
<header class="docs-header">
    <div class="docs-header-inner">
        <a class="docs-brand" href="<?php echo homeUrl(); ?>">
            <img class="docs-brand-logo" src="<?php echo $brandLogo; ?>" alt="logo">
            <span class="docs-brand-name"><?php echo esc($siteTitle); ?></span>
        </a>
        <nav class="docs-nav">
            <a href="<?php echo homeUrl(); ?>"><?php echo __('首页'); ?></a>
            <?php foreach ($navPages as $pg): ?>
                <a href="<?php echo pageUrl($pg); ?>"><?php echo esc(L($pg, 'title')); ?></a>
            <?php endforeach; ?>
        </nav>
        <form class="docs-search" action="<?php echo baseUrl('search.php'); ?>" method="get" role="search">
            <input type="text" name="q" placeholder="<?php echo __('搜索…'); ?>" aria-label="搜索">
            <button type="submit" aria-label="搜索">🔍</button>
        </form>
        <button class="nav-toggle" id="nav-toggle" type="button" aria-label="打开目录" aria-expanded="false">
            <span class="toggle-line"></span><span class="toggle-line"></span><span class="toggle-line"></span>
        </button>
    </div>
</header>

<div class="sidebar-mask" id="sidebar-mask"></div>

<div class="docs-container">
    <aside class="docs-sidebar" id="docs-sidebar">
        <div class="sidebar-content">
            <div class="sidebar-section">
                <h3 class="sidebar-title"><?php echo esc(getOption('docs_sidebar_title', __('学习目录'))); ?></h3>
                <div class="docs-tree">
                    <?php if (empty($docGroups)): ?>
                        <p class="muted"><?php echo __('还没有发布任何内容。'); ?></p>
                    <?php else: ?>
                        <ul class="tree-root">
                            <?php foreach ($docGroups as $catName => $group): ?>
                            <li class="tree-node">
                                <div class="node-content">
                                    <span class="node-toggle" aria-expanded="false">▸</span>
                                    <span class="node-label">
                                        <svg class="node-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                        <span class="node-name"><?php echo esc(L(['name' => $catName], 'name')); ?></span>
                                        <span class="node-count"><?php echo count($group['posts']); ?></span>
                                    </span>
                                </div>
                                <ul class="tree-children" style="display:none">
                                    <?php foreach ($group['posts'] as $dp): ?>
                                    <li class="tree-leaf">
                                        <div class="leaf-content">
                                            <span class="leaf-spacer"></span>
                                            <div class="leaf-label">
                                                <svg class="leaf-icon" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>
                                                <a class="leaf-link" href="<?php echo postUrl($dp); ?>"><?php echo esc(L($dp, 'title')); ?></a>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </aside>

    <main class="docs-main">
        <div class="docs-content">
            <header class="article-header">
                <h1 class="article-title"><?php echo esc($listTitle); ?></h1>
                <div class="article-meta"><span><?php echo __('共'); ?> <?php echo (int)$listTotal; ?> <?php echo __('篇文章'); ?></span></div>
            </header>
            <?php if (empty($listItems)): ?>
                <div class="empty-box"><p><?php echo __('没有找到相关内容。'); ?></p></div>
            <?php else: ?>
                <div class="doc-list">
                    <?php foreach ($listItems as $i => $p): ?>
                    <a class="doc-list-item" href="<?php echo postUrl($p); ?>">
                        <span class="doc-list-index"><?php echo str_pad((string)($listPage === 1 ? $i + 1 : ($listPage - 1) * count($listItems) + $i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="doc-list-main">
                            <span class="doc-list-title"><?php echo esc(L($p, 'title')); ?></span>
                            <span class="doc-list-meta">
                                <?php if (!empty($p['category_name'])): ?><i class="doc-list-cat"><?php echo esc(L($p, 'category_name')); ?></i><?php endif; ?>
                                <i class="doc-list-date"><?php echo formatDate($p['created_at'], 'Y-m-d'); ?></i>
                            </span>
                        </span>
                        <span class="doc-list-arrow">→</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($listPages > 1): ?>
                    <?php echo renderPager($listPage, $listPages, $listPageUrl); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<footer class="docs-footer">
    <div class="docs-footer-inner">
        <div class="docs-footer-links">
            <?php foreach (getMenus('footer') as $m): ?>
                <a href="<?php echo esc($m['resolved_url']); ?>"><?php echo esc(L($m, 'title')); ?></a>
            <?php endforeach; ?>
            <a href="<?php echo feedUrl(); ?>">RSS</a>
        </div>
        <p class="docs-footer-copy">© <?php echo date('Y'); ?> <?php echo esc($siteTitle); ?> · Powered by <a href="https://ryeblog.com/" target="_blank" rel="noopener">RyeBlog</a></p>
    </div>
</footer>
<script src="<?php echo $themeJs; ?>"></script>
<?php echo footerStats(); ?>
<?php echo doHook('footer'); ?>
</body>
</html>
