<?php
/**
 * RYE社区（RyeBlog 插件）—— 搜索
 * 路由：/bbs/search?q=关键词
 */
require_once __DIR__ . '/bootstrap.php';

$GLOBALS['bbs_page'] = 'search';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perpage = 20;
$results = [];
$total = 0;

if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $total = (int) db_val(
        'SELECT COUNT(*) FROM ' . prefix() . 'threads WHERE is_deleted=0 AND (title LIKE ? OR content LIKE ?)',
        [$like, $like]
    );
    $p = page_nav($total, $page, $perpage);
    $results = db_all(
        'SELECT t.*, u.username, ue.nickname AS ext_nickname
         FROM ' . prefix() . 'threads t
         LEFT JOIN vd_users u ON u.id=t.user_id
         LEFT JOIN ' . prefix() . 'user_ext ue ON ue.user_id=t.user_id
         WHERE t.is_deleted=0 AND (t.title LIKE ? OR t.content LIKE ?)
         ORDER BY t.replies DESC, t.updated_at DESC LIMIT ?, ?',
        [$like, $like, $p['offset'], $p['perpage']]
    );
}

$GLOBALS['__rye_seo'] = ['desc' => '搜索', 'keywords' => '论坛,搜索'];
publicHeader();
require_once __DIR__ . '/inc/nav.php';
?>
<style>
.search-wrap{max-width:860px;margin:18px auto;padding:0 12px}
.search-box{display:flex;gap:8px;margin-bottom:14px}
.search-box input{flex:1;border:1px solid #cfd9c8;border-radius:8px;padding:10px;font:inherit}
.search-box button{border:0;background:#2c7d3f;color:#fff;border-radius:8px;padding:0 18px;cursor:pointer}
.search-meta{color:#7a8a7e;font-size:13px;margin-bottom:10px}
.result-item{border:1px solid #e3eadf;border-radius:10px;background:#fff;padding:12px 14px;margin-bottom:10px}
.result-title{font-size:16px;color:#1f3d24;text-decoration:none;font-weight:600}
.result-title:hover{color:#2c7d3f}
.result-excerpt{color:#4a5a4e;font-size:13px;margin-top:4px;line-height:1.6}
.result-meta{color:#8aa091;font-size:12px;margin-top:6px}
mark{background:#fff3b0;padding:0 2px;border-radius:3px}
</style>
<div class="search-wrap">
    <form class="search-box" method="get" action="<?php echo e(bbs_url('search')); ?>">
        <input name="q" value="<?php echo e($q); ?>" placeholder="搜索主题关键词…" autofocus>
        <button type="submit">搜索</button>
    </form>

    <?php if ($q === ''): ?>
        <div class="empty">输入关键词，搜索社区里的主题。</div>
    <?php elseif (empty($results)): ?>
        <div class="search-meta">没有找到与「<?php echo e($q); ?>」相关的主题。</div>
    <?php else: ?>
        <div class="search-meta">找到 <?php echo $total; ?> 个与「<?php echo e($q); ?>」相关的主题</div>
        <?php foreach ($results as $r):
            // 匹配上下文摘要：优先截取关键词附近 ±60 字；关键词在标题中则取内容开头
            $plain = strip_tags($r['content']);
            $qpos  = mb_strpos($plain, $q, 0, 'UTF-8');
            if ($qpos === false && mb_strpos($r['title'], $q, 0, 'UTF-8') !== false) {
                $qpos = mb_strpos($plain, $q, 0, 'UTF-8') ?: -1;
            }
            if ($qpos !== false && $qpos >= 0) {
                $start = max(0, $qpos - 60);
                $prefix = $start > 0 ? '…' : '';
                $excerpt = $prefix . mb_substr($plain, $start, 130, 'UTF-8') . '…';
            } else {
                $excerpt = mb_strimwidth($plain, 0, 130, '…', 'UTF-8');
            }
            // 关键词高亮（标题 + 摘要，转义后替换）
            $hl = function ($s) use ($q) {
                return preg_replace('/(' . preg_quote($q, '/') . ')/u', '<mark>$1</mark>', $s);
            };
        ?>
            <div class="result-item">
                <a class="result-title" href="<?php echo e(bbs_url('thread?id=' . $r['id'])); ?>"><?php echo $hl(e($r['title'])); ?></a>
                <div class="result-excerpt"><?php echo $hl(e($excerpt)); ?></div>
                <div class="result-meta">👤 <?php echo e(ryebbs_name($r)); ?> · 💬 <?php echo $r['replies']; ?> · <?php echo time_ago($r['updated_at']); ?></div>
            </div>
        <?php endforeach; ?>
        <?php echo pagination_html($total, $page, $perpage, bbs_url('search?q=' . urlencode($q))); ?>
    <?php endif; ?>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
