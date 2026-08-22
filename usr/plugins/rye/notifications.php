<?php
/**
 * RYE社区（RyeBlog 插件）—— 通知
 * 路由：/bbs/notifications
 */
require_once __DIR__ . '/bootstrap.php';
require_login();

$uid = (int) currentUser()['id'];

// 单条已读并跳转（GET 副作用，带 CSRF token）
if (isset($_GET['act'], $_GET['id']) && $_GET['act'] === 'read') {
    if (!isset($_GET['_csrf']) || !hash_equals(csrfToken(), (string) $_GET['_csrf'])) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试。');
    }
    $nid = (int) $_GET['id'];
    $n = db_row('SELECT * FROM ' . prefix() . 'notifications WHERE id=? AND user_id=?', [$nid, $uid]);
    if ($n) {
        dbQuery('UPDATE ' . prefix() . 'notifications SET is_read=1 WHERE id=?', [$nid]);
        $target = $n['thread_id'] ? bbs_url('thread?id=' . $n['thread_id']) : bbs_url('forum');
        header('Location: ' . $target);
        exit;
    }
}
// 全部标已读
if (isset($_GET['act']) && $_GET['act'] === 'read_all') {
    if (!isset($_GET['_csrf']) || !hash_equals(csrfToken(), (string) $_GET['_csrf'])) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试。');
    }
    dbQuery('UPDATE ' . prefix() . 'notifications SET is_read=1 WHERE user_id=?', [$uid]);
    set_flash('已全部标为已读', 'success');
    header('Location: ' . bbs_url('notifications'));
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perpage = 20;
$total = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'notifications WHERE user_id=?', [$uid]);
$p = page_nav($total, $page, $perpage);
$list = db_all(
    'SELECT n.*, ru.username AS related_name, re.nickname AS related_nick
     FROM ' . prefix() . 'notifications n
     LEFT JOIN vd_users ru ON ru.id=n.related_user_id
     LEFT JOIN ' . prefix() . 'user_ext re ON re.user_id=n.related_user_id
     WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT ?, ?',
    [$uid, $p['offset'], $p['perpage']]
);

$GLOBALS['bbs_page'] = 'notifications';
$GLOBALS['__rye_seo'] = ['desc' => '我的通知', 'keywords' => '论坛,通知'];
publicHeader();
require_once __DIR__ . '/inc/nav.php';
$flash = get_flash();
?>
<style>
.ntf-wrap{max-width:760px;margin:18px auto;padding:0 12px}
.ntf-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.ntf-head h1{margin:0;color:#1f3d24;font-size:20px}
.ntf-item{display:flex;gap:10px;align-items:flex-start;border:1px solid #e3eadf;border-radius:10px;background:#fff;padding:12px 14px;margin-bottom:8px;text-decoration:none;color:inherit}
.ntf-item.unread{background:#f3f8f1;border-left:3px solid #2c7d3f}
.ntf-dot{width:8px;height:8px;border-radius:50%;background:#e0533d;margin-top:6px;flex:none}
.ntf-dot.read{background:transparent}
.ntf-main{flex:1}
.ntf-msg{font-size:14px;color:#2c3a30;line-height:1.5}
.ntf-time{font-size:12px;color:#8aa091;margin-top:3px}
.ntf-empty{color:#7a8a7e;text-align:center;padding:40px 0}
</style>
<div class="ntf-wrap">
    <div class="ntf-head">
        <h1>通知</h1>
        <?php if ($total > 0): ?><a class="btn" href="<?php echo e(bbs_url('notifications?act=read_all&_csrf=' . csrfToken())); ?>">全部标为已读</a><?php endif; ?>
    </div>
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <?php if (empty($list)): ?>
        <div class="ntf-empty">暂无通知。</div>
    <?php else: foreach ($list as $n): ?>
        <a class="ntf-item <?php echo $n['is_read'] ? '' : 'unread'; ?>" href="<?php echo e(bbs_url('notifications?act=read&id=' . $n['id'] . '&_csrf=' . csrfToken())); ?>">
            <span class="ntf-dot <?php echo $n['is_read'] ? 'read' : ''; ?>"></span>
            <span class="ntf-main">
                <span class="ntf-msg"><?php echo e($n['message']); ?></span>
                <span class="ntf-time"><?php echo time_ago($n['created_at']); ?></span>
            </span>
        </a>
    <?php endforeach; endif; ?>
    <?php echo pagination_html($total, $page, $perpage, bbs_url('notifications')); ?>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
