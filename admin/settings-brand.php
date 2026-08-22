<?php
/**
 * RyeBlog 后台 —— 站点设置 · 品牌与主页
 * 品牌信息、Hero/文档主题、博主卡、侧边栏、占位图
 */
require_once __DIR__ . '/admin.php';

$ok = '';
$keys = [
    'site_title','site_title_en','site_slogan','site_slogan_en','site_url',
    'hero_logo',
    'home_hero',
    'hero_subtitle','hero_btn1_text','hero_btn1_url','hero_btn2_text','hero_btn2_url',
    'feature_1_title','feature_1_desc','feature_2_title','feature_2_desc','feature_3_title','feature_3_desc',
    'docs_section_title','docs_sidebar_title',
    'author_card_show','author_card_title','author_card_name','author_card_avatar','author_card_image','author_card_bio',
    'sidebar_sticky',
    'placeholder_image',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { http_response_code(403); exit('CSRF 校验失败'); }
    foreach ($keys as $k) if (isset($_POST[$k])) setOption($k, trim($_POST[$k]));
    $ok = __('已保存。');
}
function optVal($k, $d = '') { return esc(getOption($k, $d)); }
adminHead(__('品牌与主页'), 'settings-brand.php');
?>
<h1>🎨 <?php echo __('品牌与主页'); ?></h1>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<form class="panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <h3 style="margin:0 0 10px;color:var(--g-700)"><?php echo __('品牌信息'); ?></h3>
    <label><?php echo __('站点名称'); ?></label>
    <input type="text" name="site_title" value="<?php echo optVal('site_title','RyeBlog'); ?>">
    <?php if (bilingualEnabled()): ?>
    <label><?php echo __('英文站点名称（/en 下显示）'); ?></label>
    <input type="text" name="site_title_en" value="<?php echo optVal('site_title_en',''); ?>" placeholder="English site name">
    <?php endif; ?>
    <label><?php echo __('站点标语'); ?></label>
    <input type="text" name="site_slogan" value="<?php echo optVal('site_slogan','免费开源的中英文博客系统！'); ?>">
    <?php if (bilingualEnabled()): ?>
    <label><?php echo __('英文站点标语（/en 下显示）'); ?></label>
    <input type="text" name="site_slogan_en" value="<?php echo optVal('site_slogan_en',''); ?>" placeholder="English slogan">
    <?php endif; ?>
    <label><?php echo __('官方网址'); ?></label>
    <input type="text" name="site_url" value="<?php echo optVal('site_url','https://ryeblog.com/'); ?>">
    <label><?php echo __('品牌 Logo（顶部 / Hero / favicon）'); ?></label>
    <div class="upload-row">
        <input type="text" name="hero_logo" id="hero_logo_input" value="<?php echo optVal('hero_logo'); ?>" placeholder="<?php echo __('留空 = RyeBlog 默认 logo；建议放主题 assets 下不会被核心部署覆盖'); ?>" style="flex:1">
        <label class="btn btn-ghost btn-sm" style="cursor:pointer">📷 <?php echo __('上传 Logo'); ?>
            <input type="file" data-upload-to="hero_logo_input" data-preview="hero_logo_preview" accept="image/*" style="display:none">
        </label>
    </div>
    <div id="hero_logo_preview" style="margin:6px 0"><?php $lg = trim(getOption('hero_logo','')); if ($lg !== '') echo '<img src="' . esc(baseUrl($lg)) . '" style="height:48px;border:1px solid var(--line);object-fit:contain;background:#fff;border-radius:4px">'; ?></div>

    <label><?php echo __('首页宣传区（Hero）'); ?></label>
    <select name="home_hero">
        <option value="1" <?php echo getOption('home_hero','1')==='1'?'selected':''; ?>><?php echo __('显示（RyeBlog 宣传横幅）'); ?></option>
        <option value="0" <?php echo getOption('home_hero','1')==='0'?'selected':''; ?>><?php echo __('隐藏（博客列表直接展示）'); ?></option>
    </select>

    <h3 style="margin:18px 0 10px;color:var(--g-700)">📚 <?php echo __('文档主题 Hero（Vuecho）'); ?></h3>
    <p class="muted" style="font-size:.85rem;margin:0 0 6px"><?php echo __('启用 Vuecho 文档主题后生效；以下内容留空则隐藏对应区块。'); ?></p>
    <label><?php echo __('Hero 副标题'); ?></label>
    <input type="text" name="hero_subtitle" value="<?php echo optVal('hero_subtitle','从零开始，一起学习！'); ?>">
    <div class="row">
        <div><label><?php echo __('主按钮文字'); ?></label><input type="text" name="hero_btn1_text" value="<?php echo optVal('hero_btn1_text','快速上手'); ?>"></div>
        <div><label><?php echo __('主按钮链接'); ?></label><input type="text" name="hero_btn1_url" value="<?php echo optVal('hero_btn1_url',''); ?>" placeholder="<?php echo __('留空=首页'); ?>"></div>
    </div>
    <div class="row">
        <div><label><?php echo __('次按钮文字'); ?></label><input type="text" name="hero_btn2_text" value="<?php echo optVal('hero_btn2_text',''); ?>"></div>
        <div><label><?php echo __('次按钮链接'); ?></label><input type="text" name="hero_btn2_url" value="<?php echo optVal('hero_btn2_url',''); ?>" placeholder="<?php echo __('留空=首页'); ?>"></div>
    </div>
    <?php for ($i = 1; $i <= 3; $i++): ?>
    <div class="row" style="margin-top:8px">
        <div><label><?php echo __('特性 ' . $i . ' 标题'); ?></label><input type="text" name="feature_<?php echo $i; ?>_title" value="<?php echo optVal("feature_{$i}_title",''); ?>"></div>
        <div><label><?php echo __('特性 ' . $i . ' 描述'); ?></label><input type="text" name="feature_<?php echo $i; ?>_desc" value="<?php echo optVal("feature_{$i}_desc",''); ?>"></div>
    </div>
    <?php endfor; ?>
    <label><?php echo __('学习目录标题（首页）'); ?></label>
    <input type="text" name="docs_section_title" value="<?php echo optVal('docs_section_title','学习目录'); ?>">
    <label><?php echo __('文档侧栏标题（文章页左侧）'); ?></label>
    <input type="text" name="docs_sidebar_title" value="<?php echo optVal('docs_sidebar_title','学习目录'); ?>">

    <h3 style="margin:18px 0 10px;color:var(--g-700)"><?php echo __('博主信息卡（仅 PC 首页显示）'); ?></h3>
    <label><?php echo __('是否显示'); ?></label>
    <select name="author_card_show">
        <option value="1" <?php echo getOption('author_card_show','1')==='1'?'selected':''; ?>><?php echo __('显示'); ?></option>
        <option value="0" <?php echo getOption('author_card_show','1')==='0'?'selected':''; ?>><?php echo __('隐藏'); ?></option>
    </select>
    <label><?php echo __('卡片标题'); ?></label>
    <input type="text" name="author_card_title" value="<?php echo optVal('author_card_title','关于博主'); ?>">
    <label><?php echo __('博主名称'); ?></label>
    <input type="text" name="author_card_name" value="<?php echo optVal('author_card_name','博主'); ?>">
    <label><?php echo __('头像'); ?></label>
    <div class="upload-row">
        <input type="text" name="author_card_avatar" id="author_card_avatar" value="<?php echo optVal('author_card_avatar'); ?>" placeholder="https://… <?php echo __('或'); ?> /usr/uploads/xxx.jpg" style="flex:1">
        <label class="btn btn-ghost btn-sm" style="cursor:pointer">📷 <?php echo __('上传头像'); ?>
            <input type="file" data-upload-to="author_card_avatar" data-preview="author_card_avatar_preview" accept="image/*" style="display:none">
        </label>
    </div>
    <div id="author_card_avatar_preview" style="margin:6px 0"><?php $av = trim(getOption('author_card_avatar','')); if ($av !== '') echo '<img src="' . esc(baseUrl($av)) . '" style="width:64px;height:64px;border-radius:50%;border:1px solid var(--line);object-fit:cover">'; ?></div>
    <label><?php echo __('横幅背景图'); ?></label>
    <div class="upload-row">
        <input type="text" name="author_card_image" id="author_card_image" value="<?php echo optVal('author_card_image'); ?>" placeholder="https://… <?php echo __('或'); ?> /usr/uploads/banner.jpg" style="flex:1">
        <label class="btn btn-ghost btn-sm" style="cursor:pointer">🖼 <?php echo __('上传横幅'); ?>
            <input type="file" data-upload-to="author_card_image" data-preview="author_card_image_preview" accept="image/*" style="display:none">
        </label>
    </div>
    <div id="author_card_image_preview" style="margin:6px 0"><?php $im = trim(getOption('author_card_image','')); if ($im !== '') echo '<img src="' . esc(baseUrl($im)) . '" style="width:240px;height:80px;border-radius:8px;border:1px solid var(--line);object-fit:cover">'; ?></div>
    <label><?php echo __('简介'); ?></label>
    <textarea name="author_card_bio" rows="2" style="width:100%"><?php echo optVal('author_card_bio'); ?></textarea>

    <h3 style="margin:18px 0 10px;color:var(--g-700)"><?php echo __('侧边栏与占位图'); ?></h3>
    <label><?php echo __('侧边栏固定（滚动时跟随，仅桌面端）'); ?></label>
    <select name="sidebar_sticky">
        <option value="0" <?php echo getOption('sidebar_sticky','0')==='0'?'selected':''; ?>><?php echo __('不固定（默认）'); ?></option>
        <option value="1" <?php echo getOption('sidebar_sticky','0')==='1'?'selected':''; ?>><?php echo __('固定'); ?></option>
    </select>
    <label><?php echo __('文章占位图 URL（无封面时显示）'); ?></label>
    <input type="text" name="placeholder_image" value="<?php echo optVal('placeholder_image',''); ?>" placeholder="<?php echo __('留空 = RyeBlog logo'); ?>">

    <p style="margin-top:16px"><button class="btn" type="submit"><?php echo __('保存设置'); ?></button></p>
