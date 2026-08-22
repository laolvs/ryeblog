<?php
/**
 * RyeBlog 用户退出登录
 */
require_once __DIR__ . '/../inc/functions.php';
enforceMaintenance();
userLogout();
header('Location: ' . homeUrl());
exit;
