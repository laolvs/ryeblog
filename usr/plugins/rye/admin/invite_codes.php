<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台邀请码管理
 * 路由：admin/plugin.php?p=rye&page=invite_codes
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'generate') {
        $n = max(1, min(100, (int) ($_POST['count'] ?? 1)));
        $type = $_POST['type'] ?? 'free';
        for ($i = 0; $i < $n; $i++) {
            $code = strtoupper(substr(md5(uniqid(ryebbs_rand(), true)), 0, 12));
            dbQuery("INSERT IGNORE INTO {$P}invite_codes (code, type, created_by, status, created_at) VALUES (?, ?, ?, 'active', NOW())",
                [$code, $type, currentUser()['id']]);
        }
        set_flash("已生成 {$n} 个邀请码", 'success');
    } elseif ($act === 'void') {
        dbQuery("UPDATE {$P}invite_codes SET status='void' WHERE code=?", [$_POST['code'] ?? '']);
        set_flash('已作废', 'success');
    } elseif ($act === 'delete') {
        dbQuery("DELETE FROM {$P}invite_codes WHERE code=?", [$_POST['code'] ?? '']);
        set_flash('已删除', 'success');
    }
    header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=invite_codes'));
    exit;
}

$rows = db_all("SELECT * FROM {$P}invite_codes ORDER BY id DESC LIMIT 200");
$flash = get_flash();

function ryebbs_ic_status($r)
{
    if ($r['status'] === 'used') return '<span class="mt-tag" style="background:#eaf3e6;color:#2c5234">已使用</span>';
    if ($r['status'] === 'void') return '<span class="mt-tag" style="background:#fdf3f3;color:#a33">已作废</span>';
    return '<span class="mt-tag">可用</span>';
}

adminHead('邀请码管理 · RYE社区');
require __DIR__ . '/inc/admin_nav.php';
?>
<style>
.mt-admin-wrap{max-width:900px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-form label{display:block;margin:8px 0 3px;font-size:13px;color:#3a4a3e}
.mt-form input,.mt-form select{border:1px solid #cfd9c8;border-radius:8px;padding:8px;font:inherit}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.mt-tag{display:inline-block;background:#eaf3e6;color:#2c5234;border-radius:6px;padding:1px 7px;font-size:12px}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.muted{color:#8a968c}
code{background:#f3f7f0;padding:2px 6px;border-radius:4px;font-size:13px}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2>生成邀请码</h2>
        <form class="mt-form" method="post" style="max-width:420px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="generate">
            <label>数量<input type="number" name="count" value="10" min="1" max="100"></label>
            <label>类型
                <select name="type"><option value="free">免费</option><option value="paid">付费</option></select>
            </label>
            <div style="margin-top:10px"><button class="btn btn-primary" type="submit">生成</button></div>
        </form>
    </div>
    <div class="mt-card">
        <h2>邀请码列表</h2>
        <table class="mt-table">
            <thead><tr><th>邀请码</th><th>类型</th><th>状态</th><th>使用者</th><th>创建时间</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="6" class="muted">暂无邀请码。</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><code><?php echo e($r['code']); ?></code></td>
                    <td><?php echo e($r['type']); ?></td>
                    <td><?php echo ryebbs_ic_status($r); ?></td>
                    <td><?php echo $r['used_by'] ? (int)$r['used_by'] : '—'; ?></td>
                    <td class="muted"><?php echo e(substr($r['created_at'], 0, 10)); ?></td>
                    <td>
                        <?php if ($r['status'] === 'active'): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('作废该邀请码？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="void"><input type="hidden" name="code" value="<?php echo e($r['code']); ?>"><button class="btn-sm" type="submit">作废</button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('删除该邀请码？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="code" value="<?php echo e($r['code']); ?>"><button class="btn-sm danger" type="submit">删除</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminFoot(); ?>
