<?php
/**
 * RYE社区（RyeBlog 插件）—— 话题详情 + 回复
 * 路由：/bbs/thread?id=N
 */
require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$thread = $id ? db_row(
    'SELECT t.*, u.username, u.display_name, u.avatar_url, u.avatar_source, u.email,
            ue.nickname AS ext_nickname, ue.avatar AS ext_avatar
     FROM ' . prefix() . 'threads t
     LEFT JOIN vd_users u ON u.id=t.user_id
     LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=t.user_id
     WHERE t.id=?',
    [$id]
) : null;

if (!$thread || !empty($thread['is_deleted'])) {
    http_response_code(404);
    publicHeader();
    echo '<div class="empty">主题不存在或已被删除。</div>';
    publicFooter(rye_sidebar_html());
    exit;
}

// 浏览量（同一会话仅计一次，刷新/爬虫不虚增）
$viewKey = 'rye_viewed_thread_' . $id;
if (empty($_SESSION[$viewKey])) {
    dbQuery('UPDATE ' . prefix() . 'threads SET views=views+1 WHERE id=?', [$id]);
    $_SESSION[$viewKey] = 1;
}

// 回复列表
$replies = db_all(
    'SELECT p.*, u.username, u.display_name, u.avatar_url, u.avatar_source, u.email,
            ue.nickname AS ext_nickname, ue.avatar AS ext_avatar
     FROM ' . prefix() . 'posts p
     LEFT JOIN vd_users u ON u.id=p.user_id
     LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=p.user_id
     WHERE p.thread_id=? AND p.is_deleted=0 ORDER BY p.floor ASC, p.created_at ASC',
    [$id]
);

// 发表回复
$replyError = '';
$replyKeep  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    verify_csrf();
    $content = trim($_POST['content'] ?? '');
    $replyKeep = $content; // 校验失败时保留输入
    $filtered = ryebbs_filter_sensitive($content);
    // 远程图片自动本地化（失败保留原链）
    $filtered = function_exists('localizeRemoteImages') ? localizeRemoteImages($filtered, 'markdown') : $filtered;
    if (!empty($thread['is_closed'])) {
        $replyError = '该主题已关闭，无法回复';
    } elseif (ryebbs_ip_banned()) {
        $replyError = '您的 IP 已被封禁，无法回复';
    } elseif ($filtered === false) {
        $replyError = '回复包含敏感词，已被拦截';
    } elseif ($content === '') {
        $replyError = '回复内容不能为空';
    } else {
        // 楼层计算加锁防并发同楼层：事务 + SELECT ... FOR UPDATE
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $floor = (int) db_val('SELECT COALESCE(MAX(floor),0)+1 FROM ' . prefix() . 'posts WHERE thread_id=? FOR UPDATE', [$id]);
            dbQuery('INSERT INTO ' . prefix() . 'posts (thread_id, user_id, content, is_deleted, floor, ip, created_at)
                     VALUES (?, ?, ?, 0, ?, ?, NOW())',
                [$id, currentUser()['id'], $filtered, $floor, client_ip()]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        dbQuery('UPDATE ' . prefix() . 'threads SET replies=replies+1, last_post_at=NOW() WHERE id=?', [$id]);
        dbQuery('UPDATE ' . prefix() . 'forums SET post_count=post_count+1 WHERE id=?', [$thread['forum_id']]);
        ryebbs_recount_user(currentUser()['id']);
        // 通知楼主（自己回自己的帖不通知）
        ryebbs_notify((int) $thread['user_id'], $id, 0, (int) currentUser()['id'],
            '有人回复了你的主题《' . mb_substr($thread['title'], 0, 30) . '》');
        set_flash('回复成功', 'success');
        header('Location: ' . bbs_url('thread?id=' . $id) . '#reply-' . $floor);
        exit;
    }
}

