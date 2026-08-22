<?php
/**
 * RyeBlog 安装向导
 * -----------------------------------------------------------------------------
 * 全新安装流程：
 *   1. 填写数据库连接信息 + 站点标题 + 管理员账号；
 *   2. 自动创建数据库（如账号有权限）、建表、写入默认数据；
 *   3. 完成后即可访问站点与后台。
 * 安装默认：纯动态 URL（伪静态可在后台开启）、默认插件「文末版权」、
 * 未分类 + 一篇默认文章、顶部导航（分类/标签/RSS/网站地图）。
 *
 * 安全：安装完成后建议删除本文件（后台「设置」会提示）。
 */

session_start();

define('RYEBLOG_ROOT', __DIR__);
define('RYEBLOG_VERSION', '1.4.2'); // 与 inc/functions.php 保持一致

$configFile = __DIR__ . '/config.php';

/* ---------- 已安装检测 ---------- */
$installed = false;
if (is_file($configFile)) {
    $try = @include $configFile;
    if (defined('DB_NAME')) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pf = defined('DB_PREFIX') ? DB_PREFIX : 'vd_';
            $installed = $pdo->query('SELECT 1 FROM ' . $pf . 'options LIMIT 1') !== false;
        } catch (\Throwable $e) {
            $installed = false;
        }
    }
    if ($installed) {
        header('Location: ' . (defined('DB_NAME') ? 'index.php' : 'install.php'));
        exit;
    }
}

