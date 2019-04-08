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

class Catalog {
	
	public static function getList() {
		$options = Catalog::getOptions();

		$savedlist = Data::fetchto('SELECT c.name, count(*) as mcount from showcase_catalog c 
		RIGHT JOIN showcase_models m on m.catalog_id = c.catalog_id
    	GROUP BY c.name','name');
		foreach ($savedlist as $name => $row) {
			$options[$name] = $options[$name] + $row;
		}
		$savedlist = Data::fetchto('SELECT c.name, count(*) as icount from showcase_catalog c 
		RIGHT JOIN showcase_models m on m.catalog_id = c.catalog_id
    	LEFT JOIN showcase_mitems i on m.model_id = i.model_id
    	GROUP BY c.name','name');
		foreach ($savedlist as $name => $row) {
			$options[$name] = $options[$name] + $row;
		}
		return $options;
	}
	
	public static function getOptions($filename = false) {//3 пересечения Опциии, Файлы, БазаДанных
		$list = Data::getOptions('catalog');
		$filelist = Data::getFileList(Showcase::$conf['catalogsrc']);
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
			$list[$name]['isdata'] = true;
		}
		foreach ($list as $name => $opt) { // По опциям
			$list[$name] += array(
				'name' => $name,
				'isfile' => false,
				'isopt' => false,
				'isdata' => false,
				'order' => 0,
				'producer' => $name
			);
			$list[$name]['isglob'] = !!$list[$name]['producer'];
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
		$conf = Showcase::$conf;
		$options = Catalog::getOptions();
		$list = Data::getFileList($conf['catalogsrc']);
		$order = 0;
		foreach ($list as $filename => $val) {
			$order++;	
			$producer = $filename;

			if (isset($options[$filename]['producer'])) {
				$producer = $options[$filename]['producer'];
			}
			Catalog::loadMeta($filename, $producer, $order);
		}
	}
	
	public static function readCatalog($name, $src) {
		$conf = Showcase::$conf;
		$columns = array_merge(array("Артикул","Производитель"));
		$data = Xlsx::init($src, array(
			'root' => $conf['title'],
			'more' => true,
			'Имя файла' => "Производитель",
			'Игнорировать имена листов' => $conf['ignorelistname'],
			'listreverse' => $conf['listreverse'],
			'Известные колонки' => $columns
		));
		return $data;
	}
	
	
	
	public static function actionRemove($name, $src) {
		//Удаляются ключи model_id, mitem_id
		$r = Data::exec('DELETE m, i, mv, mn, mt, iv, `in`, it 
			FROM showcase_catalog c 
			LEFT JOIN showcase_models m ON m.catalog_id = c.catalog_id
			LEFT JOIN showcase_mvalues mv ON mv.model_id = m.model_id
			LEFT JOIN showcase_mnumbers mn ON mn.model_id = m.model_id
			LEFT JOIN showcase_mtexts mt ON mt.model_id = m.model_id
			LEFT JOIN showcase_mitems i ON i.model_id = m.model_id
			LEFT JOIN showcase_ivalues iv ON iv.mitem_id = i.mitem_id
			LEFT JOIN showcase_inumbers `in` ON in.mitem_id = i.mitem_id
			LEFT JOIN showcase_itexts it ON it.mitem_id = i.mitem_id
			WHERE c.name = ?',[$name]);	
		if (FS::is_file($src)) { //ФАйл есть запись остаётся
			Data::exec('UPDATE showcase_catalog SET time = null WHERE name = ?', [$name]);
		} else {
			Data::exec('DELETE FROM showcase_catalog c WHERE c.name = ?', [$name]);
		}
		
		return $r;
	}
	public static function actionLoad($name, $src)
	{
		$time = time();
		$row = Data::fetch('SELECT catalog_id, `order` from showcase_catalog where name = ?',[$name]);
		$catalog_id = $row['catalog_id'];
		$order = $row['order'];

		$data = Catalog::readCatalog($name, $src);
		$groups = Catalog::applyGroups($data, $catalog_id, $order);
		$props = array();
		$count = 0;
		Xlsx::runPoss( $data, function (&$pos) use (&$filters, &$devcolumns, &$props, $catalog_id, $order, $time, &$count) {
			$count++;
			if (isset($pos['items'])) $count += sizeof($pos['items']); //Считаем с позициями
			$article_id = Catalog::initArticle($pos['Артикул']);
			$producer_id = Data::initProducer($pos['Производитель']);
			
			//Длинное имя группы, например: "Автомобильные регистраторы #avtoreg" берётся из Наименования в descr. Id encod(всё) title то что до решётки. Из title нельзя получить id.
			$group_nick = $pos['gid'];
			$group_id = Data::col('SELECT group_id FROM showcase_groups WHERE group_nick = ?',[$group_nick]);

			
			$model_id = Catalog::initModel($producer_id, $article_id, $catalog_id, $order, $time, $group_id); //У существующей модели указывается time
			if (!$model_id) return; //Каталог не может управлять данной моделью, так как есть более приоритетный источник

			Catalog::writeProps($model_id, 'model_id', 'm', $pos);
			if (isset($pos['items'])) {
				$item = array();
				$item['id'] = $pos['id'];
				foreach ($pos['itemrows'] as $p => $v) {
					if (isset($pos['more'][$p])) $item['more'][$p] = $pos['more'][$p]; //Игнорируем разные группы для items
				}
				array_unshift($pos['items'], $item);
				foreach ($pos['items'] as $item) {
					
					$item_id = Catalog::initItem($item['id']); //itemrows и id
					$mitem_id = Catalog::initMitem($model_id, $item_id, $time);
					
					Catalog::writeProps($mitem_id, 'mitem_id', 'i', $item);
				}
			}
		});
		Catalog::removeOldModels($time, $catalog_id);

		$duration = (time() - $time);
		$r = Data::exec('UPDATE showcase_catalog SET `time` = from_unixtime(?), `duration` = ?, count = ? WHERE catalog_id = ?', [$time, $duration, $count, $catalog_id]);
		return true;
	}
	public static function writeProps($id, $idname, $w, $data) {
		if (empty($data['more'])) return;
		$priceprops = Data::fetchto('
		SELECT mv.prop_id, p.prop, p.type from showcase_'.$w.'values mv join showcase_props p on mv.prop_id 
		WHERE mv.'.$idname.' = ? 
		UNION ALL SELECT mv.prop_id, p.prop, p.type from showcase_'.$w.'texts mv join showcase_props p on mv.prop_id 
		WHERE mv.'.$idname.' = ? 
		UNION ALL SELECT mv.prop_id, p.prop, p.type from showcase_'.$w.'numbers mv join showcase_props p on mv.prop_id 
		WHERE mv.'.$idname.' = ?','prop',[$id, $id, $id]);
		$options = Load::loadJSON('~showcase.json');
		$order = 0;
		foreach ($data['more'] as $prop => $val) {

			if (!isset($props[$prop])) {
				$type = Data::checkType($prop);
				$prop_id = Data::initProp($prop, $type);
				$props[$prop] = [$prop_id, $type];
			} else {
				list($prop_id, $type) = $props[$prop];
			}

			if (isset($priceprops[$prop]) && $priceprops[$prop]['type'] == $type) continue; //свойство взято из прайса и пропускается
			
			if ($type == 'value') {
				$ar = (in_array($prop,$options['justonevalue']))? [$val] : explode(',', $val);
				$vals = [];
				foreach ($ar as $v) {
					$order++;
					$v = trim($v);
					if (!$v) continue;
					$value_id = Data::initValue($v);
					if (isset($vals[$value_id])) continue; //Уже вставлен
					$vals[$value_id] = true;
					Data::lastId('INSERT INTO `showcase_'.$w.'values`('.$idname.',`prop_id`,`value_id`,`order`) VALUES(?,?,?,?)',
						[$id, $prop_id, $value_id, $order]
					);
				}
			} else if ($type == 'number') {
				$ar = (in_array($prop,$options['justonevalue']))? [$val] : explode(',', $val);
				$vals = [];
				foreach ($ar as $v) {
					$order++;
					$v = trim($v);
					if (!$v && $v !== 0) continue;
					if (isset($vals[$val])) continue; //Уже вставлен
					$vals[$val] = true;
					Data::lastId('INSERT INTO `showcase_'.$w.'numbers`('.$idname.',`prop_id`,`number`,`order`) VALUES(?,?,?,?)',
						[$id, $prop_id, $val, $order]
					);
				}
			} else if ($type == 'text') {
				$order++;
				Data::lastId('INSERT INTO `showcase_'.$w.'texts`('.$idname.',`prop_id`,`text`,`order`) VALUES(?,?,?,?)',
					[$id, $prop_id, $val, $order]
				);
			}
		}
	}
	public static function getCatalog($name) {
		$row = Data::fetch('SELECT name, unix_timestamp(time) as time, `order` from showcase_catalog where name = ?',[$name]);
		return $row;
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
	public static function initItem($value) {
		return Once::func( function ($value) {
			if (!$value) return null;
			$strid = 'item_id';
			$strnick = 'item_nick';
			$table = 'showcase_items';
			$nick = Path::encode($value);
			$id = Data::col('SELECT '.$strid.' from '.$table.' where '.$strnick.' = ?', [$nick]);
			if ($id) return $id;
			return Data::lastId(
				'INSERT INTO '.$table.' ('.$strnick.') VALUES(?)',
				[$nick]
			);	
		}, [$value]);
	}
	public static function clearCatalog($catalog_id) {

		//Удалить все свойства у всех моделей
		Data::exec('DELETE p FROM showcase_mvalues p
			INNER JOIN showcase_models m ON m.model_id = p.model_id
			WHERE p.price_id is null and m.catalog_id = ?', [$catalog_id]);

		Data::exec('DELETE p FROM showcase_mnumbers p
			INNER JOIN showcase_models m ON m.model_id = p.model_id
			WHERE p.price_id is null and m.catalog_id = ?', [$catalog_id]);

		$count = Data::exec('DELETE p FROM showcase_mtexts p, showcase_models m
			WHERE m.model_id = p.model_id and p.price_id is null and m.catalog_id = ?', [$catalog_id]);
		/*echo $catalog_id;
		echo '<br>';
		echo $count;
		exit;*/
		
	}
	public static function clearModel($model_id) {
		Data::exec('DELETE FROM showcase_mvalues WHERE model_id = ? and price_id is null', [$model_id]);
		Data::exec('DELETE FROM showcase_mnumbers WHERE model_id = ? and price_id is null', [$model_id]);
		Data::exec('DELETE FROM showcase_mtexts WHERE model_id = ? and price_id is null', [$model_id]);
	}
	public static function initModel($producer_id, $article_id, $catalog_id, $order, $time, $group_id) {
		
		return Once::func( function ($producer_id, $article_id) use ($catalog_id, $order, $time, $group_id) {
			$row = Data::fetch('SELECT sm.model_id, sm.catalog_id, sc.order
				FROM showcase_models sm, showcase_catalog sc 
				WHERE sc.catalog_id = sm.catalog_id And sm.producer_id = ? AND sm.article_id = ?', [$producer_id, $article_id]);
			if ($row) {
				$model_id = $row['model_id'];
				if ($catalog_id != $row['catalog_id']) { //Модель появилась из другого каталога
					if ($order > $row['order']) return false;//Новый каталог в списке позже и не управляет этой позицией
				}
				Catalog::clearModel($model_id); //Нашли модель и удалили у неё свойства
				Data::exec('UPDATE showcase_models SET time = from_unixtime(?), catalog_id = ? WHERE model_id = ?',[$time, $catalog_id, $model_id]);
				return $model_id;
			}
			return Data::lastId(
				'INSERT INTO showcase_models (producer_id, article_id, catalog_id, time, group_id) VALUES(?,?,?,from_unixtime(?),?)',
				[$producer_id, $article_id, $catalog_id, $time, $group_id]
			);	
		}, [$producer_id, $article_id]);
	}
	public static function initMitem($model_id, $item_id, $time) {
		return Once::func( function ($model_id, $item_id) use ($time) {
			$mitem_id = Data::col('SELECT mitem_id from showcase_mitems where model_id = ? and item_id = ?', [$model_id, $item_id]);
			if ($mitem_id) {
				Catalog::clearMitem($mitem_id); //Нашли модель и удалили у неё свойства
				Data::exec('UPDATE showcase_mitems SET time = from_unixtime(?) WHERE mitem_id = ?',[$time, $mitem_id]);
				return $mitem_id;
			}
			return Data::lastId(
				'INSERT INTO showcase_mitems (model_id, item_id, time) VALUES(?,?,from_unixtime(?))',
				[$model_id, $item_id, $time]
			);	
		}, [$model_id, $item_id]);
	}
	
	
	public static function loadMeta($name, $producer, $order) {
		$producer_id = Data::initProducer($producer);
		
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
		Data::exec('DELETE m, i, mv, mn, mt, iv, `in`, it 
			FROM showcase_models m 
			LEFT JOIN showcase_mvalues mv ON mv.model_id = m.model_id
			LEFT JOIN showcase_mnumbers mn ON mn.model_id = m.model_id
			LEFT JOIN showcase_mtexts mt ON mt.model_id = m.model_id
			LEFT JOIN showcase_mitems i ON i.model_id = m.model_id
			LEFT JOIN showcase_ivalues iv ON iv.mitem_id = i.mitem_id
			LEFT JOIN showcase_inumbers `in` ON in.mitem_id = i.mitem_id
			LEFT JOIN showcase_itexts it ON it.mitem_id = i.mitem_id
			WHERE m.time != from_unixtime(?) and catalog_id = ?',[$time, $catalog_id]);
	}
	public static function removeOldProps($prop_id) {
		Data::exec('DELETE `mv`, `mn`, `mt`, `iv`, `in`, `it` 
			FROM showcase_mvalues mv 
			LEFT JOIN showcase_mnumbers mn ON mn.prop_id = mv.prop_id
			LEFT JOIN showcase_mtexts mt ON mt.prop_id = mv.prop_id
			LEFT JOIN showcase_ivalues iv ON iv.mitem_id = mv.prop_id
			LEFT JOIN showcase_inumbers `in` ON in.prop_id = mv.prop_id
			LEFT JOIN showcase_itexts it ON it.prop_id = mv.prop_id
			WHERE mv.prop_id != ?', [$prop_id]);
		
	}
	
	public static function clearMitem($mitem_id) {
		Data::exec('DELETE FROM showcase_ivalues WHERE mitem_id = ? and price_id is null',[$mitem_id]);
		Data::exec('DELETE FROM showcase_inumbers WHERE mitem_id = ? and price_id is null',[$mitem_id]);
		Data::exec('DELETE FROM showcase_itexts WHERE mitem_id = ? and price_id is null',[$mitem_id]);
	}
	public static function getGroupId($group_nick) {
		if (!$group_nick) return null;
		$sql = 'SELECT group_id from showcase_groups where group_nick = ?';
		$stmt = Db::stmt($sql);
		$stmt->execute([$group_nick]);
		$group_id = $stmt->fetchColumn();
		return $group_id;
	}
	public static function updateGroupParent($parent_id, $group_id) {
		return Data::exec('UPDATE showcase_groups SET parent_id = ? WHERE group_id = ?',[$parent_id, $group_id]);
	}
	public static function insertGroup($group, $parent_id, $group_nick, $catalog_id) {
		$db = &Db::pdo();
		$sql = 'INSERT INTO `showcase_groups`(`group`,`parent_id`,`group_nick`, `catalog_id`) VALUES(?,?,?,?)';
		$stmt = Db::stmt($sql);
		$stmt->execute([$group, $parent_id, $group_nick,$catalog_id]);
		$group_id = $db->lastInsertId();
		return $group_id;
	}
	public static function applyGroups($data, $catalog_id, $order) {
		$groups = array();
		Xlsx::runGroups($data, function &($group) use ($catalog_id, &$groups, $order){
			$r = null;
			$group_nick =  $group['id'];
			if (isset($groups[$group_nick])) return $r;
			$sql = 'SELECT g1.group, g1.group_id, c.order, g2.group_nick as parent_nick, g1.catalog_id 
					FROM showcase_groups g1 
					LEFT JOIN showcase_groups g2 ON g1.parent_id = g2.group_id 
					LEFT JOIN showcase_catalog c ON g1.catalog_id = c.catalog_id 
					WHERE g1.group_nick = ?';
			$stmt = Db::stmt($sql);
			$stmt->execute([$group_nick]);
			$row =  $stmt->fetch();
			$parent_nick = $group['gid'];
			if ($row) {
				$group_id = $row['group_id'];
				if ($parent_nick && $parent_nick != $row['parent_nick'] && ($catalog_id == $row['catalog_id'] || $row['order'] < $order)) {
					$parent_id = Catalog::getGroupId($parent_nick);
					Catalog::updateGroupParent($parent_id, $group_id);
				}
				$groups[$group_nick] = $group_id;
				return $r;
			}
			
			if ($group['gid']) {
				$parent_nick = $group['gid'];
				$parent_id = Catalog::getGroupId($parent_nick);	
			} else {
				$parent_id = null;
			}
			
			$group_id = Catalog::insertGroup($group['title'], $parent_id, $group_nick, $catalog_id);
			$groups[$group_nick] = $group_id;
			return $r;
		});

		
		return $groups;
	}
}