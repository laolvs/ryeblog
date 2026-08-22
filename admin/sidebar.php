<?php
/**
 * RyeBlog 后台 —— 侧边栏模块管理
 * 功能：按页面类型配置侧边栏模块的显示/隐藏、位置（top/bottom）、排序、数量
 */
require_once __DIR__ . '/admin.php';

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            // 接收并构建配置
            $config = getSidebarConfig();
            $pageTypes = sidebarPageTypes();
            $registry = sidebarModuleRegistry();

            foreach ($pageTypes as $pt => $label) {
                // 特殊模块
                if ($pt === 'home') {
                    $config['home']['author_card']['position'] = $_POST['home_author_card_position'] ?? 'top';
                    $config['home']['author_card']['enabled'] = true; // 始终启用
                }
                if ($pt === 'post') {
                    $config['post']['toc']['position'] = $_POST['post_toc_position'] ?? 'top';
                    $config['post']['toc']['enabled'] = true; // 始终启用
                }

                // 通用模块
                foreach ($registry as $mid => $modInfo) {
                    if (!empty($modInfo['special'])) continue; // 跳过特殊模块
                    if (!isset($config[$pt][$mid])) continue;

                    $prefix = $pt . '_' . $mid;
                    $config[$pt][$mid]['enabled']  = isset($_POST[$prefix . '_enabled']);
                    $config[$pt][$mid]['position'] = $_POST[$prefix . '_position'] ?? 'top';
                    $config[$pt][$mid]['order']    = (int)($_POST[$prefix . '_order'] ?? 0);
                    if (!empty($modInfo['has_limit'])) {
                        $config[$pt][$mid]['limit'] = (int)($_POST[$prefix . '_limit'] ?? $modInfo['default_limit']);
                    }
                }
            }

            saveSidebarConfig($config);
            $msg = __('侧边栏模块配置已保存。');
        } elseif ($action === 'reset') {
            // 重置为默认
            saveSidebarConfig(sidebarDefaultConfig());
            $msg = __('已重置为默认配置。');
        }
    }
}

$config = getSidebarConfig();
$pageTypes = sidebarPageTypes();
$registry = sidebarModuleRegistry();
$activeTab = $_GET['tab'] ?? 'home';
if (!isset($pageTypes[$activeTab])) $activeTab = 'home';

adminHead(__('侧边栏管理'), 'sidebar.php');
?>
<h1><?php echo __('侧边栏模块管理'); ?></h1>
<?php if ($msg): ?><div class="notice notice-ok"><?php echo esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>

<p class="muted" style="margin-bottom:16px;font-size:.85rem">
    💡 <?php echo __('配置各页面右侧边栏显示的模块。可设置显示/隐藏、位置（顶部/底部）、排序、显示数量。'); ?>
    <?php echo __('博主信息卡仅首页显示、文章目录仅文章页显示，只能调整位置不能关闭。'); ?>
</p>

<!-- Tab 导航 -->
<div class="sb-tabs">
    <?php foreach ($pageTypes as $pt => $label): ?>
        <button type="button" class="sb-tab <?php echo $pt === $activeTab ? 'sb-tab-active' : ''; ?>" onclick="sbSwitchTab('<?php echo $pt; ?>')">
            <?php echo esc(__($label)); ?>
        </button>
    <?php endforeach; ?>
</div>

