<?php
namespace akiyatkin\showcase;
use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\once\Once;
use infrajs\excel\Xlsx;
use infrajs\db\Db;
use infrajs\config\Config;

class Data {
	public static $timer;
	public static $timerprev;
	public static function timer($msg = 'метка') {
		$t =  microtime(true);
		if (!Data::$timer) Data::$timer = $t;
		if (!Data::$timerprev) Data::$timerprev = $t;		
		$begin = round(($t - Data::$timer) * 1000);
		$prev = round(($t - Data::$timerprev) * 1000);
		Data::$timerprev = $t;
		echo $begin.'-'.$prev.' '.$msg.'<br>'."\n";
	}
	public static $types = ['number','text','value'];
	public static function fetch($sql, $args = []) {
		$stmt = Db::stmt($sql);
		$stmt->execute($args);
		return $stmt->fetch();
	}
	public static function col($sql, $args = []) {
		$stmt = Db::stmt($sql);
		$stmt->execute($args);
		return $stmt->fetchColumn();
	}
	public static function lastId($sql, $args = []) {
		$db = &Db::pdo();
		$stmt = Db::stmt($sql);
		$stmt->execute($args);
		return $db->lastInsertId();
	}
	public static function fetchto($sql, $name, $args = []) { //Колонки в аргументах $func
		$db = &Db::pdo();
		$stmt = Db::stmt($sql);
		$stmt->execute($args);
		$list = array();
		while ($row = $stmt->fetch()) $list[$row[$name]] = $row;
		return $list;
	}
	public static function all($sql, $args = []) { //Колонки в аргументах $func
		$db = &Db::pdo();
		$stmt = Db::stmt($sql);
		$stmt->execute($args);
		return $stmt->fetchAll();
	}
	public static function exec($sql, $args = []) {
		$db = &Db::pdo();
		$stmt = Db::stmt($sql);
		$stmt->execute($args);
		return $stmt->rowCount();
	}
	public static function loadShowcaseConfig(){
		$opt = Load::loadJSON('~showcase.json');
		if(!$opt) $opt = [];
		$opt = $opt + array(
			'catalog'=>[],
			'justonevalue'=>[],
			'prices'=>[],
			'filters'=>[],
			'values'=>[]	
		);
		
		if (empty($opt['numbers'])) $opt['numbers'] = ['Цена'];
		if (empty($opt['texts'])) $opt['texts'] = ["Описание","Наименование"];

		return $opt;
	}
	public static function getOptions($part = false){
		$opt = Data::loadShowcaseConfig();
		Data::prepareOptionPart($opt['catalog']);
		Data::prepareOptionPart($opt['prices']);

		if ($part) return $opt[$part];
		return $opt;
	}
	public static function prepareOptionPart(&$list){
		foreach ($list as $name => $val) $list[$name]['isopt'] = true;
	}
	/**
	 * Массив поставщиков в формает fd (nameInfo) с необработанными данными из Excel (data)
	 **/
	public static function getFileList($folder)
	{
		return Once::func( function ($folder) {
			$list = array();

			if (!FS::is_dir($folder)) return $list;
			$order = 1;
			FS::scandir($folder, function ($file) use (&$list, $folder, &$order) {
				if ($file[0] == '.') return;
				if ($file[0] == '~') return;
				if (!FS::is_file($folder.$file)) return;
				
				$file = Path::toutf($file);
				$fd = Load::nameInfo($file);
				$fd['mtime'] = FS::filemtime($folder.$file);
				$fd['order'] = ++$order;
				$fd['size'] = round(FS::filesize($folder.$file)/1000);
				if (!in_array($fd['ext'], array('xlsx'))) return;
				$list[$fd['name']] = $fd;

			});
			return $list;
		},[$folder]);
		
	}

	public static function initProducer($value) {
		return Once::func( function ($value) {
			if (!$value) return null;
			$strid = 'producer_id';
			$strnick = 'producer_nick';
			$strval = 'producer';
			$table = 'showcase_producers';
			$nick = Path::encode($value);
			$id = Data::col('SELECT '.$strid.' from '.$table.' where '.$strnick.' = ?', [$nick]);
			if ($id) return $id;
			return Data::lastId(
				'INSERT INTO '.$table.' ('.$strval.','.$strnick.') VALUES(?,?)',
				[$value, $nick]
			);	
		}, [$value]);
	}
	
