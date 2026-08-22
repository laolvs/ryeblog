<?php
/**
 * RYE插件 —— 桥接层
 *
 * 把茗潭业务函数映射到 RyeBlog 核心（db / 用户 / 转义 / 分页等），
 * 让论坛前台/后台页尽量零改动复用。前台页：require bootstrap.php 后调用
 * publicHeader()/publicFooter() 包裹 RyeBlog 布局。
 */
require_once RYEBLOG_ROOT . '/inc/functions.php';
require_once RYEBLOG_ROOT . '/inc/view.php';
require_once RYEBLOG_ROOT . '/inc/markdown.php';   // Markdown 渲染（帖子/回复内容）
require_once __DIR__ . '/Plugin.php';   // 定义 Plugin_rye（prefix 等用到）

/* ---------- 数据库（映射到 RyeBlog db* 函数） ---------- */
if (!function_exists('prefix')) {
    function prefix() { return Plugin_rye::prefix(); }
}
if (!function_exists('db_row')) {
    function db_row($sql, $params = []) { return dbOne($sql, $params); }
}
if (!function_exists('db_all')) {
    function db_all($sql, $params = []) { return dbAll($sql, $params); }
}
if (!function_exists('db_val')) {
    function db_val($sql, $params = [])
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}

/* ---------- 转义 / 输出 ---------- */
if (!function_exists('e')) {
    function e($str) { return esc($str); }
}

/* ---------- 当前用户 / 登录态（复用 RyeBlog） ---------- */
if (!function_exists('current_user')) {
    function current_user() { return currentUser(); }
}
if (!function_exists('is_logged_in')) {
    function is_logged_in() { return isLoggedIn(); }
}
if (!function_exists('is_admin')) {
    function is_admin() { return isAdmin(); }
}
if (!function_exists('require_login')) {
    function require_login()
    {
        if (!isLoggedIn()) {
            header('Location: ' . baseUrl('user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
            exit;
        }
    }
}

/* ---------- 站点设置（映射到 getOption） ---------- */
if (!function_exists('setting')) {
    /**
     * 读论坛设置（ryebbs_settings 表）。首调整表缓存。
     * 注意：后台「论坛设置」写的是 ryebbs_settings，勿用 getOption（vd_options）。
     * 表不存在（插件未激活/库被重建）时静默返回默认值，避免前台 Fatal。
     */
    function setting($key, $default = '')
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                foreach (dbAll('SELECT setting_key, setting_value FROM ' . prefix() . 'settings') as $r) {
                    $cache[$r['setting_key']] = $r['setting_value'];
                }
            } catch (Throwable $e) {
                $cache = [];
            }
        }
        return array_key_exists($key, $cache) ? (string) $cache[$key] : $default;
    }
}

/* ---------- 论坛链接（伪静态，生产经 nginx/.htaccess 重写） ---------- */
if (!function_exists('bbs_url')) {
    /**
     * 论坛绝对 URL。伪静态规则：
     *   thread?id=N            → /bbs/thread/N.html
     *   thread?id=N&act=like   → /bbs/thread/N.html?act=like
     *   user?id=N              → /bbs/user/N.html
     *   forum?id=N             → /bbs/forum/N.html
     * 其余（forum/search/rank/post/about/...）保持 /bbs/<path>。
     */
    function bbs_url($path = '')
    {
        if (preg_match('#^(thread|user|forum)\?id=(\d+)(.*)$#', $path, $m)) {
            $qs = ($m[3] !== '') ? '?' . ltrim($m[3], '&') : '';
            return baseUrl('bbs/' . $m[1] . '/' . $m[2] . '.html' . $qs);
        }
        return baseUrl('bbs/' . ltrim($path, '/'));
    }
}

