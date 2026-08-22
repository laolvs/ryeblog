<?php
/**
 * Vuecho 文档主题 —— 首页模板（自包含文档站布局，参考 Vuecho 原版首页）
 * 结构：文档顶栏（品牌+导航+搜索）→ Hero（Logo+标题+副标题+双按钮）→ 特性卡片×3 → 学习目录 → 文档页脚
 * 文案在后台「站点设置 → 首页宣传区（文档主题）」配置；导航取自后台「菜单管理」。
 */
$GLOBALS['__rye_seo'] = [
    'desc'     => siteSeoDescription(),
    'keywords' => siteSeoKeywords(),
];
$siteTitle = siteTitle();
$slogan    = siteSlogan();
$topMenus  = getMenus('top');
$navPages  = getPages();
$seoDesc   = siteSeoDescription() ?: ($slogan ?: $siteTitle);
$heroTitle = getOption('hero_title', $siteTitle);
$heroSub   = getOption('hero_subtitle', __('技术文档 · 教程 · 手册 · 快速上手指南'));
$heroBadge = getOption('hero_badge', __('文档 v1 · 持续更新'));
$logoUrl   = baseUrl('assets/img/logo-512.png');
// Hero 区品牌 logo：后台 hero_logo / site_logo 可配；fallback 通用 RyeBlog logo
$logoBase = getOption('hero_logo', getOption('site_logo', 'assets/img/logo-512.png'));
$heroLogoUrl = baseUrl($logoBase . (strpos($logoBase, '?') !== false ? '&' : '?') . 'v=' . (@filemtime(RYEBLOG_ROOT . '/' . $logoBase) ?: '1'));
$btn1Text  = getOption('hero_btn1_text', '快速上手');
// 「快速上手」默认指向第一篇文档（避免 what-is-tea.html 404）；后台 hero_btn1_url 可覆盖
$firstDoc  = getPosts(['perPage' => 1])['items'][0] ?? null;
$btn1Url   = getOption('hero_btn1_url', $firstDoc ? postUrl($firstDoc) : homeUrl());
$btn2Text  = getOption('hero_btn2_text', '访问 RyeBlog 官方');
$btn2Url   = getOption('hero_btn2_url', 'https://ryeblog.com/');
// 主题 CSS/JS 带版本号（文件修改时间），主题更新自动绕过 CDN/浏览器缓存
$themeCss  = baseUrl('usr/theme/vuecho/theme.css?v=' . (@filemtime(__DIR__ . '/theme.css') ?: '1'));
$themeJs   = baseUrl('usr/theme/vuecho/theme.js?v=' . (@filemtime(__DIR__ . '/theme.js') ?: '1'));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($heroTitle . ' - ' . $siteTitle); ?></title>
<meta name="description" content="<?php echo esc($seoDesc); ?>">
<link rel="icon" href="<?php echo $logoUrl; ?>">
<link rel="stylesheet" href="<?php echo $themeCss; ?>">
<?php echo doHook('header'); ?>
</head>
<body class="theme-vuecho doc-home doc-post">
<header class="docs-header">
    <div class="docs-header-inner">
        <a class="docs-brand" href="<?php echo homeUrl(); ?>">
            <img class="docs-brand-logo" src="<?php echo $heroLogoUrl; ?>" alt="logo">
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
    </div>
</header>

<section class="hero-section">
    <div class="hero-container">
        <div class="hero-logo">
            <img src="<?php echo $heroLogoUrl; ?>" alt="Logo" class="logo-image">
        </div>
        <div class="hero-content">
            <div class="hero-text">
                <span class="hero-badge"><?php echo esc($heroBadge); ?></span>
                <h1 class="hero-title"><?php echo esc($heroTitle); ?></h1>
                <p class="hero-description"><?php echo esc($heroSub); ?></p>
                <div class="hero-actions">
                    <?php if ($btn1Text !== ''): ?>
                    <a href="<?php echo esc($btn1Url); ?>" class="btn btn-primary"><?php echo esc($btn1Text); ?></a>
                    <?php endif; ?>
                    <?php if ($btn2Text !== ''): ?>
                    <a href="<?php echo esc($btn2Url); ?>" class="btn btn-secondary"><?php echo esc($btn2Text); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="features-section">
    <div class="features-container">
        <div class="features-grid">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <?php $ft = getOption("feature_{$i}_title", ''); if ($ft === '') continue; ?>
            <div class="feature-item">
                <h3 class="feature-title"><?php echo esc($ft); ?></h3>
                <p class="feature-description"><?php echo esc(getOption("feature_{$i}_desc", '')); ?></p>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<section class="docs-section">
    <div class="docs-section-inner">
        <h2 class="docs-section-title"><?php echo esc(getOption('docs_section_title', __('学习目录'))); ?></h2>
        <?php
        // 左侧文档目录树（按分类分组，结构同文档列表页 list_common）
        $treePosts = getPosts(['perPage' => 500, 'withTags' => false, 'orderBy' => 'p.created_at ASC'])['items'] ?? [];
        $tmpG = [];
        foreach ($treePosts as $tp) {
            $cat = $tp['category_name'] ?? '';
            if ($cat === '') continue;
            $tmpG[$cat][] = $tp;
        }
        $docGroups = [];
        foreach (getCategories() as $cc) {
            $nm = $cc['name'] ?? '';
            if (isset($tmpG[$nm])) $docGroups[$nm] = $tmpG[$nm];
        }
        foreach ($tmpG as $nm => $g) {
            if (!isset($docGroups[$nm])) $docGroups[$nm] = $g;
        }
        ?>
        <div class="docs-home-grid">
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
                                                <span class="node-name"><?php echo esc(L(['name' => $catName], 'name')); ?></span>
                                                <span class="node-count"><?php echo count($group); ?></span>
                                            </span>
                                        </div>
                                        <ul class="tree-children" style="display:none">
                                            <?php foreach ($group as $dp): ?>
                                            <li class="tree-leaf">
                                                <div class="leaf-content">
                                                    <span class="leaf-spacer"></span>
                                                    <div class="leaf-label">
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
            <div class="docs-home-main">
                <div class="doc-list">
                    <?php $docList = getPosts(['perPage' => 500, 'withTags' => true, 'orderBy' => 'p.created_at ASC'])['items'] ?? []; ?>
                    <?php if (empty($docList)): ?>
                        <p class="muted"><?php echo __('还没有发布任何内容。'); ?></p>
                    <?php else: foreach ($docList as $i => $post): ?>
                        <a class="doc-list-item" href="<?php echo postUrl($post); ?>">
                            <span class="doc-list-index"><?php echo str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                            <span class="doc-list-main">
                                <span class="doc-list-title"><?php echo esc(L($post, 'title')); ?></span>
                                <span class="doc-list-meta">
                                    <?php if (!empty($post['category_name'])): ?><i class="doc-list-cat"><?php echo esc(L($post, 'category_name')); ?></i><?php endif; ?>
                                    <?php if (!empty($post['tags'])): foreach (array_slice($post['tags'], 0, 3) as $t): ?><i class="doc-list-tag"><?php echo esc(L($t, 'name')); ?></i><?php endforeach; endif; ?>
                                </span>
                            </span>
                            <span class="doc-list-arrow">→</span>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
                <?php if (!empty($docList)): ?>
                <p class="docs-more"><a href="<?php echo esc(getOption('hero_btn1_url', homeUrl() . 'what-is-tea.html')); ?>"><?php echo __('开始学习 →'); ?></a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

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
