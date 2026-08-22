<?php
/**
 * RyeBlog 后台 —— 站点设置 · 开源与协议（新增）
 */
require_once __DIR__ . '/admin.php';

$ok = '';
$keys = [
    'rye_show_branding',       // 是否显示 RyeBlog 开源标识
    'rye_custom_credit',        // 自定义 footer 署名（留空=默认 Powered by RyeBlog）
    'rye_license',       //      // 协议（MIT/GPL-3.0/Apache-2.0/Proprietary）
    'rye_official_repo',        // 官方仓库/GitHub URL
    'rye_docs_url',             // 官方文档 URL
    'rye_market_url',           // 主题/插件市场 URL
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { http_response_code(403); exit('CSRF 校验失败'); }
    foreach ($keys as $k) if (isset($_POST[$k])) setOption($k, trim($_POST[$k]));
    $ok = __('已保存。');
}
function optVal($k, $d = '') { return esc(getOption($k, $d)); }
adminHead(__('开源与协议'), 'settings-license.php');
?>
<h1>📦 <?php echo __('开源与协议'); ?></h1>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <h3 style="margin:0 0 10px;color:var(--g-700)"><?php echo __('RyeBlog 开源标识'); ?></h3>
    <label><?php echo __('是否在页脚显示 RyeBlog 开源标识'); ?></label>
    <select name="rye_show_branding">
        <option value="1" <?php echo optVal('rye_show_branding','1')==='1'?'selected':''; ?>><?php echo __('显示（推荐）'); ?></option>
        <option value="0" <?php echo optVal('rye_show_branding','1')==='0'?'selected':''; ?>><?php echo __('隐藏'); ?></option>
    </select>
    <label><?php echo __('自定义 footer 署名（留空 = Powered by RyeBlog）'); ?></label>
    <input type="text" name="rye_custom_credit" value="<?php echo optVal('rye_custom_credit',''); ?>" placeholder="Powered by RyeBlog">
    <label><?php echo __('授权协议（展示在 footer）'); ?></label>
    <select name="rye_license">
        <option value="MIT" <?php echo optVal('rye_license','MIT')==='MIT'?'selected':''; ?>><?php echo __('MIT'); ?></option>
        <option value="GPL-3.0" <?php echo optVal('rye_license','MIT')==='GPL-3.0'?'selected':''; ?>><?php echo __('GPL-3.0'); ?></option>
        <option value="Apache-2.0" <?php echo optVal('rye_license','MIT')==='Apache-2.0'?'selected':''; ?>><?php echo __('Apache-2.0'); ?></option>
        <option value="BSD-3-Clause" <?php echo optVal('rye_license','MIT')==='BSD-3-Clause'?'selected':''; ?>><?php echo __('BSD-3-Clause'); ?></option>
        <option value="Proprietary" <?php echo optVal('rye_license','MIT')==='Proprietary'?'selected':''; ?>><?php echo __('Proprietary（商业）'); ?></option>
    </select>
    <h3 style="margin:18px 0 10px;color:var(--g-700)"><?php echo __('官方资源链接'); ?></h3>
    <label><?php echo __('官方仓库 / GitHub URL'); ?></label>
    <input type="text" name="rye_official_repo" value="<?php echo optVal('rye_official_repo','https://ryeblog.com/'); ?>" placeholder="https://github.com/...">
    <label><?php echo __('官方文档 URL'); ?></label>
    <input type="text" name="rye_docs_url" value="<?php echo optVal('rye_docs_url','https://ryeblog.com/docs.php'); ?>" placeholder="https://...">
    <label><?php echo __('主题 / 插件市场 URL'); ?></label>
    <input type="text" name="rye_market_url" value="<?php echo optVal('rye_market_url','https://ryeblog.com/cloud/'); ?>" placeholder="https://...">
    <p class="muted" style="margin-top:8px;font-size:.85rem"><?php echo __('以上链接在页脚「开源与协议」区块展示，方便访客快速找到项目源码与文档。'); ?></p>
    <p style="margin-top:16px"><button class="btn" type="submit"><?php echo __('保存设置'); ?></button></p>
</form>
<?php adminFoot();