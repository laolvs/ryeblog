<?php
/** RyeBlog —— 站点地图 sitemap.xml（双语模式输出中文 + /en 英文 URL，带 hreflang alternate） */
require_once __DIR__ . '/inc/functions.php';
if (!db()) { http_response_code(500); exit; }
enforceLangPrefix();
enforceMaintenance();
header('Content-Type: application/xml; charset=utf-8');
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$base = rtrim(siteUrl(), '/') ?: ('http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . siteBase());
$bilingual = bilingualEnabled();
$enBase = $bilingual ? $base . '/en' : '';

// [loc, priority, lastmod, en_loc(可选)]
$urls = [];
$urls[] = ['loc' => $base . '/', 'priority' => '1.0', 'en' => $enBase . '/'];
foreach (getCategories() as $c) {
    $slug = urlencode($c['slug']);
    $urls[] = ['loc' => $base . '/category/' . $slug, 'priority' => '0.6', 'en' => $enBase !== '' ? $enBase . '/category/' . $slug : ''];
}
foreach (getTags(200) as $t) {
    $slug = urlencode($t['slug']);
    $urls[] = ['loc' => $base . '/tag/' . $slug, 'priority' => '0.5', 'en' => $enBase !== '' ? $enBase . '/tag/' . $slug : ''];
}
foreach (getPages() as $pg) {
    $slug = urlencode($pg['slug']);
    $urls[] = ['loc' => $base . '/page/' . $slug, 'priority' => '0.7', 'en' => $enBase !== '' ? $enBase . '/page/' . $slug : ''];
}
foreach (getPosts(['perPage' => 1000])['items'] as $p) {
    $slug = urlencode($p['slug']);
    $enLoc = '';
    if ($enBase !== '') {
        // 英文别名（slug_en）存在时用英文 URL，否则同中文 slug（/en 下自动回退）
        $enSlug = !empty($p['slug_en']) ? urlencode($p['slug_en']) : $slug;
        $enLoc = $enBase . '/post/' . $enSlug;
    }
    $urls[] = [
        'loc' => $base . '/post/' . $slug,
        'lastmod' => date('Y-m-d', strtotime($p['updated_at'] ?: $p['created_at'])),
        'priority' => '0.8',
        'en' => $enLoc,
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
if ($bilingual) {
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
} else {
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
}
foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . esc($u['loc']) . "</loc>\n";
    if ($bilingual && !empty($u['en'])) {
        echo '    <xhtml:link rel="alternate" hreflang="zh-CN" href="' . esc($u['loc']) . '"/>' . "\n";
        echo '    <xhtml:link rel="alternate" hreflang="en" href="' . esc($u['en']) . '"/>' . "\n";
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . esc($u['loc']) . '"/>' . "\n";
    }
    if (!empty($u['lastmod'])) echo "    <lastmod>{$u['lastmod']}</lastmod>\n";
    echo "    <priority>" . ($u['priority'] ?? '0.6') . "</priority>\n  </url>\n";
}
echo '</urlset>';
