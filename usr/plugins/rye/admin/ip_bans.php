<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台 IP 封禁
 * 路由：admin/plugin.php?p=rye&page=ip_bans
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $ip = trim($_POST['ip'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $days = (int) ($_POST['days'] ?? 0);
        $expires = $days > 0 ? date('Y-m-d H:i:s', strtotime("+$days days")) : null;
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            set_flash('请输入合法的 IP 地址', 'error');
        } else {
            dbQuery("INSERT INTO {$P}ip_bans (ip, reason, expires_at, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE reason=?, expires_at=?",
                [$ip, $reason, $expires, $reason, $expires]);
            set_flash('已封禁 ' . $ip, 'success');
        }
    } elseif ($act === 'delete') {
        dbQuery("DELETE FROM {$P}ip_bans WHERE id=?", [(int) ($_POST['id'] ?? 0)]);
        set_flash('已解除', 'success');
    }
    header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=ip_bans'));
    exit;
}

$rows = db_all("SELECT * FROM {$P}ip_bans ORDER BY created_at DESC LIMIT 200");
$now = time();
$flash = get_flash();

function ryebbs_ban_state($r, $now)
{
    if (!empty($r['expires_at']) && strtotime($r['expires_at']) < $now) {
        return '<span class="mt-tag" style="background:#eee;color:#888">已过期</span>';
    }
    return '<span class="mt-tag" style="background:#fdf3f3;color:#a33">封禁中</span>';
}

adminHead('IP 封禁 · RYE社区');
?>
<style>
.mt-admin-wrap{max-width:900px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-form label{display:block;margin:8px 0 3px;font-size:13px;color:#3a4a3e}
.mt-form input{border:1px solid #cfd9c8;border-radius:8px;padding:8px;font:inherit;width:100%}
.mt-row{display:flex;gap:10px}
.mt-row>div{flex:1}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.mt-tag{display:inline-block;border-radius:6px;padding:1px 7px;font-size:12px}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.muted{color:#8a968c}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2>新增封禁</h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="add">
            <div class="mt-row">
                <div><label>IP 地址<input type="text" name="ip" placeholder="如 1.2.3.4" required></label></div>
                <div><label>封禁时长（天，0=永久）<input type="number" name="days" value="0" min="0"></label></div>
            </div>
            <label>原因<input type="text" name="reason" placeholder="违规说明"></label>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">封禁</button></div>
        </form>
    </div>
    <div class="mt-card">
        <h2>封禁列表</h2>
        <table class="mt-table">
            <thead><tr><th>IP</th><th>原因</th><th>过期</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="5" class="muted">暂无封禁记录。</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><code><?php echo e($r['ip']); ?></code></td>
                    <td><?php echo e($r['reason']); ?></td>
                    <td class="muted"><?php echo $r['expires_at'] ? e(substr($r['expires_at'], 0, 16)) : '永久'; ?></td>
                    <td><?php echo ryebbs_ban_state($r, $now); ?></td>
                    <td><form method="post" style="display:inline" onsubmit="return confirm('解除该封禁？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button class="btn-sm danger" type="submit">解除</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminFoot(); ?>
