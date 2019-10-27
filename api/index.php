<?php
use infrajs\load\Load;
use infrajs\rest\Rest;
use infrajs\ans\Ans;
use akiyatkin\showcase\Catalog;
use infrajs\event\Event;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;
use infrajs\path\Path;
use infrajs\view\View;
use infrajs\each\Each;
use infrajs\layer\seojson\Seojson;
use infrajs\rubrics\Rubrics;
//ob_start();
date_default_timezone_set("Europe/Samara");
return Rest::get( function () {
	$ans = [];
	$ans['count'] = Data::col('SELECT count(*) as `count` from showcase_models');
	echo Rest::parse('-showcase/api/index.tpl', $ans, 'API');
}, 'groups', function ($name){
	return include(Path::theme('-showcase/api/'.$name.'.php'));
}, 'filters', function ($name){
	return include(Path::theme('-showcase/api/'.$name.'.php'));
}, 'producers', [function (){
	$ans = [];
	$fd = Showcase::initMark($ans);
	if (isset($_GET['lim'])) {
		$lim = $_GET['lim'];
	} else {
		$lim='0,20';
	}
	$p = explode(',', $lim);
	if (sizeof($p) != 2){
		return Ans::err($ans, 'Is wrong paramter lim');
	}
	$start = (int) $p[0];
	$count = (int) $p[1];
	$args = array($start, $count);
	$list = Showcase::getProducers();
	
	//$ans['menu'] = Load::loadJSON(Showcase::$conf['menu']);
	$ans['list'] = $list;

	$conf = Showcase::$conf;
	$ans['breadcrumbs'][] = array('main'=> true, 'href'=>'','title'=>'Главная','add'=>'group');
	$ans['breadcrumbs'][] = array('href'=>'','title'=>$conf['title'],'add'=>'group');
	$ans['breadcrumbs'][] = array('active'=> true, 'href'=>'producers','title'=>'Производители');
	return Ans::ret($ans);

	},"seo", function(){
		$ans = [];
		$link = Ans::GET('seo');
		$link = $link.'/producers';
		$ans['title'] = 'Производители';
		$ans['canonical'] = Seojson::getSite().'/'.$link;

		return Ans::ans($ans);	
}], 'search', [function (){

		$md = Showcase::initMark($ans);
		
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

		$src  =  Rubrics::find(Showcase::$conf['groups'], $ans['title']);
		if ($src) {
			$ans['textinfo']  =  Rubrics::info($src); 
			$ans['text']  =  Load::loadTEXT('-doc/get.php?src='.$src);//Изменение текста не отражается как изменение каталога, должно быть вне кэша
		}
		return Ans::ret($ans);
	},'seo', function(){
		$ans = array();

		$val = Ans::GET('val');
		$val = Path::encode(Path::toutf(strip_tags($val)));
		$art = Ans::GET('art');
		$md = Showcase::initMark($ans, $val, $art);

		$link = Ans::GET('seo','string','');
		if ($md['group']){
			foreach($md['group'] as $val => $one) break;
			$link = $link.'/'.$val;
		} else if($md['producer']){
			foreach($md['producer'] as $val => $one) break;
			$link = $link.'/'.$val;
		} else if($md['search']){
			$val = $md['search'];
			$link = $link.'/'.$val;
		}
		$ans['canonical'] = Seojson::getSite().'/'.$link;
		unset($ans['md']);
		unset($ans['m']);
		$seo = Load::loadJSON('-seo/?path='.$link);
		$ans += $seo;

		if (empty($ans['title']) || ($ans["Адрес"] != $link && empty($ans['json']))) {
			unset($ans['description']);
			unset($ans['keywords']);
			$ans['title']  =  $val;
			$group = Showcase::getGroup($val);
			if ($group) {
				$ans['title'] = $group['group'];
				if (isset($group['icon'])) $ans['image_src'] = $group['icon'];
			}
		}
		return Ans::ans($ans);
}], 'pos', [
	function (){
		echo 'producer/article/[item_nick]';
	}, 'seo', function($a, $seo, $producer, $article){
		$ans = array();
		$md = Showcase::initMark($ans, $producer, $article);
		$pos = Showcase::getModel($producer, $article);
		
		$producer = Path::toutf(strip_tags($producer));
		$article = Path::toutf(strip_tags($article));

		unset($ans['md']);
		unset($ans['m']);
		if (!$pos) {
			http_response_code(404);
			return Ans::err($ans,'Position not found');
		}
		$link = strip_tags(Ans::GET('seo'));
		$link = $link.'/'.urlencode($pos['producer_nick']).'/'.urlencode($pos['article_nick']);

		if (!empty($pos['Наименование'])) {
			$ans['title'] = $pos['Наименование'];
			if (Showcase::$conf['cleanname']) { //Если в наименовании нет артикула, добавляем
				$ans['title'] .= ' '. $pos['producer'].' '.$pos['article'];
			}
		} else $ans['title'] = $pos['producer'].' '.$pos['article'];
		

		if (!empty($pos['Описание'])) $ans['description'] = $pos['Описание'];
		
		//if (!empty($pos['Наименование'])) $ans['description'] = 'Купить '.$pos['Наименование'];
		//if (Showcase::$conf['cleanname']) { //Если в наименовании нет артикула, добавляем
		//	$ans['description'] .= $pos['producer'].' '.$pos['article'];
		//}

		$ans['canonical'] = Seojson::getSite().'/'.$link;
		
		if (isset($pos['images'][0])) {
			$ans['image_src'] = Seojson::getSite().'/-imager/?w=400&src='.$pos['images'][0];	
		}
		/*$seo = Load::loadJSON('~'.$link.'/seo.json');
		if ($seo) {
			$ans = array_merge($ans, $seo);
		}*/
		return Ans::ans($ans);
		
	},[
		function( ) {
			$ans = [
				'msg'=>'Не указан произодитель',
				'pos'=>[]

			];
			return Ans::err($ans);
		},
		[function ($a, $producer_nick, $article_nick, $item_nick = false) {
			$ans = array();

			Showcase::initMark($ans, $producer_nick, $article_nick);
			$producer_nick = Path::toutf(strip_tags($producer_nick));
			$article_nick = Path::toutf(strip_tags($article_nick));
			
			if ($item_nick) {
				$r = explode('&', $item_nick);
				$item_nick = array_shift($r);
				$catkit = array_map(function ($r){
					$r = explode(':', $r);
					$art = Path::encode($r[0]);
					if (isset($r[1])) {
						return $art.':'.Path::encode($r[1]);
					} else {
						return $art;
					}
				}, $r);
			} else {
				$catkit = [];
			}
			$ans['pos'] = Showcase::getModelShow($producer_nick, $article_nick, $item_nick, $catkit);
			if ($item_nick && !$ans['pos']) $ans['pos'] = Showcase::getModelShow($producer_nick, $article_nick, '', $catkit);
			
			$active = $ans['pos']['article'];

			if (Showcase::$conf['hiddenarticle'] && isset($ans['pos']['Наименование'])) {
				$active = $ans['pos']['Наименование'];
			}

			
			if (!$ans['pos']) {
				//$ans['breadcrumbs'][] = array('href'=>'producers','title'=>'Производители');
				//$ans['breadcrumbs'][] = array('href'=>'','title'=>$producer_nick,'href'=>$producer_nick);
				$ans['breadcrumbs'][] = array('active'=>true, 'title'=>$active);
				return Ans::err($ans);
			}
			
			$ans['breadcrumbs'][] = array('title'=>Showcase::$conf['title'],'href'=>'','add'=>':group');
			array_map(function($p) use (&$ans){
				$group = Showcase::getGroup($p);
				$ans['breadcrumbs'][] = array('title'=>$group['group'],'href'=>$p);
			}, $ans['pos']['path']);
			//$ans['breadcrumbs'][] = array('href'=>$producer_nick, 'title'=>$ans['pos']['producer']);
			$ans['breadcrumbs'][] = array('active'=>true, 'title'=>$ans['pos']['producer'].'&nbsp;'.$active);
			return Ans::ret($ans);

		}]
	]
], function (){
	echo 'catalog, price, search, groups';
});
