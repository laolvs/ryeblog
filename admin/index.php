<?php
/**
 * RyeBlog 后台仪表盘
 */
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/../inc/core-update.php';
require_once __DIR__ . '/../inc/markdown.php';
require_once __DIR__ . '/../inc/cloud.php';

$stats = [
    'posts'    => dbOne("SELECT COUNT(*) AS c FROM vd_posts WHERE type='post'")['c'],
    'pages'    => dbOne("SELECT COUNT(*) AS c FROM vd_posts WHERE type='page'")['c'],
    'drafts'   => dbOne("SELECT COUNT(*) AS c FROM vd_posts WHERE status='draft'")['c'],
    'cats'     => dbOne("SELECT COUNT(*) AS c FROM vd_categories")['c'],
    'comments' => dbOne("SELECT COUNT(*) AS c FROM vd_comments")['c'],
    'pending'  => dbOne("SELECT COUNT(*) AS c FROM vd_comments WHERE status='pending'")['c'],
];

$recent = dbAll("SELECT id, title, created_at, status FROM vd_posts ORDER BY created_at DESC LIMIT 5");

// 核心更新检查（静默失败，不阻塞仪表盘）
$coreUpdate = null;
try {
    $coreUpdate = coreUpdateCheck(isset($_GET['check_update']) && $_GET['check_update'] === '1');
} catch (Throwable $e) {
    $coreUpdate = null;
}

// 云端插件/主题更新汇总（用于仪表盘顶部横幅）
$cloudSummary = ['plugins' => [], 'themes' => [], 'enabled' => cloudEnabled()];
try {
    $mf = cloudFetchManifest(false);
    if ($mf['ok']) {
        foreach (($mf['data']['plugins'] ?? []) as $cp) {
            if (cloudStatus('plugin', $cp) === 'update-available') {
                $cloudSummary['plugins'][] = ['name' => $cp['name'], 'title' => $cp['title'] ?? $cp['name'], 'cloud' => $cp['version'] ?? '?', 'local' => cloudLocalVersion('plugin', $cp['name'])];
            }
        }
        foreach (($mf['data']['themes'] ?? []) as $cp) {
            if (cloudStatus('theme', $cp) === 'update-available') {
                $cloudSummary['themes'][] = ['name' => $cp['name'], 'title' => $cp['title'] ?? $cp['name'], 'cloud' => $cp['version'] ?? '?', 'local' => cloudLocalVersion('theme', $cp['name'])];
            }
        }
    }
} catch (Throwable $e) {
    /* ignore */
}

adminHead(__('仪表盘'), 'index.php');
?>
<h1><?php echo __('仪表盘'); ?>
    <span style="font-size:13px;font-weight:400;color:#6b7a6f;background:#eaf3e6;border:1px solid #c8dbc2;border-radius:10px;padding:3px 10px;margin-left:8px;vertical-align:middle"><?php echo __('当前版本'); ?>: <strong style="color:#1f3d24">v<?php echo esc(RYEBLOG_VERSION); ?></strong></span>
</h1>

<?php if ($coreUpdate && !empty($coreUpdate['update'])): ?>
<div style="border:1px solid #2c7d3f;background:#f0f8ee;border-radius:10px;padding:12px 16px;margin:0 0 16px;display:flex;gap:12px;align-items:flex-start">
    <div style="font-size:22px;line-height:1">🎉</div>
    <div style="flex:1">
        <div style="font-weight:700;color:#1f3d24;font-size:15px"><?php echo __('发现新版本'); ?> v<?php echo esc($coreUpdate['version']); ?>
            <span style="font-weight:400;color:#5d6b61;font-size:13px"><?php echo __('（当前'); ?> v<?php echo esc($coreUpdate['current']); ?>）</span>
        </div>
        <?php if (!empty($coreUpdate['changelog'])): ?>
        <div class="md-changelog" style="margin:8px 0;color:#3a4a3e;font-size:13px;line-height:1.55;max-height:160px;overflow:auto;border-left:3px solid #c8dbc2;padding-left:10px"><?php echo markdownToHtml($coreUpdate['changelog']); ?></div>
        <?php endif; ?>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
            <?php if (!empty($coreUpdate['url'])): ?>
            <a class="btn btn-primary btn-sm" href="<?php echo esc($coreUpdate['url']); ?>" download>⬇ <?php echo __('下载升级包'); ?></a>
            <?php endif; ?>
            <a class="btn btn-ghost btn-sm" href="https://ryeblog.com/category/updates" target="_blank"><?php echo __('查看更新记录'); ?></a>
            <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/update.php'); ?>">⚙ <?php echo __('一键自动更新'); ?></a>
            <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/index.php?check_update=1'); ?>"><?php echo __('重新检查'); ?></a>
        </div>
    </div>