/* ---------- 客户端 IP ---------- */
if (!function_exists('client_ip')) {
    function client_ip()
    {
        // 安全策略：不信任 X-Forwarded-For / Client-IP（客户端可伪造）。
        // 生产在 Cloudflare 全代理后：CF-Connecting-IP 为真实访客 IP；直连时用 REMOTE_ADDR。
        foreach (['HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }
}

/* ---------- 时间 / 分页（纯函数，从茗潭 helpers 复用） ---------- */
if (!function_exists('time_ago')) {
    function time_ago($datetime)
    {
        $ts = is_numeric($datetime) ? (int) $datetime : strtotime($datetime);
        if ($ts === false) return '';
        $diff = time() - $ts;
        if ($diff < 60) return '刚刚';
        if ($diff < 3600) return floor($diff / 60) . ' 分钟前';
        if ($diff < 86400) return floor($diff / 3600) . ' 小时前';
        if ($diff < 2592000) return floor($diff / 86400) . ' 天前';
        return date('Y-m-d', $ts);
    }
}
if (!function_exists('page_nav')) {
    function page_nav($total, $page, $perpage)
    {
        $perpage = max(1, (int) $perpage);
        $pages = max(1, (int) ceil($total / $perpage));
        $page  = min(max((int) $page, 1), $pages);   // clamp，防越界空列表
        return [
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'perpage'  => $perpage,
            'offset'   => ($page - 1) * $perpage,
        ];
    }
}
if (!function_exists('pagination_html')) {
    function pagination_html($total, $page, $perpage, $baseUrl)
    {
        $pages = max(1, ceil($total / $perpage));
        if ($pages <= 1) return '';
        $sep = strpos($baseUrl, '?') === false ? '?' : '&';
        $html = '<div class="pagination">';
        if ($page > 1) {
            $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page - 1)) . '">上一页</a>';
        }
        $start = max(1, $page - 3);
        $end = min($pages, $page + 3);
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $page) {
                $html .= '<span class="current">' . $i . '</span>';
            } else {
                $html .= '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
            }
        }
        if ($page < $pages) {
            $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page + 1)) . '">下一页</a>';
        }
        $html .= '</div>';
        return $html;
    }
}

