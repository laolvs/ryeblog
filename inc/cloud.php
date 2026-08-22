<?php
/**
 * RyeBlog —— 云端市场客户端（插件/主题在线安装、更新、回滚）
 *
 * 云端仓库规范见 docs/cloud-marketplace-design.md：
 *   repo.json 列出插件/主题（name/title/version/desc/download/sha256/min_core/changelog）
 *   下载包为 ZIP，内含顶层目录（插件名/主题名）
 *
 * 设置项：cloud_repo_url（仓库 manifest 地址）、cloud_enabled、cloud_cache
 */

/** 云端功能开关 */
function cloudEnabled()
{
    return getOption('cloud_enabled', '1') === '1';
}

/** 云端仓库 manifest 地址 */
function cloudRepoUrl()
{
    return getOption('cloud_repo_url', 'https://ryeblog.com/cloud/repo.json');
}

/**
 * 拉取并缓存云端 manifest。
 * @return array|string ['ok'=>true,'data'=>...] 或 ['ok'=>false,'msg'=>...]
 */
function cloudFetchManifest($force = false)
{
    if (!cloudEnabled()) return ['ok' => false, 'msg' => '云端市场未启用，请在站点设置中开启。'];
    $url = cloudRepoUrl();
    if ($url === '') return ['ok' => false, 'msg' => '云端仓库地址未配置。'];

    $cacheKey = 'cloud_manifest_' . md5($url);
    $ttl = max(60, (int)getOption('cloud_cache', '3600'));
    if (!$force) {
        $cached = getOption($cacheKey, '');
        if ($cached !== '') {
            $d = json_decode($cached, true);
            if (is_array($d) && isset($d['plugins']) && isset($d['updated'])) return ['ok' => true, 'data' => $d, 'cached' => true];
        }
    }

    $body = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 15, 'user_agent' => 'RyeBlog-Cloud/1.0'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]));
    if ($body === false) return ['ok' => false, 'msg' => '无法连接云端仓库（' . $url . '），请检查网络或稍后重试。'];

    $d = json_decode($body, true);
    if (!is_array($d) || !isset($d['plugins']) || !is_array($d['plugins'])) {
        return ['ok' => false, 'msg' => '云端仓库数据格式不正确（缺少 plugins 列表）。'];
    }
    if (!isset($d['themes']) || !is_array($d['themes'])) $d['themes'] = [];
    // 缓存（用 refresh 参数强制刷新 getOption 缓存）
    setOption($cacheKey, json_encode($d, JSON_UNESCAPED_UNICODE));
    return ['ok' => true, 'data' => $d, 'cached' => false];
}

/** 本地版本号：插件读 Plugin.php @Version；主题读 theme.css @Version（无则按 1.0.0） */
function cloudLocalVersion($type, $name)
{
    if ($type === 'plugin') {
        $f = RYEBLOG_ROOT . '/usr/plugins/' . $name . '/Plugin.php';
        if (is_file($f) && preg_match('/@Version\s+([0-9.]+)/', file_get_contents($f), $m)) return $m[1];
        return '';
    }
    $f = RYEBLOG_ROOT . '/usr/theme/' . $name . '/theme.css';
    if (is_file($f)) {
        if (preg_match('/@Version\s+([0-9.]+)/', file_get_contents($f), $m)) return $m[1];
        return '1.0.0'; // 已安装但未声明版本
    }
    return '';
}

/**
 * 本地状态：installed-up-to-date / update-available / not-installed / incompatible
 */
function cloudStatus($type, $pkg)
{
    $local = cloudLocalVersion($type, $pkg['name'] ?? '');
    $cloud = $pkg['version'] ?? '';
    if ($local === '') return 'not-installed';
    if ($cloud === '') return 'installed-up-to-date';
    return version_compare($cloud, $local, '>') ? 'update-available' : 'installed-up-to-date';
}

/**
 * 下载 ZIP 并校验 SHA-256。
 * @return array|string ['ok'=>true,'file'=>tmp] 或 ['ok'=>false,'msg'=>...]
 */
