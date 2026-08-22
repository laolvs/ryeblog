<?php
/** RyeBlog 官方主题 —— 下载页专属渲染（slug=download 时由 page/post 模板引入） */

    $GLOBALS['__rye_seo'] = ['desc' => '下载 RyeBlog 开源博客系统 v' . RYEBLOG_VERSION . '：零依赖、安全、极速，支持 WordPress/Typecho 数据导入。', 'keywords' => 'RyeBlog,下载,博客系统,开源'];
    $siteTitle = siteTitle();
    $navPages  = getPages();
    $btnDownloadUrl = getOption('rye_download_url', baseUrl('download.html'));
    $btnDocUrl      = getOption('rye_doc_url', baseUrl('category/docs.html'));
    $themeCss  = baseUrl('usr/theme/rye/theme.css?v=' . (@filemtime(__DIR__ . '/theme.css') ?: '1'));
    $brandLogo = baseUrl('assets/img/logo-512.png');
    $ghRepo = 'https://github.com/laolvs/ryeblog';
    $dlZip = $ghRepo . '/releases/download/v' . RYEBLOG_VERSION . '/ryeblog-' . RYEBLOG_VERSION . '.zip';
    $dlSize = 2908297; // 1.4.2 包大小（字节），随版本可更新
    $sizeTxt = $dlSize > 1048576 ? round($dlSize / 1048576, 1) . ' MB' : round($dlSize / 1024) . ' KB';
    $topMenus = array_values(array_filter(getMenus('top'), function ($m) { return trim($m['title']) !== '首页'; }));
    // 版本更新记录：按「版本更新」分类（slug=updates）取最近 5 篇
    $updCat = getCategoryBySlug('updates');
    $updList = $updCat ? (getPosts(['category' => $updCat['id'], 'perPage' => 5])['items'] ?? []) : [];
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>下载 RyeBlog v<?php echo esc(RYEBLOG_VERSION); ?> · <?php echo esc($siteTitle); ?></title>
<meta name="description" content="下载 RyeBlog v<?php echo esc(RYEBLOG_VERSION); ?> 安装包，三步完成安装。">
<link rel="icon" href="<?php echo baseUrl('assets/img/logo-64.png'); ?>">
<link rel="stylesheet" href="<?php echo $themeCss; ?>">
<?php echo doHook('header'); ?>
<style>
/* 下载页专属样式（挂在 body.theme-rye 下，主题内） */
body.theme-rye .dl-page { max-width:960px; margin:0 auto; padding:56px 24px 70px; }
body.theme-rye .dl-head { text-align:center; margin-bottom:40px; }
body.theme-rye .dl-head h1 { font-size:34px; font-weight:800; color:var(--rye-900,#1b5e46); margin:0 0 10px; }
body.theme-rye .dl-head p { color:var(--muted,#7f8c8d); font-size:14px; margin:0; }
body.theme-rye .dl-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:18px; margin-bottom:40px; }
body.theme-rye .dl-card { background:#fff; border:1px solid var(--line,#eaecef); border-radius:16px; padding:26px; box-shadow:var(--shadow); position:relative; overflow:hidden; }
body.theme-rye .dl-card.main { background:linear-gradient(160deg,#0f5132 0%,#1e7a4f 100%); border:none; color:#fff; }
body.theme-rye .dl-card.main::before { content:''; position:absolute; right:-40px; top:-40px; width:160px; height:160px; background:rgba(255,255,255,.08); border-radius:50%; }
body.theme-rye .dl-card .dl-type { font-size:12px; font-weight:700; letter-spacing:.08em; opacity:.75; text-transform:uppercase; }
body.theme-rye .dl-card.main .dl-type { opacity:.8; }
body.theme-rye .dl-card h3 { font-size:20px; font-weight:700; margin:8px 0 4px; }
body.theme-rye .dl-card.main h3 { color:#fff; }
body.theme-rye .dl-card .dl-meta { font-size:12.5px; color:var(--muted,#7f8c8d); margin-bottom:14px; }
body.theme-rye .dl-card.main .dl-meta { color:rgba(255,255,255,.75); }
body.theme-rye .dl-card .dl-desc { font-size:13.5px; line-height:1.7; color:var(--ink,#2c3e50); margin:0 0 16px; min-height:44px; }
body.theme-rye .dl-card.main .dl-desc { color:rgba(255,255,255,.92); }
body.theme-rye .dl-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 24px; border-radius:10px; font-size:14px; font-weight:600; text-decoration:none; transition:transform .15s, box-shadow .15s; }
body.theme-rye .dl-btn:hover { transform:translateY(-2px); }
body.theme-rye .dl-card.main .dl-btn { background:#fff; color:#11603a; box-shadow:0 8px 22px rgba(0,0,0,.18); }
body.theme-rye .dl-card.main .dl-btn:hover { box-shadow:0 12px 30px rgba(0,0,0,.28); }
body.theme-rye .dl-card.main .gh-btn { background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.45); margin-left:10px; }
body.theme-rye .dl-card.main .gh-btn:hover { background:rgba(255,255,255,.24); }
body.theme-rye .dl-card.ghost .dl-btn { background:var(--rye-050,#e8f8f0); color:var(--rye-700,#2d8a5f); }
body.theme-rye .dl-steps { background:#fff; border:1px solid var(--line,#eaecef); border-radius:16px; padding:26px 30px; box-shadow:var(--shadow); margin-bottom:18px; }
body.theme-rye .dl-steps h2 { font-size:18px; font-weight:700; color:var(--rye-900,#1b5e46); margin:0 0 16px; }
body.theme-rye .dl-steps ol { margin:0; padding-left:22px; }
body.theme-rye .dl-steps li { font-size:14px; line-height:1.9; color:var(--ink,#2c3e50); }
body.theme-rye .dl-steps code { background:var(--rye-025,#f4fcf8); color:var(--rye-700,#2d8a5f); padding:2px 7px; border-radius:5px; font-size:12.5px; }
body.theme-rye .dl-req { background:var(--rye-025,#f4fcf8); border:1px dashed var(--rye-200,#7fd8b0); border-radius:14px; padding:20px 24px; font-size:13.5px; color:var(--ink,#2c3e50); }
body.theme-rye .dl-req b { color:var(--rye-700,#2d8a5f); }
body.theme-rye .dl-req ul { margin:8px 0 0; padding-left:18px; }
body.theme-rye .dl-hist { margin-top:14px; font-size:12.5px; color:var(--muted,#7f8c8d); }
body.theme-rye .dl-hist a { color:var(--rye-700,#2d8a5f); }
body.theme-rye .dl-updates { background:#fff; border:1px solid var(--line,#eaecef); border-radius:16px; padding:22px 26px; margin-top:18px; box-shadow:var(--shadow); }
body.theme-rye .dl-updates h2 { font-size:17px; font-weight:700; color:var(--rye-900,#1b5e46); margin:0 0 12px; }
body.theme-rye .dl-updates ul { list-style:none; margin:0; padding:0; }
body.theme-rye .dl-updates li { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:9px 0; border-bottom:1px dashed var(--line,#eaecef); font-size:13.5px; }
body.theme-rye .dl-updates li:last-child { border-bottom:none; }
body.theme-rye .dl-updates li a { color:var(--ink,#2c3e50); text-decoration:none; font-weight:600; }
body.theme-rye .dl-updates li a:hover { color:var(--rye-700,#2d8a5f); }
body.theme-rye .dl-updates-date { font-size:12px; color:var(--muted,#7f8c8d); white-space:nowrap; }
body.theme-rye .dl-updates-more { display:inline-block; margin-top:12px; font-size:13px; font-weight:600; color:var(--rye-700,#2d8a5f); text-decoration:none; }
body.theme-rye .dl-updates-more:hover { text-decoration:underline; }
@media (max-width:640px){ body.theme-rye .dl-page{ padding:32px 16px 50px; } body.theme-rye .dl-head h1{ font-size:26px; } }
</style>
</head>
<body class="theme-rye">
<header class="rye-header">
    <div class="rye-header-inner">
        <a class="rye-brand" href="<?php echo homeUrl(); ?>">
            <img class="rye-brand-logo" src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="RyeBlog">
            <?php echo esc($siteTitle); ?><small>Rye</small>
        </a>
        <nav class="rye-nav">
            <a href="<?php echo homeUrl(); ?>">首页</a>
            <a href="<?php echo esc($btnDownloadUrl); ?>">下载</a>
            <a href="<?php echo esc($btnDocUrl); ?>">文档</a>
            <a href="<?php echo categoryUrl(['slug' => 'knowledge']); ?>">知识库</a>
            <a href="<?php echo categoryUrl(['slug' => 'cases']); ?>">案例展示</a>
            <a href="https://demo.ryeblog.com/" target="_blank" rel="noopener">演示</a>
            <?php echo doHook('nav_top'); ?>
            <?php foreach ($navPages as $pg): ?>
            <?php if ($pg['slug'] === 'download') continue; // 下载入口已在导航与 Hero 提供，避免重复 ?>
            <a href="<?php echo pageUrl($pg); ?>"><?php echo esc(L($pg, 'title')); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="rye-header-cta">
            <a class="rye-btn rye-btn-primary rye-btn-sm" href="<?php echo esc($btnDownloadUrl); ?>">立即下载</a>
        </div>
    </div>
</header>

<div class="dl-page">
    <div class="dl-head">
        <h1>⬇ 下载 RyeBlog</h1>
        <p>免费开源 · 零依赖 · 安全极速 · 支持 WordPress / Typecho 数据导入</p>
    </div>

    <div class="dl-grid">
        <div class="dl-card main">
            <div class="dl-type">RyeBlog 核心</div>
            <h3>v<?php echo esc(RYEBLOG_VERSION); ?></h3>
            <div class="dl-meta">安装包 · <?php echo $sizeTxt; ?> · 更新时间 2026-08-22 · GitHub Releases</div>
            <p class="dl-desc">完整安装包：包含核心程序、官方主题（Rye 官方主题 / Doc 文档主题）、常用插件（防垃圾评论、导航与友情链接、数据导入、英文站）。上传即可安装。</p>
            <a class="dl-btn" href="<?php echo esc($dlZip); ?>">⬇ 下载安装包（<?php echo $sizeTxt; ?>）</a>
            <a class="dl-btn gh-btn" href="<?php echo esc($ghRepo); ?>" target="_blank" rel="noopener">★ GitHub 获取源码</a>
        </div>
        <div class="dl-card ghost">
            <div class="dl-type">云安装</div>
            <h3>后台云端市场</h3>
            <div class="dl-meta">无需手动上传</div>
            <p class="dl-desc">安装后可在后台「插件 / 主题 → 云端」在线安装更多插件与主题，一键更新、自动备份回滚，无需 FTP。</p>
            <a class="dl-btn" href="<?php echo esc(categoryUrl(['slug' => 'docs'])); ?>">📖 安装文档</a>
        </div>
        <div class="dl-card ghost">
            <div class="dl-type">在线体验</div>
            <h3>主题演示站</h3>
            <div class="dl-meta">免安装 · 即点即看</div>
            <p class="dl-desc">演示站已灌入完整数据：企业站（综合制造 / 科技 / 餐饮 / 教育 4 套主题在线切换）+ 博客主题（数十篇博文）。</p>
            <a class="dl-btn" href="https://demo.ryeblog.com/" target="_blank" rel="noopener">🎨 进入演示站 →</a>
        </div>
    </div>

    <div class="dl-steps">
        <h2>📦 三步完成安装</h2>
        <ol>
            <li><b>下载并上传</b>：把 <code>ryeblog-<?php echo esc(RYEBLOG_VERSION); ?>.zip</code> 解压后上传到网站根目录（或直接解压在服务器目录）；</li>
            <li><b>访问安装页</b>：浏览器打开 <code>https://你的域名/install.php</code>，按提示填写数据库信息与管理员账号；</li>
            <li><b>开始使用</b>：安装完成后自动跳转后台，可立即写文章、换主题、装插件；如需把 WordPress / Typecho 的文章导入，后台启用「数据导入」插件即可。</li>
        </ol>
    </div>

    <div class="dl-req">
        <b>系统要求</b>
        <ul>
            <li>PHP <b>8.1+</b>（推荐 8.2 / 8.3）</li>
            <li>MySQL <b>5.7+</b> 或 <b>8.0+</b>（MariaDB 10.3+ 亦可）</li>
            <li>Apache（mod_rewrite）或 Nginx 均可；支持虚拟主机 / VPS / 宝塔面板</li>
            <li>无需 Composer、无任何第三方依赖，解压即用</li>
        </ul>
    </div>

    <p class="dl-hist">历史版本：
        <a href="<?php echo baseUrl('download/ryeblog-1.3.0.zip'); ?>">v1.3.0</a> ·
        <a href="<?php echo baseUrl('download/ryeblog-1.2.0.zip'); ?>">v1.2.0</a> ·
        <a href="<?php echo baseUrl('download/ryeblog-1.1.0.zip'); ?>">v1.1.0</a> ·
        <a href="<?php echo baseUrl('download/ryeblog-1.0.0.zip'); ?>">v1.0.0</a> ·
        <a href="<?php echo esc($ghRepo); ?>/releases" target="_blank" rel="noopener">GitHub Releases →</a>
    </p>

    <?php if (!empty($updList)): ?>
    <div class="dl-updates">
        <h2>📝 版本更新记录</h2>
        <ul>
            <?php foreach ($updList as $up): ?>
            <li>
                <a href="<?php echo postUrl($up); ?>"><?php echo esc(L($up, 'title')); ?></a>
                <span class="dl-updates-date"><?php echo formatDate($up['created_at'], 'Y-m-d'); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <a class="dl-updates-more" href="<?php echo esc(categoryUrl(['slug' => 'updates'])); ?>">查看全部更新记录 →</a>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/inc_footer.php'; ?>
