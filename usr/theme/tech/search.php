<?php
/** Tech 主题 —— 搜索（复用 _biz 共享模板） */
$GLOBALS['__biz_theme'] = 'tech';
$listTitle   = __('搜索：') . $q;
$listMeta    = '共 ' . (int) $result['total'] . ' 篇';
$listItems   = $result['items'];
$listTotal   = (int) $result['total'];
$listPage    = (int) $result['page'];
$listPages   = (int) $result['pages'];
$listPageUrl = function ($i) use ($q) { return baseUrl('search.php?q=' . urlencode($q) . '&p=' . $i); };
require_once __DIR__ . '/_biz/list.php';
