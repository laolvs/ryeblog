<?php
/**
 * 导入功能测试（针对 verda_test 空库，不污染线上 verda）。
 * 用法：php usr/plugins/data-import/cli_import_test.php
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'verda_test');
define('DB_USER', 'root');
define('DB_PASS', '');
define('PRETTY_URLS', true);

require_once __DIR__ . '/../../../inc/functions.php';
require_once __DIR__ . '/Plugin.php';

$files = [
    'WordPress XML' => ['path' => 'C:/Users/netbe/Downloads/import_test/WordPress.2026-08-18.xml', 'type' => 'wordpress_xml'],
    'WordPress SQL (laolv.org)' => ['path' => 'C:/Users/netbe/Downloads/import_test/laolv.org.sql', 'type' => 'sql_file'],
    'Typecho SQL' => ['path' => 'C:/Users/netbe/Downloads/import_test/typecho.sql', 'type' => 'sql_file'],
];

$planDir = RYEBLOG_ROOT . '/usr/uploads/import';
if (is_dir($planDir)) {
    array_map('unlink', glob($planDir . '/_plan_*.json'));
    array_map('unlink', glob($planDir . '/_prog_*.json'));
}

function truncateTest()
{
    foreach (['vd_post_tags', 'vd_comments', 'vd_posts', 'vd_tags', 'vd_categories'] as $t) {
        db()->exec("DELETE FROM `$t`");
        db()->exec("ALTER TABLE `$t` AUTO_INCREMENT=1");
    }
}

foreach ($files as $label => $f) {
    echo "\n========== $label ==========\n";
    if (!is_file($f['path'])) { echo "  文件不存在，跳过\n"; continue; }
    truncateTest();

    $opts = [
        'download_remote_images' => ($label === 'WordPress XML') ? false : false, // 先验证引擎；远程图片下载单独测
        'preserve_slug' => true,
        'import_comments' => true,
        'skip_existing' => true,
        'import_author' => '',
    ];

    try {
        $t0 = microtime(true);
        $plan = Plugin_data_import::buildPlan($f['path'], $f['type'], $opts);
        Plugin_data_import::savePlan($plan);
        $analyzeSec = round(microtime(true) - $t0, 2);
        echo "  analyze 用时 {$analyzeSec}s，计划类型={$plan['type']}，待导入条目=" . count($plan['items'] ?? $plan['statements'] ?? []) . "\n";
        echo "  计划计数：文章 {$plan['counts']['posts']} / 页面 {$plan['counts']['pages']} / 评论 {$plan['counts']['comments']} / 分类 {$plan['counts']['categories']} / 标签 {$plan['counts']['tags']} / 远程图片 {$plan['counts']['images']}\n";

        $offset = 0;
        $batch = 3;
        $t1 = microtime(true);
        $rounds = 0;
        do {
            $r = Plugin_data_import::importChunk($plan, $offset, $batch);
            $offset = $r['offset_next'];
            $rounds++;
        } while (empty($r['finished']));
        $importSec = round(microtime(true) - $t1, 2);
        echo "  chunk 分片数=$rounds，用时 {$importSec}s\n";
        echo "  总结：{$r['summary']}\n";
    } catch (\Throwable $e) {
        echo "  !! 异常：" . $e->getMessage() . "\n";
        continue;
    }

    // 校验实际落库
    $posts = (int)dbOne("SELECT COUNT(*) c FROM vd_posts WHERE type='post'")['c'];
    $pages = (int)dbOne("SELECT COUNT(*) c FROM vd_posts WHERE type='page'")['c'];
    $comments = (int)dbOne("SELECT COUNT(*) c FROM vd_comments")['c'];
    $cats = (int)dbOne("SELECT COUNT(*) c FROM vd_categories")['c'];
    $tags = (int)dbOne("SELECT COUNT(*) c FROM vd_tags")['c'];
    $pt = (int)dbOne("SELECT COUNT(*) c FROM vd_post_tags")['c'];
    echo "  落库校验：文章 $posts / 页面 $pages / 评论 $comments / 分类 $cats / 标签 $tags / 文章-标签关联 $pt\n";
}

echo "\n全部测试结束。\n";
