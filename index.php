<?php
use infrajs\load\Load;
use infrajs\rest\Rest;
use infrajs\ans\Ans;
use akiyatkin\showcase\Catalog;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;

date_default_timezone_set("Europe/Samara");

echo Rest::get( function () {
	return Rest::parse('-showcase/index.tpl');
}, 'catalog', function () {
	$ans = array();

	$action = Ans::REQ('action');
	$name = Ans::REQ('name');
	$src = Ans::REQ('src');

	$ans['post'] = $_POST;
	$ans['conf'] = Showcase::$conf;

	Catalog::init();
	if ($action == 'clearAll') $ans['res'] = Data::actionClearAll();
	if ($action == 'load') $ans['res'] = Catalog::actionLoad($name, $src);
	if ($action == 'remove') $ans['res'] = Catalog::actionRemove($name, $src);
	

	$list = Catalog::getList();
	$ans['list'] = $list;
	$ans['durationrate'] = 10; //килобайт в секунду
	$ans['durationfactor'] = round(1/$ans['durationrate'],4); //секунд на килобайт
	

	return Rest::parse('-showcase/index.tpl', $ans, 'CATALOG');
}, 'prices', function () {
	$ans = array();
	Data::timer('инициализация');
	$action = Ans::REQ('action');
	$name = Ans::REQ('name');
	$src = Ans::REQ('src');

	$ans['post'] = $_POST;
	$ans['conf'] = Showcase::$conf;
	
	Prices::init();
	Data::timer('выполнен init');
	if ($action == 'clearAll') $ans['res'] = Data::actionClearAll();
	if ($action == 'load') $ans['res'] = Prices::actionLoad($name, $src);
	if ($action == 'remove') $ans['res'] = Prices::actionRemove($name, $src);
	Data::timer('выполнены action');

	$list = Prices::getList();
	$ans['list'] = $list;
	$ans['durationrate'] = 100; //килобайт в секунду
	$ans['durationfactor'] = round(1/$ans['durationrate'],4); //секунд на килобайт

	return Rest::parse('-showcase/index.tpl', $ans, 'PRICES');
}, 'search', function (){
	$ans = array();
	$ans['list'] = Showcase::search();
	return Ans::ret($ans);
}, 'groups', function() {
	$ans = array();
	$ans['list'] = Showcase::getGroups();
	return Ans::ret($ans);
}, 'pos', [
	function (){
		return 'producer/article';
	},[
		function( ) {
			return 'article';
		},
		function ($a, $producer, $article) {
			$ans = array();
			$ans['pos'] = Showcase::getModel($producer, $article);
			return Ans::ret($ans);
		}
	]
], function (){
	return 'catalog, price, search';
});
