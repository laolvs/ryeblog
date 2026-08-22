<?php
/**
 * RYE社区（RyeBlog 插件）—— 我的草稿
 * 路由：/bbs/drafts             （列表）
 *       /bbs/drafts?edit=N      （编辑草稿）
 *       /bbs/drafts?new=1       （新建草稿）
 */
require_once __DIR__ . '/bootstrap.php';
require_login();

$uid = (int) currentUser()['id'];

// 删除（GET 副作用，带 CSRF token）
if (isset($_GET['del'])) {
    if (!isset($_GET['_csrf']) || !hash_equals(csrfToken(), (string) $_GET['_csrf'])) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试。');
    }
    $del = (int) $_GET['del'];
    dbQuery('DELETE FROM ' . prefix() . 'drafts WHERE id=? AND user_id=?', [$del, $uid]);
    set_flash('草稿已删除', 'success');
    header('Location: ' . bbs_url('drafts'));
    exit;
}

// 保存（新建/编辑）
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$draft  = null;
if ($editId) {
    $draft = db_row('SELECT * FROM ' . prefix() . 'drafts WHERE id=? AND user_id=?', [$editId, $uid]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $fid     = (int) ($_POST['forum_id'] ?? 0);
    $id      = (int) ($_POST['id'] ?? 0);
    if ($title === '' && $content === '') {
        set_flash('草稿不能为空', 'error');
    } elseif ($id) {
        dbQuery('UPDATE ' . prefix() . 'drafts SET title=?, content=?, forum_id=?, updated_at=NOW() WHERE id=? AND user_id=?',
            [$title, $content, $fid, $id, $uid]);
        set_flash('草稿已保存', 'success');
        header('Location: ' . bbs_url('drafts'));
        exit;
    } else {
        dbQuery('INSERT INTO ' . prefix() . 'drafts (user_id, title, content, forum_id, updated_at) VALUES (?, ?, ?, ?, NOW())',
            [$uid, $title, $content, $fid]);
        set_flash('草稿已保存', 'success');
        header('Location: ' . bbs_url('drafts'));
        exit;
    }
}

$list = db_all('SELECT * FROM ' . prefix() . 'drafts WHERE user_id=? ORDER BY updated_at DESC', [$uid]);
$forums = db_all('SELECT id, name FROM ' . prefix() . 'forums ORDER BY display_order');
$editing = ($editId && $draft) || isset($_GET['new']);

$GLOBALS['bbs_page'] = 'drafts';
$GLOBALS['__rye_seo'] = ['desc' => '我的草稿', 'keywords' => '论坛,草稿'];
publicHeader();
require_once __DIR__ . '/inc/nav.php';
$flash = get_flash();
?>
<style>
.draft-wrap{max-width:820px;margin:18px auto;padding:0 12px}
.draft-wrap h1{color:#1f3d24;font-size:20px;margin:0 0 12px;display:flex;justify-content:space-between;align-items:center}
.draft-form{max-width:760px;background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:18px;margin-bottom:16px}
.draft-form label{display:block;margin:8px 0 4px;font-size:14px;color:#3a4a3e}
.draft-form input,.draft-form select,.draft-form textarea{width:100%;border:1px solid #cfd9c8;border-radius:8px;padding:9px;font:inherit}
.draft-item{border:1px solid #e3eadf;border-radius:10px;background:#fff;padding:12px 14px;margin-bottom:10px;display:flex;align-items:center;gap:10px}
.draft-item .d-main{flex:1;min-width:0}
.draft-item .d-title{font-size:15px;color:#1f3d24;font-weight:600}
.draft-item .d-sub{font-size:12px;color:#8aa091}
.draft-item a{color:#2c7d3f;text-decoration:none;margin-left:8px;font-size:13px}
.empty{color:#7a8a7e;text-align:center;padding:30px 0}
</style>
<div class="draft-wrap">
    <h1>我的草稿 <?php if (!$editing): ?><a class="btn btn-primary" href="<?php echo e(bbs_url('drafts?new=1')); ?>">+ 新建草稿</a><?php endif; ?></h1>
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>

    <?php if ($editing): ?>
        <form class="draft-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $draft ? $draft['id'] : 0; ?>">
            <label>标题<input name="title" maxlength="120" value="<?php echo e($draft['title'] ?? ''); ?>" placeholder="（可选）"></label>
            <label>版块
                <select name="forum_id">
                    <option value="0">未指定</option>
                    <?php foreach ($forums as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo ($draft && $draft['forum_id'] == $f['id']) ? 'selected' : ''; ?>><?php echo e($f['name']); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>内容
                <textarea name="content" rows="8" placeholder="随手记点什么…"><?php echo e($draft['content'] ?? ''); ?></textarea>
            </label>
            <div style="margin-top:12px;text-align:right">
                <a class="btn" href="<?php echo e(bbs_url('drafts')); ?>">取消</a>
                <button class="btn btn-primary" type="submit">保存草稿</button>
            </div>
        </form>
    <?php else: ?>
        <?php if (empty($list)): ?>
            <div class="empty">还没有草稿。在发表主题页可随时存草稿。</div>
        <?php else: foreach ($list as $d): ?>
            <div class="draft-item">
                <div class="d-main">
                    <div class="d-title"><?php echo e($d['title'] ?: '(无标题草稿)'); ?></div>
                    <div class="d-sub"><?php echo mb_strimwidth(strip_tags($d['content']), 0, 60, '…', 'UTF-8'); ?> · <?php echo time_ago($d['updated_at']); ?></div>
                </div>
                <a href="<?php echo e(bbs_url('drafts?edit=' . $d['id'])); ?>">编辑</a>
                <a href="<?php echo e(bbs_url('post?draft_id=' . $d['id'])); ?>">去发表</a>
                <a href="<?php echo e(bbs_url('drafts?del=' . $d['id'] . '&_csrf=' . csrfToken())); ?>" onclick="return confirm('确定删除该草稿？')">删除</a>
            </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
