<?php
/**
 * RYE社区（RyeBlog 插件）—— 用户主页
 * 复用 RyeBlog 账号（vd_users），展示论坛专属字段（昵称/签名/金币/关注/主题）。
 * 路由：/bbs/user?id=N  →  plugin.php?__bbs=user
 */
require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    if (!isLoggedIn()) {
        header('Location: ' . baseUrl('user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
        exit;
    }
    $id = currentUser()['id'];
}

$user = $id ? ryebbs_user($id) : null;
if (!$user) {
    http_response_code(404);
    publicHeader();
    echo '<div class="empty">用户不存在或已注销。</div>';
    publicFooter(rye_sidebar_html());
    exit;
}

/* ---------- 表单处理（签到 / 关注 / 保存资料） ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    verify_csrf();
    $me = currentUser();
    $act = $_POST['action'] ?? '';
    if ($act === 'signin') {
        $res = ryebbs_signin($me['id']);
        set_flash($res['msg'], $res['ok'] ? 'success' : 'info');
        $user = ryebbs_user($id);
    } elseif ($act === 'follow' && $me['id'] != $id) {
        $nowFollowing = ryebbs_follow($me['id'], $id);
        set_flash($nowFollowing ? '已关注 TA' : '已取消关注', 'success');
        $user = ryebbs_user($id);
    } elseif ($act === 'save' && $me['id'] == $id) {
        ryebbs_save_ext($id, [
            'nickname'       => trim($_POST['nickname'] ?? ''),
            'signature'      => trim($_POST['signature'] ?? ''),
            'bio'            => trim($_POST['bio'] ?? ''),
            'gender'         => trim($_POST['gender'] ?? ''),
            'avatar'         => trim($_POST['avatar'] ?? ''),
            'notify_enabled' => (isset($_POST['notify_enabled']) && $_POST['notify_enabled'] === '1') ? '1' : '0',
        ]);
        set_flash('资料已保存', 'success');
        $user = ryebbs_user($id);
    }
    header('Location: ' . bbs_url('user?id=' . $id));
    exit;
}

$isSelf    = isLoggedIn() && currentUser()['id'] == $id;
$following = (!$isSelf && isLoggedIn()) ? ryebbs_is_following(currentUser()['id'], $id) : false;
$followers = ryebbs_follower_count($id);
$followingCount = ryebbs_following_count($id);

$threads = db_all(
    'SELECT t.*, f.name AS forum_name FROM ' . prefix() . 'threads t
     LEFT JOIN ' . prefix() . 'forums f ON f.id=t.forum_id
     WHERE t.user_id=? AND t.is_deleted=0 ORDER BY t.updated_at DESC LIMIT 20',
    [$id]
);

$GLOBALS['__rye_seo'] = ['desc' => ryebbs_name($user) . ' 的论坛主页', 'keywords' => '用户,主页,论坛'];
$GLOBALS['bbs_page']  = 'user';
publicHeader();
require_once __DIR__ . '/inc/nav.php';

$flash = get_flash();
if ($flash) {
    echo '<div class="flash flash-' . e($flash['type']) . '">' . e($flash['msg']) . '</div>';
}
?>
<style>
.profile-wrap{max-width:860px;margin:18px auto;display:grid;grid-template-columns:300px 1fr;gap:18px;align-items:start}
@media(max-width:720px){.profile-wrap{grid-template-columns:1fr}}
.profile-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:18px;text-align:center}
.profile-avatar{width:96px;height:96px;border-radius:50%;object-fit:cover;background:#eef3ec}
.profile-name{margin:10px 0 4px;font-size:20px;color:#1f3d24}
.profile-sign{color:#6b7a6f;font-size:13px;margin:0 0 10px}
.profile-meta{display:flex;flex-wrap:wrap;gap:6px 12px;justify-content:center;font-size:12px;color:#5d6b61;margin-bottom:12px}
.profile-meta span{background:#f3f7f0;border-radius:20px;padding:3px 10px}
.profile-actions{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-bottom:6px}
.profile-edit{margin-top:14px;text-align:left;display:flex;flex-direction:column;gap:8px}
.profile-edit label{font-size:13px;color:#3a4a3e;display:flex;flex-direction:column;gap:4px}
.profile-edit input,.profile-edit textarea,.profile-edit select{border:1px solid #cfd9c8;border-radius:8px;padding:7px 9px;font:inherit}
.profile-threads .thread-item{background:#fff;border:1px solid #e3eadf}
</style>

<div class="profile-wrap">
    <div class="profile-card">
        <img class="profile-avatar" src="<?php echo e(ryebbs_avatar_src($user, 96)); ?>" alt="头像">
        <h1 class="profile-name"><?php echo e(ryebbs_name($user)); ?></h1>
        <?php if (!empty($user['signature'])): ?>
            <p class="profile-sign"><?php echo e($user['signature']); ?></p>
        <?php endif; ?>
        <div class="profile-meta">
            <span>🪙 <?php echo $user['coins']; ?> 金币</span>
            <span>✍️ <?php echo $user['thread_count']; ?> 主题</span>
            <span>💬 <?php echo $user['reply_count']; ?> 回复</span>
            <span>👥 <?php echo $followers; ?> 粉丝 / <?php echo $followingCount; ?> 关注</span>
            <span>📅 加入于 <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span>
        </div>
        <div class="profile-actions">
            <?php if ($isSelf): ?>
                <form method="post" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button class="btn" name="action" value="signin">每日签到</button>
                </form>
                <a class="btn btn-primary" href="#edit">编辑资料</a>
            <?php elseif (isLoggedIn()): ?>
                <form method="post" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button class="btn <?php echo $following ? '' : 'btn-primary'; ?>" name="action" value="follow">
                        <?php echo $following ? '取消关注' : '关注 TA'; ?>
                    </button>
                </form>
                <a class="btn" href="<?php echo e(bbs_url('messages?with=' . $id)); ?>">发私信</a>
            <?php else: ?>
                <a class="btn" href="<?php echo e(baseUrl('user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']))); ?>">登录后关注</a>
            <?php endif; ?>
        </div>

        <?php if ($isSelf): ?>
        <form class="profile-edit" id="edit" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <label>昵称
                <input name="nickname" value="<?php echo e($user['nickname'] ?? ''); ?>" maxlength="32" placeholder="论坛昵称（留空则用站点用户名）">
            </label>
            <label>签名
                <input name="signature" value="<?php echo e($user['signature'] ?? ''); ?>" maxlength="255">
            </label>
            <label>简介
                <textarea name="bio" maxlength="255" rows="3"><?php echo e($user['bio'] ?? ''); ?></textarea>
            </label>
            <label>性别
                <select name="gender">
                    <option value="" <?php echo ($user['gender'] ?? '') === '' ? 'selected' : ''; ?>>保密</option>
                    <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>男</option>
                    <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>女</option>
                </select>
            </label>
            <label>头像 URL
                <input name="avatar" value="<?php echo e($user['avatar'] ?? ''); ?>" placeholder="留空则用站点默认头像">
            </label>
            <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                <input type="checkbox" name="notify_enabled" value="1" <?php echo ($user['notify_enabled'] ?? 1) == 1 ? 'checked' : ''; ?>>
                <span><?php echo __('接收新通知'); ?><span class="muted" style="font-weight:400">（有人回复、点赞你的主题时通知你；新用户默认开启）</span></span>
            </label>
            <button class="btn btn-primary" type="submit">保存资料</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="profile-threads">
        <h3 style="margin:4px 0 12px;color:#1f3d24"><?php echo e(ryebbs_name($user)); ?> 的主题</h3>
        <div class="panel"><div class="panel-body">
            <?php if (empty($threads)): ?>
                <div class="empty">还没有发表过主题。</div>
            <?php else: foreach ($threads as $t): ?>
                <div class="thread-item">
                    <div class="thread-avatar"><?php echo e(mb_substr(ryebbs_name($user), 0, 1, 'UTF-8')); ?></div>
                    <div class="thread-main">
                        <a class="thread-title" href="<?php echo e(bbs_url('thread?id=' . $t['id'])); ?>">
                            <?php if ($t['is_top']): ?><span class="tag tag-top">置顶</span><?php endif; ?>
                            <?php if ($t['is_good']): ?><span class="tag tag-good">精华</span><?php endif; ?>
                            <?php echo e($t['title']); ?>
                        </a>
                        <div class="thread-meta">
                            <?php if ($t['forum_name']): ?><span>📌 <?php echo e($t['forum_name']); ?></span><?php endif; ?>
                            <span>💬 <?php echo $t['replies']; ?></span>
                            <span>👁 <?php echo $t['views']; ?></span>
                            <span><?php echo time_ago($t['updated_at']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div></div>
    </div>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
