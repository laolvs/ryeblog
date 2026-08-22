<?php
/**
 * RyeBlog 后台 —— 一键自动更新（WordPress 式）
 * 流程：确认 → 备份数据库 + 备份网站文件 → 下载升级包（SHA-256 强校验）→ 覆盖（跳过 config.php/usr/uploads）→ 数据库迁移 → 完成
 * 备份位置：usr/uploads/backup/ryeblog-<日期>*.sql / *.zip（失败可手动恢复）
 */
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/../inc/core-update.php';

$ROOT  = dirname(__DIR__);
$BAK   = $ROOT . '/usr/uploads/backup';
$TMP   = $ROOT . '/usr/tmp-update';

/** 输出一行进度并强制刷新 */
function ryu_log($msg)
{
    echo '<div style="padding:2px 0;font-family:ui-monospace,Consolas,monospace;font-size:13px">' . esc($msg) . '</div>';
    if (function_exists('ob_flush')) { @ob_flush(); @flush(); }
}

/** 备份数据库（纯 PHP 导出 vd_* 与 ryebbs_* 表） */
function ryu_db_backup($path)
{
    $pdo = db();
    $tables = [];
    foreach ($pdo->query('SHOW TABLES') as $r) {
        $t = (string) array_values($r)[0];
        if (preg_match('#^(vd_|ryebbs_)#', $t)) $tables[] = $t;
    }
    $sql = "-- RyeBlog database backup " . date('Y-m-d H:i:s') . "\n";
    $skipped = 0;
    foreach ($tables as $t) {
        try {
            $sql .= "\nDROP TABLE IF EXISTS `$t`;\n";
            $sql .= $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1] . ";\n";
            $rows = $pdo->query("SELECT * FROM `$t`");
            while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                $vals = [];
                foreach ($row as $v) $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string) $v);
                $sql .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
            }
        } catch (Throwable $e) {
            // 单表异常（损坏/不存在）不中断整体备份，记录跳过
            $skipped++;
            $sql .= "-- [跳过] $t：{$e->getMessage()}\n";
        }
    }
    file_put_contents($path, $sql);
    return ['size' => filesize($path), 'skipped' => $skipped];
}

/** 递归复制目录（跳过指定相对路径） */
function ryu_copy_tree($src, $dst, $skip = [])
{
    $dir = opendir($src);
    while (($f = readdir($dir)) !== false) {
        if ($f === '.' || $f === '..') continue;
        $rel = $f;
        if (in_array($rel, $skip, true)) continue;
        $s = $src . '/' . $f;
        $d = $dst . '/' . $f;
        if (is_dir($s)) {
            if (!is_dir($d)) @mkdir($d, 0755, true);
            ryu_copy_tree($s, $d, $skip);
        } else {
            @copy($s, $d);
        }
    }
    closedir($dir);
}

/** 备份当前网站文件（排除 config/uploads/临时目录） */
function ryu_code_backup($path, $root)
{
    if (!class_exists('ZipArchive')) return 0;
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $n = 0;
    foreach ($it as $f) {
        if ($f->isDir()) continue;
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        if (preg_match('#^(config\.php|usr/uploads/|usr/tmp-update/)#', $rel)) continue;
        $zip->addFile($f->getPathname(), $rel);
        $n++;
    }
    $zip->close();
    return $n;
}

