<?php
/**
 * Plugin Name: Demo 主题切换器
 * Description: 演示站专属 —— 顶部浮条（企业/博客两个分类下拉菜单，每主题带简述）一打开就显示；自动同步云端市场新主题（6 小时一次）。
 * Version: 1.2.0
 * Author: RyeBlog
 * 钩子：header（顶部浮条 + 云端主题自动同步）
 * 说明：仅演示站启用；核心的 previewThemeOverride 按目录存在性校验，普通站点不受影响。
 */
class Plugin_demo_switch
{
    /** 云端主题仓库 manifest 地址 */
    const REPO_URL = 'https://ryeblog.com/cloud/repo.json';
    /** 云端主题同步间隔（秒）：6 小时 */
    const SYNC_TTL = 21600;

    /** 主题分类分组：企业 / 博客（顺序即显示顺序） */
    private static function groups()
    {
        return [
            '企业' => ['corp', 'tech', 'food', 'edu', 'estate', 'med', 'travel', 'law', 'example'],
            '博客' => ['fresh', 'forest', 'mint', 'rye', 'vuecho'],
        ];
    }

    /** 主题元信息：[emoji, 名称, 简述]；未知主题读 theme.css @Title/@Desc */
    private static function themeMeta($name)
    {
        static $map = [
            'corp'    => ['🏭', '企业站 · 综合制造', '蓝色综合企业 · 制造/贸易/咨询'],
            'tech'    => ['🚀', '企业站 · 科技 SaaS', '深色科技蓝 · 软件/AI/硬件'],
            'food'    => ['🍜', '企业站 · 餐饮服务', '暖色餐饮 · 门店/预约'],
            'edu'     => ['🎓', '企业站 · 教育培训', '蓝+暖黄 · 课程/师资'],
            'estate'  => ['🏗', '企业站 · 建筑地产', '深灰蓝 · 房产/建筑/楼盘'],
            'med'     => ['🏥', '企业站 · 医疗健康', '浅蓝医疗 · 医院/诊所/体检'],
            'travel'  => ['🏞', '企业站 · 文旅酒店', '青色文旅 · 旅游/酒店/线路'],
            'law'     => ['⚖️', '企业站 · 法律咨询', '深蓝法律 · 律所/企业法务'],
            'example' => ['🧡', '通用 · 暖橙主题', '暖橙通用 · 企业展示'],
            'fresh'   => ['🌿', '博客 · 清新绿',     '柔和青绿 · 默认博客配色'],
            'forest'  => ['🌲', '博客 · 深林绿',     '浓郁深绿 · 沉稳博客'],
            'mint'    => ['🍃', '博客 · 薄荷绿',     '清浅青绿 · 轻快博客'],
            'rye'     => ['🌱', '博客 · Rye 官方', '绿色博客 · 官方主题'],
            'vuecho'  => ['📖', '文档 · Doc 文档', '三栏文档站 · 知识库/手册'],
        ];
        if (isset($map[$name])) return $map[$name];
        $f = RYEBLOG_ROOT . '/usr/theme/' . $name . '/theme.css';
        $title = $name; $desc = '';
        if (is_file($f)) {
            $c = @file_get_contents($f);
            if (preg_match('/@Title\s+(.+)/', $c, $m)) $title = trim($m[1]);
            if (preg_match('/@Desc\s+(.+)/', $c, $m)) $desc = mb_strimwidth(trim($m[1]), 0, 34, '…', 'UTF-8');
        }
        return ['', $title, $desc];
    }

