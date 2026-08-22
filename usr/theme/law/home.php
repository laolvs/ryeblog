<?php
/**
 * RyeBlog 企业站主题 —— 法律咨询 Law 首页
 * 结构：Banner → 服务领域 → 律师团队 → 经典案例 → 法律常识 → 免费咨询 CTA
 * 数据：后台「企业站主题」设置（biz_law_*）；案例/常识用分类文章
 */
$GLOBALS['__biz_theme'] = 'law';
require_once __DIR__ . '/_biz/bootstrap.php';

// 主题数据（后台可配）
$heroTitle   = bizOpt('hero_title', '专业<em>法律护航</em>，维护您的合法权益');
$heroSub     = bizOpt('hero_sub', '执业律师 30 年经验沉淀，专注民商事、刑事、公司法律事务，胜诉率行业领先。');
$heroBtnText = bizOpt('hero_btn_text', '免费法律咨询');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 律所硬实力数据
$stats = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("stat_{$i}_title", ''); $v = bizOpt("stat_{$i}_value", '');
    if ($t !== '' && $v !== '') $stats[] = ['title' => $t, 'value' => $v];
}
if (empty($stats)) {
    $stats = [
        ['title' => '胜诉案例',   'value' => '5000+'],
        ['title' => '服务客户',   'value' => '3000+'],
        ['title' => '执业律师',   'value' => '60+'],
        ['title' => '从业年限',   'value' => '30年'],
    ];
}

// 服务领域（biz_law_field_N_title / _desc / _icon）
$fields = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("field_{$i}_title", ''); if ($t === '') continue;
    $fields[] = [
        'icon' => bizOpt("field_{$i}_icon", '⚖️'),
        'title' => $t,
        'desc'  => bizOpt("field_{$i}_desc", ''),
    ];
}
if (empty($fields)) {
    $fields = [
        ['icon' => '📜', 'title' => '合同纠纷', 'desc' => '买卖合同、借款合同、劳动协议争议解决'],
        ['icon' => '👨‍👩‍👧', 'title' => '婚姻家庭', 'desc' => '离婚财产分割、子女抚养、遗产继承'],
        ['icon' => '🚨', 'title' => '刑事辩护', 'desc' => '取保候审、无罪辩护、量刑辩护'],
        ['icon' => '🏢', 'title' => '公司法律', 'desc' => '股权纠纷、企业合规、常年法律顾问'],
        ['icon' => '💼', 'title' => '劳动仲裁', 'desc' => '工伤赔偿、违法辞退、经济补偿金'],
        ['icon' => '💡', 'title' => '知识产权', 'desc' => '商标专利、著作权、商业秘密保护'],
    ];
}

// 律师团队（biz_law_lawyer_N_name / _title / _goodat / _img）
$lawyers = [];
for ($i = 1; $i <= 4; $i++) {
    $n = bizOpt("lawyer_{$i}_name", ''); if ($n === '') continue;
    $lawyers[] = [
        'name'   => $n,
        'title'  => bizOpt("lawyer_{$i}_title", ''),
        'goodat' => bizOpt("lawyer_{$i}_goodat", ''),
        'img'    => bizOpt("lawyer_{$i}_img", ''),
    ];
}
if (empty($lawyers)) {
    $lawyers = [
        ['name' => '赵志远', 'title' => '高级合伙人 · 执业 25 年', 'goodat' => '重大民商事诉讼、公司股权纠纷', 'img' => ''],
        ['name' => '孙丽华', 'title' => '合伙人 · 执业 18 年', 'goodat' => '婚姻家事、遗产继承', 'img' => ''],
        ['name' => '周明轩', 'title' => '刑事部主任 · 执业 20 年', 'goodat' => '经济犯罪辩护、职务犯罪', 'img' => ''],
        ['name' => '吴静怡', 'title' => '合伙人 · 执业 15 年', 'goodat' => '知识产权、商业秘密保护', 'img' => ''],
    ];
}