// 点赞 / 收藏（GET 动作，仅影响当前用户自身数据；带 CSRF token 防跨站触发）
$act = $_GET['act'] ?? '';
if ($act && isLoggedIn()) {
    if (!isset($_GET['_csrf']) || !hash_equals(csrfToken(), (string) $_GET['_csrf'])) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试。');
    }
    $uid = (int) currentUser()['id'];
    if ($act === 'like') {
        $rid = db_val('SELECT id FROM ' . prefix() . 'reactions WHERE user_id=? AND thread_id=? AND post_id=0 AND reaction_type=?', [$uid, $id, 'like']);
        if ($rid) {
            dbQuery('DELETE FROM ' . prefix() . 'reactions WHERE id=?', [$rid]);
        } else {
            dbQuery('INSERT INTO ' . prefix() . 'reactions (thread_id,post_id,user_id,reaction_type,ip,created_at) VALUES (?,0,?,?,?,NOW())', [$id, $uid, 'like', client_ip()]);
            // 首次点赞通知作者
            ryebbs_notify((int) $thread['user_id'], $id, 0, $uid,
                '有人赞了你的主题《' . mb_substr($thread['title'], 0, 30) . '》');
        }
        header('Location: ' . bbs_url('thread?id=' . $id));
        exit;
    }
    if ($act === 'fav') {
        $fid = db_val('SELECT id FROM ' . prefix() . 'favorites WHERE user_id=? AND thread_id=?', [$uid, $id]);
        if ($fid) {
            dbQuery('DELETE FROM ' . prefix() . 'favorites WHERE id=?', [$fid]);
        } else {
            dbQuery('INSERT INTO ' . prefix() . 'favorites (user_id,thread_id,created_at) VALUES (?,?,NOW())', [$uid, $id]);
        }
        header('Location: ' . bbs_url('thread?id=' . $id));
        exit;
    }
}

$me         = isLoggedIn() ? (int) currentUser()['id'] : 0;
$like_count = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'reactions WHERE thread_id=? AND post_id=0 AND reaction_type=?', [$id, 'like']);
$fav_count  = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'favorites WHERE thread_id=?', [$id]);
$liked      = $me ? (bool) db_val('SELECT 1 FROM ' . prefix() . 'reactions WHERE user_id=? AND thread_id=? AND post_id=0 AND reaction_type=?', [$me, $id, 'like']) : false;
$faved      = $me ? (bool) db_val('SELECT 1 FROM ' . prefix() . 'favorites WHERE user_id=? AND thread_id=?', [$me, $id]) : false;

