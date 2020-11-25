<?php
use akiyatkin\meta\Meta;
use infrajs\db\Db;
use akiyatkin\showcase\api2\API;
use infrajs\path\Path;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\load\Load;
use infrajs\excel\Xlsx;

$meta = new Meta();

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
$meta->addAction('groups', function ($val, $pname) {	
	$list = Data::fetchto('
		SELECT g.group_id, g.parent_id, g.group_nick, g.icon, g.order, g.group, count(distinct m.model_id) as count, g2.group_nick as parent_nick, g2.group as parent FROM showcase_groups g
		left JOIN showcase_models m ON g.group_id = m.group_id
		left JOIN showcase_groups g2 ON g2.group_id = g.parent_id
		GROUP BY group_nick
		ORDER by g.order
	','group_nick',[]);

	$parents = [];
	foreach ($list as $i=>&$group) {
		if (empty($parent[$group['parent_nick']])) $parent[$group['parent_nick']] = [];
		$parents[$group['parent_nick']][] = &$group;
	}
	$p = null;
	foreach ($list as $i => &$group) {
		if (!isset($parents[$group['group_nick']])) continue;
		$group['childs'] = $parents[$group['group_nick']];
	}
	$childs = $parents[''];
	$root = $childs[0];
	
	Xlsx::runGroups($root, function &(&$group, $i, &$parent) {
		if (!$group['count'] && empty($group['childs'])) {
			unset($parent['childs'][$i]);
			$parent['childs'] = array_values($parent['childs']);
		}

		$r = null;
		return $r;
	}, true);

	Xlsx::runGroups($root, function &(&$group, $i, &$parent) {
		unset($group['parent_nick']);
		unset($group['parent']);
		unset($group['group_id']);
		unset($group['icon']);
		unset($group['count']);
		unset($group['parent_id']);
		unset($group['order']);
		$r = null;
		return $r;
	});

	

	$this->ans['root'] = $root;
	return $this->ret();
});
$meta->addAction('filters', function () {
	$md = Showcase::initMark($this->ans);
	$group_nick = Path::encode(Showcase::$conf['title']);
	foreach ($md['group'] ?? [] as $group_nick => $one) break;
	$group_id = Db::col('SELECT group_id from showcase_groups where group_nick = :group_nick', [
		':group_nick' => $group_nick
	]);
	$group = API::getGroupById($group_id);
	unset($group['options']['props']);
	unset($group['options']['showlist']);
	unset($group['parent']);
	$props = Showcase::getOptions()['props'];

	$list = [];

	$gs = Showcase::getGroupsIn($md);
	if ($gs) $grwhere = 'm.group_id in ('.implode(',', $gs).')';
	else $grwhere = '1=1';

	foreach ($group['options']['filters'] as $name) {
		$p = $props[$name];
		$prop_nick = Path::encode($name);
		$p['prop_nick'] = $prop_nick;
		if ($prop_nick == 'producer') {
			$values = Data::all('SELECT pr.producer as value, pr.producer_nick as value_nick, count(*) as count 
				FROM showcase_models m
				INNER join showcase_producers pr on m.producer_id = pr.producer_id
			where '.$grwhere.' 
			group by pr.producer_id 
			order by count DESC');// order by value

			$p['values'] = $values;
		} else {
			$row = Data::fetch('SELECT prop_id, prop from showcase_props where prop_nick = :prop_nick',
				[
					':prop_nick' => $prop_nick
				]
			);
			if (!$row) continue;
			list($prop_id, $prop) = array_values($row);
			$type = Data::checkType($prop_nick);


			if ($p['filter'] ?? '' == 'range') {
				if ($type != 'number') continue;
			
				$row = Data::fetch('
					SELECT min(mv.number) as min, max(mv.number) as max 
					FROM showcase_models m
					left join showcase_iprops mv on mv.model_id = m.model_id
					where '.$grwhere.' 
					and mv.prop_id = :prop_id
				', [':prop_id' => $prop_id]);



				$dif = round($row['max'] - $row['min']);
				$len = strlen($dif);
				if ($len < 2 ) {
					$step = 1;
				} else {
					$step = pow(10, $len - 2);
				}
				$row['min'] = floor($row['min']/$step)*$step;
				$row['max'] = ceil($row['max']/$step)*$step;
				$row['step'] = $step;
				$row['minval'] = $row['min'];
				$row['maxval'] = $row['max'];
				

				if (isset($md['more'][$prop_nick]['minmax'])) {
					$minmax = $md['more'][$prop_nick]['minmax'];
					$r = explode('/',$minmax);
					if (sizeof($r) == 2) {
						$row['minval'] = floor($r[0]/$step)*$step;
						$row['maxval'] = ceil($r[1]/$step)*$step;
						if ($row['minval'] < $row['min']) $row['minval'] = $row['min'];
						if ($row['maxval'] > $row['max']) $row['maxval'] = $row['max'];
					}
				}
				
				if ($row['max'] == $row['min']) continue;
				$p += $row;
			} else {
				$sql = '
				SELECT v.value, v.value_nick, count(*) as count
				FROM showcase_models m
				left join showcase_iprops mv on (mv.model_id = m.model_id and mv.prop_id = :prop_id)
				left join showcase_values v on v.value_id = mv.value_id
				where '.$grwhere.'
				group by v.value_id
				order by count DESC
				';
				$values = Data::all($sql,[':prop_id' => $prop_id]);
				$p['values'] = $values;

				if (isset($p['chain'])) {
					$data = Load::loadJSON($p['chain']);
					$data = $data['data'];
					$chain = [];
					$el = array_reverse($data['head']);

					$vals = array_reduce($p['values'], function($vals, $v){
						$vals[$v['value_nick']] = 1;
						return $vals;
					},[]);

					Xlsx::runPoss($data, function($pos) use (&$list, $p, &$chain, $el, $vals) {
						if (empty($pos[$el[sizeof($el)-1]])) return;
						$keyval = Path::encode($pos[$el[sizeof($el)-1]]);
						if (!isset($vals[$keyval])) return;
						array_reduce($el, function ($ar, $key) use($pos, &$chain, &$last){
							$child = &$ar[0];
							if(empty($child['childs'])) $child['childs'] = [];
							$child['key'] = $key;
							if (!isset($pos[$key])) return $ar;
							$val = $pos[$key];
							$nick = Path::encode($val);
							if (empty($child['childs'][$nick])) $child['childs'][$nick] = ['value'=>$val,'nick'=>$nick];
							return [&$child['childs'][$nick]];
						}, [&$chain]);
					});
					$p['chain'] = $chain;
					unset($p['values']);
				}
			}
		}
		
		$list[$prop_nick] = $p;
	}

	
	$this->ans['list'] = $list;
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
		$list[$i]['showcase'] = array();
		$group = API::getGroupById($list[$i]['group_id']);
		$list[$i]['showcase']['props'] = $group['options']['props'];
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
