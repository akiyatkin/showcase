<?php
use infrajs\load\Load;
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
return Ans::ret($ans);
