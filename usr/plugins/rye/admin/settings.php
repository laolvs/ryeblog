<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台论坛设置
 * 路由：admin/plugin.php?p=rye&page=settings
 *
 * 读写 ryebbs_settings（setting_key / setting_value）。
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        // 更新已有/新增
        if (!empty($_POST['keys']) && is_array($_POST['keys'])) {
            foreach ($_POST['keys'] as $i => $k) {
                $k = trim($k);
                $v = $_POST['vals'][$i] ?? '';
                if ($k === '') continue;
                dbQuery("INSERT INTO {$P}settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=?", [$k, $v, $v]);
            }
        }
        // 删除被移除的（通过 hidden 列表比对较繁琐，这里仅支持新增与覆盖；删除走单行接口）
        set_flash('设置已保存', 'success');
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=settings'));
        exit;
    } elseif ($act === 'delete') {
        dbQuery("DELETE FROM {$P}settings WHERE setting_key=?", [$_POST['key'] ?? '']);
        set_flash('已删除该设置项', 'success');
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=settings'));
        exit;
    } elseif ($act === 'upload_save') {
        // 上传设置固定区块
        $up = [
            'upload_enabled'     => ($_POST['up_enabled'] ?? '') === '1' ? '1' : '0',
            'upload_max_size_mb' => (string) max(1, (int) ($_POST['up_max_mb'] ?? 5)),
            'upload_ext_images'  => trim((string) ($_POST['up_img_exts'] ?? 'jpg,jpeg,png,gif,webp')),
            'upload_ext_files'   => trim((string) ($_POST['up_file_exts'] ?? 'doc,docx,xls,xlsx,pdf,zip,rar,7z,txt,md')),
        ];
        foreach ($up as $k => $v) {
            dbQuery("INSERT INTO {$P}settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=?", [$k, $v, $v]);
        }
        set_flash('上传设置已保存', 'success');
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=settings'));
        exit;
    }
}

$rows = db_all("SELECT * FROM {$P}settings ORDER BY setting_key");
$flash = get_flash();

// 上传设置（固定区块专用读取，缺省用默认值）
$rows_by_key = [];
foreach ($rows as $r) { $rows_by_key[$r['setting_key']] = $r['setting_value']; }
$upEnabled = $rows_by_key['upload_enabled'] ?? '1';
$upMaxMb   = $rows_by_key['upload_max_size_mb'] ?? '5';
$upImgExts = $rows_by_key['upload_ext_images'] ?? 'jpg,jpeg,png,gif,webp';
$upFileExts= $rows_by_key['upload_ext_files'] ?? 'doc,docx,xls,xlsx,pdf,zip,rar,7z,txt,md';

adminHead('论坛设置 · RYE社区');
require __DIR__ . '/inc/admin_nav.php';
?>
<style>
.mt-admin-wrap{max-width:760px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-form label{display:block;margin:8px 0 3px;font-size:13px;color:#3a4a3e}
.mt-form input,.mt-form select,.mt-form textarea{width:100%;border:1px solid #cfd9c8;border-radius:8px;padding:8px;font:inherit}
.mt-row{display:flex;gap:8px;align-items:center;margin-bottom:6px}
.mt-row input{flex:1}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.muted{color:#8a968c}
code{background:#f3f7f0;padding:1px 5px;border-radius:4px}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2>上传设置</h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="upload_save">
            <label>允许上传（图片 / 附件）</label>
            <select name="up_enabled">
                <option value="1" <?php echo $upEnabled === '1' ? 'selected' : ''; ?>>开启</option>
                <option value="0" <?php echo $upEnabled !== '1' ? 'selected' : ''; ?>>关闭</option>
            </select>
            <label>单文件大小上限（MB）</label>
            <input type="number" name="up_max_mb" min="1" max="50" value="<?php echo e($upMaxMb); ?>">
            <label>允许的图片扩展名（逗号分隔）</label>
            <input type="text" name="up_img_exts" value="<?php echo e($upImgExts); ?>">
            <label>允许的附件扩展名（逗号分隔）</label>
            <input type="text" name="up_file_exts" value="<?php echo e($upFileExts); ?>">
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存上传设置</button></div>
            <p class="muted" style="margin:10px 0 0">上传的文件存放在 <code>usr/uploads/</code> 月度目录；论坛登录用户均可上传。关闭开关后前端「上传图片 / 上传附件」按钮会提示已关闭。</p>
        </form>
    </div>
    <div class="mt-card">
        <h2>论坛设置</h2>
        <form class="mt-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="save">
            <?php foreach ($rows as $r): ?>
                <label><?php echo e($r['setting_key']); ?></label>
                <div class="mt-row">
                    <input type="hidden" name="keys[]" value="<?php echo e($r['setting_key']); ?>">
                    <input type="text" name="vals[]" value="<?php echo e($r['setting_value']); ?>">
                    <form method="post" style="display:inline" onsubmit="return confirm('删除该设置项？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="key" value="<?php echo e($r['setting_key']); ?>"><button class="btn-sm danger" type="submit">删</button></form>
                </div>
            <?php endforeach; ?>
            <label style="margin-top:14px">新增设置项（key / value）</label>
            <div class="mt-row">
                <input type="text" name="keys[]" placeholder="setting_key">
                <input type="text" name="vals[]" placeholder="value">
            </div>
            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存设置</button></div>
        </form>
        <p class="muted" style="margin-top:12px">常用键：<code>site_name</code> 社区名 · <code>site_desc</code> 简介 · <code>forum_threads_per_page</code> 每页主题数 · <code>stats_enabled</code> 访问统计(1/0) · <code>auto_localize_images</code> 图片本地化(1/0)。</p>
    </div>
</div>
<?php adminFoot(); ?>
