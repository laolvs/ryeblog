<?php
/**
 * RyeBlog 用户中心 —— 修改资料
 */
require_once __DIR__ . '/header.php';
requireUser();

$user = currentUser();
$ok = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = '表单已过期，请刷新重试。';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $homepage = trim($_POST['homepage'] ?? '');
            $bio      = trim($_POST['bio'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $err = '邮箱格式不正确。';
            } else {
                $exists = dbOne('SELECT id FROM vd_users WHERE email=? AND id!=?', [$email, $user['id']]);
                if ($exists) {
                    $err = '该邮箱已被其他用户使用。';
                } else {
                    if ($homepage !== '' && !filter_var($homepage, FILTER_VALIDATE_URL)) {
                        $homepage = 'https://' . $homepage;
                    }
                    dbQuery('UPDATE vd_users SET email=?, phone=?, homepage=?, bio=? WHERE id=?',
                        [$email, $phone, $homepage, $bio, $user['id']]);
                    $ok = '资料已更新。';
                    $user = currentUser();
                }
            }
        } elseif ($action === 'password') {
            $old = $_POST['old_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!password_verify($old, $user['password'])) {
                $err = '原密码不正确。';
            } elseif (strlen($new) < 6) {
                $err = '新密码至少 6 位。';
            } elseif ($new !== $confirm) {
                $err = '两次输入的新密码不一致。';
            } else {
                dbQuery('UPDATE vd_users SET password=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                $ok = '密码已修改。';
                $user = currentUser();
            }
        } elseif ($action === 'avatar') {
            $source = $_POST['avatar_source'] ?? 'gravatar';
            $url    = trim($_POST['avatar_url'] ?? '');
            if (!in_array($source, ['gravatar', 'local', 'upload'], true)) $source = 'gravatar';
            if ($source === 'upload' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $url = '';
            }
            dbQuery('UPDATE vd_users SET avatar_source=?, avatar_url=? WHERE id=?', [$source, $url, $user['id']]);
            $ok = '头像设置已更新。';
            $user = currentUser();
        }
    }
}

userHeader('修改资料', 'profile.php');
?>
<h1>修改资料</h1>

<?php if ($ok): ?><div class="uc-notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<?php if ($err): ?><div class="uc-notice-err"><?php echo esc($err); ?></div><?php endif; ?>

<div class="uc-panel">
    <h2>基本信息</h2>
    <form method="post" class="uc-form">
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="profile">
        <div class="form-row">
            <div>
                <label>用户名（不可修改）</label>
                <input type="text" value="<?php echo esc($user['username']); ?>" disabled>
            </div>
            <div>
                <label>邮箱 *</label>
                <input type="email" name="email" value="<?php echo esc($user['email']); ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div>
                <label>手机号</label>
                <input type="tel" name="phone" value="<?php echo esc($user['phone'] ?? ''); ?>" placeholder="可选">
            </div>
            <div>
                <label>个人主页</label>
                <input type="url" name="homepage" value="<?php echo esc($user['homepage'] ?? ''); ?>" placeholder="https://example.com">
            </div>
        </div>
        <label>个人简介</label>
        <textarea name="bio" rows="3" placeholder="一句话介绍自己"><?php echo esc($user['bio'] ?? ''); ?></textarea>
        <div class="actions"><button class="btn" type="submit">保存资料</button></div>
    </form>
</div>

<div class="uc-panel">
    <h2>头像设置</h2>
    <form method="post" class="uc-form">
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="avatar">
        <div class="uc-avatar-picker">
            <img src="<?php echo esc(userAvatar($user, 80)); ?>" alt="当前头像">
            <div class="uc-avatar-actions">
                <strong>当前头像</strong>
                <span class="hint">Gravatar 自动从 wordpress.com 获取</span>
            </div>
        </div>
        <label>头像来源</label>
        <select name="avatar_source">
            <option value="gravatar" <?php echo ($user['avatar_source'] ?? 'gravatar')==='gravatar'?'selected':''; ?>>Gravatar（基于邮箱自动获取）</option>
            <option value="local" <?php echo ($user['avatar_source'] ?? '')==='local'?'selected':''; ?>>本地首字母头像</option>
            <option value="upload" <?php echo ($user['avatar_source'] ?? '')==='upload'?'selected':''; ?>>自定义头像 URL</option>
        </select>
        <label>自定义头像 URL（选择「自定义」时填写）</label>
        <input type="url" name="avatar_url" value="<?php echo esc($user['avatar_url'] ?? ''); ?>" placeholder="https://example.com/avatar.jpg">
        <div class="actions"><button class="btn" type="submit">更新头像</button></div>
    </form>
</div>

<div class="uc-panel">
    <h2>修改密码</h2>
    <form method="post" class="uc-form">
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="password">
        <label>原密码</label>
        <input type="password" name="old_password" required>
        <div class="form-row">
            <div>
                <label>新密码</label>
                <input type="password" name="new_password" required placeholder="至少 6 位">
            </div>
            <div>
                <label>确认新密码</label>
                <input type="password" name="confirm_password" required>
            </div>
        </div>
        <div class="actions"><button class="btn" type="submit">修改密码</button></div>
    </form>
</div>
<?php userFooter();
