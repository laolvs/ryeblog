<?php
/**
 * RyeBlog 退出登录
 */
require_once __DIR__ . '/../inc/functions.php';
adminLogout();
header('Location: ' . baseUrl('admin/login.php'));
exit;
