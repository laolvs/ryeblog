<?php
/**
 * RYE社区 —— 前台子导航
 * 在每个前台页 publicHeader() 之后 include 本文件。
 * 各页通过 $GLOBALS['bbs_page'] = 'forum' 等标记当前页以高亮。
 */
$__page = $GLOBALS['bbs_page'] ?? '';
$__me   = isLoggedIn() ? (int) currentUser()['id'] : 0;

// 未读计数（登录后）
$__unread_notify = 0;
$__unread_msg    = 0;
if ($__me) {
    $__unread_notify = (int) db_val(
        'SELECT COUNT(*) FROM ' . prefix() . 'notifications WHERE user_id=? AND is_read=0',
        [$__me]
    );
    $__unread_msg = (int) db_val(
        'SELECT COUNT(*) FROM ' . prefix() . 'messages WHERE to_user_id=? AND is_read=0',
        [$__me]
    );
}

function __nav_item($key, $label, $url, $badge = 0)
{
    global $__page;
    $active = ($__page === $key) ? ' active' : '';
    $b = $badge > 0 ? '<span class="bbs-badge">' . $badge . '</span>' : '';
    return '<a class="bbs-nav-item' . $active . '" href="' . e($url) . '">' . e($label) . $b . '</a>';
}
?>
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <!-- 经典线条图标集（编辑器工具栏共用） -->
    <symbol id="ic-bold" viewBox="0 0 24 24"><path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/></symbol>
    <symbol id="ic-italic" viewBox="0 0 24 24"><path d="M10 4h8M6 20h8M14 4l-4 16"/></symbol>
    <symbol id="ic-strike" viewBox="0 0 24 24"><path d="M6 6h12M3 12h18M10 6l1 12M14 6l-1 12"/></symbol>
    <symbol id="ic-heading" viewBox="0 0 24 24"><path d="M6 4v16M18 4v16M6 12h12"/></symbol>
    <symbol id="ic-quote" viewBox="0 0 24 24"><path d="M10 11H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v6a4 4 0 0 1-4 4M20 11h-3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v6a4 4 0 0 1-4 4"/></symbol>
    <symbol id="ic-list" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></symbol>
    <symbol id="ic-list-ol" viewBox="0 0 24 24"><path d="M10 6h11M10 12h11M10 18h11"/><text x="3" y="9" font-size="7" font-weight="bold">1</text><text x="3" y="15" font-size="7" font-weight="bold">2</text><text x="3" y="21" font-size="7" font-weight="bold">3</text></symbol>
    <symbol id="ic-code" viewBox="0 0 24 24"><path d="M8 6l-6 6 6 6M16 6l6 6-6 6"/></symbol>
    <symbol id="ic-codeblock" viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM9.5 9l-3 3 3 3M14.5 9l3 3-3 3"/></symbol>
    <symbol id="ic-link" viewBox="0 0 24 24"><path d="M10 14a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07L11.5 5.43M14 10a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></symbol>
    <symbol id="ic-image" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 15l-5-5L8 18"/></symbol>
    <symbol id="ic-attach" viewBox="0 0 24 24"><path d="M21.4 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></symbol>
    <symbol id="ic-upload" viewBox="0 0 24 24"><path d="M12 16V4M6 10l6-6 6 6M4 20h16"/></symbol>
</svg>
<nav class="bbs-nav">
    <div class="bbs-nav-inner">
        <?php echo __nav_item('forum', '版块', bbs_url('forum')); ?>
        <?php echo __nav_item('search', '搜索', bbs_url('search')); ?>
        <?php echo __nav_item('rank', '排行榜', bbs_url('rank')); ?>
        <?php echo __nav_item('about', '关于', bbs_url('about')); ?>
        <?php if ($__me): ?>
            <?php echo __nav_item('notifications', '通知', bbs_url('notifications'), $__unread_notify); ?>
            <?php echo __nav_item('messages', '私信', bbs_url('messages'), $__unread_msg); ?>
            <?php echo __nav_item('favorites', '收藏', bbs_url('favorites')); ?>
            <?php echo __nav_item('drafts', '草稿', bbs_url('drafts')); ?>
            <?php echo __nav_item('user', '我的', bbs_url('user?id=' . $__me)); ?>
        <?php endif; ?>
        <!-- 发帖按钮：始终显示（未登录点击后经 require_login 回登录页，登录后自动回到发帖页） -->
        <a class="bbs-nav-item bbs-nav-post" href="<?php echo e(bbs_url('post' . (!empty($GLOBALS['bbs_post_forum_id']) ? '?forum_id=' . (int) $GLOBALS['bbs_post_forum_id'] : ''))); ?>">✚ 发帖</a>
    </div>
