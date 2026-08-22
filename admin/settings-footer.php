<?php
/**
 * RyeBlog 后台 —— 站点设置 · Footer 与备案
 */
require_once __DIR__ . '/admin.php';

$ok = '';
$keys = ['footer_copyright','footer_support','footer_support_en','footer_icp','footer_stats'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { http_response_code(403); exit('CSRF 校验失败'); }
    foreach ($keys as $k) if (isset($_POST[$k])) setOption($k, trim($_POST[$k]));
    $ok = __('已保存。');
}
function optVal($k, $d = '') { return esc(getOption($k, $d)); }
adminHead(__('Footer 与备案'), 'settings-footer.php');
?>
<h1>📋 <?php echo __('Footer 与备案'); ?></h1>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <h3 style="margin:0 0 10px;color:var(--g-700)"><?php echo __('Footer 与备案'); ?></h3>
    <label><?php echo __('版权信息（支持 {{year}} {{site}} 占位）'); ?></label>
    <input type="text" name="footer_copyright" value="<?php echo optVal('footer_copyright'); ?>">
    <label><?php echo __('程序支持信息（支持 HTML）'); ?></label>
    <input type="text" name="footer_support" value="<?php echo optVal('footer_support'); ?>">
    <?php if (bilingualEnabled()): ?>
    <label><?php echo __('程序支持信息（英文，/en 下显示）'); ?></label>
    <input type="text" name="footer_support_en" value="<?php echo optVal('footer_support_en'); ?>" placeholder="English footer support text">
    <?php endif; ?>
    <label><?php echo __('备案信息'); ?></label>
    <input type="text" name="footer_icp" value="<?php echo optVal('footer_icp'); ?>" placeholder="<?php echo __('如：京ICP备XXXXXXXX号'); ?>">
    <label><?php echo __('统计代码（HTML/JS，放页脚）'); ?></label>
    <textarea name="footer_stats" rows="3" style="width:100%"><?php echo optVal('footer_stats'); ?></textarea>
    <p style="margin-top:16px"><button class="btn" type="submit"><?php echo __('保存设置'); ?></button></p>
</form>
<?php adminFoot();