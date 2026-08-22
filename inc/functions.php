<?php
if (defined('RYEBLOG_FUNCTIONS_LOADED')) { return; }
define('RYEBLOG_FUNCTIONS_LOADED', true);

/* ---------- 安全响应头（Web 请求生效，nginx/Apache 均覆盖） ---------- */
if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header_remove('X-Powered-By'); // 隐藏 PHP 版本
    }
}

/** RyeBlog 版本号（升级脚本/安装向导/后台展示共用） */
if (!defined('RYEBLOG_VERSION')) {
    define('RYEBLOG_VERSION', '1.4.2');
}

/**
 * RyeBlog —— 免费开源的中英文博客系统
 * 核心函数库（原创实现）
 */

// ---------- 基础工具 ----------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('RYEBLOG_ROOT', dirname(__DIR__));
define('RYEBLOG_VER', '1.0');

require_once __DIR__ . '/markdown.php';

function siteBase()
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $projRoot = str_replace('\\', '/', rtrim(RYEBLOG_ROOT, '/'));

    if ($docRoot !== '' && $docRoot !== '/' && strpos($projRoot, $docRoot) === 0) {
        $base = substr($projRoot, strlen($docRoot));
    } else {
        $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        $sub = (strpos($scriptFile, $projRoot) === 0)
            ? ltrim(substr($scriptFile, strlen($projRoot)), '/')
            : '';
        $scriptUrl = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(substr($scriptUrl, 0, strlen($scriptUrl) - strlen($sub)), '/');
    }
    return rtrim($base, '/');
}

function baseUrl($path = '')
{
    return siteBase() . '/' . ltrim($path, '/');
}

function esc($val)
{
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function slugify($text)
{
    $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', (string)$text);
    $text = trim($text, '-');
    $text = mb_strtolower($text, 'UTF-8');
    return $text === '' ? 'post-' . substr(md5((string)$text . time()), 0, 8) : $text;
}

// ---------- 多语言（i18n） ----------
// 当前请求语言：'zh' | 'en'。前台由 router.php 或 Apache 重写按 /cn /en 前缀设定；
// 后台由 admin.php 按 adminLang() 设定；未显式设定时 currentLang() 自动探测请求（见 detectLang）。

/**
 * 双语模式开关：语言包插件 english-admin 启用 ⇔ 英文站开启。
 * 中文站 URL 永远为根目录（无前缀，插件开关不变）；英文站多 /en 前缀。
 * 插件未启用时：无切换器、detectLang/adminLang 强制 zh、/en 前缀 301 回根目录。
 */
function bilingualEnabled()
{
    return in_array('english-admin', pluginActiveList(), true);
}

function setCurrentLang($l)
{
    $GLOBALS['_CUR_LANG'] = ($l === 'en') ? 'en' : 'zh';
}

function currentLang()
{
    if (isset($GLOBALS['_CUR_LANG'])) return $GLOBALS['_CUR_LANG'];
    // 未显式设定时，按请求自动探测（Apache 直链 / router.php / cookie 通用）
    $l = detectLang();
    $GLOBALS['_CUR_LANG'] = $l;
    return $l;
}

/**
 * 探测当前请求语言：
 *   1) ?lang=cn|en 显式参数（Apache 重写注入或直链）
 *   2) cookie vd_lang=en
 *   3) 路径前缀 /cn（中文） /en（英文），兼容子目录部署
 *   否则默认中文。
 */
function detectLang()
{
    if (!bilingualEnabled()) return 'zh'; // 纯中文模式：忽略一切语言信号
    $g = $_GET['lang'] ?? '';
    if ($g === 'en') return 'en';
    if ($g === 'cn') return 'zh';
    if (!empty($_COOKIE['vd_lang']) && $_COOKIE['vd_lang'] === 'en') return 'en';
    $req = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = siteBase();
    if ($base !== '' && strpos($req, $base) === 0) {
        $req = substr($req, strlen($base));
    }
    if (preg_match('#^/en(/|$)#', $req)) return 'en';
    if (preg_match('#^/cn(/|$)#', $req)) return 'zh';
    return 'zh';
}

/**
 * 翻译函数：中文即 key（默认值），英文查词典。
 * 词典 = 核心 usr/lang/en.php + 所有已启用插件的 usr/plugins/<dir>/lang/en.php。
 * 未译则原样返回中文，保证永不出现空白。
 */
function __($text, $lang = null)
{
    $lang = $lang ?: currentLang();
    if ($lang !== 'en') return $text;
    static $en = null;
    if ($en === null) $en = loadLangDict('en');
    return $en[$text] ?? $text;
}

function loadLangDict($lang)
{
    $dict = [];
    $core = RYEBLOG_ROOT . '/usr/lang/' . $lang . '.php';
    if (is_file($core)) $dict += (array)@include $core;
    foreach (pluginActiveList() as $dir) {
        $p = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/lang/' . $lang . '.php';
        if (is_file($p)) $dict += (array)@include $p;
    }
    return $dict;
}

/** 后台界面语言：session > cookie > vd_options.admin_lang（默认 zh）；纯中文模式强制 zh */
function adminLang()
{
    if (!bilingualEnabled()) return 'zh';
    if (!empty($_SESSION['rye_admin_lang'])) return $_SESSION['rye_admin_lang'] === 'en' ? 'en' : 'zh';
    if (!empty($_COOKIE['vd_admin_lang']) && $_COOKIE['vd_admin_lang'] === 'en') return 'en';
    return getOption('admin_lang', 'zh') === 'en' ? 'en' : 'zh';
}

/** 内容字段语言回退：en 态且该 *_en 字段非空则用译文，否则回退中文原值（Drupal 式） */
function L($row, $field)
{
    $enField = $field . '_en';
    // 列名不对称兼容：vd_categories 的英文描述列名为 desc_en（而非 description_en）
    static $aliases = ['description' => 'desc_en'];
    if (isset($aliases[$field])) $enField = $aliases[$field];
    if (currentLang() === 'en' && isset($row[$enField]) && $row[$enField] !== '' && $row[$enField] !== null) {
        return $row[$enField];
    }
    return $row[$field] ?? '';
}

/**
 * 语言切换器：中文链接 = 根目录当前路径（无前缀），英文链接 = /en + 当前路径。
 */
function langSwitchHtml()
{
    if (!bilingualEnabled()) return ''; // 纯中文模式：顶部不显示语言切换器
    $req = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = siteBase();
    if ($base !== '' && strpos($req, $base) === 0) {
        $req = substr($req, strlen($base));
    }
    $req = '/' . ltrim($req, '/');
    $req = preg_replace('#^/(cn|en)(?=/|$)#', '', $req); // 去掉语言前缀（中文规范=根目录）
    if ($req === '' || $req === '/') $req = '/';
    $cur = currentLang();
    $root = rtrim(siteBase(), '/');
    $cn = $root . $req;          // 中文 → 根目录
    $en = $root . '/en' . $req;  // 英文 → /en 前缀
    $cnActive = $cur === 'zh' ? ' active' : '';
    $enActive = $cur === 'en' ? ' active' : '';
    return '<span class="lang-switch">'
        . '<a href="' . esc($cn) . '" class="lang-cn' . $cnActive . '" hreflang="zh">中</a>'
        . '<a href="' . esc($en) . '" class="lang-en' . $enActive . '" hreflang="en">EN</a>'
        . '</span>';
}

/**
 * URL 语言规范化（v4）：
 *   - 中文规范 = 根目录（无前缀），无论插件开关 URL 恒定不变 → SEO 稳定
 *   - 双语模式：/cn 旧前缀 301 → 无前缀（兼容旧链接）；/en 前缀保留；无前缀不跳
 *   - 纯中文模式：/cn 与 /en 前缀均 301 → 无前缀
 * 仅前台页面调用（后台不走）。
 */
function enforceLangPrefix()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $reqPath = parse_url($uri, PHP_URL_PATH) ?: '/';
    $base = siteBase();
    if ($base !== '' && strpos($reqPath, $base) === 0) {
        $reqPath = substr($reqPath, strlen($base));
    }
    $reqPath = '/' . ltrim($reqPath, '/');

    $isCn = (bool)preg_match('#^/cn(/|$)#', $reqPath);
    $isEn = (bool)preg_match('#^/en(/|$)#', $reqPath);

    // 需要重定向到无前缀根目录的情形
    $redirect = $isCn || ($isEn && !bilingualEnabled());
    if (!$redirect) return; // /en（双语）或无前缀（中文根目录）→ 不跳

    $clean = preg_replace('#^/(cn|en)(?=/|$)#', '', $reqPath);
    $clean = $clean === '' ? '/' : $clean;
    // 只保留用户原始 query（REQUEST_URI 在内部重写后保持原样；.htaccess 注入参数不携带）
    $qs = '';
    $qpos = strpos($uri, '?');
    if ($qpos !== false) $qs = substr($uri, $qpos);
    header('Location: ' . rtrim(siteBase(), '/') . $clean . $qs, true, 301);
    exit;
}

/**
 * 站点维护模式：开启后前台所有页面（含 feed/sitemap）显示维护页。
 * 后台与 install/upgrade 不受影响；登录管理员访问前台可预览（带 _maintenance_preview=1）。
 * 设置项：site_maintenance（'1' 开启），maintenance_message（提示文案）。
 */
function siteMaintenanceEnabled()
{
    return getOption('site_maintenance', '0') === '1';
}

/**
 * 前台入口调用：维护模式下输出维护页并退出。
 * 例外：后台会话管理员可预览（?maintenance_preview=1），供开启后自查。
 */
function enforceMaintenance()
{
    if (!siteMaintenanceEnabled()) return;
    // 管理员预览通道（仅后台登录态 + 显式参数）
    if (isLoggedIn() && isAdmin() && isset($_GET['maintenance_preview'])) return;

    $msg = getOption('maintenance_message', '');
    if ($msg === '') $msg = __('站点正在维护升级中，请稍后再来访问。');
    $title = __('站点维护中');
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>' . esc($title) . '</title>'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f7f5;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;color:#2c3e50}'
       . '.mw{text-align:center;padding:40px 24px;max-width:480px}.mw .ic{font-size:56px;margin-bottom:16px}'
       . '.mw h1{font-size:26px;margin:0 0 12px}.mw p{font-size:15px;color:#7f8c8d;line-height:1.7;margin:0}'
       . '</style></head><body>'
       . '<div class="mw"><div class="ic">🛠</div><h1>' . esc($title) . '</h1>'
       . '<p>' . esc($msg) . '</p></div></body></html>';
    exit;
}

function formatDate($datetime, $format = null)
{
    if (empty($datetime)) return '';
    if ($format === null) {
        $format = currentLang() === 'en' ? 'M j, Y' : 'Y-m-d';
    }
    $dt = new DateTime((string)$datetime);
    return $dt->format($format);
}

function makeExcerpt($html, $len = 220)
{
    $text = strip_tags((string)$html);
    $text = preg_replace('/\s+/', ' ', $text);
    if (mb_strlen($text) > $len) {
        $text = mb_substr($text, 0, $len) . '…';
    }
    return trim($text);
}

function clientIp()
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ---------- 数据库 ----------

/**
 * 数据库表前缀（config.php 的 DB_PREFIX 可自定义；新装默认 rye_）。
 * 兼容旧站：config 未写 DB_PREFIX 时自动探测——库中存在 vd_options 表则视为旧前缀 vd_，否则默认 rye_。
 */
function dbPrefix()
{
    static $prefix = null;
    if ($prefix === null) {
        if (defined('DB_PREFIX') && DB_PREFIX !== '') {
            $prefix = DB_PREFIX;
        } else {
            $prefix = 'rye_'; // 新站默认
            try {
                $pdo = db();
                if ($pdo) {
                    $st = $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=' . $pdo->quote(DB_NAME) . " AND TABLE_NAME='vd_options'");
                    if ((int)$st->fetchColumn() > 0) {
                        $prefix = 'vd_'; // 旧站（vd_ 前缀）兼容
                    }
                }
            } catch (\Throwable $e) {
                $prefix = 'rye_';
            }
        }
    }
    return $prefix;
}

/**
 * 将 SQL 中的 vd_ 表名替换为当前前缀（白名单，避免误伤正文内容）。
 * 仅在自定义前缀时生效；默认 vd_ 时原样返回，零开销。
 */
function applyDbPrefix($sql)
{
    $prefix = dbPrefix();
    if ($prefix === 'vd_') return $sql;
    static $tables = null;
    if ($tables === null) {
        $tables = ['options', 'posts', 'users', 'comments', 'categories', 'tags', 'post_tags',
            'attachments', 'favorites', 'annotations', 'corrections', 'trail', 'menus',
            'login_attempts', 'lang', 'admin_lang', 'nav_groups', 'nav_links', 'nav_links2'];
    }
    return preg_replace('/\bvd_(' . implode('|', $tables) . ')\b/i', $prefix . '$1', $sql);
}

function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    if (!defined('DB_HOST')) {
        if (file_exists(RYEBLOG_ROOT . '/config.php')) {
            require_once RYEBLOG_ROOT . '/config.php';
        } else {
            return null;
        }
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES    => false,
    ];
    // DB 持久连接开关（后台「高级设置→db_persistent」，保存时写 usr/cache/db_persistent.txt）：
    // php-fpm worker 复用连接减少握手。文件标志而非 getOption（建连前查库会递归）。
    if (file_exists(RYEBLOG_ROOT . '/usr/cache/db_persistent.txt')
        && trim((string)@file_get_contents(RYEBLOG_ROOT . '/usr/cache/db_persistent.txt')) === '1') {
        $opts[PDO::ATTR_PERSISTENT] = true;
    }
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    return $pdo;
}

function dbQuery($sql, $params = [])
{
    $stmt = db()->prepare(applyDbPrefix($sql));
    $stmt->execute($params);
    return $stmt;
}

function dbAll($sql, $params = [])
{
    return dbQuery($sql, $params)->fetchAll();
}

function dbOne($sql, $params = [])
{
    return dbQuery($sql, $params)->fetch();
}

function dbInsert($sql, $params = [])
{
    dbQuery($sql, $params);
    return db()->lastInsertId();
}

// ---------- 站点设置 / 品牌 ----------

function getOption($name, $default = '', $refresh = false)
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        if (db()) {
            foreach (dbAll('SELECT name, value FROM vd_options') as $row) {
                $cache[$row['name']] = $row['value'];
            }
        }
    }
    return $cache[$name] ?? $default;
}

function setOption($name, $value)
{
    $exists = dbOne('SELECT COUNT(*) c FROM vd_options WHERE name=?', [$name])['c'];
    if ($exists) {
        dbQuery('UPDATE vd_options SET value=? WHERE name=?', [$value, $name]);
    } else {
        dbQuery('INSERT INTO vd_options (name, value) VALUES (?, ?)', [$name, $value]);
    }
    getOption(null, '', true); // 刷新缓存
}

function delOption($name)
{
    if (!db()) return false;
    dbQuery('DELETE FROM vd_options WHERE name=?', [$name]);
    getOption(null, '', true); // 刷新缓存
    return true;
}

