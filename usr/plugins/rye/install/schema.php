<?php
/**
 * RYE社区（RyeBlog 插件）—— 数据表结构
 *
 * 用户体系复用 RyeBlog 的 vd_users，因此不建 ryebbs_users；
 * 论坛专属字段放在 ryebbs_user_ext（user_id 关联 vd_users.id）。
 */

function rye_install_schema($P)
{
    $tables = [
        "CREATE TABLE IF NOT EXISTS {$P}forum_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(60) NOT NULL DEFAULT '',
            display_order INT NOT NULL DEFAULT 0,
            is_hidden TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}forums (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_id INT NOT NULL DEFAULT 0,
            name VARCHAR(60) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            topic_category_enabled TINYINT(1) NOT NULL DEFAULT 0,
            topic_categories VARCHAR(255) NOT NULL DEFAULT '',
            icon VARCHAR(255) NOT NULL DEFAULT '',
            name_color VARCHAR(20) NOT NULL DEFAULT '',
            show_on_index TINYINT(1) NOT NULL DEFAULT 1,
            display_order INT NOT NULL DEFAULT 0,
            thread_count INT NOT NULL DEFAULT 0,
            post_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY section_id (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}threads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            forum_id INT NOT NULL DEFAULT 0,
            user_id INT NOT NULL DEFAULT 0,
            topic_category VARCHAR(40) NOT NULL DEFAULT '',
            title VARCHAR(120) NOT NULL DEFAULT '',
            content MEDIUMTEXT NOT NULL,
            views INT NOT NULL DEFAULT 0,
            replies INT NOT NULL DEFAULT 0,
            is_top TINYINT(1) NOT NULL DEFAULT 0,
            is_good TINYINT(1) NOT NULL DEFAULT 0,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            is_closed TINYINT(1) NOT NULL DEFAULT 0,
            visibility_type TINYINT(1) NOT NULL DEFAULT 0,
            visibility_cost INT NOT NULL DEFAULT 0,
            last_post_at DATETIME DEFAULT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY forum_id (forum_id),
            KEY updated_at (updated_at),
            KEY is_top (is_top)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL DEFAULT 0,
            user_id INT NOT NULL DEFAULT 0,
            content MEDIUMTEXT NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            is_top TINYINT(1) NOT NULL DEFAULT 0,
            floor INT NOT NULL DEFAULT 0,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY thread_id (thread_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}post_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL DEFAULT 0,
            post_id INT NOT NULL DEFAULT 0,
            user_id INT NOT NULL DEFAULT 0,
            content TEXT NOT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY post_id (post_id),
            KEY thread_id (thread_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}reactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL DEFAULT 0,
            post_id INT NOT NULL DEFAULT 0,
            user_id INT NOT NULL DEFAULT 0,
            reaction_type VARCHAR(10) NOT NULL DEFAULT 'like',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            UNIQUE KEY user_post (user_id, thread_id, post_id, reaction_type),
            KEY post_id (post_id),
            KEY thread_id (thread_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            thread_id INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY user_thread (user_id, thread_id),
            KEY thread_id (thread_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            thread_id INT NOT NULL DEFAULT 0,
            post_id INT NOT NULL DEFAULT 0,
            related_user_id INT NOT NULL DEFAULT 0,
            message VARCHAR(180) NOT NULL DEFAULT '',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY user_read (user_id, is_read),
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL DEFAULT 0,
            to_user_id INT NOT NULL DEFAULT 0,
            content TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY from_user_id (from_user_id),
            KEY to_user_id (to_user_id),
            KEY to_read (to_user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}follows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT NOT NULL DEFAULT 0,
            following_id INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY follower_following (follower_id, following_id),
            KEY following_id (following_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL DEFAULT 0,
            post_id INT NOT NULL DEFAULT 0,
            user_id INT NOT NULL DEFAULT 0,
            file_path VARCHAR(255) NOT NULL DEFAULT '',
            original_name VARCHAR(255) NOT NULL DEFAULT '',
            file_ext VARCHAR(20) NOT NULL DEFAULT '',
            file_size INT NOT NULL DEFAULT 0,
            download_count INT NOT NULL DEFAULT 0,
            description VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY thread_id (thread_id),
            KEY post_id (post_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}drafts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            title VARCHAR(120) NOT NULL DEFAULT '',
            content MEDIUMTEXT NOT NULL,
            forum_id INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}sensitive_words (
            id INT AUTO_INCREMENT PRIMARY KEY,
            word VARCHAR(255) NOT NULL,
            action VARCHAR(10) NOT NULL DEFAULT 'replace',
            replacement VARCHAR(255) NOT NULL DEFAULT '**',
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}ip_bans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            reason VARCHAR(255) NOT NULL DEFAULT '',
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            KEY ip (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT NOT NULL DEFAULT 0,
            reported_user_id INT NOT NULL DEFAULT 0,
            target_type VARCHAR(20) NOT NULL DEFAULT '',
            target_id INT NOT NULL DEFAULT 0,
            reason VARCHAR(500) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            resolved_at DATETIME DEFAULT NULL,
            KEY reported_user_id (reported_user_id),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}settings (
            setting_key VARCHAR(60) NOT NULL DEFAULT '',
            setting_value TEXT NOT NULL,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}signins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            signin_date DATE NOT NULL,
            continuous_days INT NOT NULL DEFAULT 1,
            reward_coins INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY user_date (user_id, signin_date),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            position VARCHAR(30) NOT NULL DEFAULT '',
            title VARCHAR(80) NOT NULL DEFAULT '',
            image_path VARCHAR(255) NOT NULL DEFAULT '',
            link_url VARCHAR(255) NOT NULL DEFAULT '',
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY position (position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}navs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(40) NOT NULL DEFAULT '',
            url VARCHAR(255) NOT NULL DEFAULT '',
            display_order INT NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            KEY enabled_order (is_enabled, display_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}medals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL DEFAULT '',
            image VARCHAR(255) NOT NULL DEFAULT '',
            min_online_hours INT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}user_medals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            medal_id INT NOT NULL DEFAULT 0,
            awarded_at DATETIME NOT NULL,
            UNIQUE KEY user_medal (user_id, medal_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}coin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            amount INT NOT NULL DEFAULT 0,
            type VARCHAR(32) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}moderator_forums (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            forum_id INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY user_forum (user_id, forum_id),
            KEY forum_id (forum_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}moderator_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            moderator_id INT NOT NULL DEFAULT 0,
            action VARCHAR(40) NOT NULL DEFAULT '',
            target_type VARCHAR(20) NOT NULL DEFAULT '',
            target_id INT NOT NULL DEFAULT 0,
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY moderator_created (moderator_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            uri VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY ip_created (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}invite_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(32) NOT NULL DEFAULT '',
            type VARCHAR(10) NOT NULL DEFAULT 'free',
            created_by INT NOT NULL DEFAULT 0,
            used_by INT NOT NULL DEFAULT 0,
            used_at DATETIME DEFAULT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            UNIQUE KEY code (code),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}oauth_bindings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            oauth_type VARCHAR(20) NOT NULL DEFAULT '',
            oauth_id VARCHAR(128) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            UNIQUE KEY oauth (oauth_type, oauth_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            token VARCHAR(128) NOT NULL DEFAULT '',
            expires_at DATETIME NOT NULL,
            UNIQUE KEY token (token),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}thread_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL DEFAULT 0,
            user_id INT NOT NULL DEFAULT 0,
            cost INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY thread_user (thread_id, user_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}online (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            user_id INT NOT NULL DEFAULT 0,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            last_activity DATETIME NOT NULL,
            UNIQUE KEY uniq_session (session_id),
            KEY idx_activity (last_activity),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            page_url VARCHAR(500) NOT NULL DEFAULT '',
            referrer VARCHAR(500) NOT NULL DEFAULT '',
            user_agent VARCHAR(500) NOT NULL DEFAULT '',
            browser VARCHAR(40) NOT NULL DEFAULT '',
            os VARCHAR(40) NOT NULL DEFAULT '',
            device VARCHAR(10) NOT NULL DEFAULT 'pc',
            is_spider TINYINT(1) NOT NULL DEFAULT 0,
            spider_name VARCHAR(20) NOT NULL DEFAULT '',
            referer_domain VARCHAR(120) NOT NULL DEFAULT '',
            search_keyword VARCHAR(120) NOT NULL DEFAULT '',
            user_id INT NOT NULL DEFAULT 0,
            is_new_pv TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            last_seen DATETIME DEFAULT NULL,
            UNIQUE KEY uniq_sess_page (session_id, page_url),
            KEY idx_created (created_at),
            KEY idx_ip (ip),
            KEY idx_domain (referer_domain),
            KEY idx_spider (is_spider)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 论坛专属用户字段（关联 RyeBlog vd_users.id，命名兼容茗潭 ryebbs_users 列）
        "CREATE TABLE IF NOT EXISTS {$P}user_ext (
            user_id INT NOT NULL PRIMARY KEY,
            nickname VARCHAR(32) NOT NULL DEFAULT '',
            avatar VARCHAR(255) NOT NULL DEFAULT '',
            signature VARCHAR(255) NOT NULL DEFAULT '',
            gender VARCHAR(10) NOT NULL DEFAULT '',
            bio VARCHAR(255) NOT NULL DEFAULT '',
            is_moderator TINYINT(1) NOT NULL DEFAULT 0,
            mute_until DATETIME DEFAULT NULL,
            coins INT NOT NULL DEFAULT 0,
            reply_count INT NOT NULL DEFAULT 0,
            thread_count INT NOT NULL DEFAULT 0,
            online_seconds INT NOT NULL DEFAULT 0,
            email_verified TINYINT(1) NOT NULL DEFAULT 0,
            notify_enabled TINYINT(1) NOT NULL DEFAULT 1, -- 接收站内通知（回复/点赞），默认开启
            last_active DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_coins (coins)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $sql) {
        dbQuery($sql);
    }

    // 全文索引（非幂等，失败忽略）
    try {
        dbQuery("ALTER TABLE {$P}threads ADD FULLTEXT INDEX ft_search (title, content)");
    } catch (Throwable $e) {
        // 已存在则忽略
    }
}

function rye_install_default_data($P)
{
    $now = date('Y-m-d H:i:s');

    // 默认分区 + 版块
    dbQuery("INSERT IGNORE INTO {$P}forum_sections (id, name, display_order, is_hidden, created_at) VALUES (1, 'RYE茶馆', 0, 0, ?)", [$now]);
    dbQuery("INSERT IGNORE INTO {$P}forums (id, section_id, name, description, topic_category_enabled, topic_categories, icon, name_color, show_on_index, display_order, thread_count, post_count, created_at)
             VALUES (1, 1, '站务公告', '官方公告与问题反馈', 0, '', '', '', 1, 0, 0, 0, ?)", [$now]);

    // 论坛默认设置
    $defaults = [
        'site_name'              => 'RYE社区',
        'site_desc'              => 'RYE社区 —— 基于 RyeBlog 的论坛',
        'forum_threads_per_page' => '30',
        'stats_enabled'          => '1',
        'auto_localize_images'   => '0',
        'upload_enabled'         => '1',
        'upload_max_size_mb'     => '5',
        'upload_ext_images'      => 'jpg,jpeg,png,gif,webp',
        'upload_ext_files'       => 'doc,docx,xls,xlsx,pdf,zip,rar,7z,txt,md',
    ];
    foreach ($defaults as $k => $v) {
        dbQuery("INSERT IGNORE INTO {$P}settings (setting_key, setting_value) VALUES (?, ?)", [$k, $v]);
    }
}
