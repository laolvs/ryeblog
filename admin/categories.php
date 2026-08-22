<?php
/**
 * RyeBlog 后台 —— 分类管理
 */
require_once __DIR__ . '/admin.php';

$ok = $err = '';
$editLang = bilingualEnabled() && ($_GET['lang'] ?? 'zh') === 'en' ? 'en' : 'zh';

// 新增 / 更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } elseif ($editLang === 'en') {
        // ---- 英文模式：只更新 *_en 字段 ----
        $editId = (int)($_POST['id'] ?? 0);
        if ($editId <= 0) {
            $err = __('请先在中文模式下创建分类，再到翻译管理补英文。');
        } else {
            dbQuery('UPDATE vd_categories SET name_en=?, desc_en=? WHERE id=?',
                [trim($_POST['name_en'] ?? ''), trim($_POST['desc_en'] ?? ''), $editId]);
            header('Location: ' . baseUrl('admin/categories.php?edit=' . $editId . '&lang=en&saved=1'));
            exit;
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
        $desc = trim($_POST['description'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $editId = (int)($_POST['id'] ?? 0);
        if ($name === '') {
            $err = __('分类名称不能为空。');
        } elseif ($editId && $parentId === $editId) {
            $err = __('父分类不能选择自己。');
        } elseif ($editId && self_isDescendant($parentId, $editId)) {
            $err = __('父分类不能选择自己的子分类（避免形成循环层级）。');
        } else {
            if ($editId) {
                // 中文模式不更新 *_en 列：保留已有英文
                dbQuery('UPDATE vd_categories SET name=?, slug=?, description=?, parent_id=? WHERE id=?', [$name, $slug, $desc, $parentId, $editId]);
            } else {
                dbInsert('INSERT INTO vd_categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)', [$name, $slug, $desc, $parentId]);
            }
            bumpContentRev(); // 分类计数/侧栏缓存实时失效
            header('Location: ' . baseUrl('admin/categories.php'));
            exit;
        }
    }
}

/** 判断 $candidate 是否为 $target 的（间接）子分类——用于防循环 */
function self_isDescendant($candidate, $target, &$seen = [])
{
    if ($candidate <= 0 || $target <= 0) return false;
    if (isset($seen[$candidate])) return false;
    $seen[$candidate] = true;
    $row = dbOne('SELECT parent_id FROM vd_categories WHERE id=?', [$candidate]);
    if (!$row) return false;
    $pid = (int)$row['parent_id'];
    if ($pid === $target) return true;
    return self_isDescendant($pid, $target, $seen);
}

// 删除
if (isset($_GET['del']) && isset($_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    dbQuery('UPDATE vd_posts SET category_id = NULL WHERE category_id = ?', [$_GET['del']]);
    dbQuery('UPDATE vd_categories SET parent_id = 0 WHERE parent_id = ?', [$_GET['del']]); // 子分类提升为顶级
    dbQuery('DELETE FROM vd_categories WHERE id = ?', [$_GET['del']]);
    bumpContentRev();
    header('Location: ' . baseUrl('admin/categories.php'));
    exit;
}

$edit = isset($_GET['edit']) ? dbOne('SELECT * FROM vd_categories WHERE id = ?', [$_GET['edit']]) : null;
$cats = getCategories();

// 父分类下拉选项（排除自身与自己的后代）
$catOptions = [];
function buildCatOptionsList($cats, $excludeId, $parent, $depth, &$out)
{
    foreach ($cats as $c) {
        if ((int)$c['parent_id'] !== (int)$parent) continue;
        if ((int)$c['id'] === (int)$excludeId) continue;
        $out[] = ['id' => (int)$c['id'], 'label' => ($depth ? str_repeat('— ', $depth) : '') . $c['name']];
        buildCatOptionsList($cats, $excludeId, (int)$c['id'], $depth + 1, $out);
    }
}
buildCatOptionsList($cats, $edit['id'] ?? 0, 0, 0, $catOptions);
$catOptionsHtml = '<option value="0"' . (($edit['parent_id'] ?? 0) == 0 ? ' selected' : '') . '>' . __('无（顶级分类）') . '</option>';
foreach ($catOptions as $o) {
    $sel = (($edit['parent_id'] ?? 0) == $o['id']) ? ' selected' : '';
    $catOptionsHtml .= '<option value="' . $o['id'] . '"' . $sel . '>' . esc($o['label']) . '</option>';
}

/** 分类树行渲染（递归） */
function renderCatTreeRows($cats, $parent, $depth)
{
    $out = '';
    foreach ($cats as $c) {
        if ((int)$c['parent_id'] !== (int)$parent) continue;
        $indent = str_repeat('<span class="cat-indent">↳ </span>', $depth);
        $out .= '<tr>'
            . '<td>' . ($depth ? $indent : '') . esc($c['name']) . ($depth ? ' <span class="muted">(' . esc($c['slug']) . ')</span>' : '') . '</td>'
            . '<td class="muted">' . ($depth ? '' : esc($c['slug'])) . '</td>'
            . '<td>' . esc(($c['name_en'] ?? '') ?: '—') . '</td>'
            . '<td>' . (!empty($c['name_en']) ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>') . '</td>'
            . '<td>'
            . '<a class="btn btn-ghost btn-sm" href="' . baseUrl('admin/categories.php?edit=' . $c['id']) . '">' . __('编辑') . '</a> '
            . '<a class="btn btn-ghost btn-sm" href="' . baseUrl('admin/categories.php?edit=' . $c['id'] . '&lang=en') . '">' . __('英文') . ' ↗</a> '
            . '<a class="btn btn-danger btn-sm" href="' . baseUrl('admin/categories.php?del=' . $c['id'] . '&_csrf=' . csrfToken()) . '" onclick="return confirm(\'' . __('删除后文章将变为未分类，子分类提升为顶级，确定？') . '\')">' . __('删除') . '</a>'
            . '</td></tr>';
        $out .= renderCatTreeRows($cats, (int)$c['id'], $depth + 1);
    }
    return $out;
}

adminHead(__('分类管理'), 'categories.php');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h1><?php echo __('分类管理'); ?></h1>
    <?php if ($edit): ?>
    <div class="lang-tabs" id="lang-tabs">
        <button type="button" class="lang-tab<?php echo $editLang==='zh'?' active':''; ?>" data-lang="zh"><?php echo __('中文版'); ?></button>
        <button type="button" class="lang-tab<?php echo $editLang==='en'?' active':''; ?>" data-lang="en"><?php echo __('英文版'); ?></button>
    </div>
    <?php endif; ?>
</div>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>

<form class="panel" method="post">
    <h2><?php echo $edit ? __('编辑分类') : __('新增分类'); ?></h2>
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="lang" id="cat-lang" value="<?php echo $editLang; ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo $edit['id']; ?>"><?php endif; ?>

    <div class="lang-pane" id="pane-zh"<?php echo $editLang==='en'?' style="display:none"':''; ?>>
        <label><?php echo __('名称'); ?></label>
        <input type="text" name="name" value="<?php echo esc($edit['name'] ?? ''); ?>" required>
        <div class="row">
            <div><label><?php echo __('缩略名 (slug)'); ?></label><input type="text" name="slug" value="<?php echo esc($edit['slug'] ?? ''); ?>" placeholder="<?php echo __('自动生成'); ?>"></div>
            <div><label><?php echo __('描述'); ?></label><input type="text" name="description" value="<?php echo esc($edit['description'] ?? ''); ?>"></div>
        </div>
        <label><?php echo __('父分类'); ?></label>
        <select name="parent_id" style="width:100%;max-width:360px"><?php echo $catOptionsHtml; ?></select>
        <p class="muted" style="font-size:.82rem;margin-top:4px"><?php echo __('支持多级分类：选择父分类后，顶部导航的「分类」下拉会按层级展示全部分类。'); ?></p>
    </div>

    <?php if ($edit): ?>
    <div class="lang-pane" id="pane-en"<?php echo $editLang==='zh'?' style="display:none"':''; ?>>
        <p class="muted" style="font-size:.9rem"><?php echo __('中文版：'); ?> <strong><?php echo esc($edit['name']); ?></strong></p>
        <label><?php echo __('英文名称 (name_en)'); ?></label>
        <input type="text" name="name_en" value="<?php echo esc($edit['name_en'] ?? ''); ?>" placeholder="English name">
        <label><?php echo __('英文描述 (desc_en)'); ?></label>
        <input type="text" name="desc_en" value="<?php echo esc($edit['desc_en'] ?? ''); ?>" placeholder="English description">
        <p class="muted" style="font-size:.82rem"><?php echo __('留空则 /en 下自动回退显示中文。'); ?></p>
    </div>
    <?php endif; ?>

    <p style="margin-top:14px"><button class="btn" type="submit" name="save"><?php echo $editLang === 'en' ? __('保存英文版') : __('保存分类'); ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?php echo baseUrl('admin/categories.php'); ?>"><?php echo __('取消'); ?></a><?php endif; ?></p>
</form>
<script>
document.querySelectorAll('#lang-tabs .lang-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        var lang = tab.dataset.lang;
        document.querySelectorAll('#lang-tabs .lang-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
        document.getElementById('pane-zh').style.display = lang === 'zh' ? '' : 'none';
        var en = document.getElementById('pane-en');
        if (en) en.style.display = lang === 'en' ? '' : 'none';
        document.getElementById('cat-lang').value = lang;
        document.querySelector('form [type=submit][name=save]').textContent = lang === 'en' ? '<?php echo __('保存英文版'); ?>' : '<?php echo __('保存分类'); ?>';
    });
});
</script>

<div class="panel">
    <h2><?php echo __('现有分类'); ?></h2>
    <table class="data">
        <tr><th><?php echo __('名称'); ?></th><th><?php echo __('缩略名'); ?></th><th><?php echo __('英文'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
        <?php echo renderCatTreeRows($cats, 0, 0); ?>
        <?php if (empty($cats)): ?><tr><td colspan="5" class="muted"><?php echo __('暂无分类。'); ?></td></tr><?php endif; ?>
    </table>
</div>
<style>.cat-indent{color:#94a3b8;font-size:.82em}</style>
<?php adminFoot();
