<?php
/**
 * RyeBlog 官方主题 —— 共享页脚
 * 输出站点页脚 + 后台「统计代码」（百度统计等，放页脚）+ footer 钩子。
 * 模板已设置 $siteTitle；缺省自动取。
 */
$siteTitle = $siteTitle ?? siteTitle();
?>
<footer class="rye-footer">
    <div class="rye-footer-inner">
        <p class="rye-footer-copy">© <?php echo date('Y'); ?> <a href="<?php echo homeUrl(); ?>"><?php echo esc($siteTitle); ?></a> · Powered by <a href="https://ryeblog.com/" target="_blank" rel="noopener">RyeBlog</a> 🌱</p>
        <div class="rye-footer-links">
            <a href="<?php echo feedUrl(); ?>">RSS</a>
            <?php foreach (getMenus('footer') as $m): ?>
            <a href="<?php echo esc($m['resolved_url']); ?>"><?php echo esc(L($m, 'title')); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</footer>
<?php echo footerStats(); ?>
<?php echo doHook('footer'); ?>
</body>
</html>
