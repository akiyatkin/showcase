<?php
namespace akiyatkin\showcase;
use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\once\Once;
use infrajs\rubrics\Rubrics;
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
	public static $iexts = ['db',''];
	public static $types = ['number','text','value'];
	public static $files = ['texts', 'images', 'files', 'videos'];

	public static $images = ['png', 'gif', 'jpg', 'jpeg','svg'];
	public static $texts = ['html', 'tpl', 'mht', 'docx'];
	public static $videos = ['avi','ogv','mp4','swf'];
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
		$opt = Load::loadJSON(Showcase::$conf['jsonoptions']);
		if(!$opt) $opt = [];
		$opt += array(
			'catalog'=>[],
			'justonevalue'=>[],
			'numbers'=>[],
			'texts'=>[],
			'columns'=>[],
			'prices'=>[],
			'props'=>[],
			'filters'=>[],
			'values'=>[]	
		);
		$opt['filters'] += ['buttons'=>[],'groups'=>[],'order'=>'name'];
		
		$opt['justonevalue'][] = 'Цена';
		$opt['justonevalue'] = array_unique($opt['justonevalue']);

		$opt['numbers'][] = 'Цена';
		$opt['numbers'] = array_unique($opt['numbers']);
		
		$opt['texts'][] = 'Описание';
		$opt['texts'][] = 'Наименование';
		$opt['texts'][] = 'Иллюстрации';
		foreach (Data::$files as $f) $opt['texts'][] = $f; //Все пути текстовые

		$opt['texts'] = array_unique($opt['texts']);

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
		foreach ($list as $name => $val) {
			$list[$name]['producer_nick'] = false;
			if (!isset($list[$name]['producer'])) {
				$list[$name]['producer'] = $name;
			}
			$list[$name]['producer_nick'] = Path::encode($list[$name]['producer']);
			$list[$name]['isopt'] = true;
		}
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
		
		if (in_array($prop, $options['filters']['groups'])) return 'value';
		if (in_array($prop, $options['filters']['buttons'])) return 'value';
		
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
		if (in_array($fd['ext'], Data::$images)) return 'images';
		if (in_array($fd['ext'], Data::$texts)) return 'texts';
		if (in_array($fd['ext'], Data::$videos)) return 'videos';
		return 'files';
	}
	public static function removeFiles($producer_nick){
		if ($producer_nick) {
			foreach (Data::$files as $type) {
				$prop_id = Data::initProp($type, 'text'); //Удаляем все параметры связаные с файлами images, files, texts
				$r = Data::exec('DELETE mv FROM showcase_mtexts mv, showcase_models m, showcase_producers pr
					WHERE m.model_id = mv.model_id and m.producer_id = pr.producer_id and pr.producer_nick = ?
					and mv.prop_id = ?', [$producer_nick, $prop_id]);	
			}
		} else {
			foreach (Data::$files as $type) {
				$prop_id = Data::initProp($type, 'text'); //Удаляем все параметры связаные с файлами images, files, texts
				Data::exec('DELETE FROM `showcase_mtexts` where prop_id = ?', [$prop_id]);	
			}
		}
		
	}
	public static function applyIllustracii($producer_nick) {
		$prop_id = Data::initProp('Иллюстрации','text');
		if ($producer_nick) {
			$images = Data::all('SELECT p.prop, mv.text, mv.model_id, mv.item_num FROM showcase_mtexts mv
				INNER JOIN showcase_models m on (m.model_id = mv.model_id)
				INNER JOIN showcase_producers pr on (m.producer_id = pr.producer_id and pr.producer_nick = ?)
				INNER JOIN showcase_props p on (mv.prop_id = p.prop_id and p.prop_id = ?)
			',[$producer_nick, $prop_id]);
			
		} else {
			$images = Data::all('SELECT mv.text, mv.model_id, mv.item_num FROM showcase_mtexts mv
				INNER JOIN showcase_props p on mv.prop_id = p.prop_id
				where p.prop_id = ?
			',[$prop_id]);
		}
		$prop_id = Data::initProp('images','text');
		foreach ($images as $pos) {
			Data::exec(
				'INSERT INTO showcase_mtexts (model_id, item_num, prop_id, `text`) VALUES(?,?,?,?)',
				[$pos['model_id'], $pos['item_num'], $prop_id, $pos['text']]
			);
		}
		return sizeof($images);
	}
	public static function actionAddFiles($producer_nick = false){
		/*$producer_nick = false;
		if ($name) {
			$options = Data::getOptions('catalog');
			if (isset($options[$name]['producer'])) {
				$producer_nick = $options[$name]['producer'];	
			} else {
				$producer_nick = $name;
			}
		}*/
		/*Индексируем все файлы producer_nick - article, папки files, images, в том числе опцию Файлы
		Очищаем в базе всю инфомацию о связях с файлами- значения пропов images, files, texts
		Бежим и вносим новые связи
		producer_nick
			article
				files
				texts
				images

		*/

		return Once::func( function ($producer_nick) {
			$producer_id = Data::col('SELECT producer_id FROM showcase_producers where producer_nick = ?', [$producer_nick]);
			
			//$producer_id = ($producer_nick) ? Data::initProducer($producer_nick): false; //Собираем файлы определённого производителя
			$ans = [];
			if ($producer_nick) {
				if ($producer_id) {
					$ans['Поиск'] = 'По производителю '.$producer_nick;	
				} else {
					$ans['Поиск'] = 'Производитель не найден '.$producer_nick;
					return $ans;
				}
			} else {
				$ans['Поиск'] = 'По всем производителям';
			}
			$list = [];
			Data::addFilesFaylov($list, $producer_nick); //Файлы

			Data::addFilesFS($list, $producer_nick);
			

			Data::addFilesSyns($list, $producer_nick); //Синонимы Фото и Файл

		
			$db = &Db::pdo();
			$db->beginTransaction();
			Data::removeFiles($producer_nick);

			$pid = [];
			foreach (Data::$files as $type) $pid[$type] = Data::initProp($type, 'text');

			Data::applyIllustracii($producer_nick);
			$ans['Файлов'] = 0;
			$ans['Найденные файлы'] = [];
			$ans['Бесхозные файлы'] = array_reduce($list, function ($ak, $arts){
				return array_reduce($arts, function ($ak, $items) {
					return array_reduce($items, function ($ak, $items) {
						return array_merge($ak, $items);
					},$ak);		
				},$ak);
			}, []);
			$ans['Файлов'] = sizeof($ans['Бесхозные файлы']);
			
			

			foreach ($list as $prod => $arts) {
				//$producer_id = Data::initProducer($prod);
				$producer_id = Data::col('SELECT producer_id FROM showcase_producers where producer_nick = ?', [$prod]);
				foreach ($arts as $art => $items) {
					
					$altart = str_ireplace(mb_strtolower($prod), '', $art); //Удалили из артикула продусера
					$altart = Path::encode($altart);
					$model_id = Data::col('SELECT m.model_id
						FROM showcase_models m
						INNER JOIN showcase_articles a on (m.article_id = a.article_id and (a.article_nick = ? or a.article_nick = ?))
						where m.producer_id = ?', [$art, $altart, $producer_id]);
					if (!$model_id) continue;//Имя файла как артикул не зарегистрировано, даже если удалить производителя из артикула
					$values = [];
					$order = 0;
					foreach ($items as $item_num => $files) {
						foreach ($files as $src => $type) { //Все эти файлы относятся к найденной модели
							$order++;
							//$value_id = Data::initValue($src);
							$ans['Найденные файлы'][$src] = true;
							unset($ans['Бесхозные файлы'][$src]);
							/*if (isset($values[$value_id])) {
								continue; //Дубли одного пути или похоже пути из-за Path encode путь может давайть одинаковый value_nick в этом случае похожий путь будет проигнорирован
							}
							$values[$value_id] = true;*/
							$prop_id = $pid[$type];

							Data::exec(
								'INSERT INTO showcase_mtexts (model_id, item_num, prop_id, `text`, `order`) VALUES(?,?,?,?,?)',
								[$model_id, $item_num, $prop_id, $src, $order]
							);

							//print_r([$producer_id, $article_id, $num, $value_id]);
							//echo $prod.':'.$art.':'.$num.' '.$type.':'.$src.'<br>';
						}
					}
				}
			}

			$ans['Бесхозные файлы'] = array_keys($ans['Бесхозные файлы']);
			$ans['Найденные файлы'] = array_keys($ans['Найденные файлы']);
			Data::addFilesIcons();
			

			$db->commit();
			foreach($ans as $i=>$val){
				if (is_array($ans[$i]) && sizeof($ans[$i]) > 500) $ans[$i] = sizeof($ans[$i]);
			}
			return $ans;
		},[$producer_nick]);

	}
	public static function addFilesIcons() {
		return Once::func(function(){
			$images_id = Data::initProp('images');
			$root = Data::getGroups();
			$conf = Showcase::$conf;
			Xlsx::runGroups($root, function &(&$group) use ($images_id){
				//Ищим свою картинку
				$group['icon'] = null;
				
				$icon = Rubrics::find(Showcase::$conf['icons'], $group['group_nick'], Data::$images);
				if (!$icon) {
					$nick = Path::encode($group['group']);
					$icon = Rubrics::find(Showcase::$conf['icons'], $nick, Data::$images);
				}
				if ($icon) {
					$group['icon'] = $icon;
				} else {
					//Ищим картинку своей позииции
					$row = Data::fetch('SELECT g.group_nick, g.group, g.group_id, v.value as icon from showcase_groups g
						inner join showcase_models m on (m.group_id = g.group_id)	
						inner join showcase_mvalues mv on (mv.model_id = m.model_id and mv.prop_id = :images_id)
						left join showcase_values v on (v.value_id = mv.value_id)
						where g.group_nick = :group_nick
						', [':images_id' => $images_id, ':group_nick' => $group['group_nick']]);
					
					if ($row) {
						$group['icon'] = $row['icon'];
					} else {
						//Ищим картинку ближайшей вложенной группы
						if (isset($group['childs'])) {
							foreach ($group['childs'] as $child) {
								if ($child['icon']) {
									$group['icon'] = $child['icon'];
									break;
								}
							}
						}
					}
				}
				
				Data::exec('UPDATE showcase_groups SET icon = ? WHERE group_nick = ?',
					[$group['icon'], $group['group_nick']]
				);
				
				$r = null;
				return $r;
			}, true);


			$list = Data::all('SELECT producer_nick FROM showcase_producers');
			foreach ($list as $k => $prod) {
				$list[$k]['logo'] = null;
				//Посмотрели в иконках
				$logo = Rubrics::find($conf['icons'], $prod['producer_nick'], Data::$images);
				if ($logo) {
					$list[$k]['logo'] = $logo;
					continue;
				} 
				//Посмотрели в папках с файлами
				$dir = Rubrics::find($conf['folders'], $prod['producer_nick'], 'dir');
				$images = FS::scandir($dir, function ($file) use (&$conf, &$prod, $dir) {
					$fd = Load::nameInfo($file);
					if (in_array($fd['ext'], Data::$images)) return true;
					return false;
				});
				if ($images) {
					$list[$k]['logo'] = $dir.$images[0];
					continue;
				}
			}
			foreach($list as $prod) {
				Data::exec('UPDATE showcase_producers SET logo = ? WHERE producer_nick = ?',
					[$prod['logo'], $prod['producer_nick']]
				);
			}
		});
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
	public static function addFilesFS(&$list, $prod) {
		if ($prod) {
			Data::addFilesFSproducer($list, $prod);
			foreach (Data::$files as $type) {
				Data::addFilesFStype($list, $prod, $type);
			}
		} else {
			$dir = Showcase::$conf['folders'];
			FS::scandir($dir, function ($prod) use (&$list) {
				Data::addFilesFSproducer($list, $prod);
			});
			foreach (Data::$files as $type) {
				$dir = Showcase::$conf[$type];
				FS::scandir($dir, function ($prod) use (&$list, $type) {
					Data::addFilesFStype($list, $prod, $type);
				});
			}
		}
		foreach ($list as $prod => $arts) if (!$arts) unset($list[$prod]); 
	}
	public static function addFilesFStype(&$list, $prod, $type) { //files, texts, images, videos
		$dir = Showcase::$conf[$type].$prod.'/';
		if (!Path::theme($dir)) return; //Подходят только папки
		$ext = $type == 'files' ? false : Data::$$type;
		$index = Data::getIndex($dir, $exts);
		foreach ($index as $art => $files) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			$files = array_fill_keys($files, $type);
			$list[$prod][$art][0] += $files;
		}
	}
	public static function addFilesFSproducer(&$list, $prod) {
		if (in_array($prod,['articles','tables'])) return; //Относится к группам
		$dir = Showcase::$conf['folders'].$prod.'/';
		
		if (!Path::theme($dir)) return; //Подходят только папки
		if (!isset($list[$prod])) $list[$prod] = array();

		FS::scandir($dir, function ($fart) use ($dir, &$list, $prod) {
			$art = mb_strtolower($fart);
			if (!Path::theme($dir.$fart.'/')) return; //Подходят только папки
			if (in_array($art, Data::$files)) return;
				
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];//Data::$files;
			FS::scandir($dir.$fart.'/', function ($file) use (&$list, $prod, $dir, $art, $fart) {
				$fd = Load::nameInfo($file);
				if (in_array($fd['ext'], Data::$iexts)) return;
				$src = $dir.$fart.'/'.$file;
				$type = Data::fileType($src);
				$list[$prod][$art][0][$src] = $type;
			});	
		});
		//echo '<pre>';
		//print_r($list);
		//exit;
		$index = Data::getIndex($dir.'images/',  Data::$images);
		foreach ($index as $art => $images) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			$images = array_fill_keys($images,'images');
			$list[$prod][$art][0] += $images;
		}
		$index = Data::getIndex($dir.'files/');
		foreach ($index as $art => $files) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			foreach ($files as $src) {
				$list[$prod][$art][0][$src] = Data::fileType($src);
			}
			
		}
	}
	/*public static function addFilesFSimages(&$list, $prod) {
		$dir = Showcase::$conf['images'].$prod.'/';
		if (!Path::theme($dir)) return; //Подходят только папки
		$index = Data::getIndex($dir,  Data::$images);
		foreach ($index as $art => $images) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			$images = array_fill_keys($images,'images');
			$list[$prod][$art][0] += $images;
		}
	}*/
	public static function addFilesFaylov(&$list, $producer) {
		$dir = Showcase::$conf['folders'];
		//Можно ли привязать файлы к item. Да, только через свойства Файлы, Файл, Фото. Для связи достаточно item_num
		$fayliid = Data::initProp('Файлы', 'value');//Пути. Могут быть несколько (pr.producer, a.article, pr.item_num)
		if ($producer) {
			$producer_id = Data::initProducer($producer);
			$fayli = Data::all('SELECT pr.producer, a.article, a.article_nick, mv.item_num, v.value from showcase_mvalues mv
				INNER JOIN showcase_values v ON v.value_id = mv.value_id
				INNER JOIN showcase_models m ON m.model_id = mv.model_id and m.producer_id = ?
				INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
				INNER JOIN showcase_articles a ON a.article_id = m.article_id
				WHERE mv.prop_id = ?',
			[$producer_id, $fayliid]);
		} else {
			$fayli = Data::all('SELECT pr.producer, a.article, a.article_nick, mv.item_num, v.value from showcase_mvalues mv
				INNER JOIN showcase_values v ON v.value_id = mv.value_id
				INNER JOIN showcase_models m ON m.model_id = mv.model_id
				INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
				INNER JOIN showcase_articles a ON a.article_id = m.article_id
				WHERE mv.prop_id = ? ',
			[$fayliid]);	
		}
		
		foreach ($fayli as $fayl) {
			$prod = $fayl['producer'];
			$art = mb_strtolower($fayl['article_nick']);
			$num = $fayl['item_num'];

			if (!isset($list[$prod])) $list[$prod] = array();
			if (!isset($list[$prod][$art])) $list[$prod][$art] = array();
			if (!isset($list[$prod][$art][$num])) $list[$prod][$art][$num] = array();

			if (preg_match('/http::/',$fayl['value'])) {
				$list[$prod][$art][$num][$src] = 'images';
				continue;
			} 
			$src = $dir.$fayl['value'];
			if (!Path::theme($src)) $src = $fayl['value'];
			if (!Path::theme($src)) continue; //В Файлы путь указывается от корня data
			
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
						$art = mb_strtolower($row['article']);
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
			$fayls[$row['producer']][mb_strtolower($row['value'])][] = $row;
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
	public static function getIndex($dir, $exts = false) {
		if (!Path::theme($dir)) return array();
		$list = array();
		Config::scan($dir, function ($src, $level) use (&$list, $exts) {
			$fd = Load::pathInfo($src);
			if ($exts && !in_array($fd['ext'], $exts)) return;
			if (in_array($fd['ext'], Data::$iexts)) return;
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
			$list = Data::fetchto('SELECT g.group_nick, g.icon, g.group, c.name as catalog, count(*) as count, max(model_id) as notempty, g2.group_nick as parent_nick FROM showcase_groups g
			left JOIN showcase_models m ON g.group_id = m.group_id
			left JOIN showcase_catalog c ON c.catalog_id = g.catalog_id
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
			if (!$parents || !isset($parents[''])) return array('childs'=>[], 'group_nick'=>false, 'group'=>'Группа не найдена');
			$childs = $parents[''];
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
			Xlsx::runGroups($root, function &(&$group, $i, $parent) {
				$group['sum'] = $group['count'];
				if (isset($group['childs'])) {
					foreach ($group['childs'] as $child) {
						$group['sum'] += $child['sum'];
					}
					
				}
				$r = null;
				return $r;
			}, true);
			return $root;
		});
		$root['path'] = [];
		if ($group_nick) {
			return Xlsx::runGroups($root, function &($group) use ($group_nick){
				if ($group['group_nick'] == $group_nick) return $group;
				$r = null;
				return $r;

			});
		}
		return $root;
	}
	public static function checkCls(&$prod) {
		$prod['cls'] = 'danger';

		if (empty($prod['skip'])) $skip = [];
		else $skip = $prod['skip'];
		if (empty($skip['Пояснения'])) $skip['Пояснения'] = ['Сообщение и сколько позиций оно затрагивает'=>1];
		if (empty($skip['Без картинок'])) $skip['Без картинок'] = 0;
		if (empty($skip['Без цен'])) $skip['Без цен'] = 0;
		if (empty($skip['Ошибки каталога'])) $skip['Ошибки каталога'] = 0;
		//if (empty($skip['Ошибки каталога'])) $skip['Ошибки каталога'] = 0;

		$costs = $prod['Без картинок'] - $skip['Без картинок'];
		$images = $prod['Без цен'] - $skip['Без цен'];
		$tables = $prod['Ошибки каталога'] - $skip['Ошибки каталога'];

		$errs = ($images!=0?1:0) + ($costs!=0?1:0) + ($tables!=0?1:0);
		if ($errs === 0)  $prod['cls'] = 'success';
		if ($errs === 1)  $prod['cls'] = 'primary';
		if ($errs === 2)  $prod['cls'] = 'warning';
		if ($errs === 3)  $prod['cls'] = 'danger';
	}
	public static function getProducers($producer_nick = false) {
		$cost_id = Data::initProp("Цена");
		$image_id = Data::initProp("images");
		$price_id = Data::initProp("Прайс");
		$opt = Data::getOptions();
		if ($producer_nick) {
			$list = Data::fetch('SELECT p.producer, p.logo, p.producer_nick, 
				GROUP_CONCAT(DISTINCT c.name SEPARATOR \', \') as catalog, 
				GROUP_CONCAT(DISTINCT pp.name SEPARATOR \', \') as price
				from showcase_models m
				INNER JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = :producer_nick)
				INNER JOIN showcase_catalog c on c.catalog_id = m.catalog_id
				LEFT JOIN showcase_mnumbers n on (m.model_id = n.model_id and n.prop_id = :cost_id)
				LEFT JOIN showcase_prices pp on (n.price_id = pp.price_id)
				GROUP BY producer',[':producer_nick'=>$producer_nick,':cost_id'=>$cost_id]);
			if (!$list) $list = [];
			
			$list['count'] = Data::col('SELECT count(*) FROM showcase_models m
				INNER JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = ?)
				',[$producer_nick]);


			$costs = Data::col('SELECT count(DISTINCT m.model_id) FROM showcase_models m 
				inner join showcase_producers pr on (m.producer_id = pr.producer_id and pr.producer_nick = :producer_nick)
				inner join showcase_mnumbers n on (n.model_id = m.model_id and n.prop_id = :cost_id)
				',[':cost_id'=>$cost_id,':producer_nick'=>$producer_nick]);
			$list['Без цен'] = $list['count'] - $costs;


			$images = Data::col('SELECT count(DISTINCT m.model_id) FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id and pr.producer_nick = :producer_nick)
				inner join showcase_mtexts n on (n.model_id = m.model_id and n.prop_id = :image_id)
				',[':image_id'=>$image_id,':producer_nick'=>$producer_nick]);
			$list['Без картинок'] = $list['count'] - $images;

			
			if (isset($opt['producers'][$producer_nick])) {
				$list += $opt['producers'][$producer_nick];
			}

			


			//Есть в каталоге, но нет в прайсе
			$prices = Data::col('SELECT count(DISTINCT m.model_id) FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id and pr.producer_nick = :producer_nick)
				inner join showcase_mvalues n on (n.model_id = m.model_id and n.prop_id = :price_id)
				',[':price_id'=>$price_id,':producer_nick'=>$producer_nick]);
			$list['Ошибки каталога'] = $list['count'] - $prices;

			
			//Есть в прайсе, но нет в каталоге
			/*$options = Prices::getList();
			$list['Ошибки каталога'] = 0;
			foreach($options as $name => $p) {
				if ($p['producer_nick'] == $producer_nick) {
					if (empty($p['ans']['Ошибки каталога'])) continue;
					$list['Ошибки каталога'] += sizeof($p['ans']['Ошибки каталога']);
				}
			}*/
			Data::checkCls($list);

			return $list;
			
		} else {
			$list = Data::all('SELECT p.producer, p.logo, p.producer_nick, count(*) as `count`,
				GROUP_CONCAT(DISTINCT c.name SEPARATOR \', \') as catalog, 
				GROUP_CONCAT(DISTINCT pp.name SEPARATOR \', \') as price
				from showcase_models m
				INNER JOIN showcase_producers p on p.producer_id = m.producer_id
				INNER JOIN showcase_catalog c on c.catalog_id = m.catalog_id
				LEFT JOIN showcase_mnumbers n on m.model_id = n.model_id and n.prop_id = :cost_id
				LEFT JOIN showcase_prices pp on (n.price_id = pp.price_id)
				GROUP BY producer
				order by count DESC',[':cost_id'=>$cost_id]);
			
			$listcost = Data::fetchto('SELECT p.producer_nick, count(*) as count FROM showcase_models m
				INNER JOIN showcase_producers p on (p.producer_id = m.producer_id)
				GROUP BY p.producer_nick
				','producer_nick');


			$costs = Data::fetchto('SELECT pr.producer_nick, count(DISTINCT m.model_id) as count FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id)
				inner join showcase_mnumbers n on (n.model_id = m.model_id and n.prop_id = :cost_id)
				GROUP BY m.producer_id
				','producer_nick', [':cost_id'=>$cost_id]);
			foreach($list as $i => $row) {
				if(isset($costs[$row['producer_nick']])) {
					$list[$i]['Без цен'] = $listcost[$row['producer_nick']]['count'] - $costs[$row['producer_nick']]['count'];	
				} else {
					$list[$i]['Без цен'] = $listcost[$row['producer_nick']]['count'];
				}
			}

			$images = Data::fetchto('SELECT pr.producer_nick, count(DISTINCT m.model_id) as count FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id)
				inner join showcase_mtexts n on (n.model_id = m.model_id and n.prop_id = :image_id)
				GROUP BY m.producer_id
				','producer_nick', [':image_id'=>$image_id]);
			foreach($list as $i => $row) {
				if (isset($images[$row['producer_nick']])) {
					$list[$i]['Без картинок'] = $listcost[$row['producer_nick']]['count'] - $images[$row['producer_nick']]['count'];	
				} else {
					$list[$i]['Без картинок'] = $listcost[$row['producer_nick']]['count'];
				}
			}

			$prices = Data::fetchto('SELECT pr.producer_nick, count(DISTINCT m.model_id) as count FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id)
				inner join showcase_mvalues n on (n.model_id = m.model_id and n.prop_id = :price_id)
				GROUP BY m.producer_id
				','producer_nick', [':price_id'=>$price_id]);
			
			foreach($list as $i => $row) {
				if (isset($prices[$row['producer_nick']])) {
					$list[$i]['Ошибки каталога'] = $listcost[$row['producer_nick']]['count'] - $prices[$row['producer_nick']]['count'];
				} else {
					$list[$i]['Ошибки каталога'] = $listcost[$row['producer_nick']]['count'];
				}
			}



			$options = Prices::getList();
			foreach($list as $i => $row) {

				if (isset($opt['producers'][$row['producer_nick']])) {
					$list[$i] += $opt['producers'][$row['producer_nick']];
				}
				
				/*$list[$i]['Ошибки прайсов'] = 0;
				foreach($options as $name => $p) {
					if (empty($p['ans']['Ошибки прайсов'])) continue;
					if ($p['producer_nick'] == $row['producer_nick']) {
						$list[$i]['Ошибки прайсов'] += sizeof($p['ans']['Ошибки прайсов']);
					}
				}*/
				/*$list[$i]['Ошибки каталога'] = 0;
				foreach($options as $name => $p) {
					if (empty($p['ans']['Ошибки каталога'])) continue;
					if ($p['producer_nick'] == $row['producer_nick']) {
						$list[$i]['Ошибки каталога'] += sizeof($p['ans']['Ошибки каталога']);
					}
				}*/
				Data::checkCls($list[$i]);
			}

			return $list;
		}
		
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