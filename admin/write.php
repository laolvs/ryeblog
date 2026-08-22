<?php
/** RyeBlog 后台 —— 撰写 / 编辑文章或页面（含标签/SEO/封面/附件） */
require_once __DIR__ . '/admin.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0 ? getPost($id) : null;
$editLang = bilingualEnabled() && ($_GET['lang'] ?? 'zh') === 'en' ? 'en' : 'zh'; // 内容编辑语言（仅双语模式可用）

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } elseif (($_POST['lang'] ?? 'zh') === 'en') {
        // ---- 英文模式：只更新 *_en 字段与 slug_en，不触碰中文及其它元数据 ----
        if ($id <= 0) {
            $err = __('请先在中文模式下创建内容，再到英文版补全。');
        } else {
            $titleEn   = trim($_POST['title_en'] ?? '');
            $contentEn = $_POST['content_en'] ?? '';
            $slugEn    = trim($_POST['slug_en'] ?? '');
            $excerptEn = trim($_POST['excerpt_en'] ?? '');
            $seoDescEn = trim($_POST['seo_description_en'] ?? '');
            $seoKwEn   = trim($_POST['seo_keywords_en'] ?? '');
            dbQuery('UPDATE vd_posts SET title_en=?, content_en=?, slug_en=?, excerpt_en=?, seo_description_en=?, seo_keywords_en=?, updated_at=NOW() WHERE id=?',
                [$titleEn, $contentEn, $slugEn, $excerptEn, $seoDescEn, $seoKwEn, $id]);
            header('Location: ' . baseUrl('admin/write.php?id=' . $id . '&lang=en&saved=1'));
            exit;
        }
    } else {
        $title   = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $type    = $_POST['type'] === 'page' ? 'page' : 'post';
        $status  = $_POST['status'] === 'draft' ? 'draft' : 'published';
        $format  = $_POST['format'] === 'html' ? 'html' : 'markdown';
        $catId   = $type === 'post' ? (int)($_POST['category_id'] ?? 0) : null;
        $slug    = trim($_POST['slug'] ?? '') ?: slugify($title);
        $excerpt = trim($_POST['excerpt'] ?? '');
        $seoDesc = trim($_POST['seo_description'] ?? '');
        $seoKw   = trim($_POST['seo_keywords'] ?? '');
        $cover   = trim($_POST['cover_image'] ?? '');
        $allowC  = isset($_POST['allow_comment']) ? 1 : 0;
        $tagStr  = trim($_POST['tags'] ?? '');

        // 封面图为空时自动取正文第一张本地上传图片
        if ($cover === '') {
            $imgs = scanContentForImages($content, $format);
            if (!empty($imgs)) $cover = $imgs[0];
        }

        // 远程图片自动本地化（后台设置可关；下载失败的远程图原样保留）
        $locReport = null;
        if (getOption('localize_remote_images', '1') === '1') {
            $content = localizeRemoteImages($content, $format, $locReport);
            if ($locReport && $locReport['downloaded'] > 0) {
                $locMsg = __('已自动本地化远程图片 ') . $locReport['downloaded']
                        . ($locReport['failed'] > 0 ? '，' . $locReport['failed'] . __(' 张下载失败已保留原链') : '');
                $_SESSION['rye_flash'] = $locMsg; // saved=1 页展示
            }
        }

        if ($title === '') {
            $err = __('标题不能为空。');
        } else {
            if ($editing) {
                // 中文模式不更新 *_en 列：保留已有英文翻译，不被清空
                dbQuery(
                    'UPDATE vd_posts SET title=?, slug=?, content=?, type=?, status=?, format=?, category_id=?, excerpt=?, seo_description=?, seo_keywords=?, cover_image=?, allow_comment=?, updated_at=NOW() WHERE id=?',
                    [$title, $slug, $content, $type, $status, $format, $catId, $excerpt, $seoDesc, $seoKw, $cover, $allowC, $id]
                );
                $postId = $id;
            } else {
                $postId = dbInsert(
                    'INSERT INTO vd_posts (title, slug, content, type, status, format, category_id, author, excerpt, seo_description, seo_keywords, cover_image, allow_comment, created_at, updated_at, views)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)',
                    [$title, $slug, $content, $type, $status, $format, $catId, (currentAdmin()['username'] ?? 'admin'), $excerpt, $seoDesc, $seoKw, $cover, $allowC]
                );
                $editing = getPost($postId);
                $id = $postId;
            }
            // 内容版本号 +1：归档/分类计数/标签云/列表 COUNT 等缓存键随之换新（实时失效）
            bumpContentRev();
            // 编辑时清理该篇正文渲染缓存（防磁盘膨胀；键含 content 哈希本可自动失效）
            clearPostHtmlCache((int)$postId);
            // 新建文章：归档月计数物化增量 +1（编辑不改变 created_at，无需处理）
            if (!$editing) bumpArchiveStatsNow();
            // 标签
            setPostTags($postId, $tagStr === '' ? [] : explode(',', $tagStr));

            // --- 表单提交的传统附件上传（兼容已有 UI） ---
            if ($postId && !empty($_FILES['attachments']['name'][0])) {
                $files = $_FILES['attachments'];
                $count = count($files['name']);
                $rel   = getUploadRelDir();
                $abs   = ensureUploadDir($rel);
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $check = validateUploadFile([
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ], null, getMaxUploadSize());
                    if ($check !== true) continue;
                    $basename = makeUniqueFilename($files['name'][$i]);
                    $dest = $abs . $basename;
                    if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                        @chmod($dest, 0644);
                        $mime = function_exists('mime_content_type') ? (mime_content_type($dest) ?: 'application/octet-stream') : 'application/octet-stream';
                        addAttachment($postId, $files['name'][$i], $rel . $basename, $files['size'][$i], $mime);
                    }
                }
            }

            // --- 自动清理：未引用的附件（图片/文件均按正文实际引用比对待删） ---
            // 1) 把"编辑期间产生的临时附件（post_id IS NULL）"统一绑定到本文章，避免误删。
            // 2) 对比正文里实际出现的本地上传资源，删掉正文已删除的附件（db 记录 + 物理文件）。
            $usedKeys = attachmentUsedKeysFromContent($content, $format);
            cleanupUnusedAttachments($postId, $usedKeys);

            header('Location: ' . baseUrl('admin/write.php?id=' . $postId . '&saved=1'));
            exit;
        }
    }
}

