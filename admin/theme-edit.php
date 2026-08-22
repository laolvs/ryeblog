<?php
/**
 * RyeBlog 后台 —— 主题编辑（Doc 文档主题等自定义主题）
 * 功能：外观文案配置（站点设置字段）+ 主题文件在线编辑（白名单 + PHP 语法校验）
 */
require_once __DIR__ . '/admin.php';

$theme = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['theme'] ?? currentTheme());
$dir = RYEBLOG_ROOT . '/usr/theme/' . $theme;
if (!is_dir($dir) || !is_file($dir . '/theme.css')) {
    header('Location: themes.php'); exit;
}

// 可编辑文件白名单
$EDITABLE = [
    'home.php'      => '首页模板',
    'post.php'      => '文章/独立页模板',
    'list_common.php' => '列表页公共骨架（分类/标签/搜索）',
    'category.php'  => '分类页模板',
    'tag.php'       => '标签页模板',
    'search.php'    => '搜索页模板',
    'theme.css'     => '主题样式表',
    'theme.js'      => '主题交互脚本',
    'README.md'     => '主题说明文档',
];

// 外观可配置字段（getOption/setOption，即站点设置中的「首页宣传区（文档主题）」）
$APPEAR = [
    'hero_title'        => '首页 Hero 大标题（默认=站点名称）',
    'hero_subtitle'     => '首页 Hero 副标题',
    'hero_btn1_text'    => '主按钮文字（如：快速上手）',
    'hero_btn1_url'     => '主按钮链接',
    'hero_btn2_text'    => '次按钮文字',
    'hero_btn2_url'     => '次按钮链接',
    'feature_1_title'   => '特性 1 标题',
    'feature_1_desc'    => '特性 1 描述',
    'feature_2_title'   => '特性 2 标题',
    'feature_2_desc'    => '特性 2 描述',
    'feature_3_title'   => '特性 3 标题',
    'feature_3_desc'    => '特性 3 描述',
    'docs_section_title'=> '学习目录标题（首页）',
    'docs_sidebar_title'=> '文档侧栏标题（文章页左侧）',
];

$msg = $err = '';
$tab = $_GET['tab'] ?? 'appear';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } else {
        $tab = $_POST['tab'] ?? 'appear';
        if ($tab === 'appear') {
            foreach ($APPEAR as $key => $label) {
                if (isset($_POST[$key])) setOption($key, trim($_POST[$key]));
            }
            $msg = __('外观配置已保存。');
        } elseif ($tab === 'file') {
            $file = basename($_POST['file'] ?? '');
            if (!isset($EDITABLE[$file])) {
                $err = __('不允许编辑该文件。');
            } else {
                $path = $dir . '/' . $file;
                $content = (string)($_POST['content'] ?? '');
                // PHP 文件保存前做语法校验
                if (substr($file, -4) === '.php') {
                    $tmp = tempnam(sys_get_temp_dir(), 'rye');
                    file_put_contents($tmp, $content);
                    $out = $errs = '';
                    $rc = 0;
                    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
                    @unlink($tmp);
                    $lint = implode("\n", $out);
                    if ($rc !== 0) {
                        $err = __('PHP 语法校验未通过，未保存：') . "\n" . $lint;
                    } else {
                        if (file_put_contents($path, $content) === false) {
                            $err = __('文件写入失败（请检查目录权限）。');
                        } else {
                            $msg = __('文件「') . $file . __('」已保存（版本号已自动更新，刷新页面即可看到效果）。');
                        }
                    }
                } else {
                    if (file_put_contents($path, $content) === false) {
                        $err = __('文件写入失败（请检查目录权限）。');
                    } else {
                        $msg = __('文件「') . $file . __('」已保存（版本号已自动更新，刷新页面即可看到效果）。');
                    }
                }
            }
        }
    }
}

$meta = ['title' => $theme, 'desc' => ''];
$src = file_get_contents($dir . '/theme.css');
if (preg_match('/@Title\s+(.+)/', $src, $m)) $meta['title'] = trim($m[1]);
if (preg_match('/@Desc\s+(.+)/',  $src, $m)) $meta['desc']  = trim($m[1]);