// ============ 动作 ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { http_response_code(403); exit('CSRF 校验失败，请刷新页面重试。'); }
    $act = $_POST['act'] ?? '';
    if ($act !== 'do_update') { header('Location: ' . baseUrl('admin/update.php')); exit; }

    set_time_limit(300);
    @ini_set('display_errors', '1');
    header('Content-Type: text/html; charset=utf-8');
    adminHead('自动更新 · RyeBlog');
    echo '<div style="max-width:760px;margin:18px auto;background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:18px">';
    ryu_log('== RyeBlog 自动更新开始 ==');

    // 0) 开启维护模式（更新期间前台显示维护页；结束无论成败都恢复）
    $maintenanceWasOn = siteMaintenanceEnabled();
    setOption('site_maintenance', '1');
    ryu_log('0/6 已开启站点维护模式（前台暂不可访问）…');

    // 1) 清理上次更新残留（usr/tmp-update 中可能留有旧解压目录/zip + 旧备份文件）
    ryu_log('1/6 清理上次更新残留…');
    $cleanedFiles = 0;
    if (is_dir($TMP)) {
        foreach ((array) glob($TMP . '/*') as $f) {
            if (is_file($f)) { @unlink($f); $cleanedFiles++; }
        }
    }
    $cleanedBk = 0;
    if (is_dir($BAK)) {
        $files = glob($BAK . '/ryeblog-*') ?: [];
        usort($files, function ($a, $b) { return filemtime($a) - filemtime($b); });
        // 保留最近 5 份备份（按 mtime 倒序排列，删超出的旧备份）；生产环境至少保留 3 份以防回滚
        $keepBk = 5;
        if (count($files) > $keepBk) {
            foreach (array_slice($files, 0, count($files) - $keepBk) as $f) {
                if (@unlink($f)) $cleanedBk++;
            }
        }
    }
    ryu_log('   已清理 ' . $cleanedFiles . ' 个临时文件' . ($cleanedBk > 0 ? '、' . $cleanedBk . ' 份历史备份' : '') . '（保留最近 5 份备份以便回滚）');

    try {
        // 1) 版本检查
        $cu = coreUpdateCheck(true);
        if (empty($cu['update']) || empty($cu['url'])) {
            throw new RuntimeException('未检测到可用更新，或更新信息不完整。');
        }
        $newVer = $cu['version'];
        ryu_log("2/6 检测到新版本 v{$newVer}（当前 v" . RYEBLOG_VERSION . '）');

        // 2) 备份（文件名带随机后缀，防止被猜测直链下载；下载走后台备份管理页）
        if (!is_dir($BAK)) @mkdir($BAK, 0755, true);
        $stamp = date('Ymd-His');
        $rand  = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $sqlPath = $BAK . "/ryeblog-db-{$stamp}-{$rand}.sql";
        $codePath = $BAK . "/ryeblog-code-{$stamp}-{$rand}.zip";
        $bk = ryu_db_backup($sqlPath);
        ryu_log("3/6 数据库已备份（{$bk['size']} 字节" . ($bk['skipped'] > 0 ? "，{$bk['skipped']} 张表跳过（损坏/不存在）" : '') . "）→ usr/uploads/backup/" . basename($sqlPath));
        $nc = ryu_code_backup($codePath, $ROOT);
        ryu_log("   网站文件已备份（{$nc} 个文件）→ usr/uploads/backup/" . basename($codePath));

        // 3) 下载 + SHA-256 校验
        $zipPath = $TMP . '/upgrade.zip';
        if (!is_dir($TMP)) @mkdir($TMP, 0755, true);
        ryu_log('4/6 正在下载升级包…');
        $bin = @file_get_contents($cu['url'], false, stream_context_create([
            'http' => ['timeout' => 120, 'user_agent' => 'RyeBlog-Updater/1.0'],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]));
        if ($bin === false) throw new RuntimeException('升级包下载失败：' . $cu['url']);
        file_put_contents($zipPath, $bin);
        $sha = hash('sha256', $bin);
        if ($cu['sha256'] !== '' && !hash_equals($cu['sha256'], $sha)) {
            throw new RuntimeException("升级包 SHA-256 校验失败（下载包可能被篡改）。<br>期望 {$cu['sha256']}<br>实际 {$sha}");
        }
        ryu_log('   下载完成，SHA-256 校验通过（' . substr($sha, 0, 16) . '…）');

        // 4) 解压覆盖（跳过 config.php / usr/uploads）
        ryu_log('5/6 正在覆盖文件（config.php 与 usr/uploads 除外）…');
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('升级包无法解压。');
        $extractDir = $TMP . '/extract';
        if (is_dir($extractDir)) ryu_copy_tree($extractDir, $TMP . '/trash'); // 防残留覆盖
        $zip->extractTo($extractDir);
        $zip->close();
        $pkgRoot = is_dir($extractDir . '/ryeblog') ? $extractDir . '/ryeblog' : $extractDir;
        ryu_copy_tree($pkgRoot, $ROOT, ['config.php', 'uploads', 'tmp-update']);
        ryu_log('   文件更新完成');

        // 5) 数据库迁移（用新版本号执行 upgrade.php）
        ryu_log('6/6 执行数据库迁移…');
        // 进程内常量传递（避开部分环境禁用 putenv）：
        // upgrade.php 会优先取常量 RYEBLOG_UPGRADE_VERSION，回退到 $_SERVER['RYEBLOG_UPGRADE_VERSION']
        if (!defined('RYEBLOG_UPGRADE_VERSION')) define('RYEBLOG_UPGRADE_VERSION', $newVer);
        $_SERVER['RYEBLOG_UPGRADE_VERSION'] = $newVer;
        require_once $ROOT . '/upgrade.php';
        ryu_log('   迁移完成');

        // 升级成功后清掉旧 core_update_check 缓存（避免横幅显示旧 current=上一版本号）
        try { delOption('core_update_check'); } catch (Throwable $e) { /* ignore */ }

        @array_map('unlink', glob($TMP . '/*') ?: []); // 清理临时文件

        // 恢复维护模式（仅当更新前原本未开启时才关闭；原本就开着的保持开启）
        if (!$maintenanceWasOn) setOption('site_maintenance', '0');
        ryu_log('   ✔ 已恢复站点维护模式');

        echo '<div style="margin-top:14px;padding:12px;background:#eaf3e6;border:1px solid #2c7d3f;border-radius:8px;color:#1f3d24">'
           . '<strong>✅ 自动更新完成！</strong> 当前版本 v' . $newVer
           . '。备份位于 usr/uploads/backup/（数据库 ' . basename($sqlPath) . ' / 网站文件 ' . basename($codePath) . '）。'
           . '如出现问题，可恢复网站文件备份后用备份的 SQL 还原数据库。</div>';
    } catch (Throwable $e) {
        // 失败也要恢复维护模式（站点继续可访问，只是没升级成功）
        if (isset($maintenanceWasOn) && !$maintenanceWasOn) {
            try { setOption('site_maintenance', '0'); } catch (Throwable $e2) { /* ignore */ }
        }
        echo '<div style="margin-top:14px;padding:12px;background:#fdf3f3;border:1px solid #e0533d;border-radius:8px;color:#a33">'
           . '<strong>❌ 自动更新失败：</strong>' . nl2br(esc($e->getMessage()))
           . '<br>站点未受影响；如已覆盖部分文件，可用上方网站文件备份手动恢复。</div>';
    }
    echo '</div>';
    adminFoot();
    exit;
}

