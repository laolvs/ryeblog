<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台举报管理
 * 路由：admin/plugin.php?p=rye&page=reports
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    $id  = (int) ($_POST['id'] ?? 0);
    if ($act === 'resolve') {
        dbQuery("UPDATE {$P}reports SET status='resolved', resolved_at=NOW() WHERE id=?", [$id]);
        set_flash('已标记为已处理', 'success');
    } elseif ($act === 'dismiss') {
        dbQuery("UPDATE {$P}reports SET status='dismissed', resolved_at=NOW() WHERE id=?", [$id]);
        set_flash('已忽略该举报', 'info');
    } elseif ($act === 'delete') {
        dbQuery("DELETE FROM {$P}reports WHERE id=?", [$id]);
        set_flash('已删除', 'success');
    }
    header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=reports'));
    exit;
}

$status = $_GET['status'] ?? 'pending';
$allowed = ['pending' => 'pending', 'resolved' => 'resolved', 'dismissed' => 'dismissed', 'all' => 'all'];
$st = $allowed[$status] ?? 'pending';
$where = $st === 'all' ? '' : "WHERE r.status='$st'";
$rows = db_all(
    "SELECT r.*, ru.username AS reporter, vu.username AS reported
     FROM {$P}reports r
     LEFT JOIN vd_users ru ON ru.id=r.reporter_id
     LEFT JOIN vd_users vu ON vu.id=r.reported_user_id
     $where ORDER BY r.created_at DESC LIMIT 200"
);
$flash = get_flash();

function ryebbs_rep_action($act, $id, $label, $danger = false)
{
    return '<form method="post" style="display:inline" onsubmit="return confirm(\'确定' . $label . '？\');">' . csrf_field() . '<input type="hidden" name="act" value="' . $act . '"><input type="hidden" name="id" value="' . $id . '"><button class="btn-sm ' . ($danger ? 'danger' : '') . '" type="submit">' . $label . '</button></form>';
}
function ryebbs_rep_target($r)
{
    if ($r['target_type'] === 'thread' && $r['target_id']) return bbs_url('thread?id=' . $r['target_id']);
    if ($r['target_type'] === 'post' && $r['target_id']) {
        $tid = db_val("SELECT thread_id FROM " . prefix() . "posts WHERE id=?", [$r['target_id']]);
        return $tid ? bbs_url('thread?id=' . $tid) : '#';
    }
    return '#';
}

adminHead('举报管理 · RYE社区');
?>
<style>
.mt-admin-wrap{max-width:1000px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-tabs a{text-decoration:none;color:#3a4a3e;border:1px solid #cfe6c8;background:#f3f8f1;border-radius:18px;padding:5px 14px;font-size:13px;margin-right:6px}
.mt-tabs a.active{background:#2c7d3f;color:#fff;border-color:#2c7d3f}
.mt-item{border:1px solid #eef2ea;border-radius:10px;padding:12px;margin-bottom:10px}
.mt-item .meta{font-size:12px;color:#8a968c}
.mt-item .reason{margin:6px 0;color:#2c3a30}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.muted{color:#8a968c}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2>举报管理</h2>
        <div class="mt-tabs">
            <a class="<?php echo $status==='pending'?'active':''; ?>" href="?p=rye&page=reports&status=pending">待处理</a>
            <a class="<?php echo $status==='resolved'?'active':''; ?>" href="?p=rye&page=reports&status=resolved">已处理</a>
            <a class="<?php echo $status==='dismissed'?'active':''; ?>" href="?p=rye&page=reports&status=dismissed">已忽略</a>
            <a class="<?php echo $status==='all'?'active':''; ?>" href="?p=rye&page=reports&status=all">全部</a>
        </div>
        <?php if (empty($rows)): ?><p class="muted">暂无举报。</p><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <div class="mt-item">
                <div class="meta">举报人 <?php echo e($r['reporter'] ?? ''); ?> → 被举报 <?php echo e($r['reported'] ?? ''); ?> · 类型 <?php echo e($r['target_type']); ?> #<?php echo (int)$r['target_id']; ?> · <?php echo time_ago($r['created_at']); ?> · 状态 <?php echo e($r['status']); ?></div>
                <div class="reason">原因：<?php echo e($r['reason']); ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn-sm" href="<?php echo e(ryebbs_rep_target($r)); ?>" target="_blank">查看内容</a>
                    <?php if ($r['status'] === 'pending'): ?>
                        <?php echo ryebbs_rep_action('resolve', $r['id'], '标记已处理'); ?>
                        <?php echo ryebbs_rep_action('dismiss', $r['id'], '忽略', true); ?>
                    <?php endif; ?>
                    <?php echo ryebbs_rep_action('delete', $r['id'], '删除', true); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php adminFoot(); ?>
