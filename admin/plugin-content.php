<?php
/**
 * RyeBlog 后台 —— 插件内容管理汇总页
 * --------------------------------------------------------------------------
 * 汇集所有「已启用」插件的内容添加 / 编辑入口（插件实现 contentMenu() 方法）。
 * 机制与现有 config()/saveConfig() 一致：遍历启用插件，调用其 contentMenu()，
 * 收集返回的内容入口数组并渲染为卡片。未声明 contentMenu() 但有 config() 的插件，
 * 自动兜底一个「插件设置」入口。
 */
require_once __DIR__ . '/admin.php';

adminHead(__('插件内容管理'), 'plugin-content.php');

// 收集各启用插件的内容入口
$plugins = [];
foreach (pluginActiveList() as $dir) {
    $file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
    if (!is_file($file)) continue;
    require_once $file;
    $cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);
    if (!class_exists($cls)) continue;

    // 读取插件元数据（标题 / 描述）
    $title = $dir;
    $desc  = '';
    $src   = file_get_contents($file);
    if (preg_match('/@Title\s+(.+)/', $src, $m)) $title = trim($m[1]);
    if (preg_match('/@Desc\s+(.+)/', $src, $m))  $desc  = trim($m[1]);

    $entries = [];
    if (method_exists($cls, 'contentMenu')) {
        $entries = (array) call_user_func([$cls, 'contentMenu']);
    }
    // 兜底：有配置页但没声明 contentMenu，提供一个「设置」入口
    if (empty($entries) && method_exists($cls, 'config')) {
        $entries[] = [
            'label' => __('插件设置'),
            'url'   => baseUrl('admin/plugin-config.php?dir=' . rawurlencode($dir)),
            'desc'  => __('打开该插件的配置页面'),
            'icon'  => '⚙️',
        ];
    }

    $plugins[] = [
        'dir'     => $dir,
        'title'   => $title,
        'desc'    => $desc,
        'entries' => $entries,
    ];
}
?>
<h1><?php echo __('插件内容管理'); ?></h1>
<p class="muted" style="margin-bottom:18px;font-size:.9rem">
    💡 <?php echo __('这里汇集所有已启用插件的内容添加 / 编辑入口。点击对应入口即可直接进入该插件的专属管理界面，无需在插件列表里逐个翻找。'); ?>
</p>

<?php if (empty($plugins)): ?>
    <div class="notice notice-err"><?php echo __('当前没有启用任何插件。请先到「插件管理」启用插件。'); ?></div>
<?php else: ?>
    <div class="pc-grid">
        <?php foreach ($plugins as $p): ?>
        <div class="pc-card">
            <div class="pc-card-head">
                <span class="pc-plugin-name"><?php echo esc($p['title']); ?></span>
                <?php if ($p['desc']): ?><span class="pc-plugin-desc"><?php echo esc($p['desc']); ?></span><?php endif; ?>
            </div>
            <?php if (empty($p['entries'])): ?>
                <p class="muted" style="margin:10px 0 0;font-size:.85rem"><?php echo __('该插件暂未提供内容管理入口。'); ?></p>
            <?php else: ?>
                <div class="pc-entries">
                    <?php foreach ($p['entries'] as $e): ?>
                        <a class="pc-entry" href="<?php echo esc($e['url']); ?>">
                            <span class="pc-entry-icon"><?php echo esc($e['icon'] ?? '🔹'); ?></span>
                            <span class="pc-entry-body">
                                <span class="pc-entry-label"><?php echo esc($e['label']); ?></span>
                                <?php if (!empty($e['desc'])): ?><span class="pc-entry-desc"><?php echo esc($e['desc']); ?></span><?php endif; ?>
                            </span>
                            <span class="pc-entry-arrow">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.pc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }
.pc-card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px 18px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
.pc-card-head { border-bottom:1px solid var(--line); padding-bottom:10px; margin-bottom:10px; }
.pc-plugin-name { font-weight:700; color:var(--g-800); font-size:15px; }
.pc-plugin-desc { display:block; color:var(--muted); font-size:.8rem; margin-top:3px; line-height:1.5; }
.pc-entries { display:flex; flex-direction:column; gap:8px; }
.pc-entry { display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid var(--line); border-radius:10px; text-decoration:none; color:inherit; background:var(--g-025); transition:border-color .15s,transform .1s,background .15s; }
.pc-entry:hover { border-color:var(--g-500); background:#fff; transform:translateX(2px); }
.pc-entry-icon { font-size:20px; flex:none; }
.pc-entry-body { flex:1; min-width:0; display:flex; flex-direction:column; }
.pc-entry-label { font-weight:600; color:var(--g-800); font-size:14px; }
.pc-entry-desc { color:var(--muted); font-size:.78rem; margin-top:2px; line-height:1.4; }
.pc-entry-arrow { color:var(--g-400); font-size:16px; flex:none; }
</style>
<?php adminFoot();
