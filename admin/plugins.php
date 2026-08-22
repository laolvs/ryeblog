<?php
/**
 * RyeBlog 后台 —— 插件管理
 * 功能：列表、启用/停用、配置入口、上传ZIP安装、删除
 */
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/../inc/cloud.php';
require_once __DIR__ . '/../inc/markdown.php';

$msg = $err = '';
$cloudMsg = $cloudErr = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'cloud_install' || $action === 'cloud_update') {
            // 云端安装/更新插件
            $cname = basename(trim($_POST['cname'] ?? ''));
            $manifest = cloudFetchManifest(true);
            if (!$manifest['ok']) {
                $cloudErr = $manifest['msg'];
            } else {
                $pkg = null;
                foreach ($manifest['data']['plugins'] as $p) {
                    if ($p['name'] === $cname) { $pkg = $p; break; }
                }
                if (!$pkg) {
                    $cloudErr = __('云端未找到该插件：') . esc($cname);
                } elseif ($action === 'cloud_install') {
                    $r = cloudInstall('plugin', $pkg);
                    if ($r['ok']) $cloudMsg = $r['msg'];
                    else $cloudErr = $r['msg'];
                } else {
                    $r = cloudUpdate('plugin', $pkg);
                    if ($r['ok']) $cloudMsg = $r['msg'];
                    else $cloudErr = $r['msg'];
                }
            }
        } elseif ($action === 'cloud_refresh') {
            $manifest = cloudFetchManifest(true);
            if ($manifest['ok']) $cloudMsg = __('云端仓库已刷新。');
            else $cloudErr = $manifest['msg'];
        } elseif ($action === 'activate') {
            $dir = basename(trim($_POST['dir'] ?? ''));
            $r = activatePlugin($dir);
            if ($r === true) $msg = __('插件「') . $dir . __('」已启用。');
            else $err = $r;

        } elseif ($action === 'deactivate') {
            $dir = basename(trim($_POST['dir'] ?? ''));
            $r = deactivatePlugin($dir);
            if ($r === true) {
                $msg = __('插件「') . $dir . __('」已停用。');
                // 英文站插件：告知备份文件位置（防止"关了就前功尽弃"）
                if ($dir === 'english-admin') {
                    $backupDir = RYEBLOG_ROOT . '/usr/uploads/backup';
                    $files = glob($backupDir . '/verda_en_*.sql');
                    if ($files) {
                        sort($files);
                        $latest = basename(end($files));
                        $msg .= ' ' . __('英文数据已备份至') . ' <code>usr/uploads/backup/' . esc($latest) . '</code>' . __('（重新启用插件后可通过后台「英文数据恢复」导入）。');
                    }
                }
            }
            else $err = $r;

        } elseif ($action === 'delete') {
            $dir = basename(trim($_POST['dir'] ?? ''));
            $r = deletePlugin($dir);
            if ($r === true) $msg = __('插件「') . $dir . __('」已删除。');
            else $err = $r;

        } elseif ($action === 'install_zip') {
            if (!isset($_FILES['zipfile']) || $_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) {
                $err = __('请选择 ZIP 文件。');
            } else {
                $tmp = $_FILES['zipfile']['tmp_name'];
                $r = installPluginZip($tmp);
                if ($r['ok']) {
                    $msg = __('插件「') . ($r['dir'] ?? '') . __('」安装成功，请在下方启用。');
                } else {
                    $err = $r['msg'] ?? __('安装失败。');
                }
            }
        }
    }
}

$plugins = scanPlugins();
adminHead(__('插件管理'), 'plugins.php');
?>
<h1><?php echo __('插件管理'); ?></h1>
<?php if ($msg): ?><div class="notice notice-ok"><?php echo esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>
<?php if ($cloudMsg): ?><div class="notice notice-ok">✅ <?php echo esc($cloudMsg); ?></div><?php endif; ?>
<?php if ($cloudErr): ?><div class="notice notice-err"><?php echo esc($cloudErr); ?></div><?php endif; ?>

