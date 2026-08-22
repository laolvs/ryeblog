<?php
/**
 * RyeBlog 后台 —— 回收站
 * 文章/页面删除后进入回收站（status='trash'），可恢复或彻底删除。
 * 默认保留 90 天，超期自动清空（后台每日首次访问触发）。
 */
require_once __DIR__ . '/admin.php';

// 回收站操作：恢复 / 彻底删除 / 清空（CSRF 校验，逻辑与 posts.php 一致）
if (isset($_GET['restore'], $_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $rid = (int)$_GET['restore'];
    dbQuery("UPDATE vd_posts SET status='draft' WHERE id = ? AND status='trash'", [$rid]);
    bumpContentRev();
    rebuildArchiveStats();
    header('Location: ' . baseUrl('admin/trash.php?restored=1'));
    exit;
}
if (isset($_GET['purge'], $_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $pid = (int)$_GET['purge'];
    dbQuery('DELETE FROM vd_comments WHERE post_id = ?', [$pid]);
    dbQuery('DELETE FROM vd_post_tags WHERE post_id = ?', [$pid]);
    dbQuery("DELETE FROM vd_posts WHERE id = ? AND status='trash'", [$pid]);
    bumpContentRev();
    rebuildArchiveStats();
    header('Location: ' . baseUrl('admin/trash.php?purged=1'));
    exit;
}
if (isset($_GET['empty'], $_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $ids = dbAll("SELECT id FROM vd_posts WHERE status='trash'") ?: [];
    foreach ($ids as $r) {
        dbQuery('DELETE FROM vd_comments WHERE post_id = ?', [(int)$r['id']]);
        dbQuery('DELETE FROM vd_post_tags WHERE post_id = ?', [(int)$r['id']]);
        dbQuery('DELETE FROM vd_posts WHERE id = ?', [(int)$r['id']]);
    }
    bumpContentRev();
    rebuildArchiveStats();
    header('Location: ' . baseUrl('admin/trash.php?emptied=1'));
    exit;
}

// 每日自动清理：回收站中超过保留期（默认 90 天）的内容彻底删除（后台访问触发，幂等）
$retentionAuto = max(1, (int)getOption('trash_retention_days', '90'));
$lastRunKey = 'trash_cleanup_last_run';
$lastRun = (int)getOption($lastRunKey, '0');
if (time() - $lastRun > 86400) {
    $expired = dbAll("SELECT id FROM vd_posts WHERE status='trash' AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$retentionAuto]) ?: [];
    foreach ($expired as $r) {
        dbQuery('DELETE FROM vd_comments WHERE post_id = ?', [(int)$r['id']]);
        dbQuery('DELETE FROM vd_post_tags WHERE post_id = ?', [(int)$r['id']]);
        dbQuery('DELETE FROM vd_posts WHERE id = ?', [(int)$r['id']]);
    }
    if ($expired) {
        bumpContentRev();
        rebuildArchiveStats();
    }
    setOption($lastRunKey, (string)time());
}

$retention = max(1, (int)getOption('trash_retention_days', '90'));
$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

$where  = "WHERE p.status = 'trash'";
$params = [];
if ($q !== '') {
    $where .= " AND (p.title LIKE ? OR p.slug LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
$total = (int)dbOne("SELECT COUNT(*) c FROM vd_posts p $where", $params)['c'];
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$items = dbAll("SELECT p.*, c.name AS category_name FROM vd_posts p
                LEFT JOIN vd_categories c ON c.id = p.category_id
                $where ORDER BY p.updated_at DESC LIMIT " . $offset . ', ' . $perPage, $params);

$listUrl = function ($i) use ($q) {
    return baseUrl('admin/trash.php?p=' . $i . ($q !== '' ? '&q=' . urlencode($q) : ''));
};

adminHead(__('回收站'), 'trash.php');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h1><?php echo __('回收站'); ?></h1>
    <div>
        <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/posts.php?type=post'); ?>"><?php echo __('← 文章管理'); ?></a>
        <a class="btn btn-danger btn-sm" href="<?php echo baseUrl('admin/trash.php?empty=1&_csrf=' . csrfToken()); ?>" onclick="return confirm('<?php echo __('确定清空回收站？该操作不可恢复！'); ?>')"><?php echo __('清空回收站'); ?></a>
    </div>
</div>

<div class="panel" style="margin-top:18px">
    <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="search" name="q" value="<?php echo esc($q); ?>" placeholder="<?php echo __('按标题/别名搜索…'); ?>" style="flex:1;min-width:220px;padding:8px 12px;border:1px solid var(--line,#e2e8f0);border-radius:8px;font-size:13.5px">
        <button class="btn btn-ghost" type="submit">🔍 <?php echo __('搜索'); ?></button>
        <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?php echo baseUrl('admin/trash.php'); ?>"><?php echo __('清除'); ?></a><?php endif; ?>
    </form>
    <p class="muted" style="font-size:.85rem;margin:0 0 10px">
        <?php echo __('共'); ?> <?php echo $total; ?> <?php echo __('篇'); ?>
        ｜ <?php echo __('回收站内容保留'); ?> <strong><?php echo $retention; ?> <?php echo __('天'); ?></strong>，<?php echo __('超期将自动清空（不可恢复）；也可手动恢复或彻底删除。'); ?>
    </p>
    <?php if (isset($_GET['restored'])): ?><div class="notice notice-ok">✓ <?php echo __('已恢复到草稿。'); ?></div><?php endif; ?>
    <?php if (isset($_GET['purged'])): ?><div class="notice notice-ok">✓ <?php echo __('已彻底删除。'); ?></div><?php endif; ?>
    <?php if (isset($_GET['emptied'])): ?><div class="notice notice-ok">✓ <?php echo __('回收站已清空。'); ?></div><?php endif; ?>
    <table class="data">
        <tr><th><?php echo __('标题'); ?></th><th><?php echo __('类型'); ?></th><th><?php echo __('删除时间'); ?></th><th><?php echo __('操作'); ?></th></tr>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?php echo esc($it['title']); ?></td>
                <td><?php echo $it['type'] === 'page' ? __('页面') : __('文章'); ?></td>
                <td class="muted"><?php echo formatDate($it['updated_at']); ?></td>
                <td style="white-space:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/trash.php?restore=' . $it['id'] . '&_csrf=' . csrfToken()); ?>"><?php echo __('恢复'); ?></a>
                    <a class="btn btn-danger btn-sm" href="<?php echo baseUrl('admin/trash.php?purge=' . $it['id'] . '&_csrf=' . csrfToken()); ?>" onclick="return confirm('<?php echo __('彻底删除？该操作不可恢复！'); ?>')"><?php echo __('彻底删除'); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?><tr><td colspan="4" class="muted"><?php echo __('回收站是空的。'); ?></td></tr><?php endif; ?>
    </table>
    <?php if ($pages > 1): ?>
    <div style="display:flex;gap:6px;align-items:center;justify-content:center;margin-top:16px;flex-wrap:wrap">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?php echo $listUrl($page - 1); ?>">← <?php echo __('上一页'); ?></a><?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="btn btn-sm" style="background:var(--g-500,#3eaf7c);color:#fff"><?php echo $i; ?></span>
            <?php elseif ($i <= 2 || $i >= $pages - 1 || abs($i - $page) <= 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?php echo $listUrl($i); ?>"><?php echo $i; ?></a>
            <?php elseif ($i === 3 || $i === $pages - 2): ?>
                <span class="muted">…</span>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?php echo $listUrl($page + 1); ?>"><?php echo __('下一页'); ?> →</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php adminFoot();
