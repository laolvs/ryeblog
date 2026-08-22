<?php
/**
 * RyeBlog 后台 —— 站点设置 · 阅读与评论
 */
require_once __DIR__ . '/admin.php';

$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { http_response_code(403); exit('CSRF 校验失败'); }
    setOption('posts_per_page', trim($_POST['posts_per_page'] ?? '10'));
    setOption('comment_moderation', !empty($_POST['comment_moderation']) ? '1' : '0');
    setOption('localize_remote_images', !empty($_POST['localize_remote_images']) ? '1' : '0');
    $ok = __('已保存。');
}
function optVal($k, $d = '') { return esc(getOption($k, $d)); }
adminHead(__('阅读与评论'), 'settings-reading.php');
?>
<h1>📖 <?php echo __('阅读与评论'); ?></h1>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <h3 style="margin:0 0 10px;color:var(--g-700)"><?php echo __('阅读与评论'); ?></h3>
    <label><?php echo __('每页文章数'); ?></label>
    <input type="text" name="posts_per_page" value="<?php echo optVal('posts_per_page','10'); ?>">
    <label><?php echo __('评论审核'); ?></label>
    <select name="comment_moderation">
        <option value="1" <?php echo getOption('comment_moderation','1')==='1'?'selected':''; ?>><?php echo __('开启（评论需审核后显示）'); ?></option>
        <option value="0" <?php echo getOption('comment_moderation','1')==='0'?'selected':''; ?>><?php echo __('关闭（评论直接显示）'); ?></option>
    </select>
    <label style="display:flex;align-items:center;gap:8px;margin-top:12px;cursor:pointer">
        <input type="checkbox" name="localize_remote_images" value="1" <?php echo optVal('localize_remote_images','1')==='1'?'checked':''; ?>>
        <span><?php echo __('远程图片自动本地化'); ?><span class="muted" style="font-weight:400">（<?php echo __('写文章时把正文中的远程图片下载到本站 usr/uploads 并替换链接；默认开启'); ?>）</span></span>
    </label>
    <p style="margin-top:16px"><button class="btn" type="submit"><?php echo __('保存设置'); ?></button></p>
</form>
<?php adminFoot();