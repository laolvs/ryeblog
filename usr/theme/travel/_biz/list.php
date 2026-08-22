<?php
/**
 * RyeBlog 企业站主题 —— 共享列表模板（分类 / 标签 / 搜索）
 * 期望变量：$listTitle $listMeta $listItems $listTotal $listPage $listPages $listPageUrl(闭包)
 * 可选：$listMode —— 'card'（图文卡片，案例用）/ 缺省=传统列表（标题+摘要+日期行）
 * 由行业主题的 category.php / tag.php / search.php require。
 */
require_once __DIR__ . '/bootstrap.php';
biz_head($listTitle, $GLOBALS['__rye_seo']['desc'] ?? '');
biz_nav($listActive ?? '');
?>
<main class="biz-main">
    <div class="biz-container">
        <div class="biz-page-head">
            <h1><?php echo esc($listTitle); ?></h1>
            <?php if (!empty($listMeta)): ?><p class="muted"><?php echo esc($listMeta); ?></p><?php endif; ?>
        </div>
        <?php if (empty($listItems)): ?>
            <p class="biz-empty"><?php echo __('没有找到相关内容。'); ?></p>
        <?php elseif (($listMode ?? '') === 'card'): ?>
        <!-- 图文卡片（案例等） -->
        <div class="biz-post-list">
            <?php foreach ($listItems as $p): $cov = $p['cover_image'] ?? ($p['cover'] ?? ''); ?>
            <a class="biz-post-item" href="<?php echo postUrl($p); ?>">
                <?php if ($cov !== ''): ?>
                <img class="biz-post-cover" src="<?php echo esc($cov); ?>" alt="" loading="lazy">
                <?php endif; ?>
                <div class="biz-post-info">
                    <h3><?php echo esc(L($p, 'title')); ?></h3>
                    <?php if (!empty($p['excerpt'])): ?>
                    <p><?php echo esc(L($p, 'excerpt')); ?></p>
                    <?php endif; ?>
                    <span class="biz-post-date"><?php echo formatDate($p['created_at'], 'Y-m-d'); ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- 传统列表（新闻/文章） -->
        <div class="biz-list">
            <?php foreach ($listItems as $p): ?>
            <a class="biz-list-row" href="<?php echo postUrl($p); ?>">
                <div class="biz-list-main">
                    <span class="biz-list-title"><?php echo esc(L($p, 'title')); ?></span>
                    <?php if (!empty($p['excerpt'])): ?>
                    <span class="biz-list-desc"><?php echo esc(L($p, 'excerpt')); ?></span>
                    <?php endif; ?>
                </div>
                <span class="biz-list-date"><?php echo formatDate($p['created_at'], 'Y-m-d'); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($listPages > 1): ?>
        <nav class="biz-pagination">
            <?php for ($i = 1; $i <= $listPages; $i++): ?>
                <?php if ($i === $listPage): ?><span class="on"><?php echo $i; ?></span>
                <?php else: ?><a href="<?php echo $listPageUrl($i); ?>"><?php echo $i; ?></a><?php endif; ?>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>
</main>
<?php biz_footer();
