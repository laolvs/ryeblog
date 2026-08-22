<?php
/**
 * RyeBlog 数据导入导出插件
 *
 * 支持 WordPress WXR XML、Typecho XML 和 SQL 文件导入。
 * 支持 RyeBlog XML 和 SQL 文件导出（可用于网站恢复）。
 * 自动下载远程图片到本地，保留 slug 保证 URL 一致。
 *
 * @Title    数据导入导出
 * @Desc     导入：WordPress/Typecho XML + SQL，自动下载远程图片；导出：RyeBlog XML/SQL，可恢复网站
 * @Version  2.0.0
 * @Author   RyeBlog Team
 */

class Plugin_data_import
{
    /**
     * 配置页面 —— 导入 + 导出界面
     */
    public static function config()
    {
        $lastResult = getOption('data_import_last_result', '');
        $csrf = csrfToken();

        // 统计当前数据
        $postCount = (int)dbOne("SELECT COUNT(*) c FROM vd_posts WHERE type='post'")['c'];
        $pageCount = (int)dbOne("SELECT COUNT(*) c FROM vd_posts WHERE type='page'")['c'];
        $catCount  = (int)dbOne("SELECT COUNT(*) c FROM vd_categories")['c'];
        $tagCount  = (int)dbOne("SELECT COUNT(*) c FROM vd_tags")['c'];
        $cmtCount  = (int)dbOne("SELECT COUNT(*) c FROM vd_comments")['c'];

        // 导出文件列表（下载/删除走后台 backups.php，不暴露 web 直链；usr/uploads/export/ 已被 nginx 禁访）
        $exportDir = RYEBLOG_ROOT . '/usr/uploads/export';
        $exportFiles = [];
        $exportCsrf = csrfToken();
        if (is_dir($exportDir)) {
            $files = glob($exportDir . '/*.{xml,sql}', GLOB_BRACE);
            if ($files) {
                rsort($files);
                foreach (array_slice($files, 0, 10) as $f) {
                    $basename = basename($f);
                    $exportFiles[] = [
                        'name' => $basename,
                        'size' => filesize($f),
                        'url'  => baseUrl('admin/backups.php?download=' . rawurlencode($basename)),
                        'del'  => baseUrl('admin/backups.php?delete=' . rawurlencode($basename) . '&_csrf=' . $exportCsrf),
                        'time' => date('Y-m-d H:i:s', filemtime($f)),
                    ];
                }
            }
        }

        // 导出文件列表 HTML
        $exportListHtml = '';
        if ($exportFiles) {
            $exportListHtml = '<div style="margin-top:14px"><h5 style="margin:0 0 8px;color:var(--g-600)">历史导出文件</h5>';
            foreach ($exportFiles as $ef) {
                $sizeKb = round($ef['size'] / 1024, 1);
                $exportListHtml .= "<div style=\"display:flex;align-items:center;gap:8px;margin-bottom:6px;padding:6px 10px;background:var(--g-50);border-radius:6px\">";
                $exportListHtml .= "<a href=\"{$ef['url']}\" download style=\"color:var(--accent);text-decoration:none;font-weight:600\">{$ef['name']}</a>";
                $exportListHtml .= "<span style=\"color:var(--g-500);font-size:13px\">{$sizeKb} KB · {$ef['time']}</span>";
                $exportListHtml .= "<a href=\"{$ef['del']}\" onclick=\"return confirm('确定删除该导出文件？')\" style=\"margin-left:auto;color:#c33;font-size:12px;text-decoration:none\">删除</a>";
                $exportListHtml .= '</div>';
            }
            $exportListHtml .= '</div>';
        }

        // 作者映射下拉选项（默认选中当前登录管理员）
        $curUser = function_exists('getAdminUser') ? getAdminUser() : null;
        $curName = !empty($curUser['username']) ? $curUser['username'] : 'admin';
        $authorOptionsHtml = '';
        $allUsers = dbAll('SELECT id, username, role FROM vd_users ORDER BY id');
        foreach ($allUsers as $u) {
            $sel = ($u['username'] === $curName) ? ' selected' : '';
            $authorOptionsHtml .= '<option value="' . esc($u['username']) . '"' . $sel . '>'
                . esc($u['username']) . '（' . esc($u['role']) . '）</option>';
        }

        // 目标分类下拉（导入后文章统一归入所选分类）
        $categoryOptionsHtml = '';
        foreach (dbAll('SELECT id, name FROM vd_categories ORDER BY id') as $cat) {
            $categoryOptionsHtml .= '<option value="' . (int)$cat['id'] . '">' . esc($cat['name']) . '</option>';
        }

        $html = <<<HTML
<style>
.rye-tabs{display:flex;gap:0;margin-bottom:0;border-bottom:2px solid var(--g-200)}
.rye-tabs button{padding:10px 24px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:600;color:var(--g-400);border-bottom:2px solid transparent;margin-bottom:-2px}
.rye-tabs button.active{color:var(--accent);border-bottom-color:var(--accent)}
.rye-tab-pane{display:none;padding-top:16px}
.rye-tab-pane.active{display:block}
.rye-stat{display:inline-block;padding:2px 8px;background:var(--g-50);border-radius:4px;font-size:13px;color:var(--g-600);margin-right:6px}
.rye-stat b{color:var(--accent)}
.rye-progress-wrap{margin:14px 0;display:none}
.rye-progress-bar{height:14px;background:var(--g-100);border-radius:8px;overflow:hidden;border:1px solid var(--line)}
.rye-progress-fill{height:100%;width:0;background:linear-gradient(90deg,var(--accent),#7c5cff);transition:width .25s ease;border-radius:8px}
.rye-log{margin-top:12px;max-height:280px;overflow:auto;background:var(--g-50);border:1px solid var(--line);border-radius:8px;padding:10px 12px;font-size:12.5px;line-height:1.7;color:var(--g-600);font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.rye-log div{border-bottom:1px dashed var(--g-200);padding:2px 0}
.rye-log .rye-log-err{color:#d23f3f;font-weight:600}
.rye-log .rye-log-ok{color:#2e9e5b;font-weight:600}
.rye-counts{margin:8px 0 4px;font-size:13px;color:var(--g-600)}
.rye-counts b{color:var(--accent)}
.rye-phase{margin:6px 0;font-size:13px;color:var(--g-500)}
</style>

<div class="rye-tabs">
    <button type="button" class="active" onclick="ryeSwitchTab(event,'import')">导入数据</button>
    <button type="button" onclick="ryeSwitchTab(event,'export')">导出数据</button>
    <button type="button" onclick="ryeSwitchTab(event,'repair')">修复图片</button>
</div>

<div class="rye-tab-pane active" id="tab-import">

  <!-- 第一步：选择文件与选项 -->
  <form id="rye-import-form">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <input type="hidden" name="action" value="import">

    <h4 style="margin:0 0 10px;color:var(--g-700)">数据源类型</h4>
    <div style="margin-bottom:14px">
        <label style="display:inline-block;margin-right:18px;cursor:pointer">
            <input type="radio" name="source_type" value="wordpress_xml" checked> WordPress XML（WXR 标准导出）
        </label>
        <label style="display:inline-block;margin-right:18px;cursor:pointer">
            <input type="radio" name="source_type" value="typecho_xml"> Typecho XML
        </label>
        <label style="display:inline-block;cursor:pointer">
            <input type="radio" name="source_type" value="sql_file"> SQL 文件
        </label>
    </div>

    <label>选择导入文件</label>
    <input type="file" name="import_file" accept=".xml,.sql,.txt" required style="margin-bottom:14px">

    <h4 style="margin:0 0 8px;color:var(--g-700)">导入选项</h4>
    <label style="display:block;margin-bottom:6px;cursor:pointer">
        <input type="checkbox" name="download_remote_images" value="1" checked>
        下载远程图片到本地（自动替换正文中的远程图片 URL）
    </label>
    <label style="display:block;margin-bottom:6px;cursor:pointer">
        <input type="checkbox" name="preserve_slug" value="1" checked>
        保留原 slug（保证 URL 与原博客一致）
    </label>
    <label style="display:block;margin-bottom:6px;cursor:pointer">
        <input type="checkbox" name="import_comments" value="1" checked>
        导入评论
    </label>
    <label style="display:block;margin-bottom:14px;cursor:pointer">
        <input type="checkbox" name="skip_existing" value="1" checked>
        跳过已存在的文章（按 slug 判断）
    </label>

    <label style="display:block;margin-bottom:8px;font-weight:600;color:var(--g-700)">导入后文章分类</label>
    <select name="category_mode" id="rye-cat-mode" style="width:100%;max-width:360px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;margin-bottom:6px">
        <option value="keep">保留原分类（按源博客分类导入）</option>
        <option value="uncategorized">全部归入未分类</option>
        <option value="specific">全部归入指定分类</option>
    </select>
    <div id="rye-cat-specific" style="display:none;margin-bottom:6px">
        <select name="category_id" style="width:100%;max-width:360px;padding:10px 12px;border:1px solid var(--line);border-radius:8px">
{$categoryOptionsHtml}
        </select>
    </div>
    <p style="margin:0 0 14px;color:var(--g-500);font-size:12px">如选「全部归入指定分类」，可把老博客的文章统一放进你新建的分类（比如把 Wordpress 迁移的文章全部归入「旧站存档」）。</p>

    <label style="display:block;margin-bottom:8px;font-weight:600;color:var(--g-700)">作者映射（导入文章的作者统一归到）</label>
    <select name="import_author" style="width:100%;max-width:360px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;margin-bottom:6px">
{$authorOptionsHtml}
    </select>
    <p style="margin:0 0 14px;color:var(--g-500);font-size:12px">源博客的作者名（常为 admin 或原博主）不会与本地账号匹配，导入后统一归属到所选用户，避免作者名对不上而需进数据库手工修改。不影响登录账号安全。</p>

    <p style="margin:0 0 8px"><button class="btn" type="submit" id="rye-import-start">开始导入</button></p>
    <p style="margin:0 0 8px;color:var(--g-500);font-size:13px">提交后会先“分析”文件（秒回），再分批次逐步导入并实时显示进度，不会再出现整页卡死、误以为崩溃的情况。</p>
  </form>

  <!-- 第二步：进度与日志 -->
  <div class="rye-progress-wrap" id="rye-progress-wrap">
    <div class="rye-phase" id="rye-phase">准备中…</div>
    <div class="rye-progress-bar"><div class="rye-progress-fill" id="rye-progress-fill"></div></div>
    <div class="rye-counts" id="rye-counts"></div>
    <div class="rye-log" id="rye-log"></div>
    <p style="margin:10px 0 0"><button class="btn" type="button" id="rye-import-cancel" style="display:none">取消导入</button>
       <button class="btn" type="button" id="rye-import-reset" style="display:none">再导入一个文件</button></p>
  </div>
</div>

<div class="rye-tab-pane" id="tab-export">
<form method="post" id="export-form">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <input type="hidden" name="action" value="export">

    <div style="margin-bottom:14px">
        <span class="rye-stat">文章 <b>{$postCount}</b></span>
        <span class="rye-stat">页面 <b>{$pageCount}</b></span>
        <span class="rye-stat">分类 <b>{$catCount}</b></span>
        <span class="rye-stat">标签 <b>{$tagCount}</b></span>
        <span class="rye-stat">评论 <b>{$cmtCount}</b></span>
    </div>

    <h4 style="margin:0 0 10px;color:var(--g-700)">导出格式</h4>
    <div style="margin-bottom:14px">
        <label style="display:inline-block;margin-right:18px;cursor:pointer">
            <input type="radio" name="export_format" value="xml" checked> RyeBlog XML（完整结构化备份）
        </label>
        <label style="display:inline-block;margin-right:18px;cursor:pointer">
            <input type="radio" name="export_format" value="sql"> SQL 文件（phpMyAdmin 可直接导入）
        </label>
    </div>

    <h4 style="margin:0 0 8px;color:var(--g-700)">导出选项</h4>
    <label style="display:block;margin-bottom:6px;cursor:pointer">
        <input type="checkbox" name="export_posts" value="1" checked>
        导出文章和页面
    </label>
    <label style="display:block;margin-bottom:6px;cursor:pointer">
        <input type="checkbox" name="export_categories" value="1" checked>
        导出分类和标签
    </label>
    <label style="display:block;margin-bottom:6px;cursor:pointer">
        <input type="checkbox" name="export_comments" value="1" checked>
        导出评论
    </label>
    <label style="display:block;margin-bottom:6px;cursor:pointer">
        <input type="checkbox" name="export_settings" value="1" checked>
        导出站点设置（选项表中的站点配置）
    </label>
    <label style="display:block;margin-bottom:14px;cursor:pointer">
        <input type="checkbox" name="export_truncate" value="1">
        SQL 导出时包含 DELETE 清表语句（导入时先清空再写入，避免重复）
    </label>

    <p style="margin:0 0 8px;color:var(--g-500);font-size:13px">导出文件保存在 usr/uploads/export/ 目录，可随时下载。</p>
    <p style="margin:0 0 8px"><button class="btn" type="submit">开始导出</button></p>
</form>

{$exportListHtml}
</div>

<div class="rye-tab-pane" id="tab-repair">
<form method="post">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <input type="hidden" name="action" value="repair_images">
    <h4 style="margin:0 0 10px;color:var(--g-700)">重新下载并本地化远程图片</h4>
    <p style="margin:0 0 10px;color:var(--g-500);font-size:13px">扫描已导入文章正文里的远程图片（如旧博客的图片链接），下载到本地 <code>usr/uploads/import/</code> 并替换链接。可重复执行，已本地化的不会重复下载。</p>
    <label style="display:block;margin-bottom:8px">范围：
        <select name="repair_scope">
            <option value="all">全部文章和页面</option>
            <option value="post">仅文章</option>
            <option value="page">仅页面</option>
        </select>
    </label>
    <p style="margin:0 0 10px">或指定文章 ID（逗号分隔，留空则按上面范围）：<br>
        <input type="text" name="repair_ids" placeholder="如 10717,10802" style="width:240px;margin-top:6px">
    </p>
    <p style="margin:0 0 8px"><button class="btn" type="submit">开始修复</button></p>
</form>
</div>

<script>
function ryeSwitchTab(e,tab){
    document.querySelectorAll('.rye-tabs button').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.rye-tab-pane').forEach(p=>p.classList.remove('active'));
    e.target.classList.add('active');
    document.getElementById('tab-'+tab).classList.add('active');
}
// 分类模式联动：选「指定分类」时显示分类下拉
(function(){
    var mode = document.getElementById('rye-cat-mode');
    var box  = document.getElementById('rye-cat-specific');
    if (mode && box) {
        var sync = function(){ box.style.display = mode.value === 'specific' ? 'block' : 'none'; };
        mode.addEventListener('change', sync);
        sync();
    }
})();

/* ===== 分步导入向导：analyze → 循环 chunk，带进度条与实时日志 ===== */
(function(){
    var form = document.getElementById('rye-import-form');
    if(!form) return;
    var wrap = document.getElementById('rye-progress-wrap');
    var phaseEl = document.getElementById('rye-phase');
    var fillEl = document.getElementById('rye-progress-fill');
    var countsEl = document.getElementById('rye-counts');
    var logEl = document.getElementById('rye-log');
    var cancelBtn = document.getElementById('rye-import-cancel');
    var resetBtn = document.getElementById('rye-import-reset');
    var aborted = false;

    function setPhase(t){ phaseEl.textContent = t; }
    function setProgress(done, total){
        var pct = total > 0 ? Math.min(100, Math.round(done/total*100)) : 0;
        fillEl.style.width = pct + '%';
    }
    function appendLog(lines, cls){
        if(!lines) return;
        if(typeof lines === 'string') lines = [lines];
        lines.forEach(function(l){
            var d = document.createElement('div');
            if(cls) d.className = cls;
            d.textContent = l;
            logEl.appendChild(d);
        });
        logEl.scrollTop = logEl.scrollHeight;
    }
    function showCounts(c){
        if(!c) return;
        var parts = [];
        if(c.categories!=null) parts.push('分类 <b>'+c.categories+'</b>');
        if(c.tags!=null) parts.push('标签 <b>'+c.tags+'</b>');
        if(c.posts!=null) parts.push('文章 <b>'+c.posts+'</b>');
        if(c.pages!=null) parts.push('页面 <b>'+c.pages+'</b>');
        if(c.comments!=null) parts.push('评论 <b>'+c.comments+'</b>');
        if(c.images!=null) parts.push('远程图片 <b>'+c.images+'</b>');
        if(c.total!=null) parts.push('待导入条目 <b>'+c.total+'</b>');
        countsEl.innerHTML = '分析完成，共：' + parts.join(' · ');
    }

    function post(fd){
        return fetch(location.href, {method:'POST', body: fd, credentials:'same-origin', cache:'no-store'})
            .then(function(r){ return r.json(); });
    }

    form.addEventListener('submit', function(e){
        e.preventDefault();
        aborted = false;
        var fd = new FormData(form);
        fd.set('step','analyze');
        form.style.display = 'none';
        wrap.style.display = 'block';
        logEl.innerHTML = '';
        countsEl.innerHTML = '';
        setProgress(0, 100);
        cancelBtn.style.display = 'none';
        resetBtn.style.display = 'none';
        setPhase('正在分析文件，解析导入计划（这一步很快）…');
        post(fd).then(function(data){
            if(data.error){ setPhase('分析失败'); appendLog(data.error, 'rye-log-err'); cancelBtn.style.display='none'; resetBtn.style.display=''; return; }
            showCounts(data.counts);
            if(!data.counts || !data.counts.total){ setPhase('没有可导入的内容。'); resetBtn.style.display=''; return; }
            runChunks(fd.get('_csrf'), data.token, data.counts.total);
        }).catch(function(err){
            setPhase('请求出错'); appendLog('网络错误：' + err.message, 'rye-log-err'); resetBtn.style.display='';
        });
    });

    function runChunks(csrf, token, total){
        var offset = 0;
        cancelBtn.style.display = '';
        setPhase('开始导入…');
        function next(){
            if(aborted){ setPhase('已取消导入。'); resetBtn.style.display=''; return; }
            var cfd = new FormData();
            cfd.set('_csrf', csrf);
            cfd.set('action','import');
            cfd.set('step','chunk');
            cfd.set('token', token);
            cfd.set('offset', offset);
            post(cfd).then(function(cr){
                if(cr.error){ setPhase('导入中断'); appendLog(cr.error, 'rye-log-err'); resetBtn.style.display=''; return; }
                setProgress(cr.done, cr.total);
                appendLog(cr.log);
                offset = cr.offset_next;
                if(cr.finished){
                    setPhase('导入完成');
                    appendLog(cr.summary, 'rye-log-ok');
                    cancelBtn.style.display = 'none';
                    resetBtn.style.display = '';
                } else {
                    setPhase('正在导入（已处理 ' + cr.done + ' / ' + cr.total + '）…');
                    // 让出一帧，避免浏览器“假死”观感
                    setTimeout(next, 0);
                }
            }).catch(function(err){
                setPhase('请求出错'); appendLog('网络错误：' + err.message, 'rye-log-err'); resetBtn.style.display='';
            });
        }
        next();
    }

    cancelBtn.addEventListener('click', function(){ aborted = true; });
    resetBtn.addEventListener('click', function(){
        form.reset();
        form.style.display = '';
        wrap.style.display = 'none';
        logEl.innerHTML = '';
        countsEl.innerHTML = '';
        setProgress(0,100);
    });
})();
</script>
HTML;

        if ($lastResult) {
            $html .= '<div class="notice notice-ok" style="margin-top:20px">' . $lastResult . '</div>';
        }

        return $html;
    }

    /**
     * 插件内容管理入口（后台「插件内容管理」页会收集各插件的此方法）
     * 返回该插件「内容添加 / 编辑」入口数组，便于统一入口管理。
     * @return array [{label,url,desc,icon}]
     */
    public static function contentMenu()
    {
        return [
            [
                'label' => '数据导入 / 导出',
                'url'   => baseUrl('admin/plugin-config.php?dir=data-import'),
                'desc'  => '从 WordPress / Typecho 导入数据，或导出备份；含「修复图片」',
                'icon'  => '📥',
            ],
        ];
    }

    /**
     * 保存配置（处理导入 / 导出）
     */
    public static function saveConfig($post)
    {
        $action = $post['action'] ?? '';

        if ($action === 'import') {
            return self::handleImport($post);
        } elseif ($action === 'export') {
            return self::handleExport($post);
        } elseif ($action === 'repair_images') {
            return self::handleRepairImages($post);
        }

        return '无效的操作。';
    }

    /**
     * 处理导入
     */
    private static function handleImport($post)
    {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            return '请选择要导入的文件。';
        }

        // 取消执行时间限制，防止大文件导入超时
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $sourceType = $post['source_type'] ?? 'wordpress_xml';
        $options = self::importOpts($post);

        $tmpFile = $_FILES['import_file']['tmp_name'];

        try {
            // 兼容无 JS 环境：直接整段跑完（可能假死，仅作兜底；推荐走 AJAX 分步导入）
            $plan = self::buildPlan($tmpFile, $sourceType, $options);
            self::savePlan($plan);
            $offset = 0;
            $batch = 50;
            do {
                $r = self::importChunk($plan, $offset, $batch);
                $offset = $r['offset_next'];
            } while (empty($r['finished']));
            return true;
        } catch (\Exception $e) {
            return '导入失败：' . $e->getMessage();
        }
    }

    /**
     * 处理导出
     */
    private static function handleExport($post)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $format = $post['export_format'] ?? 'xml';
        $opts = [
            'export_posts'      => isset($post['export_posts']),
            'export_categories' => isset($post['export_categories']),
            'export_comments'   => isset($post['export_comments']),
            'export_settings'   => isset($post['export_settings']),
            'export_truncate'   => isset($post['export_truncate']),
        ];

        try {
            $exportDir = RYEBLOG_ROOT . '/usr/uploads/export';
            if (!is_dir($exportDir)) @mkdir($exportDir, 0755, true);

            $dateStr = date('Y-m-d-His');
            $rand    = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

            if ($format === 'xml') {
                $filename = "ryeblog-export-{$dateStr}-{$rand}.xml";
                $filepath = $exportDir . '/' . $filename;
                $stats = self::exportRyeBlogXml($filepath, $opts);
            } else {
                $filename = "ryeblog-export-{$dateStr}-{$rand}.sql";
                $filepath = $exportDir . '/' . $filename;
                $stats = self::exportRyeBlogSql($filepath, $opts);
            }

            // 下载走后台 backups.php（PHP 流输出 + 仅管理员），不走 web 直链（usr/uploads/export/ 已被 nginx 禁访）
            $downloadUrl = baseUrl('admin/backups.php?download=' . rawurlencode($filename));
            $summary = sprintf(
                "导出完成！%s 文件 <b>%s</b>（%.1f KB）。文章 %d，页面 %d，分类 %d，标签 %d，评论 %d，设置 %d。" .
                "<br><a href='%s' download style='color:var(--accent);font-weight:600'>点击下载</a>",
                strtoupper($format),
                $filename,
                filesize($filepath) / 1024,
                $stats['posts'], $stats['pages'], $stats['categories'],
                $stats['tags'], $stats['comments'], $stats['settings'],
                $downloadUrl
            );

            setOption('data_import_last_result', $summary);
            return true;
        } catch (\Exception $e) {
            return '导出失败：' . $e->getMessage();
        }
    }

    /**
     * 修复已导入文章中的远程图片
     * 扫描全部（或指定）文章/页面正文，把其中的远程图片下载到本地并替换 URL。
     * 幂等：已本地化的链接不会重复下载；失败的远程链接保留原样。
     *
     * @param array $post 表单数据，可含 repair_scope(all|post|page) 或 repair_ids(逗号分隔)
     * @return bool|string
     */
    public static function handleRepairImages($post)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $post = is_array($post) ? $post : [];
        $where = "type IN ('post','page')";

        $scope = $post['repair_scope'] ?? 'all';
        if ($scope === 'post') {
            $where = "type='post'";
        } elseif ($scope === 'page') {
            $where = "type='page'";
        }

        if (!empty($post['repair_ids'])) {
            $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', trim($post['repair_ids']))), function ($x) {
                return $x > 0;
            });
            if (!empty($ids)) {
                $where = 'id IN (' . implode(',', $ids) . ')';
            }
        }

        $rows = dbAll("SELECT id, content, format FROM vd_posts WHERE {$where}");
        if ($rows === false) {
            return '查询文章失败。';
        }

        $scanned = 0;
        $fixed = 0;
        $downloaded = 0;
        $failedLinks = 0;
        $failedDomains = [];

        foreach ($rows as $r) {
            $scanned++;
            $content = $r['content'];
            if (!is_string($content) || $content === '') {
                continue;
            }
            $format = ($r['format'] === 'markdown') ? 'markdown' : 'html';

            // 没有远程图片可跳过
            if (empty(scanRemoteImages($content, $format))) {
                continue;
            }

            $result = self::downloadImagesInContent($content, $format, 12);
            if ($result['content'] !== $content) {
                dbQuery('UPDATE vd_posts SET content=? WHERE id=?', [$result['content'], $r['id']]);
                $fixed++;
                $downloaded += (int)$result['count'];
            }
            // 统计未能下载、仍留在正文里的远程图（源站失效/防盗链/404 等）
            $stillRemote = scanRemoteImages($result['content'], $format);
            if (!empty($stillRemote)) {
                $failedLinks += count($stillRemote);
                foreach ($stillRemote as $u) {
                    $host = parse_url($u, PHP_URL_HOST);
                    if ($host) $failedDomains[strtolower($host)] = true;
                }
            }
        }

        $domainList = $failedDomains ? ('，涉及域名：' . implode('、', array_keys($failedDomains))) : '';
        $summary = sprintf(
            "✅ 图片修复完成！扫描 %d 篇，修复 %d 篇，下载本地图片 %d 张，%d 个远程图片链接无法下载（可能源站已失效）%s。",
            $scanned, $fixed, $downloaded, $failedLinks, $domainList
        );
        setOption('data_import_last_result', $summary);

        return true;
    }

