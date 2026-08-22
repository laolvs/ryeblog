<?php
/**
 * RyeBlog 企业站主题 —— 文旅酒店 Travel 首页
 * 结构：Banner → 热门目的地 → 精选线路 → 酒店住宿 → 游玩攻略 → 咨询 CTA
 * 数据：后台「企业站主题」设置（biz_travel_*）；线路/攻略用分类文章
 */
$GLOBALS['__biz_theme'] = 'travel';
require_once __DIR__ . '/_biz/bootstrap.php';

// 主题数据（后台可配）
$heroTitle   = bizOpt('hero_title', '遇见<em>诗与远方</em>，开启难忘旅程');
$heroSub     = bizOpt('hero_sub', '深耕文旅行业 15 年，覆盖 200+ 目的地，专业定制每一次出发，让旅行成为美好记忆。');
$heroBtnText = bizOpt('hero_btn_text', '查看精选线路');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 文旅硬实力数据
$stats = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("stat_{$i}_title", ''); $v = bizOpt("stat_{$i}_value", '');
    if ($t !== '' && $v !== '') $stats[] = ['title' => $t, 'value' => $v];
}
if (empty($stats)) {
    $stats = [
        ['title' => '年服务游客', 'value' => '50万+'],
        ['title' => '精选线路',   'value' => '200+'],
        ['title' => '合作酒店',   'value' => '800+'],
        ['title' => '好评率',     'value' => '99%'],
    ];
}

// 热门目的地（biz_travel_dest_N_name / _desc / _icon）
$dests = [];
for ($i = 1; $i <= 6; $i++) {
    $n = bizOpt("dest_{$i}_name", ''); if ($n === '') continue;
    $dests[] = ['name' => $n, 'desc' => bizOpt("dest_{$i}_desc", ''), 'icon' => bizOpt("dest_{$i}_icon", '🏞')];
}
if (empty($dests)) {
    $dests = [
        ['name' => '云南 · 大理', 'desc' => '苍山洱海，风花雪月', 'icon' => '🏔'],
        ['name' => '海南 · 三亚', 'desc' => '椰风海韵，度假天堂', 'icon' => '🏖'],
        ['name' => '四川 · 九寨沟', 'desc' => '人间仙境，水景之王', 'icon' => '💧'],
        ['name' => '甘肃 · 敦煌', 'desc' => '大漠飞天，丝路明珠', 'icon' => '🏜'],
        ['name' => '广西 · 桂林', 'desc' => '山水甲天下，漓江精华', 'icon' => '⛰'],
        ['name' => '西藏 · 拉萨', 'desc' => '雪域圣城，心灵之旅', 'icon' => '🛕'],
    ];
}

// 精选线路：取有文章的分类（前 4 个），带封面
$cats = array_values(array_filter(getCategories(), function ($c) { return $c['post_count'] > 0; }));
$routeCats = array_slice($cats, 0, 4);
$routes = [];
foreach ($routeCats as $c) {
    $first = getPosts(['category' => $c['id'], 'perPage' => 1])['items'][0] ?? null;
    if (!$first) continue;
    $routes[] = [
        'title' => L($first, 'title'),
        'cover' => $first['cover_image'] ?? '',
        'url'   => postUrl($first),
        'days'  => bizOpt("route_days_{$c['id']}", L($c, 'name')),
    ];
}
if (empty($routes)) {
    foreach (getPosts(['perPage' => 4])['items'] ?? [] as $p) {
        $routes[] = ['title' => L($p, 'title'), 'cover' => $p['cover_image'] ?? '', 'url' => postUrl($p), 'days' => ''];
    }
}