</nav>
<style>
.bbs-nav{background:#1f3d24;position:sticky;top:0;z-index:50;box-shadow:0 1px 4px rgba(0,0,0,.12)}
.bbs-nav-inner{max-width:1000px;margin:0 auto;display:flex;align-items:center;gap:2px;padding:0 12px;overflow-x:auto;white-space:nowrap}
.bbs-nav-item{display:inline-flex;align-items:center;gap:4px;color:#cfe3d2;text-decoration:none;padding:11px 12px;font-size:14px;border-bottom:2px solid transparent}
.bbs-nav-item:hover{color:#fff;background:rgba(255,255,255,.06)}
.bbs-nav-item.active{color:#fff;border-bottom-color:#7fc98a}
.bbs-nav-post{margin-left:auto;background:#fff;color:#1f5c2e;border-radius:6px;padding:7px 14px;margin-top:6px;margin-bottom:6px;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,.28)}
.bbs-nav-post:hover{background:#d9f0df;color:#145025}
.bbs-badge{display:inline-block;min-width:16px;height:16px;line-height:16px;text-align:center;background:#e0533d;color:#fff;border-radius:9px;font-size:11px;padding:0 4px}
/* 论坛侧栏（替代博客侧栏，参照 chake.org 版块导航） */
.bbs-sidebar{display:flex;flex-direction:column;gap:14px}
.bbs-widget{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:14px 16px}
.bbs-widget-intro p{margin:0;font-size:13px;line-height:1.8;color:#3a4a3e;text-align:center}
.bbs-widget-head{display:flex;align-items:center;justify-content:space-between}
.bbs-widget-title{margin:0 0 10px;font-size:15px;color:#1f3d24}
.bbs-widget-toggle{background:none;border:none;cursor:pointer;color:#2c7d3f;font-size:14px;padding:0 4px}
.bbs-widget-body.collapsed{display:none}
.bbs-side-sec{margin:8px 0 4px;font-size:12px;color:#8a968c;font-weight:600;border-bottom:1px dashed #eef2ec;padding-bottom:3px}
.bbs-side-forum{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 4px;border-radius:7px;text-decoration:none}
.bbs-side-forum:hover{background:#f3f8f1}
.bsf-name{font-size:14px;color:#2c5234;font-weight:500}
.bsf-stats{font-size:12px;color:#8a968c;white-space:nowrap}
.bbs-stats{list-style:none;margin:0;padding:0}
.bbs-stats li{display:flex;justify-content:space-between;font-size:13px;color:#3a4a3e;padding:5px 0;border-bottom:1px dashed #eef2ec}
.bbs-stats li:last-child{border-bottom:none}
.bbs-stats b{color:#2c7d3f}
/* 论坛主内容基础样式（forum 版块页 / 通用，绿色系） */
.forum-section{margin-bottom:20px}
.section-title{margin:0 0 12px;font-size:17px;color:#1f3d24;padding-left:10px;border-left:4px solid #2c7d3f}
.forum-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.forum-card{display:block;background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:14px 16px;text-decoration:none;transition:box-shadow .15s,border-color .15s}
.forum-card:hover{box-shadow:0 3px 10px rgba(44,125,63,.12);border-color:#cfe6c8}
.fname{font-size:15px;font-weight:600;margin-bottom:5px}
.fdesc{font-size:12px;color:#7a8a7e;line-height:1.6;margin-bottom:8px}
.fstat{font-size:12px;color:#8a968c}
.thread-head{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:16px 18px;margin-bottom:14px}
.thread-head h1{margin:0 0 6px;font-size:20px;color:#1f3d24}
.row-between{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.panel{background:#fff;border:1px solid #e3eadf;border-radius:12px}
.panel-body{padding:6px 18px}
.thread-item{display:flex;gap:12px;padding:13px 0;border-bottom:1px dashed #eef2ec;align-items:flex-start}
.thread-item:last-child{border-bottom:none}
.thread-avatar{width:38px;height:38px;border-radius:50%;background:#eef3ec;color:#2c7d3f;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.thread-main{flex:1;min-width:0}
.thread-title{display:block;font-size:15px;color:#1f3d24;text-decoration:none;margin-bottom:4px}
.thread-title:hover{color:#2c7d3f}
.thread-meta{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:#8a968c}
.thread-meta a{color:#2c7d3f;text-decoration:none}
.empty{padding:34px 16px;text-align:center;color:#8a968c;font-size:14px}
.muted{color:#7a8a7e;font-size:13px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:1px solid transparent;border-radius:8px;padding:8px 16px;font-size:14px;cursor:pointer;text-decoration:none;background:#eef3ec;color:#2c5234}
.btn:hover{background:#e3eadf;color:#1f3d24}
.btn-primary{background:#2c7d3f;color:#fff}
.btn-primary:hover{background:#339149;color:#fff}
.pagination{display:flex;gap:6px;margin-top:16px;flex-wrap:wrap}
.pagination a,.pagination span{display:inline-flex;min-width:30px;height:30px;align-items:center;justify-content:center;border:1px solid #cfd9c8;border-radius:7px;font-size:13px;color:#2c5234;text-decoration:none;background:#fff}
.pagination a:hover{background:#f3f8f1;border-color:#cfe6c8}
.pagination .current{background:#2c7d3f;color:#fff;border-color:#2c7d3f}
.tag{display:inline-block;border-radius:6px;padding:1px 8px;font-size:12px;margin-left:6px;vertical-align:middle}
.tag-top{background:#fff3df;color:#b5742b;border:1px solid #ffe3b8}
.tag-good{background:#eaf3e6;color:#2c5234;border:1px solid #cfe6c8}
.tag-cat{background:#e8f0ff;color:#2b5fb3;border:1px solid #cfe0ff}
/* 论坛首页 Tab（最新/热门/精华）+ 版块名徽标 */
.forum-tabs{display:flex;gap:6px;flex-wrap:wrap}
.forum-tab{display:inline-flex;align-items:center;padding:5px 14px;border:1px solid #cfd9c8;border-radius:18px;font-size:13px;color:#2c5234;text-decoration:none;background:#fff}
.forum-tab:hover{background:#f3f8f1;border-color:#cfe6c8}
.forum-tab.active{background:#2c7d3f;color:#fff;border-color:#2c7d3f}
.thread-forum{color:#2c7d3f;text-decoration:none;font-weight:500}
.thread-forum:hover{text-decoration:underline}
@media (max-width:1024px){.forum-grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width:640px){.forum-grid{grid-template-columns:1fr}}
/* 编辑器工具栏（发帖/回复共用，插入 Markdown 语法；经典线条图标） */
.rye-toolbar{display:flex;flex-wrap:wrap;gap:3px;align-items:center;border:1px solid #cfd9c8;border-bottom:none;border-radius:8px 8px 0 0;background:#f7faf5;padding:5px 7px}
.rye-toolbar button{border:1px solid transparent;background:none;border-radius:6px;min-width:28px;height:28px;padding:0;cursor:pointer;color:#3a4a3e;display:inline-flex;align-items:center;justify-content:center}
.rye-toolbar button:hover{background:#e3eadf;border-color:#cfe0c8;color:#145025}
.rye-toolbar button:active{background:#d3e6cf}
.rye-toolbar svg.ic{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.rye-toolbar svg.ic text{fill:currentColor;stroke:none;font-family:inherit}
.rye-tb-sep{width:1px;height:18px;background:#dde6da;margin:0 5px}
.rye-editor{border-radius:0 0 8px 8px !important;border-top:none !important}
/* 代码块复制按钮 */
.td-content pre{position:relative}
.rye-copy-btn{position:absolute;top:8px;right:8px;border:1px solid #cfd9c8;background:#fff;color:#2c5234;border-radius:6px;padding:3px 10px;font-size:12px;cursor:pointer;opacity:0;transition:opacity .15s;font-family:inherit}
.td-content pre:hover .rye-copy-btn{opacity:1}
.rye-copy-btn.ok{background:#2c7d3f;color:#fff;border-color:#2c7d3f}
.rye-toolbar button:disabled{opacity:.6;cursor:wait}
</style>
<script>
// 上传配置（登录用户可上传；后台可关）
window.RYE_CSRF = "<?php echo csrfToken(); ?>";
window.RYE_UPLOAD_URL = "<?php echo e(bbs_url('upload')); ?>";
window.RYE_UPLOAD_ENABLED = <?php echo setting('upload_enabled', '1') === '1' ? 'true' : 'false'; ?>;
window.RYE_LOGIN_URL = "<?php echo e(baseUrl('user/login.php')); ?>";

function ryeWrapMd(ta, mode){
    var start = ta.selectionStart, end = ta.selectionEnd;
    var sel = ta.value.substring(start, end);
    var pre = '', post = '', rep;
    switch (mode) {
        case 'bold': pre = '**'; post = '**'; break;
        case 'italic': pre = '*'; post = '*'; break;
        case 'strike': pre = '~~'; post = '~~'; break;
        case 'code': pre = '`'; post = '`'; break;
        case 'h2': pre = '## '; break;
        case 'quote': pre = '> '; break;
        case 'ul': pre = '- '; break;
        case 'ol': pre = '1. '; break;
        case 'codeblock':
            if (sel) { pre = '```\n'; post = '\n```'; }
            else { rep = '```\n\n```'; }
            break;
        case 'link':
            if (sel) { pre = '['; post = '](https://)'; }
            else { rep = '[链接文字](https://)'; }
            break;
        case 'image':
            if (sel) { pre = '!['; post = '](https://)'; }
            else { rep = '![图片描述](https://)'; }
            break;
    }
    if (rep !== undefined) {
        ta.setRangeText(rep, start, end, 'end');
    } else if (sel && post === '' && sel.indexOf('\n') >= 0) {
        // 行首插入类且多行选中：每行加前缀
        var lines = sel.split('\n').map(function(l){ return pre + l; }).join('\n');
        ta.setRangeText(lines, start, end, 'end');
    } else {
        var wrapped = pre + sel + post;
        ta.setRangeText(wrapped, start, end, 'end');
        if (!sel) { ta.selectionStart = ta.selectionEnd = start + pre.length; }
    }
    ta.focus();
}
// ---- 图片 / 附件上传（AJAX → /bbs/upload，成功插入 markdown） ----
function ryeUploadFile(file, ta, btn, isImg){
    if (!window.RYE_UPLOAD_ENABLED) { alert('管理员已关闭上传功能'); return; }
    if (!window.RYE_CSRF) { alert('页面加载未完成，请刷新后重试'); return; }
    var fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', RYE_CSRF);
    var oldHtml = btn.innerHTML;
    btn.disabled = true; btn.textContent = '上传中…';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', RYE_UPLOAD_URL, true);
    xhr.onload = function(){
        btn.disabled = false; btn.innerHTML = oldHtml;
        var res = null;
        try { res = JSON.parse(xhr.responseText); } catch(e){}
        if (xhr.status === 200 && res && res.success) {
            var md;
            if (isImg) {
                var alt = (file.name || '图片').replace(/\.[^.]+$/, '');
                md = '![' + alt + '](' + res.url + ')';
            } else {
                md = '[' + (file.name || '附件') + '](' + res.url + ')';
            }
            if (ta) {
                var pos = ta.selectionStart != null ? ta.selectionStart : ta.value.length;
                var end = ta.selectionEnd != null ? ta.selectionEnd : ta.value.length;
                ta.value = ta.value.slice(0, pos) + md + '\n' + ta.value.slice(end);
                ta.selectionStart = ta.selectionEnd = pos + md.length;
                ta.focus();
            }
        } else {
            if (xhr.status === 401) {
                alert('请先登录后再上传');
                location.href = RYE_LOGIN_URL || '/user/login.php';
                return;
            }
            alert(res && res.error ? res.error : '上传失败，请重试');
        }
    };
    xhr.onerror = function(){ btn.disabled = false; btn.innerHTML = oldHtml; alert('上传失败，请检查网络'); };
    xhr.send(fd);
}

// ---- 代码块复制按钮（.td-content pre 右上角） ----
function ryeCopyFallback(txt){
    var ta = document.createElement('textarea');
    ta.value = txt;
    ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch(e){}
    ta.remove();
}
function ryeAddCopyButtons(){
    document.querySelectorAll('.td-content pre').forEach(function(pre){
        if (pre.querySelector('.rye-copy-btn')) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rye-copy-btn';
        btn.textContent = '复制';
        btn.addEventListener('click', function(){
            var code = pre.querySelector('code');
            var txt = code ? code.textContent : pre.textContent;
            var done = function(){
                btn.textContent = '✓ 已复制'; btn.classList.add('ok');
                setTimeout(function(){ btn.textContent = '复制'; btn.classList.remove('ok'); }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(done, function(){ ryeCopyFallback(txt); done(); });
            } else { ryeCopyFallback(txt); done(); }
        });
        pre.appendChild(btn);
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ryeAddCopyButtons);
} else {
    ryeAddCopyButtons();
}

// 楼层锚点平滑滚动（回复成功跳转 #reply-N 时定位）
(function(){
    if (location.hash && location.hash.indexOf('#reply-') === 0) {
        var go = function(){
            var el = document.getElementById(location.hash.slice(1));
            if (el) { el.scrollIntoView({behavior:'smooth', block:'start'}); }
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go);
        else setTimeout(go, 80);
    }
})();

// 回复框「@TA」快捷插入：点击楼层 @TA 把昵称插到回复框
document.addEventListener('click', function(ev){
    var t = ev.target;
    if (!t || !t.closest) return;
    var at = t.closest('.rye-at');
    if (!at) return;
    ev.preventDefault();
    var ta = document.querySelector('.reply-form textarea.rye-editor');
    if (!ta) return;
    var name = at.getAttribute('data-at') || '';
    if (!name) return;
    var ins = '@' + name + ' ';
    var pos = ta.selectionStart != null ? ta.selectionStart : ta.value.length;
    ta.value = ta.value.slice(0, pos) + ins + ta.value.slice(ta.selectionEnd != null ? ta.selectionEnd : pos);
    ta.selectionStart = ta.selectionEnd = pos + ins.length;
    ta.focus();
});

// 工具栏按钮：mousedown 阻止默认（按钮不抢焦点，textarea 光标不丢，长文编辑不跳底）
document.addEventListener('mousedown', function(ev){
    var t = ev.target;
    if (!t || !t.closest) return;
    var btn = t.closest('button[data-md]');
    if (!btn) return;
    ev.preventDefault();
    var tb = btn.closest('.rye-toolbar');
    var ta = tb ? tb.nextElementSibling : null;
    while (ta && ta.tagName !== 'TEXTAREA') { ta = ta.nextElementSibling; }
    if (ta && document.activeElement !== ta) ta.focus();
});
document.addEventListener('click', function(ev){
    var t = ev.target;
    if (!t || !t.closest) return;
    var btn = t.closest('button[data-md]');
    if (!btn) return;
    ev.preventDefault();
    var tb = btn.closest('.rye-toolbar');
    var ta = tb ? tb.nextElementSibling : null;
    while (ta && ta.tagName !== 'TEXTAREA') { ta = ta.nextElementSibling; }
    if (ta) { ryeWrapMd(ta, btn.getAttribute('data-md')); }
});
document.addEventListener('click', function(ev){
    var t = ev.target;
    if (!t || !t.closest) return;
    var btn = t.closest('button[data-upload]');
    if (!btn) return;
    var tb = btn.closest('.rye-toolbar');
    var ta = tb ? tb.nextElementSibling : null;
    while (ta && ta.tagName !== 'TEXTAREA') { ta = ta.nextElementSibling; }
    if (!window.RYE_UPLOAD_ENABLED) { alert('管理员已关闭上传功能'); return; }
    var accept = btn.getAttribute('data-upload') === 'img' ? 'image/*' : '';
    var inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = accept;
    inp.style.display = 'none';
    inp.addEventListener('change', function(){
        if (inp.files && inp.files.length) {
            ryeUploadFile(inp.files[0], ta, btn, btn.getAttribute('data-upload') === 'img');
        }
        inp.remove();
    });
    document.body.appendChild(inp);
    inp.click();
});
(function(){
    document.querySelectorAll('.bbs-widget-toggle').forEach(function(btn){
        btn.addEventListener('click', function(){
            var body = btn.closest('.bbs-widget').querySelector('.bbs-widget-body');
            if (body) {
                body.classList.toggle('collapsed');
                btn.textContent = body.classList.contains('collapsed') ? '▾' : '▴';
            }
        });
    });
})();
</script>

