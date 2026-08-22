<?php
/**
 * RYE社区（RyeBlog 插件）—— 发表主题
 * 路由：/bbs/post?forum_id=N
 */
require_once __DIR__ . '/bootstrap.php';
require_login();

$forum_id = isset($_GET['forum_id']) ? (int) $_GET['forum_id'] : 0;
$forum = $forum_id ? db_row('SELECT * FROM ' . prefix() . 'forums WHERE id=?', [$forum_id]) : null;
$cat   = '';
$draftId = isset($_GET['draft_id']) ? (int) $_GET['draft_id'] : 0;
$prefillTitle = '';
$prefillContent = '';
if ($draftId) {
    $draft = db_row('SELECT * FROM ' . prefix() . 'drafts WHERE id=? AND user_id=?', [$draftId, currentUser()['id']]);
    if ($draft) {
        $prefillTitle   = $draft['title'];
        $prefillContent = $draft['content'];
        if (!$forum_id && $draft['forum_id']) {
            $forum_id = (int) $draft['forum_id'];
            $forum    = db_row('SELECT * FROM ' . prefix() . 'forums WHERE id=?', [$forum_id]);
        }
    }
}

// 编辑模式：/bbs/post?id=N —— 仅作者本人可编辑自己的主题
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($editId) {
    $editThread = db_row('SELECT * FROM ' . prefix() . 'threads WHERE id=? AND is_deleted=0', [$editId]);
    if (!$editThread || (int) $editThread['user_id'] !== (int) currentUser()['id']) {
        http_response_code(404);
        publicHeader();
        echo '<div class="empty">主题不存在或无权编辑。</div>';
        publicFooter(rye_sidebar_html());
        exit;
    }
    $prefillTitle   = $editThread['title'];
    $prefillContent = $editThread['content'];
    $cat            = (string) $editThread['topic_category'];
    $forum_id       = (int) $editThread['forum_id'];
    $forum          = db_row('SELECT * FROM ' . prefix() . 'forums WHERE id=?', [$forum_id]) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fid      = (int) ($_POST['forum_id'] ?? 0);
    $draftId  = (int) ($_POST['draft_id'] ?? 0);
    $editId   = (int) ($_POST['edit_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $cat     = trim($_POST['topic_category'] ?? '');

    // 校验版块真实存在，防孤儿主题（注意 forums 表无 is_hidden 列，仅 forum_sections 有）
    $forumExists = $fid ? db_row('SELECT id FROM ' . prefix() . 'forums WHERE id=?', [$fid]) : null;

    // 敏感词过滤（replace 替换 / block 拒绝）
    $fTitle   = ryebbs_filter_sensitive($title);
    $fContent = ryebbs_filter_sensitive($content);
    // 远程图片自动本地化（博客后台设置 localize_remote_images 控制；失败保留原链）
    $locReport = null;
    $fContent  = function_exists('localizeRemoteImages') ? localizeRemoteImages($fContent, 'markdown', $locReport) : $fContent;
    if (ryebbs_ip_banned()) {
        set_flash('您的 IP 已被封禁，无法发帖', 'error');
    } elseif ($fTitle === false || $fContent === false) {
        set_flash('内容包含敏感词，已被拦截', 'error');
    } elseif (!$fid || !$forumExists) {
        set_flash('请选择有效的版块', 'error');
    } elseif (mb_strlen($title, 'UTF-8') < 2) {
        set_flash('标题至少 2 个字符', 'error');
    } elseif ($content === '') {
        set_flash('内容不能为空', 'error');
    } elseif ($editId) {
        // 编辑主题：仅作者可更新；版块变更时同步计数
        $old = db_row('SELECT forum_id FROM ' . prefix() . 'threads WHERE id=? AND user_id=?', [$editId, currentUser()['id']]);
        if (!$old) {
            set_flash('无权编辑该主题', 'error');
        } else {
            dbQuery('UPDATE ' . prefix() . 'threads SET forum_id=?, topic_category=?, title=?, content=?, updated_at=NOW() WHERE id=? AND user_id=?',
                [$fid, $cat, $fTitle, $fContent, $editId, currentUser()['id']]);
            if ((int) $old['forum_id'] !== $fid) {
                dbQuery('UPDATE ' . prefix() . 'forums SET thread_count=GREATEST(thread_count-1,0) WHERE id=?', [(int) $old['forum_id']]);
                dbQuery('UPDATE ' . prefix() . 'forums SET thread_count=thread_count+1 WHERE id=?', [$fid]);
            }
            set_flash('主题已更新', 'success');
            header('Location: ' . bbs_url('thread?id=' . $editId));
            exit;
        }
    } else {
        dbQuery(
            'INSERT INTO ' . prefix() . 'threads
                (forum_id, user_id, topic_category, title, content, views, replies, is_top, is_good,
                 is_deleted, is_closed, visibility_type, visibility_cost, last_post_at, ip, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, 0, NOW(), ?, NOW(), NOW())',
            [$fid, currentUser()['id'], $cat, $fTitle, $fContent, client_ip()]
        );
        $newId = (int) db_val('SELECT LAST_INSERT_ID()');
        dbQuery('UPDATE ' . prefix() . 'forums SET thread_count=thread_count+1 WHERE id=?', [$fid]);
        ryebbs_recount_user(currentUser()['id']);
        if ($draftId) {
            dbQuery('DELETE FROM ' . prefix() . 'drafts WHERE id=? AND user_id=?', [$draftId, currentUser()['id']]);
        }
        set_flash('主题发布成功', 'success');
        header('Location: ' . bbs_url('thread?id=' . $newId));
        exit;
    }
}

$forums = db_all('SELECT id, name FROM ' . prefix() . 'forums ORDER BY display_order');

// 校验失败时保留已填内容（标题/内容/版块选择；敏感词 replace 的显示过滤后结果）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $forum_id       = $fid;
    $forum          = $fid ? (db_row('SELECT * FROM ' . prefix() . 'forums WHERE id=?', [$fid]) ?: null) : null;
    $prefillTitle   = ($fTitle !== false) ? $fTitle : $title;
    $prefillContent = ($fContent !== false) ? $fContent : $content;
}

$GLOBALS['__rye_seo'] = ['desc' => '发表主题', 'keywords' => '论坛,发表'];
$GLOBALS['bbs_page']  = 'post';
publicHeader();
require_once __DIR__ . '/inc/nav.php';
$flash = get_flash();
if ($flash) {
    echo '<div class="flash flash-' . e($flash['type']) . '">' . e($flash['msg']) . '</div>';
}
?>
<style>
.post-form{max-width:760px;margin:18px auto;background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:18px}
.post-form label{display:block;margin:10px 0 4px;font-size:14px;color:#3a4a3e}
.post-form input,.post-form select,.post-form textarea{width:100%;border:1px solid #cfd9c8;border-radius:8px;padding:9px;font:inherit}
</style>
<div class="post-form">
    <h1 style="margin-top:0;color:#1f3d24"><?php echo $editId ? '编辑主题' : '发表主题'; ?></h1>
    <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="draft_id" value="<?php echo $draftId; ?>">
        <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
        <label>选择版块
            <select name="forum_id">
                <option value="0">请选择…</option>
                <?php foreach ($forums as $f): ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo ($forum && $forum['id'] == $f['id']) ? 'selected' : ''; ?>><?php echo e($f['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>标题
            <input name="title" maxlength="120" value="<?php echo e($prefillTitle); ?>" placeholder="给主题起个标题">
        </label>
        <?php if ($forum && !empty($forum['topic_category_enabled'])):
            $cats = array_filter(array_map('trim', explode(',', $forum['topic_categories'])));
            if (!empty($cats)): ?>
        <label>分类
            <select name="topic_category">
                <option value="">请选择…</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?php echo e($c); ?>" <?php echo ($cat === $c) ? 'selected' : ''; ?>><?php echo e($c); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; endif; ?>
        <label>内容
            <div class="rye-toolbar">
                <button type="button" data-md="bold" title="加粗"><svg class="ic"><use href="#ic-bold"/></svg></button>
                <button type="button" data-md="italic" title="斜体"><svg class="ic"><use href="#ic-italic"/></svg></button>
                <button type="button" data-md="strike" title="删除线"><svg class="ic"><use href="#ic-strike"/></svg></button>
                <span class="rye-tb-sep"></span>
                <button type="button" data-md="h2" title="标题"><svg class="ic"><use href="#ic-heading"/></svg></button>
                <button type="button" data-md="quote" title="引用"><svg class="ic"><use href="#ic-quote"/></svg></button>
                <button type="button" data-md="ul" title="无序列表"><svg class="ic"><use href="#ic-list"/></svg></button>
                <button type="button" data-md="ol" title="有序列表"><svg class="ic"><use href="#ic-list-ol"/></svg></button>
                <span class="rye-tb-sep"></span>
                <button type="button" data-md="code" title="行内代码"><svg class="ic"><use href="#ic-code"/></svg></button>
                <button type="button" data-md="codeblock" title="代码块"><svg class="ic"><use href="#ic-codeblock"/></svg></button>
                <button type="button" data-md="link" title="插入链接"><svg class="ic"><use href="#ic-link"/></svg></button>
                <button type="button" data-md="image" title="插入图片链接"><svg class="ic"><use href="#ic-image"/></svg></button>
                <button type="button" data-upload="img" title="上传图片"><svg class="ic"><use href="#ic-upload"/></svg></button>
                <button type="button" data-upload="file" title="上传附件"><svg class="ic"><use href="#ic-attach"/></svg></button>
            </div>
            <textarea class="rye-editor" name="content" rows="8" placeholder="分享你的想法…"><?php echo e($prefillContent); ?></textarea>
            <p class="muted" style="margin:6px 0 0;font-size:12px">支持 Markdown：**加粗** *斜体* ~~删除线~~ `代码`、[链接](url)、# 标题、&gt; 引用、- 列表、``` 代码块</p>
        </label>
        <div style="margin-top:12px;text-align:right"><button class="btn btn-primary" type="submit"><?php echo $editId ? '保存修改' : '发布主题'; ?></button></div>
    </form>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
