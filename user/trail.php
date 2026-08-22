<?php
/**
 * RyeBlog 用户中心 —— 浏览轨迹
 */
require_once __DIR__ . '/header.php';
requireUser();

$user = currentUser();

// 清空轨迹
if (isset($_GET['clear']) && isset($_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    dbQuery('DELETE FROM vd_trail WHERE user_id=?', [$user['id']]);
    header('Location: ' . baseUrl('user/trail.php'));
    exit;
}

$trail = getTrail($user['id'], 200);

userHeader('浏览轨迹', 'trail.php');
?>
<h1>浏览轨迹</h1>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <p class="muted" style="margin:0">共 <?php echo count($trail); ?> 条浏览记录</p>
    <?php if ($trail): ?>
        <a href="<?php echo baseUrl('user/trail.php?clear=1&_csrf=' . csrfToken()); ?>" onclick="return confirm('确定清空所有浏览记录？')" style="color:#c0392b;font-size:.9rem">清空记录</a>
    <?php endif; ?>
</div>

<?php if (empty($trail)): ?>
    <div class="uc-empty">还没有浏览记录<br><br><a href="<?php echo homeUrl(); ?>">去阅读文章 →</a></div>
<?php else: ?>
    <div class="uc-panel" style="padding:0">
    <table class="uc-table">
        <tr><th>文章</th><th>访问时间</th><th>IP</th></tr>
        <?php foreach ($trail as $t): ?>
            <tr>
                <td><a href="<?php echo postUrl(['slug' => $t['post_slug'], 'id' => $t['post_id']]); ?>"><?php echo esc($t['post_title']); ?></a></td>
                <td class="muted"><?php echo esc($t['created_at']); ?></td>
                <td class="muted"><?php echo esc($t['ip']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
<?php endif; ?>
<?php userFooter();