/* ---------- CSRF ---------- */
function instToken()
{
    if (empty($_SESSION['rye_install_token'])) {
        $_SESSION['rye_install_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['rye_install_token'];
}
function instCheckToken($t)
{
    return $t !== '' && hash_equals($_SESSION['rye_install_token'] ?? '', $t);
}

/* ---------- 环境权限检查 ---------- */
function instDirOk($path)
{
    if (is_dir($path)) return is_writable($path);
    return @mkdir($path, 0755, true);
}
function instEnvChecks()
{
    $root = __DIR__;
    $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');

    // 关键函数是否被 disable_functions 禁用（自动更新/备份/插件安装依赖）
    $disabled = array_flip(array_filter(array_map('trim', explode(',', ini_get('disable_functions')))));
    $needFn = ['file_put_contents', 'copy', 'rename', 'unlink', 'mkdir', 'file_get_contents'];
    $missingFn = [];
    foreach ($needFn as $fn) {
        if (!function_exists($fn) || isset($disabled[$fn])) $missingFn[] = $fn;
    }

    $checks = [
        'PHP 版本（≥ 7.4）' => [$phpOk, $phpOk ? '当前 ' . PHP_VERSION : '当前 ' . PHP_VERSION . '，请升级 PHP'],
        '根目录可写（用于生成 config.php）' => [is_writable($root), is_writable($root) ? '可写' : '不可写'],
        'usr/uploads（图片上传/导入）'      => [instDirOk($root . '/usr/uploads'), '可写'],
        'usr/uploads/import（数据导入）'    => [instDirOk($root . '/usr/uploads/import'), '可写'],
        'usr/uploads/backup（备份/自动更新备份）' => [instDirOk($root . '/usr/uploads/backup'), '可写'],
        'usr/uploads/export（导出）'        => [instDirOk($root . '/usr/uploads/export'), '可写'],
        'usr/tmp-update（自动更新解压）'    => [instDirOk($root . '/usr/tmp-update'), '可写'],
        'usr/plugins（插件安装/更新）'      => [instDirOk($root . '/usr/plugins'), '可写'],
        'usr/themes（主题安装/切换）'       => [instDirOk($root . '/usr/themes'), '可写'],
    ];

    // 自动更新能力（WordPress 式一键更新）
    $zipOk  = class_exists('ZipArchive');
    $netOk  = function_exists('curl_init') || (bool)ini_get('allow_url_fopen');
    $shaOk  = function_exists('hash');
    $fnOk   = count($missingFn) === 0;
    $checks['自动更新：ZipArchive 扩展（解压升级包）'] = [$zipOk, $zipOk ? '已启用' : '未启用（php-zip 扩展），自动更新不可用'];
    $checks['自动更新：网络下载（curl / allow_url_fopen）'] = [$netOk, $netOk ? '可用' : '不可用（curl 未装且 allow_url_fopen 关闭），无法检查更新/下载升级包'];
    $checks['自动更新：SHA-256 校验（hash）'] = [$shaOk, $shaOk ? '可用' : '不可用（hash 扩展缺失），升级包校验将失败'];
    $checks['自动更新：关键函数未被禁用'] = [$fnOk, $fnOk ? '正常' : '被禁用：' . implode(', ', $missingFn) . '，自动更新覆盖文件会失败'];

    return $checks;
}
$envChecks = instEnvChecks();
$envAllOk = true;
foreach ($envChecks as $v) { if (!$v[0]) { $envAllOk = false; break; } }

$err = '';
$done = false;

/* ---------- 数据库连接测试（AJAX，只读不写） ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_db') {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_POST['_csrf'] ?? '';
    if (!instCheckToken($token)) {
        echo json_encode(['ok' => false, 'msg' => '表单已失效，请刷新页面重试。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $dbHost   = trim($_POST['db_host'] ?? 'localhost');
    $dbName   = trim($_POST['db_name'] ?? '');
    $dbUser   = trim($_POST['db_user'] ?? '');
    $dbPass   = (string)($_POST['db_pass'] ?? '');
    $dbCreate = !empty($_POST['db_create']);
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];
    try {
        $server = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, $opts);
        $msgs = ['✅ 服务器连接成功：' . $dbHost];
        if ($dbName !== '') {
            try {
                new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $opts);
                $msgs[] = '✅ 数据库「' . $dbName . '」存在且可正常连接';
            } catch (\PDOException $e) {
                if ((string)$e->getCode() === '1049') {
                    $msgs[] = $dbCreate
                        ? 'ℹ️ 数据库「' . $dbName . '」不存在——已勾选「自动创建数据库」，安装时会自动创建'
                        : 'ℹ️ 数据库「' . $dbName . '」不存在——请先手动创建，或勾选「自动创建数据库」';
                } else {
                    throw $e;
                }
            }
        }
        echo json_encode(['ok' => true, 'msg' => implode("\n", $msgs)], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        $code = $e instanceof \PDOException ? (string)$e->getCode() : '';
        $hint = '';
        if ($code === '1045')       $hint = '（用户名或密码错误，请检查数据库账号）';
        elseif ($code === '2002' || $code === '2003') $hint = '（无法连接主机，请检查主机名、端口或网络）';
        elseif ($code === '1044')   $hint = '（账号无权访问该数据库）';
        echo json_encode(['ok' => false, 'msg' => '❌ 连接失败：' . $e->getMessage() . $hint], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!instCheckToken($token)) {
        $err = '表单已失效，请刷新页面重试。';
    } else {
        $dbHost   = trim($_POST['db_host'] ?? 'localhost');
        $dbName   = trim($_POST['db_name'] ?? '');
        $dbUser   = trim($_POST['db_user'] ?? '');
        $dbPass   = (string)($_POST['db_pass'] ?? '');
        $dbCreate = !empty($_POST['db_create']);
        $dbPrefix = trim($_POST['db_prefix'] ?? 'rye_');
        if ($dbPrefix === '') $dbPrefix = 'rye_';
        $siteTitle = trim($_POST['site_title'] ?? '');
        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminMail = trim($_POST['admin_email'] ?? '');
        $adminPass = (string)($_POST['admin_pass'] ?? '');
        $adminPass2 = (string)($_POST['admin_pass2'] ?? '');

        if ($dbName === '' || $dbUser === '' || $siteTitle === '' || $adminUser === '' || $adminMail === '' || $adminPass === '') {
            $err = '请填写所有必填项。';
        } elseif (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $dbName)) {
            $err = '数据库名只能包含字母、数字、下划线、连字符、点。';
        } elseif (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $dbPrefix)) {
            $err = '表前缀需以字母开头，仅含字母、数字、下划线。';
        } elseif (!preg_match('/^[A-Za-z0-9_\-]{3,40}$/', $adminUser)) {
            $err = '管理员用户名需为 3-40 位字母、数字、下划线。';
        } elseif (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
            $err = '管理员邮箱格式不正确。';
        } elseif (strlen($adminPass) < 6) {
            $err = '管理员密码至少 6 位。';
        } elseif ($adminPass !== $adminPass2) {
            $err = '两次输入的密码不一致。';
        } else {
            try {
                $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8];
                // 1. 连接（先连服务器，不含库，便于自动建库）
                $server = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, $opts);
                // 2. 建库（可选）
                if ($dbCreate) {
                    $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                // 3. 建表（全列直接创建，结构与 upgrade.php 增量结果一致）
                $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $opts);
                installSchema($pdo, $dbPrefix);
                // 4. 默认数据
                installDefaults($pdo, $siteTitle, $adminUser, $adminMail, $adminPass, $dbPrefix);
                // 5. 写 config.php
                $cfg = "<?php\n/* RyeBlog 数据库配置 */\n"
                     . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                     . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                     . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                     . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                     . "define('PRETTY_URLS', false); // 伪静态开关：true 启用 / false 关闭（后台可切换）\n"
                     . "define('DB_PREFIX', " . var_export($dbPrefix, true) . "); // 表前缀\n";
                file_put_contents($configFile, $cfg);
                @chmod($configFile, 0644);
                $done = true;
            } catch (\Throwable $e) {
                $err = '安装失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                     . '（请检查数据库连接信息，或关闭「自动创建数据库」后先在数据库中手动建库）';
            }
        }
    }
}

