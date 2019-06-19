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

if (Showcase::$conf['checkaccess']) Access::debug(true);
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
	$ans = Catalog::action('table');

	$ans['res'] = [];
	$ans['res']['Данные'] = Catalog::actionLoadAll();
	$ans['res']['Прайсы'] = Prices::actionLoadAll();
	$ans['res']['Файлы'] = Data::actionAddFiles();	

	$list = Catalog::getList();
	$ans['list'] = $list;

	return Rest::parse('-showcase/index.tpl', $ans, 'CATALOG');
}, 'catalog', function () {
	header('location: /-showcase/tables');
	exit;
}, 'tables', function () {
	$ans = Catalog::action('table');
	

	$list = Catalog::getList();
	foreach($list as $i => $row) {
		unset($list[$i]['ans']);
	}
	$ans['list'] = $list;
	
	return Rest::parse('-showcase/index.tpl', $ans, 'CATALOG');
}, 'prices', [function () {
	$ans = Catalog::action('price');
	
	$list = Prices::getList();
	foreach($list as $i => $row) {
		unset($list[$i]['ans']);
	}
	$ans['list'] = $list;
	
	return Rest::parse('-showcase/index.tpl', $ans, 'PRICES');
	}, function($a, $name){
		$ans = Catalog::action('price');
		
		$ans += Prices::getPrice($name);

		return Rest::parse('-showcase/index.tpl', $ans, 'PRICE');
}], 'groups', function() {
	$ans = Catalog::action('table');
	$ans['list'] = Data::getGroups();
	return Rest::parse('-showcase/index.tpl', $ans, 'GROUPS');
}, 'producers',[function() {
	$ans = Catalog::action();
	$ans['list'] = Data::getProducers();
	return Rest::parse('-showcase/index.tpl', $ans, 'PRODUCERS');

	}, function ($a, $producer_nick){
		$ans = Catalog::action();

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
		
		foreach ($options as $name => $p) {
			if ($p['producer_nick'] != $producer_nick && !isset($plist[$name])) unset($options[$name]);
		}
		$ans['plist'] = $options;

		$options = Catalog::getList();
		
		foreach ($options as $name => $p) {
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
