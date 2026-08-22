<?php
/**
 * RyeBlog 后台 —— 站点设置 · 高级设置
 * 维护模式、伪静态、内置配色、云端市场、设置备份导出/导入
 */
require_once __DIR__ . '/admin.php';

$ok = $err = '';

// ---- 站点设置备份：导出（直接下载 JSON，不落盘） ----
if (($_GET['action'] ?? '') === 'export_options' && checkCsrf()) {
    $rows = dbAll('SELECT name, value FROM vd_options');
    $data = [];
    foreach ($rows as $r) $data[$r['name']] = $r['value'];
    $data['_exported_at'] = date('Y-m-d H:i:s');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="ryeblog-settings-' . date('Ymd_His') . '.json"');
    echo $json;
    exit;
}
// ---- 站点设置备份：导入恢复 ----
if (($_POST['action'] ?? '') === 'import_options') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } elseif (empty($_FILES['backup_file']['tmp_name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
        $err = __('请选择要导入的备份文件（.json）。');
    } else {
        $raw = file_get_contents($_FILES['backup_file']['tmp_name']);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $err = __('备份文件无效：无法解析为 JSON。');
        } else {
            $n = 0;
            foreach ($data as $k => $v) {
                $k = preg_replace('/[^a-zA-Z0-9_.-]/', '', $k);
                if ($k === '' || strpos($k, '_') === 0 || !is_scalar($v)) continue;
                setOption($k, (string)$v);
                $n++;
            }
            $ok = sprintf(__('已从备份恢复 %d 项设置。'), $n);
        }
    }
}

// ---- 性能优化：重建归档计数（物化表校准） ----
if (($_POST['action'] ?? '') === 'rebuild_stats' && checkCsrf()) {
    $t0 = microtime(true);
    rebuildArchiveStats();
    $ok = sprintf(__('归档月计数已重建（%.1f 秒）。'), microtime(true) - $t0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['action'])) {
    if (!checkCsrf()) { http_response_code(403); exit('CSRF 校验失败'); }
    $keys = ['theme_style','pretty_url','pretty_mode'];
    foreach ($keys as $k) if (isset($_POST[$k])) setOption($k, trim($_POST[$k]));
    setOption('site_maintenance', !empty($_POST['site_maintenance']) ? '1' : '0');
    setOption('maintenance_message', trim($_POST['maintenance_message'] ?? ''));
    setOption('cloud_enabled', !empty($_POST['cloud_enabled']) ? '1' : '0');
    if (isset($_POST['cloud_repo_url'])) setOption('cloud_repo_url', trim($_POST['cloud_repo_url']));
    // ---- 性能开关（P1/P2）----
    setOption('page_cache', !empty($_POST['page_cache']) ? '1' : '0');
    setOption('page_cache_ttl', max(10, (int)($_POST['page_cache_ttl'] ?? 60)));
    setOption('page_cache_redis', !empty($_POST['page_cache_redis']) ? '1' : '0');
    $persist = !empty($_POST['db_persistent']) ? '1' : '0';
    setOption('db_persistent', $persist);
    // db() 建连前无法查库，持久连接开关写文件标志供 db() 读取
    @file_put_contents(RYEBLOG_ROOT . '/usr/cache/db_persistent.txt', $persist);
    $ok = __('已保存。');
}
function optVal($k, $d = '') { return esc(getOption($k, $d)); }
$pm = getOption('pretty_mode','slug');
adminHead(__('高级设置'), 'settings-advanced.php');
?>
<h1>⚙️ <?php echo __('高级设置'); ?></h1>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>