function cloudDownload($pkg)
{
    $url = $pkg['download'] ?? '';
    $expect = strtolower($pkg['sha256'] ?? '');
    if ($url === '') return ['ok' => false, 'msg' => '缺少下载地址。'];
    $tmp = tempnam(sys_get_temp_dir(), 'ryecloud');
    if ($tmp === false) return ['ok' => false, 'msg' => '无法创建临时目录。'];

    $body = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 120, 'user_agent' => 'RyeBlog-Cloud/1.0'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]));
    if ($body === false || $body === '') {
        @unlink($tmp);
        return ['ok' => false, 'msg' => '下载失败（' . $url . '）。'];
    }
    file_put_contents($tmp, $body);

    if ($expect !== '') {
        $actual = hash_file('sha256', $tmp);
        if ($actual !== $expect) {
            @unlink($tmp);
            return ['ok' => false, 'msg' => '文件校验失败（SHA-256 不符），已拒绝安装——包可能被篡改或下载不完整。'];
        }
    }
    return ['ok' => true, 'file' => $tmp];
}

/** 插件/主题是否已本地存在 */
function cloudIsInstalled($type, $name)
{
    $dir = $type === 'plugin' ? (RYEBLOG_ROOT . '/usr/plugins/' . $name) : (RYEBLOG_ROOT . '/usr/theme/' . $name);
    return is_dir($dir);
}

/**
 * 安装（新装）：解压 + 合法性校验。
 * @return array|string ['ok'=>true,'dir'=>...] 或 ['ok'=>false,'msg'=>...]
 */
function cloudInstall($type, $pkg)
{
    $name = $pkg['name'] ?? '';
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $name)) return ['ok' => false, 'msg' => '非法名称。'];
    if (cloudIsInstalled($type, $name)) return ['ok' => false, 'msg' => "「$name」已安装，请使用「更新」。"];

    $dl = cloudDownload($pkg);
    if (!$dl['ok']) return $dl;
    $dest = $type === 'plugin' ? (RYEBLOG_ROOT . '/usr/plugins') : (RYEBLOG_ROOT . '/usr/theme');
    $r = extractZip($dl['file'], $dest);
    @unlink($dl['file']);
    if (!$r['ok']) return ['ok' => false, 'msg' => '解压失败：' . ($r['msg'] ?? '未知错误')];

    $dir = $r['dir'];
    if ($type === 'plugin' && !is_file($dest . '/' . $dir . '/Plugin.php')) {
        // 解压出的顶层目录名与包名不一致时，尝试重命名
        if (is_dir($dest . '/' . $name) && is_file($dest . '/' . $name . '/Plugin.php')) $dir = $name;
        else { @rrmdir($dest . '/' . $dir); return ['ok' => false, 'msg' => 'ZIP 中未找到 Plugin.php，不是有效的插件包。']; }
    }
    if ($type === 'theme' && !is_file($dest . '/' . $dir . '/theme.css')) {
        if (is_dir($dest . '/' . $name) && is_file($dest . '/' . $name . '/theme.css')) $dir = $name;
        else { @rrmdir($dest . '/' . $dir); return ['ok' => false, 'msg' => 'ZIP 中未找到 theme.css，不是有效的主题包。']; }
    }
    // 目录名规范化（与包名一致，避免歧义）
    if ($dir !== $name && is_dir($dest . '/' . $dir) && !is_dir($dest . '/' . $name)) {
        rename($dest . '/' . $dir, $dest . '/' . $name);
        $dir = $name;
    }
    return ['ok' => true, 'dir' => $dir, 'msg' => ($type === 'plugin' ? '插件' : '主题') . "「$name」安装成功。"];
}

/**
 * 更新：备份旧目录 → 覆盖安装 → 失败自动回滚。
 * @return array ['ok'=>bool, 'msg'=>string, 'backup'=>string|'']
 */
