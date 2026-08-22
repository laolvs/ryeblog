<?php
/**
 * RyeBlog 用户中心 —— 划线笔记
 */
require_once __DIR__ . '/header.php';
requireUser();

$user = currentUser();

// 删除划线
if (isset($_GET['del']) && isset($_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    $aid = (int)$_GET['del'];
    dbQuery('DELETE FROM vd_annotations WHERE id=? AND user_id=?', [$aid, $user['id']]);
    header('Location: ' . baseUrl('user/annotations.php'));
    exit;
}

$annotations = getAnnotations($user['id'], 200);

userHeader('划线笔记', 'annotations.php');
?>
<h1>划线笔记</h1>
<p class="muted" style="margin-top:-12px;margin-bottom:20px">在阅读文章时划线标记的内容和笔记</p>

<?php if (empty($annotations)): ?>
    <div class="uc-empty">还没有划线笔记<br><br><a href="<?php echo homeUrl(); ?>">去阅读文章 →</a></div>
<?php else: ?>
    <?php foreach ($annotations as $a): ?>
    <div class="uc-item">
        <div class="uc-item-title">
            <a href="<?php echo postUrl(['slug' => $a['post_slug'], 'id' => $a['post_id']]); ?>"><?php echo esc($a['post_title']); ?></a>
        </div>
        <div class="uc-item-meta">
            <?php echo esc($a['created_at']); ?>
            · <a href="<?php echo baseUrl('user/annotations.php?del=' . $a['id'] . '&_csrf=' . csrfToken()); ?>" onclick="return confirm('删除该划线？')" style="color:#c0392b">删除</a>
        </div>
        <div class="uc-quote"><?php echo esc($a['quote_text']); ?></div>
        <?php if (!empty($a['note'])): ?>
            <div class="uc-item-content"><strong>笔记：</strong><?php echo esc($a['note']); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php userFooter();
