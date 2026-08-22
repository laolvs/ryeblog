<?php
/**
 * 模拟浏览器 AJAX 导入链路测试（针对 verda_test 空库，不污染线上 verda）。
 * 不走网络（download_remote_images=false），只验证 analyze→chunk 契约与落库。
 * 用法：php usr/plugins/data-import/cli_ajax_test.php
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'verda_test');
define('DB_USER', 'root');
define('DB_PASS', '');
define('PRETTY_URLS', true);

require_once __DIR__ . '/../../../inc/functions.php';
require_once __DIR__ . '/Plugin.php';

$files = [
    'WordPress XML'          => ['path' => 'C:/Users/netbe/Downloads/import_test/WordPress.2026-08-18.xml', 'type' => 'wordpress_xml'],
    'WordPress SQL (laolv)'  => ['path' => 'C:/Users/netbe/Downloads/import_test/laolv.org.sql', 'type' => 'sql_file'],
    'Typecho SQL'            => ['path' => 'C:/Users/netbe/Downloads/import_test/typecho.sql', 'type' => 'sql_file'],
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

function uploadStub($path)
{
    return ['import_file' => ['tmp_name' => $path, 'error' => 0, 'name' => basename($path)]];
}

$allOk = true;
foreach ($files as $label => $f) {
    echo "\n========== $label ==========\n";
    if (!is_file($f['path'])) { echo "  文件不存在，跳过\n"; continue; }
    truncateTest();

    $opts = [
        'download_remote_images' => false,
        'preserve_slug' => true,
        'import_comments' => true,
        'skip_existing' => true,
        'import_author' => '',
    ];
    $post = array_merge(['step' => 'analyze', 'source_type' => $f['type']], $opts);

    // 1) analyze
    $a = Plugin_data_import::ajax($post, uploadStub($f['path']));
    if (!empty($a['error'])) { echo "  !! analyze 失败：" . $a['error'] . "\n"; $allOk = false; continue; }
    if (empty($a['token']) || empty($a['counts'])) { echo "  !! analyze 返回结构异常\n"; $allOk = false; continue; }
    echo "  analyze OK，token 前8位=" . substr($a['token'], 0, 8) . "，计数=" . json_encode($a['counts'], JSON_UNESCAPED_UNICODE) . "\n";

    // 2) chunk 循环
    $token = $a['token'];
    $offset = 0;
    $rounds = 0;
    $last = null;
    while (true) {
        try {
            $c = Plugin_data_import::ajax(['step' => 'chunk', 'token' => $token, 'offset' => $offset], []);
        } catch (\Throwable $th) {
            echo "  !! chunk 抛异常：" . get_class($th) . "：" . $th->getMessage() . "\n    " . $th->getFile() . ':' . $th->getLine() . "\n";
            $allOk = false;
            break;
        }
        if (!empty($c['error'])) { echo "  !! chunk 失败：" . $c['error'] . "\n"; $allOk = false; break; }
        $offset = $c['offset_next'];
        $rounds++;
        $last = $c;
        echo "    chunk#$rounds offset=$offset done=" . ($c['done'] ?? '?') . "/" . ($c['total'] ?? '?') . " finished=" . var_export(!empty($c['finished']), true) . "\n";
        if (!empty($c['finished'])) break;
    }
    if ($last) {
        echo "  chunk 分片数=$rounds，finished=" . var_export($last['finished'], true) . "\n";
        echo "  总结：{$last['summary']}\n";
    }

    // 3) 落库校验
    $posts = (int)dbOne("SELECT COUNT(*) c FROM vd_posts WHERE type='post'")['c'];
    $pages = (int)dbOne("SELECT COUNT(*) c FROM vd_posts WHERE type='page'")['c'];
    $comments = (int)dbOne("SELECT COUNT(*) c FROM vd_comments")['c'];
    $cats = (int)dbOne("SELECT COUNT(*) c FROM vd_categories")['c'];
    $tags = (int)dbOne("SELECT COUNT(*) c FROM vd_tags")['c'];
    $pt = (int)dbOne("SELECT COUNT(*) c FROM vd_post_tags")['c'];
    echo "  落库：文章 $posts / 页面 $pages / 评论 $comments / 分类 $cats / 标签 $tags / 关联 $pt\n";
}

echo "\n" . ($allOk ? "✅ 全部 AJAX 链路测试通过。" : "❌ 存在失败项，见上。") . "\n";
