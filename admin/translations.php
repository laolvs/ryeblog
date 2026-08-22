<?php
/**
 * RyeBlog 后台 —— 翻译管理（英文专门后台）
 * ------------------------------------------------------------
 * 集中管理所有内容类型的英文版：文章/页面/分类/标签/菜单/站点信息。
 * 英文内容编辑采用 ?lang=en 模式（write.php?lang=en&id=X 等），
 * 未填写英文的内容在前台 /en 下自动回退显示中文（Drupal 式）。
 */
require_once __DIR__ . '/admin.php';

// 翻译管理仅在双语模式（英文站插件启用）下可用；纯中文库无 *_en 列
if (!bilingualEnabled()) {
    adminHead(__('翻译管理'), 'translations.php');
    echo '<h1>' . __('翻译管理') . '</h1>';
    echo '<div class="notice notice-err">' . __('请先在「插件管理」启用英文站插件（english-admin），安装英文库后再使用翻译管理。') . '</div>';
    adminFoot();
    exit;
}

$tab = $_GET['tab'] ?? 'posts';
$tabs = [
    'posts'      => __('文章'),
    'pages'      => __('页面'),
    'categories' => __('分类'),
    'tags'       => __('标签'),
    'menus'      => __('菜单'),
    'site'       => __('站点信息'),
];
if (!isset($tabs[$tab])) $tab = 'posts';

/** 已译判定：该行任一 *_en 字段非空即视为已译 */
function trStatus($row, $fields)
{
    foreach ($fields as $f) {
        if (!empty($row[$f])) return true;
    }
    return false;
}

/* ---------- 各类型数据与统计 ---------- */
$rows   = [];
$stats  = [];
$data   = dbAll("SELECT id, title, title_en, content_en FROM vd_posts WHERE type='post' ORDER BY id DESC");
$stats['posts'] = [count(array_filter($data, fn($r) => trStatus($r, ['title_en', 'content_en']))), count($data)];
$rows['posts'] = $data;

$data   = dbAll("SELECT id, title, title_en, content_en FROM vd_posts WHERE type='page' ORDER BY id DESC");
$stats['pages'] = [count(array_filter($data, fn($r) => trStatus($r, ['title_en', 'content_en']))), count($data)];
$rows['pages'] = $data;

$data   = dbAll("SELECT id, name, name_en, desc_en FROM vd_categories ORDER BY id");
$stats['categories'] = [count(array_filter($data, fn($r) => trStatus($r, ['name_en']))), count($data)];
$rows['categories'] = $data;

$data   = dbAll("SELECT id, name, name_en, `count` FROM vd_tags ORDER BY `count` DESC, id");
$stats['tags'] = [count(array_filter($data, fn($r) => trStatus($r, ['name_en']))), count($data)];
$rows['tags'] = $data;

$data   = dbAll("SELECT id, title, title_en, location FROM vd_menus ORDER BY location, sort_order, id");
$stats['menus'] = [count(array_filter($data, fn($r) => trStatus($r, ['title_en']))), count($data)];
$rows['menus'] = $data;

$site = [
    ['key' => 'site_title',  'name' => __('站点名称'), 'zh' => getOption('site_title'),  'en' => getOption('site_title_en')],
    ['key' => 'site_slogan', 'name' => __('站点标语'), 'zh' => getOption('site_slogan'), 'en' => getOption('site_slogan_en')],
];
$stats['site'] = [count(array_filter($site, fn($r) => $r['en'] !== '')), count($site)];

adminHead(__('翻译管理'), 'translations.php');
?>
<h1><?php echo __('翻译管理'); ?></h1>
<p class="muted" style="margin-bottom:16px;font-size:.9rem">
    💡 <?php echo __('英文内容集中管理：各类型「编辑英文」进入英文专用编辑模式；未填英文的内容在前台 /en 下自动回退显示中文。'); ?>
</p>

