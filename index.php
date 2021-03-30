<?php
use infrajs\load\Load;
use infrajs\rest\Rest;
use infrajs\ans\Ans;
use infrajs\db\Db;
use infrajs\access\Access;
use infrajs\update\Update;
use akiyatkin\showcase\Catalog;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;

date_default_timezone_set("Europe/Samara");

if (Showcase::$conf['checkaccess']) Access::debug(true);

header('Cache-Control: no-store');
//ob_start();

echo Rest::get( function () {
	$ans = [];
	$ans['count'] = Data::col('SELECT count(*) as `count` from showcase_models');
	return Rest::parse('-showcase/index.tpl', $ans);
}, 'drop', function () {
	
	Data::exec('DROP TABLE IF EXISTS 
		showcase_prices,
		showcase_catalog,
		showcase_groups,
		showcase_producers,
		showcase_articles,
		showcase_props,
		showcase_values,
		showcase_items,
		showcase_models,
		showcase_search,
		showcase_mvalues,
		showcase_mnumbers,
		showcase_mtexts,
		showcase_iprops
	');
	
	Update::exec();
	header('location: /-showcase/');
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
		
		$prod = Data::fetch('SELECT producer_id, producer, producer_nick from showcase_producers where producer_nick = ?',[$producer_nick]);

		if ($prod) {
			$ans['producer'] = $prod['producer'];
			$ans['producer_nick'] = $prod['producer_nick'];
		}
		
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
