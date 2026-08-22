<?php
/**
 * RYE社区（RyeBlog 插件）—— 关于 / 社区规则
 * 路由：/bbs/about
 */
require_once __DIR__ . '/bootstrap.php';

$stats = [
    'threads' => (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'threads WHERE is_deleted=0'),
    'posts'   => (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'posts WHERE is_deleted=0'),
    'users'   => (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'user_ext'),
];

$GLOBALS['bbs_page'] = 'about';
$GLOBALS['__rye_seo'] = ['desc' => '关于RYE社区', 'keywords' => '论坛,关于,规则'];
publicHeader();
require_once __DIR__ . '/inc/nav.php';
?>
<style>
.about-wrap{max-width:800px;margin:18px auto;padding:0 12px}
.about-card{background:#fff;border:1px solid #e3eadf;border-radius:12px;padding:20px 22px;margin-bottom:16px}
.about-card h1{color:#1f3d24;margin-top:0;font-size:22px}
.about-card h2{color:#2c5234;font-size:17px;margin:18px 0 8px}
.about-card p,.about-card li{color:#3a4a3e;line-height:1.8;font-size:14px}
.about-stats{display:flex;gap:14px;margin:14px 0}
.about-stat{flex:1;text-align:center;background:#f3f8f1;border-radius:10px;padding:14px}
.about-stat b{display:block;font-size:24px;color:#2c7d3f}
.about-stat span{font-size:13px;color:#7a8a7e}
</style>
<div class="about-wrap">
    <div class="about-card">
        <h1>关于RYE社区</h1>
        <p>RYE社区是 <a href="<?php echo e(baseUrl('')); ?>">RyeBlog</a> 与 RyeCMS 的官方交流社区，直接复用站点账号，无需单独注册。在这里你可以发帖交流、提问求助、分享资源——无论是博客的使用、主题与插件开发，还是 RyeCMS 相关讨论，都欢迎来此。后续 RyeCMS 的讨论也将统一汇聚到 RYE社区。</p>
        <div class="about-stats">
            <div class="about-stat"><b><?php echo $stats['threads']; ?></b><span>主题</span></div>
            <div class="about-stat"><b><?php echo $stats['posts']; ?></b><span>回复</span></div>
            <div class="about-stat"><b><?php echo $stats['users']; ?></b><span>注册用户</span></div>
        </div>
    </div>
    <div class="about-card">
        <h2>社区规则</h2>
        <ul>
            <li>友善交流，禁止人身攻击、谩骂与歧视。</li>
            <li>不发广告、垃圾信息、外链引流等无关内容。</li>
            <li>不发布违法、违规或侵犯他人权益的内容。</li>
            <li>尊重原创，转载请注明出处。</li>
            <li>违规内容将被删除，严重者封禁账号或 IP。</li>
        </ul>
        <h2>积分与互动</h2>
        <ul>
            <li>每日签到可获得金币，金币用于社区内身份与活动。</li>
            <li>对有用内容点赞（👍）、收藏（⭐），方便日后回看。</li>
            <li>主题被设为精华、置顶由版主与管理员操作。</li>
        </ul>
        <p style="margin-top:14px"><a class="btn btn-primary" href="<?php echo e(bbs_url('forum')); ?>">进入版块</a></p>
    </div>
</div>
<?php publicFooter(rye_sidebar_html()); ?>
