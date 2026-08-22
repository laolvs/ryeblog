<?php
/**
 * RyeBlog 后台 —— 共用骨架
 */
require_once __DIR__ . '/../inc/functions.php';

if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
requireAdmin();

// 后台语言切换（?set_admin_lang=en|zh）：写 cookie + session，回退当前页
if (isset($_GET['set_admin_lang']) && in_array($_GET['set_admin_lang'], ['en', 'zh'], true)) {
    $v = $_GET['set_admin_lang'];
    setcookie('vd_admin_lang', $v, ['expires' => time() + 86400 * 365, 'path' => '/', 'httponly' => true]);
    $_SESSION['rye_admin_lang'] = $v;
    $here = basename($_SERVER['SCRIPT_NAME']);
    header('Location: ' . baseUrl('admin/' . $here));
    exit;
}
setCurrentLang(adminLang());

function adminHead($title = '', $active = '')
{
    // 5 组顶级导航（按职责聚合，避免平铺堆高）
    $navGroups = [
        ['key' => 'dashboard',   'icon' => '📊', 'label' => __('仪表盘'), 'items' => [
            ['file' => 'index.php', 'label' => __('仪表盘')],
        ]],
        ['key' => 'content',     'icon' => '📝', 'label' => __('内容'),   'items' => [
            ['file' => 'write.php',       'label' => __('写文章')],
            ['file' => 'posts.php',       'label' => __('文章管理')],
            ['file' => 'categories.php',  'label' => __('分类管理')],
            ['file' => 'tags.php',        'label' => __('标签管理')],
            ['file' => 'comments.php',    'label' => __('评论管理')],
            ['file' => 'trash.php',       'label' => __('回收站')],
            ['file' => 'attachments.php', 'label' => __('附件管理')],
        ]],
        ['key' => 'appearance',  'icon' => '🎨', 'label' => __('外观'),   'items' => [
            ['file' => 'themes.php',       'label' => __('主题管理')],
            ['file' => 'menus.php',        'label' => __('菜单管理')],
            ['file' => 'sidebar.php',      'label' => __('侧边栏管理')],
        ]],
        ['key' => 'plugins',     'icon' => '🧩', 'label' => __('插件'),   'items' => [
            ['file' => 'plugins.php',        'label' => __('插件管理')],
            ['file' => 'plugin-content.php', 'label' => __('插件内容')],
        ]],
        ['key' => 'settings',    'icon' => '⚙️', 'label' => __('设置'),   'items' => [
            ['file' => 'users.php',            'label' => __('用户管理')],
            ['file' => 'backups.php',          'label' => __('备份管理')],
            ['file' => 'update.php',           'label' => __('自动更新')],
            ['file' => 'settings-brand.php',   'label' => __('品牌与主页')],
            ['file' => 'settings-seo.php',     'label' => __('SEO')],
            ['file' => 'settings-reading.php', 'label' => __('阅读与评论')],
            ['file' => 'settings-footer.php',  'label' => __('Footer 与备案')],
            ['file' => 'settings-advanced.php','label' => __('高级设置')],
            ['file' => 'settings-license.php', 'label' => __('开源与协议')],
        ]],
    ];
    ?>
<!DOCTYPE html>
<html lang="<?php echo adminLang() === 'en' ? 'en' : 'zh-CN'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc($title); ?> · RyeBlog <?php echo __('后台'); ?></title>
    <link rel="icon" href="<?php echo baseUrl('assets/img/logo-64.png'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/themes.css'); ?>">
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/admin.css'); ?>">
</head>
<body class="theme-<?php echo esc(currentTheme()); ?>">
<div class="admin-shell">
    <aside class="admin-side">
        <div class="logo">
            <img src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="RyeBlog" style="width:28px;height:28px;vertical-align:middle;margin-right:6px">
            RyeBlog<small><?php echo __('博客管理系统'); ?></small>
        </div>
        <nav class="admin-nav">
            <?php foreach ($navGroups as $g):
                    // 当前页在该组则默认展开该组
                    $hasActive = false;
                    foreach ($g['items'] as $it) {
                        if ($active === $it['file']) { $hasActive = true; break; }
                    }
                    // 单元素组（仅仪表盘）直接展示，不折叠
                    $isSingle = count($g['items']) === 1;
                ?>
                <?php if ($isSingle):
                        $it = $g['items'][0]; ?>
                    <a href="<?php echo baseUrl('admin/' . $it['file']); ?>"
                       class="admin-nav-link <?php echo $active === $it['file'] ? 'active' : ''; ?>">
                        <?php echo $g['icon'] . ' ' . $g['label']; ?>
                    </a>
                <?php else: ?>
                    <details class="admin-nav-group" <?php echo $hasActive ? 'open' : ''; ?>>
                        <summary class="admin-nav-summary">
                            <?php echo $g['icon'] . ' ' . $g['label']; ?>
                        </summary>
                        <ul class="admin-nav-sub">
                            <?php foreach ($g['items'] as $it): ?>
                                <li>
                                    <a href="<?php echo baseUrl('admin/' . $it['file']); ?>"
                                       class="admin-nav-sub-link <?php echo $active === $it['file'] ? 'active' : ''; ?>">
                                        <?php echo $it['label']; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <?php
                            // 插件菜单注入本组（插件实现 admin_menu_<组key>() 静态方法返回 <li>…</li> 片段）
                            $gHook = doHook('admin_menu_' . $g['key']);
                            if ($gHook !== '') echo $gHook;
                            ?>
                        </ul>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php echo doHook('admin_menu'); ?>
            <a href="<?php echo baseUrl(); ?>" target="_blank" rel="noopener" class="admin-nav-link"><?php echo __('查看站点'); ?> ↗</a>
            <a href="<?php echo baseUrl('admin/logout.php'); ?>" class="admin-nav-link"><?php echo __('退出登录'); ?></a>
        </nav>
        <?php if (bilingualEnabled()): ?>
        <div class="admin-lang">
            <?php
            $al = adminLang();
            $here = basename($_SERVER['SCRIPT_NAME']);
            ?>
            <span class="muted" style="font-size:12px"><?php echo __('界面语言'); ?></span>
            <a href="<?php echo baseUrl('admin/' . $here); ?>?set_admin_lang=zh" class="<?php echo $al === 'zh' ? 'active' : ''; ?>">中</a>
            <a href="<?php echo baseUrl('admin/' . $here); ?>?set_admin_lang=en" class="<?php echo $al === 'en' ? 'active' : ''; ?>">EN</a>
        </div>
        <?php endif; ?>
    </aside>
    <main class="admin-main">
<?php
}

function adminDocBar()
{
    ?>
        <footer class="admin-foot">
            <span class="admin-foot-brand">RyeBlog</span>
            <a href="<?php echo baseUrl('docs.php?doc=HELP'); ?>" target="_blank">📖 <?php echo __('帮助文档'); ?></a>
            <a href="<?php echo baseUrl('docs.php?doc=LICENSE'); ?>" target="_blank">⚖️ <?php echo __('授权协议'); ?></a>
            <a href="<?php echo baseUrl('docs.php?doc=PLUGIN_DEV'); ?>" target="_blank"><?php echo __('插件开发'); ?></a>
        </footer>
<?php
}

function adminFoot()
{
    ?>
    </main>
    <?php adminDocBar(); ?>
</div>
</body>
</html>
<?php
}
