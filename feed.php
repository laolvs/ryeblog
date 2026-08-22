<?php
/** RyeBlog —— RSS Feed */
require_once __DIR__ . '/inc/functions.php';
if (!db()) { http_response_code(500); exit; }
enforceLangPrefix();
enforceMaintenance();
header('Content-Type: application/rss+xml; charset=utf-8');
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$base = rtrim(siteUrl(), '/') ?: ('http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . siteBase());
$posts = getPosts(['perPage' => 20])['items'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
    <title><?php echo esc(siteTitle()); ?></title>
    <link><?php echo esc($base); ?>/</link>
    <description><?php echo esc(siteSlogan()); ?></description>
    <language>zh-CN</language>
    <lastBuildDate><?php echo date(DATE_RSS); ?></lastBuildDate>
<?php foreach ($posts as $p): ?>
    <item>
        <title><?php echo esc($p['title']); ?></title>
        <link><?php echo esc($base . '/post/' . urlencode($p['slug'])); ?></link>
        <guid><?php echo esc($base . '/post/' . urlencode($p['slug'])); ?></guid>
        <pubDate><?php echo date(DATE_RSS, strtotime($p['created_at'])); ?></pubDate>
        <description><![CDATA[<?php echo postExcerpt($p, 400); ?>]]></description>
        <category><?php echo esc($p['category_name'] ?? ''); ?></category>
    </item>
<?php endforeach; ?>
</channel>
</rss>
