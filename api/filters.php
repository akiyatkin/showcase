<?php
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\ans\Ans;
use infrajs\rest\Rest;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\excel\Xlsx;


return Rest::get( function () {
	
	$ans = array();
	$md = Showcase::initMark($ans);

	$arlist = array();
		
		//depricated
		$arlist2 = Showcase::getOptions()['filters']['groups'];
		foreach ($arlist2 as $k=>$v) {
			$arlist[Path::encode($k)] = [];
			foreach ($v as $vv) $arlist[Path::encode($k)][] = Path::encode($vv);
		}

	$arlist2 = Showcase::getOptions()['groups'];
	foreach ($arlist2 as $k=>$v) {
		$k = Path::encode($k);
		if (empty($v['filters'])) continue;
		$arlist[$k] = [];
		foreach ($v['filters'] as $vv) $arlist[$k][] = Path::encode($vv);
	}

	$title_nick = Path::encode(Showcase::$conf['title']);
	if(isset($arlist[$title_nick])) {
		$ar = $arlist[$title_nick];
	} else {
		$ar = [];
	}
	
	for ($i = sizeof($ans['group']['path'])-1; $i >= 0; $i--) {
		$g = $ans['group']['path'][$i];
		if (!isset($arlist[$g])) continue;
		$ar = array_merge($arlist[$g],$ar);
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
	
	if (Showcase::getOptions()['filters']['order'] == 'count') {
		//depricated
		$order = ' order by count DESC';
	} else {
		$order = ' order by value';	
	}

	foreach ($ar as $prop_nick) {
		if ($prop_nick == 'producer') {//Артикул, Группа
			$row = [];
			
			$values = Data::all('SELECT pr.producer as value, pr.producer_nick as value_nick, count(*) as count 
				FROM showcase_models mv
				INNER join showcase_producers pr on mv.producer_id = pr.producer_id
			'.$groups.' group by pr.producer_id'.$order);// order by value
			
			
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
			$filtertype = $def;
			//$filtertype = Showcase::getOption(['props', $prop_nick, 'filter'], $def);

			if ($filtertype == 'value') {
				if ($type == 'value') {



					$gs = Showcase::getGroupsIn($md);
					if ($gs) $grwhere = 'm.group_id in ('.implode(',', $gs).')';
					else $grwhere = '1=1';

					$sql = 'SELECT v.value, v.value_nick FROM showcase_models m
					left join showcase_items i on (i.model_id = m.model_id)
					left join showcase_mvalues mv on ((mv.item_num = i.item_num or mv.item_num = 0) and mv.model_id = m.model_id and mv.prop_id = :prop_id)
					left join showcase_values v on v.value_id = mv.value_id
					where '.$grwhere.'
					group by v.value_id';

					
					$values = Data::all($sql,[':prop_id'=>$prop_id]);
					if (isset($values[0]) && empty($values[0]['value'])) {
						unset($values[0]);
						$values[] = [
							'value' => 'Не указано',
							'value_nick' => 'no'
						];
					}
					/*echo '<pre>';
					print_r($values);
					exit;
					if ($isnull) continue;

					


					$values = Data::all('SELECT v.value, v.value_nick, count(*) as count FROM showcase_mvalues mv
					left join showcase_values v on v.value_id = mv.value_id
					'.$groups.'
					where mv.prop_id = :prop_id
					group by mv.value_id
					'.$order.'
					',[':prop_id'=>$prop_id]);
					print_r($values);*/

					//sort($values);
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
						SELECT min(mv.number) as min, max(mv.number) as max 
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

	$columns = ['producer'];//Showcase::getOption(['columns']);
	/*
	Обработка параметров в showcase.json каждого фильтра
	- showalways
	- tpl

	chain выбор
	- json
	- key

	*/
	foreach ($params as $k=>$p) {
		if (!in_array($k, $columns)) $params[$k]['more'] = true;

		$params[$k] += Showcase::getOption(['filters', $p['prop_nick']],[]);

			//depricated
			$params[$k] += Showcase::getOption(['filters','props',$p['prop_nick']],['tpl'=>'prop-default']);

		$params[$k] += ['tpl'=>'prop-default'];

		$p = $params[$k];
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

			
			$params[$k]['chain'] = $chain;
			//unset($params[$k]['values']);
		}


		if (
			empty($params[$k]['showalways']) 
			&& empty($ans['md'][$p['prop_nick']]) 
			&& isset($params[$k]['values']) 
			&& sizeof($p['values'])<2) 
		{
			unset($params[$k]);
		}
	}
	
	$ans['list'] = $params;
	return Ans::ret($ans);
});