<?php
use infrajs\load\Load;
use infrajs\rest\Rest;
use infrajs\ans\Ans;
use infrajs\access\Access;
use akiyatkin\showcase\Catalog;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;

date_default_timezone_set("Europe/Samara");

Access::debug(true);
ob_start();
echo Rest::get( function () {
	$ans = [];
	$ans['count'] = Data::col('SELECT count(*) as `count` from showcase_models');
	return Rest::parse('-showcase/index.tpl', $ans);
}, 'drop', function () {
	header('location: /-showcase/?-update=true');
	
	Data::exec('DROP TABLE showcase_prices');
	Data::exec('DROP TABLE showcase_catalog');
	Data::exec('DROP TABLE showcase_groups');
	Data::exec('DROP TABLE showcase_producers');
	Data::exec('DROP TABLE showcase_articles');
	Data::exec('DROP TABLE showcase_props');
	Data::exec('DROP TABLE showcase_values');
	Data::exec('DROP TABLE showcase_items');
	Data::exec('DROP TABLE showcase_models');
	Data::exec('DROP TABLE showcase_mvalues');
	Data::exec('DROP TABLE showcase_mnumbers');
	Data::exec('DROP TABLE showcase_mtexts');
	
	exit;
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

	$action = Ans::REQ('action');
	$name = Ans::REQ('name');
	$src = Ans::REQ('src');
	$type = Ans::REQ('type');

	$ans['post'] = $_POST;
	$ans['conf'] = Showcase::$conf;

	Catalog::init();
	$ans['res'] = Catalog::action($action, $name, $src, $type);

	$list = Catalog::getList();
	$ans['list'] = $list;
	
	return Rest::parse('-showcase/index.tpl', $ans, 'CATALOG');
}, 'prices', [function () {
	$ans = array();
	
	$action = Ans::REQ('action');
	$name = Ans::REQ('name');
	$src = Ans::REQ('src');
	$type = Ans::REQ('type');

	$ans['post'] = $_POST;
	$ans['conf'] = Showcase::$conf;
	Prices::init();
	
	$ans['res'] = Prices::action($action, $name, $src);
	
	$list = Prices::getList();
	$ans['list'] = $list;
	
	return Rest::parse('-showcase/index.tpl', $ans, 'PRICES');
	}, function($a, $name){
		$ans = array();
		$action = Ans::REQ('action');
		$src = Ans::REQ('src');
		$ans['post'] = $_POST;
		$ans['conf'] = Showcase::$conf;
		Prices::init();
		$ans['res'] = Prices::action($action, $name, $src);

		$ans += Prices::getPrice($name);

		return Rest::parse('-showcase/index.tpl', $ans, 'PRICE');
}], 'groups', function() {
	$ans = array();
	$ans['list'] = Data::getGroups();
	return Rest::parse('-showcase/index.tpl', $ans, 'GROUPS');
}, 'producers',[function() {
	$ans = array();
	$ans['list'] = Data::getProducers();

	return Rest::parse('-showcase/index.tpl', $ans, 'PRODUCERS');
		}, function ($a, $producer_nick){
		Prices::init();
		Catalog::init();

		$action = Ans::REQ('action');
		$name = Ans::REQ('name');
		$src = Ans::REQ('src');
		$type = Ans::REQ('type');
		$ans = [];

		$ans['res'] = Catalog::action($action, $name, $src, $type);

		$ans += Data::getProducers($producer_nick);

		if (isset($ans['catalog'])) {
			$clist = array_flip(explode(', ',$ans['catalog']));
		} else {
			$clist = array();
		}
		if(isset($ans['price'])) {
			$plist = array_flip(explode(', ',$ans['price']));
		} else {
			$plist = [];
		}

		$options = Prices::getList();
		foreach($options as $name => $p) {
			if ($p['producer_nick'] != $producer_nick && !isset($plist[$name])) unset($options[$name]);
		}
		$ans['plist'] = $options;

		$options = Catalog::getList();
		
		foreach($options as $name => $p) {
			if ($p['producer_nick'] != $producer_nick && !isset($clist[$name])) unset($options[$name]);
		}
		$ans['conf'] = Showcase::$conf;
		$ans['clist'] = $options;
		
		return Rest::parse('-showcase/index.tpl', $ans, 'PRODUCER');
}], 'models', function() {
	$ans = array();
	$ans['list'] = Data::getModels();
	return Rest::parse('-showcase/index.tpl', $ans, 'MODELS');
}, function (){
	return 'catalog, price, search';
});
