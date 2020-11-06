<?php
use akiyatkin\meta\Meta;
use infrajs\db\Db;
use akiyatkin\showcase\api2\API;
use infrajs\path\Path;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;

$meta = new Meta();

$meta->addAction('', function () {

	return $this->empty();
});

$meta->addArgument('search');

$meta->addFunction('int', ['notempty'], function ($str, $pname) {
	$realint = (int) $str;
	$newstr = (string) $realint;
	if ($str !== $newstr) return $this->fail('meta.required', $pname);
	return $realint;
});
$meta->addFunction('notempty', function ($val, $pname) {

	if (!$val) return $this->fail('meta.required', $pname);
});
$meta->addArgument('model_id', ['int']);
$meta->addArgument('group_id', ['notempty']);
$meta->addArgument('producer_nick', ['notempty']);
$meta->addArgument('article_nick', ['notempty']);
$meta->addArgument('item_num', ['int'], function ($item_num, $pname) {
	
	if ($item_num > 255) return $this->fail('meta.required', $pname);
});


$meta->addVariable('model_id@', function () {
	if (!isset($_REQUEST['model_id'])) {
		extract($this->gets(['producer_nick','article_nick']));
		$model_id = Db::col('SELECT m.model_id from showcase_models m
			inner join showcase_producers p on p.producer_id = m.producer_id
			where 
			p.producer_nick = :producer_nick
			and m.article_nick = :article_nick
		', [
			':article_nick' => $article_nick,
			':producer_nick' => $producer_nick
		]);
	} else {
		extract($this->gets(['model_id']));
		$model_id = Db::col('SELECT m.model_id from showcase_models m where m.model_id = :model_id', [
			':model_id' => $model_id
		]);
	}
	if (!$model_id) return $this->err();
	return $model_id;
});

