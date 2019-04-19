<?php
use infrajs\load\Load;
use infrajs\rest\Rest;
use infrajs\ans\Ans;
use akiyatkin\showcase\Catalog;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;
use infrajs\path\Path;
use infrajs\rubrics\Rubrics;
ob_start();
date_default_timezone_set("Europe/Samara");

echo Rest::get( function () {
	$ans = [];
	$ans['count'] = Data::col('SELECT count(*) as `count` from showcase_models');
	return Rest::parse('-showcase/api/index.tpl', $ans, 'API');
}, 'seo', function (){
	$ans = [];
	return Ans::ret($ans);	
}, 'search', function (){

	$val = Ans::GET('val');
	$val = Path::encode(Path::toutf(strip_tags($val)));
	$art = Ans::GET('art');

	$md = Showcase::initMark($ans, $val, $art);
	
	$ans['page'] = Ans::GET('p','integer',1);
	if ($ans['page'] < 1) $ans['page'] = 1;
	
	$ans['is'] = ''; //group producer search Что было найдено по запросу val (Отдельный файл is:change)
	$ans['descr'] = '';//абзац текста в начале страницы';
	$ans['text'] = ''; //большая статья снизу всего
	$ans['name'] = ''; //заголовок длинный и человеческий
	$ans['breadcrumbs'] = array();//Путь где я нахожусь
	$ans['filters'] = array();//Данные для формирования интерфейса фильтрации, опции и тп
	$ans['groups'] = array();
	$ans['producers'] = array();
	$ans['numbers'] = array(); //Данные для построения интерфейса постраничной разбивки
	$ans['list'] = array(); //Массив позиций

	Showcase::makeBreadcrumb($md, $ans, $ans['page']);

	Showcase::search($md, $ans, $ans['page']);

	$src  =  Rubrics::find(Showcase::$conf['grouparticles'], $ans['title']);
	if ($src) {
		$ans['textinfo']  =  Rubrics::info($src); 
		$ans['text']  =  Load::loadTEXT('-doc/get.php?src='.$src);//Изменение текста не отражается как изменение каталога, должно быть вне кэша
	}
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
