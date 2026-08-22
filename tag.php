<?php
/** RyeBlog —— 标签页 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/card.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
enforceLangPrefix();
enforceMaintenance();
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$slug = $_GET['t'] ?? '';

// 兼容旧链接：更新记录按「分类」归档（slug=updates），tag/updates 301 到分类页
if ($slug === 'updates') {
    header('Location: ' . categoryUrl(['slug' => 'updates']), true, 301);
    exit;
}

$tag = getTag($slug);
if (!$tag) {
    http_response_code(404);
    publicHeader(__('标签不存在'));
    echo '<div class="empty-box"><p>' . __('该标签不存在。') . '</p></div>';
    publicFooter();
    exit;
}
$page = max(1, (int)($_GET['p'] ?? 1));
$result = getPosts(['tag' => $slug, 'page' => $page, 'perPage' => postsPerPage()]);
$posts = $result['items'];

// 主题模板：主题目录带 tag.php 时标签页由主题模板渲染（内容页风格）
$tagTpl = themeTemplate('tag');
if ($tagTpl) {
    require $tagTpl;
    exit;
}

// 标签页 SEO：描述与关键词用标签名（en 态优先 name_en）
$GLOBALS['__rye_seo'] = [
    'desc'     => __('标签：') . L($tag, 'name') . ' - ' . siteSeoDescription(),
    'keywords' => L($tag, 'name'),
];

publicHeader(__('标签：') . L($tag, 'name'));
?>
<div class="page-head"><h1><?php echo __('标签：'); ?>#<?php echo esc(L($tag, 'name')); ?></h1><p><?php echo __('共 '); ?><?php echo (int)$result['total']; ?><?php echo __(' 篇文章'); ?></p></div>
<?php if (empty($posts)): ?>
    <div class="empty-box"><p><?php echo __('该标签下还没有文章。'); ?></p></div>
<?php else: foreach ($posts as $post) echo renderPostCard($post); ?>
    <?php if ($result['pages'] > 1): ?>
        <?php echo renderPager($result['page'], $result['pages'], function ($i) use ($tag) { return tagUrl($tag) . '?p=' . $i; }); ?>
    <?php endif; ?>
<?php endif; ?>
<?php publicFooter();
