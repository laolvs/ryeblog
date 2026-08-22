<?php
/**
 * RyeBlog 官方主题 —— 首页（企业宣传风，参考 halo.run）
 * 结构：顶栏 → Hero（大幅宣传+嫩芽点缀）→ 优点矩阵 → 下载 → 入口卡片 → 最新动态 → 页脚
 */
$siteTitle = siteTitle();
$slogan    = siteSlogan();
$seoDesc   = siteSeoDescription() ?: ($slogan ?: $siteTitle);
$GLOBALS['__rye_seo'] = ['desc' => $seoDesc, 'keywords' => 'RyeBlog,博客系统,开源博客,PHP博客'];
$themeCss  = baseUrl('usr/theme/rye/theme.css?v=' . (@filemtime(__DIR__ . '/theme.css') ?: '1'));
$logoUrl   = baseUrl('assets/img/logo-512.png');
// 顶栏 CTA 链接（后台可配）
$btnDownloadUrl = getOption('rye_download_url', baseUrl('download.html'));
$btnDocUrl      = getOption('rye_doc_url', baseUrl('category/docs.html'));
// 最新动态（按时间由旧到新，取 6 条）
$newsList  = getPosts(['perPage' => 6, 'withTags' => false, 'orderBy' => 'p.created_at ASC'])['items'] ?? [];
$navPages  = getPages();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($siteTitle . ' - ' . $slogan); ?></title>
<meta name="description" content="<?php echo esc($seoDesc); ?>">
<link rel="icon" href="<?php echo baseUrl('assets/img/logo-512.png'); ?>">
<link rel="stylesheet" href="<?php echo $themeCss; ?>">
<?php echo doHook('header'); ?>
</head>
<body class="theme-rye">

<header class="rye-header">
    <div class="rye-header-inner">
        <a class="rye-brand" href="<?php echo homeUrl(); ?>">
            <img class="rye-brand-logo" src="<?php echo $logoUrl; ?>" alt="RyeBlog">
            <?php echo esc($siteTitle); ?><small>Rye</small>
        </a>
        <nav class="rye-nav">
            <a href="<?php echo homeUrl(); ?>" class="active">首页</a>
            <a href="<?php echo esc($btnDownloadUrl); ?>">下载</a>
            <a href="<?php echo esc($btnDocUrl); ?>">文档</a>
            <a href="<?php echo categoryUrl(['slug' => 'knowledge']); ?>">知识库</a>
            <a href="<?php echo categoryUrl(['slug' => 'cases']); ?>">案例展示</a>
            <a href="https://demo.ryeblog.com/" target="_blank" rel="noopener">演示</a>
            <?php echo doHook('nav_top'); ?>
            <?php foreach ($navPages as $pg): ?>
            <?php if ($pg['slug'] === 'download') continue; // 下载入口已在导航与 Hero 提供，避免重复 ?>
            <a href="<?php echo pageUrl($pg); ?>"><?php echo esc(L($pg, 'title')); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="rye-header-cta">
            <a class="rye-btn rye-btn-primary rye-btn-sm" href="<?php echo esc($btnDownloadUrl); ?>">立即下载</a>
        </div>
    </div>
</header>

<!-- Hero -->
<section class="rye-hero">
    <div class="rye-hero-inner">
        <span class="rye-hero-badge">🌱 高效简洁 · 零依赖 · 开源免费</span>
        <h1 class="rye-hero-title"><?php echo getOption('rye_hero_title', '让博客回归 <em>简单</em>'); // 允许 <em> 高亮，后台配置信任 ?></h1>
        <p class="rye-hero-sub"><?php echo esc(getOption('rye_hero_sub', 'RyeBlog 是一款轻量、安全、可扩展的 PHP 博客系统。原生 PHP 编写、零依赖，插件主题在线安装，博客从此不再复杂。')); ?></p>
        <div class="rye-hero-actions">
            <a class="rye-btn rye-btn-primary" href="<?php echo esc($btnDownloadUrl); ?>">⬇ 立即下载</a>
            <a class="rye-btn rye-btn-ghost" href="https://github.com/laolvs/ryeblog" target="_blank" rel="noopener">★ GitHub 源码</a>
            <a class="rye-btn rye-btn-ghost" href="<?php echo esc($btnDocUrl); ?>">📖 查看文档</a>
            <a class="rye-btn rye-btn-ghost" href="https://teayear.com/" target="_blank" rel="noopener">🚀 百万级演示站</a>
        </div>
        <p class="rye-hero-version">当前版本 <code>v<?php echo esc(RYEBLOG_VERSION); ?></code> · 支持 <code>PHP 8.1+</code> · <code>MySQL 5.7/8.0</code></p>
    </div>
</section>

