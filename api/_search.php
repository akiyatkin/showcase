<?php

use infrajs\load\Load;
use infrajs\rubrics\Rubrics;
use akiyatkin\showcase\Showcase;
use infrajs\ans\Ans;

$md = Showcase::initMark($ans);

$ans['page'] = Ans::GET('p','integer',1);
$count = Ans::GET('count','integer',0);
if ($count) $md['count'] = $count;
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

$groups = array_keys($md['group']);
$group_nick = null;
foreach ($groups as $group_nick) break;
$group = Showcase::getGroup($group_nick);

$ans['showlist'] = Ans::GET('showlist','bool', !empty($group['showcase']['showlist']));

Showcase::search($md, $ans, $ans['page'], $ans['showlist']);

$src  =  Rubrics::find(Showcase::$conf['groups'], $ans['title']);
if ($src) {
	$ans['textinfo']  =  Rubrics::info($src); 
	$ans['text']  =  Load::loadTEXT('-doc/get.php?src='.$src);//Изменение текста не отражается как изменение каталога, должно быть вне кэша
}
header('Cache-Control: no-cache, max-age=0, must-revalidate');
return Ans::ret($ans);