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
use akiyatkin\ydisk\Ydisk;

Event::$classes['Showcase-catalog'] = function (&$obj) {
	return $obj['pos']['producer'].' '.$obj['pos']['article'].' '.$obj['name'];
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
		//echo $name;
		//exit;
		if ($action == 'loadproducer') $res = Catalog::actionLoadProducer($name);
		if ($action == 'clearAll') $res = Data::actionClearAll();
		if ($action == 'addFiles') $res = Data::actionAddFiles($name);
		if ($action == 'addFilesAll') $res = Data::actionAddFiles();
		$ans['res'] = $res;
		return $ans;
	}
	public static function getList() {
		$options = Catalog::getOptions();

		$savedlist = Data::fetchto('SELECT c.name, count(*) as icount from showcase_catalog c 
		RIGHT JOIN showcase_models m on m.catalog_id = c.catalog_id
		RIGHT JOIN showcase_items i on i.model_id = m.model_id
    	GROUP BY c.name','name');

		foreach ($savedlist as $name => $row) {
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
			$list[$name] += array(
				'name' => $name,
				'isfile' => false,
				'isopt' => false,
				'isdata' => false,
				'order' => 0,
				'producer' => $name,
				'producer_nick' => Path::encode($name)
			);
			
			$list[$name]['isglob'] = !!$list[$name]['producer'];
			//if (empty($opt['isfile']) && empty($opt['time'])) {
			//	unset($list[$name]);
			//}
		}
		
		if ($filename) return $list[$filename];
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
		$order = 0;
		foreach ($list as $filename => $val) {
			$order++;	
			$producer_nick = $filename;

			if (isset($options[$filename]['producer'])) {
				$producer_nick = $options[$filename]['producer'];
			}
			Catalog::loadMeta($filename, $producer_nick, $order);
		}
		$savedlist = Data::fetchto('SELECT unix_timestamp(time) as time, catalog_id, name, `count` from showcase_catalog','name');
		foreach ($savedlist as $name => $row) {
			if (isset($list[$name])) continue; // Если нет файла
			if (!empty($row['time'])) continue; // Если нет даты внесения, то и данных нет
			Data::exec('DELETE from showcase_catalog where catalog_id = ?',[$row['catalog_id']]);
		}
	}
	
	public static function readCatalog($name, $src) {
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
		
		/*if (!empty($opt['producer'])) {
			Xlsx::runPoss($data, function (&$pos) use (&$opt) {
				$pos['Производитель'] = $opt['producer'];
				$pos['producer'] = $opt['producer_nick'];
			});
		}*/
		return $data;
	}
	
	
	
	public static function actionRemove($name, $src = false) {
		//Удаляются ключи model_id, mitem_id

		Data::exec('DELETE m, i, mv, mn, mt
			FROM showcase_catalog c 
			LEFT JOIN showcase_models m ON m.catalog_id = c.catalog_id
			LEFT JOIN showcase_items i ON i.model_id = m.model_id
			LEFT JOIN showcase_mvalues mv ON mv.model_id = m.model_id
			LEFT JOIN showcase_mnumbers mn ON mn.model_id = m.model_id
			LEFT JOIN showcase_mtexts mt ON mt.model_id = m.model_id
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
		
		$groups = Catalog::applyGroups($data, $catalog_id, $order, $ans);
		$props = array();
		$count = 0;

		$ans['Принято моделей'] = 0;
		$ans['Принято позиций'] = 0;
		//$ans['Пропущено из-за конфликта с другими данными'] = 0;
		$db = &Db::pdo();
		$db->beginTransaction();
		

		$prop_id = Data::initProp('Иллюстрации','value'); //Для событий в цикле
		Xlsx::runPoss( $data, function (&$pos) use ($name, &$ans, &$filters, &$devcolumns, &$props, $catalog_id, $order, $time, &$count) {
			$count++;
			if (isset($pos['items'])) $count += sizeof($pos['items']); //Считаем с позициями. В items одного items нет - он уже в описании модели.
			$article_id = Data::initArticle($pos['Артикул']);
			$producer_id = Data::initProducer($pos['Производитель']);

			//Длинное имя группы, например: "Автомобильные регистраторы #avtoreg" берётся из Наименования в descr. Id encod(всё) title то что до решётки. Из title нельзя получить id.
			$group_nick = $pos['gid'];
			$group_id = Data::col('SELECT group_id FROM showcase_groups WHERE group_nick = ?',[$group_nick]);

			$model_id = Catalog::initModel($name, $producer_id, $article_id, $catalog_id, $order, $time, $group_id); //У существующей модели указывается time

			if (!$model_id) {
				//$ans['Пропущено из-за конфликта с другими данными']++;
				return; //Каталог не может управлять данной моделью, так как есть более приоритетный источник
			}
		
			Catalog::initItem($model_id, 0, '');

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
		
			$r = Catalog::writeProps($model_id, $pos, 0);

			$obj = [
				'model_id' => $model_id, 
				'pos' => &$pos,
				'name' => $name
			];
			Event::fire('Showcase-catalog.onload', $obj); 
			//Срабатывает только для моделей. МОжно добавить недостающие свойства. 
			//Сгенерировать id для items

			$ans['Принято моделей']++;
			$ans['Принято позиций']++;
			if (isset($pos['items'])) {
				$item_num = 0;
				foreach ($pos['items'] as $item) {
					$item_num++;
					Catalog::initItem($model_id, $item_num, $item['id']); //itemrows и id
					$r = Catalog::writeProps($model_id, $item, $item_num);
					$ans['Принято позиций']++;
				}
				
				//$ans['Принято позиций']--;
			}
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

			$isprice = Data::col('SELECT price_id from `showcase_m'.$type.'s` where prop_id = ? and model_id = ?',[$prop_id, $model_id]);//Если этой свойство у модели установлено из прайса, пропускаем
			if ($isprice) continue;

			if ($type == 'text') {
				$order++;
				$r = true;
				Data::lastId('INSERT INTO `showcase_mtexts`(model_id, item_num, `prop_id`,`text`,`order`) VALUES(?,?,?,?,?)',
					[$model_id, $item_num, $prop_id, $val, $order]
				);
				
			} else {
				$strid = ($type == 'number')? 'number' : 'value_id';
				$ar = (in_array($prop, $options['justonevalue']))? [$val] : explode(',', $val);
				foreach ($ar as $v) {
					$order++;
					$v = trim($v);
					if ($v === '') continue;
					$v = ($type=='value')? Data::initValue($v) : $v;
					$test = $prop_id.':'.$v;
					if (isset($propvals[$test])) continue; //Уже вставлен
					$propvals[$test] = true;
					$r = true;

					Data::lastId('INSERT INTO `showcase_m'.$type.'s`(model_id, item_num, `prop_id`,'.$strid.',`order`) VALUES(?,?,?,?,?)',
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
		Data::exec(
			'INSERT INTO showcase_items (model_id, item_num, item_nick, item) VALUES(?,?,?,?)',
			[$model_id, $item_num, $nick, $value]
		);	
	}
	public static function clearCatalog($catalog_id) {

		//Удалить все свойства у всех моделей
		Data::exec('DELETE p FROM showcase_mvalues p
			INNER JOIN showcase_models m ON m.model_id = p.model_id
			WHERE p.price_id is null and m.catalog_id = ?', [$catalog_id]);

		Data::exec('DELETE p FROM showcase_mnumbers p
			INNER JOIN showcase_models m ON m.model_id = p.model_id
			WHERE p.price_id is null and m.catalog_id = ?', [$catalog_id]);

		Data::exec('DELETE p FROM showcase_groups p
			INNER JOIN showcase_models m ON m.model_id = p.model_id
			WHERE p.price_id is null and m.catalog_id = ?', [$catalog_id]);

		Data::exec('DELETE p FROM showcase_mtexts p
			INNER JOIN showcase_models m ON m.model_id = p.model_id
			WHERE p.price_id is null and m.catalog_id = ?', [$catalog_id]);
		/*echo $catalog_id;
		echo '<br>';
		echo $count;
		exit;*/
		
	}
	public static function clearModel($model_id) {
		$files = '("'.implode('","', Data::$files).'")';
		$sql = 'DELETE t FROM showcase_mvalues t, showcase_props p
			WHERE p.prop_id = t.prop_id 
			AND p.prop_nick not in '.$files.' 
			AND t.model_id = ? AND t.price_id is null';
		Data::exec($sql, [$model_id]);
		Data::exec('DELETE t FROM showcase_mnumbers t WHERE t.model_id = ? and t.price_id is null', [$model_id]);
		Data::exec('DELETE t FROM showcase_mtexts t WHERE t.model_id = ? and t.price_id is null', [$model_id]);
		Data::exec('DELETE t FROM showcase_items t WHERE t.model_id = ?', [$model_id]);
		//Может остаться свойство с айтемом которого нет
	}
	public static function initModel($name, $producer_id, $article_id, $catalog_id, $order, $time, $group_id) {
		//$name только для кэша, чтобы модели-дубли заменяли друг друга.
		
		$model_id = Once::func( function ($name, $producer_id, $article_id) use ($catalog_id, $order, $time, $group_id) {
		
			$row = Data::fetch('SELECT sm.model_id, sm.catalog_id, sc.order, sm.group_id
				FROM showcase_models sm, showcase_catalog sc 
				WHERE sc.catalog_id = sm.catalog_id And sm.producer_id = ? AND sm.article_id = ?', [$producer_id, $article_id]);
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
				'INSERT INTO showcase_models (producer_id, article_id, catalog_id, `time`, group_id) VALUES(?,?,?,from_unixtime(?),?)',
				[$producer_id, $article_id, $catalog_id, $time, $group_id]
			);
			
			return $model_id;
		}, [$name, $producer_id, $article_id]);
		
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
		Data::exec('DELETE m, i, mv, mn, mt
			FROM showcase_models m 
			LEFT JOIN showcase_mvalues mv ON mv.model_id = m.model_id
			LEFT JOIN showcase_mnumbers mn ON mn.model_id = m.model_id
			LEFT JOIN showcase_mtexts mt ON mt.model_id = m.model_id
			LEFT JOIN showcase_items i ON i.model_id = m.model_id
			WHERE m.time != from_unixtime(?) and catalog_id = ?',[$time, $catalog_id]);
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
		/*echo '<pre>';
		Xlsx::runGroups($data, function &(&$group){
			unset($group['data']);
			$r = null;
			return $r;
		});
		print_r($data);
		exit;*/
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