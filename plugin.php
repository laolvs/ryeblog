<?php
/**
 * RyeBlog —— 插件前台页面调度器
 *
 * URL 形态：
 *   /bbs/<page>.php[?query]      （生产环境由 .htaccess 重写到 ?__bbs=）
 *   /plugin.php?p=<plugin>&page=<page>[&...]
 *
 * 插件页自行 require 本插件 bootstrap，并调用 publicHeader()/publicFooter()
 * 包裹 RyeBlog 布局，从而与前台视觉/导航统一。
 */
require_once __DIR__ . '/inc/functions.php';

// 维护模式下插件前台路由（论坛 /bbs/ 等）一并关闭，与首页等入口一致
enforceMaintenance();

$plugin = '';
$page = '';

if (isset($_GET['p'], $_GET['page'])) {
    $plugin = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['p']);
    $page   = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['page']);
} elseif (isset($_GET['__bbs'])) {
    $plugin = 'rye';   // 当前唯一论坛插件（RYE社区）；后续可扩展为从路径解析插件名
    $raw    = explode('?', $_GET['__bbs'], 2)[0];   // 剥离可能混入的 query
    // 伪静态：/bbs/thread/26.html、/bbs/user/1.html → page=thread|user + $_GET['id']=26
    if (preg_match('#^([a-zA-Z_]+)/(\d+)\.html$#', $raw, $m)) {
        $page      = $m[1];
        $_GET['id'] = (int) $m[2];
    } else {
        $page = preg_replace('/\.php$/i', '', basename($raw));
    }
}

if ($plugin === '' || $page === '') {
    http_response_code(404);
    echo 'Plugin page not found.';
    exit;
}

$file = RYEBLOG_ROOT . '/usr/plugins/' . $plugin . '/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    echo 'Plugin page not found: ' . esc($plugin) . '/' . esc($page);
    exit;
}

require_once $file;
