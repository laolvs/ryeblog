<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台用户管理
 * 路由：admin/plugin.php?p=rye&page=users
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($act === 'coins') {
        $amount = (int) ($_POST['amount'] ?? 0);
        $type   = $_POST['type'] ?? 'add';
        $amt = $type === 'deduct' ? -abs($amount) : abs($amount);
        dbQuery("UPDATE {$P}user_ext SET coins = GREATEST(0, coins + ?) WHERE user_id=?", [$amt, $uid]);
        dbQuery("INSERT INTO {$P}coin_logs (user_id, amount, type, description, created_at) VALUES (?, ?, 'admin', '管理员调整', NOW())", [$uid, $amt]);
        set_flash('金币已调整', 'success');
    } elseif ($act === 'mute') {
        $days = (int) ($_POST['days'] ?? 0);
        $until = $days > 0 ? date('Y-m-d H:i:s', strtotime("+$days days")) : null;
        dbQuery("UPDATE {$P}user_ext SET mute_until=? WHERE user_id=?", [$until, $uid]);
        set_flash($days > 0 ? "已禁言 {$days} 天" : '已解除禁言', 'success');
    } elseif ($act === 'moderator') {
        $on = (int) ($_POST['on'] ?? 0);
        dbQuery("UPDATE {$P}user_ext SET is_moderator=? WHERE user_id=?", [$on, $uid]);
        set_flash($on ? '已设为版主' : '已取消版主', 'success');
    }
    header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=users'));
    exit;
}

$kw = trim($_GET['kw'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perpage = 30;
$where = [];
$params = [];
if ($kw) { $where[] = "(u.username LIKE ? OR ue.nickname LIKE ? OR u.email LIKE ?)"; $params[] = "%$kw%"; $params[] = "%$kw%"; $params[] = "%$kw%"; }
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$total = (int) db_val("SELECT COUNT(*) FROM {$P}user_ext ue LEFT JOIN vd_users u ON u.id=ue.user_id $wsql", $params);
$p = page_nav($total, $page, $perpage);
$rows = db_all(
    "SELECT ue.*, u.username, u.email FROM {$P}user_ext ue LEFT JOIN vd_users u ON u.id=ue.user_id
     $wsql ORDER BY ue.coins DESC LIMIT ?, ?",
    array_merge($params, [$p['offset'], $p['perpage']])
);
$flash = get_flash();

function ryebbs_user_action($act, $uid, $label, $extra = '', $danger = false)
{
    return '<form method="post" style="display:inline" onsubmit="return confirm(\'确定' . $label . '？\');">' . csrf_field() . '<input type="hidden" name="act" value="' . $act . '"><input type="hidden" name="uid" value="' . $uid . '">' . $extra . '<button class="btn-sm ' . ($danger ? 'danger' : '') . '" type="submit">' . $label . '</button></form>';
}

adminHead('用户管理 · RYE社区');
require __DIR__ . '/inc/admin_nav.php';
?>
<style>
.mt-admin-wrap{max-width:1100px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-filter{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.mt-filter input{border:1px solid #cfd9c8;border-radius:8px;padding:7px 9px;font:inherit}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.mt-tag{display:inline-block;background:#eaf3e6;color:#2c5234;border-radius:6px;padding:1px 7px;font-size:12px;margin:1px 2px}
.mt-tag.danger{background:#fdf3f3;color:#a33}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.muted{color:#8a968c}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2>论坛用户</h2>
        <form class="mt-filter" method="get">
            <input type="hidden" name="p" value="rye"><input type="hidden" name="page" value="users">
            <input type="text" name="kw" value="<?php echo e($kw); ?>" placeholder="用户名 / 昵称 / 邮箱">
            <button class="btn btn-primary" type="submit">搜索</button>
        </form>
        <table class="mt-table">
            <thead><tr><th>用户</th><th>金币</th><th>主题/回复</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="5" class="muted">无用户。</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo e($r['username'] ?? ''); ?><?php if ($r['nickname']): ?> <span class="muted">(<?php echo e($r['nickname']); ?>)</span><?php endif; ?></td>
                    <td><?php echo (int)$r['coins']; ?></td>
                    <td><?php echo (int)$r['thread_count']; ?> / <?php echo (int)$r['reply_count']; ?></td>
                    <td>
                        <?php if ($r['is_moderator']): ?><span class="mt-tag">版主</span><?php endif; ?>
                        <?php if (!empty($r['mute_until']) && strtotime($r['mute_until']) > time()): ?><span class="mt-tag danger">禁言中</span><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <a class="btn-sm" href="<?php echo e(bbs_url('user?id=' . $r['user_id'])); ?>" target="_blank">主页</a>
                        <?php echo ryebbs_user_action('coins', $r['user_id'], '加币', '<input type="hidden" name="type" value="add"><input type="hidden" name="amount" value="10">'); ?>
                        <?php echo ryebbs_user_action('coins', $r['user_id'], '扣币', '<input type="hidden" name="type" value="deduct"><input type="hidden" name="amount" value="10">', true); ?>
                        <?php echo ryebbs_user_action('mute', $r['user_id'], '禁言7天', '<input type="hidden" name="days" value="7">', true); ?>
                        <?php echo ryebbs_user_action('moderator', $r['user_id'], $r['is_moderator']?'取消版主':'设版主', '<input type="hidden" name="on" value="' . ($r['is_moderator']?0:1) . '">'); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo pagination_html($total, $page, $perpage, baseUrl('admin/plugin.php?p=rye&page=users&kw=' . urlencode($kw))); ?>
    </div>
</div>
<?php adminFoot(); ?>
