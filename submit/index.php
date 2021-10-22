<?php
use akiyatkin\meta\Meta;
use infrajs\user\User;
use akiyatkin\fs\FS;
use akiyatkin\showcase\submit\Submit;


use infrajs\db\Db;
use akiyatkin\showcase\api2\API;
use akiyatkin\showcase\Catkit;
use infrajs\path\Path;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\load\Load;
use infrajs\config\Config;

use infrajs\event\Event;
use infrajs\access\Access;
use infrajs\excel\Xlsx;

use infrajs\rubrics\Rubrics;
use infrajs\layer\seojson\Seojson;




$meta = new Meta();






$meta->addAction('clearfreefiles', function () {
	/*
		Создаём папку clearfreefiles/data/ 
		Туда повторяя структуру складываем найденные файлы
	*/
	
	
	/*
	"icons":"~catalog2/icons/",
	"groups":"~catalog2/groups/",
	"folders":"~catalog2/folders/",

	"images":"",
	"texts":"",
	"files":"",
	"videos":"",
	"slides":"",
	*/

	//Собираем все пути из базы данных
	//public static $files = ['texts', 'images', 'folders', 'videos','slides','files'];
	//$props = ['folders', 'images', 'texts', 'slides', 'files'];

	$prop_id = Data::initProp('folders', 'text');
	$folders = Db::colAll('SELECT DISTINCT text from showcase_iprops where prop_id = :prop_id', [':prop_id' => $prop_id]);

	$list = [];
	foreach (Data::$files as $type) {
		if ($type == 'folders') continue;
		$prop_id = Data::initProp($type, 'text');
		$l = Db::colAll('SELECT DISTINCT text from showcase_iprops where prop_id = :prop_id', [':prop_id' => $prop_id]);
		$list = array_merge($list, $l);
	}

	//icon
	$l = Db::colAll('SELECT icon from showcase_groups where icon is not null');
	$list = array_merge($list, $l);

	//logo
	$l = Db::colAll('SELECT logo from showcase_producers where logo is not null');
	$list = array_merge($list, $l);

	$list = array_unique($list);

	foreach ($folders as $folder) {
		foreach ($list as $k => $file) {
			if (strpos($file, $folder) === 0) {
				unset($list[$k]);
			}
		}
	}

	// echo '<pre>';
	// print_r($folders);
	// print_r($list);
	// exit;



	//Папки производителей с подпапками артикулами
	$dirs = [
		Showcase::$conf['folders'],
		Showcase::$conf['images'], 
		Showcase::$conf['texts'], 
		Showcase::$conf['files'],
		Showcase::$conf['videos'],
		Showcase::$conf['slides']
	];
	$files = [];
	foreach ($dirs as $dir) {
		if (!$dir) continue;

		Config::scans($dir, function ($src) use (&$files) {
			$files[] = Path::theme($src);
		}, true);		
	}
	$this->ans['res'] = [];
	$this->ans['res']['Найдено файлов'] = sizeof($files);
	
	foreach ($files as $i => $file) {
		if (in_array($file, $list)) {
			unset($files[$i]);
		} else {
			foreach ($folders as $folder) {
				if (strpos($file, $folder) === 0) {
					unset($files[$i]);
				}
			}	
		}
	}
	
	$this->ans['res']['Используется файлов'] = sizeof($list);
	$this->ans['res']['Используется папок'] = sizeof($folders);
	$this->ans['res']['Подготовлено для перемещения'] = sizeof($files);
	//Переместить все файлы

	if (sizeof($files)) {
		if (is_dir('data/clearfreefiles/')) return $this->err('Сохраните себе резервную копию прошлой обработки и удалить папку с сервера. Папка data/clearfreefiles/');
		mkdir('data/clearfreefiles/', 0777);
		foreach ($files as $i => $file) {
			$newfile = 'data/clearfreefiles/'.$file;
			$dir = dirname($newfile);
			if (!is_dir($dir)) mkdir($dir,0777,TRUE);	
			rename($file, $newfile);
		}
	}
	$this->ans['res']['Перемещено файлов'] = sizeof($files);
	$this->ans['res']['Файлов осталось'] = $this->ans['res']['Найдено файлов'] - $this->ans['res']['Перемещено файлов'];
	//Удалить пустые папки

	$dirs = [
		Showcase::$conf['folders'],
		Showcase::$conf['images'], 
		Showcase::$conf['texts'], 
		Showcase::$conf['files'],
		Showcase::$conf['videos'],
		Showcase::$conf['slides']
	];
	$folders = [];
	foreach ($dirs as $dir) {
		if (!$dir) continue;
		Config::scans($dir, function ($src) use (&$folders) {
			$folders[] = Path::theme($src);
		});
	}
	rsort($folders);
	$this->ans['res']['Папок на проверку наличия файлов'] = sizeof($folders);
	$counter = 0;
	foreach ($folders as $folder) {
		$r = @rmdir($folder); //Пустые папки удалятся
		if ($r) $counter++;
	}
	$this->ans['res']['Удалено пустых папок'] = $counter;
	return $this->ret();
});


$meta->addArgument('token');
$meta->addVariable('user', function () {
	extract($this->gets(['token']), EXTR_REFS);
	$user = User::fromToken($token);
	if (!$user) $this->err('meta.badrequest');
	if (!$user['admin']) $this->err('meta.forbidden');
	return $user;
});


if (!isset($_GET['token'])) {
	$token = empty($_COOKIE['token']) ? '' : $_COOKIE['token'];
	header('Location: ?token='.$token);
}

$r = $meta->init([
	'handlers' => ['user'],
	'name'=>'showcase',
	'base'=>'-showcase/submit/'
]);

return $r;
