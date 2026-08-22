<?php
/**
 * RyeBlog 官方主题 —— 独立页模板
 * slug=download 时渲染专属下载页（dl_page.php）；其他页面走 post.php 文章式布局。
 */
if (($post['slug'] ?? '') === 'download') {
    require __DIR__ . '/dl_page.php';
    exit;
}

require __DIR__ . '/post.php';
