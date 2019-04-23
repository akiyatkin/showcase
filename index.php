<?php
use infrajs\load\Load;
use infrajs\rest\Rest;
use infrajs\ans\Ans;
use akiyatkin\showcase\Catalog;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;

ob_start();
date_default_timezone_set("Europe/Samara");

echo Rest::get( function () {
	$ans = [];
	$ans['count'] = Data::col('SELECT count(*) as `count` from showcase_models');
	return Rest::parse('-showcase/index.tpl', $ans);
}, 'update', function () {
	$ans = array();

	$action = Ans::REQ('action');
	$name = Ans::REQ('name');
	$src = Ans::REQ('src');


	$ans['post'] = $_POST;
	$ans['conf'] = Showcase::$conf;

	Catalog::init();
	
	$ans['res'] = [];
	Prices::init();
	$ans['res']['Данные'] = Catalog::actionLoadAll();
	$ans['res']['Прайсы'] = Prices::actionLoadAll();
	//$ans['res']['Файлы'] = Data::actionAddFiles();	
	if ($action == 'addFiles') $ans['res'] = Data::actionAddFiles();
	
	$list = Catalog::getList();
	$ans['list'] = $list;
	$ans['durationrate'] = 10; //килобайт в секунду
	$ans['durationfactor'] = round(1/$ans['durationrate'],4); //секунд на килобайт
	

	return Rest::parse('-showcase/index.tpl', $ans, 'CATALOG');
}, 'catalog', function () {
	header('location: /-showcase/tables');
	exit;
}, 'tables', function () {
	$ans = array();
	$ans['actions'] = true;
	$action = Ans::REQ('action');
	$name = Ans::REQ('name');
	$src = Ans::REQ('src');

	$ans['post'] = $_POST;
	$ans['conf'] = Showcase::$conf;

	Catalog::init();
	if ($action == 'clearAll') $ans['res'] = Data::actionClearAll();
	if ($action == 'load') {
		$opt = Catalog::getOptions($name);
		$ans['res'] = [];
		$ans['res']['Данные'] = Catalog::actionLoad($name, $src);
		$ans['res']['Файлы'] = Data::actionAddFiles($opt['producer']);
	}
	if ($action == 'remove') $ans['res'] = Catalog::actionRemove($name, $src);
	if ($action == 'addFiles') $ans['res'] = Data::actionAddFiles();
	if ($action == 'loadAll') {
		$ans['res'] = [];
		Prices::init();
		$ans['res']['Данные'] = Catalog::actionLoadAll();
		$ans['res']['Прайсы'] = Prices::actionLoadAll();
		//$ans['res']['Файлы'] = Data::actionAddFiles();

	}
	

	$list = Catalog::getList();
	$ans['list'] = $list;
	$ans['durationrate'] = 10; //килобайт в секунду
	$ans['durationfactor'] = round(1/$ans['durationrate'],4); //секунд на килобайт
	

	return Rest::parse('-showcase/index.tpl', $ans, 'CATALOG');
}, 'prices', function () {
	$ans = array();
	
	$action = Ans::REQ('action');
	$name = Ans::REQ('name');
	$src = Ans::REQ('src');

	$ans['post'] = $_POST;
	$ans['conf'] = Showcase::$conf;
	Prices::init();
	

	if ($action == 'clearAll') $ans['res'] = Data::actionClearAll();
	if ($action == 'addFiles') $ans['res'] = Data::actionAddFiles();
	if ($action == 'load') $ans['res'] = Prices::actionLoad($name, $src);
	if ($action == 'remove') $ans['res'] = Prices::actionRemove($name, $src);
	if ($action == 'loadAll') {
		Catalog::init();
		$ans['res'] = [];
		$ans['res']['Данные'] = Catalog::actionLoadAll();
		$ans['res']['Прайсы'] = Prices::actionLoadAll();
		$ans['res']['Файлы'] = Data::actionAddFiles();
	}
	$list = Prices::getList();
	$ans['list'] = $list;
	$ans['durationrate'] = 100; //килобайт в секунду
	$ans['durationfactor'] = round(1/$ans['durationrate'],4); //секунд на килобайт*/
	
	return Rest::parse('-showcase/index.tpl', $ans, 'PRICES');
}, 'groups', function() {
	$ans = array();
	$ans['list'] = Data::getGroups();
	return Rest::parse('-showcase/index.tpl', $ans, 'GROUPS');
}, 'producers', function() {
	$ans = array();
	$ans['list'] = Data::getProducers();
	return Rest::parse('-showcase/index.tpl', $ans, 'PRODUCERS');
}, 'models', function() {
	$ans = array();
	$ans['list'] = Data::getModels();
	return Rest::parse('-showcase/index.tpl', $ans, 'MODELS');
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
