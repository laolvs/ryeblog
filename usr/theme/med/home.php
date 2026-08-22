<?php
/**
 * RyeBlog 企业站主题 —— 医疗健康 Med 首页
 * 结构：Banner → 重点科室 → 专家团队 → 特色服务 → 健康科普 → 预约 CTA
 * 数据：后台「企业站主题」设置（biz_med_*）；科普/案例用分类文章
 */
$GLOBALS['__biz_theme'] = 'med';
require_once __DIR__ . '/_biz/bootstrap.php';

// 主题数据（后台可配）
$heroTitle   = bizOpt('hero_title', '守护<em>每一份健康</em>，专业值得信赖');
$heroSub     = bizOpt('hero_sub', '三级综合医院，汇聚百余位专家，以患者为中心，提供高品质医疗健康服务。');
$heroBtnText = bizOpt('hero_btn_text', '在线预约挂号');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 医院硬实力数据
$stats = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("stat_{$i}_title", ''); $v = bizOpt("stat_{$i}_value", '');
    if ($t !== '' && $v !== '') $stats[] = ['title' => $t, 'value' => $v];
}
if (empty($stats)) {
    $stats = [
        ['title' => '重点科室',   'value' => '42'],
        ['title' => '主任医师',   'value' => '120+'],
        ['title' => '开放床位',   'value' => '1500'],
        ['title' => '年门诊量',   'value' => '200万+'],
    ];
}

// 重点科室（biz_med_dept_N_title / _desc / _icon）
$depts = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("dept_{$i}_title", ''); if ($t === '') continue;
    $depts[] = [
        'icon' => bizOpt("dept_{$i}_icon", '🏥'),
        'title' => $t,
        'desc'  => bizOpt("dept_{$i}_desc", ''),
    ];
}
if (empty($depts)) {
    $depts = [
        ['icon' => '🫀', 'title' => '心血管内科', 'desc' => '胸痛中心绿色通道，介入治疗技术领先'],
        ['icon' => '🧠', 'title' => '神经外科',   'desc' => '微创手术成熟，脑卒中救治体系完善'],
        ['icon' => '👶', 'title' => '儿科',       'desc' => '儿童专科门诊，温馨就医环境'],
        ['icon' => '🤰', 'title' => '妇产科',     'desc' => '无痛分娩、高危孕产妇救治中心'],
        ['icon' => '🦴', 'title' => '骨科',       'desc' => '关节置换、脊柱微创，康复一体化'],
        ['icon' => '🦷', 'title' => '口腔科',     'desc' => '数字化种植、正畸、儿童齿科'],
    ];
}

// 专家团队（biz_med_doctor_N_name / _title / _goodat / _img）
$doctors = [];
for ($i = 1; $i <= 4; $i++) {
    $n = bizOpt("doctor_{$i}_name", ''); if ($n === '') continue;
    $doctors[] = [
        'name'   => $n,
        'title'  => bizOpt("doctor_{$i}_title", ''),
        'goodat' => bizOpt("doctor_{$i}_goodat", ''),
        'img'    => bizOpt("doctor_{$i}_img", ''),
    ];
}
if (empty($doctors)) {
    $doctors = [
        ['name' => '王建国', 'title' => '主任医师 · 心血管内科', 'goodat' => '冠心病介入治疗、心律失常射频消融', 'img' => ''],
        ['name' => '李慧敏', 'title' => '主任医师 · 神经外科', 'goodat' => '颅内肿瘤微创手术、脑血管疾病', 'img' => ''],
        ['name' => '张明远', 'title' => '副主任医师 · 骨科', 'goodat' => '关节置换、运动损伤修复', 'img' => ''],
        ['name' => '陈雅琴', 'title' => '主任医师 · 妇产科', 'goodat' => '高危妊娠管理、腹腔镜微创手术', 'img' => ''],
    ];
}

// 特色服务（biz_med_feat_N_title / _desc / _icon）
$feats = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("feat_{$i}_title", ''); if ($t === '') continue;
    $feats[] = ['icon' => bizOpt("feat_{$i}_icon", '⭐'), 'title' => $t, 'desc' => bizOpt("feat_{$i}_desc", '')];
}
if (empty($feats)) {
    $feats = [
        ['icon' => '🩺', 'title' => '体检中心', 'desc' => '一站式健康体检，个性化报告解读'],
        ['icon' => '🔬', 'title' => '微创中心', 'desc' => '腔镜、机器人手术，创伤小恢复快'],
        ['icon' => '♿', 'title' => '康复医学', 'desc' => '神经康复、骨伤康复，重获生活能力'],
        ['icon' => '🚑', 'title' => '24h 急诊', 'desc' => '胸痛/卒中/创伤三大中心全天候待命'],
    ];
}

// 健康科普
$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => '医院,医疗,健康,体检,挂号'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <div class="biz-hero-text">
            <span class="biz-hero-badge"><?php echo esc(bizOpt('hero_badge', '三级综合医院')); ?></span>
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

<!-- 重点科室 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Departments</p>
            <h2>重点科室</h2>
            <p>专业细分，精准诊疗</p>
        </div>
        <div class="biz-features">
            <?php foreach ($depts as $d): ?>
            <div class="biz-feature">
                <div class="biz-feature-icon"><?php echo esc($d['icon']); ?></div>
                <h3><?php echo esc($d['title']); ?></h3>
                <p><?php echo esc($d['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 专家团队 -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Expert Team</p>
            <h2>专家团队</h2>
            <p>医术精湛，仁心仁术</p>
        </div>
        <div class="biz-products">
            <?php foreach ($doctors as $d): ?>
            <div class="biz-product">
                <?php if ($d['img'] !== ''): ?>
                <img class="biz-doctor-img" src="<?php echo esc($d['img']); ?>" alt="<?php echo esc($d['name']); ?>" loading="lazy">
                <?php endif; ?>
                <div class="biz-product-body">
                    <h3><?php echo esc($d['name']); ?></h3>
                    <p><?php echo esc($d['title']); ?></p>
                    <?php if ($d['goodat'] !== ''): ?><span class="biz-doctor-tag">擅长：<?php echo esc($d['goodat']); ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 特色服务 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Services</p>
            <h2>特色服务</h2>
        </div>
        <div class="biz-features">
            <?php foreach ($feats as $f): ?>
            <div class="biz-feature">
                <div class="biz-feature-icon"><?php echo esc($f['icon']); ?></div>
                <h3><?php echo esc($f['title']); ?></h3>
                <p><?php echo esc($f['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 健康科普 -->
<?php if ($news): ?>
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Health Tips</p>
            <h2>健康科普</h2>
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
                <h2>在线预约挂号，免排队</h2>
                <p><?php echo esc(bizOpt('phone', '')); ?> ｜ <?php echo esc(bizOpt('email', '')); ?></p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">立即预约</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
