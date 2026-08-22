<?php
/** RyeBlog 后台 —— 顶部/底部菜单管理 */
require_once __DIR__ . '/admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { die(__('CSRF 校验失败')); }
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $loc = ($_POST['location'] ?? 'top') === 'footer' ? 'footer' : 'top';
        $title = trim($_POST['title'] ?? '');
        $titleEn = trim($_POST['title_en'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $target = ($_POST['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
        if ($title !== '' && $url !== '') {
            dbQuery('INSERT INTO vd_menus (location,title,title_en,url,target,sort_order,status) VALUES (?,?,?,?,?,?,1)',
                [$loc, $title, $titleEn, $url, $target, (int)($_POST['sort_order'] ?? 0)]);
        }
    } elseif ($action === 'delete') {
        dbQuery('DELETE FROM vd_menus WHERE id=?', [(int)$_POST['id']]);
    } elseif ($action === 'toggle') {
        dbQuery('UPDATE vd_menus SET status=1-status WHERE id=?', [(int)$_POST['id']]);
    }
    header('Location: ' . baseUrl('admin/menus.php'));
    exit;
}
$topMenus = dbAll("SELECT * FROM vd_menus WHERE location='top' ORDER BY sort_order,id");
$footMenus = dbAll("SELECT * FROM vd_menus WHERE location='footer' ORDER BY sort_order,id");
adminHead(__('菜单管理'), 'menus.php');
?>
<h1><?php echo __('菜单管理'); ?></h1>
<p class="muted"><?php echo __('URL 支持'); ?> <code>{{home}}</code> <?php echo __('占位（自动替换为站点首页地址），例如'); ?> <code>{{home}}page/about</code>。</p>

<form class="panel" method="post" style="margin:14px 0">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="action" value="add">
    <div class="row">
        <div><label><?php echo __('位置'); ?></label><select name="location"><option value="top"><?php echo __('顶部菜单'); ?></option><option value="footer"><?php echo __('底部菜单'); ?></option></select></div>
        <div style="flex:1"><label><?php echo __('标题'); ?></label><input type="text" name="title" required></div>
        <div style="flex:1"><label><?php echo __('英文标题（可选）'); ?></label><input type="text" name="title_en" placeholder="English title"></div>
        <div style="flex:2"><label>URL</label><input type="text" name="url" placeholder="{{home}} <?php echo __('或 https://…'); ?>" required></div>
        <div><label><?php echo __('新窗口'); ?></label><select name="target"><option value="_self"><?php echo __('当前页'); ?></option><option value="_blank"><?php echo __('新窗口'); ?></option></select></div>
        <div><label><?php echo __('排序'); ?></label><input type="text" name="sort_order" value="0" style="width:60px"></div>
    </div>
    <p style="margin-top:10px"><button class="btn" type="submit"><?php echo __('添加菜单'); ?></button></p>
</form>

<h3><?php echo __('顶部菜单'); ?></h3>
<table class="data-table">
    <tr><th><?php echo __('标题'); ?></th><th><?php echo __('英文'); ?></th><th>URL</th><th><?php echo __('排序'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
    <?php foreach ($topMenus as $m): ?>
    <tr>
        <td><?php echo esc($m['title']); ?></td>
        <td><?php echo esc(($m['title_en'] ?? '') ?: '—'); ?></td>
        <td><code><?php echo esc($m['url']); ?></code></td>
        <td><?php echo (int)$m['sort_order']; ?></td>
        <td><?php echo $m['status'] ? __('显示') : __('隐藏'); ?></td>
        <td>
            <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $m['id']; ?>"><button class="btn btn-ghost" type="submit"><?php echo __('切换'); ?></button></form>
            <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $m['id']; ?>"><button class="btn btn-ghost" type="submit" onclick="return confirm('<?php echo __('删除?'); ?>')"><?php echo __('删除'); ?></button></form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$topMenus): ?><tr><td colspan="6" class="muted"><?php echo __('暂无'); ?></td></tr><?php endif; ?>
</table>

<h3><?php echo __('底部菜单'); ?></h3>
<table class="data-table">
    <tr><th><?php echo __('标题'); ?></th><th><?php echo __('英文'); ?></th><th>URL</th><th><?php echo __('排序'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
    <?php foreach ($footMenus as $m): ?>
    <tr>
        <td><?php echo esc($m['title']); ?></td>
        <td><?php echo esc(($m['title_en'] ?? '') ?: '—'); ?></td>
        <td><code><?php echo esc($m['url']); ?></code></td>
        <td><?php echo (int)$m['sort_order']; ?></td>
        <td><?php echo $m['status'] ? __('显示') : __('隐藏'); ?></td>
        <td>
            <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $m['id']; ?>"><button class="btn btn-ghost" type="submit"><?php echo __('切换'); ?></button></form>
            <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $m['id']; ?>"><button class="btn btn-ghost" type="submit" onclick="return confirm('<?php echo __('删除?'); ?>')"><?php echo __('删除'); ?></button></form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$footMenus): ?><tr><td colspan="6" class="muted"><?php echo __('暂无'); ?></td></tr><?php endif; ?>
</table>
<?php adminFoot();
