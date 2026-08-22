<?php
/**
 * RYE社区 —— 后台内部子导航（tab 条）
 * 在各后台页 adminHead() 之后 require 本文件；当前页通过 ?page= 高亮。
 * 侧边栏「内容」组只注入一个「RYE社区」入口（见 Plugin::admin_menu_content()），
 * 论坛自身的 10 个管理页靠本 tab 条切换，遵循「核心导航只放核心功能」的约定。
 */
$__cur = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['page'] ?? '');
$__items = [
    'stats'           => '📊 统计',
    'forums'          => '🗂 版块',
    'content'         => '📝 主题',
    'users'           => '👥 用户',
    'medals'          => '🏅 勋章',
    'reports'         => '🚩 举报',
    'sensitive_words' => '🔤 敏感词',
    'ip_bans'         => '⛔ IP 封禁',
    'invite_codes'    => '🎟 邀请码',
    'settings'        => '⚙️ 设置',
];
$__base = baseUrl('admin/plugin.php?p=rye&page=');
?>
<style>
.rye-admin-tabs{max-width:980px;margin:0 auto;padding:14px 18px 0;display:flex;flex-wrap:wrap;gap:6px}
.rye-admin-tabs a{display:inline-block;padding:6px 13px;border-radius:999px;border:1px solid #cfd9c8;color:#3a4a3e;font-size:13px;text-decoration:none;background:#fff;transition:all .15s}
.rye-admin-tabs a:hover{border-color:#2c5234;color:#1f3d24}
.rye-admin-tabs a.active{background:#11603a;border-color:#11603a;color:#fff}
</style>
<nav class="rye-admin-tabs">
    <?php foreach ($__items as $k => $label): ?>
        <a class="<?php echo $__cur === $k ? 'active' : ''; ?>" href="<?php echo esc($__base . $k); ?>"><?php echo esc($label); ?></a>
    <?php endforeach; ?>
</nav>
