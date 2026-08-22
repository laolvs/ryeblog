<?php
/**
 * RyeBlog 版本升级脚本（幂等，可重复执行）
 *
 * 用法（命令行）：
 *   php upgrade.php
 *
 * 作用：
 *   1. 读取数据库当前版本（db_version 选项，旧站为 1.0.0）；
 *   2. 按版本顺序执行增量迁移（建表/补列/补索引/默认值，均幂等，不破坏已有数据）；
 *   3. 全部完成后把 db_version 更新为当前版本 RYEBLOG_VERSION。
 *
 * 发版约定：每次发布新版本，若涉及数据库结构/默认数据变更，必须在本文件追加对应迁移块。
 */
require_once __DIR__ . '/inc/functions.php';

/* ---------- Web 访问保护 ----------
 * upgrade.php 是系统级脚本，仅允许命令行执行；
 * Web 访问时必须已登录管理员（防止未授权探测/触发迁移）。
 * 兼容两种会话：后台管理员（rye_admin）与论坛管理员（rye_user + role=admin）。
 */
if (PHP_SAPI !== 'cli') {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $u = $_SESSION['rye_admin'] ?? $_SESSION['rye_user'] ?? null;
    $ok = false;
    if ($u) {
        $row = dbOne('SELECT role FROM vd_users WHERE id=? AND status=1', [(int)$u]);
        $ok = $row && $row['role'] === 'admin';
    }
    if (!$ok) {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
        }
        exit('403 Forbidden：升级脚本仅允许命令行执行（php upgrade.php），或登录后台管理员后访问。');
    }
}

