<?php
/**
 * RyeBlog 企业站主题 —— 教育培训 Edu 首页
 * 结构：Hero → 课程体系 → 学习优势 → 师资团队 → 学员成果 → 最新动态 → 试听 CTA
 */
$GLOBALS['__biz_theme'] = 'edu';
require_once __DIR__ . '/_biz/bootstrap.php';

$heroTitle   = bizOpt('hero_title', '让每个孩子都能<em>学有所成</em>');
$heroSub     = bizOpt('hero_sub', '10 年专注 K12 与职业培训，名师团队 + 科学体系，见证 5000+ 学员的成长。');
$heroBtnText = bizOpt('hero_btn_text', '免费试听');
$heroBtnUrl  = bizOpt('hero_btn_url', '');

// 课程（biz_edu_course_N_title / _desc / _meta）
$courses = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("course_{$i}_title", ''); if ($t === '') continue;
    $courses[] = ['icon' => bizOpt("course_{$i}_icon", '📚'), 'title' => $t, 'desc' => bizOpt("course_{$i}_desc", ''), 'meta' => bizOpt("course_{$i}_meta", '')];
}
if (empty($courses)) {
    $courses = [
        ['icon' => '🔤', 'title' => '少儿英语', 'desc' => '沉浸式母语教学，培养语感与表达自信。', 'meta' => '3-12岁 · 32课时'],
        ['icon' => '🔢', 'title' => '数学思维', 'desc' => '趣味化思维训练，打好逻辑与计算基础。', 'meta' => '6-12岁 · 24课时'],
        ['icon' => '🎨', 'title' => '美术创意', 'desc' => '激发想象力与创造力，作品参与全国大赛。', 'meta' => '4-14岁 · 20课时'],
        ['icon' => '🎹', 'title' => '钢琴一对一', 'desc' => '中央音乐学院师资，考级通过率 98%。', 'meta' => '5岁+ · 个性化'],
        ['icon' => '💻', 'title' => '少儿编程', 'desc' => '图形化到 Python，培养未来竞争力。', 'meta' => '7-15岁 · 36课时'],
        ['icon' => '📝', 'title' => '学科辅导', 'desc' => '小班制查漏补缺，期中期末提分明显。', 'meta' => '小学-高中 · 按学期'],
    ];
}

// 学习优势
$features = [];
for ($i = 1; $i <= 6; $i++) {
    $t = bizOpt("feature_{$i}_title", ''); if ($t === '') continue;
    $features[] = ['icon' => bizOpt("feature_{$i}_icon", '✅'), 'title' => $t, 'desc' => bizOpt("feature_{$i}_desc", '')];
}
if (empty($features)) {
    $features = [
        ['icon' => '👩‍🏫', 'title' => '名师团队', 'desc' => '一线名师 + 重点名校背景，平均教龄 8 年'],
        ['icon' => '📖', 'title' => '科学课程体系', 'desc' => '分级教学，因材施教，每阶段有明确目标'],
        ['icon' => '📈', 'title' => '成长可视化', 'desc' => '月度测评报告，家长随时掌握学习进度'],
        ['icon' => '🏆', 'title' => '竞赛成果', 'desc' => '学员多次获省市竞赛奖项，升学率高'],
        ['icon' => '🏫', 'title' => '小班教学', 'desc' => '每班不超过 12 人，保证每个孩子被关注'],
        ['icon' => '🤝', 'title' => '课后服务', 'desc' => '作业辅导 + 家长沟通，24 小时答疑'],
    ];
}

// 师资
$teachers = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("teacher_{$i}_name", ''); if ($t === '') continue;
    $teachers[] = ['name' => $t, 'role' => bizOpt("teacher_{$i}_role", ''), 'desc' => bizOpt("teacher_{$i}_desc", '')];
}
if (empty($teachers)) {
    $teachers = [
        ['name' => '李老师', 'role' => '英语教研组长', 'desc' => '英语专业八级，10 年一线教学经验'],
        ['name' => '王老师', 'role' => '数学金牌讲师', 'desc' => '市数学竞赛优秀指导教师'],
        ['name' => '张老师', 'role' => '钢琴首席教师', 'desc' => '中央音乐学院硕士，考级评委'],
        ['name' => '陈老师', 'role' => '编程教学主管', 'desc' => '前互联网工程师，NOI 教练'],
    ];
}

