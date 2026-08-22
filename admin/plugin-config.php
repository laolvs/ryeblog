<?php
/**
 * RyeBlog 后台 —— 插件配置页
 * 通用页面：调用插件的 config() 方法获取表单 HTML，POST 时调用 saveConfig($_POST) 保存。
 */
require_once __DIR__ . '/admin.php';

$dir = basename(trim($_GET['dir'] ?? ''));
if ($dir === '') {
    header('Location: ' . baseUrl('admin/plugins.php'));
    exit;
}

$file = RYEBLOG_ROOT . '/usr/plugins/' . $dir . '/Plugin.php';
if (!is_file($file)) {
    adminHead(__('插件配置'), 'plugins.php');
    echo '<h1>' . __('插件配置') . '</h1><div class="notice notice-err">' . __('插件文件不存在。') . '</div>';
    adminFoot();
    exit;
}

require_once $file;
$cls = 'Plugin_' . preg_replace('/\W+/', '_', $dir);

// 插件未激活时提示启用（插件表可能未建，直接调用 config() 会报表不存在）
$activeDirs = array_filter(array_map('trim', explode(',', getOption('active_plugins', ''))));
if (!in_array($dir, $activeDirs, true)) {
    adminHead(__('插件配置'), 'plugins.php');
    echo '<h1>' . __('插件配置') . '</h1>'
        . '<div class="notice notice-warn">' . __('该插件尚未启用，启用后即可配置。') . ' <a href="' . esc(baseUrl('admin/plugins.php')) . '">' . __('前往插件管理') . '</a></div>';
    adminFoot();
    exit;
}

if (!class_exists($cls)) {
    adminHead(__('插件配置'), 'plugins.php');
    echo '<h1>' . __('插件配置') . '</h1><div class="notice notice-err">' . __('插件类不存在。') . '</div>';
    adminFoot();
    exit;
}

$msg = $err = '';

// 处理保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $err = __('表单已失效，请重试。');
    }
    // AJAX 导入分派：插件若实现了 ajax() 且请求带 import 步骤，则走 JSON 输出（复用 CSRF 与登录态）
    elseif (($_POST['action'] ?? '') === 'import' && isset($_POST['step']) && method_exists($cls, 'ajax')) {
        header('Content-Type: application/json; charset=utf-8');
        // 强制纯 JSON：清掉 PHP 错误输出（display_errors），异常统一转 JSON 错误信息
        ob_start();
        try {
            $result = call_user_func([$cls, 'ajax'], $_POST, $_FILES);
        } catch (\Throwable $e) {
            $result = ['error' => '导入异常：' . $e->getMessage()];
        }
        ob_end_clean();
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    elseif (method_exists($cls, 'saveConfig')) {
        $result = call_user_func([$cls, 'saveConfig'], $_POST);
        if ($result === true || $result === null) {
            $msg = __('配置已保存。');
        } else {
            $err = (string)$result;
        }
    } else {
        $err = __('该插件不支持配置保存。');
    }
}

// 获取插件元数据
$meta = ['title' => $dir, 'ver' => '', 'desc' => ''];
$src = file_get_contents($file);
if (preg_match('/@Title\s+(.+)/', $src, $m)) $meta['title'] = trim($m[1]);
if (preg_match('/@Desc\s+(.+)/', $src, $m)) $meta['desc'] = trim($m[1]);
if (preg_match('/@Version\s+(.+)/', $src, $m)) $meta['ver'] = trim($m[1]);

adminHead(__('插件配置') . ' - ' . $meta['title'], 'plugins.php');
?>
<h1><?php echo __('插件配置'); ?></h1>
<p style="margin-bottom:16px">
    <a href="<?php echo baseUrl('admin/plugins.php'); ?>">← <?php echo __('返回插件管理'); ?></a>
</p>
<div class="panel">
    <h3 style="margin:0 0 6px;color:var(--g-700)"><?php echo esc($meta['title']); ?>
        <?php if ($meta['ver']): ?><small class="muted">v<?php echo esc($meta['ver']); ?></small><?php endif; ?>
    </h3>
    <?php if ($meta['desc']): ?><p class="muted" style="margin:0 0 14px"><?php echo esc($meta['desc']); ?></p><?php endif; ?>
    <?php if ($msg): ?><div class="notice notice-ok"><?php echo esc($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>

    <?php if (method_exists($cls, 'config')): ?>
        <?php echo call_user_func([$cls, 'config']); ?>
    <?php else: ?>
        <p class="muted"><?php echo __('该插件没有配置页面。'); ?></p>
    <?php endif; ?>
</div>
<?php adminFoot();