        // ===================== 统一「解析计划 + 分步导入」引擎 =====================
    // 把原来“单个请求内同步完成『解析+逐条插入+下载图片』”的阻塞式导入，
    // 拆成「analyze（解析成计划，秒回）→ chunk（分批改写，带进度）”两段，
    // 彻底解决大文件导入时后台界面假死、用户以为程序崩溃的问题。

    private static function planDir()
    {
        $dir = RYEBLOG_ROOT . '/usr/uploads/import';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    private static function planPath($token)
    {
        $token = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$token);
        return self::planDir() . '/_plan_' . $token . '.json';
    }

    private static function progPath($token)
    {
        $token = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$token);
        return self::planDir() . '/_prog_' . $token . '.json';
    }

    private static function newToken()
    {
        return bin2hex(random_bytes(12)) . time();
    }

    private static function initStats()
    {
        return ['categories' => 0, 'tags' => 0, 'posts' => 0, 'pages' => 0, 'comments' => 0, 'images' => 0, 'images_failed' => 0, 'skipped' => 0, 'queries' => 0];
    }

    private static function importOpts($post)
    {
        return [
            'download_remote_images' => isset($post['download_remote_images']),
            'preserve_slug'          => isset($post['preserve_slug']),
            'import_comments'        => isset($post['import_comments']),
            'skip_existing'          => isset($post['skip_existing']),
            'import_author'          => trim((string)($post['import_author'] ?? '')),
            'category_mode'          => trim((string)($post['category_mode'] ?? 'keep')),
            'category_id'            => (int)($post['category_id'] ?? 0),
        ];
    }

    /**
     * 解析源文件为归一化导入计划（纯数据，不写库），保存到磁盘并返回计划数组。
     */
    public static function buildPlan($file, $sourceType, $opts)
    {
        $token = self::newToken();
        if ($sourceType === 'wordpress_xml') {
            $plan = self::buildPlanWordPressXml($file, $opts);
        } elseif ($sourceType === 'typecho_xml') {
            $plan = self::buildPlanTypechoXml($file, $opts);
        } elseif ($sourceType === 'sql_file') {
            $plan = self::buildPlanSql($file, $opts);
        } else {
            throw new \Exception('不支持的数据源类型。');
        }
        $plan['token'] = $token;
        $plan['source_type'] = $sourceType;
        $plan['options'] = $opts;
        $plan['max_id_before'] = (int)(dbOne('SELECT MAX(id) c FROM vd_posts')['c'] ?? 0);
        $plan['counts']['total'] = ($plan['type'] === 'raw')
            ? count($plan['statements'])
            : count($plan['items']);
        return $plan;
    }

    public static function savePlan($plan)
    {
        $dir = self::planDir();
        if (!is_writable($dir)) {
            throw new \RuntimeException('导入目录不可写：' . $dir . '（请检查目录权限，需 Web 用户可写，如 chown -R www:www）');
        }
        $ok = @file_put_contents(self::planPath($plan['token']), json_encode($plan, JSON_UNESCAPED_UNICODE));
        if ($ok === false) {
            throw new \RuntimeException('无法写入导入计划文件，请检查 ' . $dir . ' 目录权限。');
        }
    }

    public static function loadPlan($token)
    {
        $p = self::planPath($token);
        if (!is_file($p)) return null;
        $plan = json_decode(file_get_contents($p), true);
        return is_array($plan) ? $plan : null;
    }

    private static function loadProg($token)
    {
        $p = self::progPath($token);
        if (is_file($p)) {
            $d = json_decode(file_get_contents($p), true);
            if (is_array($d)) return $d;
        }
        return ['done' => 0, 'stats' => self::initStats()];
    }

    private static function saveProg($token, $prog)
    {
        file_put_contents(self::progPath($token), json_encode($prog, JSON_UNESCAPED_UNICODE));
    }

    // ---- 归一化条目插入（XML 与 SQL 共用） ----

    private static function ensureCategory($slug, $name, &$stats)
    {
        if ($slug === '') $slug = slugify($name);
        if ($slug === '') return null;
        $existing = getCategoryBySlug($slug);
        if ($existing) return $existing['id'];
        $id = dbInsert('INSERT INTO vd_categories (name, slug, description) VALUES (?, ?, ?)',
            [$name ?: $slug, $slug, '']);
        $stats['categories']++;
        return $id;
    }

    private static function ensureTag($name, &$stats)
    {
        $name = trim((string)$name);
        if ($name === '') return null;
        $slug = slugify($name);
        $tag = dbOne('SELECT id FROM vd_tags WHERE slug=?', [$slug]);
        if ($tag) return $tag['id'];
        dbQuery('INSERT INTO vd_tags (name, slug, count) VALUES (?, ?, 0)', [$name, $slug]);
        $id = db()->lastInsertId();
        $stats['tags']++;
        return $id;
    }

    /**
     * 插入一条归一化条目（post/page），含分类/标签/评论/远程图片下载。
     */
    private static function insertNormalizedItem($item, $opts, &$stats)
    {
        $title = mb_substr((string)($item['title'] ?? ''), 0, 200);
        $slug = mb_substr((string)($item['slug'] ?? ''), 0, 200);
        $content = $item['content'] ?? '';
        $type = ($item['type'] === 'page') ? 'page' : 'post';
        $status = ($item['status'] === 'published') ? 'published' : 'draft';
        $format = ($item['format'] === 'markdown') ? 'markdown' : 'html';
        $author = mb_substr((string)($item['author'] ?? 'admin'), 0, 40);
        $createdAt = $item['created_at'] ?? date('Y-m-d H:i:s');

        if (trim($title) === '' && trim($content) === '') return;

        if (trim($slug) === '') $slug = slugify($title);
        if (empty($opts['preserve_slug'])) $slug = slugify($title);

        if (!empty($opts['skip_existing'])) {
            $existing = dbOne('SELECT id FROM vd_posts WHERE slug=? AND type=?', [$slug, $type]);
            if ($existing) { $stats['skipped']++; return; }
        }

        // 分类（可按导入配置覆盖：保留原分类 / 全部未分类 / 全部归入指定分类）
        $categoryId = null;
        $catMode = $opts['category_mode'] ?? 'keep';
        if ($catMode === 'specific' && !empty($opts['category_id'])) {
            $categoryId = (int)$opts['category_id'];
        } elseif ($catMode !== 'uncategorized' && (!empty($item['category_slug']) || !empty($item['category_name']))) {
            $categoryId = self::ensureCategory($item['category_slug'] ?? '', $item['category_name'] ?? '', $stats);
        }

        // 下载远程图片
        if (!empty($opts['download_remote_images']) && $content !== '') {
            $result = self::downloadImagesInContent($content, $format);
            $content = $result['content'];
            $stats['images'] += $result['count'];
            $stats['images_failed'] += $result['failed'];
        }

        // 特色图本地化（cover_image 是外链时下载替换）
        $coverImage = $item['cover_image'] ?? '';
        if ($coverImage !== '' && preg_match('#^https?://#i', $coverImage) && !empty($opts['download_remote_images'])) {
            $cv = self::downloadImagesInContent('<img src="' . $coverImage . '">', 'html');
            if ($cv['count'] > 0) {
                $stats['images'] += $cv['count'];
                if (preg_match('/src="([^"]+)"/', $cv['content'], $cm)) $coverImage = $cm[1];
            }
        }

        // HTML 补段落（markdown 交给渲染层）
        if ($format === 'html') {
            $content = autoParagraph($content);
        }

        $postId = dbInsert(
            'INSERT INTO vd_posts (title, slug, content, type, status, format, category_id, author, created_at, updated_at, allow_comment, cover_image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [$title, $slug, $content, $type, $status, $format, $categoryId, $author, $createdAt, $createdAt, $coverImage]
        );

        if ($type === 'post') $stats['posts']++;
        else $stats['pages']++;

        // 标签
        if (!empty($item['tag_names']) && is_array($item['tag_names'])) {
            foreach ($item['tag_names'] as $tn) {
                $tid = self::ensureTag($tn, $stats);
                if ($tid) dbQuery('INSERT IGNORE INTO vd_post_tags (post_id, tag_id) VALUES (?, ?)', [$postId, $tid]);
            }
        }

        // 评论
        if (!empty($opts['import_comments']) && !empty($item['comments']) && is_array($item['comments'])) {
            foreach ($item['comments'] as $c) {
                $cContent = $c['content'] ?? '';
                if (trim($cContent) === '') continue;
                dbInsert(
                    'INSERT INTO vd_comments (post_id, author, email, website, content, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$postId,
                     mb_substr((string)($c['author'] ?? '访客'), 0, 40),
                     mb_substr((string)($c['email'] ?? ''), 0, 120),
                     mb_substr((string)($c['url'] ?? ''), 0, 200),
                     $cContent, $c['status'] ?? 'approved', $c['created_at'] ?? date('Y-m-d H:i:s')]
                );
                $stats['comments']++;
            }
        }
    }

    /**
     * 执行一个导入分片，返回进度与日志。
     */
    public static function importChunk($plan, $offset, $batch)
    {
        $token = $plan['token'];
        $prog = self::loadProg($token);
        $stats = &$prog['stats'];
        $log = [];
        $finished = false;
        $summary = '';

        if ($plan['type'] === 'raw') {
            $statements = $plan['statements'];
            $total = count($statements);
            $slice = array_slice($statements, $offset, $batch);
            $allowed = ['vd_categories', 'vd_tags', 'vd_posts', 'vd_post_tags', 'vd_comments', 'vd_options'];
            foreach ($slice as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                if (preg_match('/^\s*(?:INSERT(?:\s+IGNORE)?|REPLACE|UPDATE|DELETE)\b/i', $stmt)) {
                    if (preg_match('/(?:INTO|UPDATE|FROM)\s+`?(\w+)`?/i', $stmt, $tm)) {
                        if (!in_array(strtolower($tm[1]), $allowed, true)) { $prog['stats']['skipped']++; continue; }
                    }
                }
                try { db()->exec(applyDbPrefix($stmt)); $prog['stats']['queries']++; }
                catch (\Exception $e) { /* 忽略单条错误 */ }
            }
            $offset += count($slice);
            $prog['done'] = $offset;
            if ($offset >= $total) {
                $finished = true;
                $summary = sprintf('✅ SQL 已执行 %d 条语句（跳过 %d 条非内容表写语句）。', $prog['stats']['queries'], $prog['stats']['skipped']);
                setOption('data_import_last_result', $summary);
            }
        } else {
            $items = $plan['items'];
            $total = count($items);
            $slice = array_slice($items, $offset, $batch);
            foreach ($slice as $item) {
                self::insertNormalizedItem($item, $plan['options'], $stats);
                $label = ($item['type'] === 'page' ? '页面' : '文章');
                $img = '';
                if (!empty($item['_img'])) $img = '（图片 ' . $item['_img'] . '）';
                $log[] = '✓ 导入' . $label . '《' . mb_substr(strip_tags($item['title'] ?: '(无标题)'), 0, 40) . '》' . $img;
            }
            $offset += count($slice);
            $prog['done'] = $offset;
            if ($offset >= $total) {
                $finished = true;
                // 标签计数统一刷新
                db()->exec(applyDbPrefix("UPDATE vd_tags t SET `count`=(SELECT COUNT(*) FROM vd_post_tags pt WHERE pt.tag_id=t.id)"));
                // 作者映射
                if (!empty($plan['options']['import_author'])) {
                    dbQuery('UPDATE vd_posts SET author=? WHERE id>?', [$plan['options']['import_author'], $plan['max_id_before']]);
                }
                $s = $prog['stats'];
                $summary = sprintf('✅ 导入完成！分类 %d，标签 %d，文章 %d，页面 %d，评论 %d，远程图片下载 %d（失败 %d），跳过已存在 %d。',
                    $s['categories'], $s['tags'], $s['posts'], $s['pages'], $s['comments'], $s['images'], $s['images_failed'], $s['skipped']);
                setOption('data_import_last_result', $summary);
            }
        }

        self::saveProg($token, $prog);
        return [
            'offset_next' => $offset,
            'done' => $prog['done'],
            'total' => $total,
            'finished' => $finished,
            'stats' => $prog['stats'],
            'log' => $log,
            'summary' => $summary,
        ];
    }

    /**
     * AJAX 分派：analyze 解析计划 / chunk 执行分片。返回数组（由调用方 json 编码）。
     */
    public static function ajax($post, $files)
    {
        $step = $post['step'] ?? '';
        try {
            if ($step === 'analyze') {
                @set_time_limit(0);
                @ini_set('memory_limit', '512M');
                $sourceType = $post['source_type'] ?? 'wordpress_xml';
                $opts = self::importOpts($post);
                if (empty($files['import_file']) || $files['import_file']['error'] !== UPLOAD_ERR_OK) {
                    return ['error' => '请选择要导入的文件。'];
                }
                $plan = self::buildPlan($files['import_file']['tmp_name'], $sourceType, $opts);
                self::savePlan($plan);
                return ['token' => $plan['token'], 'counts' => $plan['counts']];
            } elseif ($step === 'chunk') {
                @set_time_limit(0);
                $token = $post['token'] ?? '';
                $offset = (int)($post['offset'] ?? 0);
                $batch = 5;
                $plan = self::loadPlan($token);
                if (!$plan) return ['error' => '导入任务已失效，请重新选择文件并分析。'];
                return self::importChunk($plan, $offset, $batch);
            }
            return ['error' => '未知步骤。'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ---- 各类源的「解析为计划」实现 ----

    public static function buildPlanWordPressXml($file, $opts)
    {
        $xml = self::loadXmlFile($file);
        $ns = $xml->getDocNamespaces(true);
        $wpNs = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';
        $contentNs = $ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
        $dcNs = $ns['dc'] ?? 'http://purl.org/dc/elements/1.1/';

        $stats = self::initStats();
        $items = [];
        $xml->registerXPathNamespace('wp', $wpNs);

        $stats['categories'] = count($xml->xpath('//wp:category'));
        $stats['tags'] = count($xml->xpath('//wp:tag'));

        $xml->registerXPathNamespace('content', $contentNs);
        $xml->registerXPathNamespace('dc', $dcNs);

        // 附件映射：attachment post_id => attachment_url（用于特色图）
        $attachmentMap = [];
        foreach ($xml->channel->item as $attItem) {
            $attChildren = $attItem->children($wpNs);
            if ((string)$attChildren->post_type === 'attachment') {
                $attachmentMap[(int)$attChildren->post_id] = (string)$attChildren->attachment_url;
            }
        }

        foreach ($xml->channel->item as $item) {
            $wpChildren = $item->children($wpNs);
            $postType = (string)$wpChildren->post_type;
            if ($postType !== 'post' && $postType !== 'page') continue;
            if ((string)$wpChildren->status === 'inherit') continue;

            $title = (string)$item->title;
            $slug = (string)$wpChildren->post_name;
            $content = (string)$item->children($contentNs)->encoded;
            $postDate = (string)$wpChildren->post_date;
            $postStatus = (string)$wpChildren->status;
            $author = (string)$item->children($dcNs)->creator ?: 'admin';

            if (trim($slug) === '') $slug = slugify($title);
            if (empty($opts['preserve_slug'])) $slug = slugify($title);

            $status = ($postStatus === 'publish' || $postStatus === 'static') ? 'published' : 'draft';

            // 特色图（WordPress _thumbnail_id → attachment_url）
            $thumbnailId = 0;
            foreach ($wpChildren->postmeta as $pm) {
                if ((string)$pm->meta_key === '_thumbnail_id') {
                    $thumbnailId = (int)$pm->meta_value;
                }
            }
            $coverImage = ($thumbnailId > 0 && isset($attachmentMap[$thumbnailId])) ? $attachmentMap[$thumbnailId] : '';

            $categorySlug = ''; $categoryName = '';
            $tagNames = [];
            foreach ($item->category as $cat) {
                $domain = (string)$cat['domain'];
                if ($domain === 'category' || $domain === '') {
                    $categoryName = (string)$cat;
                    $categorySlug = (string)$cat['nicename'] ?: slugify($categoryName);
                } elseif ($domain === 'post_tag') {
                    $tagNames[] = (string)$cat;
                }
            }

            $comments = [];
            foreach ($item->children($wpNs)->comment as $comment) {
                $cContent = (string)$comment->children($wpNs)->comment_content;
                if (trim($cContent) === '') continue;
                $cStatus = (string)$comment->children($wpNs)->comment_approved;
                $comments[] = [
                    'author'    => (string)$comment->children($wpNs)->comment_author ?: '访客',
                    'email'     => (string)$comment->children($wpNs)->comment_author_email,
                    'url'       => (string)$comment->children($wpNs)->comment_author_url,
                    'content'   => $cContent,
                    'status'    => ($cStatus === '1' || $cStatus === 'approve') ? 'approved' : 'pending',
                    'created_at' => self::parseDate((string)$comment->children($wpNs)->comment_date),
                ];
            }

            $imgCount = 0;
            if (!empty($opts['download_remote_images']) && $content !== '') {
                $imgCount = count(scanRemoteImages($content, 'html'));
            }

            $items[] = [
                'source'        => 'wordpress_xml',
                'title'         => $title,
                'slug'          => $slug,
                'content'       => $content,
                'type'          => $postType,
                'status'        => $status,
                'format'        => 'html',
                'author'        => $author,
                'created_at'    => self::parseDate($postDate),
                'category_slug' => $categorySlug,
                'category_name' => $categoryName,
                'tag_names'     => $tagNames,
                'comments'      => $comments,
                'cover_image'   => $coverImage,
                '_img'          => $imgCount,
            ];
            if ($postType === 'post') $stats['posts']++; else $stats['pages']++;
            $stats['comments'] += count($comments);
            $stats['images'] += $imgCount;
        }

        return ['type' => 'wp', 'items' => $items, 'counts' => $stats];
    }

    public static function buildPlanTypechoXml($file, $opts)
    {
        $xml = self::loadXmlFile($file);
        $ns = $xml->getDocNamespaces(true);
        $contentNs = $ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
        $dcNs = $ns['dc'] ?? 'http://purl.org/dc/elements/1.1/';
        $wpNs = $ns['wp'] ?? 'http://typecho.org/0.9/export/';

        $stats = self::initStats();
        $items = [];

        foreach ($xml->channel->item as $item) {
            $title = (string)$item->title;
            $link = (string)$item->link;
            $pubDate = (string)$item->pubDate;
            $content = (string)$item->children($contentNs)->encoded;
            $author = (string)$item->children($dcNs)->creator ?: 'admin';

            $slug = '';
            if (preg_match('#/([^/]+)/?$#', rtrim($link, '/'), $m)) $slug = $m[1];
            if (isset($item->children($wpNs)->post_name)) $slug = (string)$item->children($wpNs)->post_name ?: $slug;
            if (trim($slug) === '') $slug = slugify($title);
            if (empty($opts['preserve_slug'])) $slug = slugify($title);

            $postType = 'post';
            if (isset($item->children($wpNs)->post_type)) {
                $pt = (string)$item->children($wpNs)->post_type;
                $postType = ($pt === 'page' || $pt === 'attachment') ? 'page' : 'post';
            }
            if (strpos($link, '/page/') !== false || strpos($link, '/about') !== false) $postType = 'page';

            $status = 'published';
            if (isset($item->children($wpNs)->status)) {
                $wpStatus = (string)$item->children($wpNs)->status;
                $status = ($wpStatus === 'publish') ? 'published' : 'draft';
            }

            $categorySlug = ''; $categoryName = ''; $tagNames = [];
            foreach ($item->category as $cat) {
                $domain = (string)$cat['domain'];
                $catName = (string)$cat;
                if ($domain === 'category' || $domain === '') {
                    $categoryName = $catName;
                    $categorySlug = slugify($catName);
                } elseif ($domain === 'tag' || $domain === 'post_tag') {
                    $tagNames[] = $catName;
                }
            }

            $format = 'html';
            if (isset($item->children($wpNs)->post_format)) {
                if ((string)$item->children($wpNs)->post_format === 'markdown') $format = 'markdown';
            }

            $imgCount = 0;
            if (!empty($opts['download_remote_images']) && $content !== '') $imgCount = count(scanRemoteImages($content, $format));

            $items[] = [
                'source'        => 'typecho_xml',
                'title'         => $title,
                'slug'          => $slug,
                'content'       => $content,
                'type'          => $postType,
                'status'        => $status,
                'format'        => $format,
                'author'        => $author,
                'created_at'    => self::parseDate($pubDate, 'rss'),
                'category_slug' => $categorySlug,
                'category_name' => $categoryName,
                'tag_names'     => $tagNames,
                'comments'      => [],
                '_img'          => $imgCount,
            ];
            if ($postType === 'post') $stats['posts']++; else $stats['pages']++;
            $stats['images'] += $imgCount;
        }

        return ['type' => 'typecho', 'items' => $items, 'counts' => $stats];
    }

    public static function buildPlanSql($file, $opts)
    {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') throw new \Exception('SQL 文件为空或无法读取。');

        $statements = self::splitSqlStatements($sql);
        $tableData = [];
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strpos($stmt, '--') === 0) continue;
            if (!preg_match('/^\s*INSERT\s+INTO\s+`?(\w+)`?/i', $stmt)) continue;
            $parsed = self::parseSqlInsertMulti($stmt);
            if (!$parsed || empty($parsed['rows'])) continue;
            $table = $parsed['table'];
            if (!isset($tableData[$table])) $tableData[$table] = ['columns' => $parsed['columns'], 'rows' => []];
            foreach ($parsed['rows'] as $row) $tableData[$table]['rows'][] = $row;
        }

        $wpData = ['posts' => [], 'terms' => [], 'term_taxonomy' => [], 'term_relationships' => [], 'comments' => [], 'postmeta' => []];
        $typechoData = ['contents' => [], 'metas' => [], 'relationships' => [], 'comments' => []];

        foreach ($tableData as $tableName => $data) {
            $columns = $data['columns'];
            $colSet = array_flip($columns);
            $rows = $data['rows'];

            if (isset($colSet['post_content']) && isset($colSet['post_type'])) { $wpData['posts'] = array_merge($wpData['posts'], $rows); continue; }
            if (isset($colSet['term_id']) && isset($colSet['taxonomy'])) { $wpData['term_taxonomy'] = array_merge($wpData['term_taxonomy'], $rows); continue; }
            if (isset($colSet['term_id']) && isset($colSet['name']) && isset($colSet['slug']) && !isset($colSet['taxonomy'])) { $wpData['terms'] = array_merge($wpData['terms'], $rows); continue; }
            if (isset($colSet['object_id']) && isset($colSet['term_taxonomy_id'])) { $wpData['term_relationships'] = array_merge($wpData['term_relationships'], $rows); continue; }
            if (isset($colSet['comment_post_ID']) && isset($colSet['comment_content'])) { $wpData['comments'] = array_merge($wpData['comments'], $rows); continue; }
            if (isset($colSet['post_id']) && isset($colSet['meta_key'])) { $wpData['postmeta'] = array_merge($wpData['postmeta'], $rows); continue; }
            if (isset($colSet['coid']) && isset($colSet['cid']) && isset($colSet['text']) && !isset($colSet['mid'])) { $typechoData['comments'] = array_merge($typechoData['comments'], $rows); continue; }
            if (isset($colSet['cid']) && isset($colSet['text']) && isset($colSet['type']) && !isset($colSet['mid']) && !isset($colSet['coid'])) { $typechoData['contents'] = array_merge($typechoData['contents'], $rows); continue; }
            if (isset($colSet['mid']) && isset($colSet['name']) && isset($colSet['type'])) { $typechoData['metas'] = array_merge($typechoData['metas'], $rows); continue; }
            if (isset($colSet['cid']) && isset($colSet['mid']) && !isset($colSet['text'])) { $typechoData['relationships'] = array_merge($typechoData['relationships'], $rows); continue; }
        }

        $hasWp = !empty($wpData['posts']);
        $hasTypecho = !empty($typechoData['contents']);

        if (!$hasWp && !$hasTypecho) {
            $raw = [];
            foreach ($statements as $s) {
                $s = trim($s);
                if ($s !== '' && strpos($s, '--') !== 0) $raw[] = $s;
            }
            $stats = self::initStats();
            $stats['queries'] = count($raw);
            return ['type' => 'raw', 'statements' => $raw, 'counts' => $stats];
        }

        if ($hasWp) return self::buildPlanWordPressSqlData($wpData, $opts);
        return self::buildPlanTypechoSqlData($typechoData, $opts);
    }

    private static function buildPlanWordPressSqlData($data, $opts)
    {
        $stats = self::initStats();
        $termMap = [];
        foreach ($data['terms'] as $row) {
            $tid = $row['term_id'] ?? null; $name = $row['name'] ?? ''; $slug = $row['slug'] ?? '';
            if ($tid !== null && $name !== '') $termMap[$tid] = ['name' => $name, 'slug' => $slug, 'taxonomy' => ''];
        }
        $ttMap = [];
        foreach ($data['term_taxonomy'] as $row) {
            $ttId = $row['term_taxonomy_id'] ?? null; $tid = $row['term_id'] ?? null; $tax = $row['taxonomy'] ?? '';
            if ($ttId !== null && $tid !== null) {
                $ttMap[$ttId] = ['term_id' => $tid, 'taxonomy' => $tax];
                if (isset($termMap[$tid])) $termMap[$tid]['taxonomy'] = $tax;
            }
        }
        $postTermMap = [];
        foreach ($data['term_relationships'] as $row) {
            $pid = $row['object_id'] ?? null; $ttId = $row['term_taxonomy_id'] ?? null;
            if ($pid !== null && $ttId !== null && isset($ttMap[$ttId])) {
                $postTermMap[$pid][] = ['term_id' => $ttMap[$ttId]['term_id'], 'taxonomy' => $ttMap[$ttId]['taxonomy']];
            }
        }

        foreach ($termMap as $info) {
            if ($info['taxonomy'] === 'category') $stats['categories']++;
            elseif ($info['taxonomy'] === 'post_tag') $stats['tags']++;
        }

        $items = [];
        $wpPostIdToIndex = [];
        foreach ($data['posts'] as $row) {
            $wpPostType = $row['post_type'] ?? 'post';
            if ($wpPostType !== 'post' && $wpPostType !== 'page') continue;
            $title = $row['post_title'] ?? ''; $content = $row['post_content'] ?? ''; $slug = $row['post_name'] ?? '';
            $postDate = $row['post_date'] ?? ''; $postStatus = $row['post_status'] ?? 'publish';
            $author = $row['post_author'] ?? 'admin'; $wpPostId = $row['ID'] ?? null;
            if (trim($title) === '' && trim($content) === '') continue;
            if (trim($slug) === '') $slug = slugify($title);
            if (empty($opts['preserve_slug'])) $slug = slugify($title);
            $status = ($postStatus === 'publish') ? 'published' : 'draft';
            $type = ($wpPostType === 'page') ? 'page' : 'post';

            $categorySlug = ''; $categoryName = ''; $tagNames = [];
            if ($wpPostId !== null && isset($postTermMap[$wpPostId])) {
                foreach ($postTermMap[$wpPostId] as $t) {
                    $tid = $t['term_id']; $tax = $t['taxonomy'];
                    if ($tax === 'category' && isset($termMap[$tid])) {
                        $categoryName = $termMap[$tid]['name'];
                        $categorySlug = $termMap[$tid]['slug'] ?: slugify($categoryName);
                    } elseif ($tax === 'post_tag' && isset($termMap[$tid])) {
                        $tagNames[] = $termMap[$tid]['name'];
                    }
                }
            }

            $imgCount = 0;
            if (!empty($opts['download_remote_images']) && $content !== '') $imgCount = count(scanRemoteImages($content, 'html'));

            $idx = count($items);
            $items[] = [
                'source'        => 'sql_wp',
                'title'         => $title,
                'slug'          => $slug,
                'content'       => $content,
                'type'          => $type,
                'status'        => $status,
                'format'        => 'html',
                'author'        => $author,
                'created_at'    => self::parseDate($postDate),
                'category_slug' => $categorySlug,
                'category_name' => $categoryName,
                'tag_names'     => $tagNames,
                'comments'      => [],
                '_img'          => $imgCount,
            ];
            if ($wpPostId !== null) $wpPostIdToIndex[$wpPostId] = $idx;
            if ($type === 'post') $stats['posts']++; else $stats['pages']++;
            $stats['images'] += $imgCount;
        }

        foreach ($data['comments'] as $row) {
            $wpPostId = $row['comment_post_ID'] ?? null;
            if (!$wpPostId || !isset($wpPostIdToIndex[$wpPostId])) continue;
            $cType = $row['comment_type'] ?? 'comment';
            if ($cType === 'pingback' || $cType === 'trackback') continue;
            $cContent = $row['comment_content'] ?? '';
            if (trim($cContent) === '') continue;
            $cStatus = $row['comment_approved'] ?? '1';
            $items[$wpPostIdToIndex[$wpPostId]]['comments'][] = [
                'author'     => $row['comment_author'] ?? '访客',
                'email'      => $row['comment_author_email'] ?? '',
                'url'        => $row['comment_author_url'] ?? '',
                'content'    => $cContent,
                'status'     => ($cStatus === '1' || $cStatus === 'approve') ? 'approved' : 'pending',
                'created_at' => self::parseDate($row['comment_date'] ?? ''),
            ];
            $stats['comments']++;
        }

        return ['type' => 'wp', 'items' => $items, 'counts' => $stats];
    }

    private static function buildPlanTypechoSqlData($data, $opts)
    {
        $stats = self::initStats();
        $metaMap = [];
        foreach ($data['metas'] as $row) {
            $type = $row['type'] ?? 'category'; $name = $row['name'] ?? ''; $slug = $row['slug'] ?? ''; $mid = $row['mid'] ?? null;
            if (!$name || $mid === null) continue;
            if ($type === 'category') {
                $s = $slug ?: slugify($name);
                $stats['categories']++;
                $metaMap[$mid] = ['type' => 'category', 'name' => $name, 'slug' => $s];
            } elseif ($type === 'tag') {
                $s = $slug ?: slugify($name);
                $stats['tags']++;
                $metaMap[$mid] = ['type' => 'tag', 'name' => $name, 'slug' => $s];
            }
        }
        $postMetaMap = [];
        foreach ($data['relationships'] as $row) {
            $cid = $row['cid'] ?? null; $mid = $row['mid'] ?? null;
            if ($cid !== null && $mid !== null) $postMetaMap[$cid][] = $mid;
        }

        $items = [];
        $cidToIndex = [];
        foreach ($data['contents'] as $row) {
            $type = $row['type'] ?? 'post';
            if ($type !== 'post' && $type !== 'page') continue;
            $title = $row['title'] ?? ''; $text = $row['text'] ?? ''; $slug = $row['slug'] ?? '';
            $created = $row['created'] ?? null; $status = $row['status'] ?? 'publish';
            $author = $row['authorId'] ?? 'admin'; $cid = $row['cid'] ?? null;
            if (trim($title) === '' && trim($text) === '') continue;
            if (trim($slug) === '') $slug = slugify($title);
            if (empty($opts['preserve_slug'])) $slug = slugify($title);
            $format = 'html';
            if (strpos($text, '<!--markdown-->') === 0) { $text = substr($text, 15); $format = 'markdown'; }
            $postStatus = ($status === 'publish') ? 'published' : 'draft';
            $postType = ($type === 'page') ? 'page' : 'post';
            $createdAt = $created ? date('Y-m-d H:i:s', (int)$created) : date('Y-m-d H:i:s');

            $categorySlug = ''; $categoryName = ''; $tagNames = [];
            if ($cid !== null && isset($postMetaMap[$cid])) {
                foreach ($postMetaMap[$cid] as $mid) {
                    if (!isset($metaMap[$mid])) continue;
                    if ($metaMap[$mid]['type'] === 'category') {
                        $categoryName = $metaMap[$mid]['name'];
                        $categorySlug = $metaMap[$mid]['slug'];
                    } elseif ($metaMap[$mid]['type'] === 'tag') {
                        $tagNames[] = $metaMap[$mid]['name'];
                    }
                }
            }

            $imgCount = 0;
            if (!empty($opts['download_remote_images']) && $text !== '') $imgCount = count(scanRemoteImages($text, $format));

            $idx = count($items);
            $items[] = [
                'source'        => 'sql_typecho',
                'title'         => $title,
                'slug'          => $slug,
                'content'       => $text,
                'type'          => $postType,
                'status'        => $postStatus,
                'format'        => $format,
                'author'        => $author,
                'created_at'    => $createdAt,
                'category_slug' => $categorySlug,
                'category_name' => $categoryName,
                'tag_names'     => $tagNames,
                'comments'      => [],
                '_img'          => $imgCount,
            ];
            if ($cid !== null) $cidToIndex[$cid] = $idx;
            if ($postType === 'post') $stats['posts']++; else $stats['pages']++;
            $stats['images'] += $imgCount;
        }

        foreach ($data['comments'] as $row) {
            $cid = $row['cid'] ?? null;
            if (!$cid || !isset($cidToIndex[$cid])) continue;
            $cContent = $row['text'] ?? '';
            if (trim($cContent) === '') continue;
            $cStatus = $row['status'] ?? 'approved';
            $items[$cidToIndex[$cid]]['comments'][] = [
                'author'     => $row['author'] ?? '访客',
                'email'      => $row['mail'] ?? '',
                'url'        => $row['url'] ?? '',
                'content'    => $cContent,
                'status'     => ($cStatus === 'approved') ? 'approved' : 'pending',
                'created_at' => $row['created'] ? date('Y-m-d H:i:s', (int)$row['created']) : date('Y-m-d H:i:s'),
            ];
            $stats['comments']++;
        }

        return ['type' => 'typecho', 'items' => $items, 'counts' => $stats];
    }


// ===================== 工具方法 =====================
    /**
     * 安全加载 XML 导入文件：
     * 1) 抑制 simplexml 原生 Warning
     * 2) 先嗅探文件开头，若看起来是 SQL 文件则给出清晰提示
     * 3) 解析失败时返回可读的错误信息（含出错行号），而非裸 Warning
     */
    private static function loadXmlFile($file)
    {
        $head = @file_get_contents($file, false, null, 0, 512);
        if ($head === false) {
            throw new \Exception('无法读取上传的文件，请重试。');
        }
        $head = str_replace("\xEF\xBB\xBF", '', $head); // 去 UTF-8 BOM
        $trimmed = ltrim($head);
        if ($trimmed === '' || $trimmed[0] !== '<') {
            if (preg_match('/^(\-\-|#|\/\*|\s*(CREATE|INSERT|SET|USE|DROP|ALTER|SELECT|LOCK)\s)/i', $trimmed)) {
                throw new \Exception('您上传的文件看起来是 SQL 文件（如 phpMyAdmin 导出）。请选择「SQL 文件」导入类型，而不是 XML。');
            }
            throw new \Exception('上传的文件不是有效的 XML（XML 应以 “<” 开头）。请确认导出格式无误。');
        }

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = simplexml_load_file($file);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            $msg = 'XML 解析失败';
            if (!empty($errors)) {
                $e0 = $errors[0];
                $msg .= '：第 ' . $e0->line . ' 行附近 - ' . trim($e0->message);
            } else {
                $msg .= '，文件格式可能不正确。';
            }
            throw new \Exception($msg);
        }
        return $xml;
    }

    /**
     * 解析 INSERT 语句（支持多行 VALUES）
     * 返回 ['table' => 'wp_posts', 'columns' => [...], 'rows' => [assoc1, ...]]
     */
    private static function parseSqlInsertMulti($stmt)
    {
        if (!preg_match('/^\s*INSERT\s+INTO\s+`?(\w+)`?/i', $stmt, $m)) {
            return null;
        }
        $table = $m[1];

        $columns = [];
        $valuesStr = '';

        if (preg_match('/INSERT\s+INTO\s+`?\w+`?\s*\(([^)]+)\)\s*VALUES\s*/is', $stmt, $m)) {
            $colStr = $m[1];
            $columns = array_map(function ($c) { return trim($c, '` "\' '); }, explode(',', $colStr));
            $columns = array_filter($columns, function ($c) { return $c !== ''; });
            $columns = array_values($columns);
            $pos = strpos($stmt, $m[0]);
            $valuesStr = substr($stmt, $pos + strlen($m[0]));
        } elseif (preg_match('/INSERT\s+INTO\s+`?\w+`?\s*VALUES\s*/is', $stmt, $m)) {
            $pos = strpos($stmt, $m[0]);
            $valuesStr = substr($stmt, $pos + strlen($m[0]));
        } else {
            return null;
        }

        $valuesStr = rtrim(trim($valuesStr), ';');

        $tuples = self::splitValueTuples($valuesStr);
        if (empty($tuples)) return null;

        $rows = [];
        foreach ($tuples as $tuple) {
            $values = self::parseSqlValues($tuple);
            if (empty($columns)) {
                $rows[] = $values;
            } else {
                if (count($columns) === count($values)) {
                    $rows[] = array_combine($columns, $values);
                } else {
                    $rows[] = $values;
                }
            }
        }

        return ['table' => $table, 'columns' => $columns, 'rows' => $rows];
    }

    /**
     * 将多行 VALUES 字符串拆分为单个元组字符串数组
     */
    private static function splitValueTuples($str)
    {
        $tuples = [];
        $len = strlen($str);
        $i = 0;

        while ($i < $len) {
            while ($i < $len && ($str[$i] === ' ' || $str[$i] === ',' || $str[$i] === "\t" || $str[$i] === "\n" || $str[$i] === "\r")) $i++;
            if ($i >= $len) break;

            if ($str[$i] !== '(') break;
            $i++;

            $tuple = '';
            $depth = 0;
            $inStr = false;
            $strChar = '';

            while ($i < $len) {
                $ch = $str[$i];

                if ($inStr) {
                    $tuple .= $ch;
                    if ($ch === '\\' && $i + 1 < $len) {
                        $tuple .= $str[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($ch === $strChar) {
                        if ($i + 1 < $len && $str[$i + 1] === $strChar) {
                            $tuple .= $str[$i + 1];
                            $i += 2;
                            continue;
                        }
                        $inStr = false;
                    }
                    $i++;
                } else {
                    if ($ch === '\'' || $ch === '"') {
                        $inStr = true;
                        $strChar = $ch;
                        $tuple .= $ch;
                        $i++;
                    } elseif ($ch === '(') {
                        $depth++;
                        $tuple .= $ch;
                        $i++;
                    } elseif ($ch === ')') {
                        if ($depth > 0) {
                            $depth--;
                            $tuple .= $ch;
                            $i++;
                        } else {
                            $i++;
                            break;
                        }
                    } else {
                        $tuple .= $ch;
                        $i++;
                    }
                }
            }

            $tuples[] = $tuple;
        }

        return $tuples;
    }

    /**
     * 状态机解析 SQL VALUES 中的值列表
     */
    private static function parseSqlValues($str)
    {
        $values = [];
        $len = strlen($str);
        $i = 0;
        while ($i < $len) {
            while ($i < $len && ($str[$i] === ' ' || $str[$i] === ',' || $str[$i] === "\t" || $str[$i] === "\n" || $str[$i] === "\r")) $i++;
            if ($i >= $len) break;

            if ($str[$i] === '\'') {
                $i++;
                $val = '';
                while ($i < $len) {
                    if ($str[$i] === '\\' && $i + 1 < $len) {
                        $next = $str[$i + 1];
                        if ($next === 'n') $val .= "\n";
                        elseif ($next === 'r') $val .= "\r";
                        elseif ($next === 't') $val .= "\t";
                        elseif ($next === '0') $val .= "\0";
                        else $val .= $next;
                        $i += 2;
                    } elseif ($str[$i] === '\'') {
                        if ($i + 1 < $len && $str[$i + 1] === '\'') {
                            $val .= '\'';
                            $i += 2;
                        } else {
                            $i++;
                            break;
                        }
                    } else {
                        $val .= $str[$i];
                        $i++;
                    }
                }
                $values[] = $val;
            } elseif ($str[$i] === 'N' || $str[$i] === 'n') {
                $word = '';
                while ($i < $len && (($str[$i] >= 'a' && $str[$i] <= 'z') || ($str[$i] >= 'A' && $str[$i] <= 'Z'))) {
                    $word .= $str[$i];
                    $i++;
                }
                $values[] = (strtoupper($word) === 'NULL') ? null : $word;
            } else {
                $val = '';
                while ($i < $len && $str[$i] !== ',' && $str[$i] !== ' ' && $str[$i] !== "\t" && $str[$i] !== "\n") {
                    $val .= $str[$i];
                    $i++;
                }
                $values[] = $val;
            }
        }
        return $values;
    }




    /**
     * 下载正文中的远程图片到本地，替换 URL
     */
    private static function downloadImagesInContent($content, $format, $timeout = 30)
    {
        $remoteUrls = scanRemoteImages($content, $format);
        $count = 0;

        if (empty($remoteUrls)) return ['content' => $content, 'count' => 0, 'failed' => 0];

        $relDir = 'usr/uploads/import/' . date('Ym') . '/';
        $absDir = RYEBLOG_ROOT . '/' . $relDir;
        if (!is_dir($absDir)) {
            if (!@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
                throw new \RuntimeException('无法创建图片目录：' . $absDir . '（请检查 usr/uploads 目录权限，Web 用户需可写）');
            }
        }
        if (!is_writable($absDir)) {
            throw new \RuntimeException('图片目录不可写：' . $absDir . '（请执行 chown -R www:www ' . RYEBLOG_ROOT . '/usr）');
        }

        $urlMap = []; // remote_url => local_url
        $failed = 0;
        foreach ($remoteUrls as $remoteUrl) {
            // WordPress/Typecho 内容在 CDATA 中，URL 里的 & 会被写成 &amp;；请求前需解码，但替换时仍用原文
            $fetchUrl = html_entity_decode($remoteUrl, ENT_QUOTES | ENT_HTML5);
            $result = downloadRemoteFile($fetchUrl, $absDir, '', $timeout);
            if ($result) {
                $localRel = $relDir . $result['filename'];
                // 用根相对路径（CLI 导入时 baseUrl() 会按脚本路径推导出错，如 /tmp/xxx.php/usr/uploads/...）
                $localUrl = '/' . $localRel;
                $urlMap[$remoteUrl] = $localUrl;

                // 注册附件记录
                $mime = $result['mime'] ?: getMimeForExt(pathinfo($result['filename'], PATHINFO_EXTENSION));
                $size = @filesize($result['path']);
                if ($size === false) $size = 0; // stat 失败不传 false/空串（防 MySQL 1366）
                registerAttachmentRecord($localRel, $result['filename'], $size, $mime);
                $count++;
            } else {
                $failed++;
            }
        }

        // 替换正文中的 URL
        foreach ($urlMap as $remote => $local) {
            $content = str_replace($remote, $local, $content);
        }

        return ['content' => $content, 'count' => $count, 'failed' => $failed];
    }

    /**
     * 解析日期字符串为 MySQL DATETIME 格式
     */
    private static function parseDate($dateStr, $format = '')
    {
        $dateStr = trim($dateStr);
        if ($dateStr === '') return date('Y-m-d H:i:s');

        // RSS 日期格式: Wed, 01 Jan 2020 00:00:00 +0000
        if ($format === 'rss') {
            $ts = strtotime($dateStr);
            if ($ts !== false) return date('Y-m-d H:i:s', $ts);
        }

        // WordPress 格式: 2020-01-01 00:00:00
        $ts = strtotime($dateStr);
        if ($ts !== false) return date('Y-m-d H:i:s', $ts);

        // 尝试 "0000-00-00 00:00:00" 无效日期
        if (strpos($dateStr, '0000-00-00') !== false) return date('Y-m-d H:i:s');

        return date('Y-m-d H:i:s');
    }

    /**
     * 将多语句 SQL 拆分为单条语句数组
     */
    private static function splitSqlStatements($sql)
    {
        $statements = [];
        $len = strlen($sql);
        $start = 0;
        $inString = false;
        $stringChar = '';
        $i = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            // 处理注释 -- 到行尾
            if (!$inString && $ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                // 跳到行尾
                while ($i < $len && $sql[$i] !== "\n") $i++;
                $start = $i + 1;
                continue;
            }

            // 处理 /* */ 注释
            if (!$inString && $ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && $i + 1 < $len && $sql[$i + 1] === '/')) $i++;
                $i += 2;
                $start = $i;
                continue;
            }

            // 处理字符串
            if (!$inString && ($ch === '\'' || $ch === '"')) {
                $inString = true;
                $stringChar = $ch;
            } elseif ($inString && $ch === $stringChar) {
                // 检查是否转义
                if ($i > 0 && $sql[$i - 1] === '\\') {
                    // 转义引号，继续字符串
                } else {
                    $inString = false;
                }
            }

            // 分号分隔
            if (!$inString && $ch === ';') {
                $stmt = substr($sql, $start, $i - $start);
                $statements[] = $stmt;
                $start = $i + 1;
            }

            $i++;
        }

        // 最后一条（可能无分号）
        $last = substr($sql, $start);
        $last = trim($last);
        if ($last !== '' && strpos($last, '--') !== 0) {
            $statements[] = $last;
        }

        return $statements;
    }

    // ===================== 数据导出 =====================

    /**
     * 导出为 RyeBlog XML 格式
     * 完整结构化备份，可重新导入恢复
     */
    private static function exportRyeBlogXml($filepath, $opts)
    {
        $stats = ['posts' => 0, 'pages' => 0, 'categories' => 0, 'tags' => 0, 'comments' => 0, 'settings' => 0];

        $siteName = getOption('site_name', 'RyeBlog');
        $siteUrl  = getOption('site_url', '');
        $exportDate = date('c');

        // 打开文件写入
        $fp = fopen($filepath, 'w');
        if (!$fp) throw new \Exception('无法创建导出文件，请检查 usr/uploads/export 目录权限。');

        fwrite($fp, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        fwrite($fp, "<!-- RyeBlog Export -->\n");
        fwrite($fp, "<!-- generator=\"RyeBlog\" created=\"{$exportDate}\" -->\n");
        fwrite($fp, "<ryeblog version=\"2.0\">\n");
        fwrite($fp, "  <site>\n");
        fwrite($fp, "    <name><![CDATA[" . self::xmlCdata($siteName) . "]]></name>\n");
        fwrite($fp, "    <url><![CDATA[" . self::xmlCdata($siteUrl) . "]]></url>\n");
        fwrite($fp, "    <export_date><![CDATA[{$exportDate}]]></export_date>\n");
        fwrite($fp, "  </site>\n");

        // 导出分类
        if ($opts['export_categories']) {
            fwrite($fp, "  <categories>\n");
            $cats = dbAll("SELECT * FROM vd_categories ORDER BY id");
            foreach ($cats as $cat) {
                fwrite($fp, "    <category>\n");
                fwrite($fp, "      <id>{$cat['id']}</id>\n");
                fwrite($fp, "      <name><![CDATA[" . self::xmlCdata($cat['name']) . "]]></name>\n");
                fwrite($fp, "      <slug><![CDATA[" . self::xmlCdata($cat['slug']) . "]]></slug>\n");
                fwrite($fp, "      <description><![CDATA[" . self::xmlCdata($cat['description'] ?? '') . "]]></description>\n");
                fwrite($fp, "    </category>\n");
                $stats['categories']++;
            }
            fwrite($fp, "  </categories>\n");

            // 导出标签
            fwrite($fp, "  <tags>\n");
            $tags = dbAll("SELECT * FROM vd_tags ORDER BY id");
            foreach ($tags as $tag) {
                fwrite($fp, "    <tag>\n");
                fwrite($fp, "      <id>{$tag['id']}</id>\n");
                fwrite($fp, "      <name><![CDATA[" . self::xmlCdata($tag['name']) . "]]></name>\n");
                fwrite($fp, "      <slug><![CDATA[" . self::xmlCdata($tag['slug']) . "]]></slug>\n");
                fwrite($fp, "      <count>{$tag['count']}</count>\n");
                fwrite($fp, "    </tag>\n");
                $stats['tags']++;
            }
            fwrite($fp, "  </tags>\n");
        }

        // 导出文章和页面
        if ($opts['export_posts']) {
            $typeCond = "1=1";
            $posts = dbAll("SELECT * FROM vd_posts WHERE {$typeCond} ORDER BY id");

            // 构建 tag 关联映射
            $postTagMap = [];
            $ptRows = dbAll("SELECT pt.post_id, t.name, t.slug FROM vd_post_tags pt JOIN vd_tags t ON pt.tag_id=t.id");
            foreach ($ptRows as $r) {
                $postTagMap[$r['post_id']][] = ['name' => $r['name'], 'slug' => $r['slug']];
            }

            fwrite($fp, "  <posts>\n");
            foreach ($posts as $post) {
                fwrite($fp, "    <post>\n");
                fwrite($fp, "      <id>{$post['id']}</id>\n");
                fwrite($fp, "      <title><![CDATA[" . self::xmlCdata($post['title']) . "]]></title>\n");
                fwrite($fp, "      <slug><![CDATA[" . self::xmlCdata($post['slug']) . "]]></slug>\n");
                fwrite($fp, "      <content><![CDATA[" . self::xmlCdata($post['content']) . "]]></content>\n");
                fwrite($fp, "      <type><![CDATA[{$post['type']}]]></type>\n");
                fwrite($fp, "      <status><![CDATA[{$post['status']}]]></status>\n");
                fwrite($fp, "      <format><![CDATA[{$post['format']}]]></format>\n");
                fwrite($fp, "      <category_id>" . ($post['category_id'] ?? '') . "</category_id>\n");
                fwrite($fp, "      <author><![CDATA[" . self::xmlCdata($post['author'] ?? '') . "]]></author>\n");
                fwrite($fp, "      <created_at><![CDATA[{$post['created_at']}]]></created_at>\n");
                fwrite($fp, "      <updated_at><![CDATA[{$post['updated_at']}]]></updated_at>\n");
                fwrite($fp, "      <allow_comment>{$post['allow_comment']}</allow_comment>\n");
                fwrite($fp, "      <views>{$post['views']}</views>\n");

                // 关联标签
                if (!empty($postTagMap[$post['id']])) {
                    fwrite($fp, "      <tags>\n");
                    foreach ($postTagMap[$post['id']] as $t) {
                        fwrite($fp, "        <tag slug=\"" . self::xmlAttr($t['slug']) . "\"><![CDATA[" . self::xmlCdata($t['name']) . "]]></tag>\n");
                    }
                    fwrite($fp, "      </tags>\n");
                }

                // 导出评论
                if ($opts['export_comments']) {
                    $comments = dbAll("SELECT * FROM vd_comments WHERE post_id=? ORDER BY id", [$post['id']]);
                    if (!empty($comments)) {
                        fwrite($fp, "      <comments>\n");
                        foreach ($comments as $c) {
                            fwrite($fp, "        <comment>\n");
                            fwrite($fp, "          <id>{$c['id']}</id>\n");
                            fwrite($fp, "          <author><![CDATA[" . self::xmlCdata($c['author']) . "]]></author>\n");
                            fwrite($fp, "          <email><![CDATA[" . self::xmlCdata($c['email']) . "]]></email>\n");
                            fwrite($fp, "          <website><![CDATA[" . self::xmlCdata($c['website'] ?? '') . "]]></website>\n");
                            fwrite($fp, "          <content><![CDATA[" . self::xmlCdata($c['content']) . "]]></content>\n");
                            fwrite($fp, "          <status><![CDATA[{$c['status']}]]></status>\n");
                            fwrite($fp, "          <created_at><![CDATA[{$c['created_at']}]]></created_at>\n");
                            fwrite($fp, "        </comment>\n");
                            $stats['comments']++;
                        }
                        fwrite($fp, "      </comments>\n");
                    }
                }

                fwrite($fp, "    </post>\n");
                if ($post['type'] === 'post') $stats['posts']++;
                else $stats['pages']++;
            }
            fwrite($fp, "  </posts>\n");
        }

        // 导出站点设置
        if ($opts['export_settings']) {
            fwrite($fp, "  <settings>\n");
            $settings = dbAll("SELECT name, value FROM vd_options WHERE name IN (
                'site_name','site_url','site_description','site_keywords',
                'site_icp','footer_code','head_code',
                'social_github','social_twitter','social_email','social_qq','social_weibo',
                'sidebar_about','sidebar_notice',
                'theme_active','posts_per_page','comment_moderation','comment_order',
                'author_name','author_bio','author_avatar','author_banner','author_email'
            )");
            foreach ($settings as $s) {
                fwrite($fp, "    <setting name=\"" . self::xmlAttr($s['name']) . "\"><![CDATA[" . self::xmlCdata($s['value']) . "]]></setting>\n");
                $stats['settings']++;
            }
            fwrite($fp, "  </settings>\n");
        }

        fwrite($fp, "</ryeblog>\n");
        fclose($fp);

        return $stats;
    }

    /**
     * 导出为 SQL 格式（phpMyAdmin 可直接导入）
     */
    private static function exportRyeBlogSql($filepath, $opts)
    {
        $stats = ['posts' => 0, 'pages' => 0, 'categories' => 0, 'tags' => 0, 'comments' => 0, 'settings' => 0];

        $fp = fopen($filepath, 'w');
        if (!$fp) throw new \Exception('无法创建导出文件，请检查目录权限。');

        fwrite($fp, "-- RyeBlog SQL Export\n");
        fwrite($fp, "-- Date: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- Tables: vd_categories, vd_tags, vd_posts, vd_post_tags, vd_comments, vd_options\n\n");
        fwrite($fp, "SET NAMES utf8mb4;\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        // 分类
        if ($opts['export_categories']) {
            if ($opts['export_truncate']) {
                fwrite($fp, "-- 清空分类表\nDELETE FROM `vd_categories`;\n\n");
            }
            $cats = dbAll("SELECT * FROM vd_categories ORDER BY id");
            foreach ($cats as $cat) {
                fwrite($fp, "INSERT INTO `vd_categories` (`id`, `name`, `slug`, `description`) VALUES (");
                fwrite($fp, (int)$cat['id'] . ", ");
                fwrite($fp, self::sqlStr($cat['name']) . ", ");
                fwrite($fp, self::sqlStr($cat['slug']) . ", ");
                fwrite($fp, self::sqlStr($cat['description'] ?? '') . ");\n");
                $stats['categories']++;
            }
            fwrite($fp, "\n");

            // 标签
            if ($opts['export_truncate']) {
                fwrite($fp, "-- 清空标签表\nDELETE FROM `vd_tags`;\n\n");
            }
            $tags = dbAll("SELECT * FROM vd_tags ORDER BY id");
            foreach ($tags as $tag) {
                fwrite($fp, "INSERT INTO `vd_tags` (`id`, `name`, `slug`, `count`) VALUES (");
                fwrite($fp, (int)$tag['id'] . ", ");
                fwrite($fp, self::sqlStr($tag['name']) . ", ");
                fwrite($fp, self::sqlStr($tag['slug']) . ", ");
                fwrite($fp, (int)($tag['count'] ?? 0) . ");\n");
                $stats['tags']++;
            }
            fwrite($fp, "\n");
        }

        // 文章
        if ($opts['export_posts']) {
            if ($opts['export_truncate']) {
                fwrite($fp, "-- 清空文章和关联表\nDELETE FROM `vd_post_tags`;\nDELETE FROM `vd_posts`;\n\n");
            }
            $posts = dbAll("SELECT * FROM vd_posts ORDER BY id");
            foreach ($posts as $post) {
                fwrite($fp, "INSERT INTO `vd_posts` (`id`, `title`, `slug`, `content`, `type`, `status`, `format`, `category_id`, `author`, `created_at`, `updated_at`, `allow_comment`, `views`) VALUES (");
                fwrite($fp, (int)$post['id'] . ", ");
                fwrite($fp, self::sqlStr($post['title']) . ", ");
                fwrite($fp, self::sqlStr($post['slug']) . ", ");
                fwrite($fp, self::sqlStr($post['content']) . ", ");
                fwrite($fp, self::sqlStr($post['type']) . ", ");
                fwrite($fp, self::sqlStr($post['status']) . ", ");
                fwrite($fp, self::sqlStr($post['format']) . ", ");
                fwrite($fp, ($post['category_id'] !== null ? (int)$post['category_id'] : 'NULL') . ", ");
                fwrite($fp, self::sqlStr($post['author'] ?? '') . ", ");
                fwrite($fp, self::sqlStr($post['created_at']) . ", ");
                fwrite($fp, self::sqlStr($post['updated_at']) . ", ");
                fwrite($fp, (int)($post['allow_comment'] ?? 1) . ", ");
                fwrite($fp, (int)($post['views'] ?? 0) . ");\n");

                if ($post['type'] === 'post') $stats['posts']++;
                else $stats['pages']++;
            }
            fwrite($fp, "\n");

            // 文章-标签关联
            $pts = dbAll("SELECT post_id, tag_id FROM vd_post_tags ORDER BY post_id, tag_id");
            foreach ($pts as $pt) {
                fwrite($fp, "INSERT INTO `vd_post_tags` (`post_id`, `tag_id`) VALUES (" . (int)$pt['post_id'] . ", " . (int)$pt['tag_id'] . ");\n");
            }
            fwrite($fp, "\n");
        }

        // 评论
        if ($opts['export_comments']) {
            if ($opts['export_truncate']) {
                fwrite($fp, "-- 清空评论表\nDELETE FROM `vd_comments`;\n\n");
            }
            $comments = dbAll("SELECT * FROM vd_comments ORDER BY id");
            foreach ($comments as $c) {
                fwrite($fp, "INSERT INTO `vd_comments` (`id`, `post_id`, `author`, `email`, `website`, `content`, `status`, `created_at`) VALUES (");
                fwrite($fp, (int)$c['id'] . ", ");
                fwrite($fp, (int)$c['post_id'] . ", ");
                fwrite($fp, self::sqlStr($c['author']) . ", ");
                fwrite($fp, self::sqlStr($c['email']) . ", ");
                fwrite($fp, self::sqlStr($c['website'] ?? '') . ", ");
                fwrite($fp, self::sqlStr($c['content']) . ", ");
                fwrite($fp, self::sqlStr($c['status']) . ", ");
                fwrite($fp, self::sqlStr($c['created_at']) . ");\n");
                $stats['comments']++;
            }
            fwrite($fp, "\n");
        }

        // 站点设置
        if ($opts['export_settings']) {
            $settings = dbAll("SELECT name, value FROM vd_options WHERE name IN (
                'site_name','site_url','site_description','site_keywords',
                'site_icp','footer_code','head_code',
                'social_github','social_twitter','social_email','social_qq','social_weibo',
                'sidebar_about','sidebar_notice',
                'theme_active','posts_per_page','comment_moderation','comment_order',
                'author_name','author_bio','author_avatar','author_banner','author_email'
            )");
            foreach ($settings as $s) {
                // 使用 REPLACE INTO 避免重复键
                fwrite($fp, "REPLACE INTO `vd_options` (`name`, `value`) VALUES (");
                fwrite($fp, self::sqlStr($s['name']) . ", ");
                fwrite($fp, self::sqlStr($s['value']) . ");\n");
                $stats['settings']++;
            }
            fwrite($fp, "\n");
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);

        return $stats;
    }

    // ===================== 导出辅助方法 =====================

    /**
     * XML CDATA 安全化（处理嵌套 ]]> ）
     */
    private static function xmlCdata($str)
    {
        $str = (string)$str;
        return str_replace(']]>', ']]]]><![CDATA[>', $str);
    }

    /**
     * XML 属性值安全化
     */
    private static function xmlAttr($str)
    {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * SQL 字符串转义（单引号包裹）
     */
    private static function sqlStr($str)
    {
        $str = (string)$str;
        return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $str) . "'";
    }

    /**
     * 插件激活
     */
    public static function activate()
    {
        // 创建导入图片目录
        $dir = RYEBLOG_ROOT . '/usr/uploads/import';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        // 创建导出目录
        $exportDir = RYEBLOG_ROOT . '/usr/uploads/export';
        if (!is_dir($exportDir)) @mkdir($exportDir, 0755, true);
        return true;
    }

    /**
     * 插件停用
     */
    public static function deactivate()
    {
        return true;
    }
}
