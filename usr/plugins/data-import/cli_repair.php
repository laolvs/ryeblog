<?php
/**
 * 命令行修复远程图片（无需浏览器，适合大批量）。
 *
 * 用法：
 *   php usr/plugins/data-import/cli_repair.php            # 修复全部文章和页面
 *   php usr/plugins/data-import/cli_repair.php 10717,10802 # 仅修复指定 ID
 *
 * 功能：扫描文章正文里的远程图片 URL，下载到本地 usr/uploads/import/ 并替换链接。
 * 幂等，可重复执行。
 */

$root = dirname(__DIR__, 3);
if (!defined('RYEBLOG_ROOT')) {
    define('RYEBLOG_ROOT', $root);
}

require_once $root . '/inc/functions.php';
require_once __DIR__ . '/Plugin.php';

$ids = [];
if (!empty($argv[1])) {
    foreach (preg_split('/[,\s]+/', trim($argv[1])) as $x) {
        $x = (int)$x;
        if ($x > 0) {
            $ids[] = $x;
        }
    }
}

$post = [];
if ($ids) {
    $post['repair_ids'] = implode(',', $ids);
}

$res = Plugin_data_import::handleRepairImages($post);
if (is_string($res) && $res !== '') {
    fwrite(STDERR, $res . "\n");
    exit(1);
}

echo getOption('data_import_last_result', '完成。') . "\n";
exit(0);
