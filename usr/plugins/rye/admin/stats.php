<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台访问统计
 * 路由：admin/plugin.php?p=rye&page=stats
 *
 * 基于 ryebbs_stats 表做概览（与站点主统计独立）。
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();
$today = date('Y-m-d');
$yest  = date('Y-m-d', strtotime('-1 day'));

$totalPv   = (int) db_val("SELECT COUNT(*) FROM {$P}stats");
$todayPv   = (int) db_val("SELECT COUNT(*) FROM {$P}stats WHERE created_at>=?", [$today]);
$yestPv    = (int) db_val("SELECT COUNT(*) FROM {$P}stats WHERE created_at>=? AND created_at<?", [$yest, $today]);
$spiderPv  = (int) db_val("SELECT COUNT(*) FROM {$P}stats WHERE is_spider=1");
$realPv    = $totalPv - $spiderPv;
$topPages  = db_all("SELECT page_url, COUNT(*) c FROM {$P}stats WHERE is_spider=0 GROUP BY page_url ORDER BY c DESC LIMIT 10");
$topRef    = db_all("SELECT referer_domain, COUNT(*) c FROM {$P}stats WHERE referer_domain<>'' AND is_spider=0 GROUP BY referer_domain ORDER BY c DESC LIMIT 10");
$topBrowser= db_all("SELECT browser, COUNT(*) c FROM {$P}stats WHERE is_spider=0 GROUP BY browser ORDER BY c DESC LIMIT 6");

adminHead('访问统计 · RYE社区');
require __DIR__ . '/inc/admin_nav.php';
?>
<style>
.mt-admin-wrap{max-width:1000px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-stats{display:flex;gap:14px;flex-wrap:wrap}
.mt-stat{flex:1;min-width:140px;background:#f3f8f1;border-radius:10px;padding:14px;text-align:center}
.mt-stat b{display:block;font-size:24px;color:#2c7d3f}
.mt-stat span{font-size:13px;color:#7a8a7e}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.muted{color:#8a968c}
</style>
<div class="mt-admin-wrap">
    <div class="mt-card">
        <h2>概览</h2>
        <div class="mt-stats">
            <div class="mt-stat"><b><?php echo $totalPv; ?></b><span>总访问量</span></div>
            <div class="mt-stat"><b><?php echo $todayPv; ?></b><span>今日</span></div>
            <div class="mt-stat"><b><?php echo $yestPv; ?></b><span>昨日</span></div>
            <div class="mt-stat"><b><?php echo $realPv; ?></b><span>真实访问</span></div>
            <div class="mt-stat"><b><?php echo $spiderPv; ?></b><span>爬虫</span></div>
        </div>
    </div>
    <div class="mt-card">
        <h2>热门页面（Top 10）</h2>
        <?php if (empty($topPages)): ?><p class="muted">暂无数据。</p><?php else: ?>
        <table class="mt-table"><thead><tr><th>页面</th><th>访问量</th></tr></thead><tbody>
            <?php foreach ($topPages as $r): ?><tr><td><?php echo e(mb_strimwidth($r['page_url'],0,80,'…','UTF-8')); ?></td><td><?php echo (int)$r['c']; ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
    <div class="mt-card">
        <h2>来源域名（Top 10）</h2>
        <?php if (empty($topRef)): ?><p class="muted">暂无数据。</p><?php else: ?>
        <table class="mt-table"><thead><tr><th>域名</th><th>访问量</th></tr></thead><tbody>
            <?php foreach ($topRef as $r): ?><tr><td><?php echo e($r['referer_domain']); ?></td><td><?php echo (int)$r['c']; ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
    <div class="mt-card">
        <h2>浏览器分布</h2>
        <?php if (empty($topBrowser)): ?><p class="muted">暂无数据。</p><?php else: ?>
        <table class="mt-table"><thead><tr><th>浏览器</th><th>访问量</th></tr></thead><tbody>
            <?php foreach ($topBrowser as $r): ?><tr><td><?php echo e($r['browser'] ?: '未知'); ?></td><td><?php echo (int)$r['c']; ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
</div>
<?php adminFoot(); ?>