if (!db()) {
    fwrite(STDERR, "无法连接数据库，请先确认 config.php 已生成。\n");
    exit(1);
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- 版本信息 ---------- */
$dbVersion = (string)getOption('db_version', '');
if ($dbVersion === '') {
    // 旧站无版本记录：按 1.0.0 处理（1.0.x 无 db_version 机制）
    $dbVersion = '1.0.0';
}
// 自动更新（admin/update.php）可通过 常量/环境变量 指定目标版本；
// 优先顺序：常量 > $_SERVER > getenv（兼容未禁用 putenv 的环境）
$target = (defined('RYEBLOG_UPGRADE_VERSION') ? RYEBLOG_UPGRADE_VERSION : null)
       ?: ($_SERVER['RYEBLOG_UPGRADE_VERSION'] ?? null)
       ?: @getenv('RYEBLOG_UPGRADE_VERSION')
       ?: (defined('RYEBLOG_VERSION') ? RYEBLOG_VERSION : '1.1.0');
echo "== RyeBlog 数据库升级 ==\n";
echo "  当前数据库版本：{$dbVersion} → 目标版本：{$target}\n";

/** 表名按当前前缀映射（vd_users → {prefix}users） */
function pf($table)
{
    $prefix = dbPrefix();
    return (strpos($table, $prefix) === 0) ? $table : $prefix . substr($table, 3);
}

function colExists($table, $col)
{
    $table = pf($table);
    return (int)dbOne(
        "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?",
        [DB_NAME, $table, $col]
    )['c'] > 0;
}

function addCol($table, $col, $def)
{
    $table = pf($table);
    if (!colExists($table, $col)) {
        dbQuery("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        echo "  + {$table}.{$col}\n";
    }
}

function idxExists($table, $idx)
{
    $table = pf($table);
    return (int)dbOne(
        "SELECT COUNT(*) AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?",
        [DB_NAME, $table, $idx]
    )['c'] > 0;
}

function addIdx($table, $idx, $cols)
{
    $table = pf($table);
    if (!idxExists($table, $idx)) {
        dbQuery("ALTER TABLE `$table` ADD INDEX `$idx` ($cols)");
        echo "  + index {$table}.{$idx}\n";
    }
}

/** 全文索引（ngram 中文分词）。不支持 ngram 的环境静默跳过，避免升级中断。 */
function addFulltextIdx($table, $idx, $cols)
{
    $table = pf($table);
    if (!idxExists($table, $idx)) {
        try {
            dbQuery("ALTER TABLE `$table` ADD FULLTEXT INDEX `$idx` ($cols) WITH PARSER ngram");
            echo "  + fulltext {$table}.{$idx}\n";
        } catch (\Throwable $e) {
            echo "  ! 跳过全文索引 {$table}.{$idx}（环境不支持 ngram）：" . $e->getMessage() . "\n";
        }
    }
}

echo "== RyeBlog 数据库升级 ==\n";

/* ---------- 扩展 vd_users ---------- */
addCol('vd_users', 'email',        "VARCHAR(190) NULL DEFAULT NULL AFTER username");
addCol('vd_users', 'phone',        "VARCHAR(40) NULL DEFAULT NULL AFTER email");
addCol('vd_users', 'homepage',     "VARCHAR(255) NULL DEFAULT NULL AFTER phone");
addCol('vd_users', 'avatar_source',"ENUM('gravatar','local','upload') NOT NULL DEFAULT 'gravatar' AFTER homepage");
addCol('vd_users', 'avatar_url',   "VARCHAR(255) NULL DEFAULT NULL AFTER avatar_source");
addCol('vd_users', 'bio',          "VARCHAR(500) NULL DEFAULT NULL AFTER avatar_url");
addCol('vd_users', 'role',         "VARCHAR(20) NOT NULL DEFAULT 'user' AFTER bio");
addCol('vd_users', 'status',       "TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
addCol('vd_users', 'reset_token',  "VARCHAR(64) NULL DEFAULT NULL AFTER status");
addCol('vd_users', 'reset_expires',"DATETIME NULL DEFAULT NULL AFTER reset_token");
addCol('vd_users', 'login_ip',     "VARCHAR(45) NULL DEFAULT NULL AFTER reset_expires");
addCol('vd_users', 'login_at',     "DATETIME NULL DEFAULT NULL AFTER login_ip");
addIdx('vd_users', 'uk_users_email', 'email');
addIdx('vd_users', 'idx_users_username', 'username');

// 给原管理员补 role/email
$pdo->exec(applyDbPrefix("UPDATE vd_users SET role='admin' WHERE role='user' AND id=1"));
$pdo->exec(applyDbPrefix("UPDATE vd_users SET email=CONCAT(username,'@example.com') WHERE email IS NULL OR email=''"));

/* ---------- 扩展 vd_posts ---------- */
addCol('vd_posts', 'excerpt',         "TEXT NULL DEFAULT NULL AFTER content");
addCol('vd_posts', 'seo_description', "VARCHAR(300) NULL DEFAULT NULL AFTER excerpt");
addCol('vd_posts', 'seo_keywords',    "VARCHAR(300) NULL DEFAULT NULL AFTER seo_description");
addCol('vd_posts', 'cover_image',     "VARCHAR(255) NULL DEFAULT NULL AFTER seo_keywords");
addCol('vd_posts', 'allow_comment',   "TINYINT(1) NOT NULL DEFAULT 1 AFTER cover_image");
addIdx('vd_posts', 'idx_posts_type_status', 'type, status');
addIdx('vd_posts', 'idx_posts_category', 'category_id');
addIdx('vd_posts', 'uk_posts_slug', 'slug');

/* ---------- 双语内容字段（中/英）----------
 * 注意：英文库（*_en 列与英文选项）由 english-admin 插件 activate() 安装、
 * deactivate() 清理。核心库保持纯中文（新装无英文列），此处不再添加。 */

/* ---------- 标签 ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    `count` INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    KEY idx_pt_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 附件 ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NULL DEFAULT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    filesize INT NOT NULL DEFAULT 0,
    mime VARCHAR(100) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_att_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 收藏 ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fav (user_id, post_id),
    KEY idx_fav_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 划线（评论划线标注） ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_annotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    quote_text TEXT NOT NULL,
    note VARCHAR(500) NULL DEFAULT NULL,
    anchor VARCHAR(120) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_anno_post (post_id),
    KEY idx_anno_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 纠错 ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_corrections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL DEFAULT NULL,
    post_id INT NOT NULL,
    selected_text TEXT NOT NULL,
    suggested_text TEXT NULL DEFAULT NULL,
    reason VARCHAR(500) NULL DEFAULT NULL,
    status ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_corr_post (post_id),
    KEY idx_corr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 浏览轨迹 ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL DEFAULT NULL,
    post_id INT NOT NULL,
    post_title VARCHAR(255) NOT NULL DEFAULT '',
    ip VARCHAR(45) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trail_user (user_id),
    KEY idx_trail_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 菜单 ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location ENUM('top','footer') NOT NULL DEFAULT 'top',
    title VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    target VARCHAR(10) NOT NULL DEFAULT '_self',
    sort_order INT NOT NULL DEFAULT 0,
    parent_id INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_menu_loc (location, status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 默认选项 ---------- */
$defaults = [
    'site_title'         => 'RyeBlog',
    'site_slogan'        => '免费开源的中英文博客系统！',
    'site_seo_description' => '',
    'site_seo_keywords'    => '',
    'sidebar_sticky'       => '0',
    'placeholder_image'    => '',
    'site_url'           => 'https://ryeblog.com/',
    'theme'              => 'fresh',
    'pretty_url'         => '1',
    'pretty_mode'        => 'slug',
    'posts_per_page'     => '10',
    'comment_moderation' => '1',
    'footer_copyright'   => '© {{year}} {{site}}',
    'footer_support'     => 'Powered by <a href="https://ryeblog.com/" target="_blank" rel="noopener">RyeBlog</a> · 免费开源的中英文博客系统',
    'footer_icp'         => '',
    'footer_stats'       => '',
    'admin_lang'         => 'zh',
    'author_card_show'   => '1',
    'author_card_title'  => '关于博主',
    'author_card_name'   => '博主',
    'author_card_avatar' => '',
    'author_card_image'  => '',
    'author_card_bio'    => '热爱分享，用文字记录生活与技术。',
];
foreach ($defaults as $k => $v) {
    $exists = dbOne('SELECT COUNT(*) c FROM vd_options WHERE name=?', [$k])['c'];
    if (!$exists) {
        dbQuery('INSERT INTO vd_options (name, value) VALUES (?, ?)', [$k, $v]);
        echo "  + option {$k}\n";
    } else {
        // 升级旧品牌：把"青笺"相关默认替换为 RyeBlog
        if (in_array($k, ['site_title','site_slogan','footer_support'], true)) {
            $cur = getOption($k);
            if ($cur === '青笺博客' || $cur === '用文字记录生活' || strpos((string)$cur, '青笺') !== false || strpos((string)$cur, 'Verda') !== false) {
                dbQuery('UPDATE vd_options SET value=? WHERE name=?', [$v, $k]);
                echo "  ~ option {$k} (品牌升级)\n";
            }
        }
    }
}

/* ---------- 默认菜单 ---------- */
$menuCount = (int)dbOne("SELECT COUNT(*) c FROM vd_menus")['c'];
if ($menuCount === 0) {
    dbQuery("INSERT INTO vd_menus (location,title,url,target,sort_order,status) VALUES
        ('top','首页','{{home}}','_self',0,1),
        ('footer','关于','{{home}}page/about','_self',0,1),
        ('footer','后台管理','{{home}}admin/','_self',1,1)");
    echo "  + 默认菜单\n";
}

/* ---------- 给示例文章补 SEO/标签 ---------- */
if ((int)dbOne("SELECT COUNT(*) c FROM vd_tags")['c'] === 0) {
    $seedTags = ['博客','写作','教程','技术','随笔','开源'];
    foreach ($seedTags as $t) {
        dbQuery('INSERT IGNORE INTO vd_tags (name, slug, count) VALUES (?, ?, 0)', [$t, slugify($t)]);
    }
    // 给现有文章随机绑前两个标签
    $tagIds = dbAll('SELECT id FROM vd_tags ORDER BY id LIMIT 3');
    $posts  = dbAll('SELECT id FROM vd_posts WHERE type="post"');
    foreach ($posts as $i => $p) {
        foreach ($tagIds as $j => $tg) {
            if (($i + $j) % 2 === 0) {
                dbQuery('INSERT IGNORE INTO vd_post_tags (post_id, tag_id) VALUES (?, ?)', [$p['id'], $tg['id']]);
            }
        }
    }
    // 刷新 count
    $pdo->exec(applyDbPrefix("UPDATE vd_tags t SET `count`=(SELECT COUNT(*) FROM vd_post_tags pt WHERE pt.tag_id=t.id)"));
    echo "  + 种子标签\n";
}

/* ---------- 性能索引 ---------- */
addIdx('vd_posts', 'idx_posts_created', 'created_at');
addIdx('vd_posts', 'idx_posts_type_created', 'type, created_at');
addIdx('vd_comments', 'idx_comments_post', 'post_id');
addIdx('vd_comments', 'idx_comments_status', 'status');
addIdx('vd_trail', 'idx_trail_user_time', 'user_id, created_at');
addIdx('vd_favorites', 'idx_fav_user_time', 'user_id, created_at');
addIdx('vd_annotations', 'idx_anno_user_time', 'user_id, created_at');
addIdx('vd_corrections', 'idx_corr_user_time', 'user_id, created_at');

/* ---------- 登录限流表 ---------- */
$pdo->exec(applyDbPrefix("CREATE TABLE IF NOT EXISTS vd_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    username VARCHAR(190) NULL DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ---------- 纠错状态修正（兼容旧枚举值） ---------- */
// 确保 vd_corrections.status 支持 'accepted' 值
$corrStatusCol = dbOne("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='vd_corrections' AND COLUMN_NAME='status'", [DB_NAME]);
if ($corrStatusCol && strpos($corrStatusCol['COLUMN_TYPE'], 'accepted') === false) {
    $pdo->exec(applyDbPrefix("ALTER TABLE vd_corrections MODIFY status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending'"));
    echo "  ~ vd_corrections.status 枚举扩展\n";
}

/* ---------- 版本化迁移块 ----------
 * 约定：从 dbVersion 起，按发布顺序追加；条件用 version_compare 判断，
 *       迁移内容必须幂等（可重复执行安全）。
 * ================================================================ */
if (version_compare($dbVersion, '1.1.0', '<')) {
    /* 1.1.0：新增 tags 标签汇总页相关选项/菜单占位符解析（resolveMenuUrl 占位符无需建表）；
     * 无结构变更，仅确保默认菜单带 {{rss}}/{{sitemap}} 等占位符时可用——占位符由代码解析，无需迁移。 */
    echo "  ~ 1.1.0：标签汇总页 tags.php 与菜单占位符（代码层面，无结构变更）\n";
    /* 评论防垃圾：vd_comments.author_ip 列 */
    addCol('vd_comments', 'author_ip', "VARCHAR(45) NULL DEFAULT NULL AFTER status");
}
if (version_compare($dbVersion, '1.2.0', '<')) {
    echo "  ~ 1.2.0：子分类支持（vd_categories.parent_id）+ 数据库表前缀（DB_PREFIX，代码层支持）\n";
    /* 子分类支持：vd_categories.parent_id */
    addCol('vd_categories', 'parent_id', 'INT NOT NULL DEFAULT 0 AFTER description');
    addIdx('vd_categories', 'idx_cat_parent', 'parent_id');
}
if (version_compare($dbVersion, '1.3.0', '<')) {
    echo "  ~ 1.3.0：性能优化（列表复合索引）+ 安全加固（代码层）\n";
    /* 首页/分类/标签列表热点查询复合索引：type+status 过滤 + created_at 排序 */
    addIdx('vd_posts', 'idx_posts_type_status_created', 'type, status, created_at');
}
if (version_compare($dbVersion, '1.4.0', '<')) {
    echo "  ~ 1.4.0：文章回收站（status 增加 trash 值）+ 远程图片本地化选项 + 维护模式选项\n";
    /* vd_posts.status 枚举增加 trash：ALTER 重建枚举（MariaDB/MySQL 均支持） */
    $pdo->exec(applyDbPrefix('ALTER TABLE vd_posts MODIFY COLUMN status ENUM(\'published\',\'draft\',\'trash\') NOT NULL DEFAULT \'published\''));
    /* 选项默认值（getOption 缺省已覆盖，写入仅为了设置页回显一致） */
    $pdo->prepare(applyDbPrefix('INSERT INTO vd_options (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=value'))
         ->execute(['localize_remote_images', '1']);
    $pdo->prepare(applyDbPrefix('INSERT INTO vd_options (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=value'))
         ->execute(['site_maintenance', '0']);
    $pdo->prepare(applyDbPrefix('INSERT INTO vd_options (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=value'))
         ->execute(['trash_retention_days', '90']);
}
if (version_compare($dbVersion, '1.4.1', '<')) {
    echo "  ~ 1.4.1：列表查询分类覆盖索引 + 标签计数索引 + 标题全文索引（ngram），保障百万级数据下列表/标签云/搜索性能\n";
    /* 分类列表热点查询复合索引：type+status+category_id 过滤 + created_at 排序；
     * 与 1.3.0 的 idx_posts_type_status_created 互补，避免百万级数据时 filesort 超时。 */
    addIdx('vd_posts', 'idx_type_status_created', 'type, status, created_at');
    addIdx('vd_posts', 'idx_type_status_cat_created', 'type, status, category_id, created_at');
    /* 标签云热门排序（ORDER BY count DESC LIMIT N）走索引，避免 55 万标签 filesort。 */
    addIdx('vd_tags', 'idx_tags_count', '`count`');
    /* 中文全文索引：搜索由 LIKE 全表扫改为 MATCH，百万级秒回（getPosts 自动探测启用）。 */
    addFulltextIdx('vd_posts', 'ft_posts_title', 'title');
}
if (version_compare($dbVersion, '1.4.2', '<')) {
    echo "  ~ 1.4.2：归档月计数物化表（读 O(1) 替代百万级全表 GROUP BY）\n";
    dbQuery("CREATE TABLE IF NOT EXISTS " . pf('vd_archive_stats') . " (
        ym CHAR(7) NOT NULL PRIMARY KEY,
        cnt INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* 存量初始化：一次性全量统计（百万级约 3s），之后写路径增量维护。
     * 注意：pf() 只接受「vd_ 前缀全名」（短名会被 substr(3) 截错），故此处传 vd_posts/vd_archive_stats。 */
    try {
        dbQuery("INSERT IGNORE INTO " . pf('vd_archive_stats') . " (ym, cnt)
                 SELECT DATE_FORMAT(created_at,'%Y-%m'), COUNT(*)
                 FROM " . pf('vd_posts') . " WHERE type='post' AND status='published'
                 GROUP BY DATE_FORMAT(created_at,'%Y-%m')");
    } catch (\Throwable $e) {
        echo "  ! 归档统计初始化失败（可后续在后台「高级设置→重建计数」重试）：" . $e->getMessage() . "\n";
    }
}

/* ---------- 写入当前版本 ---------- */
$pdo->prepare(applyDbPrefix('INSERT INTO vd_options (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=?'))
    ->execute(['db_version', $target, $target]);
echo "== 升级完成（数据库版本：{$target}）==\n";
