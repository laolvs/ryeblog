<?php
/**
 * RyeBlog 示例插件 —— 文末版权声明
 *
 * 这是一个完整的插件示例，演示如何使用 RyeBlog 插件钩子机制。
 * 安装方式：将本目录放入 usr/plugins/ 下，在后台「插件管理」中启用。
 * 配置方式：启用后点击「配置」按钮，可自定义版权声明的标题、内容、样式等。
 *
 * @Title    文末版权
 * @Desc     在每篇文章末尾自动添加版权声明和转载提示，支持后台可视化编辑
 * @Version  1.1.0
 * @Author   RyeBlog Team
 */

// 插件类名规则：Plugin_ + 目录名（特殊字符替换为下划线）
class Plugin_post_copyright
{
    /**
     * 默认配置
     */
    private static function defaults()
    {
        return [
            'show'         => '1',
            'title'        => '版权声明',
            'body'         => '本文《{{title}}》由本站原创发布。',
            'reprint'      => '欢迎分享，转载请注明来源：{{site}}',
            'show_url'     => '1',
            'url_label'    => '本文链接',
            'bg_color'     => '#f0f7f1',
            'border_color' => '#43a047',
            'text_color'   => '#333333',
        ];
    }

    /**
     * 读取配置项（带默认值）；输出时经 __() 翻译（en 态命中 lang/en.php 词典，未译回退原文）
     */
    private static function cfgT($key)
    {
        return __((string)self::cfg($key));
    }

    /**
     * 读取配置项（带默认值）
     */
    private static function cfg($key)
    {
        $defaults = self::defaults();
        return getOption('post_copyright_' . $key, $defaults[$key] ?? '');
    }

