<?php
/**
 * RyeBlog 企业站主题 —— 科技 SaaS Tech 首页
 * 结构：Hero（深色渐变）→ 产品功能 → 数据指标 → 解决方案 → 客户案例 → 联系 CTA
 */
$GLOBALS['__biz_theme'] = 'tech';
require_once __DIR__ . '/_biz/bootstrap.php';

$heroTitle   = bizOpt('hero_title', '让业务增长，<em>从此简单</em>');
$heroSub     = bizOpt('hero_sub', '我们为成长型企业提供一体化 SaaS 解决方案，打通数据、流程与决策，助你专注核心业务。');
$heroBtnText = bizOpt('hero_btn_text', '免费试用');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 功能（biz_tech_feature_N）
$features = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("feature_{$i}_title", ''); if ($t === '') continue;
    $features[] = ['icon' => bizOpt("feature_{$i}_icon", '⚡'), 'title' => $t, 'desc' => bizOpt("feature_{$i}_desc", '')];
}
if (empty($features)) {
    $features = [
        ['icon' => '⚡', 'title' => '极速部署', 'desc' => '5 分钟完成部署，无需服务器运维经验'],
        ['icon' => '🔒', 'title' => '企业级安全', 'desc' => 'SOC2 认证，数据加密存储，权限精细管控'],
        ['icon' => '📊', 'title' => '智能分析', 'desc' => '内置 BI 报表，实时洞察业务关键指标'],
        ['icon' => '🔌', 'title' => '开放 API', 'desc' => 'RESTful API 与 Webhook，无缝对接现有系统'],
        ['icon' => '🌍', 'title' => '全球加速', 'desc' => '全球 26 个节点，毫秒级响应体验'],
        ['icon' => '🤝', 'title' => '专属服务', 'desc' => '1v1 客户成功经理，7×24 技术支持'],
    ];
}

// 数据指标
$stats = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("stat_{$i}_title", ''); $v = bizOpt("stat_{$i}_value", '');
    if ($t !== '' && $v !== '') $stats[] = ['title' => $t, 'value' => $v];
}
if (empty($stats)) {
    $stats = [
        ['title' => '服务企业', 'value' => '8000+'],
        ['title' => '日均请求', 'value' => '1.2亿'],
        ['title' => '系统可用性', 'value' => '99.99%'],
        ['title' => '客户满意度', 'value' => '98%'],
    ];
}

// 解决方案：有文章的分类
$cats = array_values(array_filter(getCategories(), function ($c) { return $c['post_count'] > 0; }));
$productCats = array_slice($cats, 0, 6);
$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => 'SaaS,软件,解决方案'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <span class="biz-hero-badge">🪄 新一代企业数字化平台</span>
        <h1><?php echo $heroTitle; // 允许 <em> 高亮 ?></h1>
        <p><?php echo esc($heroSub); ?></p>
        <div class="biz-hero-actions">
            <?php if ($heroBtnUrl !== ''): ?>
            <a class="biz-btn biz-btn-primary" href="<?php echo esc($heroBtnUrl); ?>"><?php echo esc($heroBtnText); ?></a>
            <?php endif; ?>
            <a class="biz-btn biz-btn-ghost" href="<?php echo esc(bizOpt('contact_url', '')); ?>">预约演示</a>
        </div>
        <div class="biz-hero-tags">
            <span>✅ 无需代码</span><span>✅ 免费试用 14 天</span><span>✅ 数据可迁移</span>
        </div>
    </div>
</section>

<!-- 产品功能 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Features</p>
            <h2>核心功能</h2>
            <p>一站式解决企业数字化难题</p>
        </div>
        <div class="biz-features">
            <?php foreach ($features as $f): ?>
            <div class="biz-feature">
                <div class="biz-feature-icon"><?php echo esc($f['icon']); ?></div>
                <h3><?php echo esc($f['title']); ?></h3>
                <p><?php echo esc($f['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 数据指标 -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-stats">
            <?php foreach ($stats as $s): ?>
            <div class="biz-stat"><b><?php echo esc($s['value']); ?></b><span><?php echo esc($s['title']); ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 解决方案 -->
<?php if ($productCats): ?>
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Solutions</p>
            <h2>行业解决方案</h2>
            <p>为不同行业量身定制</p>
        </div>
        <div class="biz-products">
            <?php foreach ($productCats as $c): ?>
            <a class="biz-product" href="<?php echo categoryUrl(['slug' => $c['slug']]); ?>">
                <div class="biz-product-body">
                    <h3><?php echo esc(L($c, 'name')); ?></h3>
                    <p><?php echo esc(L($c, 'description')); ?></p>
                    <span class="biz-product-more">了解更多 →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 客户案例 -->
<?php if ($news): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Customers</p>
            <h2>客户案例</h2>
        </div>
        <div class="biz-news">
            <?php foreach ($news as $p): ?>
            <a class="biz-news-item" href="<?php echo postUrl($p); ?>">
                <span class="biz-news-date"><?php echo formatDate($p['created_at'], 'Y-m-d'); ?></span>
                <h3><?php echo esc(L($p, 'title')); ?></h3>
                <?php if (!empty($p['excerpt'])): ?><p><?php echo esc(L($p, 'excerpt')); ?></p><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-cta">
            <div>
                <h2>准备好开始了吗？</h2>
                <p>免费试用 14 天，无需信用卡</p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">立即免费试用</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
