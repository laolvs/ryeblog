<?php
/** RyeBlog —— 文章详情页 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
enforceLangPrefix();
enforceMaintenance();

if (isset($_GET['slug'])) {
    $post = getPost($_GET['slug'], true);
} else {
    $id = (int)($_GET['p'] ?? 0);
    // 数字参数优先按 slug 查（数字 slug 站点如维基镜像，前台列表/搜索链接均为 slug），
    // 查不到再按 id 查（兼容旧 id 直链）。
    // 若先按 id 查：数字 slug 恰与某条自增 id 撞号时，会命中别的内容（搜索点击错文）。
    $post = $id ? getPost((string)$id, true) : null;
    if (!$post && $id > 0) {
        $post = getPost($id);
    }
}
if (!$post) {
    http_response_code(404);
    publicHeader(__('文章不存在'));
    echo '<div class="empty-box"><p>' . __('没有找到这篇文章。') . '</p></div>';
    publicFooter();
    exit;
}
bumpViews($post['id']);
$tags = getPostTags($post['id']);
$atts = getAttachments($post['id']);

// 浏览轨迹（登录用户）
if (isLoggedIn()) {
    addTrail($_SESSION['rye_user'], $post);
}

$msg = '';
$actionMsg = '';

// 收藏切换
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!checkCsrf()) {
        $actionMsg = __('表单已失效，请重试。');
    } elseif (isLoggedIn()) {
        $u = currentUser();
        if ($_POST['action'] === 'favorite' && $u) {
            $fav = toggleFavorite($u['id'], $post['id']);
            $actionMsg = $fav ? __('已加入收藏。') : __('已取消收藏。');
        } elseif ($_POST['action'] === 'annotate' && $u) {
            $quote = trim($_POST['quote_text'] ?? '');
            $note  = trim($_POST['note'] ?? '');
            if ($quote !== '') {
                addAnnotation($u['id'], $post['id'], $quote, $note, '');
                $actionMsg = __('划线笔记已保存。');
            }
        } elseif ($_POST['action'] === 'correct' && $u) {
            $selected  = trim($_POST['selected_text'] ?? '');
            $suggested = trim($_POST['suggested_text'] ?? '');
            $reason    = trim($_POST['reason'] ?? '');
            if ($selected !== '' && $suggested !== '') {
                addCorrection($u['id'], $post['id'], $selected, $suggested, $reason);
                $actionMsg = __('纠错已提交，感谢您的贡献！');
            }
        }
    }
}

// 评论提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && ($post['allow_comment'] ?? 1)) {
    if (!checkCsrf()) {
        $msg = __('表单已失效，请重试。');
    } elseif (trim($_POST['author'] ?? '') === '' || trim($_POST['content'] ?? '') === '') {
        $msg = __('昵称和评论内容不能为空。');
    } elseif (!commentSpamCheck($_POST['content'], $_POST['website'] ?? '', $post['id'])) {
        $msg = __('评论内容包含不被允许的内容，请修改后重试。');
    } else {
        // 插件防垃圾检查：返回非空字符串 = 拒绝原因（如蜜罐/时间陷阱/链接数等）
        $spamMsg = trim((string)doHook('comment_check', $_POST));
        if ($spamMsg !== '') {
            $msg = $spamMsg;
        } else {
            addComment($post['id'], [
                'author'  => $_POST['author'],
                'email'   => $_POST['email'] ?? '',
                'website' => $_POST['website'] ?? '',
                'content' => $_POST['content'],
            ]);
            $msg = getOption('comment_moderation', '1') === '1' ? __('评论已提交，将于审核后显示。') : __('评论发布成功！');
            $_POST = [];
        }
    }
}
$comments = getComments($post['id']);
$isFav = isLoggedIn() ? isFavorited($_SESSION['rye_user'], $post['id']) : false;

// 渲染正文并抽取 Markdown 目录（en 态优先用 content_en，空则回退中文）
// 带文件缓存：键含 content 哈希，编辑后自动失效；文章页从每次全量解析变为读文件
$rendered = renderContentWithTocCached($post['id'], L($post, 'content'), $post['format'] ?? 'html');
$tocList  = renderTocList($rendered['toc']);

// 把 TOC 存入全局变量，由侧边栏模块系统按配置渲染
if ($tocList !== '') {
    $GLOBALS['__rye_toc_html'] = $tocList;
}

// 上一篇 / 下一篇（按主键 id 相邻，瞬时；百万级数据下按 created_at 排序会触发全表扫+filesort 超时）
// FORCE INDEX (PRIMARY)：强制走主键范围倒/顺序扫，防优化器偶发改走 (type,status,created_at) 做全表过滤+排序
$prevPost = dbOne("SELECT * FROM vd_posts FORCE INDEX (PRIMARY) WHERE type='post' AND status='published' AND id < ? ORDER BY id DESC LIMIT 1",
    [$post['id']]);
$nextPost = dbOne("SELECT * FROM vd_posts FORCE INDEX (PRIMARY) WHERE type='post' AND status='published' AND id > ? ORDER BY id ASC LIMIT 1",
    [$post['id']]);

// 主题模板：主题目录带 post.php 时文章页由主题模板渲染（文档式阅读）
// 位置在正文/TOC/评论数据全部就绪之后，模板可直接使用 $post/$tags/$atts/$comments/$rendered/$tocList/$prevPost/$nextPost
$postTpl = themeTemplate('post');
if ($postTpl) {
    require $postTpl;
    exit;
}

// 设置文章页 SEO meta（description + keywords）
$GLOBALS['__rye_seo'] = [
    'desc'     => postSeoDescription($post),
    'keywords' => postSeoKeywords($post),
];

publicHeader(L($post, 'title'));
?>
    <article class="article">
        <h1><?php echo esc(L($post, 'title')); ?></h1>
        <div class="post-meta">
            <span><?php echo esc($post['author'] ?: 'admin'); ?> · <?php echo formatDate($post['created_at']); ?></span>
            <?php if ($post['category_name']): ?>
                <a class="cat" href="<?php echo categoryUrl(['slug'=>$post['category_slug']]); ?>"><?php echo esc(L($post, 'category_name')); ?></a>
            <?php endif; ?>
            <span>👁 <?php echo (int)$post['views']; ?> <?php echo __('阅读'); ?></span>
            <?php if (isLoggedIn()): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="favorite">
                    <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--g-700);font:inherit;padding:0"><?php echo $isFav ? __('★ 已收藏') : __('♡ 收藏'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($actionMsg): ?>
            <div class="notice notice-ok" style="margin:12px 0"><?php echo esc($actionMsg); ?></div>
        <?php endif; ?>

        <div class="article-content" id="article-content"><?php echo $rendered['html']; ?></div>

        <?php echo doHook('afterArticleContent', $post); ?>

        <?php if ($tags): ?>
        <div class="post-tags" style="margin-top:18px">
            <?php foreach ($tags as $t): ?>
                <a href="<?php echo tagUrl($t); ?>">#<?php echo esc(L($t, 'name')); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($atts): ?>
        <div class="attachments" style="margin-top:20px">
            <h4><?php echo __('📎 本文附件'); ?></h4>
            <?php foreach ($atts as $a): ?>
                <a href="<?php echo baseUrl(ltrim($a['filepath'], '/')); ?>" target="_blank" download><?php echo esc($a['filename']); ?></a>
                <small class="muted"><?php echo __('（') . round($a['filesize']/1024, 1) . __(' KB）'); ?></small>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (isLoggedIn()): ?>
        <div class="reader-tools" style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('annotate-box').style.display='block';document.getElementById('correct-box').style.display='none'"><?php echo __('✏ 划线笔记'); ?></button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('correct-box').style.display='block';document.getElementById('annotate-box').style.display='none'"><?php echo __('🔍 纠错'); ?></button>
        </div>

        <div id="annotate-box" style="display:none;margin-top:12px">
            <form method="post" class="uc-panel uc-form" style="padding:18px">
                <h2 style="font-size:1rem;border:none;padding:0;margin:0 0 12px"><?php echo __('✏ 划线笔记'); ?></h2>
                <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="annotate">
                <label><?php echo __('选中的文字'); ?></label>
                <textarea name="quote_text" rows="2" required placeholder="<?php echo __('复制文章中要划线的文字到此处'); ?>"></textarea>
                <label><?php echo __('笔记（可选）'); ?></label>
                <textarea name="note" rows="2" placeholder="<?php echo __('写下你的笔记或想法'); ?>"></textarea>
                <div class="actions"><button class="btn btn-sm" type="submit"><?php echo __('保存划线'); ?></button></div>
            </form>
        </div>

        <div id="correct-box" style="display:none;margin-top:12px">
            <form method="post" class="uc-panel uc-form" style="padding:18px">
                <h2 style="font-size:1rem;border:none;padding:0;margin:0 0 12px"><?php echo __('🔍 提交纠错'); ?></h2>
                <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="correct">
                <label><?php echo __('原文内容'); ?></label>
                <textarea name="selected_text" rows="2" required placeholder="<?php echo __('复制文章中需要纠正的文字'); ?>"></textarea>
                <label><?php echo __('建议修改为'); ?></label>
                <textarea name="suggested_text" rows="2" required placeholder="<?php echo __('正确的文字内容'); ?>"></textarea>
                <label><?php echo __('纠错理由（可选）'); ?></label>
                <textarea name="reason" rows="2" placeholder="<?php echo __('说明纠错原因'); ?>"></textarea>
                <div class="actions"><button class="btn btn-sm" type="submit"><?php echo __('提交纠错'); ?></button></div>
            </form>
        </div>
        <?php endif; ?>

        <?php echo doHook('articleFooter', $post); ?>

        <?php
        if ($prevPost || $nextPost):
        ?>
        <nav class="post-nav">
            <?php if ($prevPost): ?>
                <a class="post-nav-prev" href="<?php echo esc(postUrl($prevPost)); ?>">
                    <span class="post-nav-label">← <?php echo __('上一篇'); ?></span>
                    <span class="post-nav-title"><?php echo esc(L($prevPost, 'title')); ?></span>
                </a>
            <?php else: ?><span class="post-nav-prev post-nav-empty"></span><?php endif; ?>
            <?php if ($nextPost): ?>
                <a class="post-nav-next" href="<?php echo esc(postUrl($nextPost)); ?>">
                    <span class="post-nav-label"><?php echo __('下一篇'); ?> →</span>
                    <span class="post-nav-title"><?php echo esc(L($nextPost, 'title')); ?></span>
                </a>
            <?php else: ?><span class="post-nav-next post-nav-empty"></span><?php endif; ?>
        </nav>
        <?php endif; ?>
    </article>

    <?php if ($post['allow_comment'] ?? 1): ?>
    <div class="comments" style="margin-top:30px">
        <h3><?php echo __('评论'); ?> (<?php echo count($comments); ?>)</h3>
        <?php if ($msg): ?><div class="notice notice-ok"><?php echo esc($msg); ?></div><?php endif; ?>
        <ul class="comment-list">
            <?php foreach ($comments as $c): ?>
                <li class="comment-item">
                    <div class="c-meta"><?php echo esc($c['author']); ?> · <?php echo formatDate($c['created_at'], 'Y-m-d H:i'); ?></div>
                    <div><?php echo nl2br(esc($c['content'])); ?></div>
                </li>
            <?php endforeach; ?>
            <?php if (empty($comments)): ?><li class="muted"><?php echo __('还没有评论，来抢沙发吧。'); ?></li><?php endif; ?>
        </ul>
        <form class="comment-form uc-form" method="post" style="margin-top:16px">
            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="comment" value="1">
            <?php echo doHook('comment_form_extra'); /* 防垃圾插件注入蜜罐/时间戳等 */ ?>
            <div class="form-row">
                <div>
                    <label><?php echo __('昵称 *'); ?></label>
                    <input type="text" name="author" value="<?php echo esc($_POST['author'] ?? (currentUser()['username'] ?? '')); ?>" required>
                </div>
                <div>
                    <label><?php echo __('邮箱（不公开）'); ?></label>
                    <input type="email" name="email" value="<?php echo esc($_POST['email'] ?? (currentUser()['email'] ?? '')); ?>">
                </div>
            </div>
            <label><?php echo __('个人网站'); ?></label>
            <input type="url" name="website" value="<?php echo esc($_POST['website'] ?? ''); ?>">
            <label><?php echo __('评论内容 *'); ?></label>
            <textarea name="content" rows="4" required><?php echo esc($_POST['content'] ?? ''); ?></textarea>
            <div class="actions"><button class="btn" type="submit"><?php echo __('发表评论'); ?></button></div>
        </form>
    </div>
    <?php endif; ?>
<?php publicFooter();
