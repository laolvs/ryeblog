<?php
/**
 * RYE社区（RyeBlog 插件）—— 私信
 * 路由：/bbs/messages              （收件箱/会话列表）
 *       /bbs/messages?with=N       （与用户 N 的会话 + 发送）
 */
require_once __DIR__ . '/bootstrap.php';
require_login();

$uid  = (int) currentUser()['id'];
$with = isset($_GET['with']) ? (int) $_GET['with'] : 0;

// 发送私信
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $with) {
    verify_csrf();
    $content = trim($_POST['content'] ?? '');
    if ($content === '') {
        set_flash('内容不能为空', 'error');
    } elseif ($with === $uid) {
        set_flash('不能给自己发私信', 'error');
    } else {
        dbQuery('INSERT INTO ' . prefix() . 'messages (from_user_id, to_user_id, content, is_read, created_at) VALUES (?, ?, ?, 0, NOW())',
            [$uid, $with, $content]);
        set_flash('已发送', 'success');
        header('Location: ' . bbs_url('messages?with=' . $with));
        exit;
    }
}

// 标记为已读（进入会话时）
if ($with) {
    dbQuery('UPDATE ' . prefix() . 'messages SET is_read=1 WHERE to_user_id=? AND from_user_id=?', [$uid, $with]);
}

// 会话列表：按对方聚合
$partners = db_all(
    'SELECT other_uid, MAX(id) AS last_id,
            SUM(CASE WHEN direction=\'in\' AND is_read=0 THEN 1 ELSE 0 END) AS unread
     FROM (
        SELECT from_user_id AS other_uid, id, is_read, \'in\' AS direction FROM ' . prefix() . 'messages WHERE to_user_id=?
        UNION ALL
        SELECT to_user_id AS other_uid, id, 1 AS is_read, \'out\' AS direction FROM ' . prefix() . 'messages WHERE from_user_id=?
     ) x GROUP BY other_uid ORDER BY last_id DESC',
    [$uid, $uid]
);
$convUsers = [];
foreach ($partners as &$p) {
    $p['last'] = db_row(
        'SELECT m.*, u.username, ue.nickname AS ext_nickname FROM ' . prefix() . 'messages m
         LEFT JOIN vd_users u ON u.id=m.from_user_id
         LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=m.from_user_id
         WHERE m.id=?', [$p['last_id']]
    );
    $p['partner'] = ryebbs_user($p['other_uid']);
}
unset($p);

// 会话详情
$conv = [];
$partnerUser = null;
if ($with) {
    $partnerUser = ryebbs_user($with);
    $conv = db_all(
        'SELECT m.*, u.username, ue.nickname AS ext_nickname FROM ' . prefix() . 'messages m
         LEFT JOIN vd_users u ON u.id=m.from_user_id
         LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=m.from_user_id
         WHERE (m.from_user_id=? AND m.to_user_id=?) OR (m.from_user_id=? AND m.to_user_id=?)
         ORDER BY m.id ASC',
        [$uid, $with, $with, $uid]
    );
}

$me = $uid;
$GLOBALS['bbs_page'] = 'messages';
$GLOBALS['__rye_seo'] = ['desc' => '私信', 'keywords' => '论坛,私信'];
publicHeader();
require_once __DIR__ . '/inc/nav.php';
$flash = get_flash();
?>
<style>
.msg-wrap{max-width:760px;margin:18px auto;padding:0 12px;display:flex;gap:14px}
.msg-list{width:240px;flex:none;border-right:1px solid #e3eadf;padding-right:10px}
.msg-list h1{font-size:18px;color:#1f3d24;margin:0 0 10px}
.conv-item{display:block;text-decoration:none;color:inherit;padding:8px 10px;border-radius:8px;margin-bottom:4px}
.conv-item:hover{background:#f3f8f1}
.conv-item.active{background:#e7f1e3}
.conv-name{font-size:14px;font-weight:600;color:#1f3d24;display:flex;justify-content:space-between}
.conv-last{font-size:12px;color:#7a8a7e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-badge{background:#e0533d;color:#fff;border-radius:9px;font-size:11px;padding:0 6px}
.msg-detail{flex:1;min-width:0}
.msg-detail h2{font-size:16px;color:#1f3d24;margin:0 0 10px}
.bubble{max-width:80%;padding:9px 12px;border-radius:12px;margin-bottom:8px;font-size:14px;line-height:1.5;word-break:break-word}
.bubble.in{background:#eef3ec;color:#2c3a30}
.bubble.out{background:#2c7d3f;color:#fff;margin-left:auto}
.bubble.out a{color:#dff5e3}
.msg-form{margin-top:12px;display:flex;gap:8px}
.msg-form textarea{flex:1;border:1px solid #cfd9c8;border-radius:8px;padding:9px;font:inherit;min-height:44px}
.msg-form button{border:0;background:#2c7d3f;color:#fff;border-radius:8px;padding:0 16px;cursor:pointer}
.msg-empty{color:#7a8a7e;padding:30px 0;text-align:center}
</style>
<div class="msg-wrap">
    <div class="msg-list">
        <h1>私信</h1>
        <?php if (empty($partners)): ?><div class="msg-empty">还没有私信。</div>
        <?php else: foreach ($partners as $p):
            $nm = $p['partner'] ? ryebbs_name($p['partner']) : '用户#' . $p['other_uid'];
            $lastContent = $p['last'] ? $p['last']['content'] : '';
            $preview = ($p['last'] && $p['last']['from_user_id'] == $uid ? '我: ' : '') . mb_strimwidth($lastContent, 0, 22, '…', 'UTF-8');
        ?>
            <a class="conv-item <?php echo $with == $p['other_uid'] ? 'active' : ''; ?>" href="<?php echo e(bbs_url('messages?with=' . $p['other_uid'])); ?>">
                <div class="conv-name"><span><?php echo e($nm); ?></span><?php if ($p['unread'] > 0): ?><span class="conv-badge"><?php echo $p['unread']; ?></span><?php endif; ?></div>
                <div class="conv-last"><?php echo e($preview); ?></div>
            </a>
        <?php endforeach; endif; ?>
    </div>
    <div class="msg-detail">
        <?php if ($with && $partnerUser): ?>
            <h2>与 <?php echo e(ryebbs_name($partnerUser)); ?> 的对话</h2>
            <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
            <?php foreach ($conv as $m): ?>
                <div class="bubble <?php echo $m['from_user_id'] == $uid ? 'out' : 'in'; ?>"><?php echo nl2br(e($m['content'])); ?></div>
            <?php endforeach; ?>
            <form class="msg-form" method="post">
                <?php echo csrf_field(); ?>
                <textarea name="content" placeholder="输入私信内容…"></textarea>
                <button type="submit">发送</button>
            </form>
        <?php elseif ($with): ?>
            <div class="msg-empty">用户不存在。</div>
        <?php else: ?>
            <div class="msg-empty">选择一个会话开始查看，或从他人主页发起私信。</div>
        <?php endif; ?>
    </div>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
