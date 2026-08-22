<?php
/**
 * RyeBlog —— {slug}.html 调度器
 * 用于 html 模式伪静态：/cn/{slug}.html 或 /{slug}.html
 * 根据 vd_posts.type 分派到 post.php（文章）或 page.php（独立页面）
 * 与 router.php 的 getPostBySlugAnyType 逻辑保持一致
 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }

$slug = $_GET['slug'] ?? '';
if ($slug === '') {
    http_response_code(404);
    publicHeader('404');
    echo '<div class="empty-box"><p>页面不存在。</p></div>';
    publicFooter();
    exit;
}

$rec = getPostBySlugAnyType($slug);
if (!$rec) {
    http_response_code(404);
    publicHeader('404');
    echo '<div class="empty-box"><p>没有找到这篇文章。</p></div>';
    publicFooter();
    exit;
}

if ($rec['type'] === 'page') {
    $_GET['p'] = $slug;
    require __DIR__ . '/page.php';
} else {
    $_GET['slug'] = $slug;
    require __DIR__ . '/post.php';
}
