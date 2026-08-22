<?php
/** RyeBlog —— 独立页面 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/view.php';
if (!db()) { header('Location: ' . baseUrl('install.php')); exit; }
enforceLangPrefix();
enforceMaintenance();
pageCacheStart(); // 整页缓存（后台开关 page_cache；命中直接输出）

$slug = $_GET['p'] ?? '';
$page = getPostBySlugAnyType($slug);
if (!$page || $page['type'] !== 'page' || $page['status'] !== 'published') {
    http_response_code(404);
    publicHeader(__('页面不存在'));
    echo '<div class="empty-box"><p>' . __('没有找到这个页面。') . '</p></div>';
    publicFooter();
    exit;
}
if (isLoggedIn()) addTrail($_SESSION['rye_user'], $page);

// 主题模板：主题目录带 page.php 时独立页由主题模板渲染
$pageTpl = themeTemplate('page');
if ($pageTpl) {
    // 供模板使用的最小上下文（与 post.php 对齐）
    $GLOBALS['__rye_seo'] = [
        'desc'     => postSeoDescription($page),
        'keywords' => postSeoKeywords($page),
    ];
    $post = $page; $tags = getPostTags($page['id']);
    $rendered = renderContentWithToc(L($page, 'content'), $page['format'] ?? 'html');
    $tocList  = renderTocList($rendered['toc']);
    $comments = []; $prevPost = null; $nextPost = null; $msg = '';
    require $pageTpl;
    exit;
}

// 插件可完全接管此页面的渲染（如导航独立频道）。返回非空字符串则替换默认内容。
$pluginPageHtml = doHook('page_replace', $page);

// 页面 SEO meta（postSeo* 已语言感知：en 态优先 *_en，回退中文）
$GLOBALS['__rye_seo'] = [
    'desc'     => postSeoDescription($page),
    'keywords' => postSeoKeywords($page),
];

publicHeader(L($page, 'title'));
if ($pluginPageHtml !== '') {
    echo $pluginPageHtml;
} else {
?>
<section class="content-col">
    <article class="article">
        <h1><?php echo esc(L($page, 'title')); ?></h1>
        <div class="article-content"><?php echo renderContent(L($page, 'content'), $page['format'] ?? 'html'); ?></div>
    </article>
</section>
<?php
}
publicFooter();
