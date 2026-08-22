<?php
/**
 * RyeBlog 企业站主题 —— 餐饮服务 Food 首页
 * 结构：Hero → 招牌推荐 → 服务项目 → 门店信息 → 顾客评价 → 预约 CTA
 */
$GLOBALS['__biz_theme'] = 'food';
require_once __DIR__ . '/_biz/bootstrap.php';

$heroTitle   = bizOpt('hero_title', '用心做好每一道<em>美味</em>');
$heroSub     = bizOpt('hero_sub', '从选材到出品，坚持新鲜与匠心。欢迎光临，或在线预约到店体验。');
$heroBtnText = bizOpt('hero_btn_text', '在线预约');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 招牌推荐（biz_food_special_N_title / _desc / _price）
$specials = [];
for ($i = 1; $i <= 3; $i++) {
    $t = bizOpt("special_{$i}_title", ''); if ($t === '') continue;
    $specials[] = ['title' => $t, 'desc' => bizOpt("special_{$i}_desc", ''), 'price' => bizOpt("special_{$i}_price", '')];
}
if (empty($specials)) {
    $specials = [
        ['title' => '招牌手作 · 本店经典', 'desc' => '精选当季新鲜食材，传承三代秘方，每日限量供应。', 'price' => '¥38'],
        ['title' => '季节限定 · 新品上市', 'desc' => '每季更换菜单，用最新鲜的食材呈现时令风味。', 'price' => '¥28'],
        ['title' => '家庭套餐 · 多人分享', 'desc' => '适合 4-6 人聚餐，包含招牌菜与主食饮品。', 'price' => '¥168'],
    ];
}

// 服务项目
$services = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("service_{$i}_title", ''); if ($t === '') continue;
    $services[] = ['icon' => bizOpt("service_{$i}_icon", '🍽'), 'title' => $t, 'desc' => bizOpt("service_{$i}_desc", '')];
}
if (empty($services)) {
    $services = [
        ['icon' => '🍽', 'title' => '堂食服务', 'desc' => '温馨用餐环境，适合朋友小聚与家庭聚餐'],
        ['icon' => '🥡', 'title' => '外卖配送', 'desc' => '3 公里内免费配送，30 分钟送达'],
        ['icon' => '🎂', 'title' => '宴会预订', 'desc' => '承接生日宴、婚宴、企业团餐'],
        ['icon' => '🕐', 'title' => '深夜食堂', 'desc' => '营业至凌晨 2 点，为夜归人留一盏灯'],
        ['icon' => '🎫', 'title' => '会员储值', 'desc' => '储值享折扣，生日当月专属优惠'],
        ['icon' => '☕', 'title' => '下午茶', 'desc' => '每日 14:00-17:00，甜点饮品第二份半价'],
    ];
}

// 顾客评价
$reviews = [];
for ($i = 1; $i <= 3; $i++) {
    $t = bizOpt("review_{$i}_text", ''); if ($t === '') continue;
    $reviews[] = ['text' => $t, 'name' => bizOpt("review_{$i}_name", '')];
}
if (empty($reviews)) {
    $reviews = [
        ['text' => '味道非常正宗，食材新鲜，服务也很热情，已经是第 5 次来了！', 'name' => '李女士 · 老顾客'],
        ['text' => '环境温馨，适合约会。招牌菜真的名不虚传，推荐！', 'name' => '张先生 · 大众点评'],
        ['text' => '外卖包装很用心，送到还是热乎的，味道和堂食一样好。', 'name' => '王小姐 · 美团外卖'],
    ];
}

// 最新动态
$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => '餐厅,美食,预约'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <div class="biz-hero-text">
            <span class="biz-hero-badge">🌿 新鲜 · 匠心 · 温度</span>
            <h1><?php echo $heroTitle; // 允许 <em> 高亮 ?></h1>
            <p><?php echo esc($heroSub); ?></p>
            <div class="biz-hero-actions">
                <?php if ($heroBtnUrl !== ''): ?>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc($heroBtnUrl); ?>"><?php echo esc($heroBtnText); ?></a>
                <?php endif; ?>
                <a class="biz-btn biz-btn-ghost" href="<?php echo esc(bizOpt('contact_url', '')); ?>">查看菜单</a>
            </div>
        </div>
    </div>
</section>

<!-- 招牌推荐 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Signature</p>
            <h2>招牌推荐</h2>
            <p>人气之选 · 匠心之作</p>
        </div>
        <div class="biz-special">
            <?php foreach ($specials as $s): ?>
            <div class="biz-special-item">
                <div class="biz-special-body">
                    <h3><?php echo esc($s['title']); ?></h3>
                    <p><?php echo esc($s['desc']); ?></p>
                    <?php if (!empty($s['price'])): ?><span class="biz-special-price"><?php echo esc($s['price']); ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 服务项目 -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Services</p>
            <h2>我们的服务</h2>
        </div>
        <div class="biz-services">
            <?php foreach ($services as $s): ?>
            <div class="biz-service">
                <div class="biz-service-icon"><?php echo esc($s['icon']); ?></div>
                <h3><?php echo esc($s['title']); ?></h3>
                <p><?php echo esc($s['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 门店信息 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-shop">
            <div class="biz-shop-map">📍 门店位置（可放地图嵌入或实拍图）</div>
            <div class="biz-shop-info">
                <h3>门店信息</h3>
                <ul>
                    <?php if (bizOpt('phone', '') !== ''): ?><li><b>☎ 电话：</b><?php echo esc(bizOpt('phone', '')); ?></li><?php endif; ?>
                    <?php if (bizOpt('address', '') !== ''): ?><li><b>📍 地址：</b><?php echo esc(bizOpt('address', '')); ?></li><?php endif; ?>
                    <li><b>🕐 营业时间：</b><?php echo esc(bizOpt('hours', '周一至周日 10:00 - 22:00')); ?></li>
                    <?php if (bizOpt('email', '') !== ''): ?><li><b>✉️ 邮箱：</b><?php echo esc(bizOpt('email', '')); ?></li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- 顾客评价 -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Reviews</p>
            <h2>顾客评价</h2>
        </div>
        <div class="biz-reviews">
            <?php foreach ($reviews as $r): ?>
            <div class="biz-review">
                <div class="biz-review-stars">★★★★★</div>
                <p><?php echo esc($r['text']); ?></p>
                <span class="biz-review-name">— <?php echo esc($r['name']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 最新动态 -->
<?php if ($news): ?>
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">News</p>
            <h2>店铺动态</h2>
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
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-cta">
            <div>
                <h2>今天想来坐坐吗？</h2>
                <p><?php echo esc(bizOpt('phone', '')); ?> 电话预约 ｜ 到店即享</p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">立即预约</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
