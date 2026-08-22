<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台勋章管理
 * 路由：admin/plugin.php?p=rye&page=medals
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $minHours = (int) ($_POST['min_online_hours'] ?? 0);
        $order = (int) ($_POST['sort_order'] ?? 0);
        if ($name === '') {
            set_flash('勋章名称不能为空', 'error');
        } elseif ($id > 0) {
            dbQuery("UPDATE {$P}medals SET name=?, image=?, min_online_hours=?, sort_order=? WHERE id=?", [$name, $image, $minHours, $order, $id]);
            set_flash('已更新', 'success');
        } else {
            dbQuery("INSERT INTO {$P}medals (name, image, min_online_hours, sort_order) VALUES (?, ?, ?, ?)", [$name, $image, $minHours, $order]);
            set_flash('已添加', 'success');
        }
    } elseif ($act === 'delete') {
        dbQuery("DELETE FROM {$P}medals WHERE id=?", [(int) ($_POST['id'] ?? 0)]);
        set_flash('已删除', 'success');
    } elseif ($act === 'grant') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $mid = (int) ($_POST['medal_id'] ?? 0);
        if ($uid && $mid) {
            dbQuery("INSERT IGNORE INTO {$P}user_medals (user_id, medal_id, awarded_at) VALUES (?, ?, NOW())", [$uid, $mid]);
            set_flash('已授予勋章', 'success');
        } else {
            set_flash('请填写用户 ID 与勋章', 'error');
        }
    }
    header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=medals'));
    exit;
}

$edit = isset($_GET['edit']) ? db_row("SELECT * FROM {$P}medals WHERE id=?", [(int) $_GET['edit']]) : null;
$medals = db_all("SELECT m.*, (SELECT COUNT(*) FROM {$P}user_medals um WHERE um.medal_id=m.id) AS holders FROM {$P}medals ORDER BY sort_order, id");
$flash = get_flash();

adminHead('勋章管理 · RYE社区');
?>
<style>
.mt-admin-wrap{max-width:900px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-form label{display:block;margin:8px 0 3px;font-size:13px;color:#3a4a3e}
.mt-form input{border:1px solid #cfd9c8;border-radius:8px;padding:8px;font:inherit;width:100%}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.muted{color:#8a968c}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2><?php echo $edit ? '编辑勋章' : '添加勋章'; ?></h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="save">
            <input type="hidden" name="id" value="<?php echo $edit ? $edit['id'] : 0; ?>">
            <label>勋章名称<input type="text" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" required></label>
            <label>图标 URL（可选）<input type="text" name="image" value="<?php echo e($edit['image'] ?? ''); ?>"></label>
            <label>授予所需在线时长（小时，0 表示手动授予）<input type="number" name="min_online_hours" value="<?php echo (int)($edit['min_online_hours'] ?? 0); ?>"></label>
            <label>排序<input type="number" name="sort_order" value="<?php echo (int)($edit['sort_order'] ?? 0); ?>"></label>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存</button> <?php if ($edit): ?><a class="btn-sm" href="?p=rye&page=medals">取消</a><?php endif; ?></div>
        </form>
    </div>
    <div class="mt-card">
        <h2>授予勋章</h2>
        <form class="mt-form" method="post" style="max-width:420px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="grant">
            <label>用户 ID<input type="number" name="user_id" required></label>
            <label>勋章
                <select name="medal_id">
                    <?php foreach ($medals as $m): ?><option value="<?php echo $m['id']; ?>"><?php echo e($m['name']); ?></option><?php endforeach; ?>
                </select>
            </label>
            <div style="margin-top:10px"><button class="btn btn-primary" type="submit">授予</button></div>
        </form>
    </div>
    <div class="mt-card">
        <h2>勋章列表（<?php echo count($medals); ?>）</h2>
        <table class="mt-table">
            <thead><tr><th>名称</th><th>图标</th><th>在线时长</th><th>获得者</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($medals)): ?><tr><td colspan="5" class="muted">暂无勋章。</td></tr><?php endif; ?>
            <?php foreach ($medals as $m): ?>
                <tr>
                    <td><?php echo e($m['name']); ?></td>
                    <td><?php echo $m['image'] ? '<img src="'.e($m['image']).'" style="height:24px">' : '<span class="muted">—</span>'; ?></td>
                    <td><?php echo (int)$m['min_online_hours']; ?> h</td>
                    <td><?php echo (int)$m['holders']; ?></td>
                    <td>
                        <a class="btn-sm" href="?p=rye&page=medals&edit=<?php echo $m['id']; ?>">编辑</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('删除该勋章？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?php echo $m['id']; ?>"><button class="btn-sm danger" type="submit">删除</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminFoot(); ?>
