<?php
/**
 * RyeBlog 官方站 —— 发布版本更新记录（发版工具调用，仅在本站服务器执行）
 * 部署位置：ryeblog.com/cloud/publish.php（与 core.json 同目录）
 * 用法：php cloud/publish.php [version]
 * 逻辑：读同目录 core.json → 确保「版本更新」分类 → 按 slug ryeblog-update-vX-Y-Z 创建/更新文章 → 输出文章 URL
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../inc/functions.php';

$corePath = __DIR__ . '/core.json';
$core = json_decode((string) @file_get_contents($corePath), true);
if (!$core || empty($core['version'])) {
    fwrite(STDERR, "cloud/core.json 无效或缺失：$corePath\n");
    exit(1);
}
$v    = preg_replace('/[^0-9a-zA-Z.]/', '', (string) $core['version']);
$slug = 'ryeblog-update-v' . str_replace('.', '-', $v);
$title = 'RyeBlog v' . $v . ' 更新';

$changelog = trim((string) ($core['changelog'] ?? ''));
$content = ($changelog !== '' ? $changelog . "\n\n" : '')
         . "---\n\n"
         . "- 安装包 / 升级包下载：" . ($core['url'] ?? '') . "\n"
         . "- 校验值（SHA-256）：`" . ($core['sha256'] ?? '') . "`\n"
         . "- 发布于：" . ($core['published'] ?? date('Y-m-d')) . "\n"
         . "- 升级方式：备份站点 → 覆盖上传（config.php 除外）→ 访问 `upgrade.php` 完成数据库迁移。\n";

// 确保「版本更新」分类
$catId = (int) (dbOne("SELECT id FROM vd_categories WHERE slug='updates'")['id'] ?? 0);
if (!$catId) {
    dbQuery("INSERT INTO vd_categories (name, slug, description) VALUES ('版本更新', 'updates', 'RyeBlog 版本发布记录')");
    $catId = (int) dbVal('SELECT LAST_INSERT_ID()');
}

$exist = dbOne("SELECT id FROM vd_posts WHERE slug=? AND type='post'", [$slug]);
if ($exist) {
    dbQuery("UPDATE vd_posts SET title=?, content=?, category_id=?, updated_at=NOW() WHERE id=?", [$title, $content, $catId, $exist['id']]);
    $pid = (int) $exist['id'];
} else {
    dbQuery("INSERT INTO vd_posts (title, slug, content, excerpt, seo_description, type, status, format, category_id, author, created_at, updated_at, views)
             VALUES (?, ?, ?, ?, ?, 'post', 'published', 'markdown', ?, 'admin', NOW(), NOW(), 0)",
        [$title, $slug, $content, $changelog !== '' ? mb_substr($changelog, 0, 150) : '', $title, $catId]);
    $pid = (int) (dbOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
}

echo "OK article_id={$pid} url=https://ryeblog.com/post/{$slug}\n";