<form method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="action" value="save">

    <?php foreach ($pageTypes as $pt => $label): ?>
    <div class="sb-panel" id="sb-panel-<?php echo $pt; ?>" style="<?php echo $pt === $activeTab ? '' : 'display:none'; ?>">
        <div class="sb-layout-preview">
            <div class="sb-col">
                <div class="sb-col-title sb-col-top">📌 <?php echo __('顶部区域'); ?></div>
                <div class="sb-dropzone" data-zone="<?php echo $pt; ?>-top">
                    <!-- top 模块会被 JS 排到这里 -->
                </div>
            </div>
            <div class="sb-col">
                <div class="sb-col-title sb-col-bottom">📌 <?php echo __('底部区域'); ?></div>
                <div class="sb-dropzone" data-zone="<?php echo $pt; ?>-bottom">
                    <!-- bottom 模块会被 JS 排到这里 -->
                </div>
            </div>
        </div>

        <table class="sb-table">
            <thead>
                <tr>
                    <th style="width:40px"><?php echo __('显示'); ?></th>
                    <th style="width:140px"><?php echo __('模块'); ?></th>
                    <th style="width:60px"><?php echo __('位置'); ?></th>
                    <th style="width:50px"><?php echo __('排序'); ?></th>
                    <th style="width:80px"><?php echo __('数量'); ?></th>
                    <th><?php echo __('说明'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $ptConfig = $config[$pt];
                foreach ($registry as $mid => $modInfo):
                    $isSpecial = !empty($modInfo['special']);
                    // 特殊模块只在对应页面显示
                    if ($mid === 'author_card' && $pt !== 'home') continue;
                    if ($mid === 'toc' && $pt !== 'post') continue;
                    $cfg = $ptConfig[$mid];
                    $prefix = $pt . '_' . $mid;
                    $posClass = $cfg['position'] === 'top' ? 'sb-mod-top' : 'sb-mod-bottom';
                ?>
                <tr class="sb-row <?php echo $posClass; ?>" data-module="<?php echo $mid; ?>" data-position="<?php echo $cfg['position']; ?>">
                    <td style="text-align:center">
                        <?php if ($isSpecial): ?>
                            <span class="sb-lock">🔒</span>
                            <input type="hidden" name="<?php echo $prefix; ?>_enabled" value="1">
                        <?php else: ?>
                            <input type="checkbox" name="<?php echo $prefix; ?>_enabled" value="1" <?php echo $cfg['enabled'] ? 'checked' : ''; ?> class="sb-checkbox">
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo esc(__($modInfo['title'])); ?></strong>
                        <?php if ($isSpecial): ?><span class="tag tag-ok" style="font-size:.75rem"><?php echo __('固定'); ?></span><?php endif; ?>
                    </td>
                    <td>
                        <select name="<?php echo $prefix; ?>_position" class="sb-select sb-position-select" data-prefix="<?php echo $prefix; ?>">
                            <option value="top" <?php echo $cfg['position'] === 'top' ? 'selected' : ''; ?>><?php echo __('顶部'); ?></option>
                            <option value="bottom" <?php echo $cfg['position'] === 'bottom' ? 'selected' : ''; ?>><?php echo __('底部'); ?></option>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="<?php echo $prefix; ?>_order" value="<?php echo (int)$cfg['order']; ?>" class="sb-order" style="width:40px;text-align:center">
                    </td>
                    <td>
                        <?php if (!empty($modInfo['has_limit'])): ?>
                            <input type="number" name="<?php echo $prefix; ?>_limit" value="<?php echo (int)$cfg['limit']; ?>" min="1" max="100" style="width:50px;text-align:center">
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="muted"><?php echo esc(__($modInfo['desc'])); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
        <button class="btn" type="submit">💾 <?php echo __('保存配置'); ?></button>
        <button class="btn btn-ghost" type="submit" name="action" value="reset" onclick="return confirm('<?php echo __('确定重置所有侧边栏配置为默认值？'); ?>')">↩ <?php echo __('重置默认'); ?></button>
    </div>
</form>

<style>
.sb-tabs { display:flex; gap:0; margin-bottom:0; border-bottom:2px solid var(--line); }
.sb-tab {
    padding:8px 18px; border:none; background:none; cursor:pointer;
    font-size:14px; color:var(--muted); border-bottom:3px solid transparent;
    margin-bottom:-2px; transition:color .2s,border-color .2s;
    border-radius:8px 8px 0 0;
}
.sb-tab:hover { color:var(--g-700); background:var(--g-025); }
.sb-tab-active { color:var(--g-700); border-bottom-color:var(--g-500); font-weight:600; background:var(--g-025); }

.sb-panel { padding:20px; background:#fff; border:1px solid var(--line); border-top:none; border-radius:0 0 8px 8px; }

.sb-layout-preview { display:flex; gap:12px; margin-bottom:20px; }
.sb-col { flex:1; min-height:80px; background:var(--g-025); border:1px dashed var(--line); border-radius:8px; padding:8px; }
.sb-col-title { font-size:12px; font-weight:600; color:var(--g-700); margin-bottom:8px; padding-bottom:4px; border-bottom:1px solid var(--line); }
.sb-dropzone { min-height:40px; }
.sb-preview-item {
    display:inline-block; padding:3px 10px; margin:3px;
    background:var(--g-100); border:1px solid var(--g-200);
    border-radius:6px; font-size:12px; color:var(--g-700);
    transition:all .2s;
}
.sb-preview-item.sb-preview-disabled { opacity:.4; text-decoration:line-through; }
.sb-preview-item.sb-preview-special { background:var(--g-200); border-color:var(--g-400); }

.sb-table { width:100%; border-collapse:collapse; }
.sb-table th { text-align:left; padding:8px 10px; background:var(--g-050); border-bottom:2px solid var(--line); font-size:13px; color:var(--g-700); }
.sb-table td { padding:8px 10px; border-bottom:1px solid var(--line); font-size:14px; }
.sb-row { transition:opacity .2s; }
.sb-row:has(.sb-checkbox:not(:checked)) { opacity:.5; }
.sb-lock { font-size:16px; }

.sb-checkbox { width:18px; height:18px; cursor:pointer; accent-color:var(--g-500); }
.sb-select { padding:4px 8px; border:1px solid var(--line); border-radius:4px; font-size:13px; cursor:pointer; }
.sb-select:focus { outline:none; border-color:var(--g-500); }

/* 拖拽排序 */
.sb-preview-item { cursor:grab; }
.sb-preview-item.sb-dragging { opacity:.4; cursor:grabbing; }
.sb-dropzone.sb-drag-over { outline:2px dashed var(--g-500); background:var(--g-100); }
</style>

<script>
// Tab 切换
function sbSwitchTab(pt) {
    document.querySelectorAll('.sb-panel').forEach(p => p.style.display = 'none');
    document.getElementById('sb-panel-' + pt).style.display = '';
    document.querySelectorAll('.sb-tab').forEach(t => t.classList.remove('sb-tab-active'));
    event.target.classList.add('sb-tab-active');
}

// 计算拖放落点：返回光标 Y 坐标下方的兄弟元素
function sbDragAfter(container, y) {
    var items = Array.prototype.slice.call(container.querySelectorAll('.sb-preview-item:not(.sb-dragging)'));
    var closest = { offset: -Infinity, el: null };
    items.forEach(function(child) {
        var box = child.getBoundingClientRect();
        var offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            closest = { offset: offset, el: child };
        }
    });
    return closest.el;
}

// 按“位置 + 排序”重新渲染预览，并为每个条目绑定拖拽
function sbUpdatePreview(panel) {
    var pt = panel.id.replace('sb-panel-', '');
    var topZone = panel.querySelector('[data-zone="' + pt + '-top"]');
    var bottomZone = panel.querySelector('[data-zone="' + pt + '-bottom"]');
    topZone.innerHTML = '';
    bottomZone.innerHTML = '';

    // 先按当前 order 升序排列，保证预览与已保存顺序一致
    var rows = Array.prototype.slice.call(panel.querySelectorAll('.sb-row'));
    rows.sort(function(a, b) {
        return (parseInt(a.querySelector('.sb-order').value, 10) || 0) -
               (parseInt(b.querySelector('.sb-order').value, 10) || 0);
    });

    rows.forEach(function(row) {
        var mid = row.dataset.module;
        var title = row.querySelector('td:nth-child(2) strong').textContent;
        var checkbox = row.querySelector('.sb-checkbox');
        var posSelect = row.querySelector('.sb-position-select');
        var isSpecial = row.querySelector('.sb-lock');
        var position = posSelect.value;
        var enabled = isSpecial ? true : (checkbox ? checkbox.checked : false);

        var item = document.createElement('span');
        item.className = 'sb-preview-item';
        item.dataset.module = mid;
        if (isSpecial) item.classList.add('sb-preview-special');
        if (!enabled) item.classList.add('sb-preview-disabled');
        item.textContent = title;
        item.draggable = !isSpecial; // 固定模块（博主卡/目录）不允许拖动，位置由后台锁定
        item.addEventListener('dragstart', function(e) {
            item.classList.add('sb-dragging');
            e.dataTransfer.setData('text/plain', mid);
            e.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', function() {
            item.classList.remove('sb-dragging');
        });

        if (position === 'top') topZone.appendChild(item);
        else bottomZone.appendChild(item);
    });
}

// 根据拖放后的 DOM 顺序，回写每行的 order 与 position 隐藏字段
function sbRenumber(panel) {
    var pt = panel.id.replace('sb-panel-', '');
    ['top', 'bottom'].forEach(function(zone) {
        var z = panel.querySelector('[data-zone="' + pt + '-' + zone + '"]');
        var items = z.querySelectorAll('.sb-preview-item');
        items.forEach(function(it, idx) {
            var mid = it.dataset.module;
            var row = panel.querySelector('.sb-row[data-module="' + mid + '"]');
            if (!row) return;
            var orderInput = row.querySelector('.sb-order');
            if (orderInput) orderInput.value = idx + 1;
            var posSel = row.querySelector('.sb-position-select');
            if (posSel) posSel.value = zone;
        });
    });
}

// 初始化所有面板
document.querySelectorAll('.sb-panel').forEach(function(panel) {
    sbUpdatePreview(panel);

    // 位置切换时更新预览
    panel.querySelectorAll('.sb-position-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            sbUpdatePreview(panel);
        });
    });
    // 复选框切换时更新预览
    panel.querySelectorAll('.sb-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            sbUpdatePreview(panel);
        });
    });
    // 拖放区：接收拖入的模块
    panel.querySelectorAll('.sb-dropzone').forEach(function(zone) {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            zone.classList.add('sb-drag-over');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('sb-drag-over');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('sb-drag-over');
            var mid = e.dataTransfer.getData('text/plain');
            var item = panel.querySelector('.sb-preview-item[data-module="' + mid + '"]');
            if (!item) return;
            var after = sbDragAfter(zone, e.clientY);
            if (after == null) zone.appendChild(item);
            else zone.insertBefore(item, after);
            sbRenumber(panel);
        });
    });
});
</script>

<?php adminFoot();
