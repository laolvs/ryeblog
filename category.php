<?php
/** RyeBlog —— 分类页 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/card.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
enforceLangPrefix();
enforceMaintenance();
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$slug = $_GET['c'] ?? '';
$cat = getCategoryBySlug($slug);
if (!$cat) {
    http_response_code(404);
    publicHeader(__('分类不存在'));
    echo '<div class="empty-box"><p>' . __('该分类不存在。') . '</p></div>';
    publicFooter();
    exit;
}
$page = max(1, (int)($_GET['p'] ?? 1));
$result = getPosts(['category' => $cat['id'], 'page' => $page, 'perPage' => postsPerPage()]);
$posts = $result['items'];

// 主题模板：主题目录带 category.php 时分类页由主题模板渲染（内容页风格）
$catTpl = themeTemplate('category');
if ($catTpl) {
    require $catTpl;
    exit;
}

// 分类页 SEO：描述用分类描述（en 态优先 desc_en），关键词用分类名
$GLOBALS['__rye_seo'] = [
    'desc'     => L($cat, 'description') !== '' ? L($cat, 'description') : (__('分类：') . L($cat, 'name')),
    'keywords' => L($cat, 'name'),
];

publicHeader(L($cat, 'name'));
?>
<div class="page-head"><h1><?php echo __('分类：'); ?><?php echo esc(L($cat, 'name')); ?></h1><p><?php echo __('共 '); ?><?php echo (int)$result['total']; ?><?php echo __(' 篇文章'); ?></p></div>
<?php if (empty($posts)): ?>
    <div class="empty-box"><p><?php echo __('该分类下还没有文章。'); ?></p></div>
<?php else: foreach ($posts as $post) echo renderPostCard($post); ?>
    <?php if ($result['pages'] > 1): ?>
        <?php echo renderPager($result['page'], $result['pages'], function ($i) use ($cat) { return categoryPageUrl($cat, $i); }); ?>
    <?php endif; ?>
<?php endif; ?>
<?php publicFooter();
