<?php
/**
 * RyeBlog 导航与友情链接插件
 * -----------------------------------------------------------------------------
 * - 开启「导航」：博客顶栏出现「导航」入口；独立频道页 /page/{slug} 展示参考图风格的导航站
 *   （左侧分组侧栏 + 右侧分组卡片网格 + 全部/国内/国外 顶部子 Tab + 搜索过滤 + scrollspy）。
 * - 开启「友情链接」：首页底部出现友情链接卡片网格。
 * - 同时开启：首页底部和独立频道都展示对应内容。
 * - 主题颜色全部使用博客主题 CSS 变量（--green-* / --g-*），随主题切换而变化。
 * - 频道页通过核心 page.php 新增的 doHook('page_replace') 钩子渲染；
 *   顶栏通过 inc/view.php 新增的 doHook('nav_top') 钩子注入；
 *   首页底部通过 index.php 新增的 doHook('home_after') 钩子注入。
 *
 * @Title    导航与友情链接
 * @Desc     独立的导航目录与友情链接插件，两个开关可分别或同时启用；导航布局参考 DesNav：侧栏 + 卡片网格 + 全部/国内/国外 子 Tab + 搜索；主题随博客主题切换。
 * @Version  1.0.0
 * @Author   RyeBlog Team
 */

class Plugin_nav_links
{
    // ================== 配置项键 ==================
    const OPT_ENABLE_NAV    = 'navlinks_enable_nav';
    const OPT_ENABLE_FRIEND = 'navlinks_enable_friend';
    const OPT_CHANNEL_FRIEND = 'navlinks_channel_friend';
    const OPT_CHANNEL_SLUG  = 'navlinks_channel_slug';
    const OPT_CHANNEL_TITLE = 'navlinks_channel_title';
    const OPT_CHANNEL_TITLE_EN = 'navlinks_channel_title_en';
    const OPT_CARD_COLS     = 'navlinks_card_cols';

    // ================== 表名 ==================
    const TBL_GROUPS = 'vd_nav_groups';
    const TBL_LINKS  = 'vd_nav_links';