// 经典案例：优先 cases 分类图文，否则用最新文章
$caseCatId = 0;
foreach (getCategories() as $c) {
    if ($c['slug'] === 'cases') { $caseCatId = (int) $c['id']; break; }
}
$cases = $caseCatId > 0 ? (getPosts(['category' => $caseCatId, 'perPage' => 6])['items'] ?? [])
                        : (getPosts(['perPage' => 6])['items'] ?? []);

// 法律常识
$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => '律师,法律,咨询,诉讼,辩护'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <div class="biz-hero-text">
            <span class="biz-hero-badge"><?php echo esc(bizOpt('hero_badge', '诚信 · 专业 · 高效')); ?></span>
            <h1><?php echo $heroTitle; // 允许 <em> 高亮 ?></h1>
            <p><?php echo esc($heroSub); ?></p>
            <div class="biz-hero-actions">
                <?php if ($heroBtnUrl !== ''): ?>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc($heroBtnUrl); ?>"><?php echo esc($heroBtnText); ?></a>
                <?php endif; ?>
                <a class="biz-btn biz-btn-ghost" href="<?php echo esc(bizOpt('contact_url', '')); ?>">约见律师</a>
            </div>
        </div>
        <div class="biz-hero-stats">
            <?php foreach ($stats as $s): ?>
            <div class="biz-stat-card"><b><?php echo esc($s['value']); ?></b><span><?php echo esc($s['title']); ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 服务领域 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Practice Areas</p>
            <h2>服务领域</h2>
            <p>全领域覆盖，为您精准匹配专业律师</p>
        </div>
        <div class="biz-features">
            <?php foreach ($fields as $f): ?>
            <div class="biz-feature">
                <div class="biz-feature-icon"><?php echo esc($f['icon']); ?></div>
                <h3><?php echo esc($f['title']); ?></h3>
                <p><?php echo esc($f['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 律师团队 -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Our Lawyers</p>
            <h2>律师团队</h2>
            <p>经验丰富，胜诉有保障</p>
        </div>
        <div class="biz-products">
            <?php foreach ($lawyers as $l): ?>
            <div class="biz-product">
                <?php if ($l['img'] !== ''): ?>
                <img class="biz-lawyer-img" src="<?php echo esc($l['img']); ?>" alt="<?php echo esc($l['name']); ?>" loading="lazy">
                <?php endif; ?>
                <div class="biz-product-body">
                    <h3><?php echo esc($l['name']); ?></h3>
                    <p><?php echo esc($l['title']); ?></p>
                    <?php if ($l['goodat'] !== ''): ?><span class="biz-lawyer-tag">专长：<?php echo esc($l['goodat']); ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 经典案例 -->
<?php if ($cases): ?>
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Cases</p>
            <h2>经典案例</h2>
            <p>用胜诉说话</p>
        </div>
        <div class="biz-post-list">
            <?php foreach ($cases as $c): ?>
            <a class="biz-post-item" href="<?php echo postUrl($c); ?>">
                <?php if (!empty($c['cover_image'])): ?>
                <img class="biz-post-cover" src="<?php echo esc($c['cover_image']); ?>" alt="<?php echo esc(L($c, 'title')); ?>" loading="lazy">
                <?php endif; ?>
                <div class="biz-post-info">
                    <h3><?php echo esc(L($c, 'title')); ?></h3>
                    <?php if (!empty($c['excerpt'])): ?><p><?php echo esc(L($c, 'excerpt')); ?></p><?php endif; ?>
                    <span class="biz-post-date"><?php echo formatDate($c['created_at'], 'Y-m-d'); ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 法律常识 -->
<?php if ($news): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Legal Tips</p>
            <h2>法律常识</h2>
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

<!-- 免费咨询 CTA -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-cta">
            <div>
                <h2>遇到法律问题？立即免费咨询</h2>
                <p><?php echo esc(bizOpt('phone', '')); ?> ｜ <?php echo esc(bizOpt('email', '')); ?></p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">免费咨询</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
