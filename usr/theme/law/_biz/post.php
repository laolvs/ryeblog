<?php
/**
 * RyeBlog 企业站主题 —— 共享文章详情模板
 * 期望变量：$post $tags $rendered $tocList $prevPost $nextPost $comments（与核心 post.php 对齐）
 * 由行业主题的 post.php require。
 */
require_once __DIR__ . '/bootstrap.php';
biz_head(L($post, 'title'), $GLOBALS['__rye_seo']['desc'] ?? '');
biz_nav('post');
?>
<main class="biz-main">
    <div class="biz-container biz-article">
        <article>
            <h1 class="biz-article-title"><?php echo esc(L($post, 'title')); ?></h1>
            <div class="biz-article-meta">
                <span><?php echo esc($post['author'] ?: 'admin'); ?></span>
                <span><?php echo formatDate($post['created_at']); ?></span>
                <?php if (!empty($post['category_name'])): ?>
                <a class="biz-article-cat" href="<?php echo categoryUrl(['slug' => $post['category_slug']]); ?>"><?php echo esc(L($post, 'category_name')); ?></a>
                <?php endif; ?>
            </div>
            <div class="biz-article-content"><?php echo $rendered['html']; ?></div>
        </article>
        <nav class="biz-article-pager">
            <?php if ($prevPost): ?><a href="<?php echo postUrl($prevPost); ?>">← <?php echo esc(L($prevPost, 'title')); ?></a><?php endif; ?>
            <?php if ($nextPost): ?><a href="<?php echo postUrl($nextPost); ?>"><?php echo esc(L($nextPost, 'title')); ?> →</a><?php endif; ?>
        </nav>
    </div>
</main>
<?php biz_footer();