<form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <h3 style="margin:0 0 10px;color:var(--g-700)">🛠 <?php echo __('站点维护'); ?></h3>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="site_maintenance" value="1" <?php echo optVal('site_maintenance','0')==='1'?'checked':''; ?>>
        <span><?php echo __('开启维护模式'); ?><span class="muted" style="font-weight:400">（<?php echo __('前台显示维护页，后台不受影响；在线更新时会自动临时开启并恢复'); ?>）</span></span>
    </label>
    <label><?php echo __('维护提示文案（可选）'); ?></label>
    <input type="text" name="maintenance_message" value="<?php echo esc(optVal('maintenance_message','')); ?>" placeholder="<?php echo __('默认：站点正在维护升级中，请稍后再来访问。'); ?>">

    <h3 style="margin:18px 0 10px;color:var(--g-700)"><?php echo __('内置主题配色（未启用自定义主题时生效）'); ?></h3>
    <select name="theme_style">
        <option value="fresh" <?php echo getOption('theme_style','fresh')==='fresh'?'selected':''; ?>><?php echo __('清新绿（默认）'); ?></option>
        <option value="forest" <?php echo getOption('theme_style','fresh')==='forest'?'selected':''; ?>><?php echo __('深林绿（浓郁深绿）'); ?></option>
        <option value="mint" <?php echo getOption('theme_style','fresh')==='mint'?'selected':''; ?>><?php echo __('薄荷绿（清浅青绿）'); ?></option>
    </select>
    <p class="muted" style="margin-top:6px;font-size:.85rem"><?php echo __('自定义主题（外观 → 主题管理中激活）与本站设置互不影响。'); ?></p>

    <h3 style="margin:18px 0 10px;color:var(--g-700)"><?php echo __('伪静态（URL 重写）'); ?></h3>
    <label><?php echo __('是否开启伪静态'); ?></label>
    <select name="pretty_url">
        <option value="1" <?php echo getOption('pretty_url','1')==='1'?'selected':''; ?>><?php echo __('开启'); ?></option>
        <option value="0" <?php echo getOption('pretty_url','1')==='0'?'selected':''; ?>><?php echo __('关闭（使用 ?p= 查询参数）'); ?></option>
    </select>
    <label><?php echo __('URL 规则模式'); ?></label>
    <select name="pretty_mode">
        <option value="slug"    <?php echo $pm==='slug'?'selected':''; ?>><?php echo __('文章 /post/{slug} · 页面 /page/{slug}'); ?></option>
        <option value="html"    <?php echo $pm==='html'?'selected':''; ?>><?php echo __('直接 /{slug}.html'); ?></option>
        <option value="archive" <?php echo $pm==='archive'?'selected':''; ?>><?php echo __('文章 /archives/{id}.html · 页面 /page/{slug}'); ?></option>
    </select>
    <p class="muted" style="margin-top:8px"><?php echo __('开启伪静态后，请确保服务器已配置重写规则。参考规则：'); ?></p>
    <?php $sb = siteBase(); $rb = $sb !== '' ? "RewriteBase $sb/" : ''; ?>
    <label>Apache (.htaccess)</label>
    <textarea readonly rows="6" style="width:100%;font-family:monospace;font-size:12px"><?php echo esc("<IfModule mod_rewrite.c>\n  RewriteEngine On\n  $rb\n  RewriteRule ^sitemap\.xml$ sitemap.php [L]\n  RewriteRule ^post/([^/]+)/?$ post.php?slug=\$1 [L,QSA]\n  RewriteCond %{REQUEST_FILENAME} !-f\n  RewriteRule ^([^.]+)\.html$ post.php?slug=\$1 [L,QSA]\n  RewriteRule ^category/([^/]+)/?$ category.php?c=\$1 [L,QSA]\n  RewriteRule ^tag/([^/]+)/?$ tag.php?t=\$1 [L,QSA]\n  RewriteRule ^page/([^/]+)/?$ page.php?p=\$1 [L,QSA]\n</IfModule>"); ?></textarea>

    <h3 style="margin:18px 0 10px;color:var(--g-700)">☁️ <?php echo __('云端市场（插件/主题在线安装更新）'); ?></h3>
    <label><?php echo __('启用云端市场'); ?></label>
    <select name="cloud_enabled">
        <option value="1" <?php echo getOption('cloud_enabled','1')==='1'?'selected':''; ?>><?php echo __('开启（推荐）'); ?></option>
        <option value="0" <?php echo getOption('cloud_enabled','1')==='0'?'selected':''; ?>><?php echo __('关闭'); ?></option>
    </select>
    <label><?php echo __('云端仓库地址（manifest.json）'); ?></label>
    <input type="text" name="cloud_repo_url" value="<?php echo optVal('cloud_repo_url','https://ryeblog.com/cloud/repo.json'); ?>" placeholder="https://ryeblog.com/cloud/repo.json">
    <p class="muted" style="margin-top:6px"><?php echo __('默认 RyeBlog 官方仓库；可填自建仓库地址（静态 JSON 即可）。'); ?></p>

    <h3 style="margin:18px 0 10px;color:var(--g-700)">🚀 <?php echo __('性能优化（大数据量站点推荐开启）'); ?></h3>
    <label><?php echo __('整页缓存（游客页面）'); ?></label>
    <select name="page_cache">
        <option value="1" <?php echo getOption('page_cache','0')==='1'?'selected':''; ?>><?php echo __('开启'); ?></option>
        <option value="0" <?php echo getOption('page_cache','0')==='0'?'selected':''; ?>><?php echo __('关闭（默认）'); ?></option>
    </select>
    <p class="muted" style="margin-top:6px;font-size:.85rem"><?php echo __('首页/列表/归档/搜索/标签页/feed/sitemap 整体缓存，命中时不再执行 PHP 与数据库查询；登录用户、带评论 Cookie 的访客自动跳过。'); ?></p>
    <label><?php echo __('整页缓存时长（秒）'); ?></label>
    <input type="number" name="page_cache_ttl" min="10" max="86400" value="<?php echo optVal('page_cache_ttl','60'); ?>">
    <label><?php echo __('整页缓存 Redis 后端（需 PHP redis 扩展，未启用则回退文件缓存）'); ?></label>
    <select name="page_cache_redis">
        <option value="1" <?php echo getOption('page_cache_redis','0')==='1'?'selected':''; ?>><?php echo __('Redis'); ?></option>
        <option value="0" <?php echo getOption('page_cache_redis','0')==='0'?'selected':''; ?>><?php echo __('文件（默认）'); ?></option>
    </select>
    <label><?php echo __('数据库持久连接（php-fpm 复用连接，减少握手；异常环境请保持关闭）'); ?></label>
    <select name="db_persistent">
        <option value="1" <?php echo getOption('db_persistent','0')==='1'?'selected':''; ?>><?php echo __('开启'); ?></option>
        <option value="0" <?php echo getOption('db_persistent','0')==='0'?'selected':''; ?>><?php echo __('关闭（默认）'); ?></option>
    </select>

    <p style="margin-top:16px"><button class="btn" type="submit"><?php echo __('保存设置'); ?></button></p>