	public static function actionClearAll() {
		Data::exec('TRUNCATE `showcase_prices`');
		Data::exec('TRUNCATE `showcase_catalog`');
		
		Data::exec('TRUNCATE `showcase_producers`');
		Data::exec('TRUNCATE `showcase_articles`');
		Data::exec('TRUNCATE `showcase_groups`');
		Data::exec('TRUNCATE `showcase_props`');
		Data::exec('TRUNCATE `showcase_values`');
		
		Data::exec('TRUNCATE `showcase_models`');
		Data::exec('TRUNCATE `showcase_items`');
		Data::exec('TRUNCATE `showcase_mvalues`');
		Data::exec('TRUNCATE `showcase_mnumbers`');
		Data::exec('TRUNCATE `showcase_mtexts`');
		
	}
	
	public static function initValue($value) {
		return Once::func( function ($value) {
			//if (!$value) return null;
			$strid = 'value_id';
			$strnick = 'value_nick';
			$strval = 'value';
			$table = 'showcase_values';
			$nick = Path::encode($value);
			$id = Data::col('SELECT '.$strid.' from '.$table.' where '.$strnick.' = ?', [$nick]);
			if ($id) return $id;
			return Data::lastId(
				'INSERT INTO '.$table.' ('.$strval.','.$strnick.') VALUES(?,?)',
				[$value, $nick]
			);	
		}, [$value]);
	}
	public static function initProp($prop, $type = false) {
		if ($type == 'article') return false;
		if ($type) $type = ["value"=>1, "number"=>2, "text"=>3][$type];
		return Once::func( function ($prop) use ($type) {
			if (!$prop) return null;
			
			$nick = Path::encode($prop);

			$row = Data::fetch('SELECT prop_id, type from showcase_props where prop_nick = ?', [$nick]);
			
			if ($row) {
				if ($type && $type != $row['type']) {
					if ($row['type'] == 'number') { //Удаляем старые значения
						Data::exec('DELETE FROM showcase_mnumbers WHERE prop_id = ?', [$row['prop_id']]);
					} else if ($row['type'] == 'text') {
						Data::exec('DELETE FROM showcase_mtexts WHERE prop_id = ?', [$row['prop_id']]);
					} else {
						Data::exec('DELETE FROM showcase_mvalues WHERE prop_id = ?', [$row['prop_id']]);
					}
					Data::exec('UPDATE showcase_props SET type = ? WHERE prop_id = ?', [$type, $row['prop_id']]);
				}
				return $row['prop_id'];
			}
			if (!$type) return false;
			return Data::lastId(
				'INSERT INTO showcase_props (prop, prop_nick, type) VALUES(?,?,?)',
				[$prop, $nick, $type]
			);	
		}, [$prop]);
	}
	