$cats = getCategories();
$val = $editing ?? ['title'=>'','slug'=>'','content'=>'','title_en'=>'','content_en'=>'','type'=>'post','status'=>'published','format'=>'markdown','category_id'=>0,'excerpt'=>'','seo_description'=>'','seo_keywords'=>'','cover_image'=>'','allow_comment'=>1];
$curTags = $editing ? implode(',', array_map(function($t){return $t['name'];}, getPostTags($editing['id']))) : '';
$atts = $editing ? getAttachments($editing['id']) : [];

adminHead(($editing ? __('编辑内容') : __('写文章')), 'write.php');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h1><?php echo $editing ? __('编辑内容') : __('撰写新内容'); ?></h1>
    <?php if ($editing): ?>
    <div class="lang-tabs" id="lang-tabs">
        <button type="button" class="lang-tab<?php echo $editLang==='zh'?' active':''; ?>" data-lang="zh"><?php echo __('中文版'); ?></button>
        <button type="button" class="lang-tab<?php echo $editLang==='en'?' active':''; ?>" data-lang="en"><?php echo __('英文版'); ?></button>
    </div>
    <?php endif; ?>
</div>
<?php if (!$editing && $editLang === 'en'): ?>
    <div class="notice notice-err"><?php echo __('请先在中文模式下创建内容，再到英文版补全。'); ?></div>
<?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="notice notice-ok">✓ <?php echo __('已保存。'); ?>
    <?php if (!empty($_SESSION['rye_flash'])): ?><span style="color:#2c7d3f"><?php echo esc($_SESSION['rye_flash']); ?></span><?php unset($_SESSION['rye_flash']); endif; ?>
</div><?php endif; ?>
<?php if (isset($err)): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>