    // ================== 基础 + 卡片样式（前端共享） ==================
    const CSS_BASE = <<<'CSS'
.nl-card{display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--white);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);text-decoration:none;color:inherit;transition:transform .18s,border-color .18s,box-shadow .18s}
.nl-card:hover{border-color:var(--green-400);transform:translateY(-1px);box-shadow:0 10px 24px rgba(46,107,53,.14)}
.nl-card-logo{width:44px;height:44px;border-radius:10px;background:var(--green-050);color:var(--green-700);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;flex:none;overflow:hidden}
.nl-card-logo img{width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block}
.nl-card-body{min-width:0;flex:1}
.nl-card-title{font-weight:600;color:var(--ink);font-size:15px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nl-card-desc{color:var(--muted);font-size:12.5px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical}
.nl-empty{padding:40px 20px;text-align:center;color:var(--muted);background:var(--green-025);border:1px dashed var(--line);border-radius:var(--radius)}
.nl-empty a{color:var(--green-700);font-weight:600}
CSS;

    // ================== 频道页专用样式 ==================
    const CSS_CHANNEL = <<<'CSS'
.nl-channel-wrap{position:relative}
.nl-channel{display:flex;gap:22px;align-items:flex-start}
.nl-channel-sidebar{width:206px;flex:none;background:var(--white);border:1px solid var(--line);border-radius:var(--radius);padding:14px;position:sticky;top:16px;max-height:calc(100vh - 32px);overflow:auto;box-shadow:var(--shadow)}
.nl-channel-search{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px;background:var(--green-025);color:var(--ink);outline:none;margin-bottom:12px;box-sizing:border-box}
.nl-channel-search:focus{border-color:var(--green-500);background:var(--white)}
.nl-channel-sideitem{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;color:var(--ink);text-decoration:none;font-size:14px;cursor:pointer;transition:background .12s,color .12s;border:none;background:transparent;width:100%;text-align:left}
.nl-channel-sideitem:hover{background:var(--green-050);color:var(--green-700)}
.nl-channel-sideitem.is-active{background:var(--green-500);color:#fff;font-weight:600}
.nl-channel-sideicon{width:20px;text-align:center;flex:none;font-size:15px}
.nl-channel-main{flex:1;min-width:0}
.nl-channel-tabs{display:flex;gap:4px;padding:4px;background:var(--white);border:1px solid var(--line);border-radius:999px;width:max-content;margin:0 0 18px;box-shadow:var(--shadow)}
.nl-channel-tab{padding:7px 18px;border-radius:999px;font-size:13.5px;color:var(--muted);cursor:pointer;border:none;background:none;transition:background .15s,color .15s}
.nl-channel-tab.is-active{background:var(--green-500);color:#fff}
.nl-channel-section{margin-bottom:30px;scroll-margin-top:80px}
.nl-channel-section-title{font-size:16px;font-weight:700;color:var(--ink);margin:0 0 12px;display:flex;align-items:center;gap:8px}
.nl-channel-section-title::before{content:"";display:inline-block;width:4px;height:16px;background:var(--green-500);border-radius:2px}
.nl-channel-grid{display:grid;grid-template-columns:repeat(var(--nl-cols,4),1fr);gap:12px}
.nl-channel-section.is-friend .nl-channel-section-title::before{background:var(--green-400)}
.nl-channel-empty{padding:18px;text-align:center;color:var(--muted);background:var(--green-025);border-radius:8px;font-size:13.5px}
@media(max-width:1100px){.nl-channel-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:860px){.nl-channel{grid-template-columns:1fr;gap:14px}.nl-channel-sidebar{position:static;width:100%;max-height:none}.nl-channel-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.nl-channel-grid{grid-template-columns:1fr}}
body.rye-nav-channel .sidebar{display:none}
body.rye-nav-channel .content-col{flex:0 0 100%;max-width:100%}
CSS;

    // ================== 首页友情链接区域样式 ==================
    const CSS_FRIEND = <<<'CSS'
.nl-friend{margin:36px 0 8px;padding:24px;background:var(--white);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow)}
.nl-friend-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px}
.nl-friend-title{font-size:18px;font-weight:700;color:var(--ink);margin:0;display:flex;align-items:center;gap:8px}
.nl-friend-title::before{content:"";display:inline-block;width:4px;height:18px;background:var(--green-500);border-radius:2px}
.nl-friend-more{color:var(--muted);font-size:13px;text-decoration:none}
.nl-friend-more:hover{color:var(--green-700)}
.nl-friend-group{margin-bottom:22px}
.nl-friend-group-title{font-size:14px;font-weight:600;color:var(--muted);margin:0 0 10px;display:flex;align-items:center;gap:6px}
.nl-friend-group-title::before{content:"";display:inline-block;width:3px;height:12px;background:var(--green-400);border-radius:2px}
.nl-friend-grid{display:grid;grid-template-columns:repeat(var(--nl-cols,4),1fr);gap:12px}
@media(max-width:1100px){.nl-friend-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:860px){.nl-friend-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.nl-friend-grid{grid-template-columns:1fr}}
CSS;

    // ================== 管理后台样式 ==================
    const CSS_ADMIN = <<<'CSS'
.nl-admin-tabs{display:flex;gap:0;border-bottom:2px solid var(--g-200);margin:0 0 18px}
.nl-admin-tabs a{padding:10px 20px;color:var(--g-500);text-decoration:none;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;font-size:14px;display:inline-block}
.nl-admin-tabs a.is-active{color:var(--accent);border-bottom-color:var(--accent)}
.nl-admin-panel{background:#fff;border:1px solid var(--line);border-radius:8px;padding:18px;margin-bottom:18px}
.nl-admin-panel h3{margin:0 0 12px;color:var(--g-700);font-size:15px}
.nl-admin-table{width:100%;border-collapse:collapse;margin-top:8px}
.nl-admin-table th,.nl-admin-table td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;font-size:13.5px;vertical-align:middle}
.nl-admin-table th{background:var(--g-050);color:var(--g-700);font-weight:600;font-size:13px}
.nl-admin-table tbody tr:hover td{background:var(--g-025)}
.nl-admin-table .nl-thumb{width:30px;height:30px;border-radius:6px;background:var(--g-050);color:var(--g-700);display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;overflow:hidden;vertical-align:middle;margin-right:8px}
.nl-admin-table .nl-thumb img{width:100%;height:100%;object-fit:cover}
.nl-admin-form .form-row{margin-bottom:12px}
.nl-admin-form .form-row label{display:block;font-weight:600;color:var(--g-700);font-size:13px;margin-bottom:4px}
.nl-admin-form input[type=text],.nl-admin-form input[type=url],.nl-admin-form input[type=number],.nl-admin-form select,.nl-admin-form textarea{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:6px;font-size:14px;box-sizing:border-box;font-family:inherit}
.nl-admin-form textarea{min-height:62px;resize:vertical}
.nl-admin-form .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:640px){.nl-admin-form .form-grid{grid-template-columns:1fr}}
.nl-admin-actions{display:inline-flex;gap:4px;flex-wrap:wrap;align-items:center}
.nl-admin-actions .btn-mini{padding:3px 8px;font-size:12px;border:1px solid var(--line);background:#fff;border-radius:5px;cursor:pointer;color:var(--g-700);text-decoration:none;display:inline-block;line-height:1.4}
.nl-admin-actions .btn-mini:hover{border-color:var(--g-500);color:var(--accent)}
.nl-admin-actions .btn-mini.danger{color:#c0392b}
.nl-admin-actions .btn-mini.danger:hover{border-color:#c0392b}
.nl-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:var(--g-100);color:var(--g-700)}
.nl-pill.off{background:#eee;color:#999}
.nl-filterbar{display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
.nl-filterbar select,.nl-filterbar input{padding:6px 10px;border:1px solid var(--line);border-radius:6px;font-size:13px}
CSS;

    // ================== 频道页交互 JS ==================
    const JS_CHANNEL = <<<'JS'
(function(){
  var root = document.querySelector('.nl-channel-wrap');
  if(!root) return;
  var input = root.querySelector('.nl-channel-search');
  var cards = root.querySelectorAll('.nl-card[data-region]');
  var sections = root.querySelectorAll('.nl-channel-section[id]');
  var sideItems = root.querySelectorAll('.nl-channel-sideitem');
  var tabs = root.querySelectorAll('.nl-channel-tab');
  var curRegion = 'all';
  function apply(){
    var q = (input && input.value || '').trim().toLowerCase();
    cards.forEach(function(c){
      var r = c.getAttribute('data-region') || '';
      var t = (c.getAttribute('data-search') || '').toLowerCase();
      var matchR = (curRegion==='all') || (curRegion===r);
      var matchQ = !q || t.indexOf(q)!==-1;
      c.style.display = (matchR && matchQ) ? '' : 'none';
    });
    sections.forEach(function(sec){
      var cnt = 0;
      sec.querySelectorAll('.nl-card').forEach(function(c){
        if(getComputedStyle(c).display !== 'none') cnt++;
      });
      var empty = sec.querySelector('.nl-channel-empty');
      if(empty) empty.style.display = cnt>0 ? 'none' : '';
    });
  }
  if(input) input.addEventListener('input', apply);
  tabs.forEach(function(t){
    t.addEventListener('click', function(){
      tabs.forEach(function(x){x.classList.remove('is-active')});
      t.classList.add('is-active');
      curRegion = t.getAttribute('data-region') || 'all';
      apply();
    });
  });
  function setActive(id){
    sideItems.forEach(function(s){
      s.classList.toggle('is-active', s.getAttribute('data-target')===id);
    });
  }
  function spy(){
    var rootTop = root.getBoundingClientRect().top + window.pageYOffset;
    if(window.pageYOffset < rootTop + 40){ setActive('__top__'); return; }
    var cur = null;
    sections.forEach(function(s){
      var top = s.getBoundingClientRect().top;
      if(top < 140) cur = s.id;
    });
    setActive(cur || '__top__');
  }
  var ticking = false;
  window.addEventListener('scroll', function(){
    if(!ticking){ window.requestAnimationFrame(function(){ spy(); ticking=false; }); ticking=true; }
  }, {passive:true});
  window.addEventListener('resize', spy);
  spy();
  sideItems.forEach(function(s){
    s.addEventListener('click', function(ev){
      var id = s.getAttribute('data-target');
      if(!id || id==='__top__'){
        ev.preventDefault();
        setActive('__top__');
        var top = root.getBoundingClientRect().top + window.pageYOffset - 16;
        window.scrollTo({top: top, behavior:'smooth'});
        return;
      }
      var el = document.getElementById(id);
      if(el){ ev.preventDefault(); el.scrollIntoView({behavior:'smooth', block:'start'}); }
    });
  });
})();
JS;

    // ================================================================
    //  生命周期
    // ================================================================

    /** 激活：建表 + 默认设置 + 频道页 + 默认分组 */
    public static function activate()
    {
        self::ensureTables();
        if (self::getSetting(self::OPT_ENABLE_NAV) === null)    self::setSetting(self::OPT_ENABLE_NAV, 1);
        if (self::getSetting(self::OPT_ENABLE_FRIEND) === null) self::setSetting(self::OPT_ENABLE_FRIEND, 1);
        if (self::getSetting(self::OPT_CHANNEL_FRIEND) === null) self::setSetting(self::OPT_CHANNEL_FRIEND, 1);
        if (self::getSetting(self::OPT_CHANNEL_SLUG) === null)  self::setSetting(self::OPT_CHANNEL_SLUG, 'nav');
        if (self::getSetting(self::OPT_CHANNEL_TITLE) === null) self::setSetting(self::OPT_CHANNEL_TITLE, '导航');
        if (self::getSetting(self::OPT_CARD_COLS) === null)     self::setSetting(self::OPT_CARD_COLS, 4);
        self::ensureChannelPage(self::getSetting(self::OPT_CHANNEL_SLUG), self::getSetting(self::OPT_CHANNEL_TITLE));
        self::ensureDefaultGroups();
        return true;
    }

    /** 停用：保留数据（分组 / 链接 / 频道页 / 设置），仅从启用列表移除 */
    public static function deactivate()
    {
        return true;
    }

    private static function ensureTables()
    {
        dbQuery("CREATE TABLE IF NOT EXISTS " . self::TBL_GROUPS . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            icon VARCHAR(40) NOT NULL DEFAULT '',
            type VARCHAR(20) NOT NULL DEFAULT 'nav',
            sort INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            KEY idx_type_status (type,status,sort)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        dbQuery("CREATE TABLE IF NOT EXISTS " . self::TBL_LINKS . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id INT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL,
            url VARCHAR(500) NOT NULL,
            description VARCHAR(500) NOT NULL DEFAULT '',
            logo VARCHAR(500) NOT NULL DEFAULT '',
            region VARCHAR(20) NOT NULL DEFAULT '',
            target VARCHAR(10) NOT NULL DEFAULT '_blank',
            sort INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_group_sort (group_id,sort),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function ensureChannelPage($slug, $title)
    {
        $slug = trim((string)$slug);
        $title = trim((string)$title);
        if ($slug === '' || $title === '') return;
        $exists = dbOne("SELECT id FROM vd_posts WHERE type='page' AND slug=?", [$slug]);
        if ($exists) {
            dbQuery("UPDATE vd_posts SET title=? WHERE id=?", [$title, (int)$exists['id']]);
            return;
        }
        $author = (string)(dbOne("SELECT username FROM vd_users ORDER BY id LIMIT 1")['username'] ?? 'admin');
        if ($author === '') $author = 'admin';
        dbQuery("INSERT INTO vd_posts (type, slug, title, content, excerpt, format, status, author, created_at, updated_at)
                VALUES ('page', ?, ?, '', '', 'html', 'published', ?, NOW(), NOW())",
            [$slug, $title, $author]);
    }

    private static function ensureDefaultGroups()
    {
        $cnt = (int)(dbOne("SELECT COUNT(*) c FROM " . self::TBL_GROUPS)['c'] ?? 0);
        if ($cnt > 0) return;
        // 默认：一个「友情链接」分组 + 一条 RyeBlog 官方友链
        dbQuery("INSERT INTO " . self::TBL_GROUPS . " (name,icon,type,sort,status) VALUES (?,?,?,?,?)", ['友情链接', '🤝', 'friend', 10, 'active']);
        $gid = (int)(dbOne("SELECT id FROM " . self::TBL_GROUPS . " WHERE type='friend' ORDER BY id LIMIT 1")['id'] ?? 0);
        if ($gid) {
            dbQuery("INSERT INTO " . self::TBL_LINKS . " (group_id,title,url,description,region,target,sort,status) VALUES (?,?,?,?,?,?,?,?)",
                [$gid, 'RyeBlog博客系统', 'https://ryeblog.com/', '免费开源的中英文博客系统！', '', '_blank', 10, 'active']);
        }
    }

    // ================================================================
    //  设置读写 / Flash / 重定向
    // ================================================================

    private static function getSetting($k, $d = null)
    {
        $v = getOption($k, null);
        return $v === null ? $d : $v;
    }

    private static function setSetting($k, $v)
    {
        setOption($k, (string)$v);
    }

    private static function setFlash($type, $text)
    {
        setOption('navlinks_flash', json_encode(['type' => $type, 'text' => $text], JSON_UNESCAPED_UNICODE));
    }

    private static function readFlash()
    {
        $raw = getOption('navlinks_flash', '');
        if ($raw === '') return null;
        setOption('navlinks_flash', '');
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }

    private static function redirect($tab)
    {
        header('Location: ' . baseUrl('admin/plugin-config.php?dir=nav-links&tab=' . $tab));
        exit;
    }

    // ================================================================
    //  管理后台
    // ================================================================

    public static function config()
    {
        $flash = self::readFlash();
        $tab = $_GET['tab'] ?? 'links';
        if (!in_array($tab, ['links', 'groups', 'settings'], true)) $tab = 'links';

        $html = '<style>' . self::CSS_ADMIN . '</style>';
        $html .= self::renderTabs($tab);
        if ($flash) {
            $cls = $flash['type'] === 'err' ? 'notice-err' : 'notice-ok';
            $html .= '<div class="notice ' . $cls . '" style="margin-bottom:14px">' . esc($flash['text']) . '</div>';
        }
        if ($tab === 'links')        $html .= self::renderLinksTab();
        elseif ($tab === 'groups')   $html .= self::renderGroupsTab();
        else                          $html .= self::renderSettingsTab();
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
                'label' => '链接管理',
                'url'   => baseUrl('admin/plugin-config.php?dir=nav-links&tab=links'),
                'desc'  => '添加 / 编辑 / 删除导航与友情链接',
                'icon'  => '🔗',
            ],
            [
                'label' => '分组管理',
                'url'   => baseUrl('admin/plugin-config.php?dir=nav-links&tab=groups'),
                'desc'  => '管理链接分组（导航分组 / 友情链接分组）',
                'icon'  => '📂',
            ],
        ];
    }

    private static function renderTabs($active)
    {
        $items = ['links' => '链接管理', 'groups' => '分组管理', 'settings' => '基本设置'];
        $h = '<div class="nl-admin-tabs">';
        foreach ($items as $k => $v) {
            $cls = $k === $active ? ' class="is-active"' : '';
            $h .= '<a' . $cls . ' href="' . baseUrl('admin/plugin-config.php?dir=nav-links&tab=' . $k) . '">' . $v . '</a>';
        }
        $h .= '</div>';
        return $h;
    }

    private static function renderSettingsTab()
    {
        $en = (int)self::getSetting(self::OPT_ENABLE_NAV, 1);
        $ef = (int)self::getSetting(self::OPT_ENABLE_FRIEND, 1);
        $cf = (int)self::getSetting(self::OPT_CHANNEL_FRIEND, 1);
        $slug = self::getSetting(self::OPT_CHANNEL_SLUG, 'nav');
        $title = self::getSetting(self::OPT_CHANNEL_TITLE, '导航');
        $titleEn = self::getSetting(self::OPT_CHANNEL_TITLE_EN, '');
        $cols = (int)self::getSetting(self::OPT_CARD_COLS, 4);
        $csrf = csrfToken();
        $channelUrl = pageUrl(['slug' => $slug]);

        $h = '<div class="nl-admin-panel"><h3>基本设置</h3>';
        $h .= '<form class="nl-admin-form" method="post">';
        $h .= '<input type="hidden" name="_csrf" value="' . esc($csrf) . '">';
        $h .= '<input type="hidden" name="action" value="save_settings">';
        $h .= '<div class="form-row"><label style="font-weight:500"><input type="checkbox" name="enable_nav" value="1"' . ($en ? ' checked' : '') . '> 开启「导航」（顶栏入口 + 独立频道页 /page/' . esc($slug) . '）</label></div>';
        $h .= '<div class="form-row"><label style="font-weight:500"><input type="checkbox" name="enable_friend" value="1"' . ($ef ? ' checked' : '') . '> 开启「友情链接」（首页底部 + 频道页底部）</label></div>';
        $h .= '<div class="form-row" style="padding-left:24px"><label style="font-weight:500"><input type="checkbox" name="channel_friend" value="1"' . ($cf ? ' checked' : '') . '> 频道页底部显示友情链接（关闭后频道页只显示导航分组，友链仅留在首页底部）</label></div>';
        $h .= '<div class="form-grid">';
        $h .= '<div class="form-row"><label>独立频道 slug（访问路径）</label><input type="text" name="channel_slug" value="' . esc($slug) . '" maxlength="60" pattern="[A-Za-z0-9\\-]+"> <small class="muted">当前地址：' . esc($channelUrl) . '</small></div>';
        $h .= '<div class="form-row"><label>频道页标题</label><input type="text" name="channel_title" value="' . esc($title) . '" maxlength="120"></div>';
        if (bilingualEnabled()) $h .= '<div class="form-row"><label>频道页标题（英文，/en 下显示）</label><input type="text" name="channel_title_en" value="' . esc($titleEn) . '" maxlength="120"></div>';
        $h .= '</div>';
        $h .= '<div class="form-row"><label>卡片每行数量</label><select name="card_cols">';
        foreach ([3, 4, 5, 6] as $n) $h .= '<option value="' . $n . '"' . ($cols === $n ? ' selected' : '') . '>' . $n . ' 列</option>';
        $h .= '</select></div>';
        $h .= '<div class="form-row"><button class="button" type="submit">保存设置</button></div>';
        $h .= '</form></div>';

        $h .= '<div class="nl-admin-panel"><h3>说明</h3>';
        $h .= '<ul style="margin:0;padding-left:20px;color:var(--muted);line-height:1.9">';
        $h .= '<li>同时开启「导航」和「友情链接」：首页底部与独立频道都会展示对应内容。</li>';
        $h .= '<li>只开启「导航」：顶栏出现「' . esc($title) . '」入口，访问 <a href="' . esc($channelUrl) . '" target="_blank">' . esc($channelUrl) . '</a> 查看独立频道。</li>';
        $h .= '<li>只开启「友情链接」：仅首页底部展示友情链接卡片网格。</li>';
        $h .= '<li>主题颜色随博客主题自动切换（fresh / forest / mint）。</li>';
        $h .= '</ul></div>';
        return $h;
    }

    private static function renderGroupsTab()
    {
        $editId = (int)($_GET['edit_gid'] ?? 0);
        $editG = $editId ? self::getGroup($editId) : null;
        $groups = dbAll("SELECT g.*, (SELECT COUNT(*) FROM " . self::TBL_LINKS . " WHERE group_id=g.id) AS link_count FROM " . self::TBL_GROUPS . " g ORDER BY g.sort ASC, g.id ASC");
        $csrf = csrfToken();

        $h = '<div class="nl-admin-panel">';
        $h .= '<h3>' . ($editG ? '编辑分组' : '添加分组') . '</h3>';
        $h .= self::renderGroupForm($editG);
        $h .= '</div>';

        $h .= '<div class="nl-admin-panel"><h3>分组列表（' . count($groups) . '）</h3>';
        if (!$groups) {
            $h .= '<p class="muted">还没有分组，先添加一个吧。</p>';
        } else {
            $h .= '<table class="nl-admin-table"><thead><tr><th style="width:70px">排序</th><th>名称</th><th style="width:80px">图标</th><th style="width:110px">类型</th><th style="width:80px">链接数</th><th style="width:80px">状态</th><th style="width:240px">操作</th></tr></thead><tbody>';
            foreach ($groups as $g) {
                $typeLabel = $g['type'] === 'friend' ? '友情链接' : '导航';
                $statusLabel = $g['status'] === 'active' ? '<span class="nl-pill">显示</span>' : '<span class="nl-pill off">隐藏</span>';
                $icon = $g['icon'] !== '' ? esc($g['icon']) : '<span class="muted">—</span>';
                $h .= '<tr>';
                $h .= '<td>' . (int)$g['sort'] . '</td>';
                $h .= '<td><strong>' . esc($g['name']) . '</strong></td>';
                $h .= '<td>' . $icon . '</td>';
                $h .= '<td>' . $typeLabel . '</td>';
                $h .= '<td>' . (int)$g['link_count'] . '</td>';
                $h .= '<td>' . $statusLabel . '</td>';
                $h .= '<td><div class="nl-admin-actions">';
                $h .= '<form method="post" style="display:inline"><input type="hidden" name="_csrf" value="' . esc($csrf) . '"><input type="hidden" name="action" value="move_group"><input type="hidden" name="id" value="' . (int)$g['id'] . '"><button class="btn-mini" name="dir" value="up" title="上移">↑</button><button class="btn-mini" name="dir" value="down" title="下移">↓</button></form>';
                $h .= '<a class="btn-mini" href="' . baseUrl('admin/plugin-config.php?dir=nav-links&tab=groups&edit_gid=' . (int)$g['id']) . '">编辑</a>';
                $h .= '<form method="post" style="display:inline" onsubmit="return confirm(\'确定删除该分组？\\n（若分组下有链接，需先删除或移走链接）\')"><input type="hidden" name="_csrf" value="' . esc($csrf) . '"><input type="hidden" name="action" value="delete_group"><input type="hidden" name="id" value="' . (int)$g['id'] . '"><button class="btn-mini danger" type="submit">删除</button></form>';
                $h .= '</div></td>';
                $h .= '</tr>';
            }
            $h .= '</tbody></table>';
        }
        $h .= '</div>';
        return $h;
    }

    private static function renderGroupForm($group)
    {
        $csrf = csrfToken();
        $isEdit = $group ? true : false;
        $id = $group ? (int)$group['id'] : 0;
        $name = $group['name'] ?? '';
        $nameEn = $group['name_en'] ?? '';
        $icon = $group['icon'] ?? '';
        $type = $group['type'] ?? 'nav';
        $sort = $group['sort'] ?? 0;
        $status = $group['status'] ?? 'active';
        $action = $isEdit ? 'edit_group' : 'add_group';

        $h = '<form class="nl-admin-form" method="post">';
        $h .= '<input type="hidden" name="_csrf" value="' . esc($csrf) . '">';
        $h .= '<input type="hidden" name="action" value="' . $action . '">';
        if ($isEdit) $h .= '<input type="hidden" name="id" value="' . $id . '">';
        $h .= '<div class="form-grid">';
        $h .= '<div class="form-row"><label>名称 *</label><input type="text" name="name" value="' . esc($name) . '" required maxlength="120"></div>';
        if (bilingualEnabled()) $h .= '<div class="form-row"><label>名称（英文，/en 下显示）</label><input type="text" name="name_en" value="' . esc($nameEn) . '" maxlength="120"></div>';
        $h .= '<div class="form-row"><label>图标（emoji 或文字）</label><input type="text" name="icon" value="' . esc($icon) . '" maxlength="40" placeholder="如 🛠 / 📂"></div>';
        $h .= '</div>';
        $h .= '<div class="form-grid">';
        $h .= '<div class="form-row"><label>类型 *</label><select name="type">';
        $h .= '<option value="nav"' . ($type === 'nav' ? ' selected' : '') . '>导航（频道页分组）</option>';
        $h .= '<option value="friend"' . ($type === 'friend' ? ' selected' : '') . '>友情链接（首页 + 频道底部）</option>';
        $h .= '</select></div>';
        $h .= '<div class="form-row"><label>排序' . ($isEdit ? '' : ' <small class="muted">（留空自动）</small>') . '</label><input type="number" name="sort" value="' . (int)$sort . '" min="0" max="9999"></div>';
        $h .= '</div>';
        $h .= '<div class="form-row"><label>状态</label><select name="status"><option value="active"' . ($status === 'active' ? ' selected' : '') . '>显示</option><option value="hidden"' . ($status === 'hidden' ? ' selected' : '') . '>隐藏</option></select></div>';
        $h .= '<div class="form-row">';
        $h .= '<button class="button" type="submit">' . ($isEdit ? '保存修改' : '添加分组') . '</button>';
        if ($isEdit) $h .= ' <a class="btn-mini" href="' . baseUrl('admin/plugin-config.php?dir=nav-links&tab=groups') . '" style="margin-left:8px">取消</a>';
        $h .= '</div></form>';
        return $h;
    }

    private static function renderLinksTab()
    {
        $groups = dbAll("SELECT * FROM " . self::TBL_GROUPS . " ORDER BY sort ASC, id ASC");
        $editId = (int)($_GET['edit'] ?? 0);
        $editLink = $editId ? self::getLink($editId) : null;
        $filterGid = (int)($_GET['filter_group'] ?? 0);
        $links = self::getAllLinks($filterGid ?: null);
        $csrf = csrfToken();

        $h = '';

        // 筛选
        $h .= '<div class="nl-admin-panel">';
        $h .= '<form class="nl-filterbar" method="get">';
        $h .= '<input type="hidden" name="dir" value="nav-links"><input type="hidden" name="tab" value="links">';
        $h .= '<span>按分组筛选：</span><select name="filter_group" onchange="this.form.submit()"><option value="0">全部</option>';
        foreach ($groups as $g) {
            $sel = $filterGid === (int)$g['id'] ? ' selected' : '';
            $typeShort = $g['type'] === 'friend' ? '友' : '导';
            $h .= '<option value="' . (int)$g['id'] . '"' . $sel . '>' . esc($g['name']) . ' [' . $typeShort . ']</option>';
        }
        $h .= '</select></form></div>';

        // 表单
        $h .= '<div class="nl-admin-panel"><h3>' . ($editLink ? '编辑链接' : '添加链接') . '</h3>';
        if (!$groups) {
            $h .= '<p class="muted" style="color:#c0392b">请先在「分组」Tab 添加至少一个分组。</p>';
        } else {
            $h .= self::renderLinkForm($editLink, $groups);
        }
        $h .= '</div>';

        // 列表
        $h .= '<div class="nl-admin-panel"><h3>链接列表（' . count($links) . '）</h3>';
        if (!$links) {
            $h .= '<p class="muted">还没有链接。</p>';
        } else {
            $h .= '<table class="nl-admin-table"><thead><tr><th style="width:60px">排序</th><th>标题</th><th style="width:140px">分组</th><th>链接</th><th style="width:70px">区域</th><th style="width:70px">状态</th><th style="width:250px">操作</th></tr></thead><tbody>';
            foreach ($links as $l) {
                $regLabel = $l['region'] === 'domestic' ? '国内' : ($l['region'] === 'foreign' ? '国外' : '<span class="muted">—</span>');
                $statusLabel = $l['status'] === 'active' ? '<span class="nl-pill">显示</span>' : '<span class="nl-pill off">隐藏</span>';
                $thumb = $l['logo'] !== ''
                    ? '<span class="nl-thumb"><img src="' . esc($l['logo']) . '" alt="" referrerpolicy="no-referrer"></span>'
                    : '<span class="nl-thumb">' . esc(mb_substr($l['title'], 0, 1, 'utf-8')) . '</span>';
                $h .= '<tr>';
                $h .= '<td>' . (int)$l['sort'] . '</td>';
                $h .= '<td>' . $thumb . '<strong>' . esc($l['title']) . '</strong>'
                    . ($l['description'] !== '' ? '<div class="muted" style="font-size:12px">' . esc($l['description']) . '</div>' : '')
                    . '</td>';
                $h .= '<td>' . esc($l['group_name']) . '</td>';
                $h .= '<td><a href="' . esc($l['url']) . '" target="_blank" rel="noopener" style="color:var(--accent);font-size:12px;word-break:break-all">' . esc($l['url']) . '</a></td>';
                $h .= '<td>' . $regLabel . '</td>';
                $h .= '<td>' . $statusLabel . '</td>';
                $h .= '<td><div class="nl-admin-actions">';
                $h .= '<form method="post" style="display:inline"><input type="hidden" name="_csrf" value="' . esc($csrf) . '"><input type="hidden" name="action" value="move_link"><input type="hidden" name="id" value="' . (int)$l['id'] . '"><button class="btn-mini" name="dir" value="up" title="上移">↑</button><button class="btn-mini" name="dir" value="down" title="下移">↓</button></form>';
                $editUrl = 'admin/plugin-config.php?dir=nav-links&tab=links&edit=' . (int)$l['id'] . ($filterGid ? '&filter_group=' . $filterGid : '');
                $h .= '<a class="btn-mini" href="' . baseUrl($editUrl) . '">编辑</a>';
                $h .= '<form method="post" style="display:inline" onsubmit="return confirm(\'确定删除该链接？\')"><input type="hidden" name="_csrf" value="' . esc($csrf) . '"><input type="hidden" name="action" value="delete_link"><input type="hidden" name="id" value="' . (int)$l['id'] . '"><button class="btn-mini danger" type="submit">删除</button></form>';
                $h .= '</div></td>';
                $h .= '</tr>';
            }
            $h .= '</tbody></table>';
        }
        $h .= '</div>';
        return $h;
    }

    private static function renderLinkForm($link, $groups)
    {
        $csrf = csrfToken();
        $isEdit = $link ? true : false;
        $id = $link ? (int)$link['id'] : 0;
        $gid = (int)($link['group_id'] ?? 0);
        $title = $link['title'] ?? '';
        $titleEn = $link['title_en'] ?? '';
        $url = $link['url'] ?? '';
        $desc = $link['description'] ?? '';
        $descEn = $link['description_en'] ?? '';
        $logo = $link['logo'] ?? '';
        $region = $link['region'] ?? '';
        $target = $link['target'] ?? '_blank';
        $sort = $link['sort'] ?? 0;
        $status = $link['status'] ?? 'active';
        $action = $isEdit ? 'edit_link' : 'add_link';

        $h = '<form class="nl-admin-form" method="post">';
        $h .= '<input type="hidden" name="_csrf" value="' . esc($csrf) . '">';
        $h .= '<input type="hidden" name="action" value="' . $action . '">';
        if ($isEdit) $h .= '<input type="hidden" name="id" value="' . $id . '">';
        $h .= '<div class="form-grid">';
        $h .= '<div class="form-row"><label>所属分组 *</label><select name="group_id" required>';
        foreach ($groups as $g) {
            $sel = $gid === (int)$g['id'] ? ' selected' : '';
            $typeShort = $g['type'] === 'friend' ? '友' : '导';
            $h .= '<option value="' . (int)$g['id'] . '"' . $sel . '>' . esc($g['name']) . ' [' . $typeShort . ']</option>';
        }
        $h .= '</select></div>';
        $h .= '<div class="form-row"><label>排序' . ($isEdit ? '' : ' <small class="muted">（留空自动追加）</small>') . '</label><input type="number" name="sort" value="' . (int)$sort . '" min="0" max="9999"></div>';
        $h .= '</div>';
        $h .= '<div class="form-row"><label>标题 *</label><input type="text" name="title" value="' . esc($title) . '" required maxlength="200"></div>';
        if (bilingualEnabled()) $h .= '<div class="form-row"><label>标题（英文，/en 下显示）</label><input type="text" name="title_en" value="' . esc($titleEn) . '" maxlength="200"></div>';
        $h .= '<div class="form-row"><label>链接 URL *</label><input type="url" name="url" value="' . esc($url) . '" required maxlength="500" placeholder="https://"></div>';
        $h .= '<div class="form-row"><label>描述（一句话说明）</label><input type="text" name="description" value="' . esc($desc) . '" maxlength="500"></div>';
        if (bilingualEnabled()) $h .= '<div class="form-row"><label>描述（英文，/en 下显示）</label><input type="text" name="description_en" value="' . esc($descEn) . '" maxlength="500"></div>';
        $h .= '<div class="form-grid">';
        $h .= '<div class="form-row"><label>Logo URL <small class="muted">（留空用标题首字母）</small></label><input type="url" name="logo" value="' . esc($logo) . '" maxlength="500" placeholder="https://"></div>';
        $h .= '<div class="form-row"><label>区域（用于顶部 Tab 过滤）</label><select name="region"><option value=""' . ($region === '' ? ' selected' : '') . '>不限</option><option value="domestic"' . ($region === 'domestic' ? ' selected' : '') . '>国内</option><option value="foreign"' . ($region === 'foreign' ? ' selected' : '') . '>国外</option></select></div>';
        $h .= '</div>';
        $h .= '<div class="form-grid">';
        $h .= '<div class="form-row"><label>打开方式</label><select name="target"><option value="_blank"' . ($target === '_blank' ? ' selected' : '') . '>新窗口</option><option value="_self"' . ($target === '_self' ? ' selected' : '') . '>当前窗口</option></select></div>';
        $h .= '<div class="form-row"><label>状态</label><select name="status"><option value="active"' . ($status === 'active' ? ' selected' : '') . '>显示</option><option value="hidden"' . ($status === 'hidden' ? ' selected' : '') . '>隐藏</option></select></div>';
        $h .= '</div>';
        $h .= '<div class="form-row"><button class="button" type="submit">' . ($isEdit ? '保存修改' : '添加链接') . '</button>';
        if ($isEdit) $h .= ' <a class="btn-mini" href="' . baseUrl('admin/plugin-config.php?dir=nav-links&tab=links') . '" style="margin-left:8px">取消</a>';
        $h .= '</div></form>';
        return $h;
    }

    // ================================================================
    //  POST 处理（PRG：处理后 setFlash + redirect，exit）
    // ================================================================

    public static function saveConfig($post)
    {
        $action = $post['action'] ?? '';
        $tab = 'links';
        try {
            switch ($action) {
                case 'save_settings':
                    $tab = 'settings';
                    $en = !empty($post['enable_nav']) ? 1 : 0;
                    $ef = !empty($post['enable_friend']) ? 1 : 0;
                    $cf = !empty($post['channel_friend']) ? 1 : 0;
                    $slug = trim((string)($post['channel_slug'] ?? 'nav'));
                    if ($slug === '' || !preg_match('/^[A-Za-z0-9\-]+$/', $slug)) {
                        self::setFlash('err', '频道 slug 只能包含字母、数字、连字符');
                        break;
                    }
                    if (strlen($slug) > 60) { self::setFlash('err', '频道 slug 过长'); break; }
                    $title = trim((string)($post['channel_title'] ?? '导航'));
                    if ($title === '') $title = '导航';
                    if (mb_strlen($title) > 120) { self::setFlash('err', '频道标题过长'); break; }
                    $cols = max(2, min(6, (int)($post['card_cols'] ?? 4)));
                    $oldSlug = self::getSetting(self::OPT_CHANNEL_SLUG, 'nav');
                    self::setSetting(self::OPT_ENABLE_NAV, $en);
                    self::setSetting(self::OPT_ENABLE_FRIEND, $ef);
                    self::setSetting(self::OPT_CHANNEL_FRIEND, $cf);
                    self::setSetting(self::OPT_CHANNEL_SLUG, $slug);
                    self::setSetting(self::OPT_CHANNEL_TITLE, $title);
                    if (bilingualEnabled()) {
                        $titleEn = trim((string)($post['channel_title_en'] ?? ''));
                        if (mb_strlen($titleEn) > 120) { self::setFlash('err', '频道标题（英文）过长'); break; }
                        self::setSetting(self::OPT_CHANNEL_TITLE_EN, $titleEn);
                    }
                    self::setSetting(self::OPT_CARD_COLS, $cols);
                    if ($slug !== $oldSlug) {
                        self::ensureChannelPage($slug, $title);
                    } else {
                        dbQuery("UPDATE vd_posts SET title=? WHERE type='page' AND slug=?", [$title, $slug]);
                    }
                    self::setFlash('ok', '设置已保存。');
                    break;

                case 'add_group':
                    $tab = 'groups';
                    $r = self::saveGroup($post, false);
                    self::setFlash($r === true ? 'ok' : 'err', $r === true ? '分组已添加。' : $r);
                    break;

                case 'edit_group':
                    $tab = 'groups';
                    $r = self::saveGroup($post, true);
                    self::setFlash($r === true ? 'ok' : 'err', $r === true ? '分组已更新。' : $r);
                    break;

                case 'delete_group':
                    $tab = 'groups';
                    $id = (int)($post['id'] ?? 0);
                    if ($id) {
                        $cnt = (int)(dbOne("SELECT COUNT(*) c FROM " . self::TBL_LINKS . " WHERE group_id=?", [$id])['c'] ?? 0);
                        if ($cnt > 0) {
                            self::setFlash('err', '该分组下还有 ' . $cnt . ' 个链接，请先删除或移走链接');
                            break;
                        }
                        dbQuery("DELETE FROM " . self::TBL_GROUPS . " WHERE id=?", [$id]);
                        self::renumberGroups();
                        self::setFlash('ok', '分组已删除。');
                    }
                    break;

                case 'move_group':
                    $tab = 'groups';
                    $id = (int)($post['id'] ?? 0);
                    $dir = ($post['dir'] ?? '') === 'down' ? 'down' : 'up';
                    $r = self::swapGroupSort($id, $dir);
                    if ($r === 'none') self::setFlash('err', $dir === 'up' ? '已是最顶分组' : '已是最底分组');
                    elseif ($r) self::setFlash('ok', '排序已更新。');
                    else self::setFlash('err', '操作失败');
                    break;

                case 'add_link':
                    $tab = 'links';
                    $r = self::saveLink($post, false);
                    self::setFlash($r === true ? 'ok' : 'err', $r === true ? '链接已添加。' : $r);
                    break;

                case 'edit_link':
                    $tab = 'links';
                    $r = self::saveLink($post, true);
                    self::setFlash($r === true ? 'ok' : 'err', $r === true ? '链接已更新。' : $r);
                    break;

                case 'delete_link':
                    $tab = 'links';
                    $id = (int)($post['id'] ?? 0);
                    if ($id) {
                        $cur = self::getLink($id);
                        dbQuery("DELETE FROM " . self::TBL_LINKS . " WHERE id=?", [$id]);
                        if ($cur) self::renumberGroupLinks((int)$cur['group_id']);
                        self::setFlash('ok', '链接已删除。');
                    }
                    break;

                case 'move_link':
                    $tab = 'links';
                    $id = (int)($post['id'] ?? 0);
                    $dir = ($post['dir'] ?? '') === 'down' ? 'down' : 'up';
                    $r = self::swapLinkSort($id, $dir);
                    if ($r === 'none') self::setFlash('err', $dir === 'up' ? '已是该分组最上' : '已是该分组最下');
                    elseif ($r) self::setFlash('ok', '排序已更新。');
                    else self::setFlash('err', '操作失败');
                    break;

                default:
                    self::setFlash('err', '未知操作：' . esc($action));
            }
        } catch (\Throwable $e) {
            self::setFlash('err', '服务器错误：' . $e->getMessage());
        }
        self::redirect($tab);
    }

    private static function saveGroup($post, $isEdit)
    {
        $id = (int)($post['id'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        if ($name === '') return '分组名称不能为空';
        if (mb_strlen($name) > 120) return '分组名称过长';
        $type = ($post['type'] ?? 'nav') === 'friend' ? 'friend' : 'nav';
        $icon = trim((string)($post['icon'] ?? ''));
        if (mb_strlen($icon) > 40) $icon = mb_substr($icon, 0, 40);
        $status = ($post['status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';
        $sort = (int)($post['sort'] ?? 0);
        $nameEn = bilingualEnabled() ? trim((string)($post['name_en'] ?? '')) : '';
        if ($nameEn !== '' && mb_strlen($nameEn) > 120) $nameEn = mb_substr($nameEn, 0, 120);
        if ($isEdit) {
            $cur = self::getGroup($id);
            if (!$cur) return '分组不存在';
            if ($sort <= 0) $sort = (int)$cur['sort'];
            if (bilingualEnabled()) {
                dbQuery("UPDATE " . self::TBL_GROUPS . " SET name=?, name_en=?, icon=?, type=?, sort=?, status=? WHERE id=?",
                    [$name, $nameEn, $icon, $type, $sort, $status, $id]);
            } else {
                dbQuery("UPDATE " . self::TBL_GROUPS . " SET name=?, icon=?, type=?, sort=?, status=? WHERE id=?",
                    [$name, $icon, $type, $sort, $status, $id]);
            }
        } else {
            $max = (int)(dbOne("SELECT COALESCE(MAX(sort),0) s FROM " . self::TBL_GROUPS)['s'] ?? 0);
            if ($sort <= 0) $sort = $max + 10;
            if (bilingualEnabled()) {
                dbQuery("INSERT INTO " . self::TBL_GROUPS . " (name,name_en,icon,type,sort,status) VALUES (?,?,?,?,?,?)",
                    [$name, $nameEn, $icon, $type, $sort, $status]);
            } else {
                dbQuery("INSERT INTO " . self::TBL_GROUPS . " (name,icon,type,sort,status) VALUES (?,?,?,?,?)",
                    [$name, $icon, $type, $sort, $status]);
            }
        }
        self::renumberGroups();
        return true;
    }

    private static function saveLink($post, $isEdit)
    {
        $id = (int)($post['id'] ?? 0);
        $gid = (int)($post['group_id'] ?? 0);
        $g = $gid ? self::getGroup($gid) : null;
        if (!$g) return '请选择有效的分组';
        $title = trim((string)($post['title'] ?? ''));
        if ($title === '') return '标题不能为空';
        if (mb_strlen($title) > 200) return '标题过长';
        $url = trim((string)($post['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return '请输入有效的 URL（含 http/https）';
        if (strlen($url) > 500) return 'URL 过长';
        $desc = trim((string)($post['description'] ?? ''));
        if (mb_strlen($desc) > 500) $desc = mb_substr($desc, 0, 500);
        $titleEn = bilingualEnabled() ? trim((string)($post['title_en'] ?? '')) : '';
        $descEn = bilingualEnabled() ? trim((string)($post['description_en'] ?? '')) : '';
        if ($titleEn !== '' && mb_strlen($titleEn) > 200) $titleEn = mb_substr($titleEn, 0, 200);
        if ($descEn !== '' && mb_strlen($descEn) > 500) $descEn = mb_substr($descEn, 0, 500);
        $logo = trim((string)($post['logo'] ?? ''));
        if (strlen($logo) > 500) $logo = substr($logo, 0, 500);
        $region = in_array($post['region'] ?? '', ['', 'domestic', 'foreign'], true) ? $post['region'] : '';
        $target = ($post['target'] ?? '_blank') === '_self' ? '_self' : '_blank';
        $status = ($post['status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';
        $sort = (int)($post['sort'] ?? 0);
        if ($isEdit) {
            $cur = self::getLink($id);
            if (!$cur) return '链接不存在';
            if ($sort <= 0) $sort = (int)$cur['sort'];
            $oldGid = (int)$cur['group_id'];
            if (bilingualEnabled()) {
                dbQuery("UPDATE " . self::TBL_LINKS . " SET group_id=?, title=?, title_en=?, url=?, description=?, description_en=?, logo=?, region=?, target=?, sort=?, status=? WHERE id=?",
                    [$gid, $title, $titleEn, $url, $desc, $descEn, $logo, $region, $target, $sort, $status, $id]);
            } else {
                dbQuery("UPDATE " . self::TBL_LINKS . " SET group_id=?, title=?, url=?, description=?, logo=?, region=?, target=?, sort=?, status=? WHERE id=?",
                    [$gid, $title, $url, $desc, $logo, $region, $target, $sort, $status, $id]);
            }
            self::renumberGroupLinks($gid);
            if ($oldGid !== $gid) self::renumberGroupLinks($oldGid);
        } else {
            if ($sort <= 0) {
                $max = (int)(dbOne("SELECT COALESCE(MAX(sort),0) s FROM " . self::TBL_LINKS . " WHERE group_id=?", [$gid])['s'] ?? 0);
                $sort = $max + 10;
            }
            if (bilingualEnabled()) {
                dbQuery("INSERT INTO " . self::TBL_LINKS . " (group_id,title,title_en,url,description,description_en,logo,region,target,sort,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [$gid, $title, $titleEn, $url, $desc, $descEn, $logo, $region, $target, $sort, $status]);
            } else {
                dbQuery("INSERT INTO " . self::TBL_LINKS . " (group_id,title,url,description,logo,region,target,sort,status) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$gid, $title, $url, $desc, $logo, $region, $target, $sort, $status]);
            }
            self::renumberGroupLinks($gid);
        }
        return true;
    }

    // ================================================================
    //  数据访问
    // ================================================================

    private static function getGroups($type = null, $includeHidden = false)
    {
        $sql = "SELECT * FROM " . self::TBL_GROUPS;
        $w = []; $p = [];
        if ($type) { $w[] = 'type=?'; $p[] = $type; }
        if (!$includeHidden) $w[] = "status='active'";
        if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
        $sql .= ' ORDER BY sort ASC, id ASC';
        return dbAll($sql, $p) ?: [];
    }

    private static function getGroup($id)
    {
        return dbOne("SELECT * FROM " . self::TBL_GROUPS . " WHERE id=?", [(int)$id]) ?: null;
    }

    private static function getLinksInGroup($gid, $includeHidden = false)
    {
        $sql = "SELECT * FROM " . self::TBL_LINKS . " WHERE group_id=?";
        $p = [(int)$gid];
        if (!$includeHidden) $sql .= " AND status='active'";
        $sql .= " ORDER BY sort ASC, id ASC";
        return dbAll($sql, $p) ?: [];
    }

    private static function getLinksByType($type, $includeHidden = false)
    {
        $sql = "SELECT l.* FROM " . self::TBL_LINKS . " l INNER JOIN " . self::TBL_GROUPS . " g ON l.group_id=g.id WHERE g.type=?";
        $p = [$type];
        if (!$includeHidden) $sql .= " AND l.status='active' AND g.status='active'";
        $sql .= " ORDER BY g.sort ASC, l.sort ASC, l.id ASC";
        return dbAll($sql, $p) ?: [];
    }

    private static function getLink($id)
    {
        return dbOne("SELECT * FROM " . self::TBL_LINKS . " WHERE id=?", [(int)$id]) ?: null;
    }

    private static function getAllLinks($filterGroupId = null)
    {
        $sql = "SELECT l.*, g.name AS group_name, g.type AS group_type
                FROM " . self::TBL_LINKS . " l
                INNER JOIN " . self::TBL_GROUPS . " g ON l.group_id=g.id";
        $p = [];
        if ($filterGroupId) { $sql .= " WHERE l.group_id=?"; $p = [(int)$filterGroupId]; }
        $sql .= " ORDER BY g.sort ASC, l.sort ASC, l.id ASC";
        return dbAll($sql, $p) ?: [];
    }

    /** 将分组内链接按当前顺序重排为 10,20,30...（消除同 sort 导致的移动失效） */
    private static function renumberGroupLinks($gid)
    {
        $rows = dbAll("SELECT id FROM " . self::TBL_LINKS . " WHERE group_id=? ORDER BY sort ASC, id ASC", [(int)$gid]);
        $i = 10;
        foreach ($rows as $r) {
            dbQuery("UPDATE " . self::TBL_LINKS . " SET sort=? WHERE id=?", [$i, (int)$r['id']]);
            $i += 10;
        }
    }

    private static function renumberGroups()
    {
        $rows = dbAll("SELECT id FROM " . self::TBL_GROUPS . " ORDER BY sort ASC, id ASC");
        $i = 10;
        foreach ($rows as $r) {
            dbQuery("UPDATE " . self::TBL_GROUPS . " SET sort=? WHERE id=?", [$i, (int)$r['id']]);
            $i += 10;
        }
    }

    private static function swapLinkSort($id, $dir)
    {
        $cur = self::getLink($id);
        if (!$cur) return false;
        $op = $dir === 'up' ? '<' : '>';
        $order = $dir === 'up' ? 'DESC' : 'ASC';
        $nb = dbOne("SELECT id, sort FROM " . self::TBL_LINKS . " WHERE group_id=? AND sort $op ? ORDER BY sort $order, id $order LIMIT 1",
            [(int)$cur['group_id'], (int)$cur['sort']]);
        if (!$nb) return 'none';
        dbQuery("UPDATE " . self::TBL_LINKS . " SET sort=? WHERE id=?", [(int)$nb['sort'], (int)$cur['id']]);
        dbQuery("UPDATE " . self::TBL_LINKS . " SET sort=? WHERE id=?", [(int)$cur['sort'], (int)$nb['id']]);
        return true;
    }

    private static function swapGroupSort($id, $dir)
    {
        $cur = self::getGroup($id);
        if (!$cur) return false;
        $op = $dir === 'up' ? '<' : '>';
        $order = $dir === 'up' ? 'DESC' : 'ASC';
        $nb = dbOne("SELECT id, sort FROM " . self::TBL_GROUPS . " WHERE sort $op ? ORDER BY sort $order, id $order LIMIT 1",
            [(int)$cur['sort']]);
        if (!$nb) return 'none';
        dbQuery("UPDATE " . self::TBL_GROUPS . " SET sort=? WHERE id=?", [(int)$nb['sort'], (int)$cur['id']]);
        dbQuery("UPDATE " . self::TBL_GROUPS . " SET sort=? WHERE id=?", [(int)$cur['sort'], (int)$nb['id']]);
        return true;
    }

    // ================================================================
    //  前端钩子
    // ================================================================

    /** 顶栏导航入口：仅在开启「导航」时渲染 */
    public static function nav_top($arg = null)
    {
        if (!self::getSetting(self::OPT_ENABLE_NAV, 1)) return '';
        $slug = self::getSetting(self::OPT_CHANNEL_SLUG, 'nav');
        $title = self::getSetting(self::OPT_CHANNEL_TITLE, '导航');
        $titleEn = self::getSetting(self::OPT_CHANNEL_TITLE_EN, '');
        if (currentLang() === 'en' && $titleEn !== '') $title = $titleEn;
        $url = pageUrl(['slug' => $slug]);
        return '<a href="' . esc($url) . '">' . esc($title) . '</a>';
    }

    /** 首页底部友情链接区域 */
    public static function home_after($arg = null)
    {
        return self::renderFriendSection();
    }

    /** 独立频道页：接管渲染（返回非空则替换 page.php 默认内容） */
    public static function page_replace($page)
    {
        if (!is_array($page)) return '';
        $channelSlug = self::getSetting(self::OPT_CHANNEL_SLUG, 'nav');
        $pageSlug = $page['slug'] ?? '';
        if ($pageSlug !== $channelSlug) return '';
        if (!self::getSetting(self::OPT_ENABLE_NAV, 1)) return '';
        $GLOBALS['__rye_body_class'] = 'rye-nav-channel';
        return self::renderChannelPage($page);
    }

    // ================================================================
    //  前端渲染
    // ================================================================

    private static function renderChannelPage($page)
    {
        $enableNav = (int)self::getSetting(self::OPT_ENABLE_NAV, 1);
        $enableFriend = (int)self::getSetting(self::OPT_ENABLE_FRIEND, 1);
        $channelFriend = (int)self::getSetting(self::OPT_CHANNEL_FRIEND, 1);
        $cols = (int)self::getSetting(self::OPT_CARD_COLS, 4);
        if ($cols < 2) $cols = 2; if ($cols > 6) $cols = 6;

        $navGroups = $enableNav ? self::getGroups('nav') : [];
        $friendLinks = ($enableFriend && $channelFriend) ? self::getLinksByType('friend') : [];

        $hasRegion = (int)(dbOne("SELECT COUNT(*) c FROM " . self::TBL_LINKS . " WHERE status='active' AND region IN ('domestic','foreign')")['c'] ?? 0) > 0;

        $css = self::CSS_BASE . self::CSS_CHANNEL;
        $html = '<style>' . $css . '</style>';
        $html .= '<div class="nl-channel-wrap">';
        $html .= '<div class="nl-channel">';

        // 侧栏
        $html .= '<aside class="nl-channel-sidebar">';
        $html .= '<input type="search" class="nl-channel-search" placeholder="' . __('搜索网址…') . '" aria-label="' . __('搜索') . '">';
        $html .= '<button type="button" class="nl-channel-sideitem is-active" data-target="__top__"><span class="nl-channel-sideicon">🏠</span><span>' . __('全部') . '</span></button>';
        foreach ($navGroups as $g) {
            $icon = $g['icon'] !== '' ? $g['icon'] : '📂';
            $html .= '<button type="button" class="nl-channel-sideitem" data-target="nl-g-' . (int)$g['id'] . '"><span class="nl-channel-sideicon">' . esc($icon) . '</span><span>' . esc($g['name']) . '</span></button>';
        }
        if ($enableFriend && $channelFriend) {
            $html .= '<button type="button" class="nl-channel-sideitem" data-target="nl-g-friend"><span class="nl-channel-sideicon">🤝</span><span>' . __('友情链接') . '</span></button>';
        }
        $html .= '</aside>';

        // 主体
        $html .= '<div class="nl-channel-main">';
        if ($hasRegion) {
            $html .= '<div class="nl-channel-tabs">';
            $html .= '<button type="button" class="nl-channel-tab is-active" data-region="all">' . __('全部') . '</button>';
            $html .= '<button type="button" class="nl-channel-tab" data-region="domestic">' . __('国内') . '</button>';
            $html .= '<button type="button" class="nl-channel-tab" data-region="foreign">' . __('国外') . '</button>';
            $html .= '</div>';
        }
        $anyNav = false;
        foreach ($navGroups as $g) {
            $links = self::getLinksInGroup($g['id']);
            if (!$links) continue;
            $anyNav = true;
            $html .= '<section class="nl-channel-section" id="nl-g-' . (int)$g['id'] . '">';
            $html .= '<h2 class="nl-channel-section-title">' . esc(L($g, 'name')) . '</h2>';
            $html .= '<div class="nl-channel-grid" style="--nl-cols:' . $cols . '">';
            foreach ($links as $l) $html .= self::renderLinkCard($l);
            $html .= '</div>';
            $html .= '<div class="nl-channel-empty" style="display:none">' . __('本分组暂无匹配结果') . '</div>';
            $html .= '</section>';
        }
        if ($enableFriend && $friendLinks) {
            $html .= '<section class="nl-channel-section is-friend" id="nl-g-friend">';
            $html .= '<h2 class="nl-channel-section-title">' . __('友情链接') . '</h2>';
            $html .= '<div class="nl-channel-grid" style="--nl-cols:' . $cols . '">';
            foreach ($friendLinks as $l) $html .= self::renderLinkCard($l);
            $html .= '</div>';
            $html .= '<div class="nl-channel-empty" style="display:none">' . __('暂无匹配结果') . '</div>';
            $html .= '</section>';
        }
        if (!$anyNav && !$friendLinks) {
            $adminUrl = baseUrl('admin/plugin-config.php?dir=nav-links&tab=links');
            $html .= '<div class="nl-empty">' . __('还没有任何链接，') . '<a href="' . esc($adminUrl) . '">' . __('去添加') . '</a>。</div>';
        }
        $html .= '</div>'; // main
        $html .= '</div>'; // channel
        $html .= '</div>'; // wrap
        $html .= '<script>' . self::JS_CHANNEL . '</script>';
        return $html;
    }

    private static function renderFriendSection()
    {
        if (!self::getSetting(self::OPT_ENABLE_FRIEND, 1)) return '';
        $groups = self::getGroups('friend');
        if (!$groups) return '';
        $cols = (int)self::getSetting(self::OPT_CARD_COLS, 4);
        if ($cols < 2) $cols = 2; if ($cols > 6) $cols = 6;
        $slug = self::getSetting(self::OPT_CHANNEL_SLUG, 'nav');
        $channelUrl = pageUrl(['slug' => $slug]);

        $css = self::CSS_BASE . self::CSS_FRIEND;
        $html = '<style>' . $css . '</style>';
        $html .= '<section class="nl-friend">';
        $html .= '<div class="nl-friend-head"><h3 class="nl-friend-title">' . __('友情链接') . '</h3><a class="nl-friend-more" href="' . esc($channelUrl) . '">' . __('更多 →') . '</a></div>';

        $hasAny = false;
        foreach ($groups as $g) {
            $links = self::getLinksInGroup($g['id']);
            if (!$links) continue;
            $hasAny = true;
            $html .= '<div class="nl-friend-group">';
            $html .= '<h4 class="nl-friend-group-title">' . esc(L($g, 'name')) . '</h4>';
            $html .= '<div class="nl-friend-grid" style="--nl-cols:' . $cols . '">';
            foreach ($links as $l) $html .= self::renderLinkCard($l);
            $html .= '</div></div>';
        }
        if (!$hasAny) return '';
        $html .= '</section>';
        return $html;
    }

    private static function renderLinkCard($link)
    {
        $url = esc((string)$link['url']);
        $title = esc(L($link, 'title'));
        $desc = esc(L($link, 'description'));
        $logo = trim((string)($link['logo'] ?? ''));
        $region = (string)($link['region'] ?? '');
        $target = ($link['target'] ?? '_blank') === '_self' ? '_self' : '_blank';
        $searchAttr = esc(mb_strtolower(L($link, 'title') . ' ' . L($link, 'description'), 'utf-8'));
        $logoHtml = $logo !== ''
            ? '<img src="' . esc($logo) . '" alt="" loading="lazy" referrerpolicy="no-referrer">'
            : '<span>' . esc(mb_substr(L($link, 'title'), 0, 1, 'utf-8')) . '</span>';
        $rel = $target === '_blank' ? ' rel="noopener"' : '';
        return '<a class="nl-card" href="' . $url . '" target="' . $target . '"' . $rel
            . ' data-region="' . esc($region) . '" data-search="' . $searchAttr . '">'
            . '<div class="nl-card-logo">' . $logoHtml . '</div>'
            . '<div class="nl-card-body">'
            . '<div class="nl-card-title">' . $title . '</div>'
            . '<div class="nl-card-desc">' . $desc . '</div>'
            . '</div></a>';
    }
}
