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


class Prices {
	public static function getList() {
		$options = Prices::getOptions();
		
		foreach ($options as $name => $row) $options[$name]['icount'] = 0;	
		$savedlist = Data::fetchto('
			SELECT t.name, count(DISTINCT t.model_id) as icount FROM (
			SELECT p.name, m.model_id
			from showcase_models m 
			RIGHT JOIN showcase_mnumbers v on v.model_id = m.model_id
			LEFT JOIN showcase_prices p on p.price_id = v.price_id
			WHERE v.price_id is not null
			UNION ALL
			SELECT p.name, m.model_id
			from showcase_models m 
			RIGHT JOIN showcase_mtexts v on v.model_id = m.model_id
			LEFT JOIN showcase_prices p on p.price_id = v.price_id
			WHERE v.price_id is not null
			UNION ALL
			SELECT p.name, m.model_id
			from showcase_models m 
			RIGHT JOIN showcase_mvalues v on v.model_id = m.model_id
			LEFT JOIN showcase_prices p on p.price_id = v.price_id
			WHERE v.price_id is not null) t
			GROUP BY t.name
    	','name');
		foreach ($savedlist as $name => $row) if ($name) $options[$name]['icount'] += $row['icount'];

		$savedlist = Data::fetchto('
			SELECT t.name, count(DISTINCT mitem_id) as icount FROM (
			SELECT p.name, m.mitem_id
			from showcase_mitems m 
			RIGHT JOIN showcase_inumbers v on v.mitem_id = m.mitem_id
			LEFT JOIN showcase_prices p on p.price_id = v.price_id
			WHERE v.price_id is not null
			UNION ALL
			SELECT p.name, m.mitem_id
			from showcase_mitems m 
			RIGHT JOIN showcase_itexts v on v.mitem_id = m.mitem_id
			LEFT JOIN showcase_prices p on p.price_id = v.price_id
			WHERE v.price_id is not null
			UNION ALL
			SELECT p.name, m.mitem_id
			from showcase_mitems m 
			RIGHT JOIN showcase_ivalues v on v.mitem_id = m.mitem_id
			LEFT JOIN showcase_prices p on p.price_id = v.price_id
			WHERE v.price_id is not null) t
			GROUP BY t.name
    	','name');
		foreach ($savedlist as $name => $row) if ($name) $options[$name]['icount'] += $row['icount']/2;
		
		return $options;
	}
	public static function init() {
		$conf = Showcase::$conf;
		
		$options = Prices::getOptions();

		$list = Data::getFileList($conf['pricessrc']);
		$order = 0;
		foreach ($list as $filename => $val) {
			$order++;
			$producer = $filename;

			if (isset($options[$filename]['producer'])) {
				$producer = $options[$filename]['producer'];
			}
			Prices::loadMeta($filename, $producer, $order);
		}
	}
	public static function loadMeta($name, $producer, $order) {
		$producer_id = Data::initProducer($producer);
		$row = Data::fetch('SELECT price_id, `order`, producer_id from showcase_prices WHERE name = ?', 
			[$name]);		
		if ($row) {
			if ($row['order'] != $order || $row['producer_id'] != $producer_id) {
				Data::exec('UPDATE showcase_prices SET `order` = ?, producer_id = ? WHERE price_id = ?', 
					[$order, $producer_id, $row['price_id']]);
			}
			return $row['price_id'];
		}
		return Data::lastId(
			'INSERT INTO `showcase_prices`(`name`,`producer_id`,`order`) VALUES(?,?,?)',
			[$name, $producer_id, $order]
		);
		
	}
	public static function getMyModels($part, $type, $producer_id, $prop_id, $value) {
		if ($type == 'value') {
			$id = Data::initValue($value);
			$mainprop = 'value_id';
		} else if ($type == 'number') {
			$id = round($value, 2);
			$mainprop = 'number';
		}
		//$part = 'model'; //item
		$table = $part == 'model' ? 'models' : 'mitems';
		$strid = $part == 'model' ? 'model_id' : 'mitem_id';
		$w = $part[0];
		$t = $w.$type.'s'; //mvalues
		if ($producer_id) {
			$list = Data::fetchto('SELECT m.'.$strid.'
				FROM showcase_'.$table.' m, showcase_'.$t.' n
				LEFT JOIN showcase_prices p ON p.price_id = n.price_id
				WHERE m.'.$strid.' = n.'.$strid.'
				AND n.prop_id = ?
				AND n.'.$mainprop.' = ?
				AND m.producer_id = ?
				',$strid, [$prop_id, $id, $producer_id]);
		} else {
			$list = Data::fetchto('SELECT m.'.$strid.'
				FROM showcase_'.$table.' m, showcase_'.$t.' n
				LEFT JOIN showcase_prices p ON p.price_id = n.price_id
				WHERE m.'.$strid.' = n.'.$strid.'
				AND n.prop_id = ?
				AND n.'.$mainprop.' = ?
				',$strid, [$prop_id, $id]);
		}
		return $list;
	}
	public static function updateProps($part, $type, $props, $pos, $price_id, $order, $producer_id, $prop_id, $value) {
		$list = Prices::getMyModels($part, $type, $producer_id, $prop_id, $value);
		foreach ($list as $id => $find) {
			//Для этих моделей нужно записать новые свойства из props, но надо чтобы текущие значения не были более приоритеными
			foreach ($props as $p) {
				//Смотрим что установлено для нашей модели. Будет insert или update
				if (!isset($pos[$p['prop']])) continue; //Нечего копировать, свойства то и нет
				$val = $pos[$p['prop']];
				$oldorder = 0;
				$strid = $part == 'model' ? 'model_id' : 'mitem_id';
				$t = $part[0].$p['type'].'s';	//mvalues
				$mainprop = ($p['type'] == 'value') ?'value_id': $p['type'];

				$row = Data::fetch('SELECT `order` from showcase_'.$t.' WHERE '.$strid.' = ? AND prop_id = ?',
					[$id, $p['prop_id']]);
				if ($row) {
					$oldorder = $row['order'];
					if ($oldorder < $order) continue; //Свойство установлено из более приоритетного прайса
					Prices::deleteProp($part, $id, $p['prop_id']);
				}
				
				$ar = ($p['type'] == 'text') ? [$val] : explode(',', $val);
				foreach ($ar as $v) {
					$value_id = ($p['type'] == 'value') ? $v : Data::initValue($v);
					Data::exec('INSERT showcase_'.$t.' ('.$strid.', prop_id, '.$mainprop.', price_id, `order`)
					VALUES(?,?,?,?,?)', [$id, $p['prop_id'], $value_id, $price_id, $oldorder]);
				}
			}
		}
	}
	public static function actionLoad($name, $src) {
		$time = time();
		$row = Data::fetch('SELECT price_id, `order` from showcase_prices where name = ?',[$name]);	
		$price_id = $row['price_id'];
		$order = $row['order'];
		$count = 0;
		$data = Prices::readPrice($name, $src);
		$option = Prices::getOptions($name);
		if ($option['isaccurate']) {
			$type = Data::checkType($option['catalogprop']);
			if (!$type == 'text') die('Нельзя настраивать связь данных с прайсом по ключу указанному как свободный текст');
			
			$prop_id = Data::initProp($option['catalogprop'], $type);
			foreach ($option['props'] as $k => $prop) {
				$t = Data::checkType($prop);
				$pid = Data::initProp($prop, $type);
				$option['props'][$k] = [
					'prop_id' => $pid,
					'prop' => $prop,
					'type' => $t
				];
			}
		} else {
			$prop_id = false;
			$type = false;
		}
		$producer_id = $option['producer'] ? Data::initProducer($option['producer']) : false;
		Data::timer('load подготовка, прайс загружен');
		Xlsx::runPoss( $data, function &(&$pos) use (&$count, &$option, $prop_id, $type, $producer_id, $order, $price_id){
			$count++;
			$r = null;

			if ($option['isaccurate']) {
				if (!isset($pos[$option['priceprop']])) return $r;
				$value = $pos[$option['priceprop']];
				Prices::updateProps('model', $type, $option['props'], $pos, $price_id, $order, $producer_id, $prop_id, $value);
				Prices::updateProps('item', $type, $option['props'], $pos, $price_id, $order, $producer_id, $prop_id, $value);
			}
			/*
				//Через синонимы свойства props назваы также как в каталоге
					Конфиг прайса	(producer, isglob, isaccurate, catalogkeytpl, pricekeytpl, priceprop, catalogprop, propisnumber в конфиге)
				true, false		- pricekey_value глобальный, 
				true, true 		- pricekey_id по priceprop_id, catalogprop_id, глобальный поиск
				false, false 	- pricekey_value уникальный для producer
				false, true	 	- pricekey_id по priceprop_id, catalogprop_id, уникальный для producer
				parse - заменяется с обновлением прайса, удаляется с пропажей прайса		
			*/
			Data::timer('обработана позиция '.$pos['Код']);
			return $r;
		});
		Data::timer('load выполнен, осталось зафиксировать');
		$duration = (time() - $time);
		$r = Data::exec('UPDATE showcase_prices SET `time` = from_unixtime(?), `duration` = ?, `count` = ? WHERE price_id = ?', [$time, $duration, $count, $price_id]);
		return true;
	}
	public static function deleteProp($part, $id, $prop_id) {
		$strid = $part == 'model' ? 'model_id' : 'mitem_id';
		$w = $part[0];
		foreach(Data::$types as $type) {
			Data::exec('DELETE FROM showcase_'.$w.$type.'s WHERE '.$strid.' = ? and prop_id =?', [$id, $prop_id]);	
		}
	}
	public static function actionRemove($name, $src) {
		foreach (Data::$types as $type) {
			Data::exec('DELETE t FROM showcase_m'.$type.'s t, showcase_prices p
			WHERE t.price_id = p.price_id 
			AND p.name =?', [$name]);	
		}
		if (FS::is_file($src)) { //ФАйл есть запись остаётся
			Data::exec('UPDATE showcase_prices SET time = null WHERE name = ?', [$name]);
		} else {
			Data::exec('DELETE FROM showcase_prices c WHERE c.name = ?', [$name]);
		}
	}
	public static function getOptions($filename = false) {//3 пересечения Опциии, Файлы, БазаДанных
		$list = Data::getOptions('prices');
		$filelist = Data::getFileList(Showcase::$conf['pricessrc']);
		
		foreach ($filelist as $name => $val) { // По файлам
			if (!isset($list[$name])) $list[$name] = array();
			$list[$name] += $filelist[$name];
			$list[$name]['isfile'] = true;
		}
		$savedlist = Data::fetchto('SELECT unix_timestamp(time) as time, `order`, duration, name, `count` 
			FROM showcase_prices','name');
		foreach ($savedlist as $name => $val) { // По файлам
			if (!isset($list[$name])) $list[$name] = array();
			$list[$name] += $savedlist[$name];
			if (!$savedlist[$name]['time']) continue;// Данные ещё не вносились
			$list[$name]['isdata'] = true;
		}
		foreach ($list as $name => $opt) { // По опциям
			$list[$name] += array(
				'start' => 1,
				'name' => $name,
				'head' => [], //Правильные названия для колонок по порядку depricated
				'isfile' => false,
				'isopt' => false,
				'isdata' => false,
				'order' => 0,
				'ignore' => [],
				'producer' => $name,
				'props' => ["Артикул","Производитель","Цена"],
				"pricekeytpl" => "{Артикул}",
				"catalogkeytpl" => "{Артикул}",
				"priceprop" => false,
				"catalogprop" => false
			);
			$list[$name]['isglob'] = !!$list[$name]['producer'];
			$list[$name]['isaccurate'] = ($list[$name]['catalogprop'] && $list[$name]['priceprop']);
		}
		/*
		Конфиг прайса	(producer, isglob, isaccurate, catalogkeytpl, pricekeytpl, priceprop, catalogprop propisnumber в конфиге)
	true, false		- pricekey_value глобальный, 
	true, true 		- pricekey_id по priceprop_id, catalogprop_id, глобальный поиск
	false, false 	- pricekey_value уникальный для producer
	false, true	 	- pricekey_id по priceprop_id, catalogprop_id, уникальный для producer
	parse - заменяется с обновлением прайса, удаляется с пропажей прайса		
*/
		if ($filename) return $list[$filename];
		uasort($list, function($a, $b) {
			if ($b['isfile'] && !$a['isfile']) return 1; //Сначало файлы, потом база данных, потом опции
			if ($b['order'] < $a['order']) return 1;
			return 0;
		});
		return $list;
	}
	public static function readPrice($name, $src) {
		$conf = Showcase::$conf;
		$data = Xlsx::parseAll($src);
		Prices::applyRules($data, $name);
		return Xlsx::get($data, $name);
	}
	public static function applyRules(&$data, $name)
	{
		$options = Prices::getOptions();
		$rule = isset($options[$name])?$options[$name]: [];

		foreach ($data as $sheetname => $sheet) {
			if (in_array($sheetname, $rule['ignore'])) {
				unset($data[$sheetname]);
			}
		}
		if (isset($rule['merge'])) {
			/* ВОсстаноавливаем значение объеинённых ячеек по высоте в одну строку
			|  !!!	|  !!!	|		|
			|		|		|!!!|!!!|
			*/
			foreach ($data as $sheetname => $sheet) {
				foreach ($sheet as $index => $row) {
					
					if (sizeof($row)>2 && isset($data[$sheetname][$index+1])) {
						$data[$sheetname][$index+1] = $data[$sheetname][$index+1] + $data[$sheetname][$index];
						ksort($data[$sheetname][$index+1]);
						unset($data[$sheetname][$index]);
						break;
					}
				}
			}
		}


		foreach ($data as $sheetname => $sheet) {
			foreach ($sheet as $i => $row) {
				if ($i > $rule['start']-1) break;
				unset($data[$sheetname][$i]);
			}
		}

		if ($rule['head']) { //depricated
			foreach ($data as $name => $list) {
				$head = array_shift($list);
				if (!$head) continue;
				
				foreach ($head as $i => $val) {
					$head[$i] = array_shift($rule['head']);
				}
				array_unshift($data[$name], $head);
			}
		}
	}
}