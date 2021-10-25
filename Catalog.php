<?php
namespace akiyatkin\showcase;
use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\once\Once;
use infrajs\excel\Xlsx;
use infrajs\db\Db;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Showcase;
use infrajs\event\Event;
use infrajs\ans\Ans;
use infrajs\access\Access;
use akiyatkin\ydisk\Ydisk;
use akiyatkin\showcase\api2\API;

Event::$classes['Showcase-catalog'] = function (&$obj) {
	return $obj['pos']['producer'].' '.$obj['pos']['article'].' '.$obj['name'];
};
Event::$classes['Showcase-onloadprice'] = function () {
	return '';
};
class Catalog {
	public static function action($type = 'table') {
		$action = Ans::GET('action');
		if (!$action) {
			$action = Ans::REQ('action');
			if ($action) {
				if (Showcase::$conf["ydisk"]) Ydisk::replaceAll();
			}
		}
		if ($action) {
			Access::adminSetTime();
		}
		
		
		Catalog::init();
		Prices::init();
		
		$ans = array();
		$ans['post'] = $_POST;
		$ans['conf'] = Showcase::$conf;

		
		$name = Ans::REQ('name');
		$src = Ans::REQ('src');
		$type = Ans::REQ('type','string',$type);
		
		$res = null;
		if ($type == 'table') {
			if ($action == 'load') $res = Catalog::actionLoad($name, $src);
			if ($action == 'read') $res = Catalog::actionRead($name, $src);
			if ($action == 'remove') $res = Catalog::actionRemove($name, $src);
			if ($action == 'loadAll') $res = Catalog::actionLoadAll();
		} else if ($type == 'price') {
			if ($action == 'load') $res = Prices::actionLoad($name, $src);
			if ($action == 'read') $res = Prices::actionRead($name, $src);
			if ($action == 'remove') $res = Prices::actionRemove($name, $src);
			if ($action == 'loadAll') $res = Prices::actionLoadAll();
		}
		
		if ($action == 'loadproducer') $res = Catalog::actionLoadProducer($name);
		if ($action == 'clearAll') $res = Data::actionClearAll();
		if ($action == 'addFiles') $res = Data::actionAddFiles($name);
		if ($action == 'addFilesAll') $res = Data::actionAddFiles();


		if (
			in_array($action, ['loadproducer','addFiles','addFilesAll']) || 
			($type == 'price' && in_array($action, ['loadAll','load']))
		) {
			Event::fire('Showcase-priceonload');
		}
		$ans['res'] = $res;
		return $ans;
	}
	public static function getList() {
		$options = Catalog::getOptions();
// echo '<pre>';
// print_r($options);
// exit;
		$savedlist = Data::fetchto('SELECT c.name, count(*) as icount from showcase_catalog c 
		RIGHT JOIN showcase_models m on m.catalog_id = c.catalog_id
		RIGHT JOIN showcase_items i on i.model_id = m.model_id
    	GROUP BY c.name','name');
		
		foreach ($savedlist as $name => $row) {
			if (!$name) continue;
			$options[$name] = $options[$name] + $row;
		}

		$savedlist = Data::fetchto('SELECT c.name, c.ans as ans from showcase_catalog c','name');
		foreach ($savedlist as $name => $row) {
			$options[$name] =  $row + $options[$name];
		}
		foreach ($options as $name => $row) {
			if (isset($row['ans'])) $options[$name]['ans'] = Load::json_decode($row['ans']);
		}
		return $options;
	}
	
	public static function actionLoadProducer($producer_nick) {
		$options = Catalog::getList();
		$res = [];
		foreach ($options as $name => $row) {
			if (empty($row['isfile'])) {
				if (!empty($row['icount'])) {
					$res['Данные - удаляем данные удалённого файла '.$name] = Catalog::actionRemove($name);
				}
			} else if (!$row['producer_nick'] || $row['producer_nick'] == $producer_nick) {
				$src = Showcase::$conf['tables'].$row['file'];
				$res['Данные - вносим '.$name] = Catalog::actionLoad($name, $src);
			}

			
		}
		$options = Prices::getList();
		foreach ($options as $name => $row) {
			if (empty($row['isfile'])) {
				if (!empty($row['icount'])) {
					$src = Showcase::$conf['prices'].$row['file'];
					$res['Прайс - удаляем '.$name] = Prices::actionRemove($name, $src);
				}
			} else if (!$row['producer_nick'] || $row['producer_nick'] == $producer_nick) {
				$src = Showcase::$conf['prices'].$row['file'];
				$res['Прайс - вносим '.$name] = Prices::actionLoad($name, $src);
			}
		}

		return $res;
	}
	public static function actionLoadAll() {
		$options = Catalog::getList();
		$res = [];
		foreach ($options as $name => $row) {
			if (empty($row['isfile'])) {
				if (!empty($row['icount'])) {
					$res['Данные - удаляем данные удалённого файла '.$name] = Catalog::actionRemove($name);
				}
			} else {
				if (!isset($row['time']) || $row['time'] < $row['mtime']) {
					$src = Showcase::$conf['tables'].$row['file'];
					$res['Данные - вносим '.$name] = Catalog::actionLoad($name, $src);
				}
			}
		}
		return $res;
	}
	public static function getDefOpt($name) {
		return array(
			'name' => $name,
			'isfile' => false,
			'isopt' => false,
			'isdata' => false,
			'source' => 'file', //file или src
			'order' => 0,
			'producer' => $name,
			'producer_nick' => Path::encode($name)
		);
	}
	public static function getOptions($filename = false) {//3 пересечения Опциии, Файлы, БазаДанных
		$list = Data::getOptions('catalog');
		$filelist = Data::getFileList(Showcase::$conf['tables']);
		
		foreach ($filelist as $name => $val) { // По файлам
			if (!isset($list[$name])) $list[$name] = array();
			$list[$name] += $filelist[$name];
			$list[$name]['isfile'] = true;
		}
		$savedlist = Data::fetchto('SELECT unix_timestamp(time) as time, `order`, duration, name, `count` from showcase_catalog','name');
		foreach ($savedlist as $name => $val) { // По файлам
			if (!isset($list[$name])) $list[$name] = array();
			$list[$name] += $savedlist[$name];
			if (!$savedlist[$name]['time']) continue;// Данные ещё не вносились
		}

		foreach ($list as $name => $opt) { // По опциям
			$list[$name] += Catalog::getDefOpt($name);
			
			$list[$name]['isglob'] = !!$list[$name]['producer'];
			//if (empty($opt['isfile']) && empty($opt['time'])) {
			//	unset($list[$name]);
			//}
		}
		
		if ($filename) {
			if (isset($list[$filename])) return $list[$filename];
			return Catalog::getDefOpt($filename);
		}
		uasort($list, function($a, $b) {
			if ($b['isfile'] && !$a['isfile']) return 1; //Сначало файлы, потом база данных, потом опции
			if ($b['order'] < $a['order']) return 1;
			return 0;
		});
		return $list;
	}
	
	public static function init() {
		Data::init();
		$conf = Showcase::$conf;
		$options = Catalog::getOptions();
		$list = Data::getFileList($conf['tables']);
		$sources = [];
		foreach ($list as $filename => $val) {
			$sources[$filename] = isset($val['producer']) ? $val['producer'] : $filename;
		}
		foreach ($options as $filename => $val) {
			if (isset($list[$filename])) continue;
			$sources[$filename] = isset($val['producer']) ? $val['producer'] : $filename;
		}
		$order = 0;
		foreach ($sources as $filename => $producer_nick) {
			$order++;
			Catalog::loadMeta($filename, $producer_nick, $order);
		}
		$savedlist = Data::fetchto('SELECT unix_timestamp(time) as time, catalog_id, name, `count` from showcase_catalog','name');
		foreach ($savedlist as $name => $row) {
			if (isset($sources[$name])) continue; // Если нет файла
			if (!empty($row['time'])) continue; // Если нет даты внесения, то и данных нет
			Data::exec('DELETE from showcase_catalog where catalog_id = ?',[$row['catalog_id']]);
		}
	}
	
	public static function readCatalog($name, $src) {
		$ext = Path::getExt($src);
		if ($ext == 'yml') {
			$option = Catalog::getOptions($name);
			$roottitle = 'Каталог';
			$rootid = Path::encode($roottitle);
			$src = Path::theme($src);
			if (!$src) return false;
			$xml = simplexml_load_file($src);
			$groups = [];
			$ids = [];
			$list = $xml->shop->categories->category;
			
			for ($i = 0, $l = sizeof($list); $i < $l; $i++) {

				$id = $i;
				$name = (string) $list[$i];
				$attr = $list[$i]->attributes();
				$id = (string) $attr['id'];
				$parent_id = (string) $attr['parentID'];
				$nick = Path::encode($name);
				$ids[$id] = $nick;
				$groups[$nick] = [
					'parent_id' => $parent_id,
					'name' => $name,
					'title' => $name,
					'group' => $name,
					'Группа' => $name,
					'gid' => $rootid,
					'id' => $nick,
					'data' => [],
					'childs' => []
				];
			}

			$list = $xml->shop->offers->offer;
			$poss = [];
			for ($i = 0, $l = sizeof($list); $i < $l; $i++) {
				$pos = $list[$i];
				$id = (int) $pos->categoryId;
				$group = $groups[$ids[$id]];
				$prodart = Path::encode($pos->vendor).'-'.Path::encode($pos->vendorCode);
				$cost = (int) $pos->price;
				$more = [];
				$more['Наименование'] = strip_tags($pos->name);
				$more['Цена'] = $cost;
				$more['Описание'] = strip_tags($pos->description);
				$more['Страна'] = strip_tags($pos->country_of_origin);
				$more['Иллюстрации'] = strip_tags($pos->picture);

				foreach ($pos->param as $param) {
					$name = strip_tags($param->attributes()['name']);
					if ($name == 'articul') continue;
					$unit = strip_tags($param->attributes()['unit']);
					if ($unit) $name .= ', '. $unit;
				
					$more[$name] = strip_tags($param);
				}
				
				
				
				$r = $pos->attributes()['available'] == 'true';
				if ($r) $more['Наличие'] = 'В наличии';
				else $more['Наличие'] = 'На заказ';

				if ($r) {

					$buy = (int) $pos->buy_price;
					if ($buy) {
						//Максимальная скидка которую можно дать от розничной цены
						$discount = round((1 - $buy / $cost) * 100);
						if ($discount >= 25) {
							$more['Наличие'] = 'Выгодно';
						}
					}
				}
				

				
				$groups[$ids[$id]]['data'][$prodart] = [
					'more' => $more,
					'gid' => $group['id'],
					'group' => $group['name'],
					'Группа' => $group['name'],
					'Производитель' => (string) $pos->vendor,
					'Артикул' => (string) $pos->vendorCode,
					'producer' => Path::encode($pos->vendor),
					'article' => Path::encode($pos->vendorCode)
				];

			}
			
			if (isset($options['structure'])) {
				foreach ($groups as $i => &$group) {
					$parent_id = $group['parent_id'];
					if (!$parent_id) continue;
					if (empty($ids[$parent_id])) continue;
					$parent_nick = $ids[$parent_id];
					$parent = &$groups[$parent_nick];
					$group['gid'] = $parent['title'];
					$parent['childs'][] = $group;
					$group['del'] = true;
					unset($group['parent_id']);
				}
				foreach ($groups as $i => &$group) {
					if (!empty($group['del'])) {
						unset($groups[$i]);
					}
				}
			} else {
				foreach($groups as $k=>$group) {
					if (empty($group['data'])) unset($groups[$k]);
				}
			}

			$data = [
				'title' => $roottitle,
				'name' => $roottitle,
				'group' => $roottitle,
				'gid' => false,
				'id' => $rootid,
				'data' => [],
				'childs' => array_values($groups)
			];	

			
			// if (isset($option['root'])) {
			// 	$root = Path::encode($option['root']);
			// 	$g = Xlsx::runGroups($data, function &($g) use ($root) {
			// 		//if ($g['id'] == $root) return $g;
			// 		//echo sizeof($g['data']).'<br>';
			// 		$r = null;
			// 		return $r;
			// 	});
			// 	if ($g) {
			// 		$g['title'] = 'Каталог';
			// 		$g['name'] = 'Каталог';
			// 		$g['group'] = 'Каталог';
			// 		$g['Группа'] = 'Каталог';
			// 		$g['gid'] = false;
			// 		$g['id'] = Path::encode('Каталог');
			// 		//$data = $g;
					
			// 	}
			// }
			$g = Xlsx::runGroups($data, function &(&$g) {
				$g['data'] = array_values($g['data']);
				$r = null;
				return $r;
			});

			
			
		} else {
			$conf = Showcase::$conf;
			$opt = Catalog::getOptions($name);
			$data = Xlsx::init($src, array(
				'root' => $conf['title'],
				'more' => true,
				//'Не идентифицирующие колонки' => ["Файл","Файлы","Фото","Иллюстрации","Описание"],
				'Группы уникальны' => $conf['Группы уникальны'],
				'Игнорировать имена файлов' => true,
				'Производитель по умолчанию' => $opt['producer'],
				'Игнорировать имена листов' => $conf['ignorelistname'],
				'listreverse' => false,
				'Известные колонки' => array("Артикул","Производитель")
			));	
		}
		// echo '<pre>';
		// print_r($data);
		// exit;
		
		/*if (!empty($opt['producer'])) {
			Xlsx::runPoss($data, function (&$pos) use (&$opt) {
				$pos['Производитель'] = $opt['producer'];
				$pos['producer'] = $opt['producer_nick'];
			});
		}*/
		// echo '<pre>';
		// 			print_r($data);
		// 			exit;
		return $data;
	}
	
	
	
	public static function actionRemove($name, $src = false) {
		//Удаляются ключи model_id, mitem_id

		Data::exec('DELETE m, i, mv
			FROM showcase_catalog c 
			LEFT JOIN showcase_models m ON m.catalog_id = c.catalog_id
			LEFT JOIN showcase_items i ON i.model_id = m.model_id
			LEFT JOIN showcase_iprops mv ON mv.model_id = m.model_id
			WHERE c.name = ?', [$name]);	

		//Можно удалить группы, которые созданы этими данными и которые пустые, точней те группы у которых нет существующего catalog_id, так как мы его только что удалили.
		Data::exec('DELETE g from showcase_groups g
			RIGHT JOIN showcase_catalog c ON (c.catalog_id = g.catalog_id and c.catalog_id is null)
			RIGHT JOIN (select *, count(model_id) as count from showcase_models group by group_id) m ON (m.group_id = g.group_id and m.count = 0)');

		if ($src && FS::is_file($src)) { //ФАйл есть запись остаётся
			Data::exec('UPDATE showcase_catalog SET time = null WHERE name = ?', [$name]);
		} else {
			Data::exec('DELETE FROM showcase_catalog WHERE name = ?', [$name]);
		}
		
	}
	public static function actionRead($name, $src)
	{
		$data = Catalog::readCatalog($name, $src);
		if (!$data) return false;
		Xlsx::runGroups($data, function &(&$group) {
			$group['data'] = sizeof($group['data']);
			unset($group['head']);
			unset($group['unset']);
			unset($group['gid']);
			unset($group['Группа']);
			unset($group['pitch']);
			unset($group['path']);
			unset($group['name']);
			unset($group['id']);
			if(empty($group['childs']))unset($group['childs']);
			$r = null;
			return $r;
		});
		return $data;
	}
	public static function actionLoad($name, $src)
	{
		$time = time();
		$row = Data::fetch('SELECT catalog_id, `order` from showcase_catalog where name = ?',[$name]);
		$catalog_id = $row['catalog_id'];
		$order = $row['order'];
		
		$option = Catalog::getOptions($name);
		

		$ans = array('Данные' => $src);
		if ($option['producer']) $ans['Производитель'] = '<a href="/-showcase/producers/'.$option['producer_nick'].'">'.$option['producer'].'</a>';
		$data = Catalog::readCatalog($name, $src);
		if (!$data) return false;

		Catalog::applyGroups($data, $catalog_id, $order, $ans);
	
		
		
		
		$count = 0;

		$ans['Принято моделей'] = 0;
		$ans['Принято позиций'] = 0;
		$ans['Индекс поиска обновлён'] = 0;
		//$ans['Пропущено из-за конфликта с другими данными'] = 0;
		$db = &Db::cpdo();
		$db->beginTransaction();
		

		//$prop_id = Data::initProp('Иллюстрации','value'); //Для событий в цикле
		Xlsx::runPoss( $data, function (&$pos) use ($name, &$ans, &$filters, &$devcolumns, &$props, $catalog_id, $order, $time, &$count) {
			$count++;
			if (isset($pos['items'])) $count += sizeof($pos['items']); //Считаем с позициями. В items одного items нет - он уже в описании модели.

			//$article_id = Data::initArticle($pos['Артикул']);
			//exit;
			$producer_id = Data::initProducer($pos['Производитель']);

			//Длинное имя группы, например: "Автомобильные регистраторы #avtoreg" берётся из Наименования в descr. Id encod(всё) title то что до решётки. Из title нельзя получить id.
			$group_nick = $pos['gid'];
			$group_id = Data::col('SELECT group_id FROM showcase_groups WHERE group_nick = ?',[$group_nick]);

			$model_id = Catalog::initModel($producer_id, $pos['Артикул'], $catalog_id, $order, $time, $group_id); //У существующей модели указывается time

			if (!$model_id) {
				//$ans['Пропущено из-за конфликта с другими данными']++;
				return; //Каталог не может управлять данной моделью, так как есть более приоритетный источник
			}

			if (isset($pos['items'])) { //1 item уже в модели надо его вынести в отдельный items и удалить из модели
				$item = array();
				$item['id'] = $pos['id'];
				foreach ($pos['itemrows'] as $p => $v) {
					if (!isset($pos['more'][$p])) continue;
					$item['more'][$p] = $pos['more'][$p]; //Игнорируем разные группы для items
					unset($pos['more'][$p]);//Перенесли свойство в items
				}
				array_unshift($pos['items'], $item);
			}

			$obj = [
				'model_id' => $model_id, 
				'pos' => &$pos,
				'name' => $name
			];

			Event::fire('Showcase-catalog.onload', $obj); 
			//Срабатывает только для моделей. МОжно добавить недостающие свойства. 
			//Сгенерировать id для items
			
			
			
			//Catalog::writeProps($model_id, $pos, 0);
			$ans['Принято моделей']++;
			if (isset($pos['items'])) {
				$item_num = 0;
				foreach ($pos['items'] as $item) {
					$item_num++;
					Catalog::initItem($model_id, $item_num, $item['id']);
					Catalog::writeProps($model_id, $pos, $item_num);
					Catalog::writeProps($model_id, $item, $item_num);
					$ans['Принято позиций']++;
				}
			} else {
				$item_num = 1;
				$ans['Принято позиций']++;
				Catalog::initItem($model_id, $item_num, '');
				Catalog::writeProps($model_id, $pos, 1);

			}
			//Надо удалить всё после $item_num вместе со значениями в том числе файлов
			Data::exec('DELETE i, ip FROM showcase_items i
				left join showcase_iprops ip on (i.model_id = ip.model_id and i.item_num = ip.item_num)
				WHERE i.model_id = ? and i.item_num > ?', [$model_id, $item_num]);

			//Нужно создать индекс поиска для данной модели $model_id
			$r = API::updateSearchId($model_id);
			if ($r)	$ans['Индекс поиска обновлён']++;
		});
		Catalog::removeOldModels($time, $catalog_id);
		$duration = (time() - $time);
		$jsonans = Load::json_encode($ans);
		Data::exec('UPDATE showcase_catalog SET `time` = from_unixtime(?), `duration` = ?, count = ?, ans = ? 
			WHERE catalog_id = ?', [$time, $duration, $count, $jsonans, $catalog_id]);
		$db->commit();

		

		return $ans;
	}
	public static function writeProps($model_id, $item, $item_num = 0) {
		if (empty($item['more'])) return false;
		$options = Data::loadShowcaseConfig();
		$order = 0;
		$r = false;
		
		$propval = []; //Нужно убедится что нет дублей с разным написанием.
		foreach ($item['more'] as $prop => $val) {
			$type = Data::checkType($prop);
			$prop_id = Data::initProp($prop, $type);

			$isprice = Data::col('SELECT price_id from `showcase_iprops` where prop_id = ? and model_id = ? and item_num = ?',[$prop_id, $model_id, $item_num]);//Если этой свойство у модели установлено из прайса, пропускаем
			if ($isprice !== false) continue;

			if ($type == 'text') {
				$order++;
				$r = true;
				Data::lastId('INSERT INTO `showcase_iprops`(model_id, item_num, `prop_id`,`text`,`order`) VALUES(?,?,?,?,?)',
					[$model_id, $item_num, $prop_id, $val, $order]
				);
				
			} else {
				$prop_nick = Path::encode($prop);
				$strid = ($type == 'number')? 'number' : 'value_id';
				$ar = (in_array($prop_nick, $options['justonevalue']))? [$val] : explode(',', $val);
				
				foreach ($ar as $v) {
					$order++;
					$v = trim($v);
					if ($v === '') continue;
					
					$v = ($type == 'value')? Data::initValue($v) : $v;
					$test = $prop_id.':'.$v;
					if (isset($propvals[$test])) continue; //Уже вставлен
					$propvals[$test] = true;
					$r = true;

					Data::lastId('INSERT INTO `showcase_iprops`(model_id, item_num, `prop_id`,'.$strid.',`order`) VALUES(?,?,?,?,?)',
						[$model_id, $item_num, $prop_id, $v, $order]
					);
				}	
			}

		}
		return $r;
	}
	public static function getCatalog($name) {
		$row = Data::fetch('SELECT name, unix_timestamp(time) as time, `order` from showcase_catalog where name = ?',[$name]);
		return $row;
	}
	
	public static function initItem($model_id, $item_num, $value) {
		$nick = Path::encode($value);
		Data::exec('DELETE FROM showcase_items WHERE model_id = ? and item_num = ?', [$model_id, $item_num]);
		Data::exec(
			'INSERT INTO showcase_items (model_id, item_num, item_nick, item) VALUES(?,?,?,?)',
			[$model_id, $item_num, $nick, $value]
		);	
	}
	public static function clearCatalog($catalog_id) {

		//Удалить все свойства у всех моделей
		Data::exec('DELETE p FROM showcase_iprops p
			INNER JOIN showcase_models m ON m.model_id = p.model_id
			WHERE p.price_id is null and m.catalog_id = ?', [$catalog_id]);

		Data::exec('UPDATE showcase_groups SET `catalog_id` = null WHERE catalog_id = ?',[$catalog_id]);
	}
	public static function clearModel($model_id) {
		$files = '("'.implode('","', Data::$files).'")';
		Data::exec('DELETE t FROM showcase_iprops t, showcase_props p 
			WHERE p.prop_id = t.prop_id 
				AND t.model_id = ? 
				AND t.price_id IS NULL 
				AND p.prop_nick not in '.$files, [$model_id]);
		//Порядок не должен меняться у item-ов иначе надо перевнести прайс! Не на item_nick с неизменностью свойств
	}
	public static function initModel($producer_id, $article, $catalog_id, $order, $time, $group_id) {
		//$catalog_id для кэша, чтобы модели-дубли заменяли друг друга. Тогда зачем кэш, для позиций?
		$article_nick = Path::encode($article);
		

		$model_id = Once::func( function ($catalog_id, $producer_id, $article_nick) use ($article, $order, $time, $group_id) {
		
			$row = Data::fetch('SELECT sm.model_id, sm.catalog_id, sc.order, sm.group_id
				FROM showcase_models sm, showcase_catalog sc 
				WHERE sc.catalog_id = sm.catalog_id And sm.producer_id = ? AND sm.article_nick = ?', [$producer_id, $article_nick]);
			if ($row) {
				$model_id = $row['model_id'];
				if ($catalog_id != $row['catalog_id']) { //Модель появилась из другого каталога
					if ($order > $row['order']) return false;//Новый каталог в списке позже и не управляет этой позицией
				}
				Catalog::clearModel($model_id); //Нашли модель и удалили у неё свойства и items, кроме files и prices
				Data::exec('UPDATE showcase_models SET time = from_unixtime(?), catalog_id = ?, group_id = ? WHERE model_id = ?',[$time, $catalog_id, $group_id, $model_id]);
				return $model_id;
			}
			$model_id = Data::lastId(
				'INSERT INTO showcase_models (producer_id, article, article_nick, catalog_id, `time`, group_id) VALUES(?,?,?,?,from_unixtime(?),?)',
				[$producer_id, $article, $article_nick, $catalog_id, $time, $group_id]
			);
			
			return $model_id;
		}, [$catalog_id, $producer_id, $article_nick]);
		
		return $model_id;
	}
	
	public static function loadMeta($name, $producer_nick, $order) {
		$producer_id = Data::col('SELECT producer_id FROM showcase_producers where producer_nick = ?', [$producer_nick]);
		
		$row = Data::fetch('SELECT catalog_id, `order`, producer_id from showcase_catalog WHERE name = ?', 
			[$name]);		
		if ($row) {
			if ($row['order'] != $order || $row['producer_id'] != $producer_id) {
				Data::exec('UPDATE showcase_catalog SET `order` = ?, producer_id = ? WHERE catalog_id = ?', 
					[$order, $producer_id, $row['catalog_id']]);
			}
			return $row['catalog_id'];
		}
		return Data::lastId(
			'INSERT INTO `showcase_catalog`(`name`,`producer_id`,`order`) VALUES(?,?,?)',
			[$name, $producer_id, $order]
		);
		
	}
	
	public static function removeOldModels($time, $catalog_id) {
		Data::exec('DELETE m, i, mv
			FROM showcase_models m 
			LEFT JOIN showcase_iprops mv ON mv.model_id = m.model_id
			LEFT JOIN showcase_items i ON i.model_id = m.model_id
			WHERE m.time != from_unixtime(?) and catalog_id = ?',[$time, $catalog_id]);

		//Если позиций у модели стало меньше, надо удалить свободные свойства. Чтобы не было ошибки. Но всё равно нужно внести прайс повторно.
		$r = Data::exec('DELETE ip FROM `showcase_iprops` ip 
			left join showcase_items i on (i.model_id = ip.model_id and i.item_num = ip.item_num)
			where i.item_num is null');
	}
	
	public static function getGroupId($group_nick) {
		if (!$group_nick) return null;
		$sql = 'SELECT group_id from showcase_groups where group_nick = ?';
		$stmt = Db::stmt($sql);
		$stmt->execute([$group_nick]);
		$group_id = $stmt->fetchColumn();
		return $group_id;
	}
	
	
	public static function applyGroups($data, $catalog_id, $order, &$ans) { //order нового каталога
		$groups = array();
		$ans['Найдено групп'] = 0;
		$ans['Новых групп'] = 0;
		
		$gorder = 0;
		Xlsx::runGroups($data, function &($group) use ($catalog_id, &$groups, $order, &$ans, &$gorder){
			$gorder++;
			$ans['Найдено групп']++;
			$r = null;
			
			$group_nick =  $group['id'];
			$parent_nick = $group['gid'];
			
			if (isset($groups[$group_nick])) return $r;
			$row = Data::fetch('SELECT g1.group, g1.group_id, c.order, g2.group_nick as parent_nick, c.catalog_id 
					FROM showcase_groups g1 
					LEFT JOIN showcase_groups g2 ON g1.parent_id = g2.group_id 
					LEFT JOIN showcase_catalog c ON g1.catalog_id = c.catalog_id 
					WHERE g1.group_nick = ?',[$group_nick]);

			if ($row) {
				$group_id = $row['group_id'];
				if ($catalog_id == $row['catalog_id'] || $row['order'] > $order || !$row['catalog_id']) {
					//$order - новые данные должны стоять выше старых
					$parent_id = Catalog::getGroupId($parent_nick);

					Data::exec('UPDATE showcase_groups SET parent_id = ?, `order` = ?, `catalog_id` = ? WHERE group_id = ?',[$parent_id, $gorder, $catalog_id, $group_id]);
				}
				$groups[$group_nick] = $group_id;
				return $r;
			}
			
			$ans['Новых групп']++;
			if ($group['gid']) {
				$parent_nick = $group['gid'];
				$parent_id = Catalog::getGroupId($parent_nick);	
			} else {
				$parent_id = null;
			}

			$group_id = Data::lastId('INSERT INTO `showcase_groups`(`group`,`order`,`parent_id`,`group_nick`, `catalog_id`) VALUES(?,?,?,?,?)',[$group['title'], $gorder, $parent_id, $group_nick,$catalog_id]);
			
			$groups[$group_nick] = $group_id;
			return $r;
		});

		return $groups;
	}
}