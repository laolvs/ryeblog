<?php
/** RyeBlog 后台 —— 附件管理（搜索/筛选/分页/缩略图/批量删除） */
require_once __DIR__ . '/admin.php';

$ok = $err = '';

// 处理 POST 操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { $err = __('CSRF 校验失败'); }
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
            $n = 0;
            foreach ($ids as $id) { deleteAttachment($id); $n++; }
            $ok = $n ? (__('已删除 ') . $n . __(' 个附件')) : __('未选择文件');
        } elseif ($action === 'relink') {
            $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
            $pid = (int)($_POST['pid'] ?? 0);
            if ($ids && $pid) {
                dbQuery('UPDATE vd_attachments SET post_id=? WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$pid], $ids));
                $ok = __('已绑定 ') . count($ids) . __(' 个附件到文章 #') . $pid;
            } else { $err = __('缺少必要参数'); }
        } elseif ($action === 'cleanup-temp') {
            $n = cleanupOldTempAttachments((int)($_POST['hours'] ?? 24));
            $ok = __('已清理 ') . $n . __(' 个 N 小时前的临时附件');
        } elseif ($action === 'cleanup-unused') {
            // 扫描每篇文章，对未引用到的附件进行清理
            $posts = dbAll('SELECT id, content, format FROM vd_posts WHERE type="post"');
            $totalDel = 0;
            foreach ($posts as $p) {
                $keys = attachmentUsedKeysFromContent($p['content'], $p['format'] ?? 'html');
                $totalDel += cleanupUnusedAttachments($p['id'], $keys);
            }
            $ok = __('全站扫描完成，清理了 ') . $totalDel . __(' 个未引用附件');
        }
    }
}

// 一次性触发旧临时附件清理（每次打开页面清理一次 24h+）
$autoClean = cleanupOldTempAttachments(24);

// 查询参数
$q       = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? 'all';   // all | linked | unlinked | image | file | temp
$page    = max(1, (int)($_GET['p'] ?? 1));
$per     = 30;

