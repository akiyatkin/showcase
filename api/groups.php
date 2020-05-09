<?php

use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\ans\Ans;
use infrajs\excel\Xlsx;

$ans = array();
$md = Showcase::initMark($ans);
$ans['root'] = Showcase::getGroup();
$isempty = Showcase::$conf['showemptygroups'];
$isempty = Ans::GET('empty','bool', $isempty);

if (!$isempty) {
	Xlsx::runGroups($ans['root'], function &(&$group, $i, &$parent) {
		if ($parent && empty($group['childs']) && !$group['count']) {
			unset($parent['childs'][$i]);
		}
		$r = null;
		return $r;
	}, true);
}
Xlsx::runGroups($ans['root'], function &(&$group, $i, &$parent) {
	$r = null;
	if(empty($group['childs'])) return $r;
	$group['childs'] = array_values($group['childs']);
	
	return $r;
});
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
$isdata = Ans::GET('data','bool', false);
if ($isdata) {
	Xlsx::runGroups($ans['root'], function &(&$group) {
		$group_id = $group['group_id'];
		$poss = Data::all('SELECT model_id, p.producer_nick, m.article_nick 
			from showcase_models m 
			join showcase_producers p on p.producer_id = m.producer_id
			where m.group_id = ? limit 0,3',[$group_id]);
		foreach ($poss as $k => $pos) {
			$poss[$k] = Showcase::getModel($pos['producer_nick'],$pos['article_nick']);
		}
		$group['data'] = $poss;
		$r = null;
		return $r;
	});
}
$isclean = Ans::GET('clean','bool', false);
if ($isclean) {
	Xlsx::runGroups($ans['root'], function &(&$group) {
		unset($group['showcase']);
		if (empty($group['childs'])) $group['childs'] = [];
		$group = [
			'group' => $group['group'],
			'childs' => $group['childs'],
			'group_nick' => $group['group_nick']
		];
		if (empty($group['childs'])) unset($group['childs']);
		
		$r = null;
		return $r;
	});
	
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