<div class="sb-tabs" style="margin-bottom:18px">
    <?php foreach ($tabs as $t => $label): ?>
        <button type="button" class="sb-tab <?php echo $t === $tab ? 'sb-tab-active' : ''; ?>" onclick="location.href='<?php echo baseUrl('admin/translations.php?tab=' . $t); ?>'">
            <?php echo esc($label); ?> <?php echo $stats[$t][1] ? ('(' . $stats[$t][0] . '/' . $stats[$t][1] . ')') : ''; ?>
        </button>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'site'): ?>
    <div class="panel">
        <table class="data">
            <tr><th><?php echo __('项目'); ?></th><th><?php echo __('中文'); ?></th><th><?php echo __('英文'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
            <?php foreach ($site as $s): ?>
            <tr>
                <td><?php echo esc($s['name']); ?></td>
                <td><?php echo esc($s['zh'] ?: '—'); ?></td>
                <td><?php echo esc($s['en'] ?: '—'); ?></td>
                <td><?php echo $s['en'] !== '' ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>'; ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/settings.php'); ?>"><?php echo __('编辑'); ?></a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php elseif ($tab === 'menus'): ?>
    <div class="panel">
        <table class="data">
            <tr><th><?php echo __('标题'); ?></th><th><?php echo __('位置'); ?></th><th><?php echo __('英文'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
            <?php foreach ($rows['menus'] as $m): ?>
            <tr>
                <td><?php echo esc($m['title']); ?></td>
                <td><?php echo $m['location'] === 'top' ? __('顶部') : __('底部'); ?></td>
                <td><?php echo esc(($m['title_en'] ?? '') ?: '—'); ?></td>
                <td><?php echo trStatus($m, ['title_en']) ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>'; ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/menus.php'); ?>"><?php echo __('编辑'); ?></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows['menus'])): ?><tr><td colspan="5" class="muted"><?php echo __('暂无'); ?></td></tr><?php endif; ?>
        </table>
    </div>
<?php elseif ($tab === 'tags'): ?>
    <div class="panel">
        <table class="data">
            <tr><th><?php echo __('名称'); ?></th><th><?php echo __('英文'); ?></th><th><?php echo __('文章数'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
            <?php foreach ($rows['tags'] as $t): ?>
            <tr>
                <td><a href="<?php echo baseUrl('admin/tags.php?edit=' . (int)$t['id']); ?>"><?php echo esc($t['name']); ?></a></td>
                <td><?php echo esc(($t['name_en'] ?? '') ?: '—'); ?></td>
                <td><?php echo (int)$t['count']; ?></td>
                <td><?php echo trStatus($t, ['name_en']) ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>'; ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/tags.php?edit=' . (int)$t['id'] . '&lang=en'); ?>"><?php echo __('编辑英文'); ?> ↗</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows['tags'])): ?><tr><td colspan="5" class="muted"><?php echo __('暂无'); ?></td></tr><?php endif; ?>
        </table>
    </div>
<?php elseif ($tab === 'categories'): ?>
    <div class="panel">
        <table class="data">
            <tr><th><?php echo __('名称'); ?></th><th><?php echo __('英文名称'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
            <?php foreach ($rows['categories'] as $c): ?>
            <tr>
                <td><a href="<?php echo baseUrl('admin/categories.php?edit=' . (int)$c['id']); ?>"><?php echo esc($c['name']); ?></a></td>
                <td><?php echo esc(($c['name_en'] ?? '') ?: '—'); ?></td>
                <td><?php echo trStatus($c, ['name_en']) ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>'; ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/categories.php?edit=' . (int)$c['id'] . '&lang=en'); ?>"><?php echo __('编辑英文'); ?> ↗</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows['categories'])): ?><tr><td colspan="4" class="muted"><?php echo __('暂无'); ?></td></tr><?php endif; ?>
        </table>
    </div>
<?php else: ?>
    <?php $list = $rows[$tab]; $isPosts = ($tab === 'posts' || $tab === 'pages'); ?>
    <div class="panel">
        <table class="data">
            <tr><th><?php echo __('标题'); ?></th><th><?php echo __('英文标题'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
            <?php foreach ($list as $it): ?>
            <tr>
                <td><a href="<?php echo baseUrl('admin/write.php?id=' . (int)$it['id']); ?>"><?php echo esc($it['title']); ?></a></td>
                <td><?php echo esc(($it['title_en'] ?? '') ?: '—'); ?></td>
                <td><?php echo trStatus($it, ['title_en', 'content_en']) ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>'; ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/write.php?id=' . (int)$it['id'] . '&lang=en'); ?>"><?php echo __('编辑英文'); ?> ↗</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($list)): ?><tr><td colspan="4" class="muted"><?php echo __('暂无内容。'); ?></td></tr><?php endif; ?>
        </table>
    </div>
<?php endif; ?>
<?php adminFoot();