/** 站点名称：en 态且配置了 site_title_en 则显示英文，否则回退中文 */
function siteTitle()
{
    $en = getOption('site_title_en', '');
    if (currentLang() === 'en' && $en !== '') return $en;
    return getOption('site_title', 'RyeBlog');
}

/** 站点标语：en 态且配置了 site_slogan_en 则显示英文，否则回退中文 */
function siteSlogan()
{
    $en = getOption('site_slogan_en', '');
    if (currentLang() === 'en' && $en !== '') return $en;
    return getOption('site_slogan', '免费开源的中英文博客系统！');
}

/** 站点级 SEO 描述：en 态优先 site_seo_description_en，回退中文，再回退标语 */
function siteSeoDescription()
{
    $en = getOption('site_seo_description_en', '');
    if (currentLang() === 'en' && $en !== '') return $en;
    $zh = getOption('site_seo_description', '');
    if ($zh !== '') return $zh;
    return siteSlogan();
}

/** 站点级 SEO 关键词：en 态优先 site_seo_keywords_en，回退中文 */
function siteSeoKeywords()
{
    $en = getOption('site_seo_keywords_en', '');
    if (currentLang() === 'en' && $en !== '') return $en;
    return getOption('site_seo_keywords', '');
}

/**
 * 当前请求对应的另一种语言 URL（用于 hreflang alternates）。
 * 中文版 = 根目录 + 路径；英文版 = /en + 路径；保留用户 query。
 * 返回 ['zh' => url, 'en' => url, 'has_en' => bool]；纯中文模式 has_en=false。
 */
function altLangUrls()
{
    $b = bilingualEnabled();
    $req = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = siteBase();
    if ($base !== '' && strpos($req, $base) === 0) {
        $req = substr($req, strlen($base));
    }
    $req = '/' . ltrim($req, '/');
    $req = preg_replace('#^/(en)(?=/|$)#', '', $req); // 去掉 /en 前缀（中文版=根目录）
    if ($req === '' || $req === '/') $req = '/';
    $qs = '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $qpos = strpos($uri, '?');
    if ($qpos !== false) $qs = substr($uri, $qpos);
    $root = rtrim(siteBase(), '/');
    $zh = $root . $req . $qs;
    if (!$b) return ['zh' => $zh, 'en' => '', 'has_en' => false];
    $en = $root . '/en' . $req . $qs;
    return ['zh' => $zh, 'en' => $en, 'has_en' => true];
}
function siteUrl()         { return getOption('site_url', 'https://ryeblog.com/'); }
function postsPerPage()    { return (int)getOption('posts_per_page', '10') ?: 10; }

function footerCopyright()
{
    $tpl = getOption('footer_copyright', '© {{year}} {{site}}');
    return strtr($tpl, [
        '{{year}}' => date('Y'),
        '{{site}}' => siteTitle(),
    ]);
}
/** 页脚支持信息：en 态优先 footer_support_en，回退中文 */
function footerSupport()
{
    $en = getOption('footer_support_en', '');
    if (currentLang() === 'en' && $en !== '') return $en;
    return getOption('footer_support', 'Powered by RyeBlog');
}
function footerIcp()     { return getOption('footer_icp', ''); }
function footerStats()   { return getOption('footer_stats', ''); }

function authorCard()
{
    return [
        'show'   => getOption('author_card_show', '1') === '1',
        'title'  => getOption('author_card_title', '关于博主'),
        'name'   => getOption('author_card_name', '博主'),
        'avatar' => getOption('author_card_avatar', ''),
        'image'  => getOption('author_card_image', ''),
        'bio'    => getOption('author_card_bio', ''),
    ];
}

// ---------- 分类 / 页面 ----------

function getCategories()
{
    static $cache = null; // 同请求内缓存（列表页多次调用不重复查库）
    if ($cache === null) {
        // 每类文章数计数昂贵（百万级全表/索引扫）→ 文件缓存 6 小时，跨请求复用。
        // FORCE INDEX：分类 COUNT 相关子查询若不强制走 (type,status,category_id) 复合索引，
        // 优化器只取 type 单列导致 51 万行过滤（实测 9.7s vs 2.3s）。
        $cache = ryeblogCacheGet('categories', 21600, function () {
            return dbAll('SELECT c.*, (SELECT COUNT(*) FROM vd_posts p FORCE INDEX (idx_type_status_cat_created) WHERE p.category_id=c.id AND p.type="post" AND p.status="published") AS post_count FROM vd_categories c ORDER BY c.id ASC');
        });
    }
    return $cache;
}

function getCategory($id)
{
    return dbOne('SELECT * FROM vd_categories WHERE id = ?', [$id]);
}

function getCategoryBySlug($slug)
{
    return dbOne('SELECT * FROM vd_categories WHERE slug = ?', [$slug]);
}

function getPages()
{
    // 英文列仅双语模式存在，动态拼接（纯中文库无 *_en 列）
    $en = bilingualEnabled() ? ', title_en' : '';
    return dbAll("SELECT id, title$en, slug FROM vd_posts WHERE type='page' AND status='published' ORDER BY id ASC");
}

// ---------- 内容查询 ----------

/**
 * 文章表是否已建立全文索引（ngram）。
 * 有则搜索走 MATCH（百万级秒回），否则回退 LIKE（保持旧站/小站兼容）。
 */
function postsHaveFulltext()
{
    static $cache = null;
    if ($cache === null) {
        $cache = (bool)dbOne(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_TYPE = 'FULLTEXT' LIMIT 1",
            [dbPrefix() . 'posts']
        );
    }
    return $cache;
}

/**
 * 通用文件缓存：命中且在 TTL 内直接返回，否则执行 $callback 并写回。
 * 用于缓存「数据基本不变但计算昂贵」的聚合结果（百万级数据下 COUNT 动辄 1s+，
 * 而首页/列表/侧栏每个页面串行累加多个此类查询后会达到数秒）。
 */
/**
 * 内容版本号（content_rev）：文章/分类/标签等发布、编辑、删除时 +1。
 * ryeblogCacheGet 的缓存键自动附带 rev → 内容一变更，相关缓存立即失效（实时正确），
 * TTL 仅作兜底可放心拉长。百万级站告别「缓存过期首访 10s+」。
 */
function contentRev()
{
    static $rev = null;
    if ($rev === null) {
        $row = dbOne("SELECT value FROM vd_options WHERE name='content_rev'");
        $rev = (int)($row['value'] ?? 0);
    }
    return $rev;
}

// ---------- 整页缓存（P1：应用层，可开关；文件/Redis 双后端） ----------

/** 整页缓存后端：redis（需开 page_cache_redis 且有 redis 扩展）或 file */
function pageCacheBackend()
{
    static $use = null;
    if ($use === null) {
        $use = (getOption('page_cache_redis', '0') === '1' && extension_loaded('redis')) ? 'redis' : 'file';
    }
    return $use;
}

function pageCacheRead($key)
{
    $ttl = (int)getOption('page_cache_ttl', '60');
    $hk  = 'ryepage:' . md5($key);
    if (pageCacheBackend() === 'redis') {
        try {
            $r = new Redis();
            if ($r->connect('127.0.0.1', 6379, 1)) {
                $v = $r->get($hk);
                $r->close();
                return (is_string($v) && $v !== '') ? $v : null;
            }
        } catch (\Throwable $e) { /* Redis 异常回退文件 */ }
    }
    $f = RYEBLOG_ROOT . '/usr/cache/page/' . md5($key) . '.html';
    return (is_file($f) && (time() - filemtime($f) < $ttl)) ? (string)@file_get_contents($f) : null;
}

function pageCacheWrite($key, $html)
{
    $ttl = (int)getOption('page_cache_ttl', '60');
    $hk  = 'ryepage:' . md5($key);
    if (pageCacheBackend() === 'redis') {
        try {
            $r = new Redis();
            if ($r->connect('127.0.0.1', 6379, 1)) {
                $r->setex($hk, $ttl, $html);
                $r->close();
                return;
            }
        } catch (\Throwable $e) { /* 回退文件 */ }
    }
    $dir = RYEBLOG_ROOT . '/usr/cache/page/';
    @mkdir($dir, 0755, true);
    @file_put_contents($dir . md5($key) . '.html', $html);
}

/**
 * 整页缓存入口：前台页面顶部调用。
 * - 开关 page_cache（后台高级设置）＝'1' 才生效；
 * - 仅 GET 且无登录/会话/评论 cookie（避免缓存登录态或动态提示）；
 * - 命中 → 直接输出缓存并 exit（nginx 都不进 PHP 的效果在本层实现）；
 * - miss → ob_start，shutdown 时写缓存。
 * 返回 true 表示已接管输出；false 表示未启用（页面正常渲染）。
 */
function pageCacheStart($key = null)
{
    if (getOption('page_cache', '0') !== '1') return false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
    if (!empty($_COOKIE)) {
        foreach ($_COOKIE as $ck => $cv) {
            if ($ck === 'PHPSESSID' || stripos($ck, 'rye_') === 0
                || stripos($ck, 'comment_') === 0 || stripos($ck, 'wp-') === 0) {
                return false; // 登录/会话/评论者 cookie → 不缓存
            }
        }
    }
    $key = $key ?: (($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''));
    $html = pageCacheRead($key);
    if ($html !== null) {
        header('X-Rye-Cache: HIT');
        echo $html;
        exit;
    }
    ob_start();
    register_shutdown_function(function () use ($key) {
        $html = ob_get_clean();
        if ($html !== false) {
            if (strlen($html) > 200) pageCacheWrite($key, $html);
            echo $html;
        }
    });
    return true;
}
{
    static $rev = null;
    if ($rev === null) {
        $row = dbOne("SELECT value FROM vd_options WHERE name='content_rev'");
        $rev = (int)($row['value'] ?? 0);
    }
    return $rev;
}

/** 内容变更时调用：rev+1（写路径：保存/删除/恢复文章、分类标签增删改） */
function bumpContentRev()
{
    dbQuery("INSERT INTO vd_options (name, value) VALUES ('content_rev', '1')
             ON DUPLICATE KEY UPDATE value = CAST(value AS UNSIGNED) + 1");
}

function ryeblogCacheGet($key, $ttl, $callback)
{
    // 键附带 content_rev：内容变更即换新缓存文件，无需人工清缓存
    $f = RYEBLOG_ROOT . '/usr/cache/' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $key) . '_r' . contentRev() . '.json';
    if (is_file($f) && (time() - filemtime($f) < (int)$ttl)) {
        $dec = json_decode(@file_get_contents($f), true);
        if ($dec !== null) return $dec;
    }
    $v = $callback();
    @mkdir(dirname($f), 0755, true);
    @file_put_contents($f, json_encode($v, JSON_UNESCAPED_UNICODE));
    return $v;
}

