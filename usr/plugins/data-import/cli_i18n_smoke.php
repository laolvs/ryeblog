<?php
/**
 * 双语 i18n 冒烟测试（验证 ©+④ 核心逻辑，不改业务数据，仅临时置一个 title_en 后回滚）。
 */
require_once __DIR__ . '/../../../inc/functions.php';

$p = dbOne("SELECT * FROM vd_posts WHERE type='post' LIMIT 1");
if (!$p) { echo "没有文章可测\n"; exit(1); }

setCurrentLang('zh');
echo "ZH title via L : " . L($p, 'title') . "\n";

setCurrentLang('en');
echo "EN title via L (无译文→回退中文) : " . L($p, 'title') . "\n";

// 临时置一个英文标题验证回退反转
dbQuery("UPDATE vd_posts SET title_en=? WHERE id=?", ['[EN] ' . $p['title'], $p['id']]);
$p2 = dbOne("SELECT * FROM vd_posts WHERE id=?", [$p['id']]);
echo "EN title via L (已置译文) : " . L($p2, 'title') . "\n";

// URL 助手（语言感知）
echo "postUrl zh : " . postUrlForLang($p2, 'zh') . "\n";
echo "postUrl en : " . postUrlForLang($p2, 'en') . "\n";
setCurrentLang('zh');
echo "homeUrl zh : " . homeUrl() . "\n";
setCurrentLang('en');
echo "homeUrl en : " . homeUrl() . "\n";
echo "categoryUrl en : " . categoryUrl(['slug' => 'tech']) . "\n";
echo "tagUrl en : " . tagUrl(['slug' => 'blog']) . "\n";
echo "searchUrl en : " . searchUrl('php') . "\n";

// 语言切换器（模拟在 /cn/post/xxx 上）
$_SERVER['REQUEST_URI'] = '/cn/post/' . urlencode($p['slug']);
setCurrentLang('zh');
echo "langSwitch (zh 当前) : " . langSwitchHtml() . "\n";

// 回滚
dbQuery("UPDATE vd_posts SET title_en=NULL WHERE id=?", [$p['id']]);
echo "已回滚 title_en。\n";
echo "OK\n";
