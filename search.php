<?php
/** RyeBlog —— 搜索 / 归档筛选页 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/card.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
enforceLangPrefix();
enforceMaintenance();
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$q = trim($_GET['q'] ?? '');
$archive = trim($_GET['archive'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));

if ($archive !== '') {
    // 按月归档：用索引友好的范围查询 + 标准分页，百万级数据也能秒回
    $result = getPosts(['month' => $archive, 'page' => $page, 'perPage' => postsPerPage()]);
    $title = __('归档：') . $archive;
} else {
    $result = getPosts(['search' => $q, 'page' => $page, 'perPage' => postsPerPage()]);
    $title = $q !== '' ? __('搜索：') . $q : __('搜索');
}
$posts = $result['items'];

// 主题模板：主题目录带 search.php 时搜索/归档页由主题模板渲染（内容页风格）
$searchTpl = themeTemplate('search');
if ($searchTpl) {
    require $searchTpl;
    exit;
}

publicHeader($title);
?>
<div class="page-head">
    <h1><?php echo esc($title); ?></h1>
    <p><?php echo __('共 '); ?><?php echo (int)$result['total']; ?><?php echo __(' 篇文章'); ?></p>
    <?php if ($archive === ''): ?>
    <form method="get" action="<?php echo searchUrl(); ?>" class="search-form" style="margin-top:10px;display:flex;gap:6px">
        <input type="search" name="q" value="<?php echo esc($q); ?>" placeholder="<?php echo __('输入关键词'); ?>">
        <button class="btn" type="submit"><?php echo __('搜索'); ?></button>
    </form>
    <?php endif; ?>
</div>
<?php if (empty($posts)): ?>
    <div class="empty-box"><p><?php echo __('没有找到相关文章。'); ?></p></div>
<?php else: foreach ($posts as $post) echo renderPostCard($post); ?>
    <?php if ($result['pages'] > 1): ?>
        <?php echo renderPager($result['page'], $result['pages'], function ($i) use ($q, $archive) { return searchPageUrl($q, $archive, $i); }); ?>
    <?php endif; ?>
<?php endif; ?>
<?php publicFooter();
