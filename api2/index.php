<?php
use akiyatkin\meta\Meta;
use infrajs\db\Db;
use infrajs\path\Path;
use akiyatkin\showcase\Showcase;

$meta = new Meta();

$meta->addAction('', function () {
	return $this->empty();
});

$meta->addArgument('search');

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
		limit 0,10';
	$list = Db::colAll($sql, $args);
	// // $list = false;
	// if (!$list) {
	// 	$r = [];
	// 	foreach($split as $k=>$s) {
	// 		$r[] = trim($s);
	// 	}
	// 	$r = array_filter($r);
	// 	$where = [];
	// 	$args = [];
	// 	foreach ($r as $s) {
	// 		$where[] = 'ip.text like ?';
	// 		$args[] = '%'.$s.'%';
	// 	}
	// 	$sql = 'SELECT distinct m.model_id from showcase_models m, showcase_iprops ip
	// 	where 
	// 	ip.model_id = m.model_id and 
	// 	'.implode(' and ', $where).'
	// 	order by m.model_id
	// 	limit 0,10';
	// 	$list = Db::colAll($sql, $args);
	// }
	foreach ($list as $i => $model_id) {
		$list[$i] = Showcase::getModelEasyById($model_id);
	}
	$this->ans['list'] = $list;
	return $this->ret();
});

$meta->init([
	'name'=>'showcase',
	'base'=>'-showcase/api2/'
]);