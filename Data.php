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
	public static $iexts = ['db'];
	public static $types = ['number','text','value'];
	public static $files = ['texts', 'images', 'folders', 'videos','slides','files'];

	public static $texts = ['html', 'tpl', 'mht', 'docx'];
	public static $images = ['png', 'gif', 'jpg', 'jpeg','svg'];
	public static $videos = ['avi','ogv','mp4','swf'];
	public static $slides = ['png', 'jpg', 'jpeg'];
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
	public static function initPropNick(&$list) {
		$list = array_unique($list);
		foreach ($list as $k=>$v) {
			$list[$k] = Path::encode($v);
		}
	}
	public static function loadShowcaseConfig(){
		$opt = Load::loadJSON(Showcase::$conf['jsonoptions']);
		if(!$opt) $opt = [];
		$opt += array(
			'catalog'=>[],
			'justonevalue'=>[],
			//'files' => [],
			'numbers'=>[],
			'texts'=>[],
			'columns'=>[],
			'prices'=>[],
			'props'=>[],
			'groups'=>[],
			'filters'=>[],
			'values'=>[]	
		);
		$opt['filters'] += ['buttons'=>[],'groups'=>[],'order'=>'name'];

		//Data::initPropNick($opt['files']);
		Data::initPropNick($opt['values']);
		
		$opt['justonevalue'][] = 'Цена';
		Data::initPropNick($opt['justonevalue']);

		$opt['numbers'][] = 'Цена';
		Data::initPropNick($opt['numbers']);
		
		$opt['texts'][] = 'Описание';
		$opt['texts'][] = 'Наименование';
		$opt['texts'][] = 'Иллюстрации';
		foreach (Data::$files as $f) $opt['texts'][] = $f; //Все пути текстовые
		Data::initPropNick($opt['texts']);

		
		$keys = [];
		foreach ($opt['groups'] as $k => $v) {
			$keys[Path::encode($k)] = $v;
		}
		$opt['groups'] = $keys;

		$keys = [];
		foreach ($opt['filters'] as $k => $v) {
			$keys[Path::encode($k)] = $v;
		}
		$opt['filters'] = $keys;
		
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
		Data::exec('TRUNCATE showcase_catalog');
		Data::exec('TRUNCATE `showcase_producers`');
		//Data::exec('TRUNCATE `showcase_groups`'); Нельзя сбрасывать id для Директа
		Data::exec('TRUNCATE `showcase_props`');
		Data::exec('TRUNCATE `showcase_values`');
		Data::exec('TRUNCATE `showcase_models`');
		Data::exec('TRUNCATE `showcase_items`');
		Data::exec('TRUNCATE `showcase_iprops`');
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
		return Once::func( function ($prop, $type) {
			if (!$prop) return null;
			
			$nick = Path::encode($prop);

			$row = Data::fetch('SELECT prop_id, type from showcase_props where prop_nick = ?', [$nick]);

			if ($row) {
				if ($type && $type != $row['type']) {
					Data::exec('DELETE FROM showcase_iprops WHERE prop_id = ?', [$row['prop_id']]);
					Data::exec('UPDATE showcase_props SET type = ? WHERE prop_id = ?', [$type, $row['prop_id']]);
				}
				return $row['prop_id'];
			}
			if (!$type) return false;
			return Data::lastId(
				'INSERT INTO showcase_props (prop, prop_nick, type) VALUES(?,?,?)',
				[$prop, $nick, $type]
			);	
		}, [$prop, $type]);
	}
	
	public static function checkType($prop) {
		$options = Data::loadShowcaseConfig();
		$prop_nick = Path::encode($prop);

		if ($prop_nick == 'Артикул') return 'article';
		if (in_array($prop_nick, $options['numbers'])) return 'number';
		if (in_array($prop_nick, $options['texts'])) return 'text';
		if (in_array($prop_nick, $options['values'])) return 'value';
		
		if (in_array($prop_nick, $options['filters']['groups'])) return 'value';
		if (in_array($prop_nick, $options['filters']['buttons'])) return 'value';
		
		return 'value';
	}
	public static function clearProp($prop_id) {
		Data::exec('DELETE FROM `showcase_iprops` where prop_id = ?', [$prop_id]);
	}
	public static function init() {
		$props = Data::fetchto("SELECT prop_id, prop, type from showcase_props", "prop");

		$options = Data::loadShowcaseConfig();

		//1 value, 2 number, 3 text
		foreach ($props as $prop => $p) {
			$type = Data::checkType($p['prop']);
			if ($p['type'] == $type) continue;
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
				$r = Data::exec('DELETE mv FROM showcase_iprops mv, showcase_models m, showcase_producers pr
					WHERE m.model_id = mv.model_id and m.producer_id = pr.producer_id and pr.producer_nick = ?
					and mv.prop_id = ?', [$producer_nick, $prop_id]);	
			}
		} else {
			foreach (Data::$files as $type) {
				$prop_id = Data::initProp($type, 'text'); //Удаляем все параметры связаные с файлами images, files, texts
				Data::exec('DELETE FROM `showcase_iprops` where prop_id = ?', [$prop_id]);	
			}
		}
		
	}
	public static function applyIllustracii($producer_nick) {
		$prop_id = Data::initProp('Иллюстрации','text');
		if ($producer_nick) {
			$images = Data::all('SELECT p.prop, mv.text, mv.model_id, mv.item_num FROM showcase_iprops mv
				INNER JOIN showcase_models m on (m.model_id = mv.model_id)
				INNER JOIN showcase_producers pr on (m.producer_id = pr.producer_id and pr.producer_nick = ?)
				INNER JOIN showcase_props p on (mv.prop_id = p.prop_id and p.prop_id = ?)
			',[$producer_nick, $prop_id]);
			
		} else {
			$images = Data::all('SELECT mv.text, mv.model_id, mv.item_num FROM showcase_iprops mv
				INNER JOIN showcase_props p on mv.prop_id = p.prop_id
				where p.prop_id = ?
			',[$prop_id]);
		}
		$prop_id = Data::initProp('images','text');
		foreach ($images as $pos) {
			Data::exec(
				'INSERT INTO showcase_iprops (model_id, item_num, prop_id, `text`) VALUES(?,?,?,?)',
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
			

			Data::addFilesFS($list, $producer_nick); //Из папок
			

			Data::addFilesSyns($list, $producer_nick); //Синонимы Фото и Файл для поиска
			
		
			$db = &Db::pdo();
			$db->beginTransaction();
			Data::removeFiles($producer_nick);


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
						where (m.article_nick = ? or m.article_nick = ?) and m.producer_id = ?', [$art, $altart, $producer_id]);

					if (!$model_id) continue;//Имя файла как артикул не зарегистрировано, даже если удалить производителя из артикула
					$values = [];
					
					
					foreach ($items as $item_num => $files) {
						$order = 0;
						ksort($files);
						foreach ($files as $src => $type) { //Все эти файлы относятся к найденной модели
							$order++;
							//$value_id = Data::initValue($src);
							$ans['Найденные файлы'][$src] = true;
							unset($ans['Бесхозные файлы'][$src]);
							/*if (isset($values[$value_id])) {
								continue; //Дубли одного пути или похоже пути из-за Path encode путь может давайть одинаковый value_nick в этом случае похожий путь будет проигнорирован
							}
							$values[$value_id] = true;*/
							$prop_id = Data::initProp($type, 'text');
							if ($item_num) {
								Data::exec(
									'INSERT INTO showcase_iprops (model_id, item_num, prop_id, `text`, `order`) VALUES(?,?,?,?,?)',
									[$model_id, $item_num, $prop_id, $src, $order]
								);
							} else {
								$icount = Data::col('SELECT count(*) FROM showcase_items where model_id = ?',[$model_id]);
								while ($icount > 0) {
									Data::exec(
										'INSERT INTO showcase_iprops (model_id, item_num, prop_id, `text`, `order`) VALUES(?,?,?,?,?)',
										[$model_id, $icount, $prop_id, $src, $order]
									);
									$icount--;
								}
							}

							//print_r([$producer_id, $article_id, $num, $value_id]);
							//echo $prod.':'.$art.':'.$num.' '.$type.':'.$src.'<br>';
						}
					}
				}
			}

			$ans['Бесхозные файлы'] = array_keys($ans['Бесхозные файлы']);
			$ans['Найденные файлы'] = array_keys($ans['Найденные файлы']);
			$list = Data::addFilesIcons();
			if ($list) $ans['Бесхозные файлы']['Иконки групп'] = $list;

			$db->commit();
			foreach($ans as $i=>$val){
				if (is_array($ans[$i]) && sizeof($ans[$i]) > 1000) $ans[$i] = sizeof($ans[$i]);
			}
			return $ans;
		},[$producer_nick]);

	}
	public static function addFilesIcons() {
		return Once::func(function(){

			$images = FS::scandir(Showcase::$conf['icons'], function ($file) {
				$fd = Load::nameInfo($file);
				if (in_array($fd['ext'], Data::$images)) return true;
				return false;
			});

			$icons = [];
			$icons = array_reduce($images, function ($icons, $file){
				$fd = Load::nameInfo($file);
				$nick = Path::encode($fd['name']);
				$icons[$nick] = Showcase::$conf['icons'].$file;
				return $icons;
			}, $icons);

			$images_id = Data::initProp('images');
			$root = Data::getGroups();
			$conf = Showcase::$conf;
			Xlsx::runGroups($root, function &(&$group) use ($images_id, &$icons){
				//Ищим свою картинку
				$group['icon'] = null;
				
				$icon = false;
				if (!$icon) {
					$nick = $group['group_nick'];
					if (isset($icons[$nick])) {
						$icon = $icons[$nick];
						unset($icons[$nick]);
					}
				}
				if (!$icon) {
					$nick = Path::encode($group['group']);
					if (isset($icons[$nick])) {
						$icon = $icons[$nick];
						unset($icons[$nick]);
					}
				}
				
				/*if (!$icon) {
					$icon = Rubrics::find(Showcase::$conf['icons'], $group['group_nick'], Data::$images);
				}
				if (!$icon) {
					$nick = Path::encode($group['group']);
					$icon = Rubrics::find(Showcase::$conf['icons'], $nick, Data::$images);
				}*/


				if ($icon) {
					$group['icon'] = $icon;
				} else {
					//Ищим картинку своей позииции
					$row = Data::fetch('SELECT g.group_nick, g.group, g.group_id, mv.text as icon from showcase_groups g
						inner join showcase_models m on (m.group_id = g.group_id)	
						inner join showcase_iprops mv on (mv.model_id = m.model_id and mv.prop_id = :images_id)
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

			
			$list = Data::all('SELECT producer_nick, producer FROM showcase_producers');
			foreach ($list as $k => $prod) {
				$icon = false;
				if (!$icon) {
					$nick = Path::encode($prod['producer']);
					if (isset($icons[$nick])) {
						$icon = $icons[$nick];
						unset($icons[$nick]);
					}
				}
				if (!$icon) {
					$nick = $prod['producer'];
					if (isset($icons[$nick])) {
						$icon = $icons[$nick];
						unset($icons[$nick]);
					}
				}
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
			return $icons;
		});
	}
	/*public static function initArticle($value) {
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
	}*/
	public static function getFolderName($dir, $prod) {
		$prodname = false;
		FS::scandir($dir, function ($pr) use ($prod, &$prodname) {
			if ($prod == Path::encode($pr)) {
				$prodname = $pr;
				return false;
			}
		});
		return $prodname;
	}
	public static function addFilesFS(&$list, $prod) {
		if ($prod) {
			$prodname = Data::getFolderName(Showcase::$conf['folders'], $prod);
			if ($prodname) Data::addFilesFSproducer($list, $prod, Showcase::$conf['folders'].$prodname.'/');
			foreach (Data::$files as $type) {
				if (!isset(Showcase::$conf[$type])) continue;
				$prodname = Data::getFolderName(Showcase::$conf[$type], $prod);
				if ($prodname) Data::addFilesFStype(Showcase::$conf[$type].$prodname.'/', $list, $prod, $type);
			}

		} else {
			$dir = Showcase::$conf['folders'];
			FS::scandir($dir, function ($prod) use (&$list) {
				//В списке производителей могут быть некоторые левые папки depricated
				if (in_array($prod,['groups', 'articles', 'tables'])) return; //Относится к группам
				Data::addFilesFSproducer($list, $prod, Showcase::$conf['folders'].$prod.'/');
			});
			foreach (Data::$files as $type) {
				if (!isset(Showcase::$conf[$type])) continue;
				$dir = Showcase::$conf[$type];
				FS::scandir($dir, function ($prod) use (&$list, $type) {
					Data::addFilesFStype(Showcase::$conf[$type].$prod.'/', $list, $prod, $type);
				});
			}
		}
		foreach ($list as $prod => $arts) if (!$arts) unset($list[$prod]); 
	}
	public static function addFilesFStype($dir, &$list, $prod, $type) { //files, texts, images, videos
		if (!Path::theme($dir)) return; //Подходят только папки
		if ($type == 'folders') {
			return Data::addFilesFSproducer($list, $prod, $dir);
		}
		$exts = $type == 'folders' ? false : Data::$$type;
		$index = Data::getIndex($dir, $exts);
		foreach ($index as $art => $files) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			$files = array_fill_keys($files, $type);
			$list[$prod][$art][0] += $files;
		}
	}
	public static function indexdir($dir, &$list) {
		FS::scandir($dir, function ($file) use (&$list, $dir) {
			$fd = Load::nameInfo($file);
			if (in_array($fd['ext'], Data::$iexts)) return;	
			$src = $dir.$file;
			if (!Path::theme($src.'/')) {
				$type = Data::fileType($src);
				$list[$src] = $type;
			}
		});
	}
	public static function addFilesFSproducer(&$list, $prod, $dir) {
		if (!Path::theme($dir)) return; //Подходят только папки
		if (!isset($list[$prod])) $list[$prod] = array();
		FS::scandir($dir, function ($fart) use ($dir, &$list, $prod) {
			$artdir = $dir.$fart.'/';
			if (!Path::theme($artdir)) return; //Подходят только папки
			$art = mb_strtolower($fart);
			$art = Path::encode($art);
			if (in_array($art, Data::$files)) return;
				
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			$list[$prod][$art][0][$artdir] = 'folders';
			Data::indexdir($artdir, $list[$prod][$art][0]);
		});
		
		foreach (Data::$files as $type) {
			if ($type == 'folders') {
				FS::scandir($dir.$type.'/', function ($userdir) use ($dir, $type, &$list, $prod) {
					Data::addFilesFSproducer($list, $prod, $dir.$type.'/'.$userdir.'/');
				});
			} else {
				$index = Data::getIndex($dir.$type.'/',  Data::$$type);
				foreach ($index as $art => $files) {
					if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
					$files = array_fill_keys($files, $type);
					$list[$prod][$art][0] += $files;
				}
			}
		}
		
		/*$index = Data::getIndex($dir.'texts/',  Data::$texts);
		foreach ($index as $art => $texts) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			$texts = array_fill_keys($texts,'texts');
			$list[$prod][$art][0] += $texts;
		}*/

		
		/*$index = Data::getIndex($dir.'files/');
		foreach ($index as $art => $files) {
			if (!isset($list[$prod][$art])) $list[$prod][$art] = [0=>[]];
			foreach ($files as $src) {
				$list[$prod][$art][0][$src] = Data::fileType($src);
			}
			
		}*/
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
			$fayli = Data::all('SELECT pr.producer, m.article, m.article_nick, mv.item_num, v.value from showcase_iprops mv
				INNER JOIN showcase_values v ON v.value_id = mv.value_id
				INNER JOIN showcase_models m ON m.model_id = mv.model_id and m.producer_id = ?
				INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
				WHERE mv.prop_id = ?',
			[$producer_id, $fayliid]);

		} else {
			$fayli = Data::all('SELECT pr.producer, m.article, m.article_nick, mv.item_num, v.value from showcase_iprops mv
				INNER JOIN showcase_values v ON v.value_id = mv.value_id
				INNER JOIN showcase_models m ON m.model_id = mv.model_id
				INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
				WHERE mv.prop_id = ? ',
			[$fayliid]);	
		}
		
		foreach ($fayli as $fayl) {
			$prod = $fayl['producer'];
			$art = mb_strtolower($fayl['article_nick']);
			$num = $fayl['item_num'];

			if (!isset($list[$prod])) $list[$prod] = array();
			if (!isset($list[$prod][$art])) $list[$prod][$art] = array(0=>[]);
			if (!isset($list[$prod][$art][$num])) $list[$prod][$art][$num] = array();

			if (preg_match('/http::/',$fayl['value'])) {
				$list[$prod][$art][$num][$src] = 'images';
				continue;
			} 
			$src = $dir.$fayl['value'];
			
			if (!Path::theme($src)) $src = $fayl['value'];
			if (!Path::theme($src)) continue; //В Файлы путь указывается от корня data
			
			if (FS::is_dir($src)) {
				$list[$prod][$art][$num][$src] = 'folders';
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
		$rows = Data::all('SELECT pr.producer_nick, pr.producer, m.article, mv.item_num, v.value from showcase_iprops mv
			INNER JOIN showcase_values v ON v.value_id = mv.value_id
			INNER JOIN showcase_models m ON mv.model_id = m.model_id
			INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
			WHERE prop_id = ?',[$fotoid]);
		
		$fotos = []; //[$producer][$fimage] = ['article', 'item_num'];
		
		foreach ($rows as $row) {
			if (!isset($fotos[$row['producer_nick']])) $fotos[$row['producer_nick']] = [];
			$val = mb_strtolower(Path::encode($row['value']));
			if (!isset($fotos[$row['producer_nick']][$val])) $fotos[$row['producer_nick']][$val] = [];
			$fotos[$row['producer_nick']][$val][] = $row;
		}

		
		foreach ($fotos as $prod => $artsyns) {
			foreach ($artsyns as $syn => $syns) {
				if (empty($list[$prod][$syn])) continue;
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
		$rows = Data::all('SELECT pr.producer_nick, pr.producer, m.article, mv.item_num, v.value from showcase_iprops mv
			INNER JOIN showcase_values v ON v.value_id = mv.value_id
			INNER JOIN showcase_models m ON mv.model_id = m.model_id
			INNER JOIN showcase_producers pr ON pr.producer_id = m.producer_id
			WHERE prop_id = ?',[$faylid]);
		$fayls = [];
		
		foreach ($rows as $row) {
			if (!isset($fayls[$row['producer_nick']])) $fayls[$row['producer_nick']] = [];
			$art = mb_strtolower($row['value']);
			if (!isset($fayls[$row['producer_nick']][$art])) $fayls[$row['producer_nick']][$art] = [];
			$fayls[$row['producer_nick']][$art][] = $row;
		}
		
		foreach ($fayls as $prod => $artsyns) {
			foreach ($artsyns as $syn => $syns) {
				if (!isset($list[$prod][$syn][0])) continue;
				foreach ($list[$prod][$syn][0] as $src => $type) { //По синониму может быть несколько файлов
					foreach ($syns as $row) { //Один синоним может быть для нескольких позиций и каждый файл записываем в каждую позицию
						$art = mb_strtolower($row['article']);
						if (!isset($list[$prod][$art][$row['item_num']])) $list[$prod][$art][$row['item_num']] = [];
						$list[$prod][$art][$row['item_num']][$src] = $type;
					}
				}
			}
		}

	}
	public static function encode($name) {
		$name = trim($name);
		$name = preg_replace("/_\d*$/", '',$name);
		$name = preg_replace("/\s*\(\d*\)*$/", '',$name);
		$name = mb_strtolower(Path::encode($name));
		return $name;
	}
	public static function getIndex($dir, $exts = false) {
		if (!Path::theme($dir)) return array();
		$list = array();
		Config::scan($dir, function ($src, $level) use (&$list, $exts) {
			$fd = Load::pathInfo($src);
			if ($exts && !in_array($fd['ext'], $exts)) return;
			if (in_array($fd['ext'], Data::$iexts)) return;
			$name = $fd['name'];

			$p = explode(',',$name);
			foreach ($p as $name) {
				$name1 = Data::encode($name);
				if (!$name1) continue;
				//$name2 = mb_strtolower(Path::encode($name)); //Для фото, когда у itemoв свои номера
				
				if (empty($list[$name1])) $list[$name1] = array();
				$list[$name1][] = $src;

				//if (empty($list[$name2])) $list[$name2] = array();
				//$list[$name2][] = $src;
			}
		}, true);
		return $list;
	}
	public static function getGroups($group_nick = false) {
		$root = Once::func(function (){
			$cost_id = Data::initProp("Цена");

			$list = Data::fetchto('SELECT g.group_id, g.parent_id, g.group_nick, g.icon, g.order, g.group, c.name as catalog, count(distinct m.model_id) as count, max(m.model_id) as notempty, min(mn.number) as min, max(mn.number) as max, g2.group_nick as parent_nick, g2.group as parent FROM showcase_groups g
			left JOIN showcase_models m ON g.group_id = m.group_id
			left JOIN showcase_iprops mn ON (m.model_id = mn.model_id and mn.prop_id = ?)
			left JOIN showcase_catalog c ON c.catalog_id = g.catalog_id
			left JOIN showcase_groups g2 ON g2.group_id = g.parent_id
			GROUP BY group_nick
			order by c.order, g.order','group_nick',[$cost_id]);



			
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
			$opt = Data::getOptions();
			Xlsx::runGroups($root, function &(&$group, $i, $parent) use ($opt) {

				if (isset($opt['groups'][$group['group_nick']])) {
					$group['showcase'] = $opt['groups'][$group['group_nick']];
				} else if(isset($group['path'])) {
					foreach ($group['path'] as $g) {
						if (isset($opt['groups'][$g])) {
							$group['showcase'] = $opt['groups'][$g];
						}
					}	
				}
				if (!isset($group['showcase'])) {
					$root_nick = Path::encode(Showcase::$conf['title']);
					if (isset($opt['groups'][$root_nick])) {
						$group['showcase'] = $opt['groups'][$root_nick];
					}
				}
				
				
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
		
		$images = $prod['Без картинок'] - $skip['Без картинок'];
		$costs = $prod['Без цен'] - $skip['Без цен'];
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
				LEFT JOIN showcase_iprops n on (m.model_id = n.model_id and n.prop_id = :cost_id)
				LEFT JOIN showcase_prices pp on (n.price_id = pp.price_id)
				GROUP BY producer',[':producer_nick'=>$producer_nick,':cost_id'=>$cost_id]);
			if (!$list) $list = [];
			
			$list['count'] = Data::col('SELECT count(*) FROM showcase_models m
				INNER JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = ?)
				',[$producer_nick]);

			$list['icount'] = Data::col('SELECT count(*) FROM showcase_models m
				left join showcase_items i on (i.model_id = m.model_id)
				INNER JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = ?)
				',[$producer_nick]);


			$costs = Data::col('SELECT count(distinct i.model_id) FROM showcase_items i
				left join showcase_models m on (m.model_id = i.model_id)
				left join showcase_producers pr on (m.producer_id = pr.producer_id)
				left join showcase_iprops ip on (i.model_id = ip.model_id and i.item_num = ip.item_num and ip.prop_id = :cost_id)
				WHERE 
					ip.number is null
					and pr.producer_nick = :producer_nick	
				',[':cost_id' => $cost_id,':producer_nick' => $producer_nick]);
			$list['Без цен'] = $costs;

			/*$costs = Data::col('SELECT count(distinct m.model_id) FROM showcase_items i 
				left join showcase_models m on (i.model_id = m.model_id)
				left join showcase_producers pr on (m.producer_id = pr.producer_id)
				
				left join showcase_iprops n on (n.model_id = i.model_id and i.item_num = n.item_num and n.prop_id = :cost_id)
				where n.number is null and pr.producer_nick = :producer_nick
				',[':cost_id'=>$cost_id,':producer_nick'=>$producer_nick]);
			$list['Без цен'] = $list['count'] - $costs;*/

			//echo '<pre>';
			//print_r($list);
			//exit;

			$images = Data::col('SELECT count(DISTINCT m.model_id) FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id and pr.producer_nick = :producer_nick)
				inner join showcase_iprops n on (n.model_id = m.model_id and n.prop_id = :image_id)
				',[':image_id'=>$image_id,':producer_nick'=>$producer_nick]);
			$list['Без картинок'] = $list['count'] - $images;

			
			if (isset($opt['producers'][$producer_nick])) {
				$list += $opt['producers'][$producer_nick];
			}
			$producer = $list['producer'];
			if (isset($opt['producers'][$producer])) {
				$list += $opt['producers'][$producer];
			}

			//Есть в каталоге, но нет в прайсе
			$prices = Data::col('SELECT count(DISTINCT m.model_id) FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id and pr.producer_nick = :producer_nick)
				inner join showcase_iprops n on (n.model_id = m.model_id and n.prop_id = :price_id)
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
			$list = Data::all('SELECT p.producer, p.logo, p.producer_nick, count(distinct m.model_id) as `count`,
				GROUP_CONCAT(DISTINCT c.name SEPARATOR \', \') as catalog, 
				GROUP_CONCAT(DISTINCT pp.name SEPARATOR \', \') as price
				from showcase_models m
				INNER JOIN showcase_producers p on p.producer_id = m.producer_id
				INNER JOIN showcase_catalog c on c.catalog_id = m.catalog_id
				LEFT JOIN showcase_iprops n on m.model_id = n.model_id
				LEFT JOIN showcase_prices pp on (n.price_id = pp.price_id)
				GROUP BY p.producer
				order by count DESC');

			$listcost = Data::fetchto('SELECT p.producer_nick, count(*) as count FROM showcase_models m
				INNER JOIN showcase_producers p on (p.producer_id = m.producer_id)
				GROUP BY p.producer_nick
				','producer_nick');

			$costs = Data::fetchto('SELECT pr.producer_nick, count(distinct i.model_id) as count FROM showcase_items i
				left join showcase_models m on (m.model_id = i.model_id)
				left join showcase_producers pr on (m.producer_id = pr.producer_id)
				left join showcase_iprops ip on (i.model_id = ip.model_id and i.item_num = ip.item_num and ip.prop_id = :cost_id)
				WHERE ip.number is null
				GROUP BY m.producer_id
				','producer_nick',[':cost_id' => $cost_id]);

			foreach($list as $i => $row) {
				if (empty($costs[$row['producer_nick']])) $list[$i]['Без цен'] = 0;
				else $list[$i]['Без цен'] = $costs[$row['producer_nick']]['count'];	
			}

			$images = Data::fetchto('SELECT pr.producer_nick, count(DISTINCT m.model_id) as count FROM showcase_models m
				inner join showcase_producers pr on (m.producer_id = pr.producer_id)
				inner join showcase_iprops n on (n.model_id = m.model_id and n.prop_id = :image_id)
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
				inner join showcase_iprops n on (n.model_id = m.model_id and n.prop_id = :price_id)
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
				$producer = $row['producer'];
				if (isset($opt['producers'][$producer])) {
					$list[$i] += $opt['producers'][$producer];
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
		
		$list = Data::all('SELECT p.producer, p.producer_nick, g.group, g.group_nick, m.article_nick, m.article, count(*) as `count`, c.name  as catalog, 
			n.number as Цена,
			iv.value as img
			FROM showcase_models m
			INNER JOIN showcase_producers p on p.producer_id = m.producer_id
			INNER JOIN showcase_groups g on g.group_id = m.group_id
			LEFT JOIN showcase_props ps on (ps.prop_nick = "Цена")
			LEFT JOIN showcase_iprops n on (n.model_id = m.model_id and n.prop_id = ps.prop_id)

			LEFT JOIN showcase_props ps2 on (ps2.prop_nick = "images")
			LEFT JOIN showcase_iprops im on (im.model_id = m.model_id and im.prop_id = ps2.prop_id)
			LEFT JOIN showcase_values iv on (iv.value_id = im.value_id)
			
			LEFT JOIN showcase_items i on i.model_id = m.model_id
			INNER JOIN showcase_catalog c on c.catalog_id = m.catalog_id
			GROUP BY m.model_id
			order by m.model_id');
		return $list;
	}
}