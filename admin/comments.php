<?php
/**
 * RyeBlog 后台 —— 评论管理
 */
require_once __DIR__ . '/admin.php';

// 操作
if (isset($_GET['act'], $_GET['id'], $_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $cid = (int)$_GET['id'];
    if ($_GET['act'] === 'approve') {
        dbQuery("UPDATE vd_comments SET status='approved' WHERE id=?", [$cid]);
    } elseif ($_GET['act'] === 'delete') {
        dbQuery('DELETE FROM vd_comments WHERE id=?', [$cid]);
    }
    header('Location: ' . baseUrl('admin/comments.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '')));
    exit;
}

// 批量删除（POST + CSRF）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试。');
    }
    $filter = urlencode($_POST['filter'] ?? 'all');
    $act = $_POST['act'] ?? '';
    if ($act === 'bulk_delete') {
        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        $n = count($ids);
        if ($n > 0) {
            $ph = implode(',', array_fill(0, $n, '?'));
            dbQuery("DELETE FROM vd_comments WHERE id IN ($ph)", array_values($ids));
        }
        header('Location: ' . baseUrl('admin/comments.php?filter=' . $filter));
        exit;
    } elseif ($act === 'delete_pending') {
        dbQuery("DELETE FROM vd_comments WHERE status='pending'");
        header('Location: ' . baseUrl('admin/comments.php?filter=' . $filter));
        exit;
    }
}

$filter = $_GET['filter'] ?? 'all';
$where = '1=1';
// 注意：vd_posts 也有 status 列，必须加 c. 前缀避免歧义
if ($filter === 'pending') $where = "c.status='pending'";
elseif ($filter === 'approved') $where = "c.status='approved'";

$comments = dbAll("SELECT c.*, p.title AS post_title FROM vd_comments c
                  LEFT JOIN vd_posts p ON p.id = c.post_id
                  WHERE $where ORDER BY c.id DESC");

$pendingCount = (int) dbOne("SELECT COUNT(*) c FROM vd_comments WHERE status='pending'")['c'];

adminHead(__('评论管理'), 'comments.php');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h1><?php echo __('评论管理'); ?></h1>
    <div>
        <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/comments.php?filter=all'); ?>"><?php echo __('全部'); ?></a>
        <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/comments.php?filter=pending'); ?>"><?php echo __('待审核'); ?><?php echo $pendingCount > 0 ? ' (' . $pendingCount . ')' : ''; ?></a>
        <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/comments.php?filter=approved'); ?>"><?php echo __('已通过'); ?></a>
    </div>
</div>

<form method="post" id="bulk-form" onsubmit="return confirm('<?php echo __('确定删除选中的评论？'); ?>')">
    <input type="hidden" name="_csrf" value="<?php echo esc(csrfToken()); ?>">
    <input type="hidden" name="act" value="bulk_delete">
    <input type="hidden" name="filter" value="<?php echo esc($filter); ?>">
    <div style="display:flex;align-items:center;gap:10px;margin:14px 0;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:4px;font-weight:600">
            <input type="checkbox" id="check-all" onclick="var c=document.querySelectorAll('.bulk-id');c.forEach(function(x){x.checked=this.checked},this)"> <?php echo __('全选'); ?>
        </label>
        <button class="btn btn-danger btn-sm" type="submit"><?php echo __('批量删除所选'); ?></button>
        <span id="sel-count" class="muted"></span>
    </div>

    <div class="panel">
        <?php foreach ($comments as $c): ?>
            <div class="comment-item" style="margin-bottom:14px">
                <div class="c-meta">
                    <input type="checkbox" class="bulk-id" name="ids[]" value="<?php echo $c['id']; ?>" style="margin-right:8px">
                    <strong><?php echo esc($c['author']); ?></strong>
                    · <?php echo formatDate($c['created_at'], 'Y-m-d H:i'); ?>
                    · <?php echo __('于《'); ?><a href="<?php echo baseUrl('post.php?p=' . $c['post_id']); ?>"><?php echo esc($c['post_title']); ?></a><?php echo __('》'); ?>
                    · <span class="tag"><?php echo $c['status']==='pending'?__('待审核'):__('已通过'); ?></span>
                </div>
                <div style="margin:6px 0"><?php echo nl2br(esc($c['content'])); ?></div>
                <div style="display:flex;gap:8px">
                    <?php if ($c['status'] === 'pending'): ?>
                        <a class="btn btn-sm" href="<?php echo baseUrl('admin/comments.php?act=approve&id=' . $c['id'] . '&_csrf=' . csrfToken()); ?>"><?php echo __('通过'); ?></a>
                    <?php endif; ?>
                    <a class="btn btn-danger btn-sm" href="<?php echo baseUrl('admin/comments.php?act=delete&id=' . $c['id'] . '&_csrf=' . csrfToken()); ?>" onclick="return confirm('<?php echo __('确定删除该评论？'); ?>')"><?php echo __('删除'); ?></a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?><p class="muted"><?php echo __('暂无评论。'); ?></p><?php endif; ?>
    </div>
</form>

<form method="post" onsubmit="return confirm('<?php echo __('确定删除全部未通过（待审核）评论？此操作不可恢复。'); ?>')" style="margin-top:14px">
    <input type="hidden" name="_csrf" value="<?php echo esc(csrfToken()); ?>">
    <input type="hidden" name="act" value="delete_pending">
    <input type="hidden" name="filter" value="<?php echo esc($filter); ?>">
    <button class="btn btn-danger btn-sm" type="submit" <?php echo $pendingCount === 0 ? 'disabled' : ''; ?>><?php echo __('删除所有未通过评论'); ?> <?php echo $pendingCount > 0 ? '(' . $pendingCount . ')' : ''; ?></button>
    <span class="muted" style="margin-left:8px"><?php echo __('清空待审核列表（不含已通过评论）'); ?></span>
</form>
<script>
document.querySelectorAll('.bulk-id').forEach(function(cb){
    cb.addEventListener('change', function(){
        var n = document.querySelectorAll('.bulk-id:checked').length;
        document.getElementById('sel-count').textContent = n > 0 ? '已选 ' + n + ' 条' : '';
    });
});
</script>
<?php adminFoot();
