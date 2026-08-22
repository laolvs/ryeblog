<?php
/**
 * Vuecho 文档主题 —— 文章/独立页模板（自包含文档式阅读，三栏）
 * 结构：fixed 顶栏（品牌+首页/独立页导航+搜索）→ 左文档树 + 中正文 + 右 TOC → 页脚
 * 上下文变量（post.php 注入）：$post $tags $atts $comments $rendered $tocList $prevPost $nextPost $msg
 */
$GLOBALS['__rye_seo'] = [
    'desc'     => postSeoDescription($post),
    'keywords' => postSeoKeywords($post),
];
$siteTitle = siteTitle();
$themeCss  = baseUrl('usr/theme/vuecho/theme.css?v=' . (@filemtime(__DIR__ . '/theme.css') ?: '1'));
$themeJs   = baseUrl('usr/theme/vuecho/theme.js?v=' . (@filemtime(__DIR__ . '/theme.js') ?: '1'));
// 品牌 logo：xuecha.png（600×600），带版本号防 CDN/浏览器缓存
$brandLogo = baseUrl('usr/theme/vuecho/assets/xuecha.png?v=' . (@filemtime(__DIR__ . '/assets/xuecha.png') ?: '1'));
$content   = $rendered['html'] ?? L($post, 'content'); // 注意：renderContentWithToc 返回键是 'html'，不是 'content'
$docToc    = $tocList ?? ($GLOBALS['__rye_toc_html'] ?? '');
// 顶部导航：首页 + 全部独立页（与原版一致）
$navPages  = getPages();
// 左侧文档列表：全部已发布文章按发布时间由旧到新；分类按后台分类管理顺序（getCategories），无分类文章不进树
$docPosts  = getPosts(['perPage' => 500, 'withTags' => false, 'orderBy' => 'p.created_at ASC'])['items'] ?? [];
$tmpGroups = [];
foreach ($docPosts as $dp) {
    $cat = $dp['category_name'] ?? '';
    if ($cat === '') continue; // 无分类文章不显示（避免出现「未分类」分组）
    $tmpGroups[$cat]['posts'][] = $dp;
}
$docGroups = [];
foreach (getCategories() as $c) {
    $name = $c['name'] ?? '';
    if (isset($tmpGroups[$name])) $docGroups[$name] = $tmpGroups[$name];
}
// 兜底：分类管理中已不存在但文章仍引用的分类，追加在最后
foreach ($tmpGroups as $name => $g) {
    if (!isset($docGroups[$name])) $docGroups[$name] = $g;
}
$currentId = (int)($post['id'] ?? 0);
// 估算阅读时长（中文约 400 字/分钟）
$readChars = mb_strlen(strip_tags($content ?? ''));
$readMin   = max(1, (int)ceil($readChars / 400));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc(L($post, 'title') . ' - ' . $siteTitle); ?></title>
<meta name="description" content="<?php echo esc(postSeoDescription($post)); ?>">
<link rel="icon" href="<?php echo $brandLogo; ?>">
<link rel="stylesheet" href="<?php echo $themeCss; ?>">
<?php echo doHook('header'); ?>
</head>
<body class="theme-vuecho doc-post<?php echo getOption('hide_toc') === '1' ? ' no-toc' : ''; ?>">
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
            <!-- 文档分类树 -->
            <div class="sidebar-section">
                <h3 class="sidebar-title"><?php echo esc(getOption('docs_sidebar_title', __('学习目录'))); ?></h3>
                <div class="docs-tree">
                    <?php if (empty($docGroups)): ?>
                        <p class="muted"><?php echo __('还没有发布任何内容。'); ?></p>
                    <?php else: ?>
                        <ul class="tree-root">
                            <?php foreach ($docGroups as $catName => $group): ?>
                            <?php $catPosts = $group['posts']; ?>
                            <?php $isCurrentCat = false; foreach ($catPosts as $dp) { if ((int)$dp['id'] === $currentId) { $isCurrentCat = true; break; } } ?>
                            <li class="tree-node<?php echo $isCurrentCat ? ' expanded' : ''; ?>">
                                <div class="node-content">
                                    <span class="node-toggle" aria-expanded="<?php echo $isCurrentCat ? 'true' : 'false'; ?>">▸</span>
                                    <span class="node-label">
                                        <svg class="node-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                        <span class="node-name"><?php echo esc(L(['name' => $catName], 'name')); ?></span>
                                        <span class="node-count"><?php echo count($catPosts); ?></span>
                                    </span>
                                </div>
                                <ul class="tree-children"<?php echo $isCurrentCat ? '' : ' style="display:none"'; ?>>
                                    <?php foreach ($catPosts as $dp): ?>
                                    <li class="tree-leaf<?php echo (int)$dp['id'] === $currentId ? ' current-post' : ''; ?>">
                                        <div class="leaf-content">
                                            <span class="leaf-spacer"></span>
                                            <div class="leaf-label">
                                                <svg class="leaf-icon" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>
                                                <a class="leaf-link<?php echo (int)$dp['id'] === $currentId ? ' active' : ''; ?>" href="<?php echo postUrl($dp); ?>"><?php echo esc(L($dp, 'title')); ?></a>
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
            <article class="docs-article">
                <header class="article-header">
                    <h1 class="article-title"><?php echo esc(L($post, 'title')); ?></h1>
                    <div class="article-meta">
                        <div class="meta-item">
                            <svg class="meta-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                            <time class="meta-date"><?php echo formatDate($post['created_at'], 'Y年m月d日'); ?></time>
                        </div>
                        <?php if ($post['category_name']): ?>
                        <div class="meta-item">
                            <svg class="meta-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                            <a href="<?php echo categoryUrl(['slug' => $post['category_slug']]); ?>" class="meta-category"><?php echo esc(L($post, 'category_name')); ?></a>
                        </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <svg class="meta-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <span class="meta-reading"><?php echo __('约') . ' ' . $readMin . ' ' . __('分钟阅读'); ?></span>
                        </div>
                        <?php if ($tags): ?>
                        <div class="meta-item meta-tags"><?php foreach ($tags as $t): ?><a href="<?php echo tagUrl($t); ?>">#<?php echo esc(L($t, 'name')); ?></a><?php endforeach; ?></div>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if ($docToc !== '' && getOption('hide_toc') !== '1'): ?>
                <div class="toc-mobile" id="toc-mobile">
                    <div class="toc-mobile-header" id="toc-mobile-header">
                        <span class="toc-mobile-title"><?php echo __('本页目录'); ?></span>
                        <button class="toc-mobile-toggle" id="toc-mobile-toggle" type="button" aria-label="折叠目录">▾</button>
                    </div>
                    <div class="toc-mobile-body" id="toc-mobile-body"><?php echo $docToc; ?></div>
                </div>
                <?php endif; ?>

                <div class="article-content"><?php echo $content; ?></div>

        <?php if ($prevPost || $nextPost): ?>
        <nav class="doc-post-nav">
            <?php if ($prevPost): ?>
                <a class="doc-post-nav-prev" href="<?php echo esc(postUrl($prevPost)); ?>">
                    <span class="doc-post-nav-label">← <?php echo __('上一篇'); ?></span>
                    <span class="doc-post-nav-title"><?php echo esc(L($prevPost, 'title')); ?></span>
                </a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($nextPost): ?>
                <a class="doc-post-nav-next" href="<?php echo esc(postUrl($nextPost)); ?>">
                    <span class="doc-post-nav-label"><?php echo __('下一篇'); ?> →</span>
                    <span class="doc-post-nav-title"><?php echo esc(L($nextPost, 'title')); ?></span>
                </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php echo doHook('post_content_after'); /* 文末版权等插件输出 */ ?>

        <?php if ($post['allow_comment'] ?? 1): ?>
        <div class="doc-comments">
            <h3><?php echo __('评论'); ?> (<?php echo count($comments); ?>)</h3>
            <?php if ($msg): ?><div class="notice notice-ok"><?php echo esc($msg); ?></div><?php endif; ?>
            <ul class="doc-comment-list">
                <?php foreach ($comments as $c): ?>
                    <li class="doc-comment-item">
                        <div class="doc-comment-meta"><?php echo esc($c['author']); ?> · <?php echo formatDate($c['created_at'], 'Y-m-d H:i'); ?></div>
                        <div><?php echo nl2br(esc($c['content'])); ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($comments)): ?><li class="muted"><?php echo __('还没有评论，来抢沙发吧。'); ?></li><?php endif; ?>
            </ul>
            <form class="doc-comment-form" method="post">
                <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="comment" value="1">
                <?php echo doHook('comment_form_extra'); /* 防垃圾插件注入蜜罐/时间戳等 */ ?>
                <div class="doc-comment-fields">
                    <input type="text" name="author" placeholder="<?php echo __('昵称 *'); ?>" value="<?php echo esc($_POST['author'] ?? (currentUser()['username'] ?? '')); ?>" required>
                    <input type="email" name="email" placeholder="<?php echo __('邮箱（不公开）'); ?>" value="<?php echo esc($_POST['email'] ?? (currentUser()['email'] ?? '')); ?>">
                    <input type="url" name="website" placeholder="<?php echo __('个人网站'); ?>" value="<?php echo esc($_POST['website'] ?? ''); ?>">
                </div>
                <textarea name="content" rows="4" placeholder="<?php echo __('评论内容 *'); ?>" required><?php echo esc($_POST['content'] ?? ''); ?></textarea>
                <button class="doc-comment-btn" type="submit"><?php echo __('发表评论'); ?></button>
            </form>
        </div>
        <?php endif; ?>
            </article>
        </div>
    </main>

    <?php if ($docToc !== '' && getOption('hide_toc') !== '1'): ?>
    <aside class="toc-desktop" id="toc-desktop">
        <div class="toc-desktop-wrapper">
            <div class="toc-desktop-header">
                <span class="toc-desktop-title"><?php echo __('本页目录'); ?></span>
            </div>
            <div class="toc-desktop-body"><?php echo $docToc; ?></div>
        </div>
    </aside>
    <button class="toc-desktop-toggle" id="toc-desktop-toggle" type="button" aria-label="目录">
        <svg class="toc-desktop-toggle-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    </button>
    <?php endif; ?>
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
