<?php

use akiyatkin\showcase\Showcase;
use infrajs\ans\Ans;
use infrajs\excel\Xlsx;

$ans = array();
$md = Showcase::initMark($ans);
$ans['root'] = Showcase::getGroup();
$isempty = Ans::GET('empty','bool', false);

if (!$isempty) {
	Xlsx::runGroups($ans['root'], function &(&$group, $i, &$parent) {
		if ($parent && empty($group['childs']) && !$group['count']) {
			unset($parent['childs'][$i]);
		}
		$r = null;
		return $r;
	}, true);
}

$group = false;
foreach ($md['group'] as $group => $one) break;
if ($group) {
	$group = Showcase::getGroup($group);
	$path = $group['path'];

	Xlsx::runGroups($ans['root'], function &(&$group) use ($path) {
		$r = null;
		if (in_array($group['group_nick'], $path)) {
			$group['active'] = true;
		}
		return $r;
	}, true);
	$ans['path'] = $group['path'];
}
return Ans::ret($ans);
/*use infrajs\load\Load;
use infrajs\rest\Rest;
use infrajs\ans\Ans;
use akiyatkin\showcase\Catalog;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;
use infrajs\path\Path;
use infrajs\view\View;
use infrajs\each\Each;
use infrajs\rubrics\Rubrics;


$ans = array();


$root = Showcase::getGroup();

if (isset($root['childs']))
foreach ($root['childs'] as $k=>$group) {
	unset($root['childs'][$k]['childs']);
	unset($root['childs'][$k]['path']);
	unset($root['childs'][$k]['parent_nick']);
}
$ans['list'] = $root['childs'];
return Ans::ret($ans);*/
