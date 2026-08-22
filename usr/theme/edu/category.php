<?php
$GLOBALS['__biz_theme'] = 'edu';
$listTitle   = L($cat, 'name');
$listMeta    = __('分类：') . L($cat, 'name') . ' · 共 ' . (int) $result['total'] . ' 篇';
$listItems   = $result['items'];
$listTotal   = (int) $result['total'];
$listPage    = (int) $result['page'];
$listPages   = (int) $result['pages'];
$listPageUrl = function ($i) use ($cat) { return categoryPageUrl($cat, $i); };
$listActive  = 'cat-' . $cat['slug'];
$listMode  = ($cat['slug'] === 'cases') ? 'card' : '';
require_once __DIR__ . '/_biz/list.php';
