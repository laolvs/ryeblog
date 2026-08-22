<?php
/**
 * RyeBlog 后台 —— 备份管理
 * 列出 usr/uploads/backup/ 下的数据库 / 网站文件备份，支持下载与删除。
 * 安全：备份目录已被 nginx 禁止 Web 直接访问，仅本站后台管理员可经 PHP 流下载，防止数据库泄露。
 */
require_once __DIR__ . '/admin.php';

$BAK = RYEBLOG_ROOT . '/usr/uploads/backup';

/** 备份文件名白名单（防路径穿越 / 任意文件下载）：仅 ryeblog-db-* / ryeblog-code-* / verda_en_* */
function backupNameOk($name)
{
    return (bool)preg_match('#^(ryeblog-(db|code)-[\w.-]+\.(sql|zip)|ryeblog-export-[\w.-]+\.(sql|xml)|verda_en_\d{8}_\d{6}\.sql)$#', $name);
}

// ---- 删除（GET + CSRF，与评论/回收站一致） ----
if (isset($_GET['delete'])) {
    if (!isset($_GET['_csrf']) || !hash_equals($_SESSION['rye_csrf'] ?? '', (string)$_GET['_csrf'])) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试。');
    }
    $name = basename((string)$_GET['delete']);
    if (!backupNameOk($name)) { http_response_code(400); exit('非法文件名。'); }
    $path = $BAK . '/' . $name;
    if (is_file($path)) @unlink($path);
    header('Location: ' . baseUrl('admin/backups.php'));
    exit;
}

// ---- 下载（仅管理员；PHP 流输出，不暴露真实路径） ----
if (isset($_GET['download'])) {
    $name = basename((string)$_GET['download']);
    if (!backupNameOk($name)) { http_response_code(400); exit('非法文件名。'); }
    $path = $BAK . '/' . $name;
    if (!is_file($path)) { http_response_code(404); exit('备份文件不存在。'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

// ---- 列表 ----
$files = [];
if (is_dir($BAK)) {
    foreach (glob($BAK . '/*') ?: [] as $p) {
        if (!is_file($p)) continue;
        $n = basename($p);
        if (!backupNameOk($n)) continue;
        $files[] = ['name' => $n, 'size' => filesize($p), 'mtime' => filemtime($p)];
    }
}
usort($files, function ($a, $b) { return $b['mtime'] - $a['mtime']; });

function backupTypeLabel($n)
{
    if (strpos($n, 'ryeblog-db-') === 0) return '🗄️ 数据库备份';
    if (strpos($n, 'ryeblog-code-') === 0) return '📦 网站文件备份';
    if (strpos($n, 'ryeblog-export-') === 0) return '🗃️ 数据导出';
    if (strpos($n, 'verda_en_') === 0) return '🌐 英文数据备份';
    return '备份';
}

function backupFileSize($bytes)
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

adminHead(__('备份管理'), 'backups.php');
?>
<div style="max-width:960px;margin:18px auto">
<h1>🗄️ <?php echo __('备份管理'); ?></h1>
<p class="muted" style="margin-top:6px">
    <?php echo __('自动更新前会自动备份数据库与网站文件到'); ?> <code>usr/uploads/backup/</code>，
    <?php echo __('自动保留最近 5 份。备份目录已禁止 Web 直接访问，仅此处可下载 / 删除（防止数据库泄露）。'); ?>
</p>
<?php if (empty($files)): ?>
    <div class="panel"><div class="panel-body">
        <p><?php echo __('暂无备份。执行一次「自动更新」后会自动生成数据库与网站文件备份。'); ?></p>
    </div></div>
<?php else: ?>
    <div class="panel">
        <?php foreach ($files as $f):
            $dlUrl  = baseUrl('admin/backups.php?download=' . rawurlencode($f['name']));
            $delUrl = baseUrl('admin/backups.php?delete=' . rawurlencode($f['name']) . '&_csrf=' . csrfToken());
        ?>
        <div style="padding:12px 14px;border-bottom:1px solid #eef2ec">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div style="min-width:0">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span class="tag" style="white-space:nowrap"><?php echo backupTypeLabel($f['name']); ?></span>
                        <strong style="word-break:break-all;font-size:13px"><?php echo esc($f['name']); ?></strong>
                    </div>
                    <div class="muted" style="font-size:12px;margin-top:4px">
                        <?php echo backupFileSize($f['size']); ?> · <?php echo date('Y-m-d H:i:s', $f['mtime']); ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-shrink:0">
                    <a class="btn btn-sm" href="<?php echo esc($dlUrl); ?>">⬇ <?php echo __('下载'); ?></a>
                    <a class="btn btn-danger btn-sm" href="<?php echo esc($delUrl); ?>"
                       onclick="return confirm('<?php echo __('确定删除该备份？删除后不可恢复。'); ?>')"><?php echo __('删除'); ?></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
<?php adminFoot();
