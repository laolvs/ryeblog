<?php
/**
 * RyeBlog 企业站主题 —— 共享库引导（供各行业主题目录 require）
 *
 * 用法（行业主题 home.php/post.php/... 顶部）：
 *   $BIZ = __DIR__ . '/../_biz';           // 或直接 require_once __DIR__.'/../_biz/bootstrap.php'
 *   require_once $BIZ . '/bootstrap.php';
 *
 * 约定：
 *   - 当前激活主题目录名 = $GLOBALS['__biz_theme']（如 corp / tech / food / edu）
 *   - 主题设置读取：bizOpt('key', 默认值) → vd_options 的 biz_<theme>_<key>，后台「企业站主题」配置页写入
 *   - 所有模板自行输出完整 HTML（<html> 头由 biz_head() 生成）
 *   - 前台公共区块（导航/页脚/统计代码/钩子）与 rye 主题同规范
 */
if (!function_exists('bizOpt')) {
    function bizOpt($key, $default = '')
    {
        $theme = $GLOBALS['__biz_theme'] ?? 'corp';
        return getOption("biz_{$theme}_{$key}", $default);
    }
}

/**
 * 输出 <head> 前半段（title/meta/css），模板负责 <body> 后续。
 * @param string $title 页面标题
 * @param string $desc  meta description（可空）
 */
if (!function_exists('biz_head')) {
    function biz_head($title, $desc = '')
    {
        $theme   = $GLOBALS['__biz_theme'] ?? 'corp';
        $site    = siteTitle();
        $css     = baseUrl('usr/theme/' . $theme . '/theme.css?v=' . (@filemtime(dirname(__DIR__) . '/' . $theme . '/theme.css') ?: '1'));
        $favicon = baseUrl('assets/img/logo-512.png');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<title>' . esc($title . ' - ' . $site) . '</title>';
        if ($desc !== '') echo '<meta name="description" content="' . esc($desc) . '">';
        echo '<link rel="icon" href="' . esc($favicon) . '">'
           . '<link rel="stylesheet" href="' . esc($css) . '">'
           . doHook('header') . '</head><body class="theme-' . esc($theme) . '">';
    }
}

/**
 * 顶部导航（企业站横向导航；含导航钩子与页面导航）
 */
if (!function_exists('biz_nav')) {
    function biz_nav($active = '')
    {
        $site    = siteTitle();
        $pages   = getPages();
        // 品牌 logo：后台 biz_<theme>_logo 可配 → 主题自带 assets/logo.svg → 通用 RyeBlog logo
        $logo = bizOpt('logo', '');
        if ($logo === '') {
            $theme = $GLOBALS['__biz_theme'] ?? 'corp';
            $tlogo = RYEBLOG_ROOT . '/usr/theme/' . $theme . '/assets/logo.svg';
            $logo  = is_file($tlogo) ? ('usr/theme/' . $theme . '/assets/logo.svg?v=' . @filemtime($tlogo)) : 'assets/img/logo-512.png';
        }
        echo '<header class="biz-header"><div class="biz-container biz-header-inner">'
           . '<a class="biz-logo" href="' . homeUrl() . '"><img src="' . baseUrl($logo) . '" alt="">'
           . '<span>' . esc($site) . '</span></a>'
           . '<nav class="biz-nav">';
        echo '<a href="' . homeUrl() . '"' . ($active === 'home' ? ' class="on"' : '') . '>首页</a>';
        $cats = getCategories();
        foreach ($cats as $c) {
            if ($c['post_count'] < 1) continue;
            echo '<a href="' . categoryUrl(['slug' => $c['slug']]) . '"' . ($active === 'cat-' . $c['slug'] ? ' class="on"' : '') . '>'
               . esc(L($c, 'name')) . '</a>';
        }
        foreach ($pages as $pg) {
            echo '<a href="' . pageUrl($pg) . '"' . ($active === 'page-' . $pg['slug'] ? ' class="on"' : '') . '>'
               . esc(L($pg, 'title')) . '</a>';
        }
        echo doHook('nav_top');
        $phone = bizOpt('phone', '');
        if ($phone !== '') {
            echo '<a class="biz-nav-phone" href="tel:' . esc(preg_replace('/\s+/', '', $phone)) . '">📞 ' . esc($phone) . '</a>';
        }
        echo '</nav><button class="biz-burger" aria-label="菜单"><span></span><span></span><span></span></button>'
           . '</div></header>';
    }
}

/**
 * 页脚（企业站四栏：关于/产品/服务/联系 + 备案 + 统计代码）
 */
if (!function_exists('biz_footer')) {
    function biz_footer()
    {
        $site  = siteTitle();
        $phone = bizOpt('phone', '');
        $addr  = bizOpt('address', '');
        $email = bizOpt('email', '');
        echo '<footer class="biz-footer"><div class="biz-container biz-footer-grid">'
           . '<div class="biz-footer-col"><h4>' . esc($site) . '</h4>'
           . '<p>' . esc(bizOpt('slogan', '用心服务 · 值得信赖')) . '</p>'
           . '<p class="muted">' . esc(bizOpt('intro', '')) . '</p></div>';
        $cats = getCategories();
        if ($cats) {
            echo '<div class="biz-footer-col"><h4>产品与服务</h4><ul>';
            foreach ($cats as $c) {
                if ($c['post_count'] < 1) continue;
                echo '<li><a href="' . categoryUrl(['slug' => $c['slug']]) . '">' . esc(L($c, 'name')) . '</a></li>';
            }
            echo '</ul></div>';
        }
        $pages = getPages();
        if ($pages) {
            echo '<div class="biz-footer-col"><h4>快速导航</h4><ul>';
            foreach ($pages as $pg) {
                echo '<li><a href="' . pageUrl($pg) . '">' . esc(L($pg, 'title')) . '</a></li>';
            }
            echo '</ul></div>';
        }
        echo '<div class="biz-footer-col"><h4>联系我们</h4><ul>';
        if ($phone !== '') echo '<li>📞 ' . esc($phone) . '</li>';
        if ($email !== '') echo '<li>✉️ ' . esc($email) . '</li>';
        if ($addr  !== '') echo '<li>📍 ' . esc($addr) . '</li>';
        echo '</ul></div></div>'
           . '<div class="biz-container biz-footer-bottom">'
           . '<p>© ' . date('Y') . ' <a href="' . homeUrl() . '">' . esc($site) . '</a> · Powered by RyeBlog</p>'
           . (footerIcp() !== '' ? '<p class="muted">' . esc(footerIcp()) . '</p>' : '')
           . '</div></footer>'
           . '<script>' . biz_mobile_js() . '</script>'
           . footerStats() . doHook('footer') . '</body></html>';
    }
}

/**
 * 移动端汉堡菜单 JS（极简，无依赖）
 */
if (!function_exists('biz_mobile_js')) {
    function biz_mobile_js()
    {
        return "document.addEventListener('click',function(e){var b=e.target.closest('.biz-burger');if(!b)return;document.querySelector('.biz-nav').classList.toggle('open');});";
    }
}

/** 主题当前激活名（由模板设置） */
$GLOBALS['__biz_theme'] = $GLOBALS['__biz_theme'] ?? 'corp';