$GLOBALS['__rye_seo'] = ['desc' => $thread['title'], 'keywords' => '论坛,话题,' . $thread['title']];
$GLOBALS['bbs_page']  = 'forum';
$GLOBALS['bbs_post_forum_id'] = (int) $thread['forum_id'];   // 顶栏「发帖」默认当前版块
publicHeader();
require_once __DIR__ . '/inc/nav.php';
$flash = get_flash();
if ($flash) {
    echo '<div class="flash flash-' . e($flash['type']) . '">' . e($flash['msg']) . '</div>';
}
?>
<style>
.thread-detail{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:18px;margin:18px auto;max-width:860px}
.thread-detail h1{margin:0 0 6px;font-size:22px;color:#1f3d24}
.td-meta{color:#7a8a7e;font-size:13px;margin-bottom:12px}
.tag{display:inline-block;border-radius:6px;padding:1px 8px;font-size:12px;margin-left:6px;vertical-align:middle}
.tag-top{background:#fff3df;color:#b5742b;border:1px solid #ffe3b8}
.tag-good{background:#eaf3e6;color:#2c5234;border:1px solid #cfe6c8}
.tag-cat{background:#e8f0ff;color:#2b5fb3;border:1px solid #cfe0ff}
.td-content{line-height:1.7;color:#2c3a30;word-break:break-word}
.td-content p{margin:0 0 8px}
.td-content h1,.td-content h2,.td-content h3,.td-content h4,.td-content h5,.td-content h6{margin:14px 0 8px;color:#1f3d24;line-height:1.4}
.td-content h1{font-size:20px}.td-content h2{font-size:18px}.td-content h3{font-size:16px}
.td-content ul,.td-content ol{margin:0 0 8px;padding-left:22px}
.td-content li{margin:3px 0}
.td-content blockquote{margin:0 0 8px;padding:6px 12px;border-left:4px solid #2c7d3f;background:#f3f8f1;color:#4a5a4e;border-radius:0 6px 6px 0}
.td-content pre{background:#f4f6f3;border:1px solid #e3eadf;border-radius:8px;padding:10px 12px;overflow-x:auto;margin:0 0 8px}
.td-content pre code{background:none;padding:0;font-size:13px}
.td-content code{background:#eef3ec;border-radius:4px;padding:1px 5px;font-size:13px;font-family:Consolas,Menlo,monospace}
.td-content a{color:#2c7d3f}
.td-content img{max-width:100%;border-radius:8px}
.td-content del{color:#8a968c}
.td-content hr{border:none;border-top:1px dashed #cfd9c8;margin:12px 0}
.td-actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
.td-btn{display:inline-flex;align-items:center;gap:5px;border:1px solid #cfe6c8;background:#f3f8f1;border-radius:18px;padding:6px 14px;color:#2c5234;text-decoration:none;font-size:14px}
.td-btn:hover{background:#e7f1e3}
.td-btn.on{background:#2c7d3f;color:#fff;border-color:#2c7d3f}
.td-btn.disabled{color:#8aa091;cursor:default}
.reply{border-top:1px solid #eef2ec;padding:14px 0;display:flex;gap:12px}
.reply-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;background:#eef3ec}
.reply-main{flex:1;min-width:0}
.reply-head{font-size:13px;color:#6b7a6f;margin-bottom:4px}
.reply-head b{color:#1f3d24}
.reply-form{margin-top:16px}
.reply-form textarea{width:100%;min-height:90px;border:1px solid #cfd9c8;border-radius:8px;padding:9px;font:inherit}
</style>

<div class="thread-detail">
    <h1><?php echo e($thread['title']); ?></h1>
    <div class="td-meta">
        👤 <a href="<?php echo e(bbs_url('user?id=' . $thread['user_id'])); ?>"><?php echo e(ryebbs_name($thread)); ?></a>
        · 💬 <?php echo $thread['replies']; ?> 回复 · 👁 <?php echo $thread['views']; ?> 浏览 · <?php echo time_ago($thread['created_at']); ?>
        <?php if ($thread['is_top']): ?><span class="tag tag-top">置顶</span><?php endif; ?>
        <?php if ($thread['is_good']): ?><span class="tag tag-good">精华</span><?php endif; ?>
        <?php if (!empty($thread['topic_category'])): ?><span class="tag tag-cat"><?php echo e($thread['topic_category']); ?></span><?php endif; ?>
    </div>
    <div class="td-content"><?php echo markdownToHtml($thread['content']); ?></div>
    <div class="td-actions">
        <?php if ($me): ?>
            <a class="td-btn <?php echo $liked ? 'on' : ''; ?>" href="<?php echo e(bbs_url('thread?id=' . $id . '&act=like&_csrf=' . csrfToken())); ?>">👍 赞 <b><?php echo $like_count; ?></b></a>
            <a class="td-btn <?php echo $faved ? 'on' : ''; ?>" href="<?php echo e(bbs_url('thread?id=' . $id . '&act=fav&_csrf=' . csrfToken())); ?>">⭐ 收藏 <b><?php echo $fav_count; ?></b></a>
            <?php if ($me == $thread['user_id']): ?>
                <a class="td-btn" href="<?php echo e(bbs_url('post?id=' . $id)); ?>">✏️ 编辑</a>
            <?php endif; ?>
        <?php else: ?>
            <span class="td-btn disabled">👍 <?php echo $like_count; ?> · ⭐ <?php echo $fav_count; ?>（登录后可互动）</span>
        <?php endif; ?>
    </div>
    <p style="margin-top:14px"><a class="btn" href="<?php echo e(bbs_url('forum?id=' . $thread['forum_id'])); ?>">← 返回版块</a></p>
</div>

<div style="max-width:860px;margin:0 auto 18px">
    <h3 style="color:#1f3d24">全部回复（<?php echo count($replies); ?>）</h3>
    <?php if ($replyError): ?>
    <div class="flash flash-error" style="background:#fdf3f3;border:1px solid #e0533d;color:#a33;border-radius:8px;padding:10px 14px;margin:0 0 12px"><?php echo e($replyError); ?></div>
    <?php endif; ?>
    <?php if (empty($replies)): ?>
        <div class="empty">还没有回复，来抢沙发～</div>
    <?php else: foreach ($replies as $r): ?>
        <div class="reply" id="reply-<?php echo (int) $r['floor']; ?>">
            <img class="reply-avatar" src="<?php echo e(ryebbs_avatar_src($r, 40)); ?>" alt="">
            <div class="reply-main">
                <div class="reply-head"><b><?php echo e(ryebbs_name($r)); ?></b> · <?php echo time_ago($r['created_at']); ?> · <a href="#reply-<?php echo (int) $r['floor']; ?>" style="color:#7a8a7e;text-decoration:none">#<?php echo $r['floor']; ?></a>
                    <?php if (isLoggedIn() && $r['user_id'] != ($me ?? 0)): ?>
                    · <a href="javascript:;" class="rye-at" data-at="<?php echo e(ryebbs_name($r)); ?>" style="color:#2c7d3f;text-decoration:none;font-size:12px">@TA</a>
                    <?php endif; ?>
                </div>
                <div class="td-content"><?php echo markdownToHtml($r['content']); ?></div>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <div class="reply-form panel"><div class="panel-body">
        <?php if (isLoggedIn()): ?>
            <form method="post">
                <?php echo csrf_field(); ?>
                <div class="rye-toolbar">
                    <button type="button" data-md="bold" title="加粗"><svg class="ic"><use href="#ic-bold"/></svg></button>
                    <button type="button" data-md="italic" title="斜体"><svg class="ic"><use href="#ic-italic"/></svg></button>
                    <button type="button" data-md="strike" title="删除线"><svg class="ic"><use href="#ic-strike"/></svg></button>
                    <span class="rye-tb-sep"></span>
                    <button type="button" data-md="h2" title="标题"><svg class="ic"><use href="#ic-heading"/></svg></button>
                    <button type="button" data-md="quote" title="引用"><svg class="ic"><use href="#ic-quote"/></svg></button>
                    <button type="button" data-md="ul" title="无序列表"><svg class="ic"><use href="#ic-list"/></svg></button>
                    <button type="button" data-md="ol" title="有序列表"><svg class="ic"><use href="#ic-list-ol"/></svg></button>
                    <span class="rye-tb-sep"></span>
                    <button type="button" data-md="code" title="行内代码"><svg class="ic"><use href="#ic-code"/></svg></button>
                    <button type="button" data-md="codeblock" title="代码块"><svg class="ic"><use href="#ic-codeblock"/></svg></button>
                    <button type="button" data-md="link" title="插入链接"><svg class="ic"><use href="#ic-link"/></svg></button>
                    <button type="button" data-md="image" title="插入图片链接"><svg class="ic"><use href="#ic-image"/></svg></button>
                    <button type="button" data-upload="img" title="上传图片"><svg class="ic"><use href="#ic-upload"/></svg></button>
                    <button type="button" data-upload="file" title="上传附件"><svg class="ic"><use href="#ic-attach"/></svg></button>
                </div>
                <textarea class="rye-editor" name="content" placeholder="写下你的回复…"><?php echo e($replyKeep); ?></textarea>
                <div style="margin-top:8px;text-align:right"><button class="btn btn-primary" type="submit">发表回复</button></div>
            </form>
        <?php else: ?>
            <p class="muted"><a class="btn" href="<?php echo e(baseUrl('user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']))); ?>">登录后参与回复</a></p>
        <?php endif; ?>
    </div></div>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