/**
 * 建表：基础表 + 扩展表（与 verda.sql + upgrade.php 最终结构一致）
 */
function installSchema($pdo, $prefix = 'rye_')
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(64) NOT NULL UNIQUE,
        value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        slug VARCHAR(80) NOT NULL UNIQUE,
        description VARCHAR(255),
        parent_id INT NOT NULL DEFAULT 0,
        KEY idx_cat_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(40) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        display_name VARCHAR(40),
        email VARCHAR(190) NULL,
        phone VARCHAR(40) NULL,
        homepage VARCHAR(255) NULL,
        avatar_source ENUM('gravatar','local','upload') NOT NULL DEFAULT 'gravatar',
        avatar_url VARCHAR(255) NULL,
        bio VARCHAR(500) NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        status TINYINT(1) NOT NULL DEFAULT 1,
        reset_token VARCHAR(64) NULL,
        reset_expires DATETIME NULL,
        login_ip VARCHAR(45) NULL,
        login_at DATETIME NULL,
        created_at DATETIME,
        UNIQUE KEY uk_users_email (email),
        KEY idx_users_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        slug VARCHAR(200) NOT NULL,
        content MEDIUMTEXT,
        type ENUM('post','page') NOT NULL DEFAULT 'post',
        status ENUM('published','draft','trash') NOT NULL DEFAULT 'published',
        format ENUM('html','markdown') NOT NULL DEFAULT 'markdown',
        category_id INT NULL,
        author VARCHAR(40),
        created_at DATETIME,
        updated_at DATETIME,
        views INT NOT NULL DEFAULT 0,
        excerpt TEXT NULL,
        seo_description VARCHAR(300) NULL,
        seo_keywords VARCHAR(300) NULL,
        cover_image VARCHAR(255) NULL,
        allow_comment TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uk_posts_slug (slug),
        KEY idx_posts_type_status (type, status),
        KEY idx_posts_category (category_id),
        KEY idx_posts_created (created_at),
        KEY idx_posts_type_created (type, created_at),
        KEY idx_type_status_created (type, status, created_at),
        KEY idx_type_status_cat_created (type, status, category_id, created_at),
        FULLTEXT KEY ft_posts_title (title) WITH PARSER ngram
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        author VARCHAR(40) NOT NULL,
        email VARCHAR(120),
        website VARCHAR(200),
        content TEXT NOT NULL,
        status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
        author_ip VARCHAR(45) NULL,
        created_at DATETIME,
        KEY idx_comments_post (post_id),
        KEY idx_comments_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        `count` INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_tags_slug (slug),
        KEY idx_tags_count (`count`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}post_tags (
        post_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (post_id, tag_id),
        KEY idx_pt_tag (tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NULL,
        filename VARCHAR(255) NOT NULL,
        filepath VARCHAR(500) NOT NULL,
        filesize INT NOT NULL DEFAULT 0,
        mime VARCHAR(100) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_att_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_fav (user_id, post_id),
        KEY idx_fav_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}annotations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        quote TEXT,
        note TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_anno_user_time (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}corrections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        selected_text TEXT,
        suggested_text TEXT,
        reason TEXT,
        status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_corr_user_time (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}trail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NULL,
        post_title VARCHAR(200) NULL,
        ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_trail_user_time (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}menus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        location ENUM('top','footer') NOT NULL DEFAULT 'top',
        title VARCHAR(100) NOT NULL,
        url VARCHAR(255) NOT NULL,
        target VARCHAR(10) NOT NULL DEFAULT '_self',
        sort_order INT NOT NULL DEFAULT 0,
        parent_id INT NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_menu_loc (location, status, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        username VARCHAR(190) NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_la_ip (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 归档月计数物化表（P1）：读 O(1)，避免百万级全表 GROUP BY；写路径增量维护
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}archive_stats (
        ym CHAR(7) NOT NULL PRIMARY KEY,
        cnt INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * 默认数据：设置 / 未分类 / 默认文章 / 管理员 / 顶部菜单 / 默认插件
 */
function installDefaults($pdo, $siteTitle, $adminUser, $adminMail, $adminPass, $prefix = 'rye_')
{
    $defaults = [
        'site_title'           => $siteTitle,
        'site_slogan'          => '用文字记录生活',
        'site_seo_description' => '',
        'site_seo_keywords'    => '',
        'sidebar_sticky'       => '0',
        'placeholder_image'    => '',
        'site_url'             => '',
        'theme'                => 'fresh',
        'pretty_url'           => '0',
        'pretty_mode'          => 'slug',
        'posts_per_page'       => '10',
        'comment_moderation'   => '1',
        'footer_copyright'     => '© {{year}} {{site}}',
        'footer_support'       => 'Powered by <a href="https://ryeblog.com/" target="_blank" rel="noopener">RyeBlog</a>',
        'footer_icp'           => '',
        'footer_stats'         => '',
        'admin_lang'           => 'zh',
        'home_hero'            => '0',
        'author_card_show'     => '1',
        'author_card_title'    => '关于博主',
        'author_card_name'     => '博主',
        'author_card_avatar'   => '',
        'author_card_image'    => '',
        'author_card_bio'      => '热爱分享，用文字记录生活与技术。',
        'active_plugins'       => 'post-copyright',
        'db_version'           => RYEBLOG_VERSION,
        'hero_subtitle'        => '',
        'hero_btn1_text'       => '',
        'hero_btn1_url'        => '',
        'hero_btn2_text'       => '',
        'hero_btn2_url'        => '',
        'feature_1_title'      => '',
        'feature_1_desc'       => '',
        'feature_2_title'      => '',
        'feature_2_desc'       => '',
        'feature_3_title'      => '',
        'feature_3_desc'       => '',
        'docs_section_title'   => '学习目录',
        'docs_sidebar_title'   => '学习目录',
    ];
    $stmt = $pdo->prepare("INSERT INTO {$prefix}options (name, value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // 未分类
    $pdo->prepare("INSERT INTO {$prefix}categories (name, slug, description) VALUES (?, ?, ?)")
        ->execute(['未分类', 'uncategorized', '默认分类']);

    // 默认文章
    $welcome = <<<'MD'
# 欢迎使用 RyeBlog

恭喜，你的博客已经跑起来了！

**RyeBlog** 是一款轻量、免费、开源的中英文博客系统。**核心零第三方依赖**（纯 PHP + 标准库，无需 Composer/无第三方库），部署简单，专注写作本身。

## 快速开始

1. **写文章**：进入后台「写文章」，支持 Markdown 与 HTML 两种格式；
2. **分类与标签**：在「分类管理」「标签管理」中组织你的内容；
3. **换主题**：后台「主题」可切换内置主题，也可上传自定义主题；
4. **扩展功能**：后台「插件」可启用/停用插件（当前默认启用「文末版权」）；
5. **SEO 与伪静态**：后台「站点设置」可配置站点描述、开关伪静态（开启后需在服务器配置重写规则，后台会自动生成）。

## 关于本文

本文是安装时自动生成的示例文章，你可以直接在后台将其删除，然后开始第一篇真正的写作。

祝你写作愉快！
MD;

    $pdo->prepare("INSERT INTO {$prefix}posts (title, slug, content, type, status, format, category_id, author, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())")
        ->execute(['欢迎使用 RyeBlog', 'welcome', $welcome, 'post', 'published', 'markdown', 1, $adminUser]);

    // 管理员
    $pdo->prepare("INSERT INTO {$prefix}users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
        ->execute([$adminUser, $adminMail, password_hash($adminPass, PASSWORD_DEFAULT), 'admin']);

    // 顶部导航：分类 / 标签 / RSS / 网站地图（动态占位符，自动适配伪静态开关）
    $pdo->prepare("INSERT INTO {$prefix}menus (location, title, url, target, sort_order, status) VALUES (?, ?, ?, ?, ?, 1)");
    $menus = [
        ['top', '分类', '{{cat_first}}', '_self', 0],
        ['top', '标签', '{{tags}}', '_self', 1],
        ['top', 'RSS', '{{rss}}', '_self', 2],
        ['top', '网站地图', '{{sitemap}}', '_self', 3],
    ];
    foreach ($menus as $m) {
        $pdo->prepare("INSERT INTO {$prefix}menus (location, title, url, target, sort_order, status) VALUES (?, ?, ?, ?, ?, 1)")->execute($m);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装 RyeBlog</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif; background: #f0f4f1; color: #1f2a22; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.card { background: #fff; border-radius: 14px; box-shadow: 0 10px 40px rgba(46,107,53,.12); width: 100%; max-width: 560px; padding: 34px 38px; }
h1 { font-size: 24px; margin-bottom: 4px; color: #2e6b35; }
.sub { color: #6b7d70; font-size: 13.5px; margin-bottom: 24px; }
label { display: block; font-size: 13.5px; font-weight: 600; margin: 14px 0 5px; color: #2e6b35; }
input[type=text], input[type=password], input[type=email] { width: 100%; padding: 10px 12px; border: 1px solid #d7e7d9; border-radius: 8px; font-size: 14px; outline: none; }
input:focus { border-color: #43a047; }
.row { display: flex; gap: 10px; }
.row > div { flex: 1; }
.check { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 400; margin-top: 12px; }
.check input { width: auto; }
h2 { font-size: 16px; color: #2e6b35; margin: 24px 0 2px; padding-top: 20px; border-top: 1px solid #eef4ef; }
.btn { width: 100%; margin-top: 26px; padding: 12px; background: #43a047; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn:hover { background: #357a3e; }
.err { background: #fdecea; border: 1px solid #f5c6c6; color: #b71c1c; padding: 10px 14px; border-radius: 8px; font-size: 13.5px; margin-bottom: 14px; }
.ok { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; padding: 12px 16px; border-radius: 8px; font-size: 14px; line-height: 1.8; }
.ok a { color: #1b5e20; font-weight: 600; }
.hint { font-size: 12px; color: #6b7d70; margin-top: 4px; }
.envcheck { background: #f6faf6; border: 1px solid #ddebd9; border-radius: 10px; padding: 14px 16px; margin-bottom: 6px; }
.envcheck-title { font-size: 14px; color: #2e6b35; margin: 0 0 8px; border: none; padding: 0; }
.envcheck-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.envcheck-table td { padding: 4px 6px; border-bottom: 1px dashed #e4efe2; }
.envcheck-name { color: #3a4a3e; }
.envcheck-status { text-align: right; font-weight: 600; white-space: nowrap; }
.ok-dot { color: #2e7d32; }
.bad-dot { color: #c62828; }
.envcheck-ok { margin-top: 8px; font-size: 13px; font-weight: 600; color: #2e7d32; }
.btn-test { width: auto; margin: 10px 0 0; padding: 9px 18px; background: #fff; color: #2e6b35; border: 1.5px solid #43a047; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-test:hover { background: #e8f5e9; }
.dbres { margin-top: 10px; padding: 10px 14px; border-radius: 8px; font-size: 13px; line-height: 1.8; white-space: pre-line; display: none; }
.dbres.ok { display: block; }
.dbres.fail { display: block; }
</style>
</head>
<body>
<div class="card">
<?php if ($done): ?>
    <h1>安装完成 🎉</h1>
    <p class="sub">你的 RyeBlog 已经就绪。</p>
    <div class="ok">
        <p>✅ 数据库与表结构已创建</p>
        <p>✅ 默认内容已生成：<strong>未分类</strong> + 文章《欢迎使用 RyeBlog》</p>
        <p>✅ 默认插件：文末版权</p>
        <p>✅ 管理员账号：<strong><?php echo htmlspecialchars($adminUser ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <p style="margin-top:10px">
            <a href="index.php">前往前台 →</a> &nbsp;|&nbsp;
            <a href="admin/">进入后台 →</a>
        </p>
    </div>
    <p class="hint" style="margin-top:14px">安全提示：安装完成后请删除服务器上的 <code>install.php</code> 文件。伪静态为关闭状态（纯动态 URL），需要时可在后台「站点设置」开启。</p>
    <p class="hint" style="margin-top:6px">💡 自动更新已就绪：以后后台检测到新版本时，在「仪表盘」点击「一键自动更新」，系统会自动备份数据库与代码并完成升级，无需手动操作。</p>
<?php else: ?>
    <h1>安装 RyeBlog</h1>
    <p class="sub">轻量 · 免费 · 开源的中英文博客系统</p>

    <?php if ($err): ?><div class="err"><?php echo $err; ?></div><?php endif; ?>
    <?php if (is_file($configFile)): ?>
        <div class="err">检测到 <code>config.php</code> 已存在（但数据库连接失败）。如需重新安装，请先删除该文件。</div>
    <?php endif; ?>

    <div class="envcheck">
        <h2 class="envcheck-title">环境检查</h2>
        <table class="envcheck-table">
        <?php foreach ($envChecks as $name => $v): list($ok, $hint) = $v; ?>
            <tr>
                <td class="envcheck-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="envcheck-status"><?php echo $ok ? '<span class="ok-dot">✓</span> ' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') : '<span class="bad-dot">✗</span> ' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
        </table>
        <?php if (!$envAllOk): ?>
            <div class="err" style="margin-top:10px">
                ⚠️ 存在环境/目录问题，安装后部分功能（上传/导入/备份/自动更新/插件主题安装）将不可用。<br>
                目录权限请在服务器上执行：<code>chown -R www:www <?php echo htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8'); ?>/usr</code><br>
                以及 <code>chmod -R 755 <?php echo htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8'); ?>/usr</code><br>
                缺少扩展请安装：<code>php-zip</code>（自动更新必需）、<code>php-curl</code> 或开启 <code>allow_url_fopen</code>（联网检查更新必需）。
            </div>
        <?php else: ?>
            <div class="envcheck-ok">✅ 所有环境检查通过——含「一键自动更新」所需能力，安装后可自动升级</div>
        <?php endif; ?>
    </div>

    <form method="post" autocomplete="off" id="installForm">
        <input type="hidden" name="_csrf" value="<?php echo instToken(); ?>">
        <h2>数据库配置</h2>
        <label>数据库主机</label>
        <input type="text" name="db_host" value="localhost" required>
        <label>数据库名</label>
        <input type="text" name="db_name" placeholder="如：blog" required>
        <label>数据库用户</label>
        <input type="text" name="db_user" required>
        <label>数据库密码</label>
        <input type="password" name="db_pass">
        <label>表前缀</label>
        <input type="text" name="db_prefix" value="rye_" pattern="[A-Za-z_][A-Za-z0-9_]*" title="以字母开头，仅含字母、数字、下划线">
        <p class="hint">默认 rye_。如需与其他程序共存于同一数据库，可改为其他前缀（如 myblog_）。</p>
        <label class="check"><input type="checkbox" name="db_create" value="1" checked> 自动创建数据库（需要账号有建库权限；否则请先手动建库）</label>
        <button type="button" class="btn-test" id="btnTestDb">测试数据库连接</button>
        <div class="dbres" id="testDbRes"></div>

        <h2>站点信息</h2>
        <label>站点标题</label>
        <input type="text" name="site_title" placeholder="如：我的博客" required>

        <h2>管理员账号</h2>
        <label>用户名</label>
        <input type="text" name="admin_user" placeholder="3-40 位字母、数字、下划线" required>
        <label>邮箱</label>
        <input type="email" name="admin_email" required>
        <div class="row">
            <div>
                <label>密码</label>
                <input type="password" name="admin_pass" placeholder="至少 6 位" required>
            </div>
            <div>
                <label>确认密码</label>
                <input type="password" name="admin_pass2" required>
            </div>
        </div>

        <button class="btn" type="submit">开始安装</button>
        <p class="hint" style="margin-top:10px">默认配置：纯动态 URL、主题 fresh、未分类 + 一篇示例文章、默认启用「文末版权」插件。</p>
    </form>
<?php endif; ?>
</div>
<script>
(function () {
    var btn = document.getElementById('btnTestDb');
    if (!btn) return;
    var form = document.getElementById('installForm');
    var res = document.getElementById('testDbRes');
    btn.addEventListener('click', function () {
        var host = form.db_host.value.trim(), name = form.db_name.value.trim(), user = form.db_user.value.trim();
        if (host === '' || name === '' || user === '') {
            res.className = 'dbres fail';
            res.textContent = '请先填写数据库主机、数据库名和数据库用户。';
            return;
        }
        btn.disabled = true;
        btn.textContent = '测试中…';
        res.className = 'dbres ok';
        res.textContent = '正在测试连接，请稍候…';
        var body = new URLSearchParams(new FormData(form));
        body.set('action', 'test_db');
        fetch(location.href, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                res.className = 'dbres ' + (j.ok ? 'ok' : 'fail');
                res.textContent = j.msg || '无返回结果';
            })
            .catch(function (e) {
                res.className = 'dbres fail';
                res.textContent = '测试失败（网络或服务器错误）：' + e.message;
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = '测试数据库连接';
            });
    });
})();
</script>
</body>
</html>
