<?php
/**
 * RyeBlog 后台 —— 文章 / 页面管理
 */
require_once __DIR__ . '/admin.php';

// 删除 → 移入回收站（软删除 status='trash'）
if (isset($_GET['del']) && isset($_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $delId = (int)$_GET['del'];
    dbQuery("UPDATE vd_posts SET status='trash' WHERE id = ?", [$delId]);
    bumpContentRev();
    rebuildArchiveStats(); // 删除→回收站：归档月计数校准
    header('Location: ' . baseUrl('admin/posts.php?type=' . urlencode($_GET['type'] ?? 'post') . '&moved=trash'));
    exit;
}

// 回收站：恢复 / 彻底删除
if (isset($_GET['restore'], $_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $rid = (int)$_GET['restore'];
    dbQuery("UPDATE vd_posts SET status='draft' WHERE id = ? AND status='trash'", [$rid]);
    bumpContentRev();
    rebuildArchiveStats(); // 恢复：归档月计数校准
    header('Location: ' . baseUrl('admin/trash.php?restored=1'));
    exit;
}
if (isset($_GET['purge'], $_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $pid = (int)$_GET['purge'];
    dbQuery('DELETE FROM vd_comments WHERE post_id = ?', [$pid]);
    dbQuery('DELETE FROM vd_post_tags WHERE post_id = ?', [$pid]);
    dbQuery("DELETE FROM vd_posts WHERE id = ? AND status='trash'", [$pid]);
    header('Location: ' . baseUrl('admin/trash.php?purged=1'));
    exit;
}
if (isset($_GET['empty'], $_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    // 清空回收站（仅 trash 状态）
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

// 每日自动清理：回收站中超过保留期（默认 90 天）的文章彻底删除（后台访问触发，幂等）
$retention = max(1, (int)getOption('trash_retention_days', '90'));
$lastRunKey = 'trash_cleanup_last_run';
$lastRun = (int)getOption($lastRunKey, '0');
if (time() - $lastRun > 86400) {
    $expired = dbAll("SELECT id FROM vd_posts WHERE status='trash' AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$retention]) ?: [];
    foreach ($expired as $r) {
        dbQuery('DELETE FROM vd_comments WHERE post_id = ?', [(int)$r['id']]);
        dbQuery('DELETE FROM vd_post_tags WHERE post_id = ?', [(int)$r['id']]);
        dbQuery('DELETE FROM vd_posts WHERE id = ?', [(int)$r['id']]);
    }
    setOption($lastRunKey, (string)time());
}

$type = ($_GET['type'] ?? 'post') === 'page' ? 'page' : 'post';
$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

// 搜索 + 分页查询（排除回收站）
$where  = "WHERE p.type = ? AND p.status <> 'trash'";
$params = [$type];
if ($q !== '') {
    $where .= " AND (p.title LIKE ? OR p.content LIKE ? OR p.slug LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$total = (int)dbOne("SELECT COUNT(*) c FROM vd_posts p $where", $params)['c'];
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$items = dbAll("SELECT p.*, c.name AS category_name FROM vd_posts p
                LEFT JOIN vd_categories c ON c.id = p.category_id
                $where ORDER BY p.created_at DESC LIMIT " . $offset . ', ' . $perPage, $params);

// 分页 URL 辅助（保留 type/q）
$listUrl = function ($i) use ($type, $q) {
    return baseUrl('admin/posts.php?type=' . urlencode($type) . '&p=' . $i . ($q !== '' ? '&q=' . urlencode($q) : ''));
};

adminHead($type === 'page' ? __('页面管理') : __('文章管理'), 'posts.php');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h1><?php echo $type === 'page' ? __('独立页面') : __('文章'); ?><?php echo __('管理'); ?></h1>
    <div>
        <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/posts.php?type=post'); ?>"><?php echo __('文章'); ?></a>
        <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/posts.php?type=page'); ?>"><?php echo __('页面'); ?></a>
        <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/trash.php'); ?>">🗑 <?php echo __('回收站'); ?></a>
        <a class="btn btn-sm" href="<?php echo baseUrl('admin/write.php'); ?>">+ <?php echo __('新建'); ?></a>
    </div>
</div>

<div class="panel" style="margin-top:18px">
    <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="hidden" name="type" value="<?php echo esc($type); ?>">
        <input type="search" name="q" value="<?php echo esc($q); ?>" placeholder="<?php echo __('按标题/内容/别名搜索…'); ?>" style="flex:1;min-width:220px;padding:8px 12px;border:1px solid var(--line,#e2e8f0);border-radius:8px;font-size:13.5px">
        <button class="btn btn-ghost" type="submit">🔍 <?php echo __('搜索'); ?></button>
        <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?php echo baseUrl('admin/posts.php?type=' . $type); ?>"><?php echo __('清除'); ?></a><?php endif; ?>
    </form>
    <p class="muted" style="font-size:.85rem;margin:0 0 10px"><?php echo __('共'); ?> <?php echo $total; ?> <?php echo __('篇'); ?><?php echo $q !== '' ? '（' . __('关键词') . '：' . esc($q) . '）' : ''; ?></p>
    <table class="data">
        <tr><th><?php echo __('标题'); ?></th><th><?php echo __('分类'); ?></th><th><?php echo __('英文'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('时间'); ?></th><th><?php echo __('操作'); ?></th></tr>
        <?php foreach ($items as $it): ?>
            <?php $translated = !empty($it['title_en']) || !empty($it['content_en']); ?>
            <tr>
                <td><a href="<?php echo baseUrl('admin/write.php?id=' . $it['id']); ?>"><?php echo esc($it['title']); ?></a></td>
                <td><?php echo esc($it['category_name'] ?: '—'); ?></td>
                <td><?php echo $translated ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>'; ?></td>
                <td class="<?php echo $it['status']==='draft'?'status-draft':'status-published'; ?>"><?php echo $it['status']==='draft'?__('草稿'):__('已发布'); ?></td>
                <td class="muted"><?php echo formatDate($it['created_at']); ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/write.php?id=' . $it['id']); ?>"><?php echo __('编辑'); ?></a>
                    <a class="btn btn-ghost btn-sm" href="<?php echo esc(postUrlForLang($it, 'en')); ?>" target="_blank" rel="noopener"><?php echo __('英文版'); ?> ↗</a>
                    <a class="btn btn-danger btn-sm" href="<?php echo baseUrl('admin/posts.php?type=' . $type . '&del=' . $it['id'] . '&_csrf=' . csrfToken()); ?>" onclick="return confirm('<?php echo __('删除后将移入回收站，可在回收站恢复。确定删除？'); ?>')"><?php echo __('删除'); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?><tr><td colspan="6" class="muted"><?php echo __('暂无内容。'); ?></td></tr><?php endif; ?>
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