function getPosts($opts = [])
{
    $opts = array_merge([
        'type'     => 'post',
        'status'   => 'published',
        'category' => null,
        'tag'      => null,
        'month'    => null,
        'search'   => null,
        'page'     => 1,
        'perPage'  => postsPerPage(),
        'orderBy'  => 'p.created_at DESC',
    ], $opts);

    $where = ['p.type = ?', 'p.status = ?'];
    $params = [$opts['type'], $opts['status']];

    if ($opts['category'] !== null) {
        $where[] = 'p.category_id = ?';
        $params[] = $opts['category'];
    }
    // 归档按月：用 created_at 范围（命中 idx_type_status_created / idx_type_status_cat_created），避免全表扫
    if (!empty($opts['month']) && preg_match('/^\d{4}-\d{2}$/', $opts['month'])) {
        $start = $opts['month'] . '-01';
        $end   = date('Y-m-d', strtotime($start . ' +1 month'));
        $where[] = 'p.created_at >= ? AND p.created_at < ?';
        $params[] = $start;
        $params[] = $end;
    }
    // 标签过滤：用 INNER JOIN 替代 EXISTS 相关子查询，先按标签缩小结果集再排序，
    // 避免百万级数据下对每行执行相关子查询 + 全表 filesort（COUNT 同样受益，从秒级降到毫秒级）。
    $tagJoin = '';
    if ($opts['tag'] !== null) {
        $tagJoin = ' INNER JOIN vd_post_tags pt ON pt.post_id = p.id'
                 . ' INNER JOIN vd_tags t ON t.id = pt.tag_id AND t.slug = ?';
        // 占位符 ? 位于 SQL 的 FROM/JOIN 段（在 WHERE 段 type/status 的 ? 之前），
        // 故必须把 tag 值放到 params 数组首位，否则 PDO 绑定错位导致 TOTAL=0（标签页无内容）。
        array_unshift($params, $opts['tag']);
    }
    $searchMatch = false;
    if ($opts['search'] !== null && $opts['search'] !== '') {
        $q = $opts['search'];
        if (postsHaveFulltext()) {
            // 中文全文索引（ngram）：用 MATCH 替代 LIKE 全表扫，百万级数据秒回
            $where[] = 'MATCH(p.title) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $params[] = $q;
            $searchMatch = true;
        } elseif (currentLang() === 'en') {
            $where[] = '(p.title LIKE ? OR p.content LIKE ? OR p.title_en LIKE ? OR p.content_en LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        } else {
            $where[] = '(p.title LIKE ? OR p.content LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
    }

    $whereSql = implode(' AND ', $where);
    $perPage = max(1, (int)$opts['perPage']);

    // 总数计数：百万级数据 COUNT 约 1s，且对静态数据基本不变 → 文件缓存 10 分钟，
    // 避免每个页面重复全表计数（首页/列表/最新文章等高频路径累加后可达数秒）。
    // 缓存键必须包含查询参数（标签 slug / 分类 id / 搜索词等），否则不同筛选会命中同一份计数。
    $total = ryeblogCacheGet('pc_' . md5($whereSql . $tagJoin . serialize($params)), 3600, function () use ($whereSql, $params, $tagJoin) {
        return (int)dbOne("SELECT COUNT(*) AS c FROM vd_posts p$tagJoin WHERE $whereSql", $params)['c'];
    });
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = max(1, min((int)$opts['page'], $pages));
    $offset = ($page - 1) * $perPage;

    // 英文列仅双语模式存在（纯中文库无 *_en 列），动态拼接
    $enCat = bilingualEnabled() ? ', c.name_en AS category_name_en' : '';

    // 大数据量下列表查询性能：
    // - 纯列表/分类：强制走复合「过滤+排序」索引，避免对全表做 filesort（百万级会超时）；
    // - 标签：已由 INNER JOIN 驱动（从标签索引入手缩小结果集），不强走排序索引；
    // - 搜索：走 FULLTEXT（MATCH），不强走排序索引；命中时按相关度排序。
    $idxHint = '';
    if ($opts['tag'] === null && $opts['search'] === null) {
        $idxName = ($opts['category'] !== null) ? 'idx_type_status_cat_created' : 'idx_type_status_created';
        $idxHint = " FORCE INDEX ($idxName)";
    }
    $orderBySql = $opts['orderBy'];
    if ($searchMatch) {
        // 搜索结果按全文相关度降序
        $orderBySql = 'MATCH(p.title) AGAINST (?) DESC';
        $params[] = $opts['search'];
    }
    $sql = "SELECT p.*, c.name AS category_name$enCat, c.slug AS category_slug
            FROM vd_posts p$idxHint$tagJoin
            LEFT JOIN vd_categories c ON c.id = p.category_id
            WHERE $whereSql
            ORDER BY $orderBySql
            LIMIT $perPage OFFSET $offset";

    // 搜索结果 items 缓存（P2：同词+同页 10 分钟；键带 content_rev，发文章即实时失效）
    if ($searchMatch) {
        $skey = 'search_' . md5($opts['search'] . '|' . $perPage . '|' . $offset);
        $items = ryeblogCacheGet($skey, 600, function () use ($sql, $params) {
            return dbAll($sql, $params);
        });
    } else {
        $items = dbAll($sql, $params);
    }

    // 批量加载标签（withTags=true 时一次 IN 查询，消除每篇文章的 N+1 标签查询）
    if (!empty($opts['withTags']) && $items) {
        $ids = array_map('intval', array_column($items, 'id'));
        $in  = implode(',', $ids);
        $enTag = bilingualEnabled() ? ', t.name_en' : '';
        $rows = dbAll("SELECT pt.post_id, t.id, t.name$enTag, t.slug
                       FROM vd_post_tags pt JOIN vd_tags t ON t.id = pt.tag_id
                       WHERE pt.post_id IN ($in) ORDER BY t.id ASC");
        $tagMap = [];
        foreach ($rows as $r) $tagMap[$r['post_id']][] = $r;
        // 批量评论数（同一 IN 查询，消灭列表页每卡 1 条 COUNT 的 N+1）
        $crows = dbAll("SELECT post_id, COUNT(*) AS c FROM vd_comments
                        WHERE post_id IN ($in) AND status='approved' GROUP BY post_id");
        $cMap = [];
        foreach ($crows as $r) $cMap[$r['post_id']] = (int)$r['c'];
        foreach ($items as &$it) {
            $it['tags'] = $tagMap[$it['id']] ?? [];
            $it['comment_count'] = $cMap[$it['id']] ?? 0;
        }
        unset($it);
    }

    return [
        'items' => $items,
        'total' => (int)$total,
        'pages' => $pages,
        'page'  => $page,
    ];
}

function getPost($idOrSlug, $bySlug = false)
{
    // 英文列仅双语模式存在，动态拼接
    $enCat = bilingualEnabled() ? ', c.name_en AS category_name_en' : '';
    if (!$bySlug) {
        return dbOne("SELECT p.*, c.name AS category_name$enCat, c.slug AS category_slug
                      FROM vd_posts p
                      LEFT JOIN vd_categories c ON c.id = p.category_id
                      WHERE p.id = ?", [$idOrSlug]);
    }
    // 按 slug 查询：en 态优先匹配 slug_en，其次中文 slug（兼容未配英文别名的内容）
    if (currentLang() === 'en') {
        $row = dbOne("SELECT p.*, c.name AS category_name$enCat, c.slug AS category_slug
                      FROM vd_posts p
                      LEFT JOIN vd_categories c ON c.id = p.category_id
                      WHERE p.slug_en = ? LIMIT 1", [$idOrSlug]);
        if ($row) return $row;
    }
    return dbOne("SELECT p.*, c.name AS category_name$enCat, c.slug AS category_slug
                  FROM vd_posts p
                  LEFT JOIN vd_categories c ON c.id = p.category_id
                  WHERE p.slug = ? LIMIT 1", [$idOrSlug]);
}

function getPostBySlugAnyType($slug)
{
    // 英文列仅双语模式存在，动态拼接
    $enCat = bilingualEnabled() ? ', c.name_en AS category_name_en' : '';
    // en 态优先 slug_en，其次中文 slug（兼容）
    if (currentLang() === 'en') {
        $row = dbOne("SELECT p.*, c.name AS category_name$enCat, c.slug AS category_slug
                      FROM vd_posts p
                      LEFT JOIN vd_categories c ON c.id = p.category_id
                      WHERE p.slug_en = ? LIMIT 1", [$slug]);
        if ($row) return $row;
    }
    return dbOne("SELECT p.*, c.name AS category_name$enCat, c.slug AS category_slug
                  FROM vd_posts p
                  LEFT JOIN vd_categories c ON c.id = p.category_id
                  WHERE p.slug = ? LIMIT 1", [$slug]);
}

function bumpViews($postId)
{
    // 浏览计数是高频写（每次访问一次 UPDATE）：热文并发写锁 + binlog 放大。
    // 改为文件缓冲合并写：计数先入 views_buffer.json（flock 防并发竞争），
    // 单文累计 ≥5 次或缓冲 ≥20 条时一次性合并刷入 MySQL（写放大降 5~20 倍）。
    // 缓冲未刷部分浏览量显示略滞后，浏览计数非关键数据可接受；崩溃最多丢缓冲增量。
    $postId = (int)$postId;
    if ($postId <= 0) return;
    $buf = RYEBLOG_ROOT . '/usr/cache/views_buffer.json';
    $fp = @fopen($buf, 'c+');
    if (!$fp) { // 目录不可写等极端情况：退化为直写
        dbQuery('UPDATE vd_posts SET views = views + 1 WHERE id = ?', [$postId]);
        return;
    }
    if (flock($fp, LOCK_EX)) {
        $data = json_decode((string)@file_get_contents($buf), true);
        if (!is_array($data)) $data = [];
        $data[$postId] = (int)($data[$postId] ?? 0) + 1;
        if (count($data) >= 20 || $data[$postId] >= 5) {
            foreach ($data as $pid => $n) {
                if ((int)$n > 0) dbQuery('UPDATE vd_posts SET views = views + ? WHERE id = ?', [(int)$n, (int)$pid]);
            }
            $data = [];
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// ---------- 评论 ----------

function getComments($postId, $onlyApproved = true)
{
    $sql = 'SELECT * FROM vd_comments WHERE post_id = ?';
    $params = [$postId];
    if ($onlyApproved) {
        $sql .= " AND status = 'approved'";
    }
    $sql .= ' ORDER BY id ASC';
    return dbAll($sql, $params);
}

function countComments($postId, $onlyApproved = true)
{
    $sql = 'SELECT COUNT(*) AS c FROM vd_comments WHERE post_id = ?';
    $params = [$postId];
    if ($onlyApproved) {
        $sql .= " AND status = 'approved'";
    }
    return (int)dbOne($sql, $params)['c'];
}

function addComment($postId, $data)
{
    return dbInsert(
        'INSERT INTO vd_comments (post_id, author, email, website, content, status, author_ip, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
        [
            $postId,
            mb_substr(trim($data['author']), 0, 60),
            mb_substr(trim($data['email'] ?? ''), 0, 190),
            mb_substr(trim($data['website'] ?? ''), 0, 255),
            $data['content'],
            getOption('comment_moderation', '1') === '1' ? 'pending' : 'approved',
            clientIp(),
        ]
    );
}

// ---------- 标签 ----------

function getTags($limit = 100)
{
    return dbAll('SELECT * FROM vd_tags ORDER BY `count` DESC, id ASC LIMIT ' . (int)$limit);
}

function getTag($slug)
{
    return dbOne('SELECT * FROM vd_tags WHERE slug = ?', [$slug]);
}

function getPostTags($postId)
{
    // 英文列仅双语模式存在，动态拼接
    $en = bilingualEnabled() ? ', t.name_en' : '';
    return dbAll("SELECT t.id, t.name$en, t.slug FROM vd_post_tags pt JOIN vd_tags t ON t.id=pt.tag_id WHERE pt.post_id=? ORDER BY t.id ASC", [$postId]);
}

function setPostTags($postId, $tagNames)
{
    dbQuery('DELETE FROM vd_post_tags WHERE post_id=?', [$postId]);
    $names = [];
    foreach ((array)$tagNames as $raw) {
        $n = trim($raw);
        if ($n !== '') $names[$n] = true;
    }
    foreach (array_keys($names) as $name) {
        $slug = slugify($name);
        $tag = dbOne('SELECT id FROM vd_tags WHERE slug=?', [$slug]);
        if (!$tag) {
            dbQuery('INSERT INTO vd_tags (name, slug, count) VALUES (?, ?, 0)', [$name, $slug]);
            $tagId = db()->lastInsertId();
        } else {
            $tagId = $tag['id'];
        }
        dbQuery('INSERT IGNORE INTO vd_post_tags (post_id, tag_id) VALUES (?, ?)', [$postId, $tagId]);
    }
    // 注意：必须用 dbQuery（自动转换表前缀），裸 db()->exec 在非 vd_ 前缀库（如 rye_）会报表不存在
    dbQuery('UPDATE vd_tags t SET `count`=(SELECT COUNT(*) FROM vd_post_tags pt WHERE pt.tag_id=t.id)');
}

function tagCloud($max = 30)
{
    // 热门标签 top-N：标签计数基本不变 → 文件缓存 1 小时（键带 content_rev，发文章即实时失效），避免每次渲染侧栏都排序 55 万行
    return ryeblogCacheGet('tagcloud_' . (int)$max, 3600, function () use ($max) {
        // $max <= 0 表示全部标签；LIMIT 0 会返回空结果，必须避免
        $sql = 'SELECT * FROM vd_tags WHERE `count`>0 ORDER BY `count` DESC, id ASC';
        if ((int)$max > 0) $sql .= ' LIMIT ' . (int)$max;
        return dbAll($sql);
    });
}

// ---------- 附件 ----------

function getAttachments($postId = null)
{
    if ($postId !== null) {
        return dbAll('SELECT * FROM vd_attachments WHERE post_id=? ORDER BY id DESC', [$postId]);
    }
    return dbAll('SELECT * FROM vd_attachments ORDER BY id DESC');
}

/**
 * 评论垃圾防护（轻量无感）：
 * 1. 同 IP 频率限制（60 秒内最多 1 条）；
 * 2. 常见垃圾关键词过滤（中英文）。
 * 返回 true 允许提交，false 拒绝。
 */
function commentSpamCheck($content, $website = '', $postId = 0)
{
    $ip = clientIp();

    // 1. 频率限制：同 IP 60 秒内已有评论 → 拒绝
    if ($ip !== '') {
        $cnt = (int)dbOne("SELECT COUNT(*) c FROM vd_comments WHERE created_at > (NOW() - INTERVAL 60 SECOND) AND author_ip=?", [$ip])['c'];
        if ($cnt > 0) return false;
    }

    // 2. 关键词黑名单（覆盖常见中英文垃圾评论）
    $text = mb_strtolower(html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5) . ' ' . (string)$website);
    $badWords = [
        'transfer to you', 'bitcoin', 'crypto', 'viagra', 'casino', 'porn', 'xxx',
        'buy now', 'click here', 'free money', 'make money', 'earn money', 'lottery',
        'watch this', 'seo service', 'backlink', '加v', '加微信', '加我微信', '代开',
        '发票', '兼职', '刷单', '博彩', '彩票',
    ];
    foreach ($badWords as $w) {
        if (strpos($text, $w) !== false) return false;
    }

    return true;
}

function addAttachment($postId, $filename, $filepath, $filesize, $mime)
{
    return dbInsert('INSERT INTO vd_attachments (post_id, filename, filepath, filesize, mime) VALUES (?, ?, ?, ?, ?)',
        [$postId, $filename, $filepath, $filesize, $mime]);
}

function deleteAttachment($id)
{
    $row = dbOne('SELECT * FROM vd_attachments WHERE id=?', [$id]);
    if ($row && $row['filepath'] && is_file(RYEBLOG_ROOT . '/' . ltrim($row['filepath'], '/'))) {
        @unlink(RYEBLOG_ROOT . '/' . ltrim($row['filepath'], '/'));
    }
    dbQuery('DELETE FROM vd_attachments WHERE id=?', [$id]);
}

/**
 * 用月份分目录创建 uploads 目录，返回相对路径（带末尾斜杠）。
 * 例：getUploadRelDir() => 'usr/uploads/202608/'
 */
function getUploadRelDir()
{
    return 'usr/uploads/' . date('Ym') . '/';
}

/**
 * 确保物理目录存在并可写，返回绝对路径。
 */
function ensureUploadDir($rel = null)
{
    $rel = $rel ?: getUploadRelDir();
    $abs = RYEBLOG_ROOT . '/' . $rel;
    if (!is_dir($abs)) @mkdir($abs, 0755, true);
    return $abs;
}

/**
 * 生成唯一且可读的文件名（同月内时间戳 + 4 字节随机串 + 原文件名消毒）
 * 返回：basename（不是完整路径）。
 */
function makeUniqueFilename($original)
{
    return date('d_His') . '_' . bin2hex(random_bytes(4)) . '_' . sanitizeFilename($original);
}

/**
 * 判断 URL 是否指向本地上传目录（用于扫描正文时过滤站外引用）。
 * 支持：以 http(s)://开头 或 以 /verda/ 等网站 baseURL 开头的本地 uploads URL。
 * 不依赖 siteBase() —— 兼容 CLI / HTTP 两种环境。
 */
function isLocalUploadUrl($url, $prefix = null)
{
    $url = (string)$url;
    if ($url === '') return false;

    // 显式前缀模式（向后兼容旧调用）：以指定前缀开头即视为本地
    if ($prefix !== null) {
        return strpos($url, $prefix) === 0;
    }

    // 无协议（相对路径）：相对本站，视为本地已上传
    if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
        return true;
    }

    // 仅当主机与本站一致、且路径位于上传目录时，才算“已上传的本地图片”。
    // 否则（即使是 /usr/uploads/ 路径）只要主机不同，就当作远程图片去下载，
    // 避免把其他站点的 RyeBlog/Typecho 风格上传路径误判为本地而漏下载。
    $host      = parse_url($url, PHP_URL_HOST);
    $localHost = parse_url(homeUrl() ?: baseUrl(''), PHP_URL_HOST);
    if ($host && $localHost && strcasecmp($host, $localHost) === 0) {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        return (bool) preg_match('#(^|/)usr/uploads/#i', $path);
    }
    return false;
}

/**
 * 远程图片自动本地化：扫描正文中的远程图片 URL，下载到本地 usr/uploads/ 并替换为本地地址。
 * 支持 Markdown（![alt](url)）与 HTML（<img src>）两种格式。
 * 受设置项 localize_remote_images 控制（默认开）；下载失败的原样保留，不影响保存。
 * @param string $content 正文
 * @param string $format  'markdown'|'html'
 * @param array  $report  输出统计（引用传出）：['downloaded'=>n,'failed'=>n]
 * @return string 处理后的正文
 */
function localizeRemoteImages($content, $format = 'markdown', &$report = null)
{
    if (!is_string($content) || $content === '') return $content;
    if (getOption('localize_remote_images', '1') !== '1') return $content;

    $report = ['downloaded' => 0, 'failed' => 0];
    $map    = []; // 原 URL → 本地 URL

    // 收集全部图片 URL（MD 与 HTML 两种写法都扫）
    $urls = [];
    if (preg_match_all('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/i', $content, $m)) {
        foreach ($m[1] as $u) $urls[] = trim($u);
    }
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
        foreach ($m[1] as $u) $urls[] = trim($u);
    }
    $urls = array_values(array_unique($urls));

    $uploadRoot = RYEBLOG_ROOT . '/usr/uploads';
    $relRoot    = 'usr/uploads';

    foreach ($urls as $url) {
        // 已本地/相对/非 http(s) 的跳过
        if (!preg_match('#^https?://#i', $url)) continue;
        if (isLocalUploadUrl($url)) continue;
        if (isset($map[$url])) continue;

        // 扩展名白名单（图片；也可允许常见文档，但默认仅图片，避免把大附件拖进正文）
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'], true)) continue;

        $bin = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 15, 'user_agent' => 'RyeBlog-Localizer/1.0', 'follow_location' => 1],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]));
        if ($bin === false || $bin === '' || strlen($bin) > 8 * 1048576) { // 8MB 上限防拖垮
            $report['failed']++;
            continue;
        }
        // 真实格式校验：仅允许图片内容（用 getimagesizefromstring 判断内存内容，最稳）
        if (!@getimagesizefromstring($bin)) { $report['failed']++; continue; }

        // 落地：uploads/<年月>/<唯一名>
        $sub   = date('Ym');
        $absDir = $uploadRoot . '/' . $sub;
        if (!is_dir($absDir)) @mkdir($absDir, 0755, true);
        $name = makeUniqueFilename('local-' . ($ext !== '' ? $ext : 'jpg'));
        $dest = $absDir . '/' . $name;
        if (!@file_put_contents($dest, $bin)) { $report['failed']++; continue; }
        @chmod($dest, 0644);

        $map[$url] = baseUrl($relRoot . '/' . $sub . '/' . $name);
        $report['downloaded']++;
    }

    if (!$map) return $content;

    // 替换：MD 与 HTML 写法
    foreach ($map as $from => $to) {
        $fromQ = preg_quote($from, '/');
        $toEsc = str_replace('$', '\\$', $to);
        $content = preg_replace('/!\[([^\]]*)\]\((' . $fromQ . ')(?:\s+"[^"]*")?\)/i', '![$1](' . $toEsc . ')', $content);
        $content = preg_replace('/<img([^>]*)src=["\']' . $fromQ . '["\']([^>]*)>/i', '<img$1src="' . $toEsc . '"$2>', $content);
    }

    // 注册核心附件记录（供统一清理孤儿）
    if (function_exists('registerAttachmentRecord')) {
        foreach ($map as $from => $to) {
            $rel = str_replace(baseUrl(''), '', $to);
            @registerAttachmentRecord($rel, basename(parse_url($from, PHP_URL_PATH) ?: 'remote'), @filesize(RYEBLOG_ROOT . '/' . ltrim($rel, '/')) ?: 0, 'image/*', null);
        }
    }

    return $content;
}
function scanContentForUsedAttachments($content, $format = 'html')
{
    $urls = [];
    if (!is_string($content) || $content === '') return $urls;
    if ($format === 'markdown') {
        // 提取 ![alt](url) 和 [text](url)
        if (preg_match_all('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
        if (preg_match_all('/\[(?!\!)[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
        // HTML 内嵌的 <img src> / <a href>
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
    } else {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
    }
    // 去重 + 仅保留本地上传
    $urls = array_unique(array_filter($urls, function ($u) {
        return isLocalUploadUrl($u);
    }));
    return array_values($urls);
}

/**
 * 扫描正文内容，仅提取本地上传图片 URL（用于封面图自动选择）。
 * 支持 Markdown ![](url) 和 HTML <img src="url"> 两种格式。
 * 返回按出现顺序排列的 URL 数组（保留重复，供前端选择）。
 */
function scanContentForImages($content, $format = 'html')
{
    $urls = [];
    if (!is_string($content) || $content === '') return $urls;
    if ($format === 'markdown') {
        // Markdown 图片语法 ![alt](url)
        if (preg_match_all('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
        // HTML 内嵌的 <img src>（Markdown 里也可能混写 HTML）
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
    } else {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
    }
    // 仅保留本地上传图片
    $urls = array_values(array_filter($urls, function ($u) {
        return isLocalUploadUrl($u);
    }));
    return $urls;
}

/**
 * 把正文里出现的所有"上传目录 URL"归一化为可与 vd_attachments.filepath 比较的 key（basename）。
 */
function attachmentUsedKeysFromContent($content, $format = 'html')
{
    $urls = scanContentForUsedAttachments($content, $format);
    $keys = [];
    foreach ($urls as $u) {
        // 去掉可能的 query/hash
        $u = preg_replace('/[?#].*$/', '', $u);
        $bn = basename($u);
        if ($bn !== '') $keys[] = $bn;
    }
    return array_unique($keys);
}

/**
 * 把 post_id = X 的所有附件对比正文引用，删除未引用的（db 记录 + 文件）。
 * 同时把"post_id IS NULL 的临时附件"绑定为当前 post_id，避免孤儿占用。
 *
 * @param int    $postId
 * @param array  $usedKeys  形如 ['13_abc.png', ...]（仅 basename）
 * @return int   删除数量
 */
function cleanupUnusedAttachments($postId, $usedKeys = [])
{
    if (!$postId) return 0;
    $usedKeys = array_flip($usedKeys);

    // 1) 把所有临时附件（post_id 还没绑定的）绑定到本文章，保住它们
    dbQuery('UPDATE vd_attachments SET post_id=? WHERE post_id IS NULL', [$postId]);

    $atts = dbAll('SELECT * FROM vd_attachments WHERE post_id=?', [$postId]);
    $deleted = 0;
    foreach ($atts as $a) {
        $bn = basename($a['filepath']);
        if (isset($usedKeys[$bn])) continue;
        // 命中保留（正文有引用）。否则删除
        deleteAttachment($a['id']);
        $deleted++;
    }
    return $deleted;
}

/**
 * 清理过期临时附件（post_id IS NULL 且超过 N 小时未绑定）。每次调用清理一次性。
 */
function cleanupOldTempAttachments($maxHours = 24)
{
    $rows = dbAll('SELECT id FROM vd_attachments WHERE post_id IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)', [(int)$maxHours]);
    $n = 0;
    foreach ($rows as $r) {
        deleteAttachment((int)$r['id']);
        $n++;
    }
    return $n;
}

/**
 * 通用：注册一条附件记录（与 addAttachment 等价，明确返回 [id, filepath, url]）。
 */
function registerAttachmentRecord($rel, $filename, $size, $mime, $postId = null)
{
    $id = addAttachment($postId, $filename, $rel, $size, $mime);
    return [
        'id'       => (int)$id,
        'filepath' => $rel,
        'url'      => baseUrl($rel),
        'filename' => $filename,
        'size'     => (int)$size,
        'mime'     => $mime,
        'type'     => preg_match('/^image\//', (string)$mime) ? 'image' : 'file',
    ];
}

// ---------- 菜单 ----------

function getMenus($location)
{
    static $cache = []; // 同请求内缓存
    if (!isset($cache[$location])) {
        $rows = dbAll("SELECT * FROM vd_menus WHERE location=? AND status=1 ORDER BY sort_order ASC, id ASC", [$location]);
        foreach ($rows as &$m) {
            $m['resolved_url'] = resolveMenuUrl($m['url']);
        }
        unset($m);
        $cache[$location] = $rows;
    }
    return $cache[$location];
}

function resolveMenuUrl($url)
{
    if (strpos($url, '{{home}}') === 0) {
        return homeUrl() . ltrim(substr($url, strlen('{{home}}')), '/');
    }
    // 动态占位符（自动适配伪静态开关）：{{cat:slug}} / {{cat_first}} / {{tags}} / {{rss}} / {{sitemap}}
    if (preg_match('/^\{\{cat:([^}]+)\}\}$/', $url, $m)) {
        $cat = dbOne('SELECT * FROM vd_categories WHERE slug=? LIMIT 1', [$m[1]]);
        return $cat ? categoryUrl($cat) : homeUrl();
    }
    if ($url === '{{cat_first}}') {
        $cat = dbOne('SELECT * FROM vd_categories ORDER BY id ASC LIMIT 1');
        return $cat ? categoryUrl($cat) : homeUrl();
    }
    if ($url === '{{tags}}') return tagsUrl();
    if ($url === '{{rss}}') return feedUrl();
    if ($url === '{{sitemap}}') return sitemapUrl();
    return $url;
}

/**
 * 渲染分类导航树 HTML（含全部子分类，层级缩进）。
 * 供顶部导航「分类」菜单使用：展示全部分类，不只是一个分类。
 */
function renderCategoryNavTree($cats, $parent = 0, $depth = 0)
{
    $html = '';
    foreach ($cats as $c) {
        if ((int)$c['parent_id'] !== (int)$parent) continue;
        $name = esc(L($c, 'name'));
        $url  = categoryUrl($c);
        $cls  = $depth > 0 ? ' class="nav-drop-sub"' : '';
        $html .= '<a href="' . esc($url) . '"' . $cls . '>' . ($depth ? '↳ ' : '') . $name . '</a>';
        $html .= renderCategoryNavTree($cats, (int)$c['id'], $depth + 1);
    }
    return $html;
}

/** 判断某菜单项是否应渲染为「分类下拉树」（{{cat_first}} 占位符且存在分类） */
function isCategoryTreeMenu($menu)
{
    if (($menu['url'] ?? '') !== '{{cat_first}}') return false;
    $cats = getCategories();
    return !empty($cats);
}

/** 标签汇总页 URL（动态 /tags.php，伪静态 /tags） */
function tagsUrl()
{
    return prettyOn() ? langBase() . '/tags' : withLang(baseUrl('tags.php'));
}

// ---------- 伪静态 ----------

function prettyOn()
{
    return getOption('pretty_url', defined('PRETTY_URLS') && PRETTY_URLS ? '1' : '0') === '1';
}

function prettyMode()
{
    return getOption('pretty_mode', 'slug');
}

function langPrefix()
{
    // v4：中文永远根目录（无前缀）；仅英文站多 /en 前缀
    if (currentLang() === 'en') return '/en';
    return '';
}

/** 语言作用域的站点路径前缀，例如 /verda/cn 或 /verda/en（无尾斜杠） */
function langBase()
{
    return rtrim(siteBase() . langPrefix(), '/');
}

/** 非 pretty 模式下给 URL 追加 ?lang=en（zh 不加） */
function withLang($url)
{
    if (currentLang() !== 'en') return $url;
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'lang=en';
}

function homeUrl()
{
    if (prettyOn()) {
        return langBase() . '/';
    }
    return withLang(baseUrl());
}

/** 首页分页链接（语言感知） */
function homePageUrl($i)
{
    if ((int)$i <= 1) return homeUrl();
    return prettyOn() ? langBase() . '/?p=' . (int)$i : withLang(baseUrl('?p=' . (int)$i));
}

/**
 * 通用分页条（大数据友好：窗口页码 + 省略号 + 上一页/下一页）
 * 修复：总页数多时（如 1 万篇 / 每页 10 = 1000+ 页）不再把全部页码铺开渲染。
 * @param int $current 当前页
 * @param int $total 总页数
 * @param callable $urlFn 生成「第 $i 页」URL 的回调
 * @return string
 */
function renderPager($current, $total, $urlFn)
{
    $total   = max(1, (int)$total);
    $current = max(1, min((int)$current, $total));
    if ($total <= 1) return '';

    $out = '<nav class="pagination">';

    // 上一页
    if ($current > 1) {
        $out .= '<a class="pager-prev" href="' . esc(call_user_func($urlFn, $current - 1)) . '">« ' . __('上一页') . '</a>';
    } else {
        $out .= '<span class="pager-disabled">« ' . __('上一页') . '</span>';
    }

    // 页码窗口：始终含 1 与末页，当前页前后各 2 页，其余用省略号
    $seq   = [1];
    $start = max(2, $current - 2);
    $end   = min($total - 1, $current + 2);
    if ($start > 2) $seq[] = '…';
    for ($i = $start; $i <= $end; $i++) $seq[] = $i;
    if ($end < $total - 1) $seq[] = '…';
    if ($total > 1) $seq[] = $total;

    foreach ($seq as $p) {
        if ($p === '…') { $out .= '<span class="pager-ellipsis">…</span>'; continue; }
        if ((int)$p === $current) {
            $out .= '<span class="current">' . (int)$p . '</span>';
        } else {
            $out .= '<a href="' . esc(call_user_func($urlFn, $p)) . '">' . (int)$p . '</a>';
        }
    }

    // 下一页
    if ($current < $total) {
        $out .= '<a class="pager-next" href="' . esc(call_user_func($urlFn, $current + 1)) . '">' . __('下一页') . ' »</a>';
    } else {
        $out .= '<span class="pager-disabled">' . __('下一页') . ' »</span>';
    }

    $out .= '</nav>';
    return $out;
}

/** 指定语言下的文章 URL（常用于列表里的「查看英文版」链接） */
function postUrlForLang($post, $lang)
{
    $save = currentLang();
    setCurrentLang($lang === 'en' ? 'en' : 'zh');
    $u = postUrl($post);
    setCurrentLang($save);
    return $u;
}

function postUrl($post)
{
    // en 态优先 slug_en（英文 URL 别名），回退中文 slug
    $slug = currentLang() === 'en' && !empty($post['slug_en']) ? $post['slug_en'] : ($post['slug'] ?: $post['id']);
    if (prettyOn()) {
        $mode = prettyMode();
        if ($mode === 'html') {
            return langBase() . '/' . urlencode($slug) . '.html';
        }
        if ($mode === 'archive') {
            return langBase() . '/archives/' . (int)$post['id'] . '.html';
        }
        return langBase() . '/post/' . urlencode($slug);
    }
    return withLang(baseUrl('post.php?p=' . (int)$post['id']));
}

function pageUrl($pg)
{
    // en 态优先 slug_en，回退中文 slug
    $slug = currentLang() === 'en' && !empty($pg['slug_en']) ? $pg['slug_en'] : $pg['slug'];
    if (prettyOn()) {
        $mode = prettyMode();
        if ($mode === 'html') {
            return langBase() . '/' . urlencode($slug) . '.html';
        }
        return langBase() . '/page/' . urlencode($slug);
    }
    return withLang(baseUrl('page.php?p=' . urlencode($slug)));
}

function categoryUrl($cat)
{
    return prettyOn()
        ? langBase() . '/category/' . urlencode($cat['slug'])
        : withLang(baseUrl('category.php?c=' . urlencode($cat['slug'])));
}

function tagUrl($tag)
{
    return prettyOn()
        ? langBase() . '/tag/' . urlencode($tag['slug'])
        : withLang(baseUrl('tag.php?t=' . urlencode($tag['slug'])));
}

function archiveUrl($ym)
{
    return prettyOn()
        ? langBase() . '/archive?archive=' . urlencode($ym)
        : withLang(baseUrl('search.php?archive=' . urlencode($ym)));
}

function searchUrl($q = '')
{
    $suffix = $q !== '' ? '?q=' . urlencode($q) : '';
    return prettyOn() ? langBase() . '/search' . $suffix : withLang(baseUrl('search.php' . $suffix));
}

function sitemapUrl()
{
    return prettyOn() ? siteBase() . '/sitemap.xml' : baseUrl('sitemap.php');
}

function feedUrl()
{
    return prettyOn() ? langBase() . '/feed' : withLang(baseUrl('feed.php'));
}

function categoryPageUrl($cat, $i)
{
    return prettyOn()
        ? categoryUrl($cat) . '?p=' . $i
        : withLang(baseUrl('category.php?c=' . urlencode($cat['slug']) . '&p=' . $i));
}

function searchPageUrl($q, $archive, $i)
{
    if ($archive) {
        return prettyOn()
            ? langBase() . '/search?archive=' . urlencode($archive) . '&p=' . $i
            : withLang(baseUrl('search.php?archive=' . urlencode($archive) . '&p=' . $i));
    }
    return prettyOn()
        ? langBase() . '/search?q=' . urlencode($q) . '&p=' . $i
        : withLang(baseUrl('search.php?q=' . urlencode($q) . '&p=' . $i));
}

// ---------- 主题 / 内容渲染 ----------

function currentTheme()
{
    // 访客主题预览覆盖（?theme= / cookie），仅演示场景使用
    $ov = previewThemeOverride();
    if ($ov !== '') return $ov;

    // theme = 自定义主题名（usr/theme/<name>，由外观页激活）；
    // theme_style = 内置配色（fresh/forest/mint）。两者独立，互不覆盖。
    $t = getOption('theme', '');
    if ($t !== '' && is_dir(RYEBLOG_ROOT . '/usr/theme/' . $t)) return $t;
    return getOption('theme_style', 'fresh');
}

/**
 * 主题在线切换（演示站/访客预览用）：
 * 前端可通过 ?theme=<name> 或 cookie rye_theme 临时覆盖当前主题，无需后台切换。
 * 支持：usr/theme 下的自定义主题目录 + 内置配色（fresh/forest/mint）。
 * 仅当目标主题真实存在时生效；普通站点不受影响（没人传参就不覆盖）。
 */
function previewThemeOverride()
{
    $t = $_GET['theme'] ?? ($_COOKIE['rye_theme'] ?? '');
    $t = preg_replace('/[^a-z0-9_-]/i', '', (string)$t);
    if ($t === '') return '';
    // 内置配色主题（fresh/forest/mint）允许在线预览
    if (in_array($t, ['fresh', 'forest', 'mint'], true)) return $t;
    if (!is_dir(RYEBLOG_ROOT . '/usr/theme/' . $t)) return '';
    return $t;
}

/**
 * 主题模板路径：主题目录可带 home.php / post.php / page.php 模板
 * （存在则对应页面由主题模板渲染，实现文档站/产品站等特殊首页与阅读版式）。
 */
function themeTemplate($name)
{
    $t = currentTheme();
    $f = RYEBLOG_ROOT . '/usr/theme/' . $t . '/' . $name . '.php';
    return is_file($f) ? $f : '';
}

/**
 * 自动分段（wpautop 等价实现）。
 * WordPress 导出的正文常以裸文本 + 双换行存储，靠 wpautop 在前台补 <p>；
 * RyeBlog 渲染 HTML 时不补段落，故把「空行分隔的文本块」包进 <p>，
 * 已有块级标签（h2/ul/blockquote/pre…）与 inline 标签（strong/a…）原样保留。
 * 返回 cleaned HTML，不改动数据库。
 */
function autoParagraph($html, $br = true)
{
    if (trim($html) === '') return $html;

    $slots = [];
    $stash = function ($htmlFragment) use (&$slots) {
        $k = "\x01BLK" . count($slots) . "\x01";
        $slots[$k] = $htmlFragment;
        return $k;
    };

    // 1) 先抽走 <pre>（内部换行不可当分段）
    $html = preg_replace_callback('#<pre\b[^>]*>.*?</pre>#is', function ($m) use ($stash) {
        return $stash($m[0]);
    }, $html);

    // 2) 抽走已有块级元素（含多行 <p>…</p>、<blockquote>、<ul> 等），避免被二次拆分
    $blockEls = 'p|blockquote|ul|ol|li|table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|select|form|address|style|h[1-6]';
    $html = preg_replace_callback('#<(' . $blockEls . ')(?:\s[^>]*)?>.*?</\1>#is', function ($m) use ($stash) {
        return $stash($m[0]);
    }, $html);

    // 3) 把占位符孤立成独立行，避免与零散文本混在同一块
    $html = preg_replace('#(\x01BLK\d+\x01)#', "\n$1\n", $html);
    $html = preg_replace('/\n{3,}/', "\n\n", $html);

    // 4) 零散文本按空行包成 <p>
    $chunks = preg_split('/\n{2,}/', $html);
    $out = [];
    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') continue;
        if (preg_match('#^\x01BLK\d+\x01$#', $chunk)) {
            $out[] = $chunk; // 块级元素原样保留
        } else {
            $text = $br ? nl2br(preg_replace('#<br\s*/?>#i', '', $chunk)) : $chunk;
            $out[] = '<p>' . $text . '</p>';
        }
    }
    $html = implode("\n", $out);

    // 5) 还原 <pre> 与块级元素
    foreach ($slots as $k => $v) {
        $html = str_replace($k, $v, $html);
    }
    return $html;
}

function renderContent($content, $format)
{
    return $format === 'markdown' ? markdownToHtml($content) : (string)$content;
}

/**
 * 正文渲染缓存：键含 content 哈希（编辑后 content/updated_at 变化 → 自然换新文件，无需显式失效）。
 * 文章页（访问大头）从「每次全量 Markdown 解析」变为「读文件」，长文收益最大（百万级站）。
 */
function renderContentWithTocCached($postId, $content, $format)
{
    static $mem = [];
    $k = (int)$postId . '|' . $format . '|' . md5((string)$content);
    if (isset($mem[$k])) return $mem[$k];

    $dir = RYEBLOG_ROOT . '/usr/cache/post_html/';
    $f = $dir . (int)$postId . '_' . substr(md5($k), 0, 10) . '.json';
    if (is_file($f)) {
        $dec = json_decode(@file_get_contents($f), true);
        if (is_array($dec) && isset($dec['html'], $dec['toc'])) {
            $mem[$k] = $dec;
            return $dec;
        }
    }
    $rendered = renderContentWithToc($content, $format);
    @mkdir($dir, 0755, true);
    @file_put_contents($f, json_encode($rendered, JSON_UNESCAPED_UNICODE));
    $mem[$k] = $rendered;
    return $rendered;
}

/** 编辑/删除文章时清理该篇正文渲染缓存（可选，防磁盘膨胀） */
function clearPostHtmlCache($postId)
{
    foreach (glob(RYEBLOG_ROOT . '/usr/cache/post_html/' . (int)$postId . '_*.json') ?: [] as $f) {
        @unlink($f);
    }
}

/**
 * 渲染文章正文并为 h2/h3 注入 id，返回值为 ['html' => ..., 'toc' => [['level'=>2,'text'=>...,'id'=>...], ...]]
 * HTML 模式仅做 id 注入（基于正则），Markdown 模式先渲染再注入。
 */
function renderContentWithToc($content, $format)
{
    $html = renderContent($content, $format);
    $toc  = [];
    // 先按出现顺序给 h2~h6 注入 id
    $html = preg_replace_callback('/<(h[23456])>(.*?)<\/\1>/is', function ($m) use (&$toc) {
        $tag = $m[1];
        $inner = $m[2];
        $text = trim(strip_tags($inner));
        if ($text === '') return $m[0];
        $slug = 'md-' . (count($toc) + 1) . '-' . preg_replace('/[^a-z0-9]+/i', '-', mb_substr($text, 0, 30));
        $slug = trim($slug, '-') ?: ('md-' . (count($toc) + 1));
        $id   = $slug;
        // 防止重复 id
        $existing = array_column($toc, 'id');
        $n = 2;
        while (in_array($id, $existing, true)) { $id = $slug . '-' . $n++; }
        $toc[] = ['level' => (int)substr($tag, 1), 'text' => $text, 'id' => $id];
        return '<' . $tag . ' id="' . $id . '">' . $inner . '</' . $tag . '>';
    }, $html);
    return ['html' => $html, 'toc' => $toc];
}

/**
 * 把 TOC 数组渲染为 HTML 列表（h2~h6 五级，超出按最深 6 级规范化）
 */
function renderTocList($toc)
{
    if (empty($toc)) return '';
    // 规范化：所有 > 6 的级别视为 3
    $norm = array_map(function ($i) {
        $i['level'] = min(6, max(2, $i['level']));
        return $i;
    }, $toc);

    $html = '<ul class="toc-list">';
    $stack = [];        // 当前开着的层级栈（数字）
    $prevLevel = 0;     // 上一项层级
    foreach ($norm as $item) {
        $level = $item['level'];
        if ($prevLevel === 0) {
            // 第一项，直接放 li
            $html .= '<li class="toc-h' . $level . '"><a href="#' . esc($item['id']) . '">' . esc($item['text']) . '</a>';
            $stack = [$level];
        } elseif ($level > $prevLevel) {
            // 提级：开子 ul，并作为上级的子节点
            $html .= '<ul class="toc-sub"><li class="toc-h' . $level . '"><a href="#' . esc($item['id']) . '">' . esc($item['text']) . '</a>';
            $stack[] = $level;
        } else {
            // 同级或降级：先关前一个 li
            $html .= '</li>';
            while (count($stack) > 1 && end($stack) > $level) {
                array_pop($stack);
                $html .= '</ul></li>';
            }
            $html .= '<li class="toc-h' . $level . '"><a href="#' . esc($item['id']) . '">' . esc($item['text']) . '</a>';
            // 调整栈到当前层级
            while (count($stack) > 1 && end($stack) > $level) array_pop($stack);
        }
        $prevLevel = $level;
    }
    // 收尾：闭合所有未关闭的
    $html .= '</li>';
    while (count($stack) > 1) {
        array_pop($stack);
        $html .= '</ul></li>';
    }
    $html .= '</ul>';
    return $html;
}

function excerptOf($content, $format, $len = 220)
{
    // 轻量纯文本摘要：直接对原文做去标签 / 去 Markdown·媒体Wiki 语法 + 截断，不调用 renderContent。
    // 完整 Markdown 渲染（markdownToHtml）对百万字长文（维基百科条目）极慢，而列表页每张卡片都会调用，
    // 逐卡片完整渲染会导致整页超时（30s+）。摘要只需约 220 字纯文本，无需完整 HTML 渲染。
    $text = $content;
    if ($format === 'markdown') {
        $text = preg_replace([
            '/```[\s\S]*?```/',
            '/~~~[\s\S]*?~~~/',
            '/`[^`]*`/',
            '/!\[[^\]]*\]\([^)]*\)/',
            '/\[([^\]]+)\]\([^)]*\)/',
            '/\[\[([^\]|]+)(?:\|[^\]]+)?\]\]/',
            '/^#{1,6}\s+/m',
            '/\*\*([^*]+)\*\*/',
            '/\*([^*]+)\*/',
            '/__([^_]+)__/',
            '/~~([^~]+)~~/',
            '/<!--[\s\S]*?-->/',
        ], ['', '', '', '', '$1', '$1', '', '$1', '$1', '$1', '', ''], $text);
    }
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text) > $len) {
        $text = mb_substr($text, 0, $len) . '…';
    }
    return $text;
}

/** 文章摘要：优先用 excerpt 字段，否则从（当前语言对应的）正文生成 */
function postExcerpt($post, $len = 220)
{
    // en 态优先 excerpt_en，回退中文摘要/正文
    if (currentLang() === 'en' && !empty($post['excerpt_en'])) {
        return $post['excerpt_en'];
    }
    if (!empty($post['excerpt'])) {
        return $post['excerpt'];
    }
    return excerptOf(L($post, 'content'), $post['format'] ?? 'html', $len);
}

/** SEO 描述：优先当前语言的 seo_description / excerpt，最后（当前语言对应的）正文摘要 */
function postSeoDescription($post)
{
    if (currentLang() === 'en') {
        if (!empty($post['seo_description_en'])) return $post['seo_description_en'];
        if (!empty($post['excerpt_en'])) return $post['excerpt_en'];
    }
    if (!empty($post['seo_description'])) return $post['seo_description'];
    if (!empty($post['excerpt'])) return $post['excerpt'];
    return excerptOf(L($post, 'content'), $post['format'] ?? 'html', 160);
}

function postSeoKeywords($post)
{
    if (currentLang() === 'en' && !empty($post['seo_keywords_en'])) return $post['seo_keywords_en'];
    if (!empty($post['seo_keywords'])) return $post['seo_keywords'];
    $tags = getPostTags($post['id']);
    return implode(',', array_map(function ($t) { return L($t, 'name'); }, $tags));
}

// ---------- 归档 ----------

/**
 * 归档月计数物化（P1）：vd_archive_stats(ym, cnt) 表维护各月 published 文章数。
 * 读 O(1)（273 行直查，无全表 GROUP BY）；写路径增量（发文章 +1），删除/恢复/清空后全量校准
 * rebuildArchiveStats()（成本与文章数相关，普通站 <100ms，百万级站仅在低频删除时触发）。
 */
function ensureArchiveStatsTable()
{
    static $ok = false;
    if (!$ok) {
        dbQuery('CREATE TABLE IF NOT EXISTS vd_archive_stats (
            ym CHAR(7) NOT NULL PRIMARY KEY,
            cnt INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $ok = true;
    }
}

/** 全量重建归档月计数（旧站首次 / 删除恢复后校准；百万级约 3s，低频操作可接受） */
function rebuildArchiveStats()
{
    ensureArchiveStatsTable();
    dbQuery('TRUNCATE TABLE vd_archive_stats');
    dbQuery("INSERT INTO vd_archive_stats (ym, cnt)
             SELECT DATE_FORMAT(created_at,'%Y-%m'), COUNT(*)
             FROM vd_posts WHERE type='post' AND status='published'
             GROUP BY DATE_FORMAT(created_at,'%Y-%m')");
}

/** 增量：新文章发布 +1（created_at=NOW()，用当前月） */
function bumpArchiveStatsNow()
{
    ensureArchiveStatsTable();
    dbQuery("INSERT INTO vd_archive_stats (ym, cnt) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE cnt = cnt + 1", [date('Y-m')]);
}

function getArchiveMonths()
{
    static $mem = null;
    if ($mem !== null) return $mem;

    // 物化计数表直查（273 行，毫秒级）；表空（旧站未初始化）→ 全量重建一次
    $rows = [];
    try {
        ensureArchiveStatsTable();
        $rows = dbAll('SELECT ym, cnt AS c FROM vd_archive_stats ORDER BY ym DESC');
        if (!$rows) {
            rebuildArchiveStats();
            $rows = dbAll('SELECT ym, cnt AS c FROM vd_archive_stats ORDER BY ym DESC');
        }
    } catch (\Throwable $e) {
        // 兜底：表结构异常时回退原全表 GROUP BY
        $rows = dbAll("SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS c
                       FROM vd_posts WHERE type='post' AND status='published'
                       GROUP BY ym ORDER BY ym DESC");
    }
    $mem = $rows;
    return $rows;
}

// ---------- 侧边栏模块系统 ----------

/**
 * 侧边栏模块注册表 —— 定义所有可用模块。
 * 每个模块: id => [title, desc, has_limit, special(optional)]
 * special 模块（author_card / toc）不参与普通启用/禁用开关。
 */
function sidebarModuleRegistry()
{
    return [
        'author_card'     => ['title' => '博主信息卡', 'desc' => '仅首页显示', 'has_limit' => false, 'special' => true],
        'toc'             => ['title' => '文章目录导航', 'desc' => '仅文章详情页显示', 'has_limit' => false, 'special' => true],
        'search'          => ['title' => '搜索框',       'desc' => '关键词搜索', 'has_limit' => false],
        'categories'      => ['title' => '分类',         'desc' => '全部分类列表', 'has_limit' => false],
        'tags'            => ['title' => '标签云',       'desc' => '热门标签', 'has_limit' => true, 'default_limit' => 20],
        'archive'         => ['title' => '归档',         'desc' => '按月统计', 'has_limit' => false],
        'recent_posts'    => ['title' => '最新文章',     'desc' => '最新发布的文章', 'has_limit' => true, 'default_limit' => 5],
        'recent_comments' => ['title' => '最新评论',     'desc' => '最新发表的评论', 'has_limit' => true, 'default_limit' => 5],
        'hot_posts'       => ['title' => '最热文章',     'desc' => '浏览量最高的文章', 'has_limit' => true, 'default_limit' => 5],
        'hot_comments'    => ['title' => '最热评论',     'desc' => '评论数最多的文章', 'has_limit' => true, 'default_limit' => 5],
    ];
}

/**
 * 页面类型注册表 —— 定义所有可配置侧边栏的页面类型。
 */
function sidebarPageTypes()
{
    return [
        'home'     => '首页',
        'category' => '分类页',
        'tag'      => '标签页',
        'search'   => '搜索/归档页',
        'post'     => '文章详情页',
    ];
}

/**
 * 获取侧边栏模块的默认配置。
 * 返回结构: [pageType => [moduleId => [enabled, position, order, limit]]]
 */
function sidebarDefaultConfig()
{
    $pages = sidebarPageTypes();
    $reg   = sidebarModuleRegistry();

    $config = [];
    foreach ($pages as $pt => $label) {
        $cfg = [];
        $order = 0;
        // 特殊模块位置（author_card 仅 home, toc 仅 post）
        if ($pt === 'home') {
            $cfg['author_card'] = ['enabled' => true, 'position' => 'top', 'order' => $order++, 'limit' => 0];
        }
        if ($pt === 'post') {
            $cfg['toc'] = ['enabled' => true, 'position' => 'top', 'order' => $order++, 'limit' => 0];
        }

        // 通用模块默认配置
        $cfg['search']          = ['enabled' => true, 'position' => 'top', 'order' => $order++, 'limit' => 0];
        $cfg['categories']      = ['enabled' => true, 'position' => 'top', 'order' => $order++, 'limit' => 0];
        $cfg['tags']            = ['enabled' => true, 'position' => 'top', 'order' => $order++, 'limit' => $reg['tags']['default_limit']];
        $cfg['archive']         = ['enabled' => true, 'position' => 'top', 'order' => $order++, 'limit' => 0];
        $cfg['recent_posts']    = ['enabled' => true, 'position' => 'bottom', 'order' => 0, 'limit' => $reg['recent_posts']['default_limit']];
        $cfg['recent_comments'] = ['enabled' => true, 'position' => 'bottom', 'order' => 1, 'limit' => $reg['recent_comments']['default_limit']];
        $cfg['hot_posts']       = ['enabled' => false, 'position' => 'bottom', 'order' => 2, 'limit' => $reg['hot_posts']['default_limit']];
        $cfg['hot_comments']    = ['enabled' => false, 'position' => 'bottom', 'order' => 3, 'limit' => $reg['hot_comments']['default_limit']];

        $config[$pt] = $cfg;
    }
    return $config;
}

/**
 * 从数据库读取侧边栏配置，合并默认值确保完整性。
 */
function getSidebarConfig()
{
    $json = getOption('sidebar_config', '');
    $saved = $json ? json_decode($json, true) : [];
    if (!is_array($saved)) $saved = [];

    $defaults = sidebarDefaultConfig();
    $config = [];
    foreach ($defaults as $pt => $modules) {
        $config[$pt] = [];
        foreach ($modules as $mid => $def) {
            $s = $saved[$pt][$mid] ?? [];
            $config[$pt][$mid] = [
                'enabled'  => isset($s['enabled']) ? (bool)$s['enabled'] : $def['enabled'],
                'position' => $s['position'] ?? $def['position'],
                'order'    => isset($s['order']) ? (int)$s['order'] : $def['order'],
                'limit'    => isset($s['limit']) ? (int)$s['limit'] : $def['limit'],
            ];
        }
    }
    return $config;
}

/**
 * 保存侧边栏配置到数据库。
 */
function saveSidebarConfig($config)
{
    setOption('sidebar_config', json_encode($config, JSON_UNESCAPED_UNICODE));
}

/**
 * 自动检测当前页面类型。
 */
function detectPageType()
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    switch ($script) {
        case 'index.php':    return 'home';
        case 'category.php': return 'category';
        case 'tag.php':      return 'tag';
        case 'search.php':   return 'search';
        case 'post.php':     return 'post';
        case 'page.php':     return 'post'; // 自定义页面也算内容页
        case 'slug.php':     return 'post'; // html 模式 {slug}.html 由 slug.php 分派到内容页
        default:             return 'home';
    }
}

/**
 * 获取指定页面类型的已启用模块列表，按 position + order 排序。
 * 返回 [[moduleId => cfg, ...], [moduleId => cfg, ...]]  两个数组：top / bottom
 */
function getSidebarModules($pageType)
{
    $config = getSidebarConfig();
    $modules = $config[$pageType] ?? [];

    $top = [];
    $bottom = [];
    foreach ($modules as $mid => $cfg) {
        if (!$cfg['enabled']) continue;
        if ($cfg['position'] === 'top') {
            $top[$mid] = $cfg;
        } else {
            $bottom[$mid] = $cfg;
        }
    }

    // 先按注册表顺序（registry）排好，保证同 order 时的顺序确定；
    // 再用 order 排序。PHP 8 的 uasort 是稳定排序，会保留注册表顺序作为同值兜底。
    $regKeys = array_keys(sidebarModuleRegistry());
    $topOrdered = [];
    foreach ($regKeys as $mid) {
        if (isset($top[$mid])) $topOrdered[$mid] = $top[$mid];
    }
    $bottomOrdered = [];
    foreach ($regKeys as $mid) {
        if (isset($bottom[$mid])) $bottomOrdered[$mid] = $bottom[$mid];
    }

    uasort($topOrdered, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    uasort($bottomOrdered, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    return [$topOrdered, $bottomOrdered];
}

/**
 * 渲染单个侧边栏模块的 HTML。
 */
function renderSidebarModule($moduleId, $cfg)
{
    $limit = (int)($cfg['limit'] ?? 0);
    switch ($moduleId) {
        case 'search':
            return '<div class="widget widget-search">'
                . '<form class="site-search" action="' . searchUrl() . '" method="get">'
                . '<input type="search" name="q" placeholder="' . __("搜索文章…") . '" value="' . esc($_GET['q'] ?? '') . '">'
                . '<button type="submit" aria-label="' . __("搜索") . '">🔍</button>'
                . '</form></div>';

        case 'author_card':
            $card = authorCard();
            if (!$card['show']) return '';
            $firstLetter = mb_strtoupper(mb_substr($card['name'] ?: '博', 0, 1, 'UTF-8'));
            $html = '<aside class="author-card"><div class="ac-banner">';
            if ($card['image']) {
                $html .= '<img class="ac-banner-img" src="' . esc($card['image']) . '" alt="">';
            }
            $html .= '</div><div class="ac-body">';
            if ($card['avatar']) {
                $html .= '<img class="ac-avatar" src="' . esc($card['avatar']) . '" alt="' . esc($card['name']) . '">';
            } else {
                $html .= '<div class="ac-avatar" style="display:flex;align-items:center;justify-content:center;color:#fff;font-size:36px;font-weight:700;background:linear-gradient(135deg,var(--g-500),var(--g-700))">' . esc($firstLetter) . '</div>';
            }
            if (!empty($card['title']) && $card['title'] !== '关于博主') {
                $html .= '<p class="ac-title">' . esc($card['title']) . '</p>';
            }
            $html .= '<p class="ac-name">' . esc($card['name']) . '</p>';
            if (!empty($card['bio'])) {
                $html .= '<p class="ac-bio">' . nl2br(esc($card['bio'])) . '</p>';
            }
            $html .= '</div></aside>';
            return $html;

        case 'toc':
            $tocHtml = $GLOBALS['__rye_toc_html'] ?? '';
            if ($tocHtml === '') return '';
            return '<div class="widget widget-toc"><h3>📑 ' . __('文章目录') . '</h3>' . $tocHtml . '</div>';

        case 'categories':
            $cats = getCategories();
            $html = '<div class="widget"><h3>' . __('分类') . '</h3><ul>';
            foreach ($cats as $c) {
                $html .= '<li><a href="' . categoryUrl($c) . '">' . esc(L($c, 'name')) . ' <small>(' . (int)$c['post_count'] . ')</small></a></li>';
            }
            $html .= '</ul></div>';
            return $html;

        case 'tags':
            $tags = tagCloud(max(1, $limit));
            if (!$tags) return '';
            $html = '<div class="widget"><h3>' . __('标签') . '</h3><div class="tag-cloud">';
            foreach ($tags as $t) {
                $html .= '<a href="' . tagUrl($t) . '">' . esc(L($t, 'name')) . '</a>';
            }
            $html .= '</div></div>';
            return $html;

        case 'archive':
            $months = getArchiveMonths();
            if (!$months) return '';
            $shown = array_slice($months, 0, 24);
            $html = '<div class="widget"><h3>' . __('归档') . '</h3><ul>';
            foreach ($shown as $m) {
                $html .= '<li><a href="' . archiveUrl($m['ym']) . '">' . $m['ym'] . ' <small>(' . (int)$m['c'] . ')</small></a></li>';
            }
            $html .= '</ul>';
            if (count($months) > count($shown)) {
                $html .= '<a class="widget-more" href="' . baseUrl('archive.php') . '">' . __('更多归档…') . '</a>';
            }
            $html .= '</div>';
            return $html;

        case 'recent_posts':
            $posts = getPosts(['perPage' => max(1, $limit)])['items'];
            if (!$posts) return '';
            $html = '<div class="widget"><h3>' . __('最新文章') . '</h3><ul>';
            foreach ($posts as $p) {
                $html .= '<li><a href="' . postUrl($p) . '">' . esc(L($p, 'title')) . '</a></li>';
            }
            $html .= '</ul></div>';
            return $html;

        case 'recent_comments':
            $rows = getRecentComments(max(1, $limit));
            if (!$rows) return '';
            $html = '<div class="widget"><h3>' . __('最新评论') . '</h3><ul>';
            foreach ($rows as $r) {
                $html .= '<li><a href="' . postUrl($r) . '">' . esc(L($r, 'title')) . '</a>'
                    . '<br><small>' . esc($r['author']) . '：' . mb_substr(strip_tags($r['content']), 0, 30) . '…</small></li>';
            }
            $html .= '</ul></div>';
            return $html;

        case 'hot_posts':
            $posts = getHotPosts(max(1, $limit));
            if (!$posts) return '';
            $html = '<div class="widget"><h3>' . __('最热文章') . '</h3><ul>';
            foreach ($posts as $p) {
                $html .= '<li><a href="' . postUrl($p) . '">' . esc(L($p, 'title')) . '</a> <small>(' . (int)$p['views'] . ')</small></li>';
            }
            $html .= '</ul></div>';
            return $html;

        case 'hot_comments':
            $rows = getHotCommentPosts(max(1, $limit));
            if (!$rows) return '';
            $html = '<div class="widget"><h3>' . __('最热评论') . '</h3><ul>';
            foreach ($rows as $r) {
                $html .= '<li><a href="' . postUrl($r) . '">' . esc(L($r, 'title')) . '</a> <small>(' . (int)$r['comment_count'] . ')</small></li>';
            }
            $html .= '</ul></div>';
            return $html;

        default:
            return '';
    }
}

/**
 * 渲染整个侧边栏（top + bottom 区域）。
 */
function renderSidebar($pageType)
{
    [$top, $bottom] = getSidebarModules($pageType);
    $html = '';
    foreach ($top as $mid => $cfg) {
        $html .= renderSidebarModule($mid, $cfg);
    }
    $html .= doHook('sidebar_top');
    foreach ($bottom as $mid => $cfg) {
        $html .= renderSidebarModule($mid, $cfg);
    }
    $html .= doHook('sidebar_bottom');
    return $html;
}

// ---------- 侧边栏数据查询 ----------

/**
 * 获取最热文章（按浏览量降序）。
 */
function getHotPosts($limit = 5)
{
    // views 列无索引，ORDER BY views DESC 在百万级是 170 万行全表 filesort → 套 10 分钟缓存（重建 1-2s 可接受）
    return ryeblogCacheGet('hot_posts_' . (int)$limit, 600, function () use ($limit) {
        return dbAll(
            "SELECT * FROM vd_posts WHERE type='post' AND status='published' ORDER BY views DESC LIMIT " . (int)$limit
        );
    });
}

/**
 * 获取最新评论（跨文章）。
 */
function getRecentComments($limit = 5)
{
    return dbAll(
        "SELECT c.*, p.title, p.slug
         FROM vd_comments c
         JOIN vd_posts p ON p.id = c.post_id
         WHERE c.status = 'approved' AND p.type='post' AND p.status='published'
         ORDER BY c.id DESC LIMIT " . (int)$limit
    );
}

/**
 * 获取评论数最多的文章。
 */
function getHotCommentPosts($limit = 5)
{
    // 原实现：170 万行 posts 全扫 + 每行相关子查询 + HAVING 排序（灾难级）。
    // 改为两步：① 评论表按 post_id 分组取 top-N（走 post_id 索引，只扫评论表）；
    //           ② 文章表按主键 IN 取回。全程走索引 + 10 分钟缓存。
    return ryeblogCacheGet('hot_comment_posts_' . (int)$limit, 600, function () use ($limit) {
        $rows = dbAll("SELECT post_id, COUNT(*) AS c FROM vd_comments
                       WHERE status='approved' GROUP BY post_id ORDER BY c DESC LIMIT " . (int)$limit);
        if (!$rows) return [];
        $ids = array_map('intval', array_column($rows, 'post_id'));
        $in  = implode(',', $ids);
        $byId = [];
        foreach (dbAll("SELECT * FROM vd_posts WHERE id IN ($in)") as $p) {
            $byId[$p['id']] = $p;
        }
        $out = [];
        foreach ($rows as $r) {
            if (isset($byId[$r['post_id']])) {
                $p = $byId[$r['post_id']];
                $p['comment_count'] = (int)$r['c'];
                $out[] = $p;
            }
        }
        return $out;
    });
}

// ---------- 后台鉴权 ----------

function isAdmin()
{
    return !empty($_SESSION['rye_admin']);
}

function currentAdmin()
{
    if (!isAdmin()) return null;
    return dbOne('SELECT * FROM vd_users WHERE id=?', [$_SESSION['rye_admin']]);
}

function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: ' . baseUrl('admin/login.php'));
        exit;
    }
}

function adminLogin($username, $password)
{
    $ip = clientIp();
    if (!checkLoginRate($ip, $username)) {
        return false; // 限流中
    }
    // 仅允许 admin 角色登录后台（普通注册用户即便密码正确也拒绝）
    $user = dbOne('SELECT * FROM vd_users WHERE (username=? OR email=?) AND status=1 AND role=?', [$username, $username, 'admin']);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['rye_admin'] = $user['id'];
        dbQuery('UPDATE vd_users SET login_ip=?, login_at=NOW() WHERE id=?', [$ip, $user['id']]);
        logLoginAttempt($ip, $username, true);
        cleanLoginAttempts();
        return true;
    }
    logLoginAttempt($ip, $username, false);
    return false;
}

function adminLogout()
{
    unset($_SESSION['rye_admin']);
    if (empty($_SESSION['rye_user'])) session_destroy();
}

function csrfToken()
{
    if (empty($_SESSION['rye_csrf'])) {
        $_SESSION['rye_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['rye_csrf'];
}

function checkCsrf()
{
    return isset($_POST['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_POST['_csrf']);
}

// ---------- 登录限流 ----------

function checkLoginRate($ip, $username = '')
{
    $window = 15 * 60; // 15 分钟窗口
    $maxFail = 5;      // 最多失败 5 次
    $count = (int)dbOne(
        'SELECT COUNT(*) AS c FROM vd_login_attempts WHERE ip=? AND success=0 AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
        [$ip, $window]
    )['c'];
    return $count < $maxFail;
}

function logLoginAttempt($ip, $username, $success)
{
    dbQuery('INSERT INTO vd_login_attempts (ip, username, success) VALUES (?, ?, ?)',
        [$ip, $username ?: null, $success ? 1 : 0]);
}

/** 清理过期的登录尝试记录（超过 24 小时） */
function cleanLoginAttempts()
{
    dbQuery('DELETE FROM vd_login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
}

// ---------- 文件上传安全 ----------

function getAllowedUploadExts()
{
    return ['jpg','jpeg','png','gif','webp','svg','pdf','zip','rar','7z','doc','docx','xls','xlsx','ppt','pptx','txt','mp3','mp4','md'];
}

function getMaxUploadSize()
{
    return 20 * 1024 * 1024; // 20MB
}

function getMimeForExt($ext)
{
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip', 'rar' => 'application/x-rar',
        '7z' => 'application/x-7z-compressed',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4',
    ];
    return $map[strtolower($ext)] ?? 'application/octet-stream';
}

/**
 * 检测文件真实图片格式（getimagesize 优先，魔数兜底，兼容无 webp 支持的 PHP）
 */
function detectRealImageMime($path)
{
    $info = @getimagesize($path);
    if ($info !== false && !empty($info['mime'])) return $info['mime'];
    $head = @file_get_contents($path, false, null, 0, 16);
    if ($head === false) return '';
    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
    if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) return 'image/png';
    if (strncmp($head, 'GIF8', 4) === 0) return 'image/gif';
    if (strlen($head) >= 12 && strncmp(substr($head, 8, 4), 'WEBP', 4) === 0) return 'image/webp';
    return '';
}

function validateUploadFile($file, $allowed = null, $maxSize = null)
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return '文件上传出错。';
    }
    $max = $maxSize ?: getMaxUploadSize();
    if ($file['size'] > $max) {
        return '文件大小不能超过 ' . round($max / 1048576, 1) . 'MB。';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $exts = $allowed ?: getAllowedUploadExts();
    if (!in_array($ext, $exts, true)) {
        return '不支持的文件类型：' . $ext;
    }
    // MIME 校验：检测实际文件类型
    $realMime = '';
    if (function_exists('mime_content_type') && !empty($file['tmp_name'])) {
        $realMime = mime_content_type($file['tmp_name']);
    }
    $expectedMime = getMimeForExt($ext);
    // 图片类：内容必须是有效图片即可（不强求扩展名与格式一一对应，容忍 webp 内容存 .jpg 等）
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $imageExts, true)) {
        $imgMime = detectRealImageMime($file['tmp_name']);
        if ($imgMime === '' || strpos($imgMime, 'image/') !== 0) {
            return '文件内容不是有效的图片。';
        }
    } elseif ($realMime && $realMime !== $expectedMime && $realMime !== 'application/octet-stream') {
        // 某些系统返回 generic MIME，允许通过
        return '文件内容与扩展名不匹配。';
    }
    // 禁止上传可执行文件
    $dangerous = ['php','phtml','php3','php4','php5','php7','phps','pht','htaccess','exe','bat','cmd','sh','cgi'];
    if (in_array($ext, $dangerous, true)) {
        return '禁止上传可执行文件。';
    }
    return true;
}

function sanitizeFilename($name)
{
    $name = preg_replace('/[^\w.\-]/', '_', $name);
    $name = preg_replace('/_{2,}/', '_', $name);
    return trim($name, '_.');
}

// ---------- 前台用户（用户中心） ----------

function isLoggedIn()
{
    return !empty($_SESSION['rye_user']);
}

function currentUser()
{
    if (!isLoggedIn()) return null;
    return dbOne('SELECT * FROM vd_users WHERE id=? AND status=1', [$_SESSION['rye_user']]);
}

function requireUser()
{
    if (!isLoggedIn()) {
        header('Location: ' . baseUrl('user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
        exit;
    }
}

function userRegister($username, $email, $password)
{
    $username = trim($username);
    $email    = trim($email);
    if (mb_strlen($username) < 2 || mb_strlen($username) > 30) return '用户名长度需 2-30 字符。';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '邮箱格式不正确。';
    if (strlen($password) < 6) return '密码至少 6 位。';
    if (dbOne('SELECT id FROM vd_users WHERE username=?', [$username])) return '用户名已存在。';
    if (dbOne('SELECT id FROM vd_users WHERE email=?', [$email])) return '邮箱已注册。';

    dbQuery('INSERT INTO vd_users (username, email, password, role, status, created_at) VALUES (?, ?, ?, "user", 1, NOW())',
        [$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    return true;
}

function userLogin($login, $password)
{
    $ip = clientIp();
    if (!checkLoginRate($ip, $login)) {
        return false; // 限流中
    }
    $user = dbOne('SELECT * FROM vd_users WHERE (username=? OR email=?) AND status=1', [$login, $login]);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['rye_user'] = $user['id'];
        dbQuery('UPDATE vd_users SET login_ip=?, login_at=NOW() WHERE id=?', [$ip, $user['id']]);
        logLoginAttempt($ip, $login, true);
        cleanLoginAttempts();
        return true;
    }
    logLoginAttempt($ip, $login, false);
    return false;
}

function userLogout()
{
    unset($_SESSION['rye_user']);
    if (empty($_SESSION['rye_admin'])) session_destroy();
}

function userAvatar($user, $size = 80)
{
    if (!$user) return '';
    if (!empty($user['avatar_url'])) return $user['avatar_url'];
    if (($user['avatar_source'] ?? 'gravatar') === 'gravatar') {
        $email = $user['email'] ?? '';
        $hash = md5(strtolower(trim($email)));
        return 'https://en.gravatar.com/avatar/' . $hash . '?s=' . (int)$size . '&d=identicon';
    }
    // 本地首字母头像
    $letter = mb_strtoupper(mb_substr($user['username'] ?? 'U', 0, 1, 'UTF-8'));
    $colors = ['#2e6b35', '#357a3e', '#43a047', '#1b5e20', '#558b2f'];
    $c = $colors[crc32($user['username'] ?? '') % count($colors)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '">'
         . '<rect width="100%" height="100%" fill="' . $c . '"/>'
         . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-size="' . ($size * 0.5) . '" fill="#fff" font-family="sans-serif">' . esc($letter) . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function requestPasswordReset($email)
{
    $user = dbOne('SELECT * FROM vd_users WHERE email=? AND status=1', [$email]);
    if (!$user) return false;
    $token = bin2hex(random_bytes(24));
    dbQuery('UPDATE vd_users SET reset_token=?, reset_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?', [$token, $user['id']]);
    return $token; // 调用方负责发邮件/展示链接
}

function resetPassword($token, $newPassword)
{
    if (strlen($newPassword) < 6) return '密码至少 6 位。';
    $user = dbOne('SELECT * FROM vd_users WHERE reset_token=? AND reset_expires>NOW() AND status=1', [$token]);
    if (!$user) return '重置链接无效或已过期。';
    dbQuery('UPDATE vd_users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?', [password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
    return true;
}

// ---------- 收藏 / 划线 / 纠错 / 轨迹 ----------

function isFavorited($userId, $postId)
{
    return (int)dbOne('SELECT COUNT(*) c FROM vd_favorites WHERE user_id=? AND post_id=?', [$userId, $postId])['c'] > 0;
}

function toggleFavorite($userId, $postId)
{
    if (isFavorited($userId, $postId)) {
        dbQuery('DELETE FROM vd_favorites WHERE user_id=? AND post_id=?', [$userId, $postId]);
        return false;
    }
    dbQuery('INSERT IGNORE INTO vd_favorites (user_id, post_id) VALUES (?, ?)', [$userId, $postId]);
    return true;
}

function getFavorites($userId, $limit = 50)
{
    return dbAll('SELECT f.created_at AS fav_at, p.* FROM vd_favorites f JOIN vd_posts p ON p.id=f.post_id WHERE f.user_id=? ORDER BY f.id DESC LIMIT ' . (int)$limit, [$userId]);
}

function addAnnotation($userId, $postId, $quote, $note, $anchor)
{
    return dbInsert('INSERT INTO vd_annotations (user_id, post_id, quote_text, note, anchor) VALUES (?, ?, ?, ?, ?)',
        [$userId, $postId, $quote, $note, $anchor]);
}

function getAnnotations($userId, $limit = 100)
{
    return dbAll('SELECT a.*, p.title AS post_title, p.slug AS post_slug FROM vd_annotations a JOIN vd_posts p ON p.id=a.post_id WHERE a.user_id=? ORDER BY a.id DESC LIMIT ' . (int)$limit, [$userId]);
}

function addCorrection($userId, $postId, $selected, $suggested, $reason)
{
    return dbInsert('INSERT INTO vd_corrections (user_id, post_id, selected_text, suggested_text, reason, status) VALUES (?, ?, ?, ?, ?, "pending")',
        [$userId, $postId, $selected, $suggested, $reason]);
}

function getCorrectionsByUser($userId, $limit = 100)
{
    return dbAll('SELECT cr.*, p.title AS post_title, p.slug AS post_slug FROM vd_corrections cr JOIN vd_posts p ON p.id=cr.post_id WHERE cr.user_id=? ORDER BY cr.id DESC LIMIT ' . (int)$limit, [$userId]);
}

function addTrail($userId, $post)
{
    try {
        dbQuery('INSERT INTO vd_trail (user_id, post_id, post_title, ip) VALUES (?, ?, ?, ?)',
            [$userId, $post['id'], $post['title'], clientIp()]);
    } catch (Throwable $e) {
        // 浏览轨迹非关键功能：表结构缺失/写入失败不影响页面（如旧库未 ALTER）
    }
}

function getTrail($userId, $limit = 50)
{
    return dbAll('SELECT t.*, p.slug AS post_slug FROM vd_trail t JOIN vd_posts p ON p.id=t.post_id WHERE t.user_id=? ORDER BY t.id DESC LIMIT ' . (int)$limit, [$userId]);
}

// ---------- 插件 / 主题机制（钩子） ----------

/**
 * 过滤器钩子：插件可修改并返回传入的值。
 * 插件方法签名：public static function filterXxx($value, $extra = null) { return $newValue; }
 */
function applyFilter($name, $value, $extra = null)
{
    foreach (pluginActiveList() as $dir) {
        $file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
        if (!is_file($file)) continue;
        require_once $file;
        $cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);
        $method = $name;
        if (class_exists($cls) && method_exists($cls, $method)) {
            $value = call_user_func([$cls, $method], $value, $extra);
        }
    }
    return $value;
}

/**
 * 触发 action 钩子，收集各插件返回的 HTML 字符串并拼接。
 * 与 doHook() 功能一致，名字更语义化（action 不修改数据，只输出内容）。
 */
function doAction($name, $arg = null)
{
    $out = '';
    foreach (pluginActiveList() as $dir) {
        $file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
        if (!is_file($file)) continue;
        require_once $file;
        $cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);
        if (class_exists($cls) && method_exists($cls, $name)) {
            $out .= (string)call_user_func([$cls, $name], $arg);
        }
    }
    return $out;
}

function pluginActiveList()
{
    return array_filter(array_map('trim', explode(',', getOption('active_plugins', ''))));
}

function setPluginActive($name, $active)
{
    $list = pluginActiveList();
    if ($active) {
        if (!in_array($name, $list, true)) $list[] = $name;
    } else {
        $list = array_diff($list, [$name]);
    }
    setOption('active_plugins', implode(',', $list));
}

/**
 * 激活插件：调用 activate() 生命周期方法 + 加入启用列表。
 * @return bool|string 成功返回 true，失败返回错误信息
 */
function activatePlugin($dir)
{
    $file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
    if (!is_file($file)) return '插件文件不存在。';
    require_once $file;
    $cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);
    if (class_exists($cls) && method_exists($cls, 'activate')) {
        $result = call_user_func([$cls, 'activate']);
        if ($result !== true && $result !== null) return (string)$result;
    }
    setPluginActive($dir, true);
    return true;
}

/**
 * 停用插件：调用 deactivate() 生命周期方法 + 从启用列表移除。
 * @return bool|string
 */
function deactivatePlugin($dir)
{
    $file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
    if (!is_file($file)) return '插件文件不存在。';
    require_once $file;
    $cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);
    if (class_exists($cls) && method_exists($cls, 'deactivate')) {
        $result = call_user_func([$cls, 'deactivate']);
        if ($result !== true && $result !== null) return (string)$result;
    }
    setPluginActive($dir, false);
    return true;
}

/**
 * 删除插件：先停用，再递归删除目录。
 */
function deletePlugin($dir)
{
    $dir = basename($dir);
    if ($dir === '' || $dir === '.' || $dir === '..') return '无效的插件目录名。';
    deactivatePlugin($dir);
    $path = RYEBLOG_ROOT . '/usr/plugins/' . $dir;
    if (!is_dir($path)) return '插件目录不存在。';
    return rrmdir($path) ? true : '删除失败，请检查目录权限。';
}

/**
 * 检查插件是否有配置页面（config 方法）。
 */
function pluginHasConfig($dir)
{
    $file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
    if (!is_file($file)) return false;
    require_once $file;
    $cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);
    return class_exists($cls) && method_exists($cls, 'config');
}

/**
 * 安装 ZIP 插件包：解压到 usr/plugins/。
 * @return array ['ok'=>bool,'dir'=>string,'msg'=>string]
 */
function installPluginZip($tmpFile)
{
    $dest = RYEBLOG_ROOT . '/usr/plugins';
    $result = extractZip($tmpFile, $dest);
    if (!$result['ok']) return $result;
    // 检查解压出的目录是否包含 Plugin.php
    $dir = $result['dir'];
    if (!is_file($dest . '/' . $dir . '/Plugin.php')) {
        return ['ok' => false, 'msg' => 'ZIP 包中未找到 Plugin.php，不是有效的 RyeBlog 插件。'];
    }
    return ['ok' => true, 'dir' => $dir, 'msg' => '插件安装成功。'];
}

/** 触发插件钩子，收集返回的 HTML 片段 */
function doHook($name, $arg = null)
{
    $out = '';
    foreach (pluginActiveList() as $dir) {
        $file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
        if (!is_file($file)) continue;
        require_once $file;
        $cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);
        if (class_exists($cls) && method_exists($cls, $name)) {
            $out .= (string)call_user_func([$cls, $name], $arg);
        }
    }
    return $out;
}

function scanPlugins()
{
    $dir = RYEBLOG_ROOT . '/usr/plugins';
    if (!is_dir($dir)) return [];
    $list = [];
    foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
        $name = basename($d);
        $meta = ['name' => $name, 'title' => $name, 'desc' => '', 'ver' => '', 'author' => '', 'has_config' => false, 'doc' => ''];
        $cfg = $d . '/Plugin.php';
        if (is_file($cfg)) {
            $src = file_get_contents($cfg);
            if (preg_match('/@Title\s+(.+)/', $src, $m)) $meta['title'] = trim($m[1]);
            if (preg_match('/@Desc\s+(.+)/', $src, $m)) $meta['desc'] = trim($m[1]);
            if (preg_match('/@Version\s+(.+)/', $src, $m)) $meta['ver'] = trim($m[1]);
            if (preg_match('/@Author\s+(.+)/', $src, $m)) $meta['author'] = trim($m[1]);
            if (preg_match('/@Doc\s+(.+)/', $src, $m)) {
                $docPath = trim($m[1]);
                if (preg_match('/\.md$/i', $docPath) && file_exists(RYEBLOG_ROOT . '/' . ltrim($docPath, '/'))) {
                    // 以 .md 结尾 → 走图文渲染器
                    $rel = preg_replace('/^.*?docs\//', 'docs/', ltrim($docPath, '/'));
                    $meta['doc'] = baseUrl('docs.php?doc=' . preg_replace('/\.md$/', '', $rel));
                } elseif (file_exists(RYEBLOG_ROOT . '/' . ltrim($docPath, '/'))) {
                    $meta['doc'] = baseUrl($docPath);
                }
            }
            // 自动探测 docs/plugins/<name>.md → 走图文渲染器
            if (empty($meta['doc']) && file_exists(RYEBLOG_ROOT . '/docs/plugins/' . $name . '.md')) {
                $meta['doc'] = baseUrl('docs.php?doc=plugins/' . $name);
            }
            $meta['has_config'] = pluginHasConfig($name);
        }
        $meta['active'] = in_array($name, pluginActiveList(), true);
        $list[] = $meta;
    }
    return $list;
}

function scanThemes()
{
    $builtin = [
        ['name' => 'fresh',  'title' => '清新绿（默认）', 'desc' => '柔和的青绿色调', 'builtin' => true],
        ['name' => 'forest', 'title' => '深林绿',        'desc' => '浓郁深绿色调',     'builtin' => true],
        ['name' => 'mint',   'title' => '薄荷绿',        'desc' => '清浅薄荷青绿',     'builtin' => true],
    ];
    $dir = RYEBLOG_ROOT . '/usr/theme';
    $custom = [];
    if (is_dir($dir)) {
        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
            $name = basename($d);
            if ($name !== '' && $name[0] === '_') continue; // 共享库（_biz 等）不作为独立主题展示
            $meta = ['name' => $name, 'title' => $name, 'desc' => '', 'builtin' => false];
            if (is_file($d . '/theme.css')) {
                $src = file_get_contents($d . '/theme.css');
                if (preg_match('/@Title\s+(.+)/', $src, $m)) $meta['title'] = trim($m[1]);
                if (preg_match('/@Desc\s+(.+)/',  $src, $m)) $meta['desc']  = trim($m[1]);
            }
            $custom[] = $meta;
        }
    }
    $all = array_merge($builtin, $custom);
    $active = currentTheme();
    foreach ($all as &$t) {
        $t['active'] = ($t['name'] === $active);
    }
    unset($t);
    return $all;
}

/**
 * 返回自定义主题的 CSS URL（若存在）。
 */
function getThemeCssUrl($themeName)
{
    $file = RYEBLOG_ROOT . '/usr/theme/' . $themeName . '/theme.css';
    if (!is_file($file)) return '';
    $v = filemtime($file); // 文件修改时间作版本号：主题更新自动破 CDN/浏览器缓存
    return baseUrl('usr/theme/' . $themeName . '/theme.css?v=' . $v);
}

/**
 * 返回自定义主题的 JS URL（若存在，theme.js 随主题自动加载，defer）。
 */
function getThemeJsUrl($themeName)
{
    $file = RYEBLOG_ROOT . '/usr/theme/' . $themeName . '/theme.js';
    if (!is_file($file)) return '';
    $v = filemtime($file);
    return baseUrl('usr/theme/' . $themeName . '/theme.js?v=' . $v);
}

/**
 * 激活主题：写入 option。
 */
function activateTheme($name)
{
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    if ($name === '') return '无效的主题名。';
    if (in_array($name, ['fresh', 'forest', 'mint'], true)) {
        // 内置配色主题：写 theme_style，并清空自定义主题（否则 currentTheme 优先读 theme 导致切不回去）
        setOption('theme_style', $name);
        setOption('theme', '');
    } else {
        setOption('theme', $name);
    }
    return true;
}

/**
 * 删除自定义主题目录（不能删除内置主题和正在使用的主题）。
 */
function deleteTheme($name)
{
    $name = basename($name);
    if (in_array($name, ['fresh', 'forest', 'mint'], true)) return '内置主题不可删除。';
    if ($name === currentTheme()) return '不能删除正在使用的主题。';
    $path = RYEBLOG_ROOT . '/usr/theme/' . $name;
    if (!is_dir($path)) return '主题目录不存在。';
    return rrmdir($path) ? true : '删除失败，请检查目录权限。';
}

/**
 * 安装 ZIP 主题包：解压到 usr/theme/。
 */
function installThemeZip($tmpFile)
{
    $dest = RYEBLOG_ROOT . '/usr/theme';
    if (!is_dir($dest)) @mkdir($dest, 0755, true);
    $result = extractZip($tmpFile, $dest);
    if (!$result['ok']) return $result;
    $dir = $result['dir'];
    if (!is_file($dest . '/' . $dir . '/theme.css')) {
        rrmdir($dest . '/' . $dir);
        return ['ok' => false, 'msg' => 'ZIP 包中未找到 theme.css，不是有效的 RyeBlog 主题。'];
    }
    return ['ok' => true, 'dir' => $dir, 'msg' => '主题安装成功。'];
}

// ---------- ZIP / 文件操作工具 ----------

/**
 * 递归删除目录。
 */
function rrmdir($dir)
{
    if (!is_dir($dir)) return false;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}

/**
 * 解压 ZIP 文件到目标目录。
 * 优先使用 PHP ZipArchive 扩展，回退到命令行 unzip。
 * 返回 ['ok'=>bool, 'dir'=>string, 'msg'=>string]
 * dir 是解压后的顶层目录名（用于确定插件/主题名）。
 */
function extractZip($tmpFile, $destDir)
{
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) === true) {
            // 读取顶层目录名
            $topDir = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                $parts = explode('/', $entry);
                if ($parts[0] !== '' && $parts[0] !== '.') {
                    $topDir = $parts[0];
                    break;
                }
            }
            $zip->extractTo($destDir);
            $zip->close();

            // 如果 ZIP 内有顶层目录
            if ($topDir && is_dir($destDir . '/' . $topDir)) {
                return ['ok' => true, 'dir' => basename($topDir), 'msg' => '解压成功。'];
            }
            // 如果 ZIP 直接展开了文件（无顶层目录），创建一个随机目录
            return ['ok' => true, 'dir' => basename($destDir), 'msg' => '解压成功（无顶层目录）。'];
        }
    }

    // 回退：命令行 unzip
    $tmpFile = str_replace('\\', '/', $tmpFile);
    $destDir = str_replace('\\', '/', $destDir);
    $cmd = 'unzip -o ' . escapeshellarg($tmpFile) . ' -d ' . escapeshellarg($destDir) . ' 2>&1';
    @exec($cmd, $output, $code);
    if ($code === 0) {
        // 检测解压出的目录
        $items = array_diff(scandir($destDir), ['.', '..']);
        $found = null;
        foreach ($items as $item) {
            if (is_dir($destDir . '/' . $item)) {
                $found = $item;
                break;
            }
        }
        return ['ok' => true, 'dir' => $found ?: '', 'msg' => '解压成功（unzip）。'];
    }

    return ['ok' => false, 'dir' => '', 'msg' => 'ZIP 解压失败：服务器未安装 ZipArchive 扩展或 unzip 命令。'];
}

/**
 * 递归创建目录（兼容 @mkdir 的 umask 问题）。
 */
function mkDirRecursive($path)
{
    if (is_dir($path)) return true;
    @mkdir($path, 0755, true);
    return is_dir($path);
}

/**
 * 下载远程文件到本地，返回 [path, mime] 或 false。
 * 使用 cURL 或 file_get_contents。
 */
function downloadRemoteFile($url, $destDir, $filename = '', $timeout = 30)
{
    $url = trim($url);
    if ($url === '') return false;

    // 确定文件扩展名
    $parsed = parse_url($url);
    $pathPart = $parsed['path'] ?? '';
    $ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION));
    if ($ext === '' || strlen($ext) > 5) $ext = 'jpg';

    if ($filename === '') {
        $filename = date('d_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    }
    $filename = sanitizeFilename($filename);

    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
    $destPath = $destDir . '/' . $filename;

    // 方式1：cURL（模拟浏览器，绕过常见的图片防盗链）
    if (function_exists('curl_init')) {
        $fp = fopen($destPath, 'wb');
        if ($fp) {
            $referer = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_REFERER => $referer,
                CURLOPT_HTTPHEADER => [
                    'Accept: image/avif,image/webp,image/apng,image/png,image/jpeg,image/gif,*/*;q=0.8',
                    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                ],
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            fclose($fp);
            // 跳过错误页（如 404 返回的 HTML 页面），避免把网页当成图片存下来
            if ($httpCode === 200 && filesize($destPath) > 0 && stripos((string)$mime, 'text/html') === false) {
                return ['path' => $destPath, 'filename' => $filename, 'mime' => $mime];
            }
            @unlink($destPath);
        }
    }

    // 方式2：file_get_contents（同样模拟浏览器头，绕过防盗链）
    $referer2 = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/';
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'ignore_errors' => true,
        'header' => [
            'Referer: ' . $referer2,
            'Accept: image/avif,image/webp,image/png,image/jpeg,image/gif,*/*;q=0.8',
        ],
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) > 0) {
        // 粗略判断是否为 HTML 错误页（404/403 等常返回 HTML），是则跳过
        if (!preg_match('/^\s*<!DOCTYPE html|<html[\s>]/i', $data)) {
            file_put_contents($destPath, $data);
            return ['path' => $destPath, 'filename' => $filename, 'mime' => ''];
        }
    }

    return false;
}

/**
 * 扫描 HTML/Markdown 内容中的远程图片 URL（http(s):// 开头的非本站图片）。
 * 返回 URL 数组（去重）。
 */
function scanRemoteImages($content, $format = 'html')
{
    $urls = [];
    if (!is_string($content) || $content === '') return $urls;

    if ($format === 'markdown') {
        // 行内图片 ![alt](url)
        if (preg_match_all('/!\[[^\]]*\]\(([^)\s]+)/i', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
        // 引用式定义 [label]: url 与图片定义（URL 写在定义行；替换后图片即本地化）
        if (preg_match_all('/^[ \t]{0,3}\[[^\]]+\]:[ \t]*(\S+)/m', $content, $m)) {
            foreach ($m[1] as $u) $urls[] = trim($u);
        }
    }

    // 通用 HTML 属性扫描：img src / a href / srcset / data-src / video poster
    if (preg_match_all('/<(?:img|video)[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
        foreach ($m[1] as $u) $urls[] = trim($u);
    }
    if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $content, $m)) {
        foreach ($m[1] as $u) $urls[] = trim($u);
    }
    if (preg_match_all('/<(?:img|source|video)[^>]+srcset=["\']([^"\']+)["\']/i', $content, $m)) {
        foreach ($m[1] as $set) {
            // srcset 格式：url 1x, url2 2x —— 按逗号拆分取 URL
            foreach (explode(',', $set) as $part) {
                $part = trim($part);
                $url = preg_split('/\s+/', $part)[0] ?? '';
                if ($url !== '') $urls[] = $url;
            }
        }
    }
    if (preg_match_all('/<img[^>]+data-src=["\']([^"\']+)["\']/i', $content, $m)) {
        foreach ($m[1] as $u) $urls[] = trim($u);
    }

    // 仅保留 http(s):// 开头的远程 URL（排除本站上传），且仅处理「文件类」URL：
    // 有图片/文档扩展名，或路径位于 wp-content/uploads / usr/uploads 下。
    // 目的：a href 里的站内文章链接（/what-makes-good-tea、/52.html 等）不是文件，
    // 不能被误当附件下载。
    $urls = array_unique(array_filter($urls, function ($u) {
        if (!preg_match('#^https?://#i', $u) || isLocalUploadUrl($u)) return false;
        $path = parse_url($u, PHP_URL_PATH) ?: '';
        if (preg_match('#\.(jpe?g|png|gif|webp|avif|svg|bmp|ico|pdf|zip|rar|7z|docx?|xlsx?|pptx?|mp4|webm|mp3|ogg)$#i', $path)) return true;
        if (preg_match('#/(wp-content/uploads|usr/uploads|wp-content/themes|wp-content/plugins)/#i', $u)) return true;
        return false;
    }));
    return array_values($urls);
}

// 兼容旧常量/会话名（平滑迁移）
if (!defined('VERDA_ROOT')) define('VERDA_ROOT', RYEBLOG_ROOT);
if (!defined('VERDA_FUNCTIONS_LOADED')) define('VERDA_FUNCTIONS_LOADED', true);
