<?php
/** RyeBlog 官方主题 —— 分类页模板 */
$GLOBALS['__rye_seo'] = ['desc' => L($cat, 'description'), 'keywords' => L($cat, 'name')];
$listTitle = __('分类：') . L($cat, 'name');
$listTotal = (int)($result['total'] ?? 0);
$listItems = $posts;
$listPages = (int)($result['pages'] ?? 1);
$listPage  = (int)($result['page'] ?? 1);
$listPageUrl = function ($i) use ($cat) { return categoryPageUrl($cat, $i); };
require __DIR__ . '/list_common.php';
