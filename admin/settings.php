<?php
/**
 * RyeBlog 后台 —— 站点设置首页（卡片导航到各分组）
 */
require_once __DIR__ . '/admin.php';

$groups = [
    ['file' => 'settings-brand.php',    'icon' => '🎨', 'title' => __('品牌与主页'), 'desc' => __('站点名称/标语/Hero 宣传区/文档主题 Hero/博主卡/侧边栏/占位图')],
    ['file' => 'settings-seo.php',      'icon' => '🔎', 'title' => __('SEO'),          'desc' => __('首页/默认 meta 描述与关键词（中英文）')],
    ['file' => 'settings-reading.php',  'icon' => '📖', 'title' => __('阅读与评论'),  'desc' => __('每页文章数 / 评论审核 / 远程图片自动本地化')],
    ['file' => 'settings-footer.php',   'icon' => '📋', 'title' => __('Footer 与备案'),'desc' => __('版权 / 程序支持 / 备案号 / 统计代码')],
    ['file' => 'settings-advanced.php', 'icon' => '⚙️', 'title' => __('高级设置'),    'desc' => __('维护模式 / 伪静态 / 内置主题配色 / 云端市场 / 设置备份导出导入')],
    ['file' => 'settings-license.php',  'icon' => '📦', 'title' => __('开源与协议'),  'desc' => __('RyeBlog 开源标识 / 自定义 footer 署名 / 协议 / 官方资源链接')],
];

adminHead(__('站点设置'), 'settings.php');
?>
<h1>⚙️ <?php echo __('站点设置'); ?></h1>
<p class="muted" style="margin:0 0 18px"><?php echo __('按主题分组管理站点选项，点击下方卡片进入对应设置页。'); ?></p>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
    <?php foreach ($groups as $g): ?>
        <a href="<?php echo baseUrl('admin/' . $g['file']); ?>"
           style="display:block;padding:18px;background:#fff;border:1px solid var(--line);border-radius:12px;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s"
           ononmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(46,107,53,.12)'"
           ononmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <span style="font-size:28px"><?php echo $g['icon']; ?></span>
                <strong style="font-size:16px;color:var(--g-700)"><?php echo $g['title']; ?></strong>
            </div>
            <div class="muted" style="font-size:13px;line-height:1.6"><?php echo $g['desc']; ?></div>
            <div style="margin-top:10px;color:var(--accent);font-size:13px">前往设置 →</div>
        </a>
    <?php endforeach; ?>
</div>
<?php adminFoot();