// 学员成果
$results = [];
for ($i = 1; $i <= 4; $i++) {
    $t = bizOpt("result_{$i}_title", ''); $v = bizOpt("result_{$i}_value", '');
    if ($t !== '' && $v !== '') $results[] = ['title' => $t, 'value' => $v];
}
if (empty($results)) {
    $results = [
        ['title' => '累计培养学员', 'value' => '5000+'],
        ['title' => '课程满意度', 'value' => '99%'],
        ['title' => '竞赛获奖人次', 'value' => '300+'],
        ['title' => '升学重点率', 'value' => '92%'],
    ];
}

$news = getPosts(['perPage' => 3])['items'] ?? [];

$GLOBALS['__rye_seo'] = ['desc' => bizOpt('hero_sub', $heroSub), 'keywords' => '教育,培训,课程'];
biz_head(bizOpt('seo_title', '首页'), $GLOBALS['__rye_seo']['desc']);
biz_nav('home');
?>
<!-- Hero -->
<section class="biz-hero">
    <div class="biz-container biz-hero-inner">
        <div class="biz-hero-text">
            <span class="biz-hero-badge">🎓 10 年专注教育 · 5000+ 学员信赖</span>
            <h1><?php echo $heroTitle; // 允许 <em> 高亮 ?></h1>
            <p><?php echo esc($heroSub); ?></p>
            <div class="biz-hero-actions">
                <?php if ($heroBtnUrl !== ''): ?>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc($heroBtnUrl); ?>"><?php echo esc($heroBtnText); ?></a>
                <?php endif; ?>
                <a class="biz-btn biz-btn-ghost" href="<?php echo esc(bizOpt('contact_url', '')); ?>">课程咨询</a>
            </div>
        </div>
    </div>
</section>

<!-- 课程体系 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Courses</p>
            <h2>课程体系</h2>
            <p>总有一门课，适合正在成长的他/她</p>
        </div>
        <div class="biz-courses">
            <?php foreach ($courses as $c): ?>
            <div class="biz-course">
                <div class="biz-course-icon"><?php echo esc($c['icon']); ?></div>
                <h3><?php echo esc($c['title']); ?></h3>
                <p><?php echo esc($c['desc']); ?></p>
                <?php if (!empty($c['meta'])): ?><div class="biz-course-meta"><span><?php echo esc($c['meta']); ?></span></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 学习优势 -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Why Us</p>
            <h2>为什么选择我们</h2>
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

<!-- 师资团队 -->
<section class="biz-section">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Teachers</p>
            <h2>师资团队</h2>
            <p>名师出高徒</p>
        </div>
        <div class="biz-teachers">
            <?php foreach ($teachers as $t): ?>
            <div class="biz-teacher">
                <div class="biz-teacher-avatar"><?php echo esc(mb_substr($t['name'], 0, 1, 'UTF-8')); ?></div>
                <h3><?php echo esc($t['name']); ?></h3>
                <p class="biz-teacher-role"><?php echo esc($t['role']); ?></p>
                <p><?php echo esc($t['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 学员成果 -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-section-head">
            <p class="biz-eyebrow">Achievements</p>
            <h2>学员成果</h2>
        </div>
        <div class="biz-results">
            <?php foreach ($results as $r): ?>
            <div class="biz-result"><b><?php echo esc($r['value']); ?></b><span><?php echo esc($r['title']); ?></span></div>
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
            <h2>校园动态</h2>
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

<!-- 试听 CTA -->
<section class="biz-section biz-section-alt">
    <div class="biz-container">
        <div class="biz-cta">
            <div>
                <h2>免费试听，不满意不报名</h2>
                <p><?php echo esc(bizOpt('phone', '')); ?> 电话咨询 ｜ 到校参观有礼</p>
            </div>
            <div>
                <a class="biz-btn biz-btn-primary" href="<?php echo esc(bizOpt('contact_url', '')); ?>">预约免费试听</a>
            </div>
        </div>
    </div>
</section>
<?php biz_footer();
