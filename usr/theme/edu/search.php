<?php
$GLOBALS['__biz_theme'] = 'edu';
$listTitle   = __('搜索：') . $q;
$listMeta    = '共 ' . (int) $result['total'] . ' 篇';
$listItems   = $result['items'];
$listTotal   = (int) $result['total'];
$listPage    = (int) $result['page'];
$listPages   = (int) $result['pages'];
$listPageUrl = function ($i) use ($q) { return baseUrl('search.php?q=' . urlencode($q) . '&p=' . $i); };
require_once __DIR__ . '/_biz/list.php';
