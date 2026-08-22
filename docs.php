<?php
/**
 * RyeBlog 图文文档渲染器
 * 把 docs/*.md 渲染为带目录、配图的图文页面（复用 inc/markdown.php）。
 * 用法：docs.php?doc=HELP | LICENSE | PLUGIN_DEV | THEME_DEV | plugins/data-import | plugins/nav-links | plugins/post-copyright
 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/markdown.php';

// 白名单：仅允许这些文档，杜绝路径遍历
$docsWhitelist = [
    'HELP'               => '帮助文档',
    'LICENSE'            => '授权协议',
    'PLUGIN_DEV'         => '插件开发规范',
    'THEME_DEV'          => '主题开发规范',
    'plugins/data-import' => '数据导入 / 导出',
    'plugins/nav-links'   => '导航与友情链接',
    'plugins/post-copyright' => '文末版权',
    'themes/vuecho'       => 'Vuecho 文档主题',
];

$req = $_GET['doc'] ?? 'HELP';
if (!array_key_exists($req, $docsWhitelist)) $req = 'HELP';

$file = __DIR__ . '/docs/' . $req . '.md';
if (!is_file($file)) {
    header('HTTP/1.0 404 Not Found');
    echo '文档不存在：' . esc($req);
    exit;
}

$raw  = file_get_contents($file);
$base = rtrim(baseUrl(''), '/');

// 提取标题（第一个 # 标题）
$title = $docsWhitelist[$req];
if (preg_match('/^#\s+(.+)$/m', $raw, $m)) $title = trim($m[1]);

// 生成目录（h2/h3）+ 给标题加锚点 id
function docSlug($t)
{
    $t = preg_replace('/<[^>]+>/', '', $t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/[*`_#]/', '', $t);
    $t = preg_replace('/[^\p{L}\p{N}\-]+/u', '-', $t);
    return trim($t, '-');
}

$toc = [];
if (preg_match_all('/^(#{2,3})\s+(.+)$/m', $raw, $mm)) {
    foreach ($mm[2] as $k => $txt) {
        $toc[] = [
            'lvl'   => strlen($mm[1][$k]),
            'slug'  => docSlug($txt),
            'text'  => trim($txt),
        ];
    }
}

$body = markdownToHtml($raw);
$body = preg_replace_callback('/<h([23])>(.*?)<\/h\1>/s', function ($x) {
    $slug = docSlug($x[2]);
    return '<h' . $x[1] . ' id="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">' . $x[2] . '</h' . $x[1] . '>';
}, $body);

// 相对图片 / 站内链接补全为带 baseUrl 的绝对路径
$body = preg_replace('/src="docs\/img\//', 'src="' . $base . '/docs/img/', $body);
$body = preg_replace('/href="docs\//', 'href="' . $base . '/docs/', $body);

// 文档导航（其它文档）
$navLinks = '';
foreach ($docsWhitelist as $k => $label) {
    $cls = ($k === $req) ? ' class="active"' : '';
    $navLinks .= '<a href="' . $base . '/docs.php?doc=' . urlencode($k) . '"' . $cls . '>' . esc($label) . '</a>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($title); ?> · RyeBlog 文档</title>
<link rel="icon" href="<?php echo $base; ?>/assets/img/logo-64.png">
<style>
:root{
  --ink:#1f2a22; --muted:#6b7d70; --line:#d7e7d9; --white:#ffffff;
  --g-900:#173a1d; --g-700:#2e6b35; --g-600:#357a3e; --g-500:#43a047;
  --g-200:#a5d6a7; --g-100:#c8e6c9; --g-050:#e8f5e9; --g-025:#f3f9f3;
  --accent:#357a3e;
}
*{box-sizing:border-box}
body{margin:0;background:var(--g-025);color:var(--ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;
  line-height:1.75;font-size:16px}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
.topbar{position:sticky;top:0;z-index:10;background:var(--white);border-bottom:1px solid var(--line);
  display:flex;align-items:center;gap:14px;padding:12px 22px;flex-wrap:wrap}
.brand{font-weight:800;color:var(--g-700);font-size:18px;display:flex;align-items:center;gap:8px}
.brand .dot{width:10px;height:10px;border-radius:50%;background:var(--g-500)}
.topbar .spacer{flex:1}
.topbar .qlinks a{margin-left:14px;color:var(--muted);font-size:14px}
.layout{max-width:1180px;margin:0 auto;display:grid;grid-template-columns:230px 1fr;gap:34px;padding:26px 22px 60px}
.toc{position:sticky;top:70px;align-self:start;max-height:calc(100vh - 90px);overflow:auto;
  background:var(--white);border:1px solid var(--line);border-radius:12px;padding:16px 14px}
.toc h4{margin:0 0 10px;font-size:13px;color:var(--muted);letter-spacing:.05em;text-transform:uppercase}
.toc a{display:block;padding:5px 8px;border-radius:7px;color:var(--ink);font-size:14px}
.toc a:hover{background:var(--g-050);text-decoration:none}
.toc a.l3{padding-left:22px;font-size:13px;color:var(--muted)}
.toc a.active{background:var(--g-100);color:var(--g-900);font-weight:600}
.doc{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:30px 38px 40px;
  box-shadow:0 1px 3px rgba(0,0,0,.04)}
.doc h1{font-size:30px;margin:.2em 0 .5em;color:var(--g-900);border-bottom:3px solid var(--g-200);padding-bottom:.35em}
.doc h2{font-size:22px;margin:1.8em 0 .7em;color:var(--g-800,#1f5f54);scroll-margin-top:80px}
.doc h3{font-size:18px;margin:1.4em 0 .6em;color:var(--g-700);scroll-margin-top:80px}
.doc p{margin:.7em 0}
.doc ul,.doc ol{margin:.6em 0;padding-left:1.5em}
.doc li{margin:.3em 0}
.doc img{max-width:100%;height:auto;border:1px solid var(--line);border-radius:10px;margin:14px 0;
  display:block;background:var(--g-025)}
.doc img+em,.doc figure{display:block}
.doc pre{background:#0f2417;color:#d7f5dd;padding:14px 16px;border-radius:10px;overflow:auto;font-size:13.5px}
.doc code{font-family:"SFMono-Regular",Consolas,Menlo,monospace;background:var(--g-050);
  color:#b5341f;padding:1px 6px;border-radius:5px;font-size:.92em}
.doc pre code{background:none;color:inherit;padding:0}
.doc blockquote{margin:1em 0;padding:10px 16px;border-left:4px solid var(--g-300,#7cc98a);
  background:var(--g-050);color:var(--muted);border-radius:0 8px 8px 0}
.doc hr{border:none;border-top:1px solid var(--line);margin:2em 0}
.doc table{border-collapse:collapse;width:100%;margin:1em 0}
.doc th,.doc td{border:1px solid var(--line);padding:8px 12px;text-align:left}
.doc th{background:var(--g-050)}
.figcap{text-align:center;color:var(--muted);font-size:13px;margin:-6px 0 16px}
.docnav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.docnav a{padding:6px 12px;border:1px solid var(--line);border-radius:999px;font-size:13px;color:var(--muted)}
.docnav a.active{background:var(--g-700);color:#fff;border-color:var(--g-700)}
.docnav a:hover{text-decoration:none;background:var(--g-050)}
@media (max-width:860px){.layout{grid-template-columns:1fr}.toc{position:static;max-height:none}}
</style>
</head>
<body>
<div class="topbar">
  <div class="brand"><span class="dot"></span> RyeBlog 文档</div>
  <div class="spacer"></div>
  <div class="qlinks">
    <a href="<?php echo $base; ?>/">🏠 返回站点</a>
    <a href="<?php echo $base; ?>/admin/">⚙️ 后台</a>
  </div>
</div>

<div class="layout">
  <aside class="toc">
    <h4>本页目录</h4>
    <?php if ($toc): foreach ($toc as $t): ?>
      <a href="#<?php echo htmlspecialchars($t['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="l<?php echo $t['lvl']; ?>"><?php echo esc($t['text']); ?></a>
    <?php endforeach; else: ?>
      <a style="color:var(--muted)">（无子章节）</a>
    <?php endif; ?>
  </aside>

  <main class="doc">
    <div class="docnav"><?php echo $navLinks; ?></div>
    <?php echo $body; ?>
  </main>
</div>
</body>
</html>
