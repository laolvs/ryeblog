<?php
/**
 * RyeBlog 后台 —— 标签管理（双语）
 * 中文：name（+slug 自动生成）；英文：name_en（翻译管理 ?lang=en 模式编辑）。
 */
require_once __DIR__ . '/admin.php';

$ok = $err = '';
$editLang = bilingualEnabled() && ($_GET['lang'] ?? 'zh') === 'en' ? 'en' : 'zh';

// 新增 / 更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } elseif ($editLang === 'en') {
        // ---- 英文模式：只更新 name_en ----
        $editId = (int)($_POST['id'] ?? 0);
        if ($editId <= 0) {
            $err = __('请先在中文模式下创建标签，再到翻译管理补英文。');
        } else {
            dbQuery('UPDATE vd_tags SET name_en=? WHERE id=?', [trim($_POST['name_en'] ?? ''), $editId]);
            header('Location: ' . baseUrl('admin/tags.php?edit=' . $editId . '&lang=en&saved=1'));
            exit;
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
        $editId = (int)($_POST['id'] ?? 0);
        if ($name === '') {
            $err = __('标签名称不能为空。');
        } else {
            if ($editId) {
                dbQuery('UPDATE vd_tags SET name=?, slug=? WHERE id=?', [$name, $slug, $editId]);
            } else {
                dbQuery('INSERT INTO vd_tags (name, slug, `count`) VALUES (?, ?, 0)', [$name, $slug]);
            }
            bumpContentRev(); // 标签云缓存实时失效
            header('Location: ' . baseUrl('admin/tags.php'));
            exit;
        }
    }
}

// 删除（同时清理文章关联）
if (isset($_GET['del']) && isset($_GET['_csrf']) && hash_equals($_SESSION['rye_csrf'] ?? '', $_GET['_csrf'])) {
    dbQuery('DELETE FROM vd_post_tags WHERE tag_id = ?', [(int)$_GET['del']]);
    dbQuery('DELETE FROM vd_tags WHERE id = ?', [(int)$_GET['del']]);
    bumpContentRev();
    header('Location: ' . baseUrl('admin/tags.php'));
    exit;
}

$edit = isset($_GET['edit']) ? dbOne('SELECT * FROM vd_tags WHERE id = ?', [(int)$_GET['edit']]) : null;
$tags = dbAll('SELECT t.*, (SELECT COUNT(*) FROM vd_post_tags pt WHERE pt.tag_id = t.id) AS post_count FROM vd_tags t ORDER BY post_count DESC, t.id');

adminHead(__('标签管理'), 'tags.php');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h1><?php echo __('标签管理'); ?></h1>
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
    <h2><?php echo $edit ? __('编辑标签') : __('新增标签'); ?></h2>
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="lang" id="tag-lang" value="<?php echo $editLang; ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>

    <div class="lang-pane" id="pane-zh"<?php echo $editLang==='en'?' style="display:none"':''; ?>>
        <div class="row">
            <div><label><?php echo __('名称'); ?></label><input type="text" name="name" value="<?php echo esc($edit['name'] ?? ''); ?>" required></div>
            <div><label><?php echo __('缩略名 (slug)'); ?></label><input type="text" name="slug" value="<?php echo esc($edit['slug'] ?? ''); ?>" placeholder="<?php echo __('自动生成'); ?>"></div>
        </div>
    </div>

    <?php if ($edit): ?>
    <div class="lang-pane" id="pane-en"<?php echo $editLang==='zh'?' style="display:none"':''; ?>>
        <p class="muted" style="font-size:.9rem"><?php echo __('中文版：'); ?> <strong><?php echo esc($edit['name']); ?></strong></p>
        <label><?php echo __('英文名称 (name_en)'); ?></label>
        <input type="text" name="name_en" value="<?php echo esc($edit['name_en'] ?? ''); ?>" placeholder="English tag name">
        <p class="muted" style="font-size:.82rem"><?php echo __('留空则 /en 下自动回退显示中文。'); ?></p>
    </div>
    <?php endif; ?>

    <p style="margin-top:14px"><button class="btn" type="submit" name="save"><?php echo $editLang === 'en' ? __('保存英文版') : __('保存标签'); ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?php echo baseUrl('admin/tags.php'); ?>"><?php echo __('取消'); ?></a><?php endif; ?></p>
</form>
<script>
document.querySelectorAll('#lang-tabs .lang-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        var lang = tab.dataset.lang;
        document.querySelectorAll('#lang-tabs .lang-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
        document.getElementById('pane-zh').style.display = lang === 'zh' ? '' : 'none';
        var en = document.getElementById('pane-en');
        if (en) en.style.display = lang === 'en' ? '' : 'none';
        document.getElementById('tag-lang').value = lang;
        document.querySelector('form [type=submit][name=save]').textContent = lang === 'en' ? '<?php echo __('保存英文版'); ?>' : '<?php echo __('保存标签'); ?>';
    });
});
</script>

<div class="panel">
    <h2><?php echo __('现有标签'); ?>（<?php echo count($tags); ?>）</h2>
    <table class="data">
        <tr><th><?php echo __('名称'); ?></th><th><?php echo __('英文'); ?></th><th><?php echo __('文章数'); ?></th><th><?php echo __('状态'); ?></th><th><?php echo __('操作'); ?></th></tr>
        <?php foreach ($tags as $t): ?>
            <tr>
                <td><a href="<?php echo tagUrl($t); ?>" target="_blank" rel="noopener"><?php echo esc($t['name']); ?></a></td>
                <td><?php echo esc(($t['name_en'] ?? '') ?: '—'); ?></td>
                <td><?php echo (int)$t['post_count']; ?></td>
                <td><?php echo !empty($t['name_en']) ? '<span class="badge badge-ok">' . __('已译') . '</span>' : '<span class="badge">' . __('仅中文') . '</span>'; ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/tags.php?edit=' . (int)$t['id']); ?>"><?php echo __('编辑'); ?></a>
                    <a class="btn btn-ghost btn-sm" href="<?php echo baseUrl('admin/tags.php?edit=' . (int)$t['id'] . '&lang=en'); ?>"><?php echo __('编辑英文'); ?> ↗</a>
                    <a class="btn btn-danger btn-sm" href="<?php echo baseUrl('admin/tags.php?del=' . (int)$t['id'] . '&_csrf=' . csrfToken()); ?>" onclick="return confirm('<?php echo __('确定删除该标签？文章将失去该标签关联。'); ?>')"><?php echo __('删除'); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($tags)): ?><tr><td colspan="5" class="muted"><?php echo __('暂无标签。'); ?></td></tr><?php endif; ?>
    </table>
</div>
<?php adminFoot();
