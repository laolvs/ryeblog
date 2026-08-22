<?php
/**
 * RYE社区（RyeBlog 插件）—— 我的收藏
 * 路由：/bbs/favorites
 */
require_once __DIR__ . '/bootstrap.php';
require_login();

$uid  = (int) currentUser()['id'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perpage = 20;
$total = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'favorites WHERE user_id=?', [$uid]);
$p = page_nav($total, $page, $perpage);
$list = db_all(
    'SELECT t.*, f.created_at AS fav_at, u.username, ue.nickname AS ext_nickname
     FROM ' . prefix() . 'favorites f
     LEFT JOIN ' . prefix() . 'threads t ON t.id=f.thread_id
     LEFT JOIN vd_users u ON u.id=t.user_id
     LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=t.user_id
     WHERE f.user_id=? AND t.is_deleted=0
     ORDER BY f.created_at DESC LIMIT ?, ?',
    [$uid, $p['offset'], $p['perpage']]
);

$GLOBALS['bbs_page'] = 'favorites';
$GLOBALS['__rye_seo'] = ['desc' => '我的收藏', 'keywords' => '论坛,收藏'];
publicHeader();
require_once __DIR__ . '/inc/nav.php';
?>
<style>
.fav-wrap{max-width:860px;margin:18px auto;padding:0 12px}
.fav-wrap h1{color:#1f3d24;font-size:20px;margin:0 0 12px}
.fav-item{border:1px solid #e3eadf;border-radius:10px;background:#fff;padding:12px 14px;margin-bottom:10px}
.fav-title{font-size:16px;color:#1f3d24;text-decoration:none;font-weight:600}
.fav-title:hover{color:#2c7d3f}
.fav-meta{color:#8aa091;font-size:12px;margin-top:5px}
.empty{color:#7a8a7e;text-align:center;padding:40px 0}
</style>
<div class="fav-wrap">
    <h1>我的收藏</h1>
    <?php if (empty($list)): ?>
        <div class="empty">你还没有收藏任何主题。在主题页点「⭐ 收藏」即可加入这里。</div>
    <?php else: foreach ($list as $t): ?>
        <div class="fav-item">
            <a class="fav-title" href="<?php echo e(bbs_url('thread?id=' . $t['id'])); ?>">
                <?php if ($t['is_top']): ?><span class="tag tag-top">置顶</span><?php endif; ?><?php echo e($t['title']); ?>
            </a>
            <div class="fav-meta">👤 <?php echo e(ryebbs_name($t)); ?> · 💬 <?php echo $t['replies']; ?> · 收藏于 <?php echo time_ago($t['fav_at']); ?></div>
        </div>
    <?php endforeach; endif; ?>
    <?php echo pagination_html($total, $page, $perpage, bbs_url('favorites')); ?>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
