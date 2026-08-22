<?php
/** Vuecho 标签页模板（内容页风格）—— 变量：$tag $result $posts $page */
$GLOBALS['__rye_seo'] = [
    'desc'     => __('标签：') . L($tag, 'name') . ' - ' . siteSeoDescription(),
    'keywords' => L($tag, 'name'),
];
$listTitle    = __('标签：') . '#' . L($tag, 'name');
$listTotal    = (int)($result['total'] ?? 0);
$listItems    = $posts;
$listPages    = (int)($result['pages'] ?? 1);
$listPage     = (int)($result['page'] ?? 1);
$listPageUrl  = function ($i) use ($tag) { return tagUrl($tag) . '?p=' . $i; };
require __DIR__ . '/list_common.php';
