<?php
/**
 * RyeBlog 后台 —— 用户管理
 * 功能：列表、搜索、修改角色（admin/user）、启用/禁用、删除、重置密码
 */
require_once __DIR__ . '/admin.php';

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } else {
        $uid = (int)($_POST['uid'] ?? 0);
        $act = $_POST['act'] ?? '';
        $me  = currentAdmin();
        if (!$uid) {
            $err = __('参数错误。');
        } elseif ($act === 'role') {
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            if ($uid === (int)$me['id']) {
                $err = __('不能修改自己的角色。');
            } else {
                dbQuery('UPDATE vd_users SET role=? WHERE id=?', [$role, $uid]);
                $msg = __('角色已更新。');
            }
        } elseif ($act === 'status') {
            $status = !empty($_POST['status']) ? 1 : 0;
            if ($uid === (int)$me['id']) {
                $err = __('不能禁用自己。');
            } else {
                dbQuery('UPDATE vd_users SET status=? WHERE id=?', [$status, $uid]);
                $msg = $status ? __('用户已启用。') : __('用户已禁用。');
            }
        } elseif ($act === 'delete') {
            if ($uid === (int)$me['id']) {
                $err = __('不能删除自己。');
            } else {
                // 连带清理：论坛扩展资料 / 评论 / 帖子归属（帖子保留，user_id 置空或保留？此处仅删账号）
                dbQuery('DELETE FROM vd_users WHERE id=?', [$uid]);
                dbQuery('DELETE FROM ryebbs_user_ext WHERE user_id=?', [$uid]);
                $msg = __('用户已删除。');
            }
        } elseif ($act === 'password') {
            $pwd = (string)($_POST['password'] ?? '');
            if (mb_strlen($pwd, 'UTF-8') < 6) {
                $err = __('新密码至少 6 个字符。');
            } else {
                $hash = password_hash($pwd, PASSWORD_BCRYPT);
                dbQuery('UPDATE vd_users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?', [$hash, $uid]);
                $msg = __('密码已重置。');
            }
        }
    }
}

$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

$where  = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (username LIKE ? OR email LIKE ? OR display_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$total = (int)dbOne("SELECT COUNT(*) c FROM vd_users $where", $params)['c'];
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$users = dbAll("SELECT * FROM vd_users $where ORDER BY id ASC LIMIT " . $offset . ', ' . $perPage, $params);

$listUrl = function ($i) use ($q) {
    return baseUrl('admin/users.php?p=' . $i . ($q !== '' ? '&q=' . urlencode($q) : ''));
};

adminHead(__('用户管理'), 'users.php');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h1><?php echo __('用户管理'); ?></h1>
</div>

<div class="panel" style="margin-top:18px">
    <?php if ($msg): ?><div class="notice notice-ok">✓ <?php echo esc($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="notice notice-err">✗ <?php echo esc($err); ?></div><?php endif; ?>
    <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="search" name="q" value="<?php echo esc($q); ?>" placeholder="<?php echo __('按用户名/邮箱/昵称搜索…'); ?>" style="flex:1;min-width:220px;padding:8px 12px;border:1px solid var(--line,#e2e8f0);border-radius:8px;font-size:13.5px">
        <button class="btn btn-ghost" type="submit">🔍 <?php echo __('搜索'); ?></button>
        <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?php echo baseUrl('admin/users.php'); ?>"><?php echo __('清除'); ?></a><?php endif; ?>
    </form>
    <p class="muted" style="font-size:.85rem;margin:0 0 10px"><?php echo __('共'); ?> <?php echo $total; ?> <?php echo __('个用户'); ?></p>
    <table class="data">
        <tr>
            <th><?php echo __('用户名'); ?></th><th><?php echo __('昵称'); ?></th><th><?php echo __('邮箱'); ?></th>
            <th><?php echo __('角色'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('注册时间'); ?></th><th><?php echo __('操作'); ?></th>
        </tr>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><strong><?php echo esc($u['username']); ?></strong></td>
            <td><?php echo esc($u['display_name'] ?: '—'); ?></td>
            <td><?php echo esc($u['email'] ?: '—'); ?></td>
            <td>
                <form method="post" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?php echo esc(csrfToken()); ?>">
                    <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                    <input type="hidden" name="act" value="role">
                    <select name="role" onchange="this.form.submit()" <?php echo $u['id'] == currentAdmin()['id'] ? 'disabled title="不能修改自己"' : ''; ?>>
                        <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                        <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>user</option>
                    </select>
                </form>
            </td>
            <td>
                <form method="post" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?php echo esc(csrfToken()); ?>">
                    <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                    <input type="hidden" name="act" value="status">
                    <input type="hidden" name="status" value="<?php echo $u['status'] ? '0' : '1'; ?>">
                    <?php if ($u['id'] == currentAdmin()['id']): ?>
                        <span class="badge badge-ok"><?php echo __('正常'); ?></span>
                    <?php else: ?>
                        <button class="btn btn-ghost btn-sm" type="submit"><?php echo $u['status'] ? '<span class="badge badge-ok">' . __('正常') . '</span>' : '<span class="badge badge-err">' . __('已禁用') . '</span>'; ?></button>
                    <?php endif; ?>
                </form>
            </td>
            <td class="muted"><?php echo formatDate($u['created_at']); ?></td>
            <td style="white-space:nowrap">
                <button class="btn btn-ghost btn-sm" type="button" onclick="document.getElementById('pwd-<?php echo (int)$u['id']; ?>').style.display='block'">🔑 <?php echo __('重置密码'); ?></button>
                <?php if ($u['id'] != currentAdmin()['id']): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('<?php echo __('确定删除该用户？其论坛资料将一并删除。'); ?>')">
                    <input type="hidden" name="_csrf" value="<?php echo esc(csrfToken()); ?>">
                    <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                    <input type="hidden" name="act" value="delete">
                    <button class="btn btn-danger btn-sm" type="submit"><?php echo __('删除'); ?></button>
                </form>
                <?php endif; ?>
                <div id="pwd-<?php echo (int)$u['id']; ?>" style="display:none;margin-top:6px">
                    <form method="post" style="display:flex;gap:6px;align-items:center">
                        <input type="hidden" name="_csrf" value="<?php echo esc(csrfToken()); ?>">
                        <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                        <input type="hidden" name="act" value="password">
                        <input type="password" name="password" placeholder="<?php echo __('新密码（至少 6 位）'); ?>" style="padding:6px 10px;border:1px solid var(--line,#e2e8f0);border-radius:6px;font-size:13px" required>
                        <button class="btn btn-sm" type="submit"><?php echo __('保存'); ?></button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?><tr><td colspan="7" class="muted"><?php echo __('暂无用户。'); ?></td></tr><?php endif; ?>
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
