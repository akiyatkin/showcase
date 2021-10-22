<?php
namespace akiyatkin\showcase\submit;
use infrajs\db\Db;
use infrajs\path\Path;
use akiyatkin\fs\FS;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\cache\CacheOnce;


class Submit {
	use CacheOnce; //once($name, $args, $fn) , $once
	// public static function move($src) {
	// 	return Submit::once('move', [], function () {
	// 	})
	// }

	public static $savedir = 'data/clearfreefiles';
	public static $basedir = 'data/';
	//Путь отсекаемый, а всё что после в $src повторяется внутрь после $savedir
	public static function move($src) {
		
	}
}