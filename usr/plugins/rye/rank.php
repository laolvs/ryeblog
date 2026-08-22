<?php
/**
 * RYE社区（RyeBlog 插件）—— 排行榜
 * 路由：/bbs/rank?by=coins|threads|replies
 */
require_once __DIR__ . '/bootstrap.php';

$by = $_GET['by'] ?? 'coins';
$allowed = ['coins' => 'coins', 'threads' => 'thread_count', 'replies' => 'reply_count'];
$col = $allowed[$by] ?? 'coins';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perpage = 30;
$total = (int) db_val(
    'SELECT COUNT(*) FROM ' . prefix() . 'user_ext WHERE coins>0 OR thread_count>0 OR reply_count>0'
);
$p = page_nav($total, $page, $perpage);
$rows = db_all(
    'SELECT ue.*, u.username, u.display_name, u.avatar_url, u.avatar_source, u.email
     FROM ' . prefix() . 'user_ext ue
     LEFT JOIN vd_users u ON u.id=ue.user_id
     WHERE ue.coins>0 OR ue.thread_count>0 OR ue.reply_count>0
     ORDER BY ue.' . $col . ' DESC LIMIT ?, ?',
    [$p['offset'], $p['perpage']]
);

function __rank_tab($key, $label, $active)
{
    $cls = $active === $key ? ' active' : '';
    return '<a class="rank-tab' . $cls . '" href="' . e(bbs_url('rank?by=' . $key)) . '">' . e($label) . '</a>';
}
$GLOBALS['bbs_page'] = 'rank';
$GLOBALS['__rye_seo'] = ['desc' => '排行榜', 'keywords' => '论坛,排行榜'];
publicHeader();
require_once __DIR__ . '/inc/nav.php';
?>
<style>
.rank-wrap{max-width:760px;margin:18px auto;padding:0 12px}
.rank-wrap h1{color:#1f3d24;font-size:20px;margin:0 0 12px}
.rank-tabs{display:flex;gap:8px;margin-bottom:14px}
.rank-tab{text-decoration:none;color:#3a4a3e;border:1px solid #cfe6c8;background:#f3f8f1;border-radius:18px;padding:6px 16px;font-size:14px}
.rank-tab.active{background:#2c7d3f;color:#fff;border-color:#2c7d3f}
.rank-item{display:flex;align-items:center;gap:12px;border:1px solid #e3eadf;border-radius:10px;background:#fff;padding:10px 14px;margin-bottom:8px}
.rank-no{width:28px;text-align:center;font-weight:700;color:#8aa091;font-size:16px}
.rank-no.top{color:#e0a23d}
.rank-av{width:38px;height:38px;border-radius:50%;object-fit:cover;background:#eef3ec}
.rank-main{flex:1}
.rank-name{font-size:15px;color:#1f3d24;font-weight:600;text-decoration:none}
.rank-sub{font-size:12px;color:#8aa091}
.rank-val{font-weight:700;color:#2c7d3f}
.empty{color:#7a8a7e;text-align:center;padding:40px 0}
</style>
<div class="rank-wrap">
    <h1>社区排行榜</h1>
    <div class="rank-tabs">
        <?php echo __rank_tab('coins', '金币榜', $by); ?>
        <?php echo __rank_tab('threads', '主题榜', $by); ?>
        <?php echo __rank_tab('replies', '回复榜', $by); ?>
    </div>
    <?php if (empty($rows)): ?>
        <div class="empty">暂无排行数据。</div>
    <?php else: foreach ($rows as $i => $r):
        $no = $p['offset'] + $i + 1;
    ?>
        <div class="rank-item">
            <div class="rank-no <?php echo $no <= 3 ? 'top' : ''; ?>"><?php echo $no; ?></div>
            <img class="rank-av" src="<?php echo e(ryebbs_avatar_src($r, 38)); ?>" alt="">
            <a class="rank-main" href="<?php echo e(bbs_url('user?id=' . $r['user_id'])); ?>">
                <div class="rank-name"><?php echo e(ryebbs_name($r)); ?></div>
                <div class="rank-sub">主题 <?php echo $r['thread_count']; ?> · 回复 <?php echo $r['reply_count']; ?></div>
            </a>
            <div class="rank-val"><?php
                if ($by === 'coins') echo '💰 ' . $r['coins'];
                elseif ($by === 'threads') echo '📝 ' . $r['thread_count'];
                else echo '💬 ' . $r['reply_count'];
            ?></div>
        </div>
    <?php endforeach; endif; ?>
    <?php echo pagination_html($total, $page, $perpage, bbs_url('rank?by=' . $by)); ?>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