</div>
<?php elseif ($coreUpdate && isset($_GET['check_update']) && $_GET['check_update'] === '1'): ?>
<div style="border:1px solid #cfd9c8;background:#f6f9f3;border-radius:10px;padding:10px 14px;margin:0 0 16px;color:#2c5234;font-size:13px">✅ <?php echo __('已是最新版本'); ?>（v<?php echo esc($coreUpdate['current'] ?? RYEBLOG_VERSION); ?>）</div>
<?php endif; ?>

<?php if (!empty($cloudSummary['plugins']) || !empty($cloudSummary['themes'])): ?>
<div style="border:1px solid #f0c674;background:#fff8e6;border-radius:10px;padding:12px 16px;margin:0 0 16px;display:flex;gap:12px;align-items:flex-start">
    <div style="font-size:22px;line-height:1">📦</div>
    <div style="flex:1">
        <div style="font-weight:700;color:#7a5c0a;font-size:15px"><?php echo __('云端扩展有更新'); ?></div>
        <div style="margin:6px 0;color:#5d4a1f;font-size:13px;line-height:1.6">
            <?php if (!empty($cloudSummary['plugins'])): ?>
                <?php echo __('插件'); ?>：<?php foreach ($cloudSummary['plugins'] as $i => $p): ?><strong><?php echo esc($p['title']); ?></strong> (v<?php echo esc($p['local']); ?> → v<?php echo esc($p['cloud']); ?>)<?php echo $i < count($cloudSummary['plugins']) - 1 || !empty($cloudSummary['themes']) ? '、' : ''; ?><?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($cloudSummary['themes'])): ?>
                <?php if (!empty($cloudSummary['plugins'])) echo '<br>'; ?>
                <?php echo __('主题'); ?>：<?php foreach ($cloudSummary['themes'] as $i => $t): ?><strong><?php echo esc($t['title']); ?></strong> (v<?php echo esc($t['local']); ?> → v<?php echo esc($t['cloud']); ?>)<?php echo $i < count($cloudSummary['themes']) - 1 ? '、' : ''; ?><?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
            <?php if (!empty($cloudSummary['plugins'])): ?><a class="btn btn-primary btn-sm" href="<?php echo baseUrl('admin/plugins.php'); ?>"><?php echo __('去更新插件'); ?></a><?php endif; ?>
            <?php if (!empty($cloudSummary['themes'])): ?><a class="btn btn-primary btn-sm" href="<?php echo baseUrl('admin/themes.php'); ?>"><?php echo __('去更新主题'); ?></a><?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="doc-banner">
    <span class="doc-banner-tag">📌 <?php echo __('重要文档'); ?></span>
    <span class="doc-banner-text"><?php echo __('使用前请阅读：'); ?></span>
    <a class="btn btn-sm" href="<?php echo baseUrl('docs.php?doc=HELP'); ?>" target="_blank">📖 <?php echo __('帮助文档'); ?></a>
    <a class="btn btn-sm btn-amber" href="<?php echo baseUrl('docs.php?doc=LICENSE'); ?>" target="_blank">⚖️ <?php echo __('授权协议'); ?></a>
</div>

<div class="cards">
    <div class="stat"><div class="n"><?php echo $stats['posts']; ?></div><div class="l"><?php echo __('文章'); ?></div></div>
    <div class="stat"><div class="n"><?php echo $stats['pages']; ?></div><div class="l"><?php echo __('独立页面'); ?></div></div>
    <div class="stat"><div class="n"><?php echo $stats['drafts']; ?></div><div class="l"><?php echo __('草稿'); ?></div></div>
    <div class="stat"><div class="n"><?php echo $stats['cats']; ?></div><div class="l"><?php echo __('分类'); ?></div></div>
    <div class="stat"><div class="n"><?php echo $stats['comments']; ?></div><div class="l"><?php echo __('评论'); ?></div></div>
    <div class="stat"><div class="n"><?php echo $stats['pending']; ?></div><div class="l"><?php echo __('待审评论'); ?></div></div>
</div>

<div class="panel" style="margin-top:22px">
    <h2><?php echo __('最近内容'); ?></h2>
    <table class="data">
        <tr><th><?php echo __('标题'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('发布时间'); ?></th></tr>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><a href="<?php echo baseUrl('admin/write.php?id=' . $r['id']); ?>"><?php echo esc($r['title']); ?></a></td>
                <td><span class="tag"><?php echo $r['status'] === 'draft' ? __('草稿') : __('已发布'); ?></span></td>
                <td class="muted"><?php echo formatDate($r['created_at'], 'Y-m-d H:i'); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php adminFoot();
