<?php
use infrajs\path\Path;
use infrajs\ans\Ans;
use infrajs\db\Db;
use akiyatkin\showcase\Showcase;
use akiyatkin\ydisk\Ydisk;
use akiyatkin\showcase\Data;
use akiyatkin\showcase\Prices;
use akiyatkin\showcase\Catalog;
use infrajs\event\Event;
use akiyatkin\showcase\Catkit;

/*
Всё что добавляется в общий адресный параметр "m" как криетрий поиска, сортировки, вывода должно быть обработано 
в search чтобы опция применяалсь 
в filters чтобы показывалась пользователю
в шаблонах вывода. 
Критерии такие, чтобы при передачи ссылки кому-то и он должен увидеть то что нужно.
*/
if (isset($_GET['-showcase'])) {
	$is = Ans::get('-showcase',['true']);
	if ($is) {
		Ydisk::replaceAll();
		Catalog::init();
		Prices::init();
		$res = Catalog::actionLoadAll();
		$res = Prices::actionLoadAll();
		$res = Data::actionAddFiles();
	}
}
Event::handler('Showcase-priceonload', function () {
	//Нужно посчитать комплекты для всех позиций по умолчанию
	//Есть Комлпектация и нет Цены
	$mark = Showcase::getDefaultMark();
	$mark->setVal(':more.'.Path::encode('Комплектация').'.yes=1:count=5000');
	$md = $mark->getData();
	$data = Showcase::search($md); //Здесь выполнилось onshow и цена установилась
	foreach ($data['list'] as $pos) {
		//Позиции у которых есть Комплектация, были найдены и для них рассчиталась цена, которую и нужно записать в цену по умолчанию.
		$oldorder = Prices::deleteProp($pos['model_id'], $pos['item_num'], 'Цена');
		if (isset($pos['Цена'])) {
			Prices::insertProp($pos['model_id'], $pos['item_num'], 'Цена', $pos['Цена'], $oldorder, 0);
		}
	}
});
Event::handler('Showcase-catalog.onload', function ($obj) {
	$pos = &$obj['pos']; //pos после Xlsx::make()
	if (empty($pos['more']['Совместимость'])) return; //Комплект к которому относится позиция
	$kit = Catkit::explode($pos['more']['Совместимость'], $pos['producer']); //Это данные из Execl ещё не база данных producer = producer_nick
	$pos['more']['compatibilities'] = Catkit::implode($kit,',');//compatibilities комплекты в правильной записи
});
Showcase::add('count', function () {
	$conf = Showcase::$conf;
	return 12;
}, function (&$val) {
	$val = (int) $val;
	if ($val < 1 || $val > 10000) return false;
	return true;
});
Showcase::add('reverse', function () {
	return false;
}, function (&$val) {
	$val = !!$val;
	return true;
});
Showcase::add('sort', function () {
	$conf = Showcase::$conf;
	return $conf['sort'];
}, function ($val) {
	return in_array($val, array('source', 'isimage', 'iscost', 'is', 
	 'name', 'art', 'group', 'change', 'items'));
});

Showcase::add('producer', function () {
	return array();
}, function (&$val) {
	if (!is_array($val)) return false;
	$val = array_filter($val);
	$producers = array_keys($val);
	$producers = array_filter($producers, function ($value) {
		if (in_array($value,array('yes', 'no'))) return true;
		if (Showcase::getProducer($value)) return true;
		return false;
	});
	$val = array_fill_keys($producers, 1);
	return !!$val;
});

Showcase::add('group', function () {
	return array();
}, function (&$val) {

	if (!is_array($val)){
		$s = $val;
		$val = array();
		$val[$s] = 1;
	}
	$val = array_filter($val);
	$values = array_keys($val);
	$values = array_map(function ($nick) {
		return Path::encode($nick);
	}, $values);
	$values = array_filter($values, function ($nick) {
		if (in_array($nick, array('yes', 'no'))) return true;
		if (!$nick) return false;
		$group_id = Db::col('SELECT group_id from showcase_groups where group_nick = :group_nick',[
			':group_nick' => $nick
		]);
		if (!$group_id) return false;
		return true;
	});

	$val = array_fill_keys($values, 1);
	return !!$val;
});

Showcase::add('search', function () {
	return '';
}, function (&$val) {
	$val = strip_tags($val);
	$val = mb_strtolower($val);
	$val = preg_replace("/[\s\-\"\']+/u", " ", $val);
	return is_string($val);
});

Showcase::add('cost', function () {
	return array();
}, function (&$val) {
	if (!is_array($val)) return false;
	$val = array_filter($val);//Удаляет false значения
	$values = array_keys($val);
	$values = array_filter($values, function (&$value) {
		if (in_array($value, array('yes', 'no'))) return true;
		if (!$value) return false;
		return true;
	});
	$mm = isset($val['minmax']);
	if ($mm) $minmax = $val['minmax'];
	$val = array_fill_keys($values, 1);
	if ($mm) $val['minmax'] = $minmax;
	return !!$val;
});
Showcase::add('more', function () {
	return array();
}, function (&$val) {
	if (!is_array($val)) return;
	
	foreach ($val as $name => $values) {
		$newvalues = [];
		if (!is_array($values)) continue;
		foreach ($values as $key => $v) {
			$newkey = Path::encode($key);
			$newvalues[$newkey] = $v;
		}
		$val[$name] = $newvalues;
	}

	foreach ($val as $k => $v){
		if (!is_array($v)) {
			unset($val[$k]);
		} else {
			$last = false;
			foreach ($val[$k] as $key => $one) {
				if ($one && in_array($key, ['no','yes','minmax'])) $last = $key;
			}
			if ($last) {
				//Не противоречит no и minmax, yes и minmax
				
				if ($last == 'minmax') {
					if (!empty($val[$k]['no'])) {
						$val[$k] = [ 'no' => 1, 'minmax' => $val[$k]['minmax']];
					} else if (!empty($val['yes'])) {
						$val[$k] = [ 'yes' => 1, 'minmax' => $val[$k]['minmax']];	
					} else {
						$val[$k] = ['minmax' => $val[$k]['minmax']];
					}
				} else if ($last == 'yes') { //Показывать позиции с ценой

					unset($val[$k]['no']);
				} else if ($last == 'no') {
					unset($val[$k]['yes']);
				}


				/*if (!empty($val[$k]['minmax'])) {
					$new = ['minmax' => $val[$k]['minmax']];
					if (!empty($new['no'])) $new['no'] = 1;
					$val[$k] = $new;
				} else if (!empty($val[$k]['no']) && !empty($val[$k]['yes'])) { 
					//что-то должно обедить или объединиться
					//Выбрать все отмеченные и все не отмеченные сбрасывает выбор. 
					//Становится выбрать всё по этому критерию.
					unset($val[$k]); //критерий удаляется
					continue;
				} else if (!empty($val[$k]['yes'])) {
					//если все указанные, остальные уточнения не имеют смысла и остаётся только yes
					$val[$k] = [ 'yes' => 1 ];
				}*/
				
			} else {
				foreach($v as $kk => $vv){
					if (!$vv) unset($val[$k][$kk]);
					else $val[$k][$kk] = 1;//Все значения значений сбрасываются на 1
				}
				if (!$val[$k]) unset($val[$k]);	
			}
			
		}		
	}

	return !!$val;
});
Path::reqif(Showcase::$conf['phpoptions']);