	public static function checkType($prop) {
		$options = Data::loadShowcaseConfig();
		$prop = Path::encode($prop);
		if ($prop == 'Артикул') return 'article';
		if (in_array($prop, $options['numbers'])) return 'number';
		if (in_array($prop, $options['texts'])) return 'text';
		if (in_array($prop, $options['values'])) return 'value';
		foreach ($options['filters'] as $row) {
			if (in_array($prop, $row)) return 'value';
		}
		return 'value';
	}
	public static function clearProp($prop_id) {
		Data::exec('DELETE FROM `showcase_mvalues` where prop_id = ?', [$prop_id]);
		Data::exec('DELETE FROM `showcase_mnumbers` where prop_id = ?', [$prop_id]);
		Data::exec('DELETE FROM `showcase_mtexts` where prop_id = ?', [$prop_id]);
	}
	public static function init() {
		$props = Data::fetchto("SELECT prop_id, prop, type from showcase_props", "prop");
		$options = Data::loadShowcaseConfig();
		//1 value, 2 number, 3 text
		foreach ($props as $prop => $p) {
			$type = Data::checkType($p['prop']);
			if ($p['type'] == 1 && $type == 'value') continue;
			if ($p['type'] == 2 && $type == 'number') continue;
			if ($p['type'] == 3 && $type == 'text') continue;
			//По умолчанию свойство считается values

			Data::initProp($prop, $type);
			Data::clearProp($p['prop_id']);

		}
		
	}
	public static function fileType($src) {
		$fd = Load::pathInfo($src);
		if (in_array($fd['ext'], array('png', 'gif', 'jpg'))) return 'images';
		if (in_array($fd['ext'], array('html', 'tpl', 'mht', 'docx'))) return 'texts';
		if (in_array($fd['ext'], array('avi','ogv','mp4','swf'))) return 'videos';
		return 'files';
	}
	public static function removeFiles($producer){
		if ($producer) {
			$producer_id = Data::initProducer($producer);
			foreach (Data::$files as $type) {
				$prop_id = Data::initProp($type, 'value'); //Удаляем все параметры связаные с файлами images, files, texts
				$r = Data::exec('DELETE mv FROM showcase_mvalues mv, showcase_models m
					WHERE m.model_id = mv.model_id and m.producer_id = ?
					and mv.prop_id = ?', [$producer_id, $prop_id]);	
			}
		} else {
			foreach (Data::$files as $type) {
				$prop_id = Data::initProp($type, 'value'); //Удаляем все параметры связаные с файлами images, files, texts
				Data::exec('DELETE FROM `showcase_mvalues` where prop_id = ?', [$prop_id]);	
			}
		}
		
	}
	public static function applyIllustracii($producer) {
		$prop_id = Data::initProp('Иллюстрации','value');
		if ($producer) {
			$producer_id = Data::initProducer($producer);
			$images = Data::all('SELECT v.value_id, v.value, mv.model_id, mv.item_num FROM showcase_mvalues mv
				RIGHT JOIN showcase_props p on mv.prop_id = p.prop_id
				RIGHT JOIN showcase_models m on m.model_id = mv.model_id and m.producer_id = ?
				RIGHT JOIN showcase_values v on v.value_id = mv.value_id
				where p.prop_id = ?
			',[$producer_id, $prop_id]);
		} else {
			$images = Data::all('SELECT v.value_id, v.value, mv.model_id, mv.item_num FROM showcase_mvalues mv
				RIGHT JOIN showcase_props p on mv.prop_id = p.prop_id
				RIGHT JOIN showcase_values v on v.value_id = mv.value_id
				where p.prop_id = ?
			',[$prop_id]);
		}
		
		
		

		$prop_id = Data::initProp('images','value');
		foreach ($images as $pos) {
			Data::exec(
				'INSERT INTO showcase_mvalues (model_id, item_num, prop_id, value_id) VALUES(?,?,?,?)',
				[$pos['model_id'], $pos['item_num'], $prop_id, $pos['value_id']]
			);
		}
		return sizeof($images);
	}
	public static function actionAddFiles($producer = false){
		/*Индексируем все файлы producer - article, папки files, images, в том числе опцию Файлы
		Очищаем в базе всю инфомацию о связях с файлами- значения пропов images, files, texts
		Бежим и вносим новые связи
		producer
			article
				files
				texts
				images

		*/
		return Once::func( function ($producer) {
			$producer_id = ($producer) ? Data::initProducer($producer): false; //Собираем файлы определённого производителя
			
			$list = [];
			Data::addFilesFaylov($list, $producer); //Файлы
			Data::addFilesFS($list, $producer);
			Data::addFilesSyns($list, $producer); //Синонимы Фото и Файл
			
			$db = &Db::pdo();
			$db->beginTransaction();
			Data::removeFiles($producer);



			$pid = [];
			foreach (Data::$files as $type) $pid[$type] = Data::initProp($type, 'value');

			$ans['Иллюстраций с прямым адресом'] = Data::applyIllustracii($producer);
			$ans['Иллюстраций на сервере'] = 0;
			$ans['Бесхозных файлов'] = 0;
			$ans['Моделей с иллюстрациями'] = 0;
			$ans['Прочих файлов на сервере'] = 0;
			foreach ($list as $prod => $arts) {
				$producer_id = Data::initProducer($prod);
				foreach ($arts as $art => $items) {
					

					$article_id = Data::col('SELECT article_id from showcase_articles where article_nick = ?', [$art]);
					
					if (!$article_id) {
						$altart = str_ireplace($prod, '', $art); //Удалили из артикула продусера
						$altart = Path::encode($altart);
						$article_id = Data::col('SELECT article_id from showcase_articles where article_nick = ?', [$altart]);

						if (!$article_id) {
							$ans['Бесхозных файлов']++;
							continue;//Имя файла как артикул не зарегистрировано, даже если удалить производителя
						}
					}
					$model_id = Data::col('SELECT model_id from showcase_models where producer_id = ? and article_id = ?',
						[$producer_id, $article_id]);
					if (!$model_id) {
						$ans['Бесхозных файлов']++;
						continue; //Арт есть, но видимо у другова производителя. Модель не найдена
					}

					$ans['Моделей с иллюстрациями']++;
					$values = [];
					foreach ($items as $item_num => $files) {
						foreach ($files as $src => $type) {
							$value_id = Data::initValue($src);
							if (isset($values[$value_id])) continue; //Дубли одного пути или похоже пути из-за Path encode путь может давайть одинаковый value_nick
							$values[$value_id] = true;
							$prop_id = $pid[$type];
							if ($type == 'images') $ans['Иллюстраций на сервере']++;
							else $ans['Прочих файлов на сервере']++;

							Data::exec(
								'INSERT INTO showcase_mvalues (model_id, item_num, prop_id, value_id) VALUES(?,?,?,?)',
								[$model_id, $item_num, $prop_id, $value_id]
							);

							//print_r([$producer_id, $article_id, $num, $value_id]);
							//echo $prod.':'.$art.':'.$num.' '.$type.':'.$src.'<br>';
						}
					}
				}
			}
			$db->commit();
			
			return $ans;
		},[$producer]);

	}
	public static function initArticle($value) {
		return Once::func( function ($value) {
			if (!$value) return null;
			$strid = 'article_id';
			$strnick = 'article_nick';
			$strval = 'article';
			$table = 'showcase_articles';
			$nick = Path::encode($value);
			$id = Data::col('SELECT '.$strid.' from '.$table.' where '.$strnick.' = ?', [$nick]);
			if ($id) return $id;
			return Data::lastId(
				'INSERT INTO '.$table.' ('.$strval.','.$strnick.') VALUES(?,?)',
				[$value, $nick]
			);	
		}, [$value]);
	}
	public static function addFilesFSproducer(&$list, $prod) {
		$dir = Showcase::$conf['dir'];
		if (!Path::theme($dir.$prod.'/')) return; //Подходят только папки
		

		if (in_array($prod,['articles','tables'])) return; //Относится к группам
		if (!isset($list[$prod])) $list[$prod] = array();
		FS::scandir($dir.$prod.'/', function ($fart) use ($dir, &$list, $prod) {
			$art = mb_strtolower($fart);
			if (!Path::theme($dir.$prod.'/'.$art.'/')) return; //Подходят только папки
			if (in_array($art, ['files','images','texts'])) return;
				
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];//Data::$files;
			FS::scandir($dir.$prod.'/'.$fart.'/', function ($file) use (&$list, $prod, $dir, $art, $fart) {
				$src = $dir.$prod.'/'.$fart.'/'.$file;
				$type = Data::fileType($src);
				$list[$prod][$art][0][$src] = $type;
			});	
		});
	
		$index = Data::getIndex($dir.$prod.'/images/',  array('jpg', 'png', 'jpeg'));
		foreach ($index as $art => $images) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			$images = array_fill_keys($images,'images');
			$list[$prod][$art][0] += $images;
		}
		$index = Data::getIndex($dir.$prod.'/files/');
		foreach ($index as $art => $files) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			foreach ($files as $src) {
				$list[$prod][$art][0][$src] = Data::fileType($src);
			}
			
		}
	}
	public static function addFilesFS(&$list, $producer) {
		if ($producer) {
			Data::addFilesFSproducer($list, $producer);
		} else {
			$dir = Showcase::$conf['dir'];
			FS::scandir($dir, function ($prod) use (&$list) {
				Data::addFilesFSproducer($list, $prod);
			});
		}
		foreach ($list as $prod => $arts) if (!$arts) unset($list[$prod]); 
	}
	public static function addFilesFaylov(&$list, $producer) {
		$dir = Showcase::$conf['dir'];
		//Можно ли привязать файлы к item. Да, только через свойства Файлы, Файл, Фото. Для связи достаточно item_num
		$fayliid = Data::initProp('Файлы', 'value');//Пути. Могут быть несколько (pr.producer, a.article, pr.item_num)
		if ($producer) {
			$producer_id = Data::initProducer($producer);
			$fayli = Data::all('SELECT pr.producer, a.article, mv.item_num, v.value from showcase_mvalues mv
				INNER JOIN showcase_values v ON v.value_id = mv.value_id
				INNER JOIN showcase_models m ON m.model_id = mv.model_id and m.producer_id = ?
				INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
				INNER JOIN showcase_articles a ON a.article_id = m.article_id
				WHERE mv.prop_id = ?',
			[$producer_id, $fayliid]);
		} else {
			$fayli = Data::all('SELECT pr.producer, a.article, mv.item_num, v.value from showcase_mvalues mv
				INNER JOIN showcase_values v ON v.value_id = mv.value_id
				INNER JOIN showcase_models m ON m.model_id = mv.model_id
				INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
				INNER JOIN showcase_articles a ON a.article_id = m.article_id
				WHERE mv.prop_id = ? ',
			[$fayliid]);	
		}
		
		foreach ($fayli as $fayl) {
			$prod = $fayl['producer'];
			$art = mb_strtolower($fayl['article']);
			$num = $fayl['item_num'];

			if (!isset($list[$prod])) $list[$prod] = array();
			if (!isset($list[$prod][$art])) $list[$prod][$art] = array();
			if (!isset($list[$prod][$art][$num])) $list[$prod][$art][$num] = array();

			if (preg_match('/http::/',$fayl['value'])) {
				$list[$prod][$art][$num][$src] = 'images';
				continue;
			} 
			$src = $dir.$fayl['value'];

			if (!Path::theme($src)) continue;

			
			if (FS::is_dir($src)) {
				$fs = [];
				FS::scandir($src, function($file) use(&$fs, $src) {
					if (!Path::theme($src.$file)) return;
					$fs[] = $src.$file;
				});
			} else {
				$fs = [$src];
			}
			foreach ($fs as $src) {
				$list[$prod][$art][$num][$src] = Data::fileType($src);	
			}
		}
	}
	public static function addFilesSyns(&$list, $producer) {
		//В list уже есть все файлы, нужно их привязать к артикулам на которые есть синонимы в Фото и Файл
		$fotoid = Data::initProp('Фото', 'value');//Имя файла который считать images. producer не отменяется
		
		//!!!!Имя файла может содержать имя производителя
		
		$rows = Data::all('SELECT pr.producer, a.article, mv.item_num, v.value from showcase_mvalues mv
			INNER JOIN showcase_values v ON v.value_id = mv.value_id
			INNER JOIN showcase_models m ON mv.model_id = m.model_id
			INNER JOIN showcase_articles a ON a.article_id = m.article_id
			INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
			WHERE prop_id = ?',[$fotoid]);
		
		$fotos = []; //[$producer][$fimage] = ['article', 'item_num'];
		
		foreach ($rows as $row) {
			if (!isset($fotos[$row['producer']])) $fotos[$row['producer']] = [];
			if (!isset($fotos[$row['producer']][$row['value']])) $fotos[$row['producer']][$row['value']] = [];
			$fotos[$row['producer']][$row['value']][] = $row;
		}

		
		foreach ($fotos as $prod => $artsyns) {
			foreach ($artsyns as $syn => $syns) {
				if (!isset($list[$prod][$syn][0])) continue;
				foreach ($list[$prod][$syn][0] as $src => $type) { //По синониму может быть несколько файлов
					foreach ($syns as $row) { //Один синоним может быть для нескольких позиций и каждый файл записываем в каждую позицию
						if ($type !== 'images') continue;
						$art = $art = mb_strtolower($row['article']);
						if (!isset($list[$prod][$art][$row['item_num']])) $list[$prod][$art][$row['item_num']] = [];
						$list[$prod][$art][$row['item_num']][$src] = $type;
					}
				}
			}
		}

		$faylid = Data::initProp('Файл', 'value');//Имя файла тип которого надо ещё определить.  producer не отменяется
		$rows = Data::all('SELECT pr.producer, a.article, mv.item_num, v.value from showcase_mvalues mv
			INNER JOIN showcase_values v ON v.value_id = mv.value_id
			INNER JOIN showcase_models m ON mv.model_id = m.model_id
			INNER JOIN showcase_articles a ON a.article_id = m.article_id
			INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
			WHERE prop_id = ?',[$faylid]);
		$fayls = [];
		
		foreach ($rows as $row) {
			if (!isset($fayls[$row['producer']])) $fayls[$row['producer']] = [];
			if (!isset($fayls[$row['producer']][$row['value']])) $fayls[$row['producer']][$row['value']] = [];
			$fayls[$row['producer']][$row['value']][] = $row;
		}
		foreach ($fayls as $prod => $artsyns) {
			foreach ($artsyns as $syn => $syns) {
				if (!isset($list[$prod][$syn][0])) continue;
				foreach ($list[$prod][$syn][0] as $src => $type) { //По синониму может быть несколько файлов
					foreach ($syns as $row) { //Один синоним может быть для нескольких позиций и каждый файл записываем в каждую позицию
						if (!isset($list[$prod][$row['article']][$row['item_num']])) $list[$prod][$row['article']][$row['item_num']] = [];
						$list[$prod][$row['article']][$row['item_num']][$src] = $type;
					}
				}
			}
		}
	}
	public static $files = ['texts', 'images', 'files', 'videos'];
	public static function getIndex($dir, $exts = false) {
		if (!Path::theme($dir)) return array();
		
		$list = array();
		Config::scan($dir, function ($src, $level) use (&$list, $exts) {
			$fd = Load::pathInfo($src);
			if ($exts && !in_array($fd['ext'], $exts)) return;
			$name = $fd['name'];
			$p = explode(', ',$name);
			foreach ($p as $name) {
				$name = preg_replace("/_\d*$/", '',$name);
				$name = preg_replace("/\s*\(\d*\)*$/", '',$name);
				$name = mb_strtolower(Path::encode($name));
				if (!$name) continue;
				if (empty($list[$name])) $list[$name] = array();
				$list[$name][] = $src;
			}
		}, true);
		return $list;
	}
	public static function getGroups($group_nick = false) {
		$root = Once::func(function (){
			$list = Data::fetchto('SELECT g.group_nick, g.group, c.name as catalog, count(*) as count, max(model_id) as notempty, g2.group_nick as parent_nick FROM showcase_groups g
			left JOIN showcase_models m ON g.group_id = m.group_id
			inner JOIN showcase_catalog c ON c.catalog_id = g.catalog_id
			left JOIN showcase_groups g2 ON g2.group_id = g.parent_id
			GROUP BY group_nick
			order by g.group_id','group_nick');
			
			$parents = [];
			foreach ($list as $i=>&$group) {
				if(!$group['notempty']) $group['count'] = 0;
				unset($group['notempty']);
				if (empty($parent[$group['parent_nick']])) $parent[$group['parent_nick']] = [];
				$parents[$group['parent_nick']][] = &$group;
			}

			
			$p = null;
			foreach ($list as $i => &$group) {
				if (!isset($parents[$group['group_nick']])) continue;
				$group['childs'] = $parents[$group['group_nick']];
			}
			$childs = $parents[(string)null];
			$root = $childs[0];
			
			Xlsx::runGroups($root, function &(&$group, $i, $parent) {
				$r = null;
				if (!$parent) return $r;
				if (!isset($parent['path'])) {
					$group['path'] = [$group['group_nick']];
					return $r;
				}
				$group['path'] = $parent['path'];
				$group['path'][] = $group['group_nick'];
				return $r;
			});
			return $root;
		});
		if ($group_nick) {
			return Xlsx::runGroups($root, function &($group) use ($group_nick){
				if ($group['group_nick'] == $group_nick) return $group;
				$r = null;
				return $r;

			});
		}
		return $root;
	}
	public static function getProducers() {
		
		$list = Data::all('SELECT p.producer, p.producer_nick, count(*) as `count`, c.name as catalog from showcase_models m
			INNER JOIN showcase_producers p on p.producer_id = m.producer_id
			INNER JOIN showcase_catalog c on c.catalog_id = m.catalog_id
			GROUP BY producer
			order by m.producer_id');
		return $list;
	}
	public static function getModels() {
		
		$list = Data::all('SELECT p.producer, p.producer_nick, g.group, g.group_nick, a.article_nick, a.article, count(*) as `count`, c.name  as catalog, 
			n.number as Цена,
			iv.value as img
			FROM showcase_models m
			INNER JOIN showcase_producers p on p.producer_id = m.producer_id
			INNER JOIN showcase_groups g on g.group_id = m.group_id
			LEFT JOIN showcase_props ps on (ps.prop_nick = "Цена")
			LEFT JOIN showcase_mnumbers n on (n.model_id = m.model_id and n.prop_id = ps.prop_id)

			LEFT JOIN showcase_props ps2 on (ps2.prop_nick = "images")
			LEFT JOIN showcase_mvalues im on (im.model_id = m.model_id and im.prop_id = ps2.prop_id)
			LEFT JOIN showcase_values iv on (iv.value_id = im.value_id)
			
			LEFT JOIN showcase_items i on i.model_id = m.model_id
			INNER JOIN showcase_articles a on a.article_id = m.article_id
			INNER JOIN showcase_catalog c on c.catalog_id = m.catalog_id
			GROUP BY m.model_id
			order by m.model_id');
		return $list;
	}
}