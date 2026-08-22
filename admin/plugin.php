<?php
/**
 * RyeBlog —— 插件后台页面调度器
 *
 * URL: admin/plugin.php?p=<plugin>&page=<page>
 *
 * 插件后台页自行调用 adminHead()/adminFoot()（admin.php 已提供后台布局），
 * 并通过 admin_menu 钩子出现在后台侧边栏。
 */
require_once __DIR__ . '/admin.php';

$plugin = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['p'] ?? '');
$page   = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['page'] ?? '');
if ($plugin === '' || $page === '') {
    http_response_code(404);
    echo 'Plugin admin page not found.';
    exit;
}

$file = RYEBLOG_ROOT . '/usr/plugins/' . $plugin . '/admin/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    echo 'Not found: ' . esc($plugin) . '/admin/' . esc($page);
    exit;
}

require_once $file;
