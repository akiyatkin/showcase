<?php
namespace akiyatkin\showcase;
use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\once\Once;
use infrajs\excel\Xlsx;
use infrajs\db\Db;

class Data {
	public static $timer;
	public static function timer($msg = '') {
		if (!Data::$timer) Data::$timer = microtime(true);
		echo round((microtime(true) - Data::$timer) * 1000) . ' мс '.$msg.'<br>';
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

	public static function getOptions($part = false){
		$opt = Load::loadJSON('~showcase.json');
		$opt = $opt + array(
			'catalog'=>[],
			'prices'=>[]
		);
		Data::prepareOptionPart($opt['catalog']);
		Data::prepareOptionPart($opt['prices']);

		if ($part) return $opt[$part];
		return $opt;
	}
	public static function prepareOptionPart(&$list){
		foreach ($list as $name => $val) { // По опциям
			$list[$name]['isopt'] = true;
		}
	}
	/**
	 * Массив поставщиков в формает fd (nameInfo) с необработанными данными из Excel (data)
	 **/
	public static function getFileList($folder)
	{
		return Once::func( function () use ($folder) {
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
		});
		
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
		Data::exec('TRUNCATE `showcase_articles`');
		Data::exec('TRUNCATE `showcase_catalog`');
		Data::exec('TRUNCATE `showcase_groups`');
		Data::exec('TRUNCATE `showcase_inumbers`');
		Data::exec('TRUNCATE `showcase_items`');
		Data::exec('TRUNCATE `showcase_itexts`');
		Data::exec('TRUNCATE `showcase_ivalues`');
		Data::exec('TRUNCATE `showcase_mitems`');
		Data::exec('TRUNCATE `showcase_mnumbers`');
		Data::exec('TRUNCATE `showcase_models`');
		Data::exec('TRUNCATE `showcase_mtexts`');
		Data::exec('TRUNCATE `showcase_mvalues`');
		Data::exec('TRUNCATE `showcase_prices`');
		Data::exec('TRUNCATE `showcase_producers`');
		Data::exec('TRUNCATE `showcase_props`');
		Data::exec('TRUNCATE `showcase_values`');
	}
	public static function initProp($value, $type) {
		$type = ["value"=>1, "number"=>2, "text"=>3][$type];
		return Once::func( function ($value) use ($type) {
			if (!$value) return null;
			
			$nick = Path::encode($value);

			$row = Data::fetch('SELECT prop_id, type from showcase_props where prop_nick = ?', [$nick]);
			if ($row && $row['type'] == $type) {
				return $row['prop_id'];
			}
			if ($row) {
				$prop_id = $row['prop_id'];
				Data::exec('UPDATE showcase_props SET type = ? WHERE prop_id = ?',[$type, $prop_id]);

				Catalog::removeOldProps($prop_id);
				return $prop_id;
			}
			return Data::lastId(
				'INSERT INTO showcase_props (prop, prop_nick, type) VALUES(?,?,?)',
				[$value, $nick, $type]
			);	
		}, [$value]);
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
	public static function checkType($prop) {
		$options = Load::loadJSON('~showcase.json');
		if (in_array($prop, $options['numbers'])) return 'number';
		if (in_array($prop, $options['texts'])) return 'text';
		if (in_array($prop, $options['values'])) return 'value';
		foreach ($options['filters'] as $row) {
			if (in_array($prop, $row)) return 'value';
		}
		return 'value';
	}
}