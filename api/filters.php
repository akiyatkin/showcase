<?php
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\ans\Ans;
use infrajs\rest\Rest;
use infrajs\load\Load;


return Rest::get( function () {
	$ans = array();

	$md = Showcase::initMark($ans);

	$group = '';
	foreach ($md['group'] as $group => $one) break;
	$arlist = Showcase::getOptions()['filters'];
	
	$ar = array();
	
	if (!$group) $group = Showcase::$conf['title'];
	$group = Showcase::getGroup($group);
	if (!$group) $group = Showcase::getGroup();

	for ($i = sizeof($group['path'])-1; $i >= 0; $i--) {
		$g = $group['path'][$i];
		if (!isset($arlist[$g])) continue;
		$ar = array_merge($ar,$arlist[$g]);
	}
	
	if (!$ar && isset($arlist[Showcase::$conf['title']])) {
		$ar = $arlist[Showcase::$conf['title']];
	}
	$props = Ans::get('props','string');
	if ($props) {
		$props = explode(',', $props);
		$ar = array_merge($ar, $props);
	}
	$params = [];
	
	$columns = Showcase::getOption(['columns']);

	$groups = Showcase::getGroupsIn($md);
	
	if ($groups) $groups = 'INNER JOIN showcase_models m on (m.model_id = mv.model_id and m.group_id in ('.implode(',', $groups).'))';
	else $groups = '';
	
	

	foreach ($ar as $prop_nick) {
		if ($prop_nick == 'producer') {//Артикул, Группа
			$row = [];			
			$values = Data::all('SELECT pr.producer as value, pr.producer_nick as value_nick, count(*) as count 
				FROM showcase_models mv
				left join showcase_producers pr on mv.producer_id = pr.producer_id
			'.$groups.' group by pr.producer_id order by value');
			
			//if (sizeof($values) < 2) continue;
			$params[$prop_nick] = [
				'values' => $values
			];
			$params[$prop_nick] += array(
				'prop_nick' => 'producer',
				'prop' => 'Производитель',
				'type' => 'producer'
			);
		} else {
			$row = Data::fetch('SELECT prop_id, prop from showcase_props where prop_nick = ?',[$prop_nick]);
			if(!$row) continue;
			list($prop_id, $prop) = array_values($row);
			if(!$prop_id) continue;
			$type = Data::checkType($prop_nick);
			$def = ($type == 'number')? 'range':'value';
			$filtertype = Showcase::getOption(['props', $prop_nick, 'filter'], $def);
			if ($filtertype == 'value') {
				if ($type == 'value') {
					$values = Data::all('SELECT v.value, v.value_nick, count(*) as count FROM showcase_mvalues mv
					left join showcase_values v on v.value_id = mv.value_id
					'.$groups.'
					where mv.prop_id = :prop_id
					group by mv.value_id
					order by value
					',[':prop_id'=>$prop_id]);
				} else if ($type == 'number') {
					
					$values = Data::all('
						SELECT mv.number as value, mv.number as value_nick, count(*) as count 
						FROM showcase_mnumbers mv
						'.$groups.'
						WHERE mv.prop_id = :prop_id
					group by mv.number
					order by mv.number DESC
					', [':prop_id'=>$prop_id]);
					foreach ($values as $i => $val) {
						$values[$i]['value'] = (float) $val['value'];
						$values[$i]['value_nick'] = (float) $val['value_nick'];
					}
				} else {
					continue;
				}
				//if (sizeof($values)<2) continue;
				$params[$prop_nick] = [
					'values' => $values
				];
			} else if ($filtertype == 'range') {
				if ($type == 'number') {	
					
					$row = Data::fetch('
						SELECT mv.model_id, min(mv.number) as min, max(mv.number) as max 
						FROM showcase_mnumbers mv
						'.$groups.'
						WHERE mv.prop_id = :prop_id
					', [':prop_id' => $prop_id]);


					$dif = round($row['max'] - $row['min']);
					$len = strlen($dif);
					if ($len < 2 ) {
						$step = 1;
					} else {
						$step = pow(10, $len-2);
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
				} else {
					continue;
				}
				if ($row['max'] == $row['min']) continue;
				$params[$prop_nick] = $row;
			}
			
			$params[$prop_nick] += array(
				'prop_nick' => $prop_nick,
				'prop' => $prop,
				'type' => $filtertype
			);
		}
	}
	$ans['list'] = $params;
	return Ans::ret($ans);
});