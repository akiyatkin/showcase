<?php
use infrajs\db\Db;
use infrajs\path\Path;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\api2\API;

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

$meta->addAction('modelgroup', function () {
	extract($this->gets(['group_id']), EXTR_REFS);
	$group = API::getGroupById($group_id);
	unset($group['parent']['options']);
	unset($group['parent']['parent']);
	$group['props'] = $group['options']['props'];
	unset($group['options']);

	foreach ($group['props'] as $k=>$v) {
		unset($group['props'][$k]['tplfilter']);
	}
	$this->ans['group'] = $group;
	return $this->ret();
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