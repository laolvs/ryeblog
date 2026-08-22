<?php
/**
 * RyeBlog 后台 —— 主题管理
 * 功能：列表(内置+自定义)、预览卡片、激活、上传ZIP安装、删除
 */
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/../inc/cloud.php';

$msg = $err = '';
$cloudMsg = $cloudErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'cloud_install' || $action === 'cloud_update') {
            // 云端安装/更新主题
            $cname = basename(trim($_POST['cname'] ?? ''));
            $manifest = cloudFetchManifest(true);
            if (!$manifest['ok']) {
                $cloudErr = $manifest['msg'];
            } else {
                $pkg = null;
                foreach ($manifest['data']['themes'] as $p) {
                    if ($p['name'] === $cname) { $pkg = $p; break; }
                }
                if (!$pkg) {
                    $cloudErr = __('云端未找到该主题：') . esc($cname);
                } elseif ($action === 'cloud_install') {
                    $r = cloudInstall('theme', $pkg);
                    if ($r['ok']) $cloudMsg = $r['msg'];
                    else $cloudErr = $r['msg'];
                } else {
                    $r = cloudUpdate('theme', $pkg);
                    if ($r['ok']) $cloudMsg = $r['msg'];
                    else $cloudErr = $r['msg'];
                }
            }
        } elseif ($action === 'cloud_refresh') {
            $manifest = cloudFetchManifest(true);
            if ($manifest['ok']) $cloudMsg = __('云端仓库已刷新。');
            else $cloudErr = $manifest['msg'];
        } elseif ($action === 'activate') {
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['theme'] ?? '');
            $r = activateTheme($name);
            if ($r === true) $msg = __('主题「') . $name . __('」已激活。');
            else $err = $r;

        } elseif ($action === 'delete') {
            $name = basename($_POST['theme'] ?? '');
            $r = deleteTheme($name);
            if ($r === true) $msg = __('主题「') . $name . __('」已删除。');
            else $err = $r;

        } elseif ($action === 'install_zip') {
            if (!isset($_FILES['zipfile']) || $_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) {
                $err = __('请选择 ZIP 文件。');
            } else {
                $r = installThemeZip($_FILES['zipfile']['tmp_name']);
                if ($r['ok']) $msg = __('主题「') . ($r['dir'] ?? '') . __('」安装成功，请在下方激活。');
                else $err = $r['msg'] ?? __('安装失败。');
            }
        }
    }
}

$themes = scanThemes();
adminHead(__('主题管理'), 'themes.php');
?>
<h1><?php echo __('主题管理'); ?></h1>
<?php if ($msg): ?><div class="notice notice-ok"><?php echo esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>
<?php if ($cloudMsg): ?><div class="notice notice-ok">✅ <?php echo esc($cloudMsg); ?></div><?php endif; ?>
<?php if ($cloudErr): ?><div class="notice notice-err"><?php echo esc($cloudErr); ?></div><?php endif; ?>

