<?php
/**
 * RYE社区（RyeBlog 插件）—— 后台内容管理（主题 / 回复）
 * 路由：admin/plugin.php?p=rye&page=content
 */
require_once __DIR__ . '/../bootstrap.php';
if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

$P = prefix();

// ---- 动作 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'toggle_top' || $act === 'toggle_good' || $act === 'toggle_close' || $act === 'toggle_delete') {
        $tid = (int) ($_POST['id'] ?? 0);
        $col = ['toggle_top' => 'is_top', 'toggle_good' => 'is_good', 'toggle_close' => 'is_closed', 'toggle_delete' => 'is_deleted'][$act];
        dbQuery("UPDATE {$P}threads SET {$col} = 1 - {$col} WHERE id=?", [$tid]);
        // 同步版块计数（删除/恢复时；post_count 口径=真实回复 floor>1，与前台一致）
        if ($act === 'toggle_delete') {
            $f = db_val("SELECT forum_id FROM {$P}threads WHERE id=?", [$tid]);
            $dc = (int) db_val("SELECT COUNT(*) FROM {$P}threads WHERE forum_id=? AND is_deleted=0", [$f]);
            $pc = (int) db_val("SELECT COUNT(*) FROM {$P}posts p JOIN {$P}threads t ON t.id=p.thread_id WHERE t.forum_id=? AND p.is_deleted=0 AND p.floor>1", [$f]);
            dbQuery("UPDATE {$P}forums SET thread_count=?, post_count=? WHERE id=?", [$dc, $pc, $f]);
        }
        set_flash('操作成功', 'success');
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=content'));
        exit;
    }
    if ($act === 'delete_post') {
        $pid = (int) ($_POST['id'] ?? 0);
        $post = db_row("SELECT id, thread_id, user_id, floor, is_deleted FROM {$P}posts WHERE id=?", [$pid]);
        if ($post && !$post['is_deleted']) {
            dbQuery("UPDATE {$P}posts SET is_deleted=1 WHERE id=?", [$pid]);
            // 同步计数：真实回复（floor>1）减主题回复数；重算版块 post_count；刷新用户统计
            if ((int) $post['floor'] > 1) {
                dbQuery("UPDATE {$P}threads SET replies=GREATEST(replies-1,0) WHERE id=?", [$post['thread_id']]);
            }
            $f = (int) db_val("SELECT forum_id FROM {$P}threads WHERE id=?", [$post['thread_id']]);
            $pc = (int) db_val("SELECT COUNT(*) FROM {$P}posts p JOIN {$P}threads t ON t.id=p.thread_id WHERE t.forum_id=? AND p.is_deleted=0 AND p.floor>1", [$f]);
            dbQuery("UPDATE {$P}forums SET post_count=? WHERE id=?", [$pc, $f]);
            ryebbs_recount_user((int) $post['user_id']);
        }
        set_flash('回复已删除', 'success');
        header('Location: ' . baseUrl('admin/plugin.php?p=rye&page=content&thread=' . ((int) ($_POST['thread_id'] ?? 0))));
        exit;
    }
}

$threadId = isset($_GET['thread']) ? (int) $_GET['thread'] : 0;

if ($threadId) {
    // 查看该主题下的回复
    $thread = db_row("SELECT t.*, u.username FROM {$P}threads t LEFT JOIN vd_users u ON u.id=t.user_id WHERE t.id=?", [$threadId]);
    $posts  = db_all("SELECT p.*, u.username FROM {$P}posts p LEFT JOIN vd_users u ON u.id=p.user_id WHERE p.thread_id=? ORDER BY p.floor ASC", [$threadId]);
    adminHead('回复管理 · RYE社区');
    ?>
    <div class="mt-admin-wrap">
        <p><a class="btn-sm" href="?p=rye&page=content">← 返回主题列表</a></p>
        <div class="mt-card">
            <h2>主题：<?php echo e($thread['title'] ?? ''); ?></h2>
            <p class="muted">作者 <?php echo e($thread['username'] ?? ''); ?> · 回复 <?php echo (int) $thread['replies']; ?></p>
        </div>
        <div class="mt-card">
            <h2>全部回复（<?php echo count($posts); ?>）</h2>
            <?php foreach ($posts as $p): ?>
                <div style="border-top:1px solid #eef2ea;padding:10px 0;display:flex;gap:10px;align-items:flex-start">
                    <div style="flex:1">
                        <div class="muted" style="font-size:12px">#<?php echo (int) $p['floor']; ?> · <?php echo e($p['username'] ?? ''); ?> · <?php echo time_ago($p['created_at']); ?><?php if ($p['is_deleted']): ?> · <span style="color:#a33">已删除</span><?php endif; ?></div>
                        <div style="margin-top:4px"><?php echo nl2br(e(mb_strimwidth($p['content'], 0, 200, '…', 'UTF-8'))); ?></div>
                    </div>
                    <?php if (!$p['is_deleted']): ?>
                    <form method="post" onsubmit="return confirm('删除该回复？');"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_post"><input type="hidden" name="id" value="<?php echo $p['id']; ?>"><input type="hidden" name="thread_id" value="<?php echo $threadId; ?>"><button class="btn-sm danger" type="submit">删除</button></form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php adminFoot(); exit;
}

