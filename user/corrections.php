<?php
/**
 * RyeBlog 用户中心 —— 纠错记录
 */
require_once __DIR__ . '/header.php';
requireUser();

$user = currentUser();
$corrections = getCorrectionsByUser($user['id'], 200);

userHeader('纠错记录', 'corrections.php');
?>
<h1>纠错记录</h1>
<p class="muted" style="margin-top:-12px;margin-bottom:20px">提交的文章纠错建议及处理状态</p>

<?php if (empty($corrections)): ?>
    <div class="uc-empty">还没有提交纠错<br><br><a href="<?php echo homeUrl(); ?>">去阅读文章 →</a></div>
<?php else: ?>
    <?php foreach ($corrections as $cr): ?>
    <div class="uc-item">
        <div class="uc-item-title">
            <a href="<?php echo postUrl(['slug' => $cr['post_slug'], 'id' => $cr['post_id']]); ?>"><?php echo esc($cr['post_title']); ?></a>
            <?php
            $statusBadge = ['pending' => 'badge-pending', 'accepted' => 'badge-accepted', 'rejected' => 'badge-rejected'];
            $statusText  = ['pending' => '待处理', 'accepted' => '已采纳', 'rejected' => '未采纳'];
            $cls = $statusBadge[$cr['status']] ?? 'badge-pending';
            $txt = $statusText[$cr['status']] ?? $cr['status'];
            ?>
            <span class="badge <?php echo $cls; ?>" style="margin-left:8px"><?php echo esc($txt); ?></span>
        </div>
        <div class="uc-item-meta"><?php echo esc($cr['created_at']); ?></div>
        <div class="uc-quote">原文：<?php echo esc($cr['selected_text']); ?></div>
        <div class="uc-suggestion">建议改为：<?php echo esc($cr['suggested_text']); ?></div>
        <?php if (!empty($cr['reason'])): ?>
            <div class="uc-item-content"><strong>理由：</strong><?php echo esc($cr['reason']); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php userFooter();
