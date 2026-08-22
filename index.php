<?php
/** RyeBlog —— 站点首页（图文列表） */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/card.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }

// 双语模式：无前缀请求统一 301 → /cn/（含根路径、分页、query）；纯中文模式不动
enforceLangPrefix();
enforceMaintenance();
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$page = max(1, (int)($_GET['p'] ?? 1));
$result = getPosts(['page' => $page, 'perPage' => postsPerPage(), 'withTags' => true]);
$posts = $result['items'];

// 主题模板：主题目录带 home.php 时首页由主题模板渲染（文档站/产品站首页）
$homeTpl = themeTemplate('home');
if ($homeTpl) {
    require $homeTpl;
    exit;
}

// 站点级 SEO（首页/兜底；en 态优先英文版）
$GLOBALS['__rye_seo'] = [
    'desc'     => siteSeoDescription(),
    'keywords' => siteSeoKeywords(),
];

publicHeader();
?>
<?php if (getOption('home_hero', '1') === '1'): ?>
<section class="hero">
    <div class="hero-inner">
        <img class="hero-logo" src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="RyeBlog" loading="lazy">
        <h1 class="hero-title">RyeBlog <span class="hero-sub">青禾博客系统</span></h1>
        <p class="hero-slogan"><?php echo esc(siteSlogan()); ?></p>
        <p class="hero-desc">轻量、优雅的开源博客系统 · 中文/英文双语站 · 云端市场一键扩展</p>
        <div class="hero-actions">
            <a class="btn btn-hero-primary" href="<?php echo baseUrl('download.html'); ?>">⬇ <?php echo __('立即下载'); ?></a>
            <a class="btn btn-hero-ghost" href="<?php echo baseUrl('docs.php?doc=HELP'); ?>">📖 <?php echo __('在线文档'); ?></a>
            <a class="btn btn-hero-ghost" href="<?php echo baseUrl('page/nav'); ?>">✨ <?php echo __('案例展示'); ?></a>
        </div>
    </div>
</section>
<?php endif; ?>
<?php if (empty($posts)): ?>
    <div class="empty-box"><p><?php echo __('还没有发布任何文章。'); ?></p></div>
<?php else: foreach ($posts as $post) echo renderPostCard($post); ?>

    <?php if ($result['pages'] > 1): ?>
        <?php echo renderPager($result['page'], $result['pages'], 'homePageUrl'); ?>
    <?php endif; ?>
<?php endif; ?>
<?php echo doHook('home_after'); ?>
<?php publicFooter();