$where = [];
$params = [];
if ($filter === 'linked')   { $where[] = 'post_id IS NOT NULL'; }
if ($filter === 'unlinked') { $where[] = 'post_id IS NULL'; }
if ($filter === 'image')    { $where[] = "mime LIKE 'image/%'"; }
if ($filter === 'file')     { $where[] = "mime NOT LIKE 'image/%'"; }
if ($q !== '')              { $where[] = '(filename LIKE ? OR filepath LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }

// 统计总数
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$total = (int)(dbOne("SELECT COUNT(*) AS c FROM vd_attachments $wsql", $params)['c'] ?? 0);
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$rows = $total ? dbAll("SELECT * FROM vd_attachments $wsql ORDER BY id DESC LIMIT $per OFFSET $offset", $params) : [];

// 全部文章（下拉，用于"批量绑定到文章"）
$postOpts = dbAll("SELECT id, title FROM vd_posts ORDER BY id DESC LIMIT 200");

adminHead(__('附件管理'), 'attachments.php');
?>
<h1><?php echo __('附件管理'); ?><?php if ($autoClean) echo ' <small style="font-weight:400;color:var(--muted)">（' . __('本次自动清理') . ' ' . $autoClean . ' ' . __('个旧临时附件') . '）</small>'; ?></h1>
<?php if ($ok): ?><div class="notice notice-ok"><?php echo esc($ok); ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice notice-err"><?php echo esc($err); ?></div><?php endif; ?>

<form class="panel" method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="search" name="q" value="<?php echo esc($q); ?>" placeholder="<?php echo __('搜索文件名/路径…'); ?>" style="flex:1;min-width:200px">
    <select name="filter">
        <option value="all"       <?php echo $filter==='all'?'selected':''; ?>><?php echo __('全部'); ?>（<?php echo (int)(dbOne('SELECT COUNT(*) c FROM vd_attachments')['c']); ?>）</option>
        <option value="linked"    <?php echo $filter==='linked'?'selected':''; ?>><?php echo __('已关联文章'); ?></option>
        <option value="unlinked"  <?php echo $filter==='unlinked'?'selected':''; ?>><?php echo __('未关联'); ?></option>
        <option value="temp"      <?php echo $filter==='temp'?'selected':''; ?>><?php echo __('临时（写入中）'); ?></option>
        <option value="image"     <?php echo $filter==='image'?'selected':''; ?>><?php echo __('仅图片'); ?></option>
        <option value="file"      <?php echo $filter==='file'?'selected':''; ?>><?php echo __('仅非图片'); ?></option>
    </select>
    <button class="btn" type="submit"><?php echo __('查询'); ?></button>
    <a class="btn btn-ghost" href="<?php echo baseUrl('admin/attachments.php'); ?>"><?php echo __('重置'); ?></a>
</form>

<form method="post" id="batch-form">
    <input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="action" value="delete" id="batch-action">

    <div style="margin:6px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <label style="margin:0;display:flex;align-items:center;gap:6px;font-weight:500">
            <input type="checkbox" id="check-all"> <?php echo __('全选当前页'); ?>
        </label>
        <button type="button" class="btn btn-danger btn-sm" onclick="batchAct('delete')">🗑 <?php echo __('批量删除'); ?></button>
        <span class="muted">|</span>
        <select name="pid" style="max-width:240px">
            <option value="">— <?php echo __('关联到文章'); ?> —</option>
            <?php foreach ($postOpts as $po): ?>
                <option value="<?php echo (int)$po['id']; ?>">#<?php echo (int)$po['id']; ?> · <?php echo esc(mb_substr($po['title'], 0, 40)); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-ghost btn-sm" onclick="batchAct('relink')">🔗 <?php echo __('批量绑定'); ?></button>
        <span class="muted">|</span>
        <button type="button" class="btn btn-ghost btn-sm" onclick="batchAct('cleanup-temp', '?hours=24')">🧹 <?php echo __('清理 24h+ 临时附件'); ?></button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="batchAct('cleanup-unused')">♻ <?php echo __('全站扫描清理未引用'); ?></button>
    </div>

    <table class="data-table" style="width:100%">
        <tr>
            <th style="width:32px"></th>
            <th style="width:64px"><?php echo __('预览'); ?></th>
            <th><?php echo __('文件名'); ?></th>
            <th style="width:80px"><?php echo __('大小'); ?></th>
            <th><?php echo __('类型'); ?></th>
            <th><?php echo __('关联'); ?></th>
            <th>URL / <?php echo __('路径'); ?></th>
            <th style="width:140px"><?php echo __('时间'); ?></th>
        </tr>
        <?php foreach ($rows as $a):
            $abs = RYEBLOG_ROOT . '/' . ltrim($a['filepath'], '/');
            $isImg = strpos($a['mime'], 'image/') === 0;
            $exists = is_file($abs);
        ?>
        <tr>
            <td><input type="checkbox" name="ids[]" value="<?php echo (int)$a['id']; ?>" class="row-check"></td>
            <td>
                <?php if ($isImg && $exists): ?>
                    <img src="<?php echo esc(baseUrl($a['filepath'])); ?>" alt="" style="width:60px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--line)">
                <?php else: ?>
                    <div style="width:60px;height:48px;border-radius:6px;background:var(--g-050);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:1.5rem"><?php
                        $ext = strtolower(pathinfo($a['filename'], PATHINFO_EXTENSION));
                        echo in_array($ext, ['pdf']) ? '📕' :
                            (in_array($ext, ['zip','rar','7z']) ? '🗜' :
                            (in_array($ext, ['mp3','wav','flac','m4a']) ? '🎵' :
                            (in_array($ext, ['mp4','mov','avi','mkv']) ? '🎬' :
                            (in_array($ext, ['doc','docx','rtf']) ? '📄' :
                            (in_array($ext, ['xls','xlsx','csv']) ? '📊' :
                            (in_array($ext, ['ppt','pptx']) ? '🎞' : '📎')))))); ?></div>
                <?php endif; ?>
            </td>
            <td>
                <strong><?php echo esc($a['filename']); ?></strong>
                <?php if (!$exists): ?><span class="tag" style="background:#fdecea;color:#b3261e"><?php echo __('丢失'); ?></span><?php endif; ?>
            </td>
            <td><?php echo round($a['filesize']/1024, 1); ?> KB</td>
            <td><span class="tag"><?php echo esc($a['mime'] ?: 'unknown'); ?></span></td>
            <td>
                <?php if (!empty($a['post_id'])): ?>
                    <a href="<?php echo baseUrl('admin/write.php?id=' . (int)$a['post_id']); ?>">#<?php echo (int)$a['post_id']; ?></a>
                <?php else: ?>
                    <span class="tag" style="background:#fff3cd;color:#856404"><?php echo __('未关联'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <input type="text" readonly value="<?php echo esc(baseUrl($a['filepath'])); ?>" style="font-size:11px;color:var(--muted)" onclick="this.select()">
            </td>
            <td><?php echo esc(formatDate($a['created_at'] ?? '', 'Y-m-d H:i')); ?><br><span class="muted" style="font-size:.75rem">ID <?php echo (int)$a['id']; ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px"><?php echo __('暂无附件'); ?></td></tr>
        <?php endif; ?>
    </table>

    <p class="muted" style="margin:10px 0"><?php echo __('共'); ?> <?php echo (int)$total; ?> <?php echo __('个附件'); ?><?php echo $pages > 1 ? ' · ' . __('第') . ' ' . $page . '/' . $pages . ' ' . __('页') : ''; ?></p>
    <?php if ($pages > 1): ?>
    <p style="display:flex;gap:6px;flex-wrap:wrap">
        <?php
            $baseQ = $_GET; unset($baseQ['p']);
            $qs = http_build_query($baseQ);
            $url = function ($i) use ($qs) {
                return baseUrl('admin/attachments.php?' . ($qs ? $qs . '&' : '') . 'p=' . $i);
            };
        ?>
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?php echo $url($page-1); ?>">« <?php echo __('上一页'); ?></a><?php endif; ?>
        <?php
            $from = max(1, $page - 2);
            $to   = min($pages, $page + 2);
            for ($i = $from; $i <= $to; $i++):
        ?>
            <?php if ($i === $page): ?>
                <span class="btn btn-sm" style="background:var(--g-700)"><?php echo $i; ?></span>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?php echo $url($i); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?php echo $url($page+1); ?>"><?php echo __('下一页'); ?> »</a><?php endif; ?>
    </p>
    <?php endif; ?>
</form>

<script>
document.getElementById('check-all')?.addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(function (c) { c.checked = this.checked; }.bind(this));
});
function batchAct(act, extra) {
    if (act === 'delete') {
        var checked = document.querySelectorAll('.row-check:checked').length;
        if (checked === 0) return alert('<?php echo __('请先勾选要删除的附件'); ?>');
        if (!confirm('<?php echo __('确定删除 '); ?>' + checked + '<?php echo __(' 个附件？'); ?>')) return;
    } else if (act === 'relink') {
        var pid = document.querySelector('#batch-form select[name=pid]').value;
        if (!pid) return alert('<?php echo __('请先在下拉框中选择目标文章'); ?>');
        var checked = document.querySelectorAll('.row-check:checked').length;
        if (checked === 0) return alert('<?php echo __('请先勾选要绑定的附件'); ?>');
        if (!confirm('<?php echo __('把 '); ?>' + checked + '<?php echo __(' 个附件绑定到文章 #'); ?>' + pid + '<?php echo __('？'); ?>')) return;
    } else if (act === 'cleanup-unused') {
        if (!confirm('<?php echo __('将扫描全部文章，将未引用的附件一次性清理（删 db + 删文件）。继续？'); ?>')) return;
    } else if (act === 'cleanup-temp') {
        var h = parseInt((extra || '').replace(/[^\d]/g, '') || 24, 10);
        if (!confirm('<?php echo __('清理 '); ?>' + h + '<?php echo __(' 小时前未绑定的临时附件？'); ?>')) return;
        var fd = new FormData();
        fd.append('_csrf', document.querySelector('[name=_csrf]').value);
        fd.append('action', 'cleanup-temp');
        fd.append('hours', h);
        fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function () { window.location.reload(); });
        return;
    }
    document.getElementById('batch-action').value = act;
    document.getElementById('batch-form').submit();
}
</script>
<?php adminFoot();