<!-- 云端仓库 -->
<div class="panel" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0;color:var(--g-700)">☁️ <?php echo __('云端仓库'); ?>
            <small class="muted" style="font-weight:400">(<code><?php echo esc(cloudRepoUrl()); ?></code>)</small>
        </h3>
        <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="cloud_refresh">
            <button class="btn btn-ghost btn-sm" type="submit">🔄 <?php echo __('刷新'); ?></button>
        </form>
    </div>
    <?php
    $manifest = cloudFetchManifest();
    if (!cloudEnabled()):
        echo '<p class="muted">' . __('云端市场未启用，请在「站点设置 → 云端市场」中开启。') . '</p>';
    elseif (!$manifest['ok']):
        echo '<div class="notice notice-err">' . esc($manifest['msg']) . '</div>';
    elseif (empty($manifest['data']['plugins'])):
        echo '<p class="muted">' . __('云端暂无可用的插件。') . '</p>';
    else: ?>
    <div class="cloud-grid" style="margin-top:10px">
        <?php foreach ($manifest['data']['plugins'] as $cp):
            $st = cloudStatus('plugin', $cp);
            $local = cloudLocalVersion('plugin', $cp['name']);
            // 插件主色：按 name 简单哈希出协调色（无截图时 ui-mini 背景）
            $palette = ['#2563eb','#7c3aed','#dc2626','#0891b2','#ea580c','#475569','#16a34a'];
            $primary = $palette[crc32($cp['name']) % count($palette)];
        ?>
        <div class="cloud-card">
            <div class="cloud-thumb" style="background:<?php echo esc($primary); ?>22">
                <?php if (!empty($cp['screenshot'])): ?>
                    <img src="<?php echo esc($cp['screenshot']); ?>" loading="lazy" alt="<?php echo esc($cp['title']); ?>" onerror="this.remove()">
                <?php else: ?>
                    <span class="cloud-thumb-text" style="background:linear-gradient(135deg,<?php echo esc($primary); ?>,<?php echo esc($primary); ?>cc);-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent"><?php echo esc(mb_substr($cp['title'] ?? $cp['name'], 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div class="cloud-info">
                <h3><?php echo esc($cp['title'] ?? $cp['name']); ?></h3>
                <p><?php echo esc($cp['desc'] ?? ''); ?></p>
                <div class="cloud-meta">
                    <span class="tag"><?php echo __('云端'); ?> v<?php echo esc($cp['version']); ?></span>
                    <?php if ($local !== ''): ?><span class="tag"><?php echo __('本地'); ?> v<?php echo esc($local); ?></span><?php endif; ?>
                    <?php if (!empty($cp['updated'])): ?><span class="tag"><?php echo __('更新'); ?> <?php echo esc($cp['updated']); ?></span><?php endif; ?>
                    <?php if ($st === 'not-installed'): ?>
                        <span class="tag"><?php echo __('未安装'); ?></span>
                    <?php elseif ($st === 'update-available'): ?>
                        <span class="tag" style="background:#fff3cd;color:#856404"><?php echo __('有新版本'); ?></span>
                    <?php else: ?>
                        <span class="tag tag-ok"><?php echo __('已是最新'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cp['changelog'])): ?>
                <details style="margin:2px 0"><summary class="muted" style="cursor:pointer;font-size:12px">📝 <?php echo __('更新说明'); ?></summary>
                <div class="md-changelog" style="margin-top:4px;padding:6px 10px;background:#fafcf8;border:1px solid #e2ebd9;border-radius:6px;font-size:12px;line-height:1.55"><?php echo markdownToHtml($cp['changelog']); ?></div>
                </details>
                <?php endif; ?>
                <div class="cloud-actions">
                    <?php if ($st === 'not-installed'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="cloud_install">
                            <input type="hidden" name="cname" value="<?php echo esc($cp['name']); ?>">
                            <button class="btn btn-sm" type="submit">⬇ <?php echo __('安装'); ?></button>
                        </form>
                    <?php elseif ($st === 'update-available'): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('<?php echo __('更新将自动备份当前版本，失败可回滚。确定更新？'); ?>')">
                            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="cloud_update">
                            <input type="hidden" name="cname" value="<?php echo esc($cp['name']); ?>">
                            <button class="btn btn-sm" type="submit">🔄 <?php echo __('更新'); ?></button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:12px;line-height:24px">✓ <?php echo __('已安装'); ?></span>
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
    <h3 style="margin:0 0 10px;color:var(--g-700)">📦 <?php echo __('上传安装插件'); ?></h3>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="install_zip">
        <input type="file" name="zipfile" accept=".zip" style="flex:1;min-width:200px">
        <button class="btn" type="submit"><?php echo __('上传安装'); ?></button>
    </form>
    <p class="muted" style="margin-top:6px;font-size:.85rem"><?php echo __('ZIP 包需包含一个顶层目录，内含 Plugin.php 文件。'); ?></p>
</div>

<!-- 插件列表 -->
<div class="panel">
    <h3 style="margin:0 0 14px;color:var(--g-700)"><?php echo __('已安装插件'); ?>（<?php echo count($plugins); ?>）</h3>
    <?php if (empty($plugins)): ?>
        <p class="muted"><?php echo __('还没有安装任何插件。将插件目录放到'); ?> <code>usr/plugins/</code> <?php echo __('下或上传 ZIP 安装。'); ?></p>
    <?php else: ?>
        <table class="data">
            <tr>
                <th><?php echo __('插件名称'); ?></th>
                <th><?php echo __('版本'); ?></th>
                <th><?php echo __('作者'); ?></th>
                <th><?php echo __('描述'); ?></th>
                <th><?php echo __('状态'); ?></th>
                <th><?php echo __('操作'); ?></th>
            </tr>
            <?php foreach ($plugins as $p): ?>
                <tr>
                    <td>
                        <strong><?php echo esc($p['title']); ?></strong>
                        <br><small class="muted"><?php echo esc($p['name']); ?></small>
                    </td>
                    <td><?php echo esc($p['ver'] ?: '—'); ?></td>
                    <td><?php echo esc($p['author'] ?: '—'); ?></td>
                    <td><?php echo esc($p['desc'] ?: '—'); ?></td>
                    <td>
                        <?php if ($p['active']): ?>
                            <span class="tag tag-ok"><?php echo __('已启用'); ?></span>
                        <?php else: ?>
                            <span class="tag"><?php echo __('已停用'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <form method="post" style="display:inline">
                            <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="dir" value="<?php echo esc($p['name']); ?>">
                            <?php if ($p['active']): ?>
                                <?php if ($p['name'] === 'english-admin'): ?>
                                    <button type="submit" name="action" value="deactivate" class="btn btn-ghost btn-sm"
                                        onclick="return confirm('⚠️ <?php echo __('关闭英文站插件将删除所有英文数据'); ?>\n\n<?php echo __('包括：文章/页面/分类/标签/菜单的英文版、英文 URL (slug_en)、站点英文名。'); ?>\n<?php echo __('会先自动备份到 usr/uploads/backup/，可随时恢复。'); ?>\n\n<?php echo __('确定停用？'); ?>')"
                                        style="border-color:#b3261e;color:#b3261e"><?php echo __('停用'); ?></button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="deactivate" class="btn btn-ghost btn-sm"><?php echo __('停用'); ?></button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="submit" name="action" value="activate" class="btn btn-sm"><?php echo __('启用'); ?></button>
                            <?php endif; ?>
                        </form>
                        <?php if ($p['has_config'] && $p['active']): ?>
                            <a href="<?php echo baseUrl('admin/plugin-config.php?dir=' . urlencode($p['name'])); ?>" class="btn btn-ghost btn-sm"><?php echo __('配置'); ?></a>
                        <?php endif; ?>
                        <?php if ($p['doc']): ?>
                            <a href="<?php echo esc($p['doc']); ?>" target="_blank" class="btn btn-ghost btn-sm"><?php echo __('说明'); ?></a>
                        <?php endif; ?>
                        <?php if ($p['name'] === 'english-admin'): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('⚠️ <?php echo __('删除英文站插件将执行停用并删除插件文件'); ?>\n\n<?php echo __('英文数据会先备份到 usr/uploads/backup/，但恢复需先重新安装本插件，再从「配置」页导入备份。'); ?>\n\n<?php echo __('确定删除？此操作不可逆。'); ?>')">
                                <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="dir" value="<?php echo esc($p['name']); ?>">
                                <button type="submit" name="action" value="delete" class="btn btn-ghost btn-sm" style="color:#b3261e;border-color:#b3261e;font-weight:600"><?php echo __('删除'); ?></button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('<?php echo __('确认删除插件「'); ?><?php echo esc($p['title']); ?><?php echo __('」？此操作不可恢复。'); ?>')">
                                <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="dir" value="<?php echo esc($p['name']); ?>">
                                <button type="submit" name="action" value="delete" class="btn btn-ghost btn-sm" style="color:#b3261e"><?php echo __('删除'); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<p class="muted" style="margin-top:16px;font-size:.85rem">
    💡 <?php echo __('插件开发文档请参阅'); ?> <a href="<?php echo baseUrl('docs.php?doc=PLUGIN_DEV'); ?>" target="_blank">PLUGIN_DEV</a>。
    <?php echo __('将插件目录放入'); ?> <code>usr/plugins/your-plugin/</code>，<?php echo __('确保包含'); ?> <code>Plugin.php</code>。
</p>
<style>
/* 云端市场卡片：上图下文，图片占 2/3+ */
.cloud-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(224px,1fr)); gap:14px; margin-top:12px; }
.cloud-card { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; transition:box-shadow .2s,transform .2s; }
.cloud-card:hover { box-shadow:0 10px 28px rgba(0,0,0,.10); transform:translateY(-3px); }
.cloud-thumb { aspect-ratio: 4/3; overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center; }
.cloud-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.cloud-thumb-text { font-size:58px; font-weight:900; line-height:1; }
.cloud-info { padding:10px 14px 12px; display:flex; flex-direction:column; gap:6px; }
.cloud-info h3 { font-size:14.5px; margin:0; color:var(--g-700); line-height:1.3; }
.cloud-info p { font-size:12px; color:var(--muted); margin:0; line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:36px; }
.cloud-meta { display:flex; gap:5px; flex-wrap:wrap; }
.cloud-meta .tag { font-size:11px; padding:2px 7px; }
.cloud-actions { display:flex; gap:6px; margin-top:2px; flex-wrap:wrap; }
.cloud-actions .btn { padding:4px 10px; font-size:12.5px; }
</style>
<?php adminFoot();
