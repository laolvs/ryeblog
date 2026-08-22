<?php
/**
 * CLI —— 激活英文后台语言包插件（english-admin）
 *
 * 用法（在真实站点根目录执行，需 MySQL 可用）：
 *   php usr/plugins/english-admin/cli_enable.php
 *
 * 幂等：重复执行无副作用。仅做两件事：
 *   1) 确保 english-admin 在 active_plugins 列表中（启用）
 *   2) 输出词典条目数与冒烟测试结果（__() 命中验证）
 */
require_once __DIR__ . '/../../../inc/functions.php';

if (!db()) {
    fwrite(STDERR, "DB 不可用（install.php 未完成？）。\n");
    exit(1);
}

$cur = pluginActiveList();
if (!in_array('english-admin', $cur, true)) {
    $cur[] = 'english-admin';
    setOption('active_plugins', implode(',', $cur));
    echo "+ plugin english-admin enabled\n";
} else {
    echo "= plugin english-admin already enabled\n";
}

// 冒烟：词典加载 + __() 命中
$dict = loadLangDict('en');
echo 'dict entries: ' . count($dict) . "\n";
setCurrentLang('en');
$checks = [
    '仪表盘'      => 'Dashboard',
    '文章管理'    => 'Posts',
    '写文章'      => 'Write Post',
    '分类管理'    => 'Categories',
    '评论管理'    => 'Comments',
    '附件管理'    => 'Attachment Management',
    '菜单管理'    => 'Menu Management',
    '侧边栏管理'  => 'Sidebar',
    '插件管理'    => 'Plugins',
    '主题管理'    => 'Themes',
    '站点设置'    => 'Settings',
    '退出登录'    => 'Logout',
    '标题不能为空。' => 'Title is required.',
    '表单已失效，请重试。' => 'Form expired. Please retry.',
    '博主信息卡'  => 'Author Card',
];
$fail = 0;
foreach ($checks as $zh => $expected) {
    $got = __($zh);
    $ok = $got === $expected;
    if (!$ok) $fail++;
    printf("[%s] __('%s') => '%s'%s\n", $ok ? 'OK' : 'FAIL', $zh, $got, $ok ? '' : ' (expected: ' . $expected . ')');
}
// 未译回退检查
$fb = __('这是不存在的词条');
if ($fb !== '这是不存在的词条') $fail++;
printf("[%s] fallback: __('这是不存在的词条') => '%s'\n", $fb === '这是不存在的词条' ? 'OK' : 'FAIL', $fb);

echo $fail === 0 ? "ALL OK\n" : "$fail check(s) FAILED\n";
exit($fail === 0 ? 0 : 1);