function cloudUpdate($type, $pkg)
{
    $name = $pkg['name'] ?? '';
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $name)) return ['ok' => false, 'msg' => '非法名称。'];
    $dest = $type === 'plugin' ? (RYEBLOG_ROOT . '/usr/plugins') : (RYEBLOG_ROOT . '/usr/theme');
    $curDir = $dest . '/' . $name;
    if (!is_dir($curDir)) return ['ok' => false, 'msg' => "「$name」未安装，请使用「安装」。"];

    // 1) 备份当前版本
    $backupDir = RYEBLOG_ROOT . '/usr/uploads/backup/cloud-' . $type . '-' . $name . '-' . date('Ymd_His');
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    $bk = copyDirRecursive($curDir, $backupDir);
    if (!$bk) {
        @rrmdir($backupDir);
        return ['ok' => false, 'msg' => '备份当前版本失败，已中止更新。'];
    }

    // 2) 下载并解压到临时名，校验后再替换
    $dl = cloudDownload($pkg);
    if (!$dl['ok']) { @rrmdir($backupDir); return $dl; }
    $tmpDest = RYEBLOG_ROOT . '/usr/uploads/backup/.cloud-tmp-' . $name;
    if (is_dir($tmpDest)) @rrmdir($tmpDest);
    mkdir($tmpDest, 0755, true);
    $r = extractZip($dl['file'], $tmpDest);
    @unlink($dl['file']);
    if (!$r['ok']) { @rrmdir($tmpDest); @rrmdir($backupDir); return ['ok' => false, 'msg' => '解压失败：' . ($r['msg'] ?? '未知错误')]; }

    $newDir = $tmpDest . '/' . $r['dir'];
    if ($type === 'plugin' && !is_file($newDir . '/Plugin.php')) {
        @rrmdir($tmpDest); @rrmdir($backupDir);
        return ['ok' => false, 'msg' => 'ZIP 中未找到 Plugin.php，不是有效的插件包。'];
    }
    if ($type === 'theme' && !is_file($newDir . '/theme.css')) {
        @rrmdir($tmpDest); @rrmdir($backupDir);
        return ['ok' => false, 'msg' => 'ZIP 中未找到 theme.css，不是有效的主题包。'];
    }
    if ($r['dir'] !== $name && is_dir($newDir) && !is_dir($tmpDest . '/' . $name)) {
        rename($newDir, $tmpDest . '/' . $name);
        $newDir = $tmpDest . '/' . $name;
    }

    // 3) 替换：删除旧目录 → 移入新目录
    try {
        if (!@rrmdir($curDir)) { throw new Exception('无法移除旧版本目录'); }
        if (!@rename($newDir, $curDir)) { throw new Exception('无法写入新版本'); }
    } catch (Throwable $e) {
        // 回滚：恢复备份
        @rrmdir($curDir);
        @rename($backupDir, $curDir);
        @rrmdir($tmpDest);
        return ['ok' => false, 'msg' => '更新失败，已自动回滚：' . $e->getMessage()];
    }
    @rrmdir($tmpDest);
    return ['ok' => true, 'msg' => ($type === 'plugin' ? '插件' : '主题') . "「$name」已更新到 v" . ($pkg['version'] ?? '') . "（旧版本已备份，可回滚）。", 'backup' => $backupDir];
}

/** 递归复制目录 */
function copyDirRecursive($src, $dst)
{
    if (!is_dir($src)) return false;
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $ok = true;
    foreach (array_diff(scandir($src), ['.', '..']) as $item) {
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            if (!copyDirRecursive($s, $d)) $ok = false;
        } else {
            if (!@copy($s, $d)) $ok = false;
        }
    }
    return $ok;
}

/** 云端更新备份列表 */
function cloudBackupList($type = null)
{
    $dir = RYEBLOG_ROOT . '/usr/uploads/backup';
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (glob($dir . '/cloud-*') as $d) {
        if (!is_dir($d)) continue;
        $base = basename($d); // cloud-plugin-<name>-<ts> 或 cloud-theme-<name>-<ts>
        if (preg_match('/^cloud-(plugin|theme)-(.+)-(\d{8}_\d{6})$/', $base, $m)) {
            if ($type !== null && $m[1] !== $type) continue;
            $out[] = ['path' => $d, 'type' => $m[1], 'name' => $m[2], 'time' => $m[3]];
        }
    }
    usort($out, fn($a, $b) => strcmp($b['time'], $a['time']));
    return $out;
}

/** 回滚：用备份目录覆盖当前（当前目录先移走） */
function cloudRollback($backupPath)
{
    if (!is_dir($backupPath)) return '备份目录不存在。';
    if (!preg_match('/cloud-(plugin|theme)-(.+)-(\d{8}_\d{6})$/', basename($backupPath), $m)) return '非法的备份目录。';
    $type = $m[1];
    $name = $m[2];
    $dest = $type === 'plugin' ? (RYEBLOG_ROOT . '/usr/plugins') : (RYEBLOG_ROOT . '/usr/theme');
    $cur = $dest . '/' . $name;

    $trash = RYEBLOG_ROOT . '/usr/uploads/backup/.rollback-' . $name . '-' . date('Ymd_His');
    if (is_dir($cur)) {
        if (!@rename($cur, $trash)) return '无法移动当前版本。';
    }
    if (!@rename($backupPath, $cur)) {
        // 恢复当前版本
        if (is_dir($trash)) @rename($trash, $cur);
        return '回滚失败。';
    }
    if (is_dir($trash)) @rrmdir($trash);
    return true;
}
