<?php
/**
 * RyeBlog —— 云端市场发布工具（开发工具，发布安装包时排除本文件）
 *
 * 用法：
 *   php tools/cli_pack.php plugin english-admin [--version 2.1.0] [--changelog "更新说明"]
 *   php tools/cli_pack.php theme dark [--version 1.0.0]
 *   php tools/cli_pack.php manifest                      # 重新生成 cloud-release/repo.json
 *   php tools/cli_pack.php list                          # 列出可发布的插件/主题
 *
 * 输出：cloud-release/（plugins/ themes/ repo.json），上传到任意静态托管即构成云端仓库。
 * 打包规则：跳过 cli_*.php 开发脚本；插件读 @Version 注释、主题读 theme.css 的 @Version。
 */
define('RYEBLOG_ROOT', dirname(__DIR__));

$args = array_slice($argv, 1);
if (empty($args)) { usage(); exit(1); }
$cmd = $args[0];

$releaseDir = RYEBLOG_ROOT . '/cloud-release';
$outPlugins = $releaseDir . '/plugins';
$outThemes  = $releaseDir . '/themes';

function usage()
{
    echo "RyeBlog 云端市场发布工具\n"
        . "  用法:\n"
        . "    php tools/cli_pack.php plugin <name> [--version x.y.z] [--changelog \"...\"]\n"
        . "    php tools/cli_pack.php theme  <name> [--version x.y.z]\n"
        . "    php tools/cli_pack.php manifest\n"
        . "    php tools/cli_pack.php list\n";
}

/** 读取 PHP 文件头注释里的 @Tag 值 */
function tagValue($file, $tag)
{
    if (!is_file($file)) return '';
    $src = file_get_contents($file);
    if (preg_match('/@' . preg_quote($tag, '/') . '\s+(.+)/', $src, $m)) return trim($m[1]);
    return '';
}

/** 读取主题版本（theme.css 的 @Version，默认 1.0.0） */
function themeVersion($dir)
{
    $v = tagValue($dir . '/theme.css', 'Version');
    if ($v !== '' && preg_match('/^\d+\.\d+(\.\d+)?$/', $v)) return $v;
    return '1.0.0';
}

/** 目录打包为 ZIP（跳过 cli_*.php / .git / backup） */
function packDir($srcDir, $zipPath, $entryRoot)
{
    if (!class_exists('ZipArchive')) { echo "错误：需要 PHP zip 扩展（php.ini 启用 extension=zip）。\n"; exit(1); }
    if (is_file($zipPath)) @unlink($zipPath);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo "错误：无法创建 $zipPath\n"; exit(1);
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS));
    $n = 0;
    foreach ($it as $f) {
        if ($f->isDir()) continue;
        $rel = substr($f->getPathname(), strlen($srcDir) + 1);
        $rel = str_replace('\\', '/', $rel);
        // 排除开发脚本与杂物
        if (preg_match('#(^|/)cli_[^/]*\.php$#', $rel)) continue;
        if (preg_match('#(^|/)(\.git|node_modules|vendor-cache|backup|\.DS_Store)(/|$)#', $rel)) continue;
        $zip->addFile($f->getPathname(), $entryRoot . '/' . $rel);
        $n++;
    }
    $zip->close();
    return $n;
}

/** 读取 manifest，或返回默认结构 */
function loadManifest()
{
    $f = $GLOBALS['releaseDir'] . '/repo.json';
    if (is_file($f)) {
        $d = json_decode(file_get_contents($f), true);
        if (is_array($d)) return $d;
    }
    return ['repo' => 'ryeblog-official', 'homepage' => 'https://ryeblog.com/', 'updated' => '', 'plugins' => [], 'themes' => []];
}

function saveManifest($m)
{
    $m['updated'] = date('c');
    $f = $GLOBALS['releaseDir'] . '/repo.json';
    file_put_contents($f, json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "✓ repo.json 已更新（" . count($m['plugins']) . " 插件 / " . count($m['themes']) . " 主题）\n";
}

/** 打包插件 */
function packPlugin($name, $versionOpt, $changelog)
{
    $src = RYEBLOG_ROOT . '/usr/plugins/' . $name;
    if (!is_dir($src)) { echo "错误：插件目录不存在 usr/plugins/$name\n"; exit(1); }
    if (!is_file($src . '/Plugin.php')) { echo "错误：缺少 Plugin.php，不是有效插件\n"; exit(1); }
    $title = tagValue($src . '/Plugin.php', 'Title') ?: $name;
    $desc  = tagValue($src . '/Plugin.php', 'Desc') ?: '';
    $ver   = $versionOpt !== '' ? $versionOpt : (tagValue($src . '/Plugin.php', 'Version') ?: '1.0.0');
    if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $ver)) { echo "错误：版本号 $ver 不符合语义化版本\n"; exit(1); }

    $zipPath = $GLOBALS['outPlugins'] . "/$name-$ver.zip";
    $n = packDir($src, $zipPath, $name);
    $sha = hash_file('sha256', $zipPath);

    $m = loadManifest();
    $m['plugins'] = array_values(array_filter($m['plugins'], fn($p) => $p['name'] !== $name));
    $m['plugins'][] = [
        'name' => $name, 'title' => $title, 'version' => $ver, 'desc' => $desc,
        'download' => $GLOBALS['repoBase'] . "/plugins/$name-$ver.zip",
        'sha256' => $sha, 'min_core' => '1.0.0',
        'changelog' => $changelog,
        'updated' => date('Y-m-d'),
    ];
    usort($m['plugins'], fn($a, $b) => strcmp($a['name'], $b['name']));
    saveManifest($m);
    echo "✓ 插件 $name v$ver 打包完成（$n 文件, " . round(filesize($zipPath) / 1024, 1) . " KB）\n";
    echo "  SHA-256: $sha\n";
}