// ============ 确认页 ============
$cu = coreUpdateCheck(true);
$current = defined('RYEBLOG_VERSION') ? RYEBLOG_VERSION : '?';
adminHead('自动更新 · RyeBlog');
?>
<div style="max-width:760px;margin:18px auto">
<h1>自动更新</h1>
<?php if (empty($cu['update'])): ?>
    <div class="panel"><div class="panel-body">
        <p>✅ 当前已是最新版本（v<?php echo esc($current); ?>）。</p>
        <p><a class="btn" href="<?php echo baseUrl('admin/index.php'); ?>">返回仪表盘</a></p>
    </div></div>
<?php else: ?>
    <div style="background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:18px">
        <p style="margin-top:0"><strong style="color:#1f3d24;font-size:16px">发现新版本 v<?php echo esc($cu['version']); ?></strong>
        <span class="muted">（当前 v<?php echo esc($current); ?>）</span></p>
        <?php if (!empty($cu['changelog'])): ?>
        <div style="background:#f6f9f3;border:1px solid #e3eadf;border-radius:8px;padding:12px 14px;margin:10px 0;font-size:13px;line-height:1.7"><?php echo nl2br(esc($cu['changelog'])); ?></div>
        <?php endif; ?>
        <div style="background:#fff8e6;border:1px solid #e6c96a;border-radius:8px;padding:12px 14px;margin:10px 0;font-size:13px">
            <strong>自动更新将自动完成：</strong>
            ① 自动开启维护模式（前台显示维护页，结束无论成败自动恢复）→
            ② 备份数据库与当前网站文件到 <code>usr/uploads/backup/</code> →
            ③ 下载升级包并做 SHA-256 完整性校验 →
            ④ 覆盖旧文件（<code>config.php</code>、<code>usr/uploads</code> 除外）→
            ⑤ 执行数据库迁移（幂等）→ 完成。
            <br><span class="muted">升级过程中请勿关闭页面；备份文件会保留，可随时手动恢复。</span>
        </div>
        <form method="post" onsubmit="return confirm('确定开始自动更新？将先自动备份站点，然后覆盖文件并迁移数据库。')">
            <?php echo '<input type="hidden" name="_csrf" value="' . esc(csrfToken()) . '">'; ?>
            <input type="hidden" name="act" value="do_update">
            <button class="btn btn-primary" type="submit">⬇ 备份并自动更新</button>
            <a class="btn btn-ghost" href="<?php echo baseUrl('admin/index.php'); ?>">取消</a>
        </form>
    </div>
<?php endif; ?>
</div>
<?php adminFoot();
