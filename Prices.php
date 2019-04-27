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

Event::$classes['Showcase-prices'] = function (&$obj) {
	return '1'; //tik каждый раз
};

class Prices {
	public static function getList() {
		$options = Prices::getOptions();
	
		$savedlist = Data::fetchto('
			SELECT ps.name, ps.ans, count(DISTINCT t.model_id, t.item_num) as icount FROM (
				SELECT p.price_id, v.model_id, v.item_num
				from showcase_mnumbers v, showcase_prices p
				WHERE p.price_id = v.price_id and v.price_id is not null
				
				UNION ALL
				SELECT p.price_id, v.model_id, v.item_num
				from showcase_mtexts v, showcase_prices p
				WHERE p.price_id = v.price_id and v.price_id is not null
				
				UNION ALL
				SELECT p.price_id, v.model_id, v.item_num
				from showcase_mvalues v, showcase_prices p
				WHERE p.price_id = v.price_id and v.price_id is not null
			) t
			RIGHT JOIN showcase_prices ps ON ps.price_id = t.price_id
			GROUP BY ps.name
    	','name');
		foreach ($savedlist as $name => $row) {
			$row['ans'] = Load::json_decode($row['ans'],true);
			if ($name) $options[$name] += $row;
		}
		return $options;
	}
	public static function actionLoadAll() {
		$options = Prices::getList();
		$res = [];
		foreach ($options as $name => $row) {
			if (empty($row['isfile'])) { //Удалить
				//удаляем
				if (!empty($row['icount'])) {
					$src = Showcase::$conf['pricessrc'].$row['file'];
					$res['Прайс - удаляем '.$name] = Prices::actionRemove($name, $src);
				}
			} else {
				if (!isset($row['time']) || $row['time'] < $row['mtime']) {
					$src = Showcase::$conf['pricessrc'].$row['file'];
					$res['Прайс - вносим '.$name] = Prices::actionLoad($name, $src);
				} 
			}
		}
		return $res;
	}
	public static function init() {
		Data::init();
		$conf = Showcase::$conf;
		
		$options = Data::getOptions('prices');
		$list = Data::getFileList($conf['pricessrc']);
		$order = 0;
		foreach ($list as $filename => $val) {
			$order++;
			$producer = $filename;
			if (isset($options[$filename]['producer'])) {
				$producer = $options[$filename]['producer'];
			}
			Prices::activatePrice($filename, $producer, $order);
		}
	}
	public static function activatePrice($name, $producer, $order) {
		$producer_id = Data::initProducer($producer);
		$row = Data::fetch('SELECT price_id, `order`, producer_id from showcase_prices WHERE name = ?', 
			[$name]);		
		if (!$row) return Data::lastId(
			'INSERT INTO `showcase_prices`(`name`,`producer_id`,`order`) VALUES(?,?,?)',
			[$name, $producer_id, $order]
		);
		if ($row['order'] != $order || $row['producer_id'] != $producer_id) {
			Data::exec('UPDATE showcase_prices SET `order` = ?, producer_id = ? WHERE price_id = ?', 
				[$order, $producer_id, $row['price_id']]);
		}
		return $row['price_id'];
	}
	public static function getMyModels($type, $producer_id, $prop_id, $value) {
		//Вообщедолжна быть одна модель. Прайс связываеся с этими моделями по prop_id и value
		if ($type == 'value') {
			$id = Data::initValue($value);
			$mainprop = 'value_id';
			$t = 'mvalues'; 
		} else if ($type == 'article') {
			$nick = Path::encode($value);
			$article_id = Data::col('SELECT article_id from showcase_articles where article_nick = ?', [$nick]);
			if (!$article_id) return [];
			if ($producer_id) {
				$list = Data::fetchto('SELECT model_id, "0" as item_num FROM showcase_models WHERE article_id = ? and producer_id = ?',
					'model_id', [$article_id, $producer_id]);
			} else {
				$list = Data::fetchto('SELECT model_id, "0" as item_num FROM showcase_models WHERE article_id = ?',
					'model_id', [$article_id]);
			}
			return $list;
		} else if ($type == 'number') {
			$id = round($value, 2);
			$mainprop = 'number';
			$t = 'mnumbers'; 
		}
		if ($producer_id) {
			$list = Data::fetchto('SELECT m.model_id, n.item_num
				FROM showcase_'.$t.' n 
				RIGHT JOIN showcase_models m ON m.model_id = n.model_id AND m.producer_id = ?
				WHERE n.prop_id = ?
				AND n.'.$mainprop.' = ?
			','model_id', [$producer_id, $prop_id, $id]);
		} else {
			$sql = 'SELECT n.model_id, n.item_num
				FROM showcase_'.$t.' n
				WHERE n.prop_id = ?
				AND n.'.$mainprop.' = ?
				';
			$list = Data::fetchto($sql,'model_id', [$prop_id, $id]);
		}

		return $list;
	}
	public static function updateProps($type, $props, $pos, $price_id, $order, $producer_id, $prop_id, $value) {
		$list = Prices::getMyModels($type, $producer_id, $prop_id, $value);
		
		$modified = 0;
		foreach ($list as $i => $find) {
			$model_id = $find['model_id'];
			$item_num = $find['item_num'];
			//Для этих моделей нужно записать новые свойства из props, но надо чтобы текущие значения не были более приоритеными
			$r = false;
			foreach ($props as $p) {
				//Смотрим что установлено для нашей модели. Будет insert или update

				if (!isset($pos[$p['prop']])) {
					continue; //Нечего копировать, свойства то и нет
				}

				$val = $pos[$p['prop']];
				
				$oldorder = 0;
				$t = 'm'.$p['type'].'s';	//mvalues
				$mainprop = ($p['type'] == 'value') ?'value_id': $p['type'];

				$row = Data::fetch('SELECT p.order from showcase_'.$t.' v
					left join showcase_prices p on p.price_id = v.price_id
					WHERE
					model_id = ? 
					AND item_num = ? AND prop_id = ?',
					[$model_id, $item_num, $p['prop_id']]);

				if ($row) {
					if ($row['order']){
						$oldorder = $row['order'];
						if ($oldorder < $order) {
							continue; //Свойство установлено из более приоритетного прайса
						}
					}
					Prices::deleteProp($model_id, $item_num, $p['prop_id']);
				}

				$r = true;
				$ar = ($p['type'] == 'text') ? [$val] : explode(',', $val);
				foreach ($ar as $v) {
					$value_id = ($p['type'] == 'value') ? Data::initValue($v) : $v;
					Data::exec('INSERT showcase_'.$t.' (model_id, item_num, prop_id, '.$mainprop.', price_id, `order`)
					VALUES(?,?,?,?,?,?)', [$model_id, $item_num, $p['prop_id'], $value_id, $price_id, $oldorder]);
				}

			}
			if ($r) $modified++;
			$list[$i]['r'] = $r;
		}
		return [sizeof($list), $modified];
	}
	public static function actionLoad($name, $src) {
		$time = time();
		$ans = array();

		$row = Data::fetch('SELECT price_id, `order` from showcase_prices where name = ?',[$name]);	
		$price_id = $row['price_id'];
		$order = $row['order'];
		$count = 0;
		$data = Prices::readPrice($name, $src);
		$option = Prices::getOptions($name);
		$ans['Внесение параметров'] = implode(', ',$option['props']);
		$ans['Ключ соответствия'] = '<b>'.$option['priceprop'].'</b> в прайсе и <b>'.$option['catalogprop'].'</b> в каталоге';
		
		if ($option['isaccurate']) {
			$type = Data::checkType($option['catalogprop']);
			if ($type == 'text') die('Нельзя настраивать связь данных с прайсом по ключу указанному как свободный текст');
			$prop_id = Data::initProp($option['catalogprop'], $type);
			foreach ($option['props'] as $k => $prop) {
				$t = Data::checkType($prop);
				$pid = Data::initProp($prop, $t);
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

		$db = &Db::pdo();
		$db->beginTransaction();
		
		$producer_id = $option['producer'] ? Data::initProducer($option['producer']) : false;

		
		$ans['Количество строк'] = 0;
		$ans['Позиций с ключём'] = 0; //Позиций в прайсе
		$ans['Изменение позиций'] = 0; //изменённых моделей
		$ans['Пропущено из-за отсутствия параметров в прайсе или конфликта с другим прайсом'] = 0;
		$ans['Дубли по ключу в данных'] = 0;
		$ans['Не найдено в каталоге'] = 0;
		$ans['Принято'] = 0;
		$ans['Синонимы'] = $option['synonyms'];
		
		$heads = [];
		Xlsx::runGroups($data, function &($group) use (&$heads) {
			if ($group['type'] == 'list' ) {
				$heads[$group['title']] = $group['head'];
			}
			$r = null;
			return $r;
		});
		$ans['Колонки на листах'] = $heads;

		if ($option['isaccurate']) {
			Xlsx::runPoss( $data, function &(&$pos) use ($name, &$ans, &$option, $prop_id, $type, $producer_id, $order, $price_id){
				$r = null;
				
				Prices::checkSynonyms($pos, $option);

				
				$obj = [
					'option' => $option,
					'name' => $name,
					'pos' => &$pos
				];
				Event::tik('Showcase-prices.onload');
				Event::fire('Showcase-prices.onload', $obj); //В событии дописываем нужное свойство которое уже есть в props
				
				$ans['Количество строк']++; //Записей с ключём прайса
				if (!isset($pos[$option['priceprop']])) return $r;
				$ans['Позиций с ключём']++; //Записей с ключём прайса

				$value = $pos[$option['priceprop']];
				list($c, $modified) = Prices::updateProps($type, $option['props'], $pos, $price_id, $order, $producer_id, $prop_id, $value);
				if ($c == 0) $ans['Не найдено в каталоге']++;
				if ($c > 1) $ans['Дубли по ключу в данных'] += $c;
				$ans['Изменение позиций'] += $c;
				$ans['Пропущено из-за отсутствия параметров в прайсе или конфликта с другим прайсом'] += ($c-$modified);
				$ans['Принято'] += $modified;
				return $r;
			});
		}
		$duration = (time() - $time);
		$jsonans = Load::json_encode($ans);
		$r = Data::exec('UPDATE showcase_prices SET `time` = from_unixtime(?), `duration` = ?, `count` = ?, ans = ? WHERE price_id = ?', [$time, $duration, $ans['Позиций с ключём'], $jsonans, $price_id]);
		$db->commit();
		return $ans;
	}
	public static function deleteProp($model_id, $item_num, $prop_id) {
		foreach (Data::$types as $type) {
			Data::exec('DELETE FROM showcase_m'.$type.'s WHERE model_id = ? and item_num = ? and prop_id =?', 
				[$model_id, $item_num, $prop_id]);	
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
			Data::exec('DELETE FROM showcase_prices WHERE name = ?', [$name]);
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
				'start' => 0,
				'name' => $name,
				'head' => [], //Правильные названия для колонок по порядку depricated
				'synonyms' => [],
				'isfile' => false,
				'isopt' => false,
				'isdata' => false,
				'order' => 0,
				'ignore' => [],
				"lists"	=> [],
				'producer' => $name,
				'props' => ["Цена"],
				"priceprop" => "Артикул",
				"catalogprop" => "Артикул"
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
			if ($a['isfile'] && !$b['isfile']) return -1; //Сначало файлы, потом база данных, потом опции
			if (!$a['isfile'] && $b['isfile']) return 1; //Сначало файлы, потом база данных, потом опции
			if ($b['order'] < $a['order']) return 1;
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
			if ($rule['lists'] && !in_array($sheetname, $rule['lists'])) {
				unset($data[$sheetname]);
				continue;
			} 
			if (in_array($sheetname, $rule['ignore'])) {
				unset($data[$sheetname]);
				continue;
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
	public static function checkSynonyms(&$pos, $rule) 
	{

		if (isset($rule['synonyms'])) {
			foreach ($rule['synonyms'] as $val => $vals) {
				//if (!isset($pos[$val])) continue;
				foreach ($vals as $syn) {
					if (empty($pos[$syn])) continue;
					$pos[$val] = $pos[$syn];
					break; //Приоритетней первое совпадение
				};
			}
		}
	}
}