$meta->addAction('live', function () {
	extract($this->gets(['search']), EXTR_REFS);
	$split = preg_split("/[\s\-]/u", $search);
	
	$props_equal = [];
	$props_trim = [];
	$props_start = [];
	$props = [];

	$props[] = 'g.group_nick';
	$props[] = 'gp.group_nick';
	$props[] = 'v.value_nick';
	
	$props_start[] = 'p.producer_nick';
	if (sizeof($split) == 1) {
		$props[] = 'm.article_nick';
	} else {
		$props_trim[] = 'ip.text';
	}
	$where = [];
	$args = [];

	foreach ($split as $s) {
		$s = preg_replace("/ы$/", "", $s);
		$t = trim($s);
		if (!$t) continue;
		$s = Path::encode($s);
		if (!$s) continue;
		

		$w = [];
		foreach($props_equal as $p) {
			$w[] = $p.' = ?';
			$args[] = $s;
		}
		foreach($props as $p) {
			$w[] = $p.' like ?';
			$args[] = '%'.$s.'%';
		}
		foreach ($props_start as $p) {
			$w[] = $p.' like ?';
			$args[] = $s.'%';
		}
		foreach ($props_trim as $p) {
			$w[] = $p.' like ?';
			$args[] = '%'.$t.'%';
		}
		$where[] = '('.implode(' or ', $w).')';
	}

	$sql = 'SELECT distinct m.model_id from showcase_models m
		left join showcase_producers p on p.producer_id = m.producer_id
		left join showcase_iprops ip on ip.model_id = m.model_id
		left join showcase_groups g on g.group_id = m.group_id
		left join showcase_groups gp on g.parent_id = gp.group_id
		left join showcase_values v on ip.value_id = v.value_id
		where 
		'.implode(' and ', $where).'
		 order by m.model_id
		limit 0,12';
	$list = Db::colAll($sql, $args);
	foreach ($list as $i => $model_id) {
		$list[$i] = Showcase::getModelEasyById($model_id);
	}
	$this->ans['list'] = $list;
	return $this->ret();
});
$meta->addAction('group', function ($val, $pname) {
	extract($this->gets(['group_id']), EXTR_REFS);
	$group = API::getGroupById($group_id);
	$group['props'] = $group['options']['props'];
	unset($group['options']);
	foreach ($group['props'] as $k=>$v) {
		unset($group['props'][$k]['tplfilter']);
	}

	$parent = &$group;
	while (!empty($parent['parent'])) {
		$parent = &$parent['parent'];
		unset($parent['options']);
	}

	$this->ans['group'] = $group;
	return $this->ret();
});
$meta->addAction('actions', function () {
	$prop_nick = Path::encode('Наличие');
	$prop_id = Db::col('SELECT prop_id from showcase_props where prop_nick = :prop_nick', [
		':prop_nick' => $prop_nick
	]);

	$value = 'Распродажа';
	$value_nick = Path::encode($value);
	$value_id1 = Db::col('SELECT value_id from showcase_values where value_nick = :value_nick', [
		':value_nick' => $value_nick
	]);

	$value = 'Акция';
	$value_nick = Path::encode($value);
	$value_id2 = Db::col('SELECT value_id from showcase_values where value_nick = :value_nick', [
		':value_nick' => $value_nick
	]);

	$prop_nick = Path::encode('Цена');
	$cost_id = Db::col('SELECT prop_id from showcase_props where prop_nick = :prop_nick', [
		':prop_nick' => $prop_nick
	]);

	$prop_nick = Path::encode('images');
	$image_id = Db::col('SELECT prop_id from showcase_props where prop_nick = :prop_nick', [
		':prop_nick' => $prop_nick
	]);
	$sql = 'SELECT distinct m.model_id from showcase_models m
		left join showcase_iprops ip on ip.model_id = m.model_id
		left join showcase_iprops ipc on (ipc.model_id = m.model_id and ipc.prop_id = :cost_id)
		left join showcase_iprops ipi on (ipi.model_id = m.model_id and ipi.prop_id = :image_id)
		WHERE
		ip.prop_id = :prop_id and (ip.value_id = :value_id1 or ip.value_id = :value_id2) and ipi.text is not null
		order by ipc.number DESC
		limit 0,12';

	$list = Db::colAll($sql, [
		':prop_id' => $prop_id,
		':cost_id' => $cost_id,
		':image_id' => $image_id,
		':value_id1' => $value_id1,
		':value_id2' => $value_id2
	]);

	foreach ($list as $i => $model_id) {
		$list[$i] = Showcase::getModelEasyById($model_id);
	}
	$this->ans['list'] = $list;
	return $this->ret();
});
$meta->addAction('columns', function () {
	$columns = Showcase::getOption(['columns']);
	return $columns;
});
$meta->addAction('pos', function () {
	extract($this->gets(['producer_nick','article_nick','item_num']));
	$pos = Showcase::getModelEasy($producer_nick, $article_nick, $item_num);
	//breadcrumb собираем в шаблоне из group
	//more собираем в шаблоне из columns
	//items и itemrows??? items - сделать отдельным запросом и отдельным слоем
	//catkit
	
	$this->ans['pos'] = $pos;
	return $this->ret();
});





$meta->addAction('items', function () {
	//props у items дублируются. Найти отличия не так уж просто.
	//Надо найти различия
	/*
		item, prop, val
		1, 1, 5
		2, 1, 6
	*/

	extract($this->gets(['model_id@']));
	$list = Db::all('SELECT ip.item_num, p.prop, v.value, ip.number, ip.text from showcase_iprops ip
		left join showcase_values v on ip.value_id = v.value_id
		left join showcase_props p on p.prop_id = ip.prop_id
		where 
		ip.model_id = :model_id
	', [
		':model_id' => $model_id
	]);
	
	$items = [];
	foreach ($list as $p) {
		$item_num = $p['item_num'];
		if (empty($items[$item_num])) $items[$item_num] = [];
		$val = $p['value'] ?? $p['number'] ?? $p['text'];
		if (in_array($p['prop'], Data::$files)) {
			if (!isset($items[$item_num][$p['prop']])) $items[$item_num][$p['prop']] = [];
			$items[$item_num][$p['prop']][] = $val;
		} else {		
			if (isset($items[$item_num][$p['prop']])) $items[$item_num][$p['prop']] .= ', '.$val;
			else $items[$item_num][$p['prop']] = $val;
		}
	}

	
	$diff = [];
	$first = $items["1"];

	foreach ($items as $num => $item) {
		foreach ($item as $prop => $val) {
			if ($first[$prop] != $val) {
				$diff[] = $prop;
			}
		}
	}
	foreach ($items as $num => $item) {
		foreach ($item as $p => $val){
			if (!in_array($p, $diff)) unset($items[$num][$p]);
		}
	}


	return $items;
});

return $meta->init([
	'name'=>'showcase',
	'base'=>'-showcase/api2/'
]);
