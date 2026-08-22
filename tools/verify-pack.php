<?php
/**
 * RyeBlog 安装包校验器（开发工具）
 * 用法：php tools/verify-pack.php [zip文件名]  默认 ryeblog-1.1.0.zip
 */
$file = $argv[1] ?? 'ryeblog-1.1.0.zip';
$path = __DIR__ . '/../download/' . basename($file);
if (!is_file($path)) { fwrite(STDERR, "找不到 $path\n"); exit(1); }

$z = new ZipArchive();
if ($z->open($path) !== true) { fwrite(STDERR, "无法打开 $path\n"); exit(1); }

$bad = [];
$need = ['install.php', 'upgrade.php', 'tags.php', 'LICENSE.txt', 'usr/plugins/post-copyright/Plugin.php', 'inc/core-update.php'];
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    if (preg_match('#(bilingual-en|cloud-marketplace|migration-plan|showcase-content|verda\.sql|_out\.txt|router\.php|zip-probe|/tmp/|/tools/|/usr/uploads/|/cloud/|(^|/)config\.php$)#', $n)) {
        $bad[] = $n;
    }
}
echo "异常文件: " . count($bad) . "\n";
foreach (array_slice($bad, 0, 8) as $b) echo "  $b\n";
foreach ($need as $f) {
    echo ($z->locateName('ryeblog/' . $f) >= 0 ? '含' : '缺') . " $f\n";
}
echo '文件数: ' . $z->numFiles . "\n";
$z->close();
