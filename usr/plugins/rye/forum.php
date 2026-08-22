<?php
/**
 * RYE社区（RyeBlog 插件）—— 论坛首页 / 版块页
 * 无 id：展示跨版块最新主题列表（参照 chake.org，Tab 筛选：最新/热门/精华）；
 * 有 id：展示该版块下的主题列表。
 */
require_once __DIR__ . '/bootstrap.php';

$GLOBALS['__rye_seo'] = ['desc' => 'RYE社区论坛', 'keywords' => '论坛,社区,讨论'];

$forum_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($forum_id) {
    $forum = db_row('SELECT * FROM ' . prefix() . 'forums WHERE id=?', [$forum_id]);
    if (!$forum) {
        http_response_code(404);
        publicHeader();
        echo '<div class="empty">版块不存在。</div>';
        publicFooter(rye_sidebar_html());
        exit;
    }
    $page_title = $forum['name'];
    $perpage = (int) setting('forum_threads_per_page', 30);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $total = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'threads WHERE forum_id=? AND is_deleted=0', [$forum_id]);
    $p = page_nav($total, $page, $perpage);
    $threads = db_all(
        'SELECT t.*, u.username, u.display_name, u.avatar_url, u.avatar_source, u.email,
                ue.nickname AS ext_nickname, ue.avatar AS ext_avatar
         FROM ' . prefix() . 'threads t
         LEFT JOIN vd_users u ON u.id=t.user_id
         LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=t.user_id
         WHERE t.forum_id=? AND t.is_deleted=0
         ORDER BY t.is_top DESC, t.updated_at DESC LIMIT ?, ?',
        [$forum_id, $p['offset'], $p['perpage']]
    );
} else {
    $page_title = '最新主题';
    $filter = isset($_GET['filter']) && in_array($_GET['filter'], ['latest', 'hot', 'good'], true) ? $_GET['filter'] : 'latest';
    $perpage = (int) setting('forum_threads_per_page', 30);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $where = 't.is_deleted=0';
    if ($filter === 'good') $where .= ' AND t.is_good=1';
    $total = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'threads t WHERE ' . $where);
    $p = page_nav($total, $page, $perpage);
    $order = $filter === 'hot'
        ? 't.is_top DESC, t.views DESC, t.updated_at DESC'
        : 't.is_top DESC, t.updated_at DESC';
    $threads = db_all(
        'SELECT t.*, f.name AS forum_name, u.username, u.display_name, u.avatar_url, u.avatar_source, u.email,
                ue.nickname AS ext_nickname, ue.avatar AS ext_avatar
         FROM ' . prefix() . 'threads t
         LEFT JOIN ' . prefix() . 'forums f ON f.id=t.forum_id
         LEFT JOIN vd_users u ON u.id=t.user_id
         LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=t.user_id
         WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ?, ?',
        [$p['offset'], $p['perpage']]
    );
}

$GLOBALS['bbs_page'] = 'forum';
$GLOBALS['bbs_post_forum_id'] = $forum_id;   // 顶栏「发帖」默认当前版块（无 id 时 0=首页）
publicHeader();
require_once __DIR__ . '/inc/nav.php';

if ($forum_id):
?>
    <div class="thread-head">
        <h1><?php echo e($forum['name']); ?></h1>
        <p class="muted"><?php echo e($forum['description']); ?></p>
        <div class="row-between" style="margin-top:10px">
            <span class="muted"><?php echo $forum['thread_count']; ?> 主题 · <?php echo $forum['post_count']; ?> 回复</span>
            <a class="btn btn-primary" href="<?php echo e(bbs_url('post?forum_id=' . $forum_id)); ?>">✚ 发表主题</a>
        </div>
    </div>
    <div class="panel">
        <div class="panel-body">
            <?php if (empty($threads)): ?>
                <div class="empty">该版块还没有主题。</div>
            <?php else: foreach ($threads as $t): ?>
                <div class="thread-item">
                    <div class="thread-avatar"><?php echo e(mb_substr(ryebbs_name($t), 0, 1, 'UTF-8')); ?></div>
                    <div class="thread-main">
                        <a class="thread-title" href="<?php echo e(bbs_url('thread?id=' . $t['id'])); ?>">
                            <?php if ($t['is_top']): ?><span class="tag tag-top">置顶</span><?php endif; ?>
                            <?php if ($t['is_good']): ?><span class="tag tag-good">精华</span><?php endif; ?>
                            <?php echo e($t['title']); ?>
                        </a>
                        <div class="thread-meta">
                            <span>👤 <a href="<?php echo e(bbs_url('user?id=' . $t['user_id'])); ?>"><?php echo e(ryebbs_name($t)); ?></a></span>
                            <span>💬 <?php echo $t['replies']; ?></span>
                            <span>👁 <?php echo $t['views']; ?></span>
                            <span><?php echo time_ago($t['updated_at']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php echo pagination_html($total, $page, $perpage, bbs_url('forum?id=' . $forum_id)); ?>
<?php else: ?>
    <div class="thread-head">
        <h1><?php echo __('最新主题'); ?></h1>
        <div class="row-between" style="margin-top:10px">
            <div class="forum-tabs">
                <a class="forum-tab<?php echo $filter === 'latest' ? ' active' : ''; ?>" href="<?php echo e(bbs_url('forum')); ?>">最新</a>
                <a class="forum-tab<?php echo $filter === 'hot' ? ' active' : ''; ?>" href="<?php echo e(bbs_url('forum?filter=hot')); ?>">热门</a>
                <a class="forum-tab<?php echo $filter === 'good' ? ' active' : ''; ?>" href="<?php echo e(bbs_url('forum?filter=good')); ?>">精华</a>
            </div>
            <a class="btn btn-primary" href="<?php echo e(bbs_url('post')); ?>">✚ 发表主题</a>
        </div>
    </div>
    <div class="panel">
        <div class="panel-body">
            <?php if (empty($threads)): ?>
                <div class="empty"><?php echo __('还没有主题，来发第一帖吧！'); ?></div>
            <?php else: foreach ($threads as $t): ?>
                <div class="thread-item">
                    <div class="thread-avatar"><?php echo e(mb_substr(ryebbs_name($t), 0, 1, 'UTF-8')); ?></div>
                    <div class="thread-main">
                        <a class="thread-title" href="<?php echo e(bbs_url('thread?id=' . $t['id'])); ?>">
                            <?php if ($t['is_top']): ?><span class="tag tag-top">置顶</span><?php endif; ?>
                            <?php if ($t['is_good']): ?><span class="tag tag-good">精华</span><?php endif; ?>
                            <?php echo e($t['title']); ?>
                        </a>
                        <div class="thread-meta">
                            <span>👤 <a href="<?php echo e(bbs_url('user?id=' . $t['user_id'])); ?>"><?php echo e(ryebbs_name($t)); ?></a></span>
                            <?php if (!empty($t['forum_name'])): ?>
                            <span>📁 <a class="thread-forum" href="<?php echo e(bbs_url('forum?id=' . $t['forum_id'])); ?>"><?php echo e($t['forum_name']); ?></a></span>
                            <?php endif; ?>
                            <span>💬 <?php echo $t['replies']; ?></span>
                            <span>👁 <?php echo $t['views']; ?></span>
                            <span><?php echo time_ago($t['updated_at']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php echo pagination_html($total, $page, $perpage, bbs_url('forum?filter=' . $filter)); ?>
<?php endif; ?>
<?php publicFooter(rye_sidebar_html()); ?>