/* ---------- CSRF（映射到 RyeBlog 的 _csrf 约定） ---------- */
if (!function_exists('csrf_token')) {
    function csrf_token() { return csrfToken(); }
}
if (!function_exists('csrf_field')) {
    function csrf_field() { return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">'; }
}
if (!function_exists('verify_csrf')) {
    function verify_csrf($method = 'POST')
    {
        if (!checkCsrf()) {
            http_response_code(403);
            exit('请求校验失败（CSRF 防护），请刷新页面后重试。');
        }
    }
}

/* ---------- 闪存消息 ---------- */
if (!function_exists('set_flash')) {
    function set_flash($msg, $type = 'info') { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
}
if (!function_exists('get_flash')) {
    function get_flash()
    {
        if (!empty($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $f;
        }
        return null;
    }
}

/* ---------- 用户桥接层（合并 vd_users + ryebbs_user_ext，金币/签到/关注） ---------- */
require_once __DIR__ . '/users.php';

/* ---------- 通知系统（回复/点赞触发，notifications 表） ---------- */
if (!function_exists('ryebbs_notify')) {
    /**
     * 写一条站内通知。$userId=接收者；$relatedUserId=触发者。
     * 通知自己（作者回自己的帖/给自己点赞）时不写。
     */
    function ryebbs_notify($userId, $threadId, $postId, $relatedUserId, $message)
    {
        $userId        = (int) $userId;
        $relatedUserId = (int) $relatedUserId;
        if ($userId <= 0 || $userId === $relatedUserId) return;
        // 接收者关闭了站内通知则不写（新用户默认开启 notify_enabled=1；无扩展记录的老用户默认放行）
        $ext = db_row('SELECT notify_enabled FROM ' . prefix() . 'user_ext WHERE user_id=?', [$userId]);
        if ($ext && (int) $ext['notify_enabled'] === 0) return;
        dbQuery('INSERT INTO ' . prefix() . 'notifications
                 (user_id, thread_id, post_id, related_user_id, message, is_read, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())',
            [$userId, (int) $threadId, (int) $postId, $relatedUserId, mb_substr($message, 0, 180)]);
    }
}

/* ---------- 敏感词过滤（sensitive_words 表，action: replace|block） ---------- */
if (!function_exists('ryebbs_filter_sensitive')) {
    /**
     * 过滤敏感词：replace → 替换为 replacement；block → 返回 false 表示拒绝。
     * 返回过滤后的文本；含 block 词时返回 false。
     */
    function ryebbs_filter_sensitive($text)
    {
        static $words = null;
        if ($words === null) {
            $words = [];
            try {
                foreach (dbAll('SELECT word, action, replacement FROM ' . prefix() . 'sensitive_words') as $r) {
                    $words[] = $r;
                }
            } catch (Throwable $e) { $words = []; }
        }
        if (empty($words)) return $text;
        $out = (string) $text;
        foreach ($words as $w) {
            $word = (string) $w['word'];
            if ($word === '') continue;
            if (mb_stripos($out, $word) === false) continue;
            if ($w['action'] === 'block') return false;
            $out = str_ireplace($word, $w['replacement'] !== '' ? $w['replacement'] : '**', $out);
        }
        return $out;
    }
}

/* ---------- IP 封禁（ip_bans 表，expires_at 空=永久） ---------- */
if (!function_exists('ryebbs_ip_banned')) {
    function ryebbs_ip_banned($ip = null)
    {
        $ip = $ip !== null ? $ip : client_ip();
        if ($ip === '' || $ip === '0.0.0.0') return false;
        try {
            $row = db_row('SELECT id FROM ' . prefix() . 'ip_bans
                           WHERE ip=? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1', [$ip]);
            return (bool) $row;
        } catch (Throwable $e) {
            return false;
        }
    }
}

/* ---------- 论坛侧栏（替代博客侧栏，参照 chake.org 版块导航） ---------- */
if (!function_exists('rye_sidebar_html')) {
    function rye_sidebar_html()
    {
        $P = prefix();
        $sections = db_all('SELECT * FROM ' . $P . 'forum_sections WHERE is_hidden=0 ORDER BY display_order');
        $forums   = db_all('SELECT * FROM ' . $P . 'forums ORDER BY display_order');
        $bySec = [];
        foreach ($forums as $f) { $bySec[$f['section_id']][] = $f; }
        $tCount = (int) db_val('SELECT COUNT(*) FROM ' . $P . 'threads WHERE is_deleted=0');
        $pCount = (int) db_val('SELECT COUNT(*) FROM ' . $P . 'posts WHERE is_deleted=0 AND floor>1');
        $uCount = (int) db_val('SELECT COUNT(*) FROM ' . $P . 'user_ext');

        $html  = '<aside class="bbs-sidebar">';
        $html .= '<div class="bbs-widget bbs-widget-intro"><p>RYE社区 —— RyeBlog 与 RyeCMS 官方交流社区，交流讨论、问题求助、贡献代码。</p></div>';
        $html .= '<div class="bbs-widget">';
        $html .= '<div class="bbs-widget-head"><h3 class="bbs-widget-title">论坛版块</h3><button class="bbs-widget-toggle" type="button" aria-label="展开/收起论坛版块">▴</button></div>';
        $html .= '<div class="bbs-widget-body">';
        foreach ($sections as $s) {
            $html .= '<div class="bbs-side-sec">' . e($s['name']) . '</div>';
            foreach (($bySec[$s['id']] ?? []) as $f) {
                $html .= '<a class="bbs-side-forum" href="' . e(bbs_url('forum?id=' . $f['id'])) . '">'
                       . '<span class="bsf-name">' . e($f['name']) . '</span>'
                       . '<span class="bsf-stats">' . (int) $f['thread_count'] . ' 主题</span>'
                       . '</a>';
            }
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="bbs-widget">';
        $html .= '<h3 class="bbs-widget-title">社区统计</h3>';
        $html .= '<ul class="bbs-stats">'
               . '<li>主题 <b>' . $tCount . '</b></li>'
               . '<li>回复 <b>' . $pCount . '</b></li>'
               . '<li>会员 <b>' . $uCount . '</b></li>'
               . '</ul>';
        $html .= '</div>';
        $html .= '</aside>';
        return $html;
    }
}