/** 打包主题 */
function packTheme($name, $versionOpt)
{
    $src = RYEBLOG_ROOT . '/usr/theme/' . $name;
    if (!is_dir($src)) { echo "错误：主题目录不存在 usr/theme/$name\n"; exit(1); }
    $title = tagValue($src . '/theme.css', 'Title') ?: $name;
    $desc  = tagValue($src . '/theme.css', 'Desc') ?: '';
    $ver   = $versionOpt !== '' ? $versionOpt : themeVersion($src);
    if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $ver)) { echo "错误：版本号 $ver 不符合语义化版本\n"; exit(1); }

    $zipPath = $GLOBALS['outThemes'] . "/$name-$ver.zip";
    $n = packDir($src, $zipPath, $name);
    $sha = hash_file('sha256', $zipPath);

    $m = loadManifest();
    $m['themes'] = array_values(array_filter($m['themes'], fn($p) => $p['name'] !== $name));
    $m['themes'][] = [
        'name' => $name, 'title' => $title, 'version' => $ver, 'desc' => $desc,
        'download' => $GLOBALS['repoBase'] . "/themes/$name-$ver.zip",
        'sha256' => $sha, 'min_core' => '1.0.0',
        'updated' => date('Y-m-d'),
    ];
    usort($m['themes'], fn($a, $b) => strcmp($a['name'], $b['name']));
    saveManifest($m);
    echo "✓ 主题 $name v$ver 打包完成（$n 文件, " . round(filesize($zipPath) / 1024, 1) . " KB）\n";
    echo "  SHA-256: $sha\n";
}

// ---- 参数解析 ----
$name = $args[1] ?? '';
$versionOpt = '';
$changelog = '';
$repoBase = 'https://ryeblog.com/cloud';
for ($i = 1; $i < count($args); $i++) {
    if ($args[$i] === '--version' && isset($args[$i + 1])) { $versionOpt = $args[$i + 1]; $i++; }
    elseif ($args[$i] === '--changelog' && isset($args[$i + 1])) { $changelog = $args[$i + 1]; $i++; }
    elseif ($args[$i] === '--base' && isset($args[$i + 1])) { $repoBase = rtrim($args[$i + 1], '/'); $i++; }
}

$GLOBALS['releaseDir'] = $releaseDir;
$GLOBALS['outPlugins'] = $outPlugins;
$GLOBALS['outThemes']  = $outThemes;
$GLOBALS['repoBase']   = $repoBase;

foreach ([$releaseDir, $outPlugins, $outThemes] as $d) if (!is_dir($d)) mkdir($d, 0755, true);

switch ($cmd) {
    case 'plugin':
        if ($name === '') { usage(); exit(1); }
        packPlugin($name, $versionOpt, $changelog);
        break;
    case 'theme':
        if ($name === '') { usage(); exit(1); }
        packTheme($name, $versionOpt);
        break;
    case 'manifest':
        $m = loadManifest();
        // 按当前 base 重写 download 地址
        foreach ($m['plugins'] as &$p) $p['download'] = $repoBase . '/plugins/' . $p['name'] . '-' . $p['version'] . '.zip';
        unset($p);
        foreach ($m['themes'] as &$t) $t['download'] = $repoBase . '/themes/' . $t['name'] . '-' . $t['version'] . '.zip';
        unset($t);
        saveManifest($m);
        break;
    case 'list':
        echo "可发布的插件：\n";
        foreach (glob(RYEBLOG_ROOT . '/usr/plugins/*/Plugin.php') as $f) {
            $d = dirname($f);
            $n = basename($d);
            $v = tagValue($f, 'Version') ?: '?';
            echo "  plugin $n  (v$v)\n";
        }
        echo "可发布的主题：\n";
        foreach (glob(RYEBLOG_ROOT . '/usr/theme/*/theme.css') as $f) {
            $d = dirname($f);
            echo "  theme " . basename($d) . "  (v" . themeVersion($d) . ")\n";
        }
        break;
    default:
        usage();
        exit(1);
}
