<?php
/** Vuecho 搜索/归档页模板（内容页风格）—— 变量：$title $q $archive $result $posts $page */
$GLOBALS['__rye_seo'] = [
    'desc'     => $title,
    'keywords' => $q,
];
$listTitle    = $title;
$listTotal    = (int)($result['total'] ?? 0);
$listItems    = $posts;
$listPages    = (int)($result['pages'] ?? 1);
$listPage     = (int)($result['page'] ?? 1);
$listPageUrl  = function ($i) use ($q, $archive) { return searchPageUrl($q, $archive, $i); };
require __DIR__ . '/list_common.php';
