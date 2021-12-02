<?php
namespace akiyatkin\showcase\apin;
use akiyatkin\fs\FS;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\api2\API as GAPI;
use infrajs\path\Path;
use infrajs\load\Load;
use infrajs\db\Db;
use infrajs\config\Config;
use infrajs\cache\CacheOnce;

class API {
	use CacheOnce;
	public static function getArticles($producer_nick) {
		//images, folders
		return static::once('getArticles',[$producer_nick], function ($producer_nick) {
			$prop_idf = Data::initProp('Файлы','text'); //Файлы - пути до папки или файла от [folders], от корня сайта, от [tables], может быть http://
			$prop_idi = Data::initProp('Иллюстрации','text'); //Иллюстрации - считаются адресом до картинки "как есть" от корня или http
			$prop_idn = Data::initProp('Файл','text'); //Файл - имя файла который подойдёт при поиске search или папка артикула
			$prop_idm = Data::initProp('Фото','text'); //Файл - имя файла который подойдёт при поиске search или папка артикула
			
			$articles = Db::all('SELECT m.model_id, m.producer_id, i.item_num, m.article_nick, 
				idf.text as "idfv",
				idi.text as "idiv",
				idn.text as "idnv",
				idm.text as "idmv"
				 from showcase_models m 
				left join showcase_items i on i.model_id = m.model_id
				left join showcase_producers p on p.producer_id = m.producer_id
				left join showcase_iprops idf on (idf.model_id = m.model_id and idf.item_num = i.item_num  and idf.prop_id = :prop_idf)
				left join showcase_iprops idi on (idi.model_id = m.model_id and idi.item_num = i.item_num  and idi.prop_id = :prop_idi)
				left join showcase_iprops idn on (idn.model_id = m.model_id and idn.item_num = i.item_num  and idn.prop_id = :prop_idn)
				left join showcase_iprops idm on (idm.model_id = m.model_id and idm.item_num = i.item_num  and idm.prop_id = :prop_idm)
				where p.producer_nick = :producer_nick
			', [
				':prop_idf' => $prop_idf,
				':prop_idi' => $prop_idi,
				':prop_idn' => $prop_idn,
				':prop_idm' => $prop_idm,
				':producer_nick' => $producer_nick
			]);

			foreach ($articles as &$pos) {
				$pos['idnv'] = Path::encode($pos['idnv']);
				$pos['idmv'] = Path::encode($pos['idmv']);
				$pos['idfv'] = API::getSrcFromValue($pos['idfv']);
				$pos['idiv'] = API::getSrcFromValue($pos['idiv']);
			}
			return $articles;
		});
	}
	public static function getProducers() {
		//images, folders
		return static::once('getProducers',[], function () {
			$prods = [];
			
			foreach (Data::$files as $type) {
				FS::scandir(Showcase::$conf[$type], function ($proddir, $dir) use (&$prods) {
					if (!FS::is_dir($dir.$proddir)) return;
					$producer_nick = Path::encode($proddir);
					$prods[$producer_nick] = ['proddir' => $proddir];
				});
			}
			FS::scandir(Showcase::$conf['folders'], function ($proddir, $dir) use (&$prods) {
				$producer_nick = Path::encode($proddir);
				$prods[$producer_nick] = ['proddir' => $proddir];
			});
			$list = Db::colAll('SELECT producer_nick FROM showcase_producers');
			foreach($list as $producer_nick) {
				if (isset($prods[$producer_nick])) continue;
				$prods[$producer_nick] = ['proddir' => false];	
			}
			return $prods;
		});
	}
	public static function scanListReq($src, $exts, &$list) {
		$src = Path::theme($src);
		if (!$src) return;

		Config::scan($src, function ($src) use (&$list, $exts) {
			$info = Load::srcInfo($src);
			if (!in_array($info['ext'], $exts)) return;
			$file = $info['name'];

			$s = explode(',', $file);

			foreach ($s as $search) {
				$search = Path::encode($search);
				if (!$search) continue;
				$list[$src.$search] = [
					'src' => $src,
					'search' => $search,
					'file' => $info['file']
				];
			}
		}, true);
	}
	public static function scanDir($src, $exts, &$list, $search = false) {
		if (!$exts) {
			$list[$src] = [ //Это папка
				'src' => $src,
				'search' => $search,
				'file' => $src
			];
		}
		FS::scandir($src, function ($file, $dir) use (&$list, $search, $exts) {
			$ext = Path::getExt($file);
		 	if (!in_array($ext, $exts)) return;
			$list[$dir.$file] = [
				'src' => $dir.$file,
				'search' => $search,
				'file' => $file
			];
		});
	}
	public static function scanDirs($src, $exts, &$list) {
		if (!Path::theme($src)) return;
		FS::scandir($src, function ($file, $dir) use (&$list, $exts) {
			if (!FS::is_dir($dir.$file)) return;
			if (in_array($file, Data::$files)) return;
			$file = Load::nameInfo($file)['file'];
			$s = explode(',', $file);
			foreach ($s as $search) {
				$search = Path::encode($search);
				API::scanDir($dir.$file.'/', $exts, $list, $search);
			}
			
		});
	}
	public static function getSrcFromValue($v) {
		$res = [];
		if (!$v) return $res;
		$r = explode(',', $v);
		foreach($r as $value) {
			$value = trim($value);

			if (preg_match('/^http/', $value)) {
				$src = $value;
			} else {
				$src = Path::theme(Showcase::$conf['folders'].$value);	
			}
			if (!$src) $src = Path::theme($value);
			if (!$src) $src = Path::theme(Showcase::$conf['tables'].$value);
			if (!$src) continue;
			$res[] = $src;
		}
		return $res;
	}
	public static function getFiles($producer_nick, $type = 'images') {
		$proddir = API::getProducers()[$producer_nick]['proddir'];
		$list = [];
		if (!$proddir) return $list;
		$proddir = $proddir.'/';
		
		$types = Data::exts($type);
		
		// catalog/type[images]
		API::scanListReq(Showcase::$conf[$type].$proddir, $types, $list);
		
		if (!in_array($type, ['slides'])) {
			// catalog/folders/Kemppi/
			API::scanDirs(Showcase::$conf['folders'].$proddir, $types, $list);

			// catalog/folders/Kemppi/folders/
			$src = Showcase::$conf['folders'].$proddir.'folders/';
			if (Path::theme($src)) {
			 	FS::scandir($src, function ($file, $dir) use (&$list, $types) {
			 		API::scanDirs($dir.$file.'/', $types, $list);
			 	});
			}
		}
		
		// catalog/folders/Kemppi/type[images]
		API::scanListReq(Showcase::$conf['folders'].$proddir.$type.'/', $types, $list);
		//array_multisort(array_column($list, 'file'), SORT_ASC, $list);
		$search = [];
		foreach ($list as $f) {
			if (empty($search[$f['search']])) $search[$f['search']] = [];
			$search[$f['search']][$f['src']] = $f;
		}
		
		return $search;
	}
	public static function apply($producer_nick, $type) {
		$files = API::getFiles($producer_nick, $type);
		$types = Data::exts($type);
		$poss = API::getArticles($producer_nick);
		$res = [];
		$empty = [];

		foreach ($files as $search => $file) {
			$psearch = str_ireplace(mb_strtolower($producer_nick), '', $search); //Удалили из артикула продусера
			$psearch = Path::encode($psearch);
			if ($psearch == $search) continue;
			if (!isset($files[$psearch])) $files[$psearch] = [];
			$files[$psearch] = array_merge($files[$psearch], $files[$search]);
		}



		foreach ($poss as $pos) {			
			$r = ['pos' => $pos, 'files' => []];
			$search = $pos['article_nick'];
			if (isset($files[$search])) {
				$r['files'] = array_merge($r['files'], $files[$search]);
			}
			if ($type != 'slides') {
				$search = $pos['idfv'];//Файлы
				if ($search) {
					foreach ($search as $src) {
						$info = Load::srcInfo($src);
						if (FS::is_dir($src)) {
							API::scanDir($src, $types, $r['files']);
						} else {
							if (!in_array($info['ext'], $types)) continue;
							$r['files'][$src] = [
								'src' => $src,
								'file' => $info['file']
							];	
						}
					}
				}
			}
			if ($type == 'images') {
				$search = $pos['idiv'];//Иллюстрации
				if ($search) {
					foreach ($search as $src) {
						$info = Load::srcInfo($src);
						$r['files'][$src] = [
							'src' => $src,
							'file' => $info['file']
						];
					}
				}
			}
			$search = $pos['idnv'];
			if (isset($files[$search])) {
				$r['files'] = array_merge($r['files'], $files[$search]);
			}
			$search = $pos['idmv'];
			if (isset($files[$search])) {
				$r['files'] = array_merge($r['files'], $files[$search]);
			}
			array_multisort(array_column($r['files'], 'file'), SORT_ASC, $r['files']);
			$r['files'] = array_column($r['files'], 'src');
			if ($r['files']) $res[] = $r;
			else $empty[] = $pos;
		}

		$prop_id = Data::initProp($type, 'text');
		Db::start();
		Data::exec('DELETE mv FROM showcase_iprops mv, showcase_models m, showcase_producers pr
			WHERE m.model_id = mv.model_id and m.producer_id = pr.producer_id and pr.producer_nick = :producer_nick
			and mv.prop_id = :prop_id', [
				':producer_nick' => $producer_nick, 
				':prop_id' => $prop_id
			]);
		
		foreach ($res as $obj) {
			$pos = $obj['pos'];
			foreach($obj['files'] as $i => $src) {
				Db::exec('INSERT INTO showcase_iprops (model_id, item_num, prop_id, text, `order`) 
					VALUES(:model_id,:item_num, :prop_id, :src, :order)',
					[	':model_id' => $pos['model_id'], 
						':item_num' => $pos['item_num'], 
						':prop_id' => $prop_id, 
						':src' => $src,
						':order' => $i + 1
					]
				);	
			}
		}
		Db::commit();	

		$files = $files ? array_column(call_user_func_array('array_merge', array_values($files)), 'src') : [];
		$find = $res ? call_user_func_array('array_merge_recursive', $res)['files'] : [];
		if (!$find) return [];
		if (!$files) return [];
		$res = [
			'Всего файлов' => sizeof($files),
			'Файлов привязано' => sizeof($find),
			'Бесхозных файлов' => sizeof($files) - sizeof($find),
			'Бесхозные файлы' => array_diff($files, $find),
			'Всего позиций' => sizeof($poss),
			'Позиций с файлами' => sizeof($res),
			'Позиций без файлов' => sizeof($poss) - sizeof($res),
			'Позиции без файлов' => array_column($empty,'article_nick')
		];

		return $res;
	}
	public static function applyAll($producer_nick = false) {
		if ($producer_nick) $prods = [$producer_nick => true];
		else $prods = API::getProducers();

		//$types = array_diff(Data::$files, ['folders']);
		$types = Data::$files;
		$ress = []; 

		$ress['Производители'] = [];
		foreach ($prods as $producer_nick => $v) {
			$ress['Производители'][$producer_nick] = [];
			foreach ($types as $type) {
				$res = API::apply($producer_nick, $type);
				if ($res) $ress['Производители'][$producer_nick][$type] = $res;
			}
		}

		
		$ress['Иконки'] = Data::addFilesIcons(); //Нужны уже записанные картинки для позиций

		//GAPI::updateSearch();
		//$ress['Индекс быстрого поиска обновлён'] = 'ОК';
		return $ress;
	}
}