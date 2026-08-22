/* RyeBlog 后台 Markdown/HTML 编辑器 —— 原创轻量脚本（中英双编辑区）
 * 工具栏：标题/加粗/斜体/代码/引用/列表/链接/上传图片/上传附件/预览
 * 额外能力：拖拽文件到正文框、剪贴板粘贴图片、批量上传、即时插入到光标处
 * 每个 pane（中文版/英文版）独立 initEditor()，共享全局 format-select。
 */
(function () {
    var select = document.getElementById('format-select');

    function initEditor(paneId) {
        var pane   = document.getElementById(paneId);
        if (!pane) return;
        var toolbar = pane.querySelector('.md-toolbar');
        var input   = pane.querySelector('textarea');
        var preview = pane.querySelector('.md-preview');
        var previewBtn = pane.querySelector('.md-preview-btn');
        if (!toolbar || !input) return;

        // ----- 模式切换（与全局 format-select 联动） -----
        function syncMode() {
            var isMd = select ? select.value === 'markdown' : true;
            toolbar.style.display = isMd ? 'flex' : 'none';
            if (!isMd) { preview.style.display = 'none'; preview.innerHTML = ''; }
            input.style.fontFamily = isMd ? 'ui-monospace, Consolas, monospace' : 'inherit';
        }
        if (select) select.addEventListener('change', syncMode);
        syncMode();

        // ----- 工具栏按钮 -----
        // mousedown 阻止默认：按钮不抢焦点，textarea 保持光标位置（长文编辑不跳底）
        toolbar.addEventListener('mousedown', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            if (btn === previewBtn || btn.getAttribute('data-md') === 'upload-image' || btn.getAttribute('data-md') === 'upload-file') return;
            e.preventDefault();
            if (document.activeElement !== input) input.focus({ preventScroll: true });
        });
        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            if (btn === previewBtn) { togglePreview(); return; }
            var kind = btn.getAttribute('data-md');
            if (kind === 'upload-image' || kind === 'upload-file') { return; }
            insertMd(kind);
        });

        // 在长文中插入内容后，把光标放回插入点，但「绝不」改变 textarea 的滚动位置
        // —— 解决点工具栏按钮（链接/图片等）后编辑器跳到底部的问题。
        function placeCaret(start, end) {
            var st = input.scrollTop, sl = input.scrollLeft;
            input.focus({ preventScroll: true });
            try { input.setSelectionRange(start, end); }
            catch (e) { input.selectionStart = start; input.selectionEnd = end; }
            // 还原滚动位置：浏览器在 focus/选区变化后可能把光标滚入视野，这里强制还原
            input.scrollTop = st;
            input.scrollLeft = sl;
            requestAnimationFrame(function () { input.scrollTop = st; input.scrollLeft = sl; });
        }
        function wrap(before, after, placeholder) {
            var s = input.selectionStart, en = input.selectionEnd;
            var sel = input.value.slice(s, en) || placeholder;
            input.value = input.value.slice(0, s) + before + sel + after + input.value.slice(en);
            placeCaret(s + before.length, s + before.length + sel.length);
        }
        function linePrefix(prefix) {
            var s = input.selectionStart;
            var lineStart = input.value.lastIndexOf('\n', s - 1) + 1;
            input.value = input.value.slice(0, lineStart) + prefix + input.value.slice(lineStart);
            placeCaret(s + prefix.length, s + prefix.length);
        }
        function insertMd(kind) {
            switch (kind) {
                case 'h':       linePrefix('## '); break;
                case 'bold':    wrap('**', '**', '加粗文字'); break;
                case 'italic':  wrap('*', '*', '斜体'); break;
                case 'code':    wrap('`', '`', 'code'); break;
                case 'quote':   linePrefix('> '); break;
                case 'ul':      linePrefix('- '); break;
                case 'link':    insertLink(); break;
            }
        }
        // 链接：弹窗输入真实 URL + 文字（不再塞死 https:// 占位）
        function insertLink() {
            var sel = input.value.slice(input.selectionStart, input.selectionEnd);
            var url = window.prompt('链接地址（URL）：', 'https://');
            if (url === null) return;              // 取消
            url = url.trim();
            if (!url) return;
            var text = sel;
            if (!text) {
                text = window.prompt('链接文字（留空则用网址）：', '');
                if (text === null) return;
                text = text.trim() || url;
            }
            wrap('[', '](' + url + ')', text);
        }

        // ----- 预览 -----
        function togglePreview() {
            var showing = preview.style.display === 'block';
            if (showing) { preview.style.display = 'none'; preview.innerHTML = ''; return; }
            preview.style.display = 'block';
            preview.innerHTML = '<p class="muted">渲染中…</p>';
            var url = window.VERDA_PREVIEW || ('/admin/preview.php');
            console.log('[editor] preview POST', url);
            var ctrl = window.AbortController ? new AbortController() : null;
            var timer = null;
            if (ctrl) timer = setTimeout(function () { ctrl.abort(); }, 15000); // 15s 超时兜底
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'md=' + encodeURIComponent(input.value),
                credentials: 'same-origin',
                signal: ctrl ? ctrl.signal : undefined
            })
            .then(function (r) {
                console.log('[editor] preview status', r.status, r.redirected, r.url);
                if (r.redirected && /login/.test(r.url)) {
                    preview.innerHTML = '<p class="muted">登录已过期，请刷新页面后重试。</p>';
                    return null;
                }
                return r.text().then(function (t) {
                    console.log('[editor] preview body', t.length, 'bytes');
                    if (!r.ok) return '<p class="muted" style="color:#b5341f">' + t.replace(/</g, '&lt;') + '</p>';
                    return t;
                });
            })
            .then(function (html) {
                if (timer) clearTimeout(timer);
                if (html !== null) { preview.style.display = 'block'; preview.innerHTML = html; }
            })
            .catch(function (err) {
                if (timer) clearTimeout(timer);
                console.error('[editor] preview error', err);
                preview.innerHTML = '<p class="muted" style="color:#b5341f">预览失败：' + String(err) + '</p>';
            });
        }

        // ============================================================
        //   即时上传（无需先保存就能上传图片/附件，并自动插入当前正文）
        // ============================================================
        var csrf = document.querySelector('#write-form [name=_csrf]');
        var csrfToken = csrf ? csrf.value : '';
        var uploadUrl = window.VERDA_UPLOAD_URL;
        var uploadImageBtn = pane.querySelector('.md-upload-image');
        var uploadFileBtn  = pane.querySelector('.md-upload-file');
        var fileImageInput = pane.querySelector('.upload-image-input');
        var fileAnyInput   = pane.querySelector('.upload-file-input');
        var progressBox    = pane.querySelector('.upload-progress');

        function progress(msg) {
            if (!progressBox) return;
            progressBox.style.display = 'block';
            progressBox.innerHTML = '<span class="up-spin"></span> ' + msg;
        }
        function progressOk(msg) {
            if (!progressBox) return;
            progressBox.className = 'upload-progress up-ok';
            progressBox.innerHTML = '✓ ' + msg;
            setTimeout(function () {
                if (progressBox) {
                    progressBox.style.display = 'none';
                    progressBox.className = 'upload-progress';
                    progressBox.innerHTML = '';
                }
            }, 2400);
        }
        function progressErr(msg) {
            if (!progressBox) return;
            progressBox.className = 'upload-progress up-err';
            progressBox.innerHTML = '✗ ' + msg;
        }

        function uploadOne(file) {
            return new Promise(function (resolve, reject) {
                var fd = new FormData();
                fd.append('file', file);
                fd.append('_csrf', csrfToken);
                fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
                    .then(function (r) {
                        if (r.status >= 200 && r.status < 300 && r.json.success) resolve(r.json);
                        else reject(r.json && r.json.error ? r.json.error : ('HTTP ' + r.status));
                    })
                    .catch(function (err) { reject(err && err.toString ? err.toString() : '网络错误'); });
            });
        }

        function insertUploaded(rec) {
            var isMd = select ? select.value === 'markdown' : true;
            var name = rec.filename || rec.url.split('/').pop();
            var url  = rec.url;
            var insert;
            if (rec.type === 'image') {
                insert = isMd
                    ? ('\n\n![描述](' + url + ')\n\n')
                    : ('\n\n<img src="' + url + '" alt="' + name.replace(/"/g, '&quot;') + '">\n\n');
            } else {
                insert = isMd
                    ? ('\n\n[下载 ' + name + '](' + url + ')\n\n')
                    : ('\n\n<a href="' + url + '" download>' + name + '</a>\n\n');
            }
            var s = input.selectionStart;
            input.value = input.value.slice(0, s) + insert + input.value.slice(s);
            placeCaret(s + insert.length, s + insert.length);
            // 刷新封面选择器（新上传的图片可能被选为封面；仅中文 pane 有）
            if (coverGrid) setTimeout(renderCoverPicker, 50);
        }

        function uploadList(files) {
            if (!files || !files.length) return;
            var queue = [].slice.call(files);
            progress('正在上传 ' + queue.length + ' 个文件…');
            var done = 0, ok = 0, failed = [];
            (function next() {
                if (!queue.length) {
                    if (failed.length === 0) progressOk(ok + ' 个文件已插入正文');
                    else progressErr('完成：成功 ' + ok + ' 个，失败 ' + failed.length + '（' + failed.join('、') + '）');
                    return;
                }
                var f = queue.shift();
                progress('上传中 (' + (++done) + '/' + files.length + ')  ' + f.name);
                uploadOne(f).then(function (rec) {
                    ok++;
                    insertUploaded(rec);
                }).catch(function (err) {
                    failed.push(f.name + '(' + (err || '失败') + ')');
                }).then(next);
            })();
        }

        if (uploadImageBtn && fileImageInput) {
            uploadImageBtn.addEventListener('click', function () { fileImageInput.click(); });
            fileImageInput.addEventListener('change', function () { uploadList(this.files); this.value = ''; });
        }
        if (uploadFileBtn && fileAnyInput) {
            uploadFileBtn.addEventListener('click', function () { fileAnyInput.click(); });
            fileAnyInput.addEventListener('change', function () { uploadList(this.files); this.value = ''; });
        }

        // ----- 拖拽 / 粘贴到正文 -----
        ['dragover', 'dragenter'].forEach(function (ev) {
            input.addEventListener(ev, function (e) {
                if (e.dataTransfer && [].slice.call(e.dataTransfer.types || []).indexOf('Files') !== -1) {
                    e.preventDefault();
                    input.classList.add('drop-target');
                }
            });
        });
        ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
            input.addEventListener(ev, function () { input.classList.remove('drop-target'); });
        });
        input.addEventListener('drop', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.files.length) return;
            e.preventDefault();
            uploadList(e.dataTransfer.files);
        });
        input.addEventListener('paste', function (e) {
            var items = (e.clipboardData && e.clipboardData.items) || [];
            var files = [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    var f = items[i].getAsFile();
                    if (f) files.push(f);
                }
            }
            if (files.length) {
                e.preventDefault();
                uploadList(files);
            }
        });

        // 封面选择器数据源：仅中文 pane 需要（英文正文不参与封面选择）
        if (pane.querySelector('#cover-picker-grid')) {
            input.addEventListener('input', renderCoverPicker);
            input.addEventListener('drop', function () { setTimeout(renderCoverPicker, 600); });
        }
    }

    // ============================================================
    //   封面图选择器（中文正文 → 选封面，全局唯一）
    // ============================================================
    var coverInput = document.getElementById('cover-image-input');
    var coverGrid  = document.getElementById('cover-picker-grid');

    function scanContentForImageUrls() {
        var input = document.getElementById('content-input');
        if (!input) return [];
        var text = input.value;
        var isMd = select ? select.value === 'markdown' : true;
        var urls = [];
        var re;
        if (isMd) {
            re = /!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/gi;
            var m;
            while ((m = re.exec(text)) !== null) urls.push(m[1].trim());
        }
        re = /<img[^>]+src=["']([^"']+)["']/gi;
        var m2;
        while ((m2 = re.exec(text)) !== null) urls.push(m2[1].trim());
        urls = urls.filter(function (u) {
            return u.indexOf('/usr/uploads/') !== -1;
        });
        return urls;
    }

    function renderCoverPicker() {
        if (!coverGrid) return;
        var urls = scanContentForImageUrls();
        var current = coverInput ? coverInput.value : '';
        if (urls.length === 0) {
            coverGrid.innerHTML = '<p class="muted cover-picker-empty">正文中暂无已上传图片。上传图片后会自动出现在这里供选择。</p>';
            return;
        }
        var html = '<div class="cover-option' + (current === '' ? ' selected' : '') + '" data-url="" title="不设封面（自动取第一张）">' +
            '<div class="cover-option-thumb cover-none">🚫</div><span class="cover-option-label">不设</span></div>';
        for (var i = 0; i < urls.length; i++) {
            var u = urls[i];
            html += '<div class="cover-option' + (u === current ? ' selected' : '') + '" data-url="' + u + '" title="' + u + '">' +
                '<div class="cover-option-thumb"><img src="' + u + '" loading="lazy"></div>' +
                '<span class="cover-option-label">图 ' + (i + 1) + '</span></div>';
        }
        coverGrid.innerHTML = html;
        coverGrid.querySelectorAll('.cover-option').forEach(function (el) {
            el.addEventListener('click', function () {
                var url = el.getAttribute('data-url');
                if (coverInput) coverInput.value = url;
                coverGrid.querySelectorAll('.cover-option').forEach(function (x) { x.classList.remove('selected'); });
                el.classList.add('selected');
            });
        });
    }

    // ----- 初始化：中文版 + 英文版两个编辑区 -----
    initEditor('pane-zh');
    if (document.getElementById('pane-en')) initEditor('pane-en');

    if (coverGrid) {
        var zhInput = document.getElementById('content-input');
        if (zhInput) {
            zhInput.addEventListener('input', renderCoverPicker);
            zhInput.addEventListener('drop', function () { setTimeout(renderCoverPicker, 600); });
        }
        renderCoverPicker();
    }
})();