    /** 已安装主题按分组归类（动态扫描 usr/theme/*，云端新装主题自动出现） */
    private static function installedGroups()
    {
        $installed = ['fresh' => true, 'forest' => true, 'mint' => true]; // 内置配色永远在（核心默认主题）
        $dir = RYEBLOG_ROOT . '/usr/theme';
        if (is_dir($dir)) {
            foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
                $n = basename($d);
                if ($n === '' || $n[0] === '_' || !is_file($d . '/theme.css')) continue;
                $installed[$n] = true;
            }
        }
        $out = [];
        foreach (self::groups() as $g => $names) {
            $out[$g] = [];
            foreach ($names as $n) {
                if (isset($installed[$n])) $out[$g][$n] = self::themeMeta($n);
            }
        }
        // 未在分组里的已装主题 → 归入「企业」
        foreach ($installed as $n => $_) {
            $found = false;
            foreach (self::groups() as $names) {
                if (in_array($n, $names, true)) { $found = true; break; }
            }
            if (!$found) $out['企业'][$n] = self::themeMeta($n);
        }
        return $out;
    }

    /** 浮条 HTML：两个分类下拉（<details> 原生展开，零 JS），每主题带简述 */
    private static function barHtml()
    {
        $groups  = self::installedGroups();
        $current = currentTheme();
        // 主题链接：保留当前页面路径与其余参数（避免 ?theme= 相对链接覆盖整段 query 导致 404）
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $qs   = parse_url($uri, PHP_URL_QUERY) ?? '';
        parse_str($qs, $q);
        unset($q['theme']);
        // /blog 博客入口：点任意主题跳回首页（/blog 的 rewrite 会替换 query 丢 theme，且 demo_blog 强制 rye）
        if ($path === '/blog' || isset($q['demo_blog'])) { $path = '/'; unset($q['demo_blog']); }
        $prefix = $path . ($q ? '?' . http_build_query($q) : '');
        $link = function ($name) use ($prefix) {
            $sep = (strpos($prefix, '?') !== false) ? '&' : '?';
            return $prefix . $sep . 'theme=' . rawurlencode($name);
        };
        $html = '<style>'
              . '.demo-switch{position:sticky;top:0;left:0;right:0;z-index:99999;background:#16202b;color:#fff;padding:8px 14px;'
              . 'display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;font-size:13px;box-shadow:0 2px 10px rgba(0,0,0,.25);'
              . 'font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif}'
              . '.demo-switch details{position:relative}'
              . '.demo-switch summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:5px;padding:5px 14px;'
              . 'border-radius:16px;border:1px solid rgba(255,255,255,.35);color:#fff;user-select:none;font-weight:600}'
              . '.demo-switch summary::-webkit-details-marker{display:none}'
              . '.demo-switch details[open] summary{background:rgba(255,255,255,.16)}'
              . '.demo-switch .ds-menu{position:absolute;top:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1d2733;'
              . 'border:1px solid rgba(255,255,255,.14);border-radius:10px;min-width:250px;padding:6px;box-shadow:0 12px 30px rgba(0,0,0,.45);'
              . 'max-height:66vh;overflow:auto;z-index:99999}'
              . '.demo-switch .ds-item{display:flex;flex-direction:column;gap:2px;color:#fff;text-decoration:none;padding:9px 12px;border-radius:7px}'
              . '.demo-switch .ds-item:hover{background:rgba(255,255,255,.08)}'
              . '.demo-switch .ds-item.on{background:#2c7d3f}'
              . '.demo-switch .ds-item b{font-size:13.5px;font-weight:600}'
              . '.demo-switch .ds-item small{font-size:11.5px;opacity:.72}'
              . '@media(max-width:700px){.demo-switch{justify-content:flex-start;overflow-x:auto;flex-wrap:nowrap}}'
              . '</style>';
        $html .= '<div class="demo-switch"><span style="opacity:.85;white-space:nowrap">🎨 演示主题：</span>';
        foreach ($groups as $g => $items) {
            if (empty($items)) continue;
            $html .= '<details><summary>' . esc($g) . ' ▾</summary><div class="ds-menu">';
            foreach ($items as $name => $meta) {
                list($icon, $title, $desc) = $meta;
                $on = ($name === $current) ? ' on' : '';
                $html .= '<a class="ds-item' . $on . '" href="' . esc($link($name)) . '">'
                       . '<b>' . ($icon !== '' ? $icon . ' ' : '') . esc($title) . '</b>'
                       . ($desc !== '' ? '<small>' . esc($desc) . '</small>' : '')
                       . '</a>';
            }
            $html .= '</div></details>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * 云端主题自动同步：拉 repo.json，未装主题自动安装、有新版自动升级（SHA-256 强校验 + 失败回滚）。
     * 带 6 小时缓存（vd_options.demo_theme_sync）；?sync_themes=1 强制。任何失败静默。
     */
    private static function syncCloudThemes($force = false)
    {
        if ($force || !self::syncedRecently()) {
            $result = self::doSync();
            setOption('demo_theme_sync', json_encode(['ts' => time(), 'result' => $result], JSON_UNESCAPED_UNICODE));
        }
    }

    private static function syncedRecently()
    {
        $c = getOption('demo_theme_sync', '');
        if ($c === '' || $c[0] !== '{') return false;
        $d = json_decode($c, true);
        return is_array($d) && isset($d['ts']) && (time() - (int) $d['ts']) < self::SYNC_TTL;
    }

    private static function doSync()
    {
        require_once RYEBLOG_ROOT . '/inc/cloud.php';
        $out = [];
        $body = @file_get_contents(self::REPO_URL, false, stream_context_create([
            'http' => ['timeout' => 25, 'user_agent' => 'RyeBlog-Demo/1.0'],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]));
        if ($body === false || $body === '') return ['error' => '无法连接云端仓库'];
        $d = json_decode($body, true);
        if (!is_array($d) || empty($d['themes']) || !is_array($d['themes'])) return ['error' => '仓库数据异常'];

        foreach ($d['themes'] as $pkg) {
            $name = $pkg['name'] ?? '';
            if ($name === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $name)) continue;
            try {
                $st = cloudStatus('theme', $pkg);
                if ($st === 'not-installed') {
                    $r = cloudInstall('theme', $pkg);
                    $out[] = $name . '：新装' . (($r['ok'] ?? false) ? '成功' : '失败 ' . ($r['msg'] ?? ''));
                } elseif ($st === 'update-available') {
                    $r = cloudUpdate('theme', $pkg);
                    $out[] = $name . '：升级' . (($r['ok'] ?? false) ? '成功' : '失败 ' . ($r['msg'] ?? ''));
                }
            } catch (Throwable $e) {
                $out[] = $name . '：异常 ' . mb_substr($e->getMessage(), 0, 60);
            }
        }
        return $out === [] ? ['up-to-date' => true] : $out;
    }

    /** header 钩子：云端主题同步 + 顶部浮条（一打开就显示，仅前台） */
    public static function header()
    {
        // 后台请求不显示
        if (defined('IS_ADMIN') && IS_ADMIN) return '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/admin') !== false || strpos($uri, '/bbs/') !== false) return '';

        // 云端主题自动同步（渲染浮条前执行，新主题立即出现在浮条）
        self::syncCloudThemes(isset($_GET['sync_themes']));

        // /blog 目录入口：强制博客主题（rye），并种 cookie 保持
        if (isset($_GET['demo_blog'])) {
            if (is_dir(RYEBLOG_ROOT . '/usr/theme/rye')) {
                setcookie('rye_theme', 'rye', time() + 86400 * 30, '/', '', false, false);
                $GLOBALS['__rye_body_class'] = ($GLOBALS['__rye_body_class'] ?? '') . ' demo-blog';
            }
        }

        // ?theme= 切换：写 cookie + 302 到「新主题首页」（?theme=xxx&_t=1，_t 标记防循环）
        // 渲染按 GET theme 生效（previewThemeOverride 优先 GET），从任何页面切换都进入新风格首页
        // 内置配色（fresh/forest/mint）也支持在线预览（previewThemeOverride 同步放行）
        if (isset($_GET['theme']) && !isset($_GET['_t'])) {
            $t = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['theme']);
            $builtin = in_array($t, ['fresh', 'forest', 'mint'], true);
            if ($t !== '' && ($builtin || is_dir(RYEBLOG_ROOT . '/usr/theme/' . $t))) {
                setcookie('rye_theme', $t, time() + 86400 * 30, '/', '', false, false);
                header('Location: /?theme=' . rawurlencode($t) . '&_t=1', true, 302);
                exit;
            }
        }

        return self::barHtml();
    }
}
