<?php
/**
 * RyeBlog 用户中心 —— 我的收藏
 */
require_once __DIR__ . '/header.php';
requireUser();

$user = currentUser();

// 取消收藏
if (isset($_GET['remove']) && isset($_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $pid = (int)$_GET['remove'];
    dbQuery('DELETE FROM vd_favorites WHERE user_id=? AND post_id=?', [$user['id'], $pid]);
    header('Location: ' . baseUrl('user/fav.php'));
    exit;
}

$favs = getFavorites($user['id'], 100);

userHeader('我的收藏', 'fav.php');
?>
<h1>我的收藏</h1>
<p class="muted" style="margin-top:-12px;margin-bottom:20px">共 <?php echo count($favs); ?> 篇收藏文章</p>

<?php if (empty($favs)): ?>
    <div class="uc-empty">还没有收藏任何文章</div>
<?php else: ?>
    <?php foreach ($favs as $f): ?>
    <div class="uc-item">
        <div class="uc-item-title">
            <a href="<?php echo postUrl($f); ?>"><?php echo esc($f['title']); ?></a>
        </div>
        <div class="uc-item-meta">
            收藏于 <?php echo esc($f['fav_at']); ?>
            · <a href="<?php echo baseUrl('user/fav.php?remove=' . $f['id'] . '&_csrf=' . csrfToken()); ?>" onclick="return confirm('取消收藏？')" style="color:#c0392b">取消收藏</a>
        </div>
        <?php if (!empty($f['excerpt'])): ?>
            <div class="uc-item-content"><?php echo esc(mb_substr($f['excerpt'], 0, 120)); ?>…</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php userFooter();
