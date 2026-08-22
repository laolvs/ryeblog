<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台敏感词管理
 * 路由：admin/plugin.php?p=rye&page=sensitive_words
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id  = (int) ($_POST['id'] ?? 0);
        $word = trim($_POST['word'] ?? '');
        $action = $_POST['action'] ?? 'replace';
        $replacement = trim($_POST['replacement'] ?? '**');
        if ($word === '') {
            set_flash('敏感词不能为空', 'error');
        } elseif ($id > 0) {
            dbQuery("UPDATE {$P}sensitive_words SET word=?, action=?, replacement=? WHERE id=?", [$word, $action, $replacement, $id]);
            set_flash('已更新', 'success');
        } else {
            dbQuery("INSERT INTO {$P}sensitive_words (word, action, replacement, created_at) VALUES (?, ?, ?, NOW())", [$word, $action, $replacement]);
            set_flash('已添加', 'success');
        }
    } elseif ($act === 'delete') {
        dbQuery("DELETE FROM {$P}sensitive_words WHERE id=?", [(int) ($_POST['id'] ?? 0)]);
        set_flash('已删除', 'success');
    }
    header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=sensitive_words'));
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_row("SELECT * FROM {$P}sensitive_words WHERE id=?", [(int) $_GET['edit']]);
}
$words = db_all("SELECT * FROM {$P}sensitive_words ORDER BY id DESC");
$flash = get_flash();

adminHead('敏感词管理 · RYE社区');
require __DIR__ . '/inc/admin_nav.php';
?>
<style>
.mt-admin-wrap{max-width:900px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-form label{display:block;margin:8px 0 3px;font-size:13px;color:#3a4a3e}
.mt-form input,.mt-form select{width:100%;border:1px solid #cfd9c8;border-radius:8px;padding:8px;font:inherit}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.mt-tag{display:inline-block;background:#eaf3e6;color:#2c5234;border-radius:6px;padding:1px 7px;font-size:12px}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.muted{color:#8a968c}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2><?php echo $edit ? '编辑敏感词' : '添加敏感词'; ?></h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="save">
            <input type="hidden" name="id" value="<?php echo $edit ? $edit['id'] : 0; ?>">
            <label>敏感词<input type="text" name="word" value="<?php echo e($edit['word'] ?? ''); ?>" required></label>
            <label>处理方式
                <select name="action">
                    <option value="replace" <?php echo ($edit['action'] ?? 'replace')==='replace'?'selected':''; ?>>替换为</option>
                    <option value="block" <?php echo ($edit['action'] ?? '')==='block'?'selected':''; ?>>禁止发布</option>
                </select>
            </label>
            <label>替换内容（处理方式为替换时生效）<input type="text" name="replacement" value="<?php echo e($edit['replacement'] ?? '**'); ?>"></label>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存</button> <?php if ($edit): ?><a class="btn-sm" href="?p=rye&page=sensitive_words">取消</a><?php endif; ?></div>
        </form>
    </div>
    <div class="mt-card">
        <h2>敏感词列表（<?php echo count($words); ?>）</h2>
        <table class="mt-table">
            <thead><tr><th>词语</th><th>方式</th><th>替换</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($words)): ?><tr><td colspan="4" class="muted">暂无敏感词。</td></tr><?php endif; ?>
            <?php foreach ($words as $w): ?>
                <tr>
                    <td><?php echo e($w['word']); ?></td>
                    <td><?php echo $w['action']==='block'?'<span class="mt-tag" style="background:#fdf3f3;color:#a33">禁止</span>':'<span class="mt-tag">替换</span>'; ?></td>
                    <td><?php echo e($w['replacement']); ?></td>
                    <td>
                        <a class="btn-sm" href="?p=rye&page=sensitive_words&edit=<?php echo $w['id']; ?>">编辑</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('删除该敏感词？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?php echo $w['id']; ?>"><button class="btn-sm danger" type="submit">删除</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminFoot(); ?>
