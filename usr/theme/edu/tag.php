<?php
$GLOBALS['__biz_theme'] = 'edu';
$listTitle   = __('标签：') . L($tag, 'name');
$listMeta    = '共 ' . (int) $result['total'] . ' 篇';
$listItems   = $result['items'];
$listTotal   = (int) $result['total'];
$listPage    = (int) $result['page'];
$listPages   = (int) $result['pages'];
$listPageUrl = function ($i) use ($tag) { return tagPageUrl($tag, $i); };
require_once __DIR__ . '/_biz/list.php';
