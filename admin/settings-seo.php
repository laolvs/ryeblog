<?php
/**
 * RyeBlog 后台 —— 站点设置 · SEO
 */
require_once __DIR__ . '/admin.php';

$ok = '';
$keys = ['site_seo_description','site_seo_description_en','site_seo_keywords','site_seo_keywords_en'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { http_response_code(403); exit('CSRF 校验失败'); }
    foreach ($keys as $k) if (isset($_POST[$k])) setOption($k, trim($_POST[$k]));
    $ok = __('已保存。');
}
function optVal($k, $d = '') { return esc(getOption($k, $d)); }
adminHead(__('SEO'), 'settings-seo.php');
?>
<h1>🔎 <?php echo __('SEO'); ?></h1>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <h3 style="margin:0 0 10px;color:var(--g-700)"><?php echo __('站点 SEO（首页/默认 meta）'); ?></h3>
    <label><?php echo __('SEO 描述'); ?></label>
    <input type="text" name="site_seo_description" value="<?php echo optVal('site_seo_description',''); ?>" placeholder="<?php echo __('留空使用站点标语'); ?>">
    <?php if (bilingualEnabled()): ?>
    <label><?php echo __('SEO 描述（英文，/en 下显示）'); ?></label>
    <input type="text" name="site_seo_description_en" value="<?php echo optVal('site_seo_description_en',''); ?>" placeholder="English SEO description">
    <?php endif; ?>
    <label><?php echo __('SEO 关键词'); ?></label>
    <input type="text" name="site_seo_keywords" value="<?php echo optVal('site_seo_keywords',''); ?>" placeholder="关键词,逗号,分隔">
    <?php if (bilingualEnabled()): ?>
    <label><?php echo __('SEO 关键词（英文，/en 下显示）'); ?></label>
    <input type="text" name="site_seo_keywords_en" value="<?php echo optVal('site_seo_keywords_en',''); ?>" placeholder="keywords,comma,separated">
    <?php endif; ?>
    <p class="muted" style="margin-top:6px"><?php echo __('文章/页面/分类/标签页有各自专属 SEO 时优先使用；站点级作为首页与兜底描述。'); ?></p>
    <p style="margin-top:16px"><button class="btn" type="submit"><?php echo __('保存设置'); ?></button></p>
</form>
<?php adminFoot();