<?php
/**
 * RyeBlog 用户中心 —— 面板首页
 */
require_once __DIR__ . '/header.php';
requireUser();

$user = currentUser();
$stats = [
    'favorites'   => (int)dbOne('SELECT COUNT(*) AS c FROM vd_favorites WHERE user_id=?', [$user['id']])['c'],
    'annotations' => (int)dbOne('SELECT COUNT(*) AS c FROM vd_annotations WHERE user_id=?', [$user['id']])['c'],
    'corrections' => (int)dbOne('SELECT COUNT(*) AS c FROM vd_corrections WHERE user_id=?', [$user['id']])['c'],
    'trail'       => (int)dbOne('SELECT COUNT(*) AS c FROM vd_trail WHERE user_id=?', [$user['id']])['c'],
];
$recentTrail = getTrail($user['id'], 5);
$recentFav   = getFavorites($user['id'], 5);

userHeader('用户面板', 'index.php');
?>
<h1>用户面板</h1>

<div class="uc-stat-grid">
    <div class="uc-stat"><span class="stat-num"><?php echo $stats['favorites']; ?></span><span class="stat-label">收藏</span></div>
    <div class="uc-stat"><span class="stat-num"><?php echo $stats['annotations']; ?></span><span class="stat-label">划线笔记</span></div>
    <div class="uc-stat"><span class="stat-num"><?php echo $stats['corrections']; ?></span><span class="stat-label">纠错</span></div>
    <div class="uc-stat"><span class="stat-num"><?php echo $stats['trail']; ?></span><span class="stat-label">浏览记录</span></div>
</div>

<div class="uc-panel">
    <h2>最近浏览</h2>
    <?php if ($recentTrail): ?>
    <table class="uc-table">
        <tr><th>文章</th><th>时间</th></tr>
        <?php foreach ($recentTrail as $t): ?>
            <tr>
                <td><a href="<?php echo postUrl(['slug' => $t['post_slug'], 'id' => $t['post_id']]); ?>"><?php echo esc($t['post_title']); ?></a></td>
                <td class="muted"><?php echo esc($t['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p class="uc-empty">暂无浏览记录</p>
    <?php endif; ?>
</div>

<div class="uc-panel">
    <h2>最近收藏</h2>
    <?php if ($recentFav): ?>
    <table class="uc-table">
        <tr><th>文章</th><th>收藏时间</th></tr>
        <?php foreach ($recentFav as $f): ?>
            <tr>
                <td><a href="<?php echo postUrl($f); ?>"><?php echo esc($f['title']); ?></a></td>
                <td class="muted"><?php echo esc($f['fav_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p class="uc-empty">暂无收藏</p>
    <?php endif; ?>
</div>
<?php userFooter();
