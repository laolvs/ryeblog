<?php
/**
 * RyeBlog 企业站主题 —— 综合制造 Corp 首页
 * 结构：Banner → 企业优势 → 产品与服务 → 荣誉资质 → 最新动态 → 联系 CTA
 * 数据：后台「企业站主题」设置（biz_corp_*）；产品/动态用分类文章
 */
$GLOBALS['__biz_theme'] = 'corp';
require_once __DIR__ . '/_biz/bootstrap.php';

// 主题数据（后台可配）
$heroTitle   = bizOpt('hero_title', '专业 · 可靠的<em>工业解决方案</em>提供商');
$heroSub     = bizOpt('hero_sub', '我们深耕行业 20 年，为制造、能源、工程领域客户提供高品质产品与一站式服务。');
$heroBtnText = bizOpt('hero_btn_text', '了解我们的产品');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 企业硬实力数据（biz_corp_stat_N_title / _value）
$stats = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("stat_{$i}_title", ''); $v = bizOpt("stat_{$i}_value", '');
    if ($t !== '' && $v !== '') $stats[] = ['title' => $t, 'value' => $v];
}
if (empty($stats)) {
    $stats = [
        ['title' => '年行业经验', 'value' => '20+'],
        ['title' => '服务客户',   'value' => '3000+'],
        ['title' => '技术专利',   'value' => '86'],
        ['title' => '覆盖国家',   'value' => '30+'],
    ];
}

// 优势（biz_corp_feature_N_title / _desc / _icon）
$features = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("feature_{$i}_title", ''); if ($t === '') continue;
    $features[] = [
        'icon' => bizOpt("feature_{$i}_icon", '✅'),
        'title' => $t,
        'desc'  => bizOpt("feature_{$i}_desc", ''),
    ];
}
if (empty($features)) {
    $features = [
        ['icon' => '🏭', 'title' => '规模制造', 'desc' => '3 大生产基地，年产能 50 万台，交期有保障'],
        ['icon' => '📋', 'title' => 'ISO 认证', 'desc' => '通过 ISO9001 / ISO14001 质量与环境管理体系认证'],
        ['icon' => '🔧', 'title' => '定制能力', 'desc' => '支持非标定制，快速打样，满足特殊工况需求'],
        ['icon' => '🌐', 'title' => '全球服务', 'desc' => '产品远销 30+ 国家，海外服务网点覆盖主要市场'],
        ['icon' => '⏱', 'title' => '快速响应', 'desc' => '售前 2 小时响应，售后 24 小时到场支持'],
        ['icon' => '🤝', 'title' => '长期合作', 'desc' => '与多家行业头部企业建立十年以上稳定合作关系'],
    ];
}

// 产品与服务：取有文章的分类（前 6 个）
$cats = array_values(array_filter(getCategories(), function ($c) { return $c['post_count'] > 0; }));
$productCats = array_slice($cats, 0, 6);

// 最新动态：取最近 3 篇
$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => '企业,制造,解决方案'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <div class="biz-hero-text">
            <span class="biz-hero-badge"><?php echo esc(bizOpt('hero_badge', '专注于工业解决方案')); ?></span>
            <h1><?php echo $heroTitle; // 允许 <em> 高亮 ?></h1>
            <p><?php echo esc($heroSub); ?></p>
            <div class="biz-hero-actions">
                <?php if ($heroBtnUrl !== ''): ?>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc($heroBtnUrl); ?>"><?php echo esc($heroBtnText); ?></a>
                <?php endif; ?>
                <a class="biz-btn biz-btn-ghost" href="<?php echo esc(bizOpt('contact_url', '')); ?>">联系我们</a>
            </div>
        </div>
        <div class="biz-hero-stats">
            <?php foreach ($stats as $s): ?>
            <div class="biz-stat-card"><b><?php echo esc($s['value']); ?></b><span><?php echo esc($s['title']); ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 企业优势 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Why Choose Us</p>
            <h2>为什么选择我们</h2>
            <p>实力铸就信任，细节决定品质</p>
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

<!-- 产品与服务 -->
<?php if ($productCats): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Products & Services</p>
            <h2>产品与服务</h2>
            <p>覆盖多个行业的产品体系，满足多样化需求</p>
        </div>
        <div class="biz-products">
            <?php foreach ($productCats as $c):
                // 取该分类第一篇已发布文章作为封面图（cover_image 或正文首图）
                $first = getPosts(['category' => $c['id'], 'perPage' => 1])['items'][0] ?? null;
                $cov = $first ? ($first['cover_image'] ?? '') : '';
            ?>
            <a class="biz-product" href="<?php echo categoryUrl(['slug' => $c['slug']]); ?>">
                <?php if ($cov !== ''): ?>
                <img class="biz-product-img" src="<?php echo esc($cov); ?>" alt="<?php echo esc(L($c, 'name')); ?>" loading="lazy">
                <?php endif; ?>
                <div class="biz-product-body">
                    <h3><?php echo esc(L($c, 'name')); ?></h3>
                    <p><?php echo esc(L($c, 'description')); ?></p>
                    <span class="biz-product-more">查看详情 →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 荣誉资质 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Certifications</p>
            <h2>荣誉资质</h2>
        </div>
        <div class="biz-certs">
            <?php $certs = array_filter(array_map('trim', explode(',', bizOpt('certs', 'ISO9001 质量管理体系,ISO14001 环境管理,CE 欧盟认证,高新技术企业,发明专利 86 项,国家专精特新')))); ?>
            <?php foreach ($certs as $i => $c): ?>
            <div class="biz-cert"><?php echo esc($c); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 最新动态 -->
<?php if ($news): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">News</p>
            <h2>最新动态</h2>
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

<!-- 联系 CTA -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-cta">
            <div>
                <h2>有需求？立即联系我们获取方案</h2>
                <p><?php echo esc(bizOpt('phone', '')); ?> ｜ <?php echo esc(bizOpt('email', '')); ?></p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">在线咨询</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
