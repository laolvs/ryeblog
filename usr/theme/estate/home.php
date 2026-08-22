<?php
/**
 * RyeBlog 企业站主题 —— 建筑地产 Estate 首页
 * 结构：Banner → 核心业务 → 精选项目 → 服务流程 → 最新动态 → 预约 CTA
 * 数据：后台「企业站主题」设置（biz_estate_*）；项目/动态用分类文章
 */
$GLOBALS['__biz_theme'] = 'estate';
require_once __DIR__ . '/_biz/bootstrap.php';

// 主题数据（后台可配）
$heroTitle   = bizOpt('hero_title', '筑造<em>理想空间</em>，成就美好生活');
$heroSub     = bizOpt('hero_sub', '深耕地产开发 20 年，住宅、商业、产业园区全业态布局，用心筑造每一座城市地标。');
$heroBtnText = bizOpt('hero_btn_text', '查看精选项目');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 企业硬实力数据
$stats = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("stat_{$i}_title", ''); $v = bizOpt("stat_{$i}_value", '');
    if ($t !== '' && $v !== '') $stats[] = ['title' => $t, 'value' => $v];
}
if (empty($stats)) {
    $stats = [
        ['title' => '在售项目',   'value' => '36+'],
        ['title' => '累计交付',   'value' => '1200万㎡'],
        ['title' => '覆盖城市',   'value' => '28'],
        ['title' => '业主满意度', 'value' => '98%'],
    ];
}

// 核心业务（biz_estate_biz_N_title / _desc / _icon）
$bizs = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("biz_{$i}_title", ''); if ($t === '') continue;
    $bizs[] = [
        'icon' => bizOpt("biz_{$i}_icon", '🏗'),
        'title' => $t,
        'desc'  => bizOpt("biz_{$i}_desc", ''),
    ];
}
if (empty($bizs)) {
    $bizs = [
        ['icon' => '🏠', 'title' => '住宅开发', 'desc' => '高端住宅、刚需大盘，全生命周期品质管控'],
        ['icon' => '🏢', 'title' => '商业地产', 'desc' => '城市综合体、写字楼，打造活力商业地标'],
        ['icon' => '🏭', 'title' => '产业园区', 'desc' => '标准厂房、科创园区，助力产业升级'],
        ['icon' => '🔑', 'title' => '物业服务', 'desc' => '自有物业团队，24 小时贴心守护'],
        ['icon' => '🏗', 'title' => '建筑工程', 'desc' => '特级施工资质，安全文明标准化管理'],
        ['icon' => '📐', 'title' => '景观设计', 'desc' => '国际团队，东方美学与现代设计融合'],
    ];
}

// 精选项目：取有文章的分类（前 6 个），带封面大图网格
$cats = array_values(array_filter(getCategories(), function ($c) { return $c['post_count'] > 0; }));
$projectCats = array_slice($cats, 0, 6);
$projects = [];
foreach ($projectCats as $c) {
    $first = getPosts(['category' => $c['id'], 'perPage' => 1])['items'][0] ?? null;
    if (!$first) continue;
    $projects[] = [
        'cat'  => $c,
        'title' => L($first, 'title'),
        'cover' => $first['cover_image'] ?? '',
        'url'   => postUrl($first),
        'sub'   => L($c, 'name'),
    ];
}
if (empty($projects)) {
    foreach (getPosts(['perPage' => 4])['items'] ?? [] as $p) {
        $projects[] = [
            'cat'  => ['slug' => ''],
            'title' => L($p, 'title'),
            'cover' => $p['cover_image'] ?? '',
            'url'   => postUrl($p),
            'sub'   => '',
        ];
    }
}

// 服务流程（biz_estate_step_N_title / _desc）
$steps = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("step_{$i}_title", ''); if ($t === '') continue;
    $steps[] = ['title' => $t, 'desc' => bizOpt("step_{$i}_desc", '')];
}
if (empty($steps)) {
    $steps = [
        ['title' => '土地研判', 'desc' => '专业团队城市调研，精准区位价值判断'],
        ['title' => '规划设计', 'desc' => '国际大师操刀，产品与景观一体化设计'],
        ['title' => '匠心建造', 'desc' => '精工细作，全周期质量巡检与第三方评估'],
        ['title' => '交付服务', 'desc' => '透明交付、暖心入住，物业服务终身陪伴'],
    ];
}

// 最新动态
$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => '房产,地产,建筑,楼盘'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <div class="biz-hero-text">
            <span class="biz-hero-badge"><?php echo esc(bizOpt('hero_badge', '匠心筑家 20 年')); ?></span>
            <h1><?php echo $heroTitle; // 允许 <em> 高亮 ?></h1>
            <p><?php echo esc($heroSub); ?></p>
            <div class="biz-hero-actions">
                <?php if ($heroBtnUrl !== ''): ?>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc($heroBtnUrl); ?>"><?php echo esc($heroBtnText); ?></a>
                <?php endif; ?>
                <a class="biz-btn biz-btn-ghost" href="<?php echo esc(bizOpt('contact_url', '')); ?>">预约看房</a>
            </div>
        </div>
        <div class="biz-hero-stats">
            <?php foreach ($stats as $s): ?>
            <div class="biz-stat-card"><b><?php echo esc($s['value']); ?></b><span><?php echo esc($s['title']); ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 核心业务 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Core Business</p>
            <h2>核心业务</h2>
            <p>全业态布局，一站式城市开发运营</p>
        </div>
        <div class="biz-features">
            <?php foreach ($bizs as $b): ?>
            <div class="biz-feature">
                <div class="biz-feature-icon"><?php echo esc($b['icon']); ?></div>
                <h3><?php echo esc($b['title']); ?></h3>
                <p><?php echo esc($b['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 精选项目 -->
<?php if ($projects): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Featured Projects</p>
            <h2>精选项目</h2>
            <p>每一座作品，都是城市的风景</p>
        </div>
        <div class="biz-projects">
            <?php foreach ($projects as $p): ?>
            <a class="biz-project" href="<?php echo esc($p['url']); ?>">
                <?php if ($p['cover'] !== ''): ?>
                <img src="<?php echo esc($p['cover']); ?>" alt="<?php echo esc($p['title']); ?>" loading="lazy">
                <?php endif; ?>
                <div class="biz-project-cap"><b><?php echo esc($p['title']); ?></b><span><?php echo esc($p['sub']); ?></span></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 服务流程 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Process</p>
            <h2>开发服务流程</h2>
            <p>从拿地到交付，每一步都专业透明</p>
        </div>
        <div class="biz-steps">
            <?php foreach ($steps as $i => $st): ?>
            <div class="biz-step">
                <span class="biz-step-n"><?php echo str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                <h3><?php echo esc($st['title']); ?></h3>
                <p><?php echo esc($st['desc']); ?></p>
            </div>
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

<!-- 预约 CTA -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-cta">
            <div>
                <h2>心动的楼盘？预约看房免费接送</h2>
                <p><?php echo esc(bizOpt('phone', '')); ?> ｜ <?php echo esc(bizOpt('email', '')); ?></p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">立即预约</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