// 酒店住宿（biz_travel_hotel_N_name / _desc / _img）
$hotels = [];
for ($i = 1; $i <= 3; $i++) {
    $n = bizOpt("hotel_{$i}_name", ''); if ($n === '') continue;
    $hotels[] = ['name' => $n, 'desc' => bizOpt("hotel_{$i}_desc", ''), 'img' => bizOpt("hotel_{$i}_img", '')];
}
if (empty($hotels)) {
    $hotels = [
        ['name' => '湖畔度假酒店', 'desc' => '湖景房 · 亲子主题 · 无边泳池', 'img' => ''],
        ['name' => '山间精品民宿', 'desc' => '星空露台 · 温泉泡池 · 私厨餐饮', 'img' => ''],
        ['name' => '城市商务酒店', 'desc' => '中心地段 · 会议宴会 · 行政酒廊', 'img' => ''],
    ];
}

// 游玩攻略
$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => '旅游,旅行,线路,酒店,攻略'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <div class="biz-hero-text">
            <span class="biz-hero-badge"><?php echo esc(bizOpt('hero_badge', '私人定制 · 纯玩无购物')); ?></span>
            <h1><?php echo $heroTitle; // 允许 <em> 高亮 ?></h1>
            <p><?php echo esc($heroSub); ?></p>
            <div class="biz-hero-actions">
                <?php if ($heroBtnUrl !== ''): ?>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc($heroBtnUrl); ?>"><?php echo esc($heroBtnText); ?></a>
                <?php endif; ?>
                <a class="biz-btn biz-btn-ghost" href="<?php echo esc(bizOpt('contact_url', '')); ?>">免费定制行程</a>
            </div>
        </div>
        <div class="biz-hero-stats">
            <?php foreach ($stats as $s): ?>
            <div class="biz-stat-card"><b><?php echo esc($s['value']); ?></b><span><?php echo esc($s['title']); ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 热门目的地 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Destinations</p>
            <h2>热门目的地</h2>
            <p>总有一个远方，值得出发</p>
        </div>
        <div class="biz-features">
            <?php foreach ($dests as $d): ?>
            <div class="biz-feature">
                <div class="biz-feature-icon"><?php echo esc($d['icon']); ?></div>
                <h3><?php echo esc($d['name']); ?></h3>
                <p><?php echo esc($d['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 精选线路 -->
<?php if ($routes): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Routes</p>
            <h2>精选线路</h2>
            <p>金牌导游 · 高性价比 · 纯玩零购物</p>
        </div>
        <div class="biz-products">
            <?php foreach ($routes as $r): ?>
            <a class="biz-product" href="<?php echo esc($r['url']); ?>">
                <?php if ($r['cover'] !== ''): ?>
                <img class="biz-product-img" src="<?php echo esc($r['cover']); ?>" alt="<?php echo esc($r['title']); ?>" loading="lazy">
                <?php endif; ?>
                <div class="biz-product-body">
                    <h3><?php echo esc($r['title']); ?></h3>
                    <?php if ($r['days'] !== ''): ?><p><?php echo esc($r['days']); ?></p><?php endif; ?>
                    <span class="biz-product-more">查看详情 →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 酒店住宿 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Hotels</p>
            <h2>酒店住宿</h2>
            <p>严选好宿，安心好眠</p>
        </div>
        <div class="biz-products">
            <?php foreach ($hotels as $h): ?>
            <div class="biz-product">
                <?php if ($h['img'] !== ''): ?>
                <img class="biz-product-img" src="<?php echo esc($h['img']); ?>" alt="<?php echo esc($h['name']); ?>" loading="lazy">
                <?php endif; ?>
                <div class="biz-product-body">
                    <h3><?php echo esc($h['name']); ?></h3>
                    <p><?php echo esc($h['desc']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 游玩攻略 -->
<?php if ($news): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Travel Guide</p>
            <h2>游玩攻略</h2>
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

<!-- 咨询 CTA -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-cta">
            <div>
                <h2>想去哪？免费定制专属行程</h2>
                <p><?php echo esc(bizOpt('phone', '')); ?> ｜ <?php echo esc(bizOpt('email', '')); ?></p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">立即咨询</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
