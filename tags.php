<?php
/** RyeBlog —— 标签汇总页（全站标签云，分页）
 * 全站标签量可能极大（数十万），必须分页，否则一次性输出会导致数百 MB 页面、内存/超时。
 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
enforceLangPrefix();
enforceMaintenance();
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$perPage = 200;
$p = max(1, (int)($_GET['p'] ?? 1));
$total = (int)(dbOne("SELECT COUNT(*) AS c FROM vd_tags WHERE count > 0")['c']);
$pages = max(1, (int)ceil($total / $perPage));
$p = min($p, $pages);
$tags = dbAll(
    "SELECT * FROM vd_tags WHERE count > 0 ORDER BY count DESC, id ASC LIMIT ?, ?",
    [($p - 1) * $perPage, $perPage]
);

$GLOBALS['__rye_seo'] = [
    'desc'     => siteSeoDescription(),
    'keywords' => siteSeoKeywords(),
];

publicHeader(__('标签'));
?>
<div class="page-content">
    <h1><?php echo __('标签'); ?> <small class="muted">(共 <?php echo $total; ?> 个 · 第 <?php echo $p; ?>/<?php echo $pages; ?> 页)</small></h1>
    <?php if ($tags): ?>
    <div class="tag-cloud" style="margin:18px 0">
        <?php foreach ($tags as $t): ?>
            <a href="<?php echo tagUrl($t); ?>" style="display:inline-block;margin:0 6px 8px 0;padding:4px 12px;background:var(--green-050);border:1px solid var(--line);border-radius:6px;color:var(--green-700);text-decoration:none;font-size:14px">
                <?php echo esc(L($t, 'name')); ?>
                <small class="muted">(<?php echo (int)$t['count']; ?>)</small>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pagination">
        <?php if ($p > 1): ?><a href="tags.php?p=<?php echo $p - 1; ?>">«</a><?php endif; ?>
        <?php
        $win = 2;
        $shown = [];
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === 1 || $i === $pages || ($i >= $p - $win && $i <= $p + $win)) $shown[] = $i;
        }
        $prev = null;
        foreach ($shown as $i) {
            if ($prev !== null && $i - $prev > 1) echo '<span class="dots">…</span>';
            if ($i === $p) echo '<span class="current">' . $i . '</span>';
            else echo '<a href="tags.php?p=' . $i . '">' . $i . '</a>';
            $prev = $i;
        }
        ?>
        <?php if ($p < $pages): ?><a href="tags.php?p=<?php echo $p + 1; ?>">»</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php else: ?>
        <p class="muted"><?php echo __('暂无标签。'); ?></p>
    <?php endif; ?>
</div>
<?php publicFooter();