<?php
$manifest = cloudFetchManifest();
// 构造 cloud 主题名 → 截图 URL 的映射（供下方"已安装主题"列表复用）
$cloudShotMap = [];
if ($manifest['ok'] && !empty($manifest['data']['themes'])) {
    foreach ($manifest['data']['themes'] as $ct) {
        if (!empty($ct['screenshot'])) $cloudShotMap[$ct['name']] = $ct['screenshot'];
    }
}
?>
<!-- 云端仓库 -->
<div class="panel" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0;color:var(--g-700)">☁️ <?php echo __('云端主题'); ?>
            <small class="muted" style="font-weight:400">(<code><?php echo esc(cloudRepoUrl()); ?></code>)</small>
        </h3>
        <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="cloud_refresh">
            <button class="btn btn-ghost btn-sm" type="submit">🔄 <?php echo __('刷新'); ?></button>
        </form>
    </div>
    <?php
    if (!cloudEnabled()):
        echo '<p class="muted">' . __('云端市场未启用，请在「站点设置 → 云端市场」中开启。') . '</p>';
    elseif (!$manifest['ok']):
        echo '<div class="notice notice-err">' . esc($manifest['msg']) . '</div>';
    elseif (empty($manifest['data']['themes'])):
        echo '<p class="muted">' . __('云端暂无可用的主题。') . '</p>';
    else: ?>
    <div class="cloud-grid">
        <?php foreach ($manifest['data']['themes'] as $ct):
            $st = cloudStatus('theme', $ct);
            $local = cloudLocalVersion('theme', $ct['name']);
            $installed = cloudIsInstalled('theme', $ct['name']);
            $primary = '#94a3b8';
            $cssFile = RYEBLOG_ROOT . '/usr/theme/' . $ct['name'] . '/theme.css';
            if (is_file($cssFile) && preg_match('/--biz-primary:\s*(#[0-9a-fA-F]{6})/', file_get_contents($cssFile), $m)) $primary = $m[1];
        ?>
        <div class="cloud-card">
            <div class="cloud-thumb" style="background:<?php echo esc($primary); ?>22">
                <?php if (!empty($ct['screenshot'])): ?>
                    <img src="<?php echo esc($ct['screenshot']); ?>" loading="lazy" alt="<?php echo esc($ct['title']); ?>" onerror="this.remove()">
                <?php else: ?>
                    <div class="ui-mini" style="--t:<?php echo esc($primary); ?>">
                        <div class="ui-nav"></div>
                        <div class="ui-hero"></div>
                        <div class="ui-cards"><i></i><i></i><i></i></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="cloud-info">
                <h3><?php echo esc($ct['title'] ?? $ct['name']); ?></h3>
                <p><?php echo esc($ct['desc'] ?? ''); ?></p>
                <div class="cloud-meta">
                    <span class="tag"><?php echo __('云端'); ?> v<?php echo esc($ct['version']); ?></span>
                    <?php if ($local !== ''): ?><span class="tag"><?php echo __('本地'); ?> v<?php echo esc($local); ?></span><?php endif; ?>
                    <?php if (!empty($ct['updated'])): ?><span class="tag"><?php echo __('更新'); ?> <?php echo esc($ct['updated']); ?></span><?php endif; ?>
                    <?php if ($st === 'not-installed'): ?>
                        <span class="tag"><?php echo __('未安装'); ?></span>
                    <?php elseif ($st === 'update-available'): ?>
                        <span class="tag" style="background:#fff3cd;color:#856404"><?php echo __('有新版本'); ?></span>
                    <?php else: ?>
                        <span class="tag tag-ok"><?php echo __('已是最新'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="cloud-actions">
                    <?php if ($st === 'not-installed'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="cloud_install">
                            <input type="hidden" name="cname" value="<?php echo esc($ct['name']); ?>">
                            <button class="btn btn-sm" type="submit">⬇ <?php echo __('安装'); ?></button>
                        </form>
                    <?php elseif ($st === 'update-available'): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('<?php echo __('更新将自动备份当前版本，失败可回滚。确定更新？'); ?>')">
                            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="cloud_update">
                            <input type="hidden" name="cname" value="<?php echo esc($ct['name']); ?>">
                            <button class="btn btn-sm" type="submit">🔄 <?php echo __('更新'); ?></button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:12px;line-height:24px">✓ <?php echo __('已安装'); ?></span>
                    <?php endif; ?>
                    <?php if ($installed && currentTheme() !== $ct['name']): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="theme" value="<?php echo esc($ct['name']); ?>">
                            <button type="submit" name="action" value="activate" class="btn btn-ghost btn-sm"><?php echo __('激活'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 上传安装 -->
<div class="panel" style="margin-bottom:20px">
    <h3 style="margin:0 0 10px;color:var(--g-700)">🎨 <?php echo __('上传安装主题'); ?></h3>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="install_zip">
        <input type="file" name="zipfile" accept=".zip" style="flex:1;min-width:200px">
        <button class="btn" type="submit"><?php echo __('上传安装'); ?></button>
    </form>
    <p class="muted" style="margin-top:6px;font-size:.85rem"><?php echo __('ZIP 包需包含一个顶层目录，内含 theme.css 文件。'); ?></p>
</div>

<!-- 主题列表 -->
<div class="theme-grid">
    <?php foreach ($themes as $t):
        $tShot = $cloudShotMap[$t['name']] ?? '';
        // 内置主题（fresh/forest/mint）走核心自带的 assets/img/themes/ 截图资源
        $builtinShot = '';
        if ($t['builtin']) {
            $f = RYEBLOG_ROOT . '/assets/img/themes/' . $t['name'] . '.png';
            if (is_file($f)) $builtinShot = baseUrl('assets/img/themes/' . $t['name'] . '.png?v=' . filemtime($f));
        }
        $logoPath = RYEBLOG_ROOT . '/usr/theme/' . $t['name'] . '/assets/logo.svg';
        $hasLogo = !$t['builtin'] && is_file($logoPath);
    ?>
        <div class="theme-card <?php echo $t['active'] ? 'theme-active' : ''; ?>">
            <div class="theme-preview theme-<?php echo esc($t['name']); ?>">
                <?php if ($tShot): ?>
                    <img src="<?php echo esc($tShot); ?>" alt="" onerror="this.remove()">
                <?php elseif ($builtinShot): ?>
                    <img src="<?php echo esc($builtinShot); ?>" alt="" onerror="this.remove()">
                <?php elseif ($hasLogo): ?>
                    <img src="<?php echo esc(baseUrl('usr/theme/' . $t['name'] . '/assets/logo.svg?v=' . filemtime($logoPath))); ?>" alt="">
                <?php else: ?>
                    <span class="theme-preview-text">Aa</span>
                <?php endif; ?>
            </div>
            <div class="theme-info">
                <h3><?php echo esc($t['title']); ?>
                    <?php if ($t['active']): ?><span class="tag tag-ok"><?php echo __('当前'); ?></span><?php endif; ?>
                    <?php if ($t['builtin']): ?><span class="tag"><?php echo __('内置'); ?></span><?php endif; ?>
                </h3>
                <p class="muted"><?php echo esc($t['desc'] ?: '—'); ?></p>
                <div class="cloud-meta" style="margin-top:2px">
                    <span class="tag"><?php echo __('版本'); ?> 1.0.0</span>
                    <span class="tag"><?php echo __('内置配色'); ?></span>
                </div>
                <div class="theme-actions" style="margin-top:10px;display:flex;gap:8px">
                    <?php if (!$t['active']): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="theme" value="<?php echo esc($t['name']); ?>">
                            <button type="submit" name="action" value="activate" class="btn btn-sm"><?php echo __('激活'); ?></button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:12px;line-height:24px">✓ <?php echo __('已启用'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<p class="muted" style="margin-top:16px;font-size:.85rem">
    💡 <?php echo __('主题开发文档请参阅'); ?> <a href="<?php echo baseUrl('docs.php?doc=THEME_DEV'); ?>" target="_blank">THEME_DEV</a>。
    <?php echo __('自定义主题放入'); ?> <code>usr/theme/your-theme/theme.css</code>，<?php echo __('通过覆盖 CSS 变量实现换肤。'); ?>
</p>

<style>
.theme-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(224px,1fr)); gap:14px; }
.theme-card { background:#fff; border:2px solid var(--line); border-radius:12px; overflow:hidden; display:flex; flex-direction:column; transition:border-color .2s,box-shadow .2s; }
.theme-card.theme-active { border-color:var(--g-500); box-shadow:0 4px 16px rgba(46,107,53,.12); }
.theme-preview { aspect-ratio: 4/3; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,var(--g-100),var(--g-050)); overflow:hidden; }
.theme-preview img { width:100%; height:100%; object-fit:cover; display:block; }
.theme-preview-text { font-size:32px; font-weight:700; color:var(--g-500); }
.theme-info { padding:12px 14px; }
.theme-info h3 { font-size:15px; margin:0 0 4px; color:var(--g-700); }
.theme-info small { display:block; margin-top:4px; font-size:.8rem; }
/* 云端市场卡片（halo.run 商店风格：上图下文，图片占 2/3+） */
.cloud-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(224px,1fr)); gap:14px; margin-top:12px; }
.cloud-card { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; transition:box-shadow .2s,transform .2s; }
.cloud-card:hover { box-shadow:0 10px 28px rgba(0,0,0,.10); transform:translateY(-3px); }
/* 图片区：1:1 + object-fit:cover，背景用主题主色 13% 透明，加载前/失败时仍协调 */
.cloud-thumb { aspect-ratio: 4/3; overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center; }
.cloud-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.cloud-info { padding:10px 14px 12px; display:flex; flex-direction:column; gap:6px; }
.cloud-info h3 { font-size:14.5px; margin:0; color:var(--g-700); line-height:1.3; }
.cloud-info p { font-size:12px; color:var(--muted); margin:0; line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:36px; }
.cloud-meta { display:flex; gap:5px; flex-wrap:wrap; }
.cloud-meta .tag { font-size:11px; padding:2px 7px; }
.cloud-actions { display:flex; gap:6px; margin-top:2px; flex-wrap:wrap; }
.cloud-actions .btn { padding:4px 10px; font-size:12.5px; }
/* 界面示意（mini 首页：nav 条 + hero 块 + 卡片行），作为无截图时的回退 */
.ui-mini{width:100%;height:100%;padding:10px;display:flex;flex-direction:column;gap:6px;box-sizing:border-box;background:#f8fafc}
.ui-mini .ui-nav{height:10px;border-radius:3px;background:var(--t,#94a3b8)}
.ui-mini .ui-hero{height:40px;border-radius:3px;background:linear-gradient(135deg,var(--t,#94a3b8),rgba(255,255,255,.35));position:relative}
.ui-mini .ui-hero::after{content:'';position:absolute;left:8px;top:8px;width:40%;height:8px;border-radius:3px;background:rgba(255,255,255,.6)}
.ui-mini .ui-cards{display:flex;gap:6px;flex:1}
.ui-mini .ui-cards i{flex:1;border-radius:3px;background:#fff;border:1px solid #e2e8f0}
</style>
<?php adminFoot();