</form>
<style>.upload-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }</style>
<script>
window.VERDA_UPLOAD_URL = '<?php echo baseUrl('admin/upload-temp.php'); ?>';
(function () {
    var csrf = document.querySelector('form [name=_csrf]').value;
    document.querySelectorAll('input[type=file][data-upload-to]').forEach(function (file) {
        file.addEventListener('change', function () {
            if (!file.files || !file.files.length) return;
            var targetId = file.getAttribute('data-upload-to');
            var previewId = file.getAttribute('data-preview');
            var target = document.getElementById(targetId);
            var preview = document.getElementById(previewId);
            var fd = new FormData();
            fd.append('file', file.files[0]);
            fd.append('_csrf', csrf);
            file.disabled = true;
            fetch(window.VERDA_UPLOAD_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    file.disabled = false; file.value = '';
                    if (j.error) { alert('<?php echo __('上传失败：'); ?>' + j.error); return; }
                    var rel = j.filepath;
                    if (target) target.value = rel;
                    if (preview) {
                        preview.innerHTML = '<img src="' + j.url + '" style="' + (previewId.indexOf('image') >= 0 ? 'width:240px;height:80px' : 'width:64px;height:64px;border-radius:50%') + ';border:1px solid var(--line);object-fit:cover">';
                    }
                })
                .catch(function (err) { file.disabled = false; file.value = ''; alert('<?php echo __('上传异常：'); ?>' + err); });
        });
    });
})();
</script>
<?php adminFoot();