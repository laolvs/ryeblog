<?php
/**
 * RyeBlog 企业站主题 —— 共享独立页模板
 * 期望变量：$post $rendered（与核心 page.php 对齐）
 * 由行业主题的 page.php require。
 */
require_once __DIR__ . '/bootstrap.php';
biz_head(L($post, 'title'), $GLOBALS['__rye_seo']['desc'] ?? '');
biz_nav('page');
?>
<main class="biz-main">
    <div class="biz-container biz-article">
        <article>
            <h1 class="biz-article-title"><?php echo esc(L($post, 'title')); ?></h1>
            <div class="biz-article-content"><?php echo $rendered['html']; ?></div>
        </article>
    </div>
</main>
<?php biz_footer();