<!-- 优点矩阵 -->
<section class="rye-section">
    <div class="rye-container">
        <div class="rye-section-head">
            <p class="rye-section-eyebrow">Why RyeBlog</p>
            <h2 class="rye-section-title">为什么选择 RyeBlog？</h2>
            <p class="rye-section-desc">嫩芽虽小，欣欣向荣。每一个细节都为「简单、安全、可扩展」而生。</p>
        </div>
        <div class="rye-features">
            <?php for ($i = 1; $i <= 6; $i++): ?>
            <?php $ft = getOption("rye_feature_{$i}_title", ''); if ($ft === '') continue; ?>
            <div class="rye-feature">
                <div class="rye-feature-icon"><?php echo getOption("rye_feature_{$i}_icon", '🌱'); ?></div>
                <h3 class="rye-feature-title"><?php echo esc($ft); ?></h3>
                <p class="rye-feature-desc"><?php echo esc(getOption("rye_feature_{$i}_desc", '')); ?></p>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- 下载 -->
<section class="rye-section rye-section-alt rye-download">
    <div class="rye-container">
        <div class="rye-download-box">
            <span class="rye-download-badge">最新版本</span>
            <h2 class="rye-download-ver">v<?php echo esc(RYEBLOG_VERSION); ?></h2>
            <p class="rye-download-tag"><?php echo esc(getOption('rye_download_tag', '全新发布 · 一键安装 · 数据导入 WordPress / Typecho')); ?></p>
            <div class="rye-download-actions">
                <a class="rye-btn rye-btn-primary rye-btn-download" href="<?php echo esc($btnDownloadUrl); ?>">
                    <span class="rye-dl-icon">⬇</span>
                    <span class="rye-dl-text">
                        <b>下载 RyeBlog</b>
                        <small>安装包 · 约 2.8 MB · GitHub Releases</small>
                    </span>
                </a>
                <a class="rye-btn rye-btn-ghost" href="<?php echo esc($btnDocUrl); ?>">📖 安装文档</a>
                <a class="rye-btn rye-btn-ghost" href="<?php echo esc(categoryUrl(['slug' => 'docs'])); ?>">🛠 开发文档</a>
                <a class="rye-btn rye-btn-ghost" href="<?php echo esc(categoryUrl(['slug' => 'updates'])); ?>">📝 更新记录</a>
            </div>
            <p class="rye-download-req">运行环境：<code>PHP 8.1+</code> · <code>MySQL 5.7+ / 8.0+</code> · <code>Apache / Nginx</code> · <code>零依赖</code></p>
        </div>
    </div>
</section>

<!-- 入口卡片 -->
<section class="rye-section">
    <div class="rye-container">
        <div class="rye-gates">
            <a class="rye-gate" href="<?php echo esc($btnDocUrl); ?>">
                <span class="rye-gate-icon">📖</span>
                <span class="rye-gate-name">在线文档</span>
                <span class="rye-gate-desc">安装部署、主题插件开发、API 参考</span>
                <span class="rye-gate-more">进入文档 →</span>
            </a>
            <a class="rye-gate" href="<?php echo categoryUrl(['slug' => 'knowledge']); ?>">
                <span class="rye-gate-icon">🗂</span>
                <span class="rye-gate-name">知识库</span>
                <span class="rye-gate-desc">常见问题、技巧经验、最佳实践</span>
                <span class="rye-gate-more">浏览知识库 →</span>
            </a>
            <a class="rye-gate" href="<?php echo categoryUrl(['slug' => 'cases']); ?>">
                <span class="rye-gate-icon">🏆</span>
                <span class="rye-gate-name">案例展示</span>
                <span class="rye-gate-desc">看看大家都用 RyeBlog 建了什么站</span>
                <span class="rye-gate-more">提交你的案例 →</span>
            </a>
            <a class="rye-gate" href="<?php echo esc(baseUrl('bbs/')); ?>">
                <span class="rye-gate-icon">💬</span>
                <span class="rye-gate-name">社区</span>
                <span class="rye-gate-desc">交流讨论、问题求助、贡献代码</span>
                <span class="rye-gate-more">进入社区 →</span>
            </a>
        </div>
    </div>
</section>

<!-- 最新动态 -->
<section class="rye-section rye-section-alt" id="community">
    <div class="rye-container">
        <div class="rye-section-head">
            <p class="rye-section-eyebrow">News</p>
            <h2 class="rye-section-title">最新动态</h2>
        </div>
        <div class="rye-news">
            <?php if (empty($newsList)): ?>
                <p class="muted" style="text-align:center;color:var(--muted)">还没有发布内容。</p>
            <?php else: foreach ($newsList as $p): ?>
            <a class="rye-news-item" href="<?php echo postUrl($p); ?>">
                <span class="rye-news-date"><?php echo formatDate($p['created_at'], 'Y-m-d'); ?></span>
                <span class="rye-news-title"><?php echo esc(L($p, 'title')); ?></span>
                <?php if (!empty($p['category_name'])): ?><span class="rye-news-cat"><?php echo esc(L($p, 'category_name')); ?></span><?php endif; ?>
            </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/inc_footer.php'; ?>