<form method="post" id="write-form" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="lang" id="write-lang" value="<?php echo $editLang; ?>">

    <div class="lang-pane" id="pane-zh"<?php echo $editLang==='en'?' style="display:none"':''; ?>>
    <label><?php echo __('标题'); ?></label>
    <input type="text" name="title" value="<?php echo esc($val['title']); ?>" required>

    <div class="row">
        <div><label><?php echo __('类型'); ?></label>
            <select name="type">
                <option value="post" <?php echo $val['type']==='post'?'selected':''; ?>><?php echo __('文章'); ?></option>
                <option value="page" <?php echo $val['type']==='page'?'selected':''; ?>><?php echo __('独立页面'); ?></option>
            </select>
        </div>
        <div><label><?php echo __('状态'); ?></label>
            <select name="status">
                <option value="published" <?php echo $val['status']==='published'?'selected':''; ?>><?php echo __('已发布'); ?></option>
                <option value="draft" <?php echo $val['status']==='draft'?'selected':''; ?>><?php echo __('草稿'); ?></option>
            </select>
        </div>
        <div><label><?php echo __('分类（仅文章）'); ?></label>
            <select name="category_id">
                <option value="0"><?php echo __('未分类'); ?></option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($val['category_id'] ?? 0)==$c['id']?'selected':''; ?>><?php echo esc($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label><?php echo __('正文格式'); ?></label>
            <select name="format" id="format-select">
                <option value="markdown" <?php echo ($val['format'] ?? 'markdown')==='markdown'?'selected':''; ?>>Markdown</option>
                <option value="html" <?php echo ($val['format'] ?? 'markdown')==='html'?'selected':''; ?>>HTML</option>
            </select>
        </div>
        <div><label><?php echo __('允许评论'); ?></label>
            <select name="allow_comment_on">
                <option value="1" <?php echo ($val['allow_comment'] ?? 1) ? 'selected':''; ?>><?php echo __('允许'); ?></option>
                <option value="0" <?php echo !($val['allow_comment'] ?? 1) ? 'selected':''; ?>><?php echo __('关闭'); ?></option>
            </select>
        </div>
    </div>

    <label><?php echo __('缩略名 (slug，留空自动生成)'); ?></label>
    <input type="text" name="slug" value="<?php echo esc($val['slug']); ?>" placeholder="<?php echo __('例如'); ?> my-first-post">

    <label><?php echo __('标签（逗号分隔）'); ?></label>
    <input type="text" name="tags" value="<?php echo esc($curTags); ?>" placeholder="<?php echo __('例如 博客,教程,PHP'); ?>">

    <label><?php echo __('封面图（列表页缩略图，从正文中已上传的图片选择，留空自动取第一张）'); ?></label>
    <input type="hidden" name="cover_image" id="cover-image-input" value="<?php echo esc($val['cover_image']); ?>">
    <div id="cover-picker" class="cover-picker">
        <div class="cover-picker-grid" id="cover-picker-grid">
            <p class="muted cover-picker-empty"><?php echo __('正文中暂无已上传图片。上传图片后会自动出现在这里供选择。'); ?></p>
        </div>
    </div>

    <label><?php echo __('正文'); ?></label>
    <svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
        <!-- 经典线条图标集（编辑器工具栏共用，与论坛一致） -->
        <symbol id="ic-bold" viewBox="0 0 24 24"><path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/></symbol>
        <symbol id="ic-italic" viewBox="0 0 24 24"><path d="M10 4h8M6 20h8M14 4l-4 16"/></symbol>
        <symbol id="ic-strike" viewBox="0 0 24 24"><path d="M6 6h12M3 12h18M10 6l1 12M14 6l-1 12"/></symbol>
        <symbol id="ic-heading" viewBox="0 0 24 24"><path d="M6 4v16M18 4v16M6 12h12"/></symbol>
        <symbol id="ic-quote" viewBox="0 0 24 24"><path d="M10 11H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v6a4 4 0 0 1-4 4M20 11h-3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v6a4 4 0 0 1-4 4"/></symbol>
        <symbol id="ic-list" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></symbol>
        <symbol id="ic-list-ol" viewBox="0 0 24 24"><path d="M10 6h11M10 12h11M10 18h11"/><text x="3" y="9" font-size="7" font-weight="bold">1</text><text x="3" y="15" font-size="7" font-weight="bold">2</text><text x="3" y="21" font-size="7" font-weight="bold">3</text></symbol>
        <symbol id="ic-code" viewBox="0 0 24 24"><path d="M8 6l-6 6 6 6M16 6l6 6-6 6"/></symbol>
        <symbol id="ic-codeblock" viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM9.5 9l-3 3 3 3M14.5 9l3 3-3 3"/></symbol>
        <symbol id="ic-link" viewBox="0 0 24 24"><path d="M10 14a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07L11.5 5.43M14 10a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></symbol>
        <symbol id="ic-image" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 15l-5-5L8 18"/></symbol>
        <symbol id="ic-attach" viewBox="0 0 24 24"><path d="M21.4 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></symbol>
        <symbol id="ic-upload" viewBox="0 0 24 24"><path d="M12 16V4M6 10l6-6 6 6M4 20h16"/></symbol>
        <symbol id="ic-preview" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
    </svg>
    <div id="md-toolbar" class="md-toolbar" style="display:none">
        <button type="button" data-md="h" title="<?php echo __('标题'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-heading"/></svg></button>
        <button type="button" data-md="bold" title="<?php echo __('加粗'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-bold"/></svg></button>
        <button type="button" data-md="italic" title="<?php echo __('斜体'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-italic"/></svg></button>
        <button type="button" data-md="strike" title="<?php echo __('删除线'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-strike"/></svg></button>
        <button type="button" data-md="quote" title="<?php echo __('引用'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-quote"/></svg></button>
        <button type="button" data-md="ul" title="<?php echo __('列表'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-list"/></svg></button>
        <button type="button" data-md="ol" title="<?php echo __('有序列表'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-list-ol"/></svg></button>
        <button type="button" data-md="code" title="<?php echo __('代码'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-code"/></svg></button>
        <button type="button" data-md="link" title="<?php echo __('链接'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-link"/></svg></button>
        <button type="button" id="md-preview-btn" class="md-preview-btn" title="<?php echo __('预览'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-preview"/></svg></button>
    </div>
    <div class="md-upload-bar">
        <button type="button" id="md-upload-image" class="md-upload-image btn btn-ghost btn-sm"><svg class="ic" width="16" height="16"><use href="#ic-upload"/></svg> <?php echo __('上传图片'); ?></button>
        <button type="button" id="md-upload-file"  class="md-upload-file btn btn-ghost btn-sm"><svg class="ic" width="16" height="16"><use href="#ic-attach"/></svg> <?php echo __('上传附件'); ?></button>
        <span class="muted"><?php echo __('支持拖拽文件到下方文本框，或直接 Ctrl/Cmd+V 粘贴图片'); ?></span>
        <input type="file" id="upload-image-input" class="upload-image-input" accept="image/*" multiple style="display:none">
        <input type="file" id="upload-file-input"  class="upload-file-input" multiple style="display:none">
    </div>
    <textarea name="content" id="content-input" placeholder="<?php echo __('可以拖拽文件/粘贴图片到此处；保存时未引用的附件会自动清理…'); ?>"
              style="min-height:340px; font-family:ui-monospace,Consolas,monospace"><?php echo esc($val['content']); ?></textarea>
    <div id="upload-progress" class="upload-progress" style="display:none"></div>
    <div id="md-preview" class="md-preview" style="display:none"></div>

    <details open class="seo-box" style="margin-top:14px">
        <summary style="cursor:pointer;font-weight:600;color:var(--g-700)"><?php echo __('SEO 设置（摘要 / 描述 / 关键词）'); ?></summary>
        <label><?php echo __('文章摘要（excerpt，列表展示用，留空自动取正文）'); ?></label>
        <textarea name="excerpt" rows="1" class="seo-field" style="width:100%"><?php echo esc($val['excerpt']); ?></textarea>
        <label><?php echo __('SEO 描述（meta description，留空取摘要）'); ?></label>
        <textarea name="seo_description" rows="1" class="seo-field" style="width:100%"><?php echo esc($val['seo_description']); ?></textarea>
        <label><?php echo __('SEO 关键词（meta keywords，逗号分隔）'); ?></label>
        <input type="text" name="seo_keywords" class="seo-field" value="<?php echo esc($val['seo_keywords']); ?>" style="width:100%">
    </details>

    <input type="hidden" name="allow_comment" value="<?php echo ($val['allow_comment'] ?? 1) ? 1 : 0; ?>">

    <?php if ($editing): ?>
    <div style="margin-top:18px;padding:16px;background:var(--g-025);border:1px solid var(--line);border-radius:10px">
        <h3 style="margin:0 0 12px;color:var(--g-700);font-size:15px">📎 <?php echo __('附件管理'); ?></h3>
        <label><?php echo __('上传附件（可多选，最大'); ?> <?php echo round(getMaxUploadSize() / 1048576, 1); ?> MB / <?php echo __('文件）'); ?></label>
        <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.mp3,.mp4,.md" style="padding:8px;border:1px dashed var(--line);border-radius:8px;width:100%;background:#fff">
        <p class="muted" style="font-size:.82rem;margin:6px 0 0"><?php echo __('支持常见文档、图片、压缩包、音视频。点击保存后会上传。'); ?></p>
        <?php if ($atts): ?>
        <div style="margin-top:14px">
            <table class="uc-table" style="font-size:.88rem">
                <tr><th><?php echo __('文件名'); ?></th><th><?php echo __('大小'); ?></th><th>URL</th><th><?php echo __('操作'); ?></th></tr>
                <?php foreach ($atts as $a): ?>
                <tr>
                    <td><?php echo esc($a['filename']); ?></td>
                    <td><?php echo round($a['filesize']/1024, 1); ?> KB</td>
                    <td><a href="<?php echo baseUrl($a['filepath']); ?>" target="_blank"><?php echo __('查看'); ?></a></td>
                    <td><a href="<?php echo baseUrl('admin/attachments.php?del=' . $a['id'] . '&pid=' . $editing['id'] . '&_csrf=' . csrfToken()); ?>" onclick="return confirm('<?php echo __('删除附件？'); ?>')" style="color:#b3261e"><?php echo __('删除'); ?></a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else: ?>
            <p class="muted" style="font-size:.85rem;margin-top:10px"><?php echo __('暂无附件。'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p style="margin-top:18px">
        <button class="btn" type="submit"><?php echo __('保存'); ?><?php echo $editing ? __('（含附件）') : ''; ?></button>
        <a class="btn btn-ghost" href="<?php echo baseUrl('admin/posts.php'); ?>"><?php echo __('取消'); ?></a>
    </p>
    </div><!-- /pane-zh -->

    <?php if ($editing): ?>
    <div class="lang-pane" id="pane-en"<?php echo $editLang==='zh'?' style="display:none"':''; ?>>
        <div class="panel">
            <p class="muted" style="font-size:.9rem">
                <?php echo __('中文版：'); ?> <strong><?php echo esc($val['title']); ?></strong>
            </p>
            <label><?php echo __('英文标题 (title_en)'); ?></label>
            <input type="text" name="title_en" value="<?php echo esc($val['title_en'] ?? ''); ?>" placeholder="English title">
            <label><?php echo __('英文别名 (slug_en，/en 下 URL，留空自动用中文别名)'); ?></label>
            <input type="text" name="slug_en" value="<?php echo esc($val['slug_en'] ?? ''); ?>" placeholder="<?php echo __('留空 = 中文别名'); ?>">
            <label><?php echo __('英文正文 (content_en)'); ?></label>
            <div id="md-toolbar-en" class="md-toolbar" style="display:flex">
                <button type="button" data-md="h" title="<?php echo __('标题'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-heading"/></svg></button>
                <button type="button" data-md="bold" title="<?php echo __('加粗'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-bold"/></svg></button>
                <button type="button" data-md="italic" title="<?php echo __('斜体'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-italic"/></svg></button>
                <button type="button" data-md="strike" title="<?php echo __('删除线'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-strike"/></svg></button>
                <button type="button" data-md="quote" title="<?php echo __('引用'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-quote"/></svg></button>
                <button type="button" data-md="ul" title="<?php echo __('列表'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-list"/></svg></button>
                <button type="button" data-md="ol" title="<?php echo __('有序列表'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-list-ol"/></svg></button>
                <button type="button" data-md="code" title="<?php echo __('代码'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-code"/></svg></button>
                <button type="button" data-md="link" title="<?php echo __('链接'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-link"/></svg></button>
                <button type="button" class="md-preview-btn" title="<?php echo __('预览'); ?>"><svg class="ic" width="16" height="16"><use href="#ic-preview"/></svg></button>
            </div>
            <div class="md-upload-bar">
                <button type="button" class="md-upload-image btn btn-ghost btn-sm"><svg class="ic" width="16" height="16"><use href="#ic-upload"/></svg> <?php echo __('上传图片'); ?></button>
                <button type="button" class="md-upload-file btn btn-ghost btn-sm"><svg class="ic" width="16" height="16"><use href="#ic-attach"/></svg> <?php echo __('上传附件'); ?></button>
                <span class="muted"><?php echo __('支持拖拽/粘贴图片'); ?></span>
                <input type="file" class="upload-image-input" accept="image/*" multiple style="display:none">
                <input type="file" class="upload-file-input" multiple style="display:none">
            </div>
            <textarea name="content_en" id="content-en-input" rows="14" style="min-height:340px;font-family:ui-monospace,Consolas,monospace" placeholder="English content (markdown / HTML)"><?php echo esc($val['content_en'] ?? ''); ?></textarea>
            <div class="upload-progress" style="display:none"></div>
            <div class="md-preview" style="display:none"></div>
            <details open class="seo-box" style="margin-top:12px">
                <summary style="cursor:pointer;font-weight:600;color:var(--g-700)"><?php echo __('SEO 设置（英文）'); ?></summary>
                <label><?php echo __('英文摘要 (excerpt_en，列表展示)'); ?></label>
                <textarea name="excerpt_en" rows="1" class="seo-field" style="width:100%"><?php echo esc($val['excerpt_en'] ?? ''); ?></textarea>
                <label><?php echo __('英文 SEO 描述 (seo_description_en)'); ?></label>
                <textarea name="seo_description_en" rows="1" class="seo-field" style="width:100%"><?php echo esc($val['seo_description_en'] ?? ''); ?></textarea>
                <label><?php echo __('英文 SEO 关键词 (seo_keywords_en)'); ?></label>
                <input type="text" name="seo_keywords_en" class="seo-field" value="<?php echo esc($val['seo_keywords_en'] ?? ''); ?>" style="width:100%">
            </details>
            <p class="muted" style="font-size:.82rem"><?php echo __('留空则 /en 下自动回退显示中文（Drupal 式）。'); ?></p>
            <p style="margin-top:14px">
                <button class="btn" type="submit"><?php echo __('保存英文版'); ?></button>
                <a class="btn btn-ghost" href="<?php echo baseUrl('admin/posts.php'); ?>"><?php echo __('取消'); ?></a>
            </p>
        </div>
    </div><!-- /pane-en -->
    <?php endif; ?>
</form>
<script>
window.VERDA_PREVIEW  = '<?php echo baseUrl('admin/preview.php'); ?>';
window.VERDA_UPLOAD_URL = '<?php echo baseUrl('admin/upload-temp.php'); ?>';
document.querySelector('[name=allow_comment_on]').addEventListener('change', function(){
    document.querySelector('[name=allow_comment]').value = this.value;
});
// 页面内中/英切换：切 pane 显示 + 更新隐藏 lang 字段
document.querySelectorAll('#lang-tabs .lang-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        var lang = tab.dataset.lang;
        document.querySelectorAll('#lang-tabs .lang-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
        document.getElementById('pane-zh').style.display = lang === 'zh' ? '' : 'none';
        var en = document.getElementById('pane-en');
        if (en) en.style.display = lang === 'en' ? '' : 'none';
        document.getElementById('write-lang').value = lang;
    });
});
</script>
<script src="<?php echo baseUrl('assets/js/editor.js?v=' . (@filemtime(__DIR__ . '/../assets/js/editor.js') ?: '1')); ?>"></script>
<?php adminFoot();
