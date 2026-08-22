<?php
/**
 * RyeBlog 后台 —— Markdown 实时预览接口
 * 仅管理员可用，返回由原创解析器生成的 HTML。
 * 错误可见化：未登录/非法请求/渲染异常均返回明确信息，前端直接展示原因。
 */
require_once __DIR__ . '/admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['md'])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo '预览接口需要 POST 请求且携带 md 参数。';
    exit;
}

try {
    header('Content-Type: text/html; charset=utf-8');
    echo markdownToHtml((string)$_POST['md']);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '预览渲染异常：' . $e->getMessage();
}
