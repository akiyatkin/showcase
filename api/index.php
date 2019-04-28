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
	
	$ans['menu'] = Load::loadJSON(Showcase::$conf['menu']);
	$ans['list'] = $list;

	$conf = Showcase::$conf;
	$ans['breadcrumbs'][] = array('main'=>true, 'href'=>'','title'=>'Главная','add'=>'group');
	$ans['breadcrumbs'][] = array('href'=>'','title'=>$conf['title'],'add'=>'group');
	$ans['breadcrumbs'][] = array('active'=>true, 'href'=>'producers','title'=>'Производители');
	return Ans::ret($ans);

	},"seo", function(){
		$ans = [];
		$link = Ans::GET('seo');
		$link = $link.'/producers';
		$seofile = Showcase::$conf['grouparticles'].'producers.json';
		if (Path::theme($seofile)) {
			$ans['external'] = $seofile;
		} else {
			$ans['title'] = 'Производители';
			$seofile = Showcase::$conf['grouparticles'].'seo.json';
			if (Path::theme($seofile)) $ans['external'] = $seofile;
		}
		$ans['canonical'] = View::getPath().$link;
		return Ans::ans($ans);	
}], 'search', [function (){
		$val = Ans::GET('val');
		$val = Path::encode(Path::toutf(strip_tags($val)));
		$art = Ans::GET('art');

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

		$src  =  Rubrics::find(Showcase::$conf['grouparticles'], $ans['title']);
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

		$link = $_GET['seo'];
		
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

		unset($ans['md']);
		unset($ans['m']);
		$seosrc = Showcase::$conf['grouparticles'].Path::encode(Showcase::$conf['title']).'.json';
		Path::theme($seosrc);
		if ($val) {
			$seofile = Showcase::$conf['grouparticles'].$val.'.json';
			if (Path::theme($seofile)) {
				$ans['external']  =  Showcase::$conf['grouparticles'].$val.'.json';
			} else {
				if (Path::theme($seosrc)) $ans['external']  =  Showcase::$conf['grouparticles'].Path::encode(Showcase::$conf['title']).'.json';
				$title = $val;
				$group = Showcase::getGroup($val);
				if ($group) $title = $group['group'];
				$ans['title']  =  $title;
			}
		} else {
			if (Path::theme($seosrc)) $ans['external']  =  Showcase::$conf['grouparticles'].Path::encode(Showcase::$conf['title']).'.json';
		}
		$ans['canonical']  =  View::getPath().$link;
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
		$link = $link.'/'.urlencode($pos['producer']).'/'.urlencode($pos['article']);

		$seosrc = Showcase::$conf['grouparticles'].Path::encode(Showcase::$conf['title']).'.json';
		if (Path::theme($seosrc)) $ans['external'] = $seosrc;
		$ans['title'] = $pos['producer'].' '.$pos['article'];
		if(!empty($pos['Наименование'])) $ans['title'] = $pos['Наименование'].' '.$ans['title'];
		$ans['canonical'] = View::getPath().$link;
		
		if (isset($pos['images'][0])) {
			$ans['image_src'] = '/-imager/?w=400&src='.$pos['images'][0];	
		}
		/*$seo = Load::loadJSON('~'.$link.'/seo.json');
		if ($seo) {
			$ans = array_merge($ans, $seo);
		}*/
		return Ans::ans($ans);
		
	},[
		function( ) {
			echo 'article';
		},
		[function ($a, $producer_nick, $article_nick, $item_nick = false) {
			$ans = array();
			Showcase::initMark($ans, $producer_nick, $article_nick);
			$producer_nick = Path::toutf(strip_tags($producer_nick));
			$article_nick = Path::toutf(strip_tags($article_nick));
			$ans['pos'] = Showcase::getModelShow($producer_nick, $article_nick, $item_nick);
			
			$active = $article_nick;
			if (Showcase::$conf['hiddenarticle']) {
				if(isset($pos['Наименование'])) {
					$active = $pos['Наименование'];
				} else {
					$active = $pos['article'];		
				}
			}
			
			if (!$ans['pos']) {
				$ans['breadcrumbs'][] = array('href'=>'producers','title'=>'Производители');
				$ans['breadcrumbs'][] = array('href'=>'','title'=>$producer_nick,'href'=>$producer_nick);
				$ans['breadcrumbs'][] = array('active'=>true, 'title'=>$active);
				return Ans::err($ans);
			}
			
			
			array_map(function($p) use (&$ans){
				$group = Showcase::getGroup($p);
				$ans['breadcrumbs'][] = array('title'=>$group['group'],'href'=>$p);
			}, $ans['pos']['path']);
			$ans['breadcrumbs'][] = array('href'=>$producer_nick, 'title'=>$ans['pos']['producer']);
			$ans['breadcrumbs'][] = array('active'=>true, 'title'=>$active);
			return Ans::ret($ans);

		}]
	]
], function (){
	echo 'catalog, price, search, groups';
});
