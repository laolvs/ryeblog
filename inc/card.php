<?php
/** RyeBlog —— 列表卡片共享渲染（首页/分类/标签/搜索/归档均使用） */
function renderPostCard($post, $opts = [])
{
    // 优先使用 getPosts(withTags) 批量加载的标签，避免每篇再查一次库（N+1）
    $tags = $opts['tags'] ?? ($post['tags'] ?? getPostTags($post['id']));
    $thumb = $post['cover_image'] ?? '';
    // 封面指向不存在的本地文件（usr/uploads/...）→ 视为无封面，防裂图
    if ($thumb !== '' && strpos($thumb, 'usr/uploads') !== false) {
        $fp = RYEBLOG_ROOT . '/' . ltrim($thumb, '/');
        if (!is_file($fp)) $thumb = '';
    }
    // 未设置封面时自动取正文第一张图（导入/老文章通用；本地文件不存在的跳过）
    if ($thumb === '' && !empty($post['content'])) {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)/i', $post['content'], $m)) {
            foreach ($m[1] as $u) {
                $u = html_entity_decode($u);
                // 本地路径必须存在，远程 URL 直接用第一个
                if (strpos($u, 'usr/uploads') !== false || strpos($u, '/usr/uploads') !== false) {
                    if (is_file(RYEBLOG_ROOT . '/' . ltrim($u, '/'))) { $thumb = $u; break; }
                } else {
                    $thumb = $u; break;
                }
            }
        }
    }
    // 仍无图 → 占位：后台可配自定义占位图（placeholder_image）；留空 = 绿色背景 + RyeBlog logo
    $placeholder = getOption('placeholder_image', '');
    if ($thumb === '' && $placeholder !== '') $thumb = $placeholder;
    $useLogoPh = ($thumb === '' && $placeholder === '');
    $url = postUrl($post);
    $cat = !empty($post['category_name'])
        ? '<span class="meta-item meta-cat"><i class="i-folder"></i><a href="' . categoryUrl(['slug' => $post['category_slug']]) . '">' . esc(L($post, 'category_name')) . '</a></span>'
        : '';
    ob_start(); ?>
<article class="post-card">
    <a class="post-thumb<?php echo $useLogoPh ? ' empty' : ''; ?>" href="<?php echo $url; ?>">
        <?php if ($useLogoPh): ?>
            <span class="thumb-placeholder"><img class="thumb-logo" src="<?php echo baseUrl('assets/img/logo-512.png'); ?>" alt="<?php echo esc(siteTitle()); ?>" loading="lazy"></span>
        <?php elseif ($thumb): ?>
            <img src="<?php echo esc($thumb); ?>" alt="<?php echo esc(L($post, 'title')); ?>" loading="lazy">
        <?php else: ?>
            <span class="thumb-placeholder">🌿</span>
        <?php endif; ?>
    </a>    <div class="post-body">
        <h2 class="post-title"><a href="<?php echo $url; ?>"><?php echo esc(L($post, 'title')); ?></a></h2>
        <p class="post-excerpt"><?php echo esc(postExcerpt($post)); ?></p>
        <?php if ($tags): ?>
        <div class="post-tags">
            <?php foreach ($tags as $t): ?>
                <a href="<?php echo tagUrl($t); ?>">#<?php echo esc(L($t, 'name')); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="post-footer-row">
            <div class="post-meta">
                <span class="meta-item"><i class="i-calendar"></i><?php echo formatDate($post['created_at']); ?></span>
                <span class="meta-item"><i class="i-user"></i><?php echo esc($post['author'] ?: 'admin'); ?></span>
                <span class="meta-item"><i class="i-eye"></i><?php echo (int)$post['views']; ?></span>
                <span class="meta-item"><i class="i-comment"></i><?php echo isset($post['comment_count']) ? (int)$post['comment_count'] : countComments($post['id']); ?> <?php echo __('评论'); ?></span>
                <?php echo $cat; ?>
            </div>
            <a class="read-more" href="<?php echo $url; ?>"><?php echo __('阅读全文 →'); ?></a>
        </div>
    </div>
</article>
<?php
    return ob_get_clean();
}
