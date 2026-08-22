/* ===========================================================
   RyeBlog Vuecho 文档主题交互（theme.js）
   - 分类树展开/收起（点击分类名切换）
   - 右侧 TOC 栏：滚动高亮 + 显示/隐藏切换
   - 右下 UX 工具栏：夜间模式 / 字体大小 / 阅读模式
   =========================================================== */
(function () {
    'use strict';

    var STORE = { theme: 'vuecho-theme', font: 'vuecho-font', reading: 'vuecho-reading', toc: 'vuecho-toc' };

    function getLS(k, d) { try { return localStorage.getItem(k) || d; } catch (e) { return d; } }
    function setLS(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

    /* ---------- 1. 分类树：点击分类名展开/收起 ---------- */
    var treeNodes = document.querySelectorAll('body.theme-vuecho .docs-tree .tree-node');
    Array.prototype.forEach.call(treeNodes, function (node) {
        var toggle = node.querySelector('.node-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            node.classList.toggle('expanded');
            var children = node.querySelector(':scope > .tree-children');
            if (children) children.style.display = node.classList.contains('expanded') ? '' : 'none';
        });
        // 分类名点击同样触发
        var name = node.querySelector('.node-name');
        if (name) name.addEventListener('click', function (e) {
            e.preventDefault();
            toggle.click();
        });
    });

    /* ---------- 1.5 移动端汉堡菜单（抽屉目录） ---------- */
    var navToggle = document.getElementById('nav-toggle');
    var mask = document.getElementById('sidebar-mask');
    if (navToggle && mask) {
        function closeMenu() {
            document.body.classList.remove('menu-open');
            navToggle.setAttribute('aria-expanded', 'false');
        }
        navToggle.addEventListener('click', function () {
            var open = document.body.classList.toggle('menu-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        mask.addEventListener('click', closeMenu);
        var sb = document.getElementById('docs-sidebar');
        if (sb) sb.addEventListener('click', function (e) {
            if (e.target.closest('a')) closeMenu();
        });
    }

    /* ---------- 1.6 移动端 TOC 折叠 ---------- */
    var tocMobile = document.getElementById('toc-mobile');
    if (tocMobile) {
        var tocMobileHeader = document.getElementById('toc-mobile-header');
        var tocMobileToggle = document.getElementById('toc-mobile-toggle');
        function toggleTocMobile() {
            tocMobile.classList.toggle('collapsed');
        }
        if (tocMobileHeader) tocMobileHeader.addEventListener('click', toggleTocMobile);
        if (tocMobileToggle) tocMobileToggle.addEventListener('click', function (e) { e.stopPropagation(); toggleTocMobile(); });
    }

    /* ---------- 2. 右侧 TOC：滚动高亮 + 切换 ---------- */
    var tocPanel = document.getElementById('toc-desktop');
    if (tocPanel) {
        var tocLinks = tocPanel.querySelectorAll('.toc-list a');
        var headings = [];
        Array.prototype.forEach.call(tocLinks, function (a) {
            var id = a.getAttribute('href').replace(/^#/, '');
            var el = document.getElementById(id);
            if (el) headings.push({ id: id, el: el, link: a });
        });

        var current = null;
        function setActive(id) {
            if (current === id) return;
            current = id;
            Array.prototype.forEach.call(tocLinks, function (a) {
                a.classList.toggle('active', a.getAttribute('href') === '#' + id);
            });
        }
        function onScroll() {
            if (!headings.length) return;
            var top = window.pageYOffset + 110;
            var found = null;
            for (var i = 0; i < headings.length; i++) {
                if (headings[i].el.getBoundingClientRect().top + window.pageYOffset <= top) found = headings[i].id;
                else break;
            }
            if (!found) found = headings[0].id;
            setActive(found);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        var tocToggle = document.getElementById('toc-desktop-toggle');
        if (tocToggle) {
            tocToggle.addEventListener('click', function () {
                tocPanel.classList.toggle('hidden');
            });
            // 每次进入默认显示 TOC，不记忆隐藏状态
        }
    }

    /* ---------- 2.5 代码块轻量语法高亮（零依赖，pre code） ---------- */
    function highlightCode() {
        var codes = document.querySelectorAll('body.theme-vuecho .article-content pre code');
        Array.prototype.forEach.call(codes, function (code) {
            if (code.getAttribute('data-hl')) return;
            var text = code.textContent;
            if (!text || !text.trim()) return;
            var esc = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            // 字符串（先处理，避免污染内部注释/关键字）
            esc = esc.replace(/(&quot;.*?&quot;|&#39;.*?&#39;|".*?"|'.*?')/g, '<span class="tok-str">$1</span>');
            // 行注释
            esc = esc.replace(/(\/\/[^\n]*|#[^\n]*)/g, '<span class="tok-comment">$1</span>');
            // 关键字
            esc = esc.replace(/\b(function|return|if|else|for|while|foreach|echo|require|include|new|class|public|private|static|const|var|let|def|import|from|and|or|not|in|is|print|true|false|null|TRUE|FALSE|NULL|php|echo)\b/g, '<span class="tok-kw">$1</span>');
            // 数字
            esc = esc.replace(/\b(\d+(?:\.\d+)?)\b/g, '<span class="tok-num">$1</span>');
            code.innerHTML = esc;
            code.setAttribute('data-hl', '1');
        });
    }
    highlightCode();

    /* ---------- 3. 右下 UX 工具栏 ---------- */
    if (document.querySelector('.ux-toolbar')) return;

    var toolbar = document.createElement('div');
    toolbar.className = 'ux-toolbar';
    toolbar.innerHTML =
        '<button class="ux-tool dark-mode-toggle tooltip" data-tooltip="夜间模式" aria-label="夜间模式">' +
        '<svg class="sun-icon" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18M12,2A1,1 0 0,0 11,3V5A1,1 0 0,0 13,5V3A1,1 0 0,0 12,2M17,7A1,1 0 0,0 18,6A1,1 0 0,0 17,5A1,1 0 0,0 16,6A1,1 0 0,0 17,7M21,11A1,1 0 0,0 22,12A1,1 0 0,0 21,13A1,1 0 0,0 20,12A1,1 0 0,0 21,11M17,17A1,1 0 0,0 18,18A1,1 0 0,0 17,19A1,1 0 0,0 16,18A1,1 0 0,0 17,17M12,22A1,1 0 0,0 13,21V19A1,1 0 0,0 11,19V21A1,1 0 0,0 12,22M7,17A1,1 0 0,0 6,18A1,1 0 0,0 7,19A1,1 0 0,0 8,18A1,1 0 0,0 7,17M3,11A1,1 0 0,0 2,12A1,1 0 0,0 3,13A1,1 0 0,0 4,12A1,1 0 0,0 3,11M7,7A1,1 0 0,0 6,6A1,1 0 0,0 7,5A1,1 0 0,0 8,6A1,1 0 0,0 7,7Z"/></svg>' +
        '<svg class="moon-icon" viewBox="0 0 24 24" width="20" height="20" style="display:none"><path fill="currentColor" d="M17.75,4.09L15.22,6.03L16.13,9.09L13.5,7.28L10.87,9.09L11.78,6.03L9.25,4.09L12.44,4L13.5,1L14.56,4L17.75,4.09M18.97,15.95C19.8,15.87 20.69,17.05 20.16,17.8C19.84,18.25 19.5,18.67 19.08,19.07C15.17,23 8.84,23 4.94,19.07C1.03,15.17 1.03,8.83 4.94,4.93C5.34,4.53 5.76,4.17 6.21,3.85C6.96,3.32 8.14,4.21 8.06,5.04C7.79,7.9 8.75,10.87 10.95,13.06C13.14,15.26 16.1,16.22 18.97,15.95M17.33,17.97C14.5,17.81 11.7,16.64 9.53,14.5C7.36,12.31 6.2,9.5 6.04,6.68C3.23,9.82 3.34,14.4 6.35,17.41C9.37,20.43 14,20.54 17.33,17.97Z"/></svg>' +
        '</button>' +
        '<button class="ux-tool font-size-toggle tooltip" data-tooltip="字体大小: 中" data-current-size="1" aria-label="字体大小">' +
        '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M9 4v3h5v12h3V7h5V4H9zm-6 8h3v7h3v-7h3V9H3v3z"/></svg>' +
        '</button>' +
        '<button class="ux-tool reading-mode-toggle tooltip" data-tooltip="阅读模式" aria-label="阅读模式">' +
        '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5v-12c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/></svg>' +
        '</button>';
    document.body.appendChild(toolbar);

    /* --- 夜间模式 --- */
    var darkBtn = toolbar.querySelector('.dark-mode-toggle');
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            darkBtn.classList.add('active');
            darkBtn.querySelector('.sun-icon').style.display = 'none';
            darkBtn.querySelector('.moon-icon').style.display = '';
        } else {
            document.documentElement.removeAttribute('data-theme');
            darkBtn.classList.remove('active');
            darkBtn.querySelector('.sun-icon').style.display = '';
            darkBtn.querySelector('.moon-icon').style.display = 'none';
        }
    }
    applyTheme(getLS(STORE.theme, 'light'));
    darkBtn.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        setLS(STORE.theme, next);
    });

    /* --- 字体大小（三档） --- */
    var fontBtn = toolbar.querySelector('.font-size-toggle');
    var fontSizes = [{ size: 0.95, name: '小' }, { size: 1, name: '中' }, { size: 1.12, name: '大' }];
    function applyFont(size) {
        document.documentElement.style.setProperty('--reading-font-size', size + 'rem');
        var idx = fontSizes.findIndex(function (f) { return Math.abs(f.size - size) < 0.01; });
        if (idx < 0) idx = 1;
        fontBtn.setAttribute('data-current-size', fontSizes[idx].size);
        fontBtn.setAttribute('data-tooltip', '字体大小: ' + fontSizes[idx].name);
    }
    var savedFont = parseFloat(getLS(STORE.font, '1'));
    applyFont(isNaN(savedFont) ? 1 : savedFont);
    fontBtn.addEventListener('click', function () {
        var cur = parseFloat(fontBtn.getAttribute('data-current-size') || '1');
        var idx = fontSizes.findIndex(function (f) { return Math.abs(f.size - cur) < 0.01; });
        idx = (idx + 1) % fontSizes.length;
        applyFont(fontSizes[idx].size);
        setLS(STORE.font, String(fontSizes[idx].size));
    });

    /* --- 阅读模式 --- */
    var readBtn = toolbar.querySelector('.reading-mode-toggle');
    function applyReading(on) {
        document.body.classList.toggle('reading-mode', on);
        readBtn.classList.toggle('active', on);
    }
    applyReading(getLS(STORE.reading, '0') === '1');
    readBtn.addEventListener('click', function () {
        var on = document.body.classList.contains('reading-mode');
        applyReading(!on);
        setLS(STORE.reading, !on ? '1' : '0');
    });
})();