adminHead(__('主题编辑：') . $meta['title'], 'theme-edit.php');
?>
<h1>🎨 <?php echo __('主题编辑：'); ?><?php echo esc($meta['title']); ?>
    <small class="muted" style="font-weight:400;font-size:.85rem">(<?php echo esc($theme); ?>)</small>
</h1>
<p class="muted" style="margin-top:-6px"><?php echo esc($meta['desc']); ?></p>

<?php if ($msg): ?><div class="notice notice-ok"><?php echo nl2br(esc($msg)); ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice notice-err"><?php echo nl2br(esc($err)); ?></div><?php endif; ?>

<div style="display:flex;gap:8px;margin:14px 0">
    <a href="?theme=<?php echo esc($theme); ?>&tab=appear" class="btn <?php echo $tab==='appear'?'':'btn-ghost'; ?> btn-sm"><?php echo __('外观配置'); ?></a>
    <a href="?theme=<?php echo esc($theme); ?>&tab=file" class="btn <?php echo $tab==='file'?'':'btn-ghost'; ?> btn-sm"><?php echo __('文件编辑'); ?></a>
    <a href="themes.php" class="btn btn-ghost btn-sm">← <?php echo __('返回主题'); ?></a>
</div>

<?php if ($tab === 'appear'): ?>
<form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="tab" value="appear">
    <h3 style="margin:0 0 10px;color:var(--g-700)">📝 <?php echo __('首页 / 文档外观文案'); ?></h3>
    <p class="muted" style="font-size:.85rem;margin:0 0 10px"><?php echo __('这些配置与「站点设置 → 首页宣传区（文档主题）」一致，修改后立即生效（无需保存整站设置）。'); ?></p>
    <?php foreach ($APPEAR as $key => $label): ?>
    <label><?php echo esc($label); ?></label>
    <input type="text" name="<?php echo esc($key); ?>" value="<?php echo esc(getOption($key, '')); ?>">
    <?php endforeach; ?>
    <button class="btn" type="submit"><?php echo __('保存外观配置'); ?></button>
</form>
<?php else: ?>
<form class="panel" method="post" onsubmit="return confirm('<?php echo __('确定保存该文件？请确保修改正确，语法校验失败将不会保存。'); ?>')">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="tab" value="file">
    <h3 style="margin:0 0 10px;color:var(--g-700)">📄 <?php echo __('主题文件编辑'); ?></h3>
    <p class="muted" style="font-size:.85rem;margin:0 0 10px"><?php echo __('选择文件进行编辑。PHP 文件保存前会自动做语法校验，校验失败不会写入。'); ?></p>
    <label><?php echo __('选择文件'); ?></label>
    <select name="file" id="theme-file-select" onchange="window.location='?theme=<?php echo esc($theme); ?>&tab=file&file='+this.value">
        <?php foreach ($EDITABLE as $f => $desc): ?>
        <option value="<?php echo esc($f); ?>" <?php echo ($_GET['file'] ?? 'home.php') === $f ? 'selected' : ''; ?>><?php echo esc($f); ?> — <?php echo esc($desc); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
    $curFile = isset($EDITABLE[$_GET['file'] ?? '']) ? ($_GET['file']) : 'home.php';
    $curPath = $dir . '/' . $curFile;
    $curContent = is_file($curPath) ? file_get_contents($curPath) : '';
    ?>
    <label><?php echo __('文件内容'); ?>：<code><?php echo esc($curFile); ?></code>
        <span class="muted">（<?php echo esc($EDITABLE[$curFile]); ?>）</span></label>
    <textarea name="content" rows="22" style="width:100%;font-family:ui-monospace,Consolas,monospace;font-size:12.5px;white-space:pre" spellcheck="false"><?php echo esc($curContent); ?></textarea>
    <button class="btn" type="submit"><?php echo __('保存文件'); ?></button>
    <button class="btn btn-ghost" type="button" onclick="if(confirm('<?php echo __('放弃修改并刷新？'); ?>'))window.location.reload()"><?php echo __('放弃'); ?></button>
</form>
<?php endif; ?>
<?php adminFoot();