// ---- 主题列表 ----
$fid = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
$kw  = trim($_GET['kw'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perpage = 30;
$where = [];
$params = [];
if ($fid) { $where[] = "t.forum_id=?"; $params[] = $fid; }
if ($kw)  { $where[] = "(t.title LIKE ? OR t.content LIKE ?)"; $params[] = "%$kw%"; $params[] = "%$kw%"; }
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$total = (int) db_val("SELECT COUNT(*) FROM {$P}threads t $wsql", $params);
$p = page_nav($total, $page, $perpage);
$threads = db_all(
    "SELECT t.*, u.username, f.name AS forum_name
     FROM {$P}threads t LEFT JOIN vd_users u ON u.id=t.user_id LEFT JOIN {$P}forums f ON f.id=t.forum_id
     $wsql ORDER BY t.updated_at DESC LIMIT ?, ?",
    array_merge($params, [$p['offset'], $p['perpage']])
);
$forums = db_all("SELECT id, name FROM {$P}forums ORDER BY display_order");
$flash = get_flash();

function ryebbs_thread_action($act, $id, $label, $danger = false, $threadId = 0)
{
    $h = $threadId ? '<input type="hidden" name="thread_id" value="' . $threadId . '">' : '';
    return '<form method="post" style="display:inline" onsubmit="return confirm(\'确定' . $label . '？\');">' . csrf_field() . '<input type="hidden" name="act" value="' . $act . '"><input type="hidden" name="id" value="' . $id . '">' . $h . '<button class="btn-sm ' . ($danger ? 'danger' : '') . '" type="submit">' . $label . '</button></form>';
}

adminHead('内容管理 · RYE社区');
?>
<style>
.mt-admin-wrap{max-width:1100px;margin:0 auto;padding:18px}
.mt-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px;margin-bottom:18px}
.mt-card h2{margin:0 0 12px;font-size:17px;color:#1f3d24}
.mt-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
.mt-filter input,.mt-filter select{border:1px solid #cfd9c8;border-radius:8px;padding:7px 9px;font:inherit}
.mt-table{width:100%;border-collapse:collapse;font-size:14px}
.mt-table th,.mt-table td{padding:9px 10px;border-bottom:1px solid #eef2ea;text-align:left}
.mt-table th{color:#5d6b61;font-weight:600;background:#f6f9f3}
.mt-table tr:hover{background:#fafcf8}
.mt-st{display:inline-block;background:#eaf3e6;color:#2c5234;border-radius:6px;padding:1px 7px;font-size:12px;margin:1px 2px}
.mt-st.danger{background:#fdf3f3;color:#a33}
.btn-sm{padding:4px 10px;font-size:13px;border-radius:7px;border:1px solid #cfd9c8;background:#f6f9f3;cursor:pointer}
.btn-sm.danger{color:#a33;border-color:#e3b7b7;background:#fdf3f3}
.muted{color:#8a968c}
</style>
<div class="mt-admin-wrap">
    <?php if ($flash): ?><div class="flash flash-<?php echo e($flash['type']); ?>" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;background:<?php echo $flash['type']==='success'?'#eaf3e6':'#fdf3f3'; ?>;color:<?php echo $flash['type']==='success'?'#2c5234':'#a33'; ?>"><?php echo e($flash['msg']); ?></div><?php endif; ?>
    <div class="mt-card">
        <h2>主题管理</h2>
        <form class="mt-filter" method="get">
            <input type="hidden" name="p" value="rye"><input type="hidden" name="page" value="content">
            <select name="fid"><option value="0">全部版块</option><?php foreach ($forums as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo $fid==$f['id']?'selected':''; ?>><?php echo e($f['name']); ?></option><?php endforeach; ?></select>
            <input type="text" name="kw" value="<?php echo e($kw); ?>" placeholder="关键词">
            <button class="btn btn-primary" type="submit">筛选</button>
        </form>
        <table class="mt-table">
            <thead><tr><th>标题</th><th>作者</th><th>版块</th><th>回复/浏览</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($threads)): ?><tr><td colspan="6" class="muted">暂无主题。</td></tr><?php endif; ?>
            <?php foreach ($threads as $t): ?>
                <tr>
                    <td><a href="<?php echo e(bbs_url('thread?id=' . $t['id'])); ?>" target="_blank"><?php echo e($t['title']); ?></a></td>
                    <td><?php echo e($t['username'] ?? ''); ?></td>
                    <td><?php echo e($t['forum_name'] ?? ''); ?></td>
                    <td><?php echo (int)$t['replies']; ?> / <?php echo (int)$t['views']; ?></td>
                    <td>
                        <?php if ($t['is_top']): ?><span class="mt-st">置顶</span><?php endif; ?>
                        <?php if ($t['is_good']): ?><span class="mt-st">精华</span><?php endif; ?>
                        <?php if ($t['is_closed']): ?><span class="mt-st danger">已关闭</span><?php endif; ?>
                        <?php if ($t['is_deleted']): ?><span class="mt-st danger">已删除</span><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php echo ryebbs_thread_action('toggle_top', $t['id'], $t['is_top']?'取消置顶':'置顶'); ?>
                        <?php echo ryebbs_thread_action('toggle_good', $t['id'], $t['is_good']?'取消精华':'精华'); ?>
                        <?php echo ryebbs_thread_action('toggle_close', $t['id'], $t['is_closed']?'开启':'关闭'); ?>
                        <a class="btn-sm" href="?p=rye&page=content&thread=<?php echo $t['id']; ?>">回复</a>
                        <?php echo ryebbs_thread_action('toggle_delete', $t['id'], $t['is_deleted']?'恢复':'删除', true); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo pagination_html($total, $page, $perpage, baseUrl('admin/plugin.php?p=rye&page=content&fid=' . $fid . '&kw=' . urlencode($kw))); ?>
    </div>
</div>
<?php adminFoot(); ?>
