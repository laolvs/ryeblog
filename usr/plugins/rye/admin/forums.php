<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台版块管理
 * 路由：admin/plugin.php?p=rye&page=forums
 *
 * 功能：版块分组(section) 与版块(forum) 的增删改、隐藏/显示，
 *       以及「一键应用推荐结构」。线程数 > 0 的版块禁止删除（防孤儿主题）。
 */
require_once __DIR__ . '/../bootstrap.php';

$P = prefix();

// 推荐结构（参照 chake.org 三段式：知识库 / 交流区 / 站务）
function rye_recommended_structure()
{
    return [
        'sections' => [
            ['name' => '知识库', 'display_order' => 1],
            ['name' => '交流区', 'display_order' => 2],
            ['name' => '站务',   'display_order' => 3],
        ],
        'forums' => [
            [1, 'RyeBlog 教程',     '安装部署、配置、日常使用与技巧教程', 0, '', 1],
            [1, 'RyeCMS 教程',      'RyeCMS 建站、模块与二次开发教程',   0, '', 2],
            [1, '主题与插件开发',   '主题分享、定制与插件开发答疑',       0, '', 3],
            [2, '综合讨论',         'RyeBlog / RyeCMS 相关话题自由交流',  0, '', 1],
            [2, '案例与作品',       '展示你的站点、主题、插件与实战经验',  0, '', 2],
            [2, '闲聊灌水',         '茶余饭后、随便聊聊',                 0, '', 3],
            [3, '公告动态',         '版本发布、活动与社区公告',           0, '', 1],
            [3, 'BUG 提交',         '反馈缺陷与异常，请附复现步骤',       1, '待处理,处理中,已解决,已拒绝', 2],
            [3, '建议反馈',         '功能建议与改进意见',                 0, '', 3],
        ],
    ];
}

function rye_apply_recommended()
{
    global $P;
    $pdo = db();
    $pdo->exec("DELETE FROM {$P}forums");
    $pdo->exec("DELETE FROM {$P}forum_sections");
    $pdo->exec("ALTER TABLE {$P}forum_sections AUTO_INCREMENT=1");
    $pdo->exec("ALTER TABLE {$P}forums AUTO_INCREMENT=1");
    $now = date('Y-m-d H:i:s');
    $rec = rye_recommended_structure();
    $insS = $pdo->prepare("INSERT INTO {$P}forum_sections (name, display_order, is_hidden, created_at) VALUES (?,?,0,?)");
    foreach ($rec['sections'] as $s) {
        $insS->execute([$s['name'], $s['display_order'], $now]);
    }
    $insF = $pdo->prepare("INSERT INTO {$P}forums (section_id,name,description,topic_category_enabled,topic_categories,icon,name_color,show_on_index,display_order,thread_count,post_count,created_at) VALUES (?,?,?,?,?,?,?,1,?,0,0,?)");
    foreach ($rec['forums'] as $f) {
        $insF->execute([$f[0], $f[1], $f[2], $f[3], $f[4], '', '', $f[5], $now]);
    }
}

