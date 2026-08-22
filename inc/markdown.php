<?php
/**
 * RyeBlog —— 轻量 Markdown 解析器（原创实现）
 * 设计原则：先对全文做 HTML 转义，再注入受控标签，杜绝 XSS。
 * 支持：标题、粗体、斜体、行内/块代码、链接、图片、有序/无序列表、引用、分割线、段落。
 */

function mdInline($s, $refs = [])
{
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // 引用式图片/链接：! [id] / [id] → 用定义表解析（定义在 markdownToHtml 开头统一提取）
    if ($refs) {
        foreach ($refs as $id => $url) {
            $q = preg_quote($id, '/');
            $safeUrl = htmlspecialchars(mdSafeUrl($url), ENT_QUOTES);
            $img = '<img src="' . $safeUrl . '" alt="$1">';
            $a = '<a href="' . $safeUrl . '" rel="nofollow noopener" target="_blank">$1</a>';
            $s = preg_replace('/!\[([^\]]*)\]\[' . $q . '\]/', $img, $s);
            $s = preg_replace('/\[([^\]]+)\]\[' . $q . '\]/', $a, $s);
            $s = str_replace('![' . $id . ']', '<img src="' . $safeUrl . '" alt="">', $s);
            $s = str_replace('[' . $id . ']', '<a href="' . $safeUrl . '" rel="nofollow noopener" target="_blank">' . $id . '</a>', $s);
        }
    }
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
        return '<img src="' . mdSafeUrl($m[2]) . '" alt="' . $m[1] . '">';
    }, $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
        return '<a href="' . mdSafeUrl($m[2]) . '" rel="nofollow noopener" target="_blank">' . $m[1] . '</a>';
    }, $s);
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $s);
    $s = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $s);
    // 自动链接裸 URL（http/https）：跳过已被 [text](url) / ![alt](url) 替换成标签的（引号内 URL）
    // 前缀不能是引号（避免匹配 href=/src= 引号内的地址）；URL 内不含空白/引号/尖括号/括号
    $s = preg_replace_callback(
        '/(^|[\s(（【\[:])https?:\/\/[^\s<>"\')\]）】]+/i',
        function ($m) {
            $body = substr($m[0], strlen($m[1]));          // 前缀后的 URL（含尾随标点）
            $url  = rtrim($body, '.,;:!?，。；：！？、');
            $tail = substr($body, strlen($url));           // 剥离的句末标点
            return $m[1] . '<a href="' . mdSafeUrl($url) . '" rel="nofollow noopener" target="_blank">' . $url . '</a>' . $tail;
        },
        $s
    );
    return $s;
}

/**
 * 链接/图片 URL 安全过滤：仅允许 http/https/mailto 或站内相对路径，
 * 拦截 javascript:、data: 等危险协议（防存储型 XSS）。
 * 入参为 mdInline 中已 htmlspecialchars 转义的 URL。
 */
function mdSafeUrl($url)
{
    $url = trim((string) $url);
    // 有协议头（xxx: 形式）→ 白名单外一律拒绝
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
        if (!preg_match('#^(https?|mailto):#i', $url)) return '#';
    }
    return $url;
}

function mdIsBlock($line)
{
    return (bool)preg_match('/^(#{1,6}\s|>\s|\s*[-*+]\s|\s*\d+\.\s|```)/', $line);
}

function markdownToHtml($md)
{
    $md = str_replace(["\r\n", "\r"], "\n", (string)$md);
    // 预处理：提取引用式定义 [id]: url（独立行），从正文移除
    $refs = [];
    $md = preg_replace_callback('/^\[([^\]]+)\]:\s*(\S+)\s*$/m', function ($m) use (&$refs) {
        $refs[$m[1]] = $m[2];
        return '';
    }, $md);
    $lines = explode("\n", $md);
    $n = count($lines);
    $html = '';
    $i = 0;

    while ($i < $n) {
        $line = $lines[$i];

        // 代码块
        if (preg_match('/^```/', $line)) {
            $buf = [];
            $i++;
            while ($i < $n && !preg_match('/^```/', $lines[$i])) {
                $buf[] = $lines[$i];
                $i++;
            }
            $i++; // 跳过结尾 ```
            $html .= '<pre><code>' . htmlspecialchars(implode("\n", $buf), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</code></pre>';
            continue;
        }

        // 标题
        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $lvl = strlen($m[1]);
            $html .= "<h$lvl>" . mdInline(trim($m[2]), $refs) . "</h$lvl>";
            $i++;
            continue;
        }

        // 分割线
        if (preg_match('/^(-{3,}|\*{3,})$/', trim($line))) {
            $html .= '<hr>';
            $i++;
            continue;
        }

        // 引用
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $buf = [];
            while ($i < $n && preg_match('/^>\s?(.*)$/', $lines[$i], $mm)) {
                $buf[] = $mm[1];
                $i++;
            }
            $html .= '<blockquote>' . mdInline(implode(' ', $buf), $refs) . '</blockquote>';
            continue;
        }

        // 无序列表
        if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m)) {
            $buf = [];
            while ($i < $n && preg_match('/^\s*[-*+]\s+(.*)$/', $lines[$i], $mm)) {
                $buf[] = '<li>' . mdInline($mm[1], $refs) . '</li>';
                $i++;
            }
            $html .= '<ul>' . implode('', $buf) . '</ul>';
            continue;
        }

        // 有序列表
        if (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) {
            $buf = [];
            while ($i < $n && preg_match('/^\s*\d+\.\s+(.*)$/', $lines[$i], $mm)) {
                $buf[] = '<li>' . mdInline($mm[1], $refs) . '</li>';
                $i++;
            }
            $html .= '<ol>' . implode('', $buf) . '</ol>';
            continue;
        }

        // 空行
        if (trim($line) === '') {
            $i++;
            continue;
        }

        // 段落（持续收集到空行或块起始）
        $buf = [];
        while ($i < $n && trim($lines[$i]) !== '' && !mdIsBlock($lines[$i])) {
            $buf[] = $lines[$i];
            $i++;
        }
        // 单换行识别为分行（<br>）：用户书写时一行即一行；可配置关闭（markdown_hard_br=0 则折叠为空格）
        $hardBr = function_exists('getOption') ? getOption('markdown_hard_br', '1') === '1' : true;
        $join = $hardBr ? "<br>\n" : ' ';
        $parts = array_map(function ($ln) use ($refs) { return mdInline($ln, $refs); }, $buf); // 先逐行转义，再拼接 <br>，避免 br 被转义
        $html .= '<p>' . implode($join, $parts) . '</p>';
    }

    return $html;
}