    /**
     * 文章内容渲染后钩子
     * 在文章 HTML 输出后追加版权声明
     *
     * @param array $post 文章数据（title, slug, id 等）
     * @return string 返回要追加的 HTML
     */
    public static function articleFooter($post)
    {
        if (!$post) return '';
        if (self::cfg('show') !== '1') return '';

        $title = htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $url   = self::postUrl($post);
        $site  = htmlspecialchars(siteTitle(), ENT_QUOTES, 'UTF-8');

        // 替换占位符（内容经 __() 翻译，en 态命中词典）
        $body    = strtr(self::cfgT('body'),    ['{{title}}' => $title, '{{site}}' => $site, '{{url}}' => $url]);
        $reprint = strtr(self::cfgT('reprint'), ['{{title}}' => $title, '{{site}}' => $site, '{{url}}' => $url]);

        $bg     = htmlspecialchars(self::cfg('bg_color'),     ENT_QUOTES, 'UTF-8');
        $border = htmlspecialchars(self::cfg('border_color'), ENT_QUOTES, 'UTF-8');
        $text   = htmlspecialchars(self::cfg('text_color'),    ENT_QUOTES, 'UTF-8');

        $titleText = htmlspecialchars(self::cfgT('title'), ENT_QUOTES, 'UTF-8');

        $html = '<div style="margin-top:30px;padding:16px 20px;background:' . $bg . ';border-left:4px solid ' . $border . ';border-radius:0 10px 10px 0;font-size:.9rem;color:' . $text . '">' . "\n";
        $html .= '<p style="margin:0 0 6px"><strong>' . $titleText . '</strong></p>' . "\n";
        $html .= '<p style="margin:0 0 6px">' . $body . '</p>' . "\n";
        if ($reprint !== '') {
            $html .= '<p style="margin:0 0 6px">' . $reprint . '</p>' . "\n";
        }
        if (self::cfg('show_url') === '1' && $url !== '') {
            $label = htmlspecialchars(self::cfgT('url_label'), ENT_QUOTES, 'UTF-8');
            $html .= '<p style="margin:0"><small>' . $label . '：<a href="' . $url . '" style="color:' . $border . '">' . $url . '</a></small></p>' . "\n";
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * 获取文章 URL（兼容伪静态开关）
     */
    private static function postUrl($post)
    {
        if (function_exists('postUrl')) {
            return postUrl($post);
        }
        $slug = $post['slug'] ?? $post['id'] ?? '';
        return ($slug !== '') ? '/post/' . urlencode($slug) : '';
    }

    /**
     * 页头钩子 —— 在 <head> 标签内输出内容
     */
    public static function header()
    {
        return '<meta name="generator" content="RyeBlog with Copyright Plugin">' . "\n";
    }

    /**
     * 页脚钩子 —— 在 </body> 前输出内容
     */
    public static function footer()
    {
        return '<!-- RyeBlog Copyright Plugin: active -->' . "\n";
    }

    /**
     * 侧边栏顶部钩子
     */
    public static function sidebarTop()
    {
        return '';
    }

    // ---------- 配置页面接口 ----------

    /**
     * 返回配置页面 HTML
     * 后台 plugin-config.php 会调用此方法，外层已包裹面板和 CSRF 令牌。
     */
    public static function config()
    {
        // 预计算所有值，避免 heredoc 中使用表达式
        $show        = self::cfg('show');
        $title       = self::cfg('title');
        $body        = self::cfg('body');
        $reprint     = self::cfg('reprint');
        $showUrl     = self::cfg('show_url');
        $urlLabel    = self::cfg('url_label');
        $bgColor     = self::cfg('bg_color');
        $borderColor = self::cfg('border_color');
        $textColor   = self::cfg('text_color');

        $showSel1     = $show === '1' ? 'selected' : '';
        $showSel0     = $show === '0' ? 'selected' : '';
        $showUrlSel1  = $showUrl === '1' ? 'selected' : '';
        $showUrlSel0  = $showUrl === '0' ? 'selected' : '';

        $titleEsc    = htmlspecialchars($title,     ENT_QUOTES, 'UTF-8');
        $bodyEsc     = htmlspecialchars($body,     ENT_QUOTES, 'UTF-8');
        $reprintEsc  = htmlspecialchars($reprint,  ENT_QUOTES, 'UTF-8');
        $urlLabelEsc = htmlspecialchars($urlLabel, ENT_QUOTES, 'UTF-8');

        $csrf = csrfToken();

        return <<<HTML
<form method="post">
    <input type="hidden" name="_csrf" value="{$csrf}">

    <label>是否显示版权声明</label>
    <select name="show" style="width:120px">
        <option value="1" {$showSel1}>显示</option>
        <option value="0" {$showSel0}>隐藏</option>
    </select>

    <h4 style="margin:18px 0 8px;color:var(--g-700)">内容设置</h4>
    <label>标题</label>
    <input type="text" name="title" value="{$titleEsc}" style="width:100%" placeholder="如：版权声明">

    <label>声明内容 <small class="muted">（支持占位符：{{title}} {{site}} {{url}}）</small></label>
    <input type="text" name="body" value="{$bodyEsc}" style="width:100%" placeholder="如：本文《{{title}}》由本站原创发布。">

    <label>转载提示 <small class="muted">（留空则不显示此行）</small></label>
    <input type="text" name="reprint" value="{$reprintEsc}" style="width:100%" placeholder="如：转载请注明来源：{{site}}">

    <h4 style="margin:18px 0 8px;color:var(--g-700)">文章链接</h4>
    <label>是否显示文章链接</label>
    <select name="show_url" style="width:120px">
        <option value="1" {$showUrlSel1}>显示</option>
        <option value="0" {$showUrlSel0}>隐藏</option>
    </select>
    <label>链接标签</label>
    <input type="text" name="url_label" value="{$urlLabelEsc}" style="width:100%" placeholder="如：本文链接">

    <h4 style="margin:18px 0 8px;color:var(--g-700)">配色方案</h4>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
        <div>
            <label>背景色</label><br>
            <input type="color" name="bg_color" value="{$bgColor}" style="width:80px;height:36px;border:1px solid var(--line);border-radius:6px;cursor:pointer">
        </div>
        <div>
            <label>边框色</label><br>
            <input type="color" name="border_color" value="{$borderColor}" style="width:80px;height:36px;border:1px solid var(--line);border-radius:6px;cursor:pointer">
        </div>
        <div>
            <label>文字色</label><br>
            <input type="color" name="text_color" value="{$textColor}" style="width:80px;height:36px;border:1px solid var(--line);border-radius:6px;cursor:pointer">
        </div>
    </div>

    <h4 style="margin:18px 0 8px;color:var(--g-700)">实时预览</h4>
    <div id="copyright-preview" style="margin-bottom:16px"></div>

    <p style="margin-top:8px">
        <button class="btn" type="submit">保存配置</button>
        <button class="btn btn-ghost" type="button" onclick="resetCopyrightDefaults()">恢复默认</button>
    </p>
</form>

<script>
(function () {
    var defaults = {
        show: '1', title: '版权声明',
        body: '本文《{{title}}》由本站原创发布。',
        reprint: '欢迎分享，转载请注明来源：{{site}}',
        show_url: '1', url_label: '本文链接',
        bg_color: '#f0f7f1', border_color: '#43a047', text_color: '#333333'
    };
    var form = document.querySelector('form[method=post]');

    function val(name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) return defaults[name] || '';
        if (el.tagName === 'SELECT') return el.value;
        return el.value;
    }

    function updatePreview() {
        var title = val('title') || '版权声明';
        var body = (val('body') || '').replace(/\{\{title\}\}/g, '示例文章标题').replace(/\{\{site\}\}/g, '我的博客').replace(/\{\{url\}\}/g, '/post/example');
        var reprint = (val('reprint') || '').replace(/\{\{title\}\}/g, '示例文章标题').replace(/\{\{site\}\}/g, '我的博客').replace(/\{\{url\}\}/g, '/post/example');
        var bg = val('bg_color'), border = val('border_color'), text = val('text_color');
        var showUrl = val('show_url') === '1';
        var urlLabel = val('url_label') || '本文链接';

        var p = document.getElementById('copyright-preview');
        if (val('show') !== '1') {
            p.innerHTML = '<p class="muted">版权声明已设置为隐藏</p>';
            return;
        }
        var h = '<div style="padding:16px 20px;background:' + bg + ';border-left:4px solid ' + border + ';border-radius:0 10px 10px 0;font-size:.9rem;color:' + text + '">';
        h += '<p style="margin:0 0 6px"><strong>' + title + '</strong></p>';
        h += '<p style="margin:0 0 6px">' + body + '</p>';
        if (reprint) h += '<p style="margin:0 0 6px">' + reprint + '</p>';
        if (showUrl) h += '<p style="margin:0"><small>' + urlLabel + '：<a href="#" style="color:' + border + '">/post/example</a></small></p>';
        h += '</div>';
        p.innerHTML = h;
    }

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);
    updatePreview();

    window.resetCopyrightDefaults = function () {
        Object.keys(defaults).forEach(function (key) {
            var el = form.querySelector('[name="' + key + '"]');
            if (el) {
                if (el.tagName === 'SELECT') el.value = defaults[key];
                else el.value = defaults[key];
            }
        });
        updatePreview();
    };
})();
</script>
HTML;
    }

    /**
     * 保存配置
     * @param array $post $_POST 数据
     * @return bool|string 成功返回 true，失败返回错误信息
     */
    public static function saveConfig($post)
    {
        $keys = ['show', 'title', 'body', 'reprint', 'show_url', 'url_label', 'bg_color', 'border_color', 'text_color'];
        foreach ($keys as $key) {
            if (isset($post[$key])) {
                $val = trim($post[$key]);
                // 颜色值校验
                if (in_array($key, ['bg_color', 'border_color', 'text_color'], true)) {
                    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) {
                        $val = self::defaults()[$key];
                    }
                }
                setOption('post_copyright_' . $key, $val);
            }
        }
        return true;
    }

    /**
     * 插件激活时执行
     * 写入默认配置项
     */
    public static function activate()
    {
        $defaults = self::defaults();
        foreach ($defaults as $key => $val) {
            if (getOption('post_copyright_' . $key, '') === '') {
                setOption('post_copyright_' . $key, $val);
            }
        }
        return true;
    }

    /**
     * 插件停用时执行
     * 配置保留（不删除），方便下次启用恢复
     */
    public static function deactivate()
    {
        return true;
    }
}
