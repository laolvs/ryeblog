<?php
/**
 * RyeBlog —— PHP 内置服务器路由（用于 `php -S localhost:8000 -t . router.php` 测试）
 * 同时兼容所有伪静态模式：/post/slug、/slug.html、/archives/123.html
 */
require_once __DIR__ . '/inc/functions.php';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
// 去掉站点子目录前缀（如 /verda），使路由规则与根部署一致
$base = siteBase();
if ($base !== '' && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}
if ($uri === '') $uri = '/';

// 语言前缀：/en（英文站，仅双语模式）；/cn 旧前缀兼容剥离（页面内 enforceLangPrefix 会 301 回根目录）
$hasQuery = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?') !== false;
if (preg_match('#^/(cn|en)(/.*)?$#', $uri, $m)) {
    setCurrentLang($m[1] === 'en' && bilingualEnabled() ? 'en' : 'zh');
    $uri = $m[2] ?: '/';
    if ($uri === '') $uri = '/';
} else {
    // 无前缀（中文根目录）：按 ?lang= / cookie 推断（仅双语模式认可 en）
    if (($_GET['lang'] ?? '') === 'en' && bilingualEnabled()) {
        setCurrentLang('en');
    } elseif (isset($_COOKIE['vd_lang']) && $_COOKIE['vd_lang'] === 'en' && bilingualEnabled()) {
        setCurrentLang('en');
    }
}

// 静态资源
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

// 站点地图 / RSS
if ($uri === '/sitemap.xml') { require __DIR__ . '/sitemap.php'; return true; }
if ($uri === '/feed' || $uri === '/feed.xml' || $uri === '/rss.xml') { require __DIR__ . '/feed.php'; return true; }

// /post/{slug}
if (preg_match('#^/post/([^/]+)/?$#', $uri, $m)) { $_GET['slug'] = $m[1]; require __DIR__ . '/post.php'; return true; }
// /category/{slug}
if (preg_match('#^/category/([^/]+)/?$#', $uri, $m)) { $_GET['c'] = $m[1]; require __DIR__ . '/category.php'; return true; }
// /tag/{slug}
if (preg_match('#^/tag/([^/]+)/?$#', $uri, $m)) { $_GET['t'] = $m[1]; require __DIR__ . '/tag.php'; return true; }
// /page/{slug}
if (preg_match('#^/page/([^/]+)/?$#', $uri, $m)) { $_GET['p'] = $m[1]; require __DIR__ . '/page.php'; return true; }
// /archives/{id}.html
if (preg_match('#^/archives/(\d+)\.html$#', $uri, $m)) { $_GET['p'] = $m[1]; require __DIR__ . '/post.php'; return true; }
// /archive
if ($uri === '/archive') { require __DIR__ . '/archive.php'; return true; }
// /search
if ($uri === '/search' || strpos($uri, '/search') === 0) { require __DIR__ . '/search.php'; return true; }
// /{slug}.html —— 区分文章/独立页面
if (preg_match('#^/([^.]+)\.html$#', $uri, $m)) {
    $rec = getPostBySlugAnyType($m[1]);
    if ($rec && $rec['type'] === 'page') {
        $_GET['p'] = $m[1]; require __DIR__ . '/page.php';
    } else {
        $_GET['slug'] = $m[1]; require __DIR__ . '/post.php';
    }
    return true;
}

// 兜底：首页
require __DIR__ . '/index.php';
return true;
