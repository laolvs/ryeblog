<?php
/**
 * RyeBlog 官方安装包生成器（开发工具，不出现在安装包中）
 * 用法：php tools/build-release.php [version]
 * 输出：download/ryeblog-<version>.zip（顶层目录 ryeblog/）
 * 排除：config.php / config.php.bak / _out.txt / tmp / tools / download /
 *       cloud-release / usr/uploads / cli_*.php / zip-probe.php / .git 等
 */
$version = $argv[1] ?? '1.0.0';
$root = dirname(__DIR__);
$outDir = $root . '/download';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
$zipPath = $outDir . "/ryeblog-{$version}.zip";
if (is_file($zipPath)) @unlink($zipPath);

if (!class_exists('ZipArchive')) { fwrite(STDERR, "需要 PHP zip 扩展\n"); exit(1); }
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) { fwrite(STDERR, "无法创建 $zipPath\n"); exit(1); }

$skipDir = ['tmp', 'tmp-cptest', 'tools', 'download', 'cloud-release', 'cloud', '.git', 'node_modules', 'usr/uploads', 'usr/tmp-update', 'docs/themes'];
$skipFile = ['config.php', 'config.php.bak', '_out.txt', 'zip-probe.php', 'router.php', 'verda.sql'];
$skipDocs = ['bilingual-en-design.md', 'cloud-marketplace-design.md', 'migration-plan.md', 'showcase-content-plan.md'];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
$n = 0;
foreach ($it as $f) {
    if ($f->isDir()) continue;
    $rel = substr($f->getPathname(), strlen($root) + 1);
    $rel = str_replace('\\', '/', $rel);
    $parts = explode('/', $rel);
    // 排除目录（前缀匹配，如 usr/uploads）
    foreach ($skipDir as $d) {
        if ($parts[0] === $d || ($d === 'usr/uploads' && $parts[0] === 'usr' && ($parts[1] ?? '') === 'uploads')) {
            continue 2;
        }
    }
    // 排除文件
    if (in_array($parts[count($parts) - 1], $skipFile, true)) continue;
    if (in_array($parts[count($parts) - 1], $skipDocs, true) && $parts[0] === 'docs') continue;
    if (preg_match('#(^|/)cli_[^/]*\.php$#', $rel)) continue;
    $zip->addFile($f->getPathname(), 'ryeblog/' . $rel);
    $n++;
}
$zip->close();

$size = round(filesize($zipPath) / 1024, 1);
echo "✓ {$zipPath} 生成完成（{$n} 文件, {$size} KB）\n";
