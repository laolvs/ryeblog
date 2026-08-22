<?php
/** RyeBlog —— 归档页：默认按月列表；?archive=YYYY-MM 进入该月文章列表（分页） */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/card.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
enforceLangPrefix();
enforceMaintenance();
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

// —— 指定月份：该月文章列表（分页）。pretty 模式下 archiveUrl 指向 /archive?archive=YYYY-MM，
//    故 archive.php 必须自行处理该参数（旧版只渲染月份列表，导致点击月份后永远停留在归档页）。 ——
$ym = trim((string)($_GET['archive'] ?? ''));
if ($ym !== '' && preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $page = max(1, (int)($_GET['p'] ?? 1));
    $result = getPosts(['month' => $ym, 'page' => $page, 'perPage' => postsPerPage(), 'withTags' => true]);
    $posts = $result['items'];

    publicHeader(__('归档') . ' · ' . esc($ym));
    ?>
    <div class="page-head">
        <h1><?php echo __('文章归档'); ?> · <?php echo esc($ym); ?></h1>
        <p><a href="<?php echo baseUrl('archive'); ?>">← <?php echo __('返回月份列表'); ?></a></p>
    </div>
    <?php if (empty($posts)): ?>
        <div class="empty-box"><p><?php echo __('该月暂无文章。'); ?></p></div>
    <?php else: foreach ($posts as $post) echo renderPostCard($post); ?>
        <?php if ($result['pages'] > 1): ?>
            <?php echo renderPager($result['page'], $result['pages'], function ($i) use ($ym) {
                return prettyOn()
                    ? langBase() . '/archive?archive=' . urlencode($ym) . ((int)$i > 1 ? '&p=' . (int)$i : '')
                    : withLang(baseUrl('search.php?archive=' . urlencode($ym) . ((int)$i > 1 ? '&p=' . (int)$i : '')));
            }); ?>
        <?php endif; ?>
    <?php endif; ?>
    <?php publicFooter();
    exit;
}

// —— 默认：月份列表 ——
$months = getArchiveMonths();
publicHeader(__('归档'));
?>
<div class="page-head"><h1><?php echo __('文章归档'); ?></h1><p><?php echo __('按月份浏览全部文章，共 '); ?><?php echo (int)array_sum(array_column($months ?: [], 'c')); ?><?php echo __(' 篇'); ?></p></div>
<div class="article" style="padding:18px 24px">
<?php if (empty($months)): ?>
    <p class="muted"><?php echo __('暂无文章。'); ?></p>
<?php else: ?>
    <ul style="list-style:none;column-count:3;column-gap:24px">
    <?php foreach ($months as $m): ?>
        <li style="padding:6px 0;border-bottom:1px dashed var(--line)">
            <a href="<?php echo archiveUrl($m['ym']); ?>"><?php echo esc($m['ym']); ?> <small class="muted">(<?php echo (int)$m['c']; ?> <?php echo __('篇'); ?>)</small></a>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>
</div>
<?php publicFooter();