</form>

<div class="panel" style="margin-top:18px">
    <h3 style="margin:0 0 10px;color:var(--g-700)">📊 <?php echo __('归档计数维护'); ?></h3>
    <p class="muted" style="font-size:.85rem;margin:0 0 12px"><?php echo __('归档按月的文章数存于物化计数表（读 O(1)）。发布/删除文章会自动维护；如用导入工具等绕过后台写入，可手动重建校准。'); ?></p>
    <form method="post" style="display:flex;gap:10px;align-items:center">
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="rebuild_stats">
        <button class="btn btn-ghost" type="submit">🔄 <?php echo __('重建归档计数'); ?></button>
    </form>
</div>

<div class="panel" style="margin-top:18px">
    <h3 style="margin:0 0 10px;color:var(--g-700)">💾 <?php echo __('站点设置备份'); ?></h3>
    <p class="muted" style="font-size:.85rem;margin:0 0 12px"><?php echo __('备份/恢复全部站点选项：主题与配色、Hero 宣传区、特性卡片、Footer、SEO、统计代码等。建议每次调整风格前先导出一次。'); ?></p>
    <div class="upload-row" style="align-items:center">
        <a class="btn" href="settings-advanced.php?action=export_options&_csrf=<?php echo csrfToken(); ?>">⬇️ <?php echo __('导出设置备份'); ?></a>
        <span style="color:var(--muted);font-size:.85rem"><?php echo __('（下载 JSON 文件到本地）'); ?></span>
    </div>
    <form method="post" enctype="multipart/form-data" style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="import_options">
        <input type="file" name="backup_file" accept=".json,application/json" required>
        <button class="btn btn-ghost" type="submit">⬆️ <?php echo __('导入备份并恢复'); ?></button>
        <span style="color:var(--muted);font-size:.85rem"><?php echo __('（将覆盖当前全部设置）'); ?></span>
    </form>
    <?php
    $bkDir = RYEBLOG_ROOT . '/usr/uploads/backup';
    $bks = is_dir($bkDir) ? glob($bkDir . '/options-*.json') : [];
    if ($bks): rsort($bks); ?>
    <p style="margin:14px 0 4px;font-size:.85rem;color:var(--muted)"><?php echo __('服务器上的设置备份'); ?>（<?php echo count($bks); ?>）：</p>
    <ul style="margin:0;padding-left:18px;font-size:.85rem">
        <?php foreach (array_slice($bks, 0, 8) as $b): ?>
        <li><?php echo esc(basename($b)); ?> <span style="color:var(--muted)">(<?php echo number_format(filesize($b)); ?> B)</span></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<style>.upload-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }</style>
<?php adminFoot();