// ---- POST 处理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'apply_recommended') {
        // 仅当无真实主题时允许重置，避免误清数据
        $tc = (int) db_val("SELECT COUNT(*) FROM {$P}threads");
        if ($tc > 0) {
            set_flash("存在 {$tc} 个主题，为避免误删数据，已取消重置。请先清空主题后再操作。", 'error');
        } else {
            rye_apply_recommended();
            set_flash('已应用推荐版块结构', 'success');
        }
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=forums'));
        exit;
    }
    if ($act === 'save_section') {
        $id    = (int) ($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $order = (int) ($_POST['display_order'] ?? 0);
        if ($name === '') {
            set_flash('分组名称不能为空', 'error');
        } elseif ($id > 0) {
            dbQuery("UPDATE {$P}forum_sections SET name=?, display_order=? WHERE id=?", [$name, $order, $id]);
            set_flash('分组已更新', 'success');
        } else {
            dbQuery("INSERT INTO {$P}forum_sections (name, display_order, is_hidden, created_at) VALUES (?,?,0,NOW())", [$name, $order]);
            set_flash('分组已添加', 'success');
        }
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=forums'));
        exit;
    }
    if ($act === 'save_forum') {
        $id       = (int) ($_POST['id'] ?? 0);
        $secId    = (int) ($_POST['section_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $catOn    = isset($_POST['topic_category_enabled']) ? 1 : 0;
        $cats     = trim($_POST['topic_categories'] ?? '');
        $showIdx  = isset($_POST['show_on_index']) ? 1 : 0;
        $order    = (int) ($_POST['display_order'] ?? 0);
        if ($name === '') {
            set_flash('版块名称不能为空', 'error');
        } elseif ($secId < 1) {
            set_flash('请选择所属分组', 'error');
        } elseif ($id > 0) {
            dbQuery("UPDATE {$P}forums SET section_id=?, name=?, description=?, topic_category_enabled=?, topic_categories=?, show_on_index=?, display_order=? WHERE id=?",
                [$secId, $name, $desc, $catOn, $cats, $showIdx, $order, $id]);
            set_flash('版块已更新', 'success');
        } else {
            dbQuery("INSERT INTO {$P}forums (section_id,name,description,topic_category_enabled,topic_categories,icon,name_color,show_on_index,display_order,thread_count,post_count,created_at) VALUES (?,?,?,?,?,?,?,?,?,0,0,NOW())",
                [$secId, $name, $desc, $catOn, $cats, '', '', $showIdx, $order]);
            set_flash('版块已添加', 'success');
        }
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=forums'));
        exit;
    }
    if ($act === 'delete_section') {
        $id = (int) ($_POST['id'] ?? 0);
        $fc = (int) db_val("SELECT COUNT(*) FROM {$P}forums WHERE section_id=?", [$id]);
        if ($fc > 0) {
            set_flash("该分组下还有 {$fc} 个版块，无法删除。请先移除版块。", 'error');
        } else {
            dbQuery("DELETE FROM {$P}forum_sections WHERE id=?", [$id]);
            set_flash('分组已删除', 'success');
        }
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=forums'));
        exit;
    }
    if ($act === 'delete_forum') {
        $id = (int) ($_POST['id'] ?? 0);
        $tc = (int) db_val("SELECT thread_count FROM {$P}forums WHERE id=?", [$id]);
        if ($tc > 0) {
            set_flash("该版块还有 {$tc} 个主题，无法删除（防孤儿主题）。请先清空版块主题。", 'error');
        } else {
            dbQuery("DELETE FROM {$P}forums WHERE id=?", [$id]);
            set_flash('版块已删除', 'success');
        }
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=forums'));
        exit;
    }
    if ($act === 'toggle_section_hidden') {
        $id = (int) ($_POST['id'] ?? 0);
        dbQuery("UPDATE {$P}forum_sections SET is_hidden = 1 - is_hidden WHERE id=?", [$id]);
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=forums'));
        exit;
    }
    if ($act === 'toggle_forum_show') {
        $id = (int) ($_POST['id'] ?? 0);
        dbQuery("UPDATE {$P}forums SET show_on_index = 1 - show_on_index WHERE id=?", [$id]);
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=forums'));
        exit;
    }
}

// ---- 编辑态预填 ----
$editSection = null;
$editForum   = null;
if (isset($_GET['edit_section'])) {
    $editSection = db_row("SELECT * FROM {$P}forum_sections WHERE id=?", [(int) $_GET['edit_section']]);
}
if (isset($_GET['edit_forum'])) {
    $editForum = db_row("SELECT * FROM {$P}forums WHERE id=?", [(int) $_GET['edit_forum']]);
}

$sections = db_all("SELECT * FROM {$P}forum_sections ORDER BY display_order, id");
$forums   = db_all("SELECT * FROM {$P}forums ORDER BY section_id, display_order, id");
$bySec = [];
foreach ($forums as $f) {
    $bySec[$f['section_id']][] = $f;
}
$flash = get_flash();

adminHead('版块管理 · RYE社区');
require __DIR__ . '/inc/admin_nav.php';
?>
<style>
.mt-admin-wrap{max-width:980px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-sec{border:1px solid #eef2ea;border-radius:10px;padding:12px;margin-bottom:12px}
.mt-sec-title{font-weight:600;color:#2c5234;display:flex;align-items:center;gap:8px}
.mt-forum{display:flex;align-items:center;gap:10px;padding:7px 0;border-top:1px dashed #eef2ea;font-size:14px}
.mt-forum:first-child{border-top:none}
.mt-forum .nm{font-weight:500}
.mt-forum .meta{color:#8a968c;font-size:12px}
.mt-tag{display:inline-block;background:#eaf3e6;color:#2c5234;border-radius:6px;padding:1px 7px;font-size:12px;margin-left:6px}
.mt-hidden{opacity:.5}
.mt-form label{display:block;margin:8px 0 3px;font-size:13px;color:#3a4a3e}
.mt-form input[type=text],.mt-form input[type=number],.mt-form textarea,.mt-form select{width:100%;border:1px solid #cfd9c8;border-radius:8px;padding:8px;font:inherit}
.mt-row{display:flex;gap:12px;flex-wrap:wrap}
.mt-row>div{flex:1;min-width:160px}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.mt-actions{margin-left:auto;display:flex;gap:6px}
.muted{color:#8a968c}
</style>

<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>

    <div class="mt-card">
        <h2>一键推荐结构</h2>
        <p class="muted">参照 chake.org 三段式快速建立：知识库（RyeBlog 教程 / RyeCMS 教程 / 主题与插件开发）+ 交流区（综合讨论 / 案例与作品 / 闲聊灌水）+ 站务（公告动态 / BUG 提交 / 建议反馈）。仅在当前无任何主题时可重置。</p>
        <form method="post" onsubmit="return confirm('确定应用推荐结构？将清空当前所有版块（需无主题）。');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="apply_recommended">
            <button class="btn btn-primary" type="submit">应用推荐结构</button>
        </form>
    </div>

    <div class="mt-card">
        <h2>版块分组与版块</h2>
        <?php if (empty($sections)): ?>
            <p class="muted">暂无分组，请在下方添加。</p>
        <?php endif; ?>
        <?php foreach ($sections as $sec): ?>
            <div class="mt-sec <?php echo $sec['is_hidden'] ? 'mt-hidden' : ''; ?>">
                <div class="mt-sec-title">
                    <?php echo e($sec['name']); ?>
                    <?php if ($sec['is_hidden']): ?><span class="mt-tag">已隐藏</span><?php endif; ?>
                    <span class="mt-actions">
                        <a class="btn-sm" href="?p=rye&page=forums&edit_section=<?php echo $sec['id']; ?>">编辑</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('隐藏/显示该分组？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="toggle_section_hidden"><input type="hidden" name="id" value="<?php echo $sec['id']; ?>"><button class="btn-sm" type="submit"><?php echo $sec['is_hidden'] ? '显示' : '隐藏'; ?></button></form>
                        <form method="post" style="display:inline" onsubmit="return confirm('删除该分组？（需先无版块）');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_section"><input type="hidden" name="id" value="<?php echo $sec['id']; ?>"><button class="btn-sm danger" type="submit">删除</button></form>
                    </span>
                </div>
                <?php foreach (($bySec[$sec['id']] ?? []) as $f): ?>
                    <div class="mt-forum <?php echo $f['show_on_index'] ? '' : 'mt-hidden'; ?>">
                        <span class="nm"><?php echo e($f['name']); ?></span>
                        <span class="meta">主题 <?php echo (int) $f['thread_count']; ?></span>
                        <?php if ($f['topic_category_enabled']): ?><span class="mt-tag">状态分类</span><?php endif; ?>
                        <?php if (!$f['show_on_index']): ?><span class="mt-tag">不显示在首页</span><?php endif; ?>
                        <span class="mt-actions">
                            <a class="btn-sm" href="?p=rye&page=forums&edit_forum=<?php echo $f['id']; ?>">编辑</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('显示/隐藏该版块？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="toggle_forum_show"><input type="hidden" name="id" value="<?php echo $f['id']; ?>"><button class="btn-sm" type="submit"><?php echo $f['show_on_index'] ? '隐藏' : '显示'; ?></button></form>
                            <form method="post" style="display:inline" onsubmit="return confirm('删除该版块？（需先无主题）');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_forum"><input type="hidden" name="id" value="<?php echo $f['id']; ?>"><button class="btn-sm danger" type="submit">删除</button></form>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($editSection): ?>
    <div class="mt-card">
        <h2>编辑分组</h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="save_section">
            <input type="hidden" name="id" value="<?php echo $editSection['id']; ?>">
            <label>分组名称<input type="text" name="name" value="<?php echo e($editSection['name']); ?>" required></label>
            <label>排序（数字越小越靠前）<input type="number" name="display_order" value="<?php echo (int) $editSection['display_order']; ?>"></label>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存</button> <a class="btn-sm" href="?p=rye&page=forums">取消</a></div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($editForum): ?>
    <div class="mt-card">
        <h2>编辑版块</h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="save_forum">
            <input type="hidden" name="id" value="<?php echo $editForum['id']; ?>">
            <label>所属分组
                <select name="section_id">
                    <?php foreach ($sections as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $editForum['section_id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>版块名称<input type="text" name="name" value="<?php echo e($editForum['name']); ?>" required></label>
            <label>简介<input type="text" name="description" value="<?php echo e($editForum['description']); ?>"></label>
            <div class="mt-row">
                <div><label>启用状态分类（如 BUG 处理状态）
                    <select name="topic_category_enabled">
                        <option value="0" <?php echo empty($editForum['topic_category_enabled']) ? 'selected' : ''; ?>>关闭</option>
                        <option value="1" <?php echo !empty($editForum['topic_category_enabled']) ? 'selected' : ''; ?>>开启</option>
                    </select>
                </label></div>
                <div><label>分类项（逗号分隔，如 待处理,处理中,已解决,已拒绝）<input type="text" name="topic_categories" value="<?php echo e($editForum['topic_categories']); ?>"></label></div>
            </div>
            <div class="mt-row">
                <div><label>排序<input type="number" name="display_order" value="<?php echo (int) $editForum['display_order']; ?>"></label></div>
                <div><label>显示在论坛首页
                    <select name="show_on_index">
                        <option value="1" <?php echo !empty($editForum['show_on_index']) ? 'selected' : ''; ?>>显示</option>
                        <option value="0" <?php echo empty($editForum['show_on_index']) ? 'selected' : ''; ?>>隐藏</option>
                    </select>
                </label></div>
            </div>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存</button> <a class="btn-sm" href="?p=rye&page=forums">取消</a></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="mt-card">
        <h2>添加版块分组</h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="save_section">
            <label>分组名称<input type="text" name="name" required></label>
            <label>排序<input type="number" name="display_order" value="0"></label>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">添加分组</button></div>
        </form>
    </div>

    <div class="mt-card">
        <h2>添加版块</h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="save_forum">
            <div class="mt-row">
                <div><label>所属分组
                    <select name="section_id">
                        <option value="0">请选择…</option>
                        <?php foreach ($sections as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo e($s['name']); ?></option><?php endforeach; ?>
                    </select>
                </label></div>
                <div><label>版块名称<input type="text" name="name" required></label></div>
            </div>
            <label>简介<input type="text" name="description"></label>
            <div class="mt-row">
                <div><label>启用状态分类
                    <select name="topic_category_enabled">
                        <option value="0">关闭</option>
                        <option value="1">开启</option>
                    </select>
                </label></div>
                <div><label>分类项（逗号分隔）<input type="text" name="topic_categories" placeholder="待处理,处理中,已解决,已拒绝"></label></div>
            </div>
            <div class="mt-row">
                <div><label>排序<input type="number" name="display_order" value="0"></label></div>
                <div><label>显示在论坛首页
                    <select name="show_on_index">
                        <option value="1">显示</option>
                        <option value="0">隐藏</option>
                    </select>
                </label></div>
            </div>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">添加版块</button></div>
        </form>
    </div>
</div>
<?php adminFoot(); ?>
