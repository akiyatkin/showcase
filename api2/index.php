<?php
use akiyatkin\meta\Meta;
use infrajs\db\Db;
use akiyatkin\showcase\api2\API;
use infrajs\path\Path;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\load\Load;
use infrajs\excel\Xlsx;
use infrajs\rubrics\Rubrics;
use infrajs\layer\seojson\Seojson;

$meta = new Meta();

$meta->addArgument('query');

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
$meta->addArgument('p', ['int']);
$meta->addArgument('group_id', ['notempty']);



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
	extract($this->gets(['query']), EXTR_REFS);
	$split = preg_split("/[\s\-]/u", $query);
	
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
$meta->addFunction('encode', function ($val) {
	return Path::encode($val);
});

$meta->addArgument('showlist');
$meta->addAction('search', function () {
	$ans = &$this->ans;
	$md = Showcase::initMark($ans);
	$ans['page'] = $this->get('p');
	$count = 0;

	if ($count) $md['count'] = $count;
	if ($ans['page'] < 1) $ans['page'] = 1;
	$ans['is'] = ''; //group producer search Что было найдено по запросу val (Отдельный файл is:change)
	$ans['descr'] = '';//абзац текста в начале страницы';
	$ans['text'] = ''; //большая статья снизу всего
	$ans['name'] = ''; //заголовок длинный и человеческий
	$ans['breadcrumbs'] = array();//Путь где я нахожусь
	$ans['filters'] = array();//Данные для формирования интерфейса фильтрации, опции и тп
	$ans['groups'] = array();
	$ans['producers'] = array();
	$ans['numbers'] = array(); //Данные для построения интерфейса постраничной разбивки
	$ans['list'] = array(); //Массив позиций


	Showcase::makeBreadcrumb($md, $ans, $ans['page']);

	
	$group_nick = Path::encode(Showcase::$conf['title']);
	foreach ($md['group'] ?? [] as $group_nick => $one) break;
	$group_id = Db::col('SELECT group_id from showcase_groups where group_nick = :group_nick', [
		':group_nick' => $group_nick
	]);
	$group = API::getGroupById($group_id);
	
	$ans['showlist'] = $this->get('showlist') || !empty($group['options']['showlist']);
	





	$showlist = $ans['showlist'];
	$page = $ans['page'];
	if (empty($md['count'])) $count = 0;
	else $count = $md['count'];

	
	$cost_id = Db::col('SELECT prop_id from showcase_props where prop_nick = :prop_nick', [
		':prop_nick' => Path::encode('Цена')
	]);
	$name_id = Db::col('SELECT prop_id from showcase_props where prop_nick = :prop_nick', [
		':prop_nick' => Path::encode('Наименование')
	]);
	$image_id = Db::col('SELECT prop_id from showcase_props where prop_nick = :prop_nick', [
		':prop_nick' => Path::encode('images')
	]);
	$nalichie_id = Db::col('SELECT prop_id from showcase_props where prop_nick = :prop_nick', [
		':prop_nick' => Path::encode('Наличие')
	]);
	
	$ans['filters'] = [];


	$grquery = '';
	$groups = Showcase::getGroupsIn($md);
	if ($groups) {
		$grquery = implode(',', $groups);
		$grquery = 'and m.group_id in (' . $grquery . ')';
	}


	$prquery = '';
	$prods = [];
	if (!empty($md['producer'])) foreach ($md['producer'] as $prod => $one) {
		$prod = Db::fetch('SELECT producer_id, producer, producer_nick from showcase_producers where producer_nick = :producer_nick', [
			':producer_nick' => $prod
		]);
		if ($prod) $prods[] = $prod;
	}
	if ($prods) { //Если есть группа надо достать все вложенные группы
		$prods = array_unique($prods);
		$ans['filters'][] = array(
			'name' => 'producer',
			'value' => implode(', ', array_column($prods, 'producer')),
			'title' => 'Производитель'
		);

		$prods = array_column($prods, 'producer_id');
		$prquery = implode(',', $prods);
		$prquery = 'and m.producer_id in (' . $prquery . ')';
	}


	$nal1 = Db::col('SELECT value_id from showcase_values where value_nick = :value_nick', [
		':value_nick' => Path::encode('Акция')
	]);
	$nal2 = Db::col('SELECT value_id from showcase_values where value_nick = :value_nick', [
		':value_nick' => Path::encode('Распродажа')
	]);
	$nal3 = Db::col('SELECT value_id from showcase_values where value_nick = :value_nick', [
		':value_nick' => Path::encode('В наличии')
	]);
	$nal4 = Db::col('SELECT value_id from showcase_values where value_nick = :value_nick', [
		':value_nick' => Path::encode('Есть в наличии')
	]);
	$nal5 = Db::col('SELECT value_id from showcase_values where value_nick = :value_nick', [
		':value_nick' => Path::encode('Мало')
	]);
	

	$join = [];
	$no = [];

	if (!empty($md['more'])) {
		foreach ($md['more'] as $prop_nick => $vals) {
			$titles = [];
			foreach ($vals as $v => $one) {
				if ($v === 'no') {
					//if (sizeof($vals) > 1) continue;
					$titles[] = 'не указано';
				} else if ($v === 'yes') {
					$titles[] = 'Указано';
				} else if ($v === 'minmax') {
					$r = explode('/', $one);
					if (sizeof($r) == 2) {
						if ($prop_nick == 'Цена') {
							$fncost = Template::$scope['~cost'];
							$titles[] = 'От ' . $fncost($r[0]) . ' до ' . $fncost($r[1]) . ' руб.';
						} else {
							$titles[] = 'От ' . $r[0] . ' до ' . $r[1];
						}
					} else {
						$titles[] = $one;
					}
				} else {
					$row = Showcase::getMean($prop_nick, $v);
					if ($row) $titles[] = $row['mean'];
				}
			}
			$titles = implode(' или ', $titles);

			$prop = Db::fetch('SELECT prop_id, prop from showcase_props where prop_nick = :prop_nick', [
				':prop_nick' => $prop_nick
			]);
			if (!$prop) {
				$ans['filters'][] = array(
					'name' => 'more.' . $prop_nick,
					'value' => $titles,
					'title' => 'Нет свойства ' . $prop_nick
				);
				$no[] = 'and 1=0';
				continue;
			}

			$prop_id = $prop['prop_id'];
			$title = Showcase::getOption(['props', $prop['prop'], 'title'], $prop['prop']);
			$ans['filters'][] = array(
				'name' => 'more.' . $prop_nick,
				'value' => $titles,
				'title' => $title
			);
			$type = Data::checkType($prop_nick);

			$un = $prop_id;
			if ($type == 'value') {
				if (isset($vals['no'])) {
					unset($vals['no']);
					$join[] = 'LEFT JOIN showcase_iprops p' . $un . ' on (p' . $un . '.model_id = m.model_id and p' . $un . '.prop_id = ' . $prop_id . ')';
					if ($vals) {
						$joinp = [];
						foreach ($vals as $val => $one) {
							$value_id = Data::initValue($val);
							$joinp[] = 'p' . $un . '.value_id = ' . $value_id;
						}
						$vals = [];
						$joinp = implode(' OR ', $joinp);
						$no[] = 'and (p' . $un . '.value_id is null OR (' . $joinp . '))';
					} else {
						$no[] = 'and p' . $un . '.value_id is null';
					}
				} else if (isset($vals['yes'])) {
					unset($vals['yes']);
					$join[] = 'INNER JOIN showcase_iprops p' . $un . ' on (p' . $un . '.model_id = m.model_id and p' . $un . '.item_num = i.item_num and p' . $un . '.prop_id = ' . $prop_id . ')';
				}
			} else if ($type == 'text') {
				if (!empty($vals['no'])) {

					$join[] = 'LEFT JOIN showcase_iprops p' . $un . ' on (p' . $un . '.model_id = m.model_id and p' . $un . '.prop_id = ' . $prop_id . ')';


					$no[] = 'and (p' . $un . '.text is null)';
					//Только no
					unset($vals['no']);


					if ($vals) {
						$joinp = [];
						foreach ($vals as $val => $one) {
							$joinp[] = 'p' . $un . '.text = ' . $val;
						}
						$vals = [];
						$joinp = implode(' OR ', $joinp);
						$no[] = 'and (p' . $un . '.text is null OR (' . $joinp . '))';
					}
				} else if (isset($vals['yes'])) {
					unset($vals['yes']);
					$join[] = 'INNER JOIN showcase_iprops p' . $un . ' on (p' . $un . '.model_id = m.model_id and p' . $un . '.item_num = i.item_num and p' . $un . '.prop_id = ' . $prop_id . ')';
				}
			} else if ($type == 'number') {

				if (!empty($vals['no']) || !empty($vals['minmax'])) {
					$join[] = 'LEFT JOIN showcase_iprops p' . $un . ' on (p' . $un . '.model_id = m.model_id and p' . $un . '.item_num = i.item_num and p' . $un . '.prop_id = ' . $prop_id . ')';

					$nn = !empty($vals['no']);
					$mm = !empty($vals['minmax']);
					//unset($vals['no']);
					if ($mm) {
						$r = explode('/', $vals['minmax']);
						unset($vals['minmax']);
						if (sizeof($r) == 2) {
							$min = (float) $r[0];
							$max = (float) $r[1];
						}
						unset($vals['no']);
						unset($vals['minmax']);
						if ($nn) {
							if ($vals) {
								$joinp = [];
								foreach ($vals as $val => $one) {
									$joinp[] = 'p' . $un . '.number = ' . $val;
								}

								$vals = [];
								$joinp = implode(' OR ', $joinp);
								$no[] = 'and (p' . $un . '.number is null OR (p' . $un . '.number >= ' . $min . ' AND p' . $un . '.number <= ' . $max . ') OR (' . $joinp . '))';
							} else {
								$no[] = 'and (p' . $un . '.number is null OR (p' . $un . '.number >= ' . $min . ' AND p' . $un . '.number <= ' . $max . '))';
							}
						} else {
							$no[] = 'and (p' . $un . '.number >= ' . $min . ' AND p' . $un . '.number <= ' . $max . ')';
						}
					} else {
						$no[] = 'and (p' . $un . '.number is null)';
						//Только no
						unset($vals['no']);
					}

					if ($vals) {
						$joinp = [];
						foreach ($vals as $val => $one) {
							$joinp[] = 'p' . $un . '.number = ' . $val;
						}
						$vals = [];
						$joinp = implode(' OR ', $joinp);
						$no[] = 'and (p' . $un . '.number is null OR (' . $joinp . '))';
					}
				} else if (isset($vals['yes'])) {
					unset($vals['yes']);
					$join[] = 'INNER JOIN showcase_iprops p' . $un . ' on (p' . $un . '.model_id = m.model_id and p' . $un . '.item_num = i.item_num and p' . $un . '.prop_id = ' . $prop_id . ')';
				}
			}

			if ($vals) {
				$un = $prop_id . 'v';
				$joinp = [];
				if ($type == 'value') {
					foreach ($vals as $val => $one) {
						$value_id = Data::initValue($val);
						$joinp[] = 'p' . $un . '.value_id = ' . $value_id;
					}
				} else if ($type == 'number') {
					foreach ($vals as $val => $one) {
						$number = (float) $val;
						$joinp[] = 'p' . $un . '.number = ' . $number;
					}
				} else if ($type == 'text') {
				}
				$joinp = implode(' OR ', $joinp);
				$join[] = 'INNER JOIN showcase_iprops p' . $un . ' on (p' . $un . '.model_id = i.model_id and p' . $un . '.item_num = i.item_num and p' . $un . '.prop_id = ' . $prop_id . ' and (' . $joinp . '))';
			}
		}
	}

	if (!empty($md['search'])) {
		$v = $md['search'];
		//$v = Path::encode($v);
		$v = preg_split("/[\s\-]+/", mb_strtolower($v));
		$v = array_unique($v);
		$str = '';
		foreach ($v as $i => $s) {
			$v[$i] = preg_replace("/ы$/", "", $s);
			$s = $v[$i];
			$str .= 'and (m.model_id in (SELECT smv' . $i . '.model_id from showcase_values sv' . $i . '
				inner join showcase_iprops smv' . $i . ' on smv' . $i . '.value_id = sv' . $i . '.value_id
				where sv' . $i . '.value_nick like "%' . $s . '%") 

				OR m.model_id in (SELECT svt' . $i . '.model_id from showcase_iprops svt' . $i . '
				where svt' . $i . '.text like "%' . $s . '%") 

				OR m.model_id in (SELECT svn' . $i . '.model_id from showcase_iprops svn' . $i . '
				where svn' . $i . '.number like "%' . $s . '%") 

				OR m.article_nick LIKE "%' . $s . '%" 
				OR m.article LIKE "%' . $s . '%" 
				OR i.item_nick LIKE "%' . $s . '%" 
				OR g.group_nick LIKE "%' . $s . '%" 
				OR g.group LIKE "%' . $s . '%" 
				OR p.group_nick LIKE "%' . $s . '%" 
				OR p.group LIKE "%' . $s . '%" 
				OR p2.group_nick LIKE "%' . $s . '%" 
				OR p2.group LIKE "%' . $s . '%" 

				OR m.model_id LIKE "%' . $s . '%" 
				OR pr.producer_nick LIKE "%' . $s . '%"
			)';
		}
		$join[] = 'LEFT JOIN showcase_groups p2 on p.parent_id = p2.group_id';
		$ans['filters'][] = array(
			'title' => 'Поиск',
			'name' => 'search',
			'value' => strip_tags($md['search'])
		);
		$no[] = $str;
	}







	//sort нужно регистрировать 
	$sort = 'ORDER BY';

	if ($md['sort'] == 'items') {
		$sort = 'ORDER BY IF(i.item_nick = "",1,0),';
	}

	$sort .= '
		IF(mn3.text is null,1,0),
		IF(mn2.value_id = :nal1,0,1),
		IF(mn2.value_id = :nal2,0,1), 
		IF(mn2.value_id = :nal3,0,1), 
		IF(mn2.value_id = :nal4,0,1), 
		IF(mn2.value_id = :nal5,0,1), 
		IF(mn.number IS NULL,1,0), 
		mn.number';
	$binds = [':nal1' => $nal1, ':nal2' => $nal2, ':nal3' => $nal3, ':nal4' => $nal4, ':nal5' => $nal5];
	$groupbinds = [];

	if ($md['sort'] == 'source') {
		$sort = '';
		$binds = [];
	}
	if ($md['sort'] == 'isimage') {
		$sort = 'ORDER BY IF(mn3.text is null,0,1)';
		$binds = [];
	}
	if ($md['sort'] == 'iscost') {
		$sort = 'ORDER BY IF(mn.number IS NULL,0,1)';
		$binds = [];
	}
	if ($md['sort'] == 'is') {

		$sort = 'ORDER BY 
			IF(mn3.text is null,1,0), 
			IF(mn.number IS NULL,1,0),
			IF(mn2.value_id = :nal1,0,1),
			IF(mn2.value_id = :nal2,0,1), 
			IF(mn2.value_id = :nal3,0,1), 
			IF(mn2.value_id = :nal4,0,1), 
			IF(mn2.value_id = :nal5,0,1), 
			mn.number
			';
		$binds = [':nal1' => $nal1, ':nal2' => $nal2, ':nal3' => $nal3, ':nal4' => $nal4, ':nal5' => $nal5];
	}
	if ($md['sort'] == 'art') {
		$md['reverse'] = !$md['reverse'];
		$sort = 'ORDER BY m.article_nick';
		$binds = [];
	}
	if ($md['sort'] == 'name') {
		$join[] = '
		LEFT JOIN showcase_iprops ipn on (ipn.model_id = m.model_id and ipn.item_num = i.item_num and ipn.prop_id = :name_id)';

		$md['reverse'] = !$md['reverse'];
		if ($md['reverse']) {
			$sort = 'ORDER BY IF(ipn.text is null,1,0), ipn.text';
		} else {
			//null всегда снизу внезависимости от сортировки
			$sort = 'ORDER BY IF(ipn.text is null,0,1), ipn.text';
		}
		$binds = [':name_id' => $name_id];
		$groupbinds = [':name_id' => $name_id];
	}


	if ($sort) {
		if ($md['reverse']) {
			$asc = "ASC";
		} else {
			$asc = "DESC";
		}
		$sort .= ' ' . $asc;
	}




	$no = implode(' ', $no);
	$start = ($page - 1) * $count;
	if ($count) $limit = 'limit ' . $start . ',' . $count;
	else $limit = '';

	$join = implode(' ', $join);
	$sql = '
		SELECT 
			SQL_CALC_FOUND_ROWS i.model_id, 
			min(i.item_num) as item_num,
			GROUP_CONCAT(distinct i.item_num) as items, 
			min(mn.number) as cost, 
			m.article_nick, pr.producer_nick, GROUP_CONCAT(distinct m.group_id) as groups from showcase_items i
		LEFT JOIN showcase_models m on i.model_id = m.model_id
		LEFT JOIN showcase_groups g on g.group_id = m.group_id
		LEFT JOIN showcase_groups p on g.parent_id = p.group_id
		LEFT JOIN showcase_producers pr on pr.producer_id = m.producer_id
		LEFT JOIN showcase_iprops mn on (mn.model_id = m.model_id and mn.item_num = i.item_num and mn.prop_id = :cost_id)
		LEFT JOIN showcase_iprops mn2 on (mn2.model_id = m.model_id and mn2.item_num = i.item_num and mn2.prop_id = :nalichie_id)
		LEFT JOIN showcase_iprops mn3 on (mn3.model_id = m.model_id and mn3.item_num = i.item_num and mn3.prop_id = :image_id)
		
		' . $join . '
		WHERE 1=1 ' . $grquery . ' ' . $prquery . ' ' . $no . '
		GROUP BY m.model_id
		' . $sort . '
		' . $limit . '
		';



	$binds += [':cost_id' => $cost_id, ':nalichie_id' => $nalichie_id, ':image_id' => $image_id];

	$models = Data::all($sql, $binds);

	$size = Data::col('SELECT FOUND_ROWS()');
	$ans['count'] = (int) $size;

	$limit = 500;

	$ans['showlist'] = $showlist ? $showlist : $limit < $count || (!empty($ans['group']['count'])) || (sizeof($ans['filters']) && $ans['count'] < $limit);

	if ($ans['showlist']) {
		foreach ($models as $k => $m) {

			/*
				Подготовили список items внутри найденой позиции
				Модель в результатах поиска будет выглядеть иначе. Кликаем и видим друое количество позиций.
			*/
			//$m['items'] = explode(',', $m['items']);
			//foreach ($m['items'] as $j => $v) if (!$v) unset($m['items'][$j]);

			$models[$k] = Showcase::getModelEasyById($m['model_id']);
			$models[$k]['showcase'] = array();
			$group = API::getGroupById($models[$k]['group_id']);
			$models[$k]['showcase']['props'] = $group['options']['props'];
		}
		$ans['list'] = $models;
	}



	$groupbinds += [':image_id' => $image_id];

	$groups = Data::fetchto('
		SELECT max(mn3.text) as img, g.icon, g.group, g.group_nick, g.group_id, g.parent_id, count(DISTINCT m.model_id) as `count` from showcase_items i
		LEFT JOIN showcase_models m on m.model_id = i.model_id
		LEFT JOIN showcase_groups g on g.group_id = m.group_id
		LEFT JOIN showcase_groups p on g.parent_id = p.group_id
		LEFT JOIN showcase_producers pr on pr.producer_id = m.producer_id
		LEFT JOIN showcase_iprops mn3 on (mn3.model_id = m.model_id and mn3.prop_id = :image_id)
		' . $join . '
		WHERE m.model_id = i.model_id ' . $grquery . ' ' . $prquery . ' ' . $no . '
		GROUP BY m.group_id
		', 'group_nick', $groupbinds);
	//Найти общего предка для всех групп
	//Пропустить 1 вложенную группу
	//Отсортировать группы по их order

	$nicks = [];
	foreach ($groups as $k => $g) {
		$parent = API::getGroupById($g['group_id']);


		$test = $parent;
		$path = [];
		do {
			$nicks[$test['group_nick']] = $test;
			$path[] = $test['group_nick'];
			$test = $test['parent'];
		} while ($test);
		
		$groups[$k]['path'] = array_reverse($path);
	}

	foreach ($groups as $k => $g) {
		$path = array_intersect($path, $g['path']);
	}
	$level = sizeof($path);
	$childs = [];
	foreach ($groups as $k => $g) {
		if (empty($g['path'][$level])) continue;
		$nick = $g['path'][$level];
		if (isset($childs[$nick])) continue;
		$childs[$nick] = [
			'group' => $nicks[$nick]['group'],
			'group_nick' => $nicks[$nick]['group_nick'],
			'icon' => $g['icon'],
			'img' => $g['img']
		];
	}
	
	if (sizeof($childs) == 1) $childs = [];
	$ans['childs'] = array_values($childs);
	
	// echo sizeof($groups);
	// print_r($groups);
	// exit;
	// $root = Data::getGroups();

	// Xlsx::runGroups($root, function & (&$group) use ($groups) {
	// 	$r = null;
	// 	$nick = $group['group_nick'];
	// 	if (!isset($groups[$nick])) return $r;
	// 	$group['found'] = $groups[$nick]['count'];
	// 	$group['img'] = $groups[$nick]['img'];
	// 	return $r;
	// });

	// Xlsx::runGroups($root, function & (&$group, $i, &$parent) {
	// 	$r = null;
	// 	if (!$parent) return $r;
	// 	if (isset($group['img']) && empty($parent['img'])) $parent['img'] = $group['img'];
	// 	if (!isset($group['found'])) {
	// 		array_splice($parent['childs'], $i, 1);
	// 		return $r;
	// 	}
	// 	if (!isset($parent['found'])) $parent['found'] = 0;
	// 	$parent['found'] += $group['found'];

	// 	if (!isset($group['childs'])) return $r;

	// 	/*usort($group['childs'], function ($a, $b){
	// 		if ($a['found'] > $b['found']) return -1;
	// 		if ($a['found'] < $b['found']) return 1;
	// 		return 0;
	// 	});*/
	// 	return $r;
	// }, true);

	// $conf = Showcase::$conf;
	// Xlsx::runGroups($root, function & (&$group) use ($conf) {
	// 	$r = null;
	// 	while (isset($group['childs']) && sizeof($group['childs']) == 1 && isset($group['childs'][0]['childs'])) {
	// 		$group['childs'] = array_values($group['childs'][0]['childs']);
	// 	}

	// 	$img = Rubrics::find(
	// 		$conf['icons'],
	// 		$group['group_nick'],
	// 		Data::$images
	// 	);
	// 	if ($img) $group['img'] = $img;
	// 	return $r;
	// });

	// if (!empty($root['childs'])) {
	// 	$ans['childs'] = array_values($root['childs']);
	// 	foreach ($ans['childs'] as $i => $ch) {
	// 		if (empty($ans['childs'][$i]['childs'])) continue;
	// 		foreach ($ans['childs'][$i]['childs'] as $ii => $cch) {
	// 			unset($ans['childs'][$i]['childs'][$ii]['childs']);
	// 		}
	// 	}
	// 	if (sizeof($ans['childs']) == 1 && isset($ans['group'])) {
	// 		if ($ans['childs'][0]['group_nick'] == $ans['group']['group_nick']) {
	// 			unset($ans['childs']);
	// 		}
	// 	}
	// }

	

	if ($ans['showlist'] && $count) {
		$pages = ceil($size / $count);
		if ($pages < $page) $page = $pages;
		$ans['numbers'] = Showcase::numbers($page, $pages, 6);
	}


	$src  =  Rubrics::find(Showcase::$conf['groups'], $ans['title']);
	if (!$src) $src  =  Rubrics::find(Showcase::$conf['groups'], $ans['name']);
	
	if ($src) {
		$ans['textinfo']  =  Rubrics::info($src); 
		$ans['text']  =  Load::loadTEXT('-doc/get.php?src='.$src);//Изменение текста не отражается как изменение каталога, должно быть вне кэша
	}

	return $this->ret();
});
$meta->addAction('columns', function () {
	$columns = Showcase::getOption(['columns']);
	return $columns;
});

// $meta->addAction('pos', function () {
// 	extract($this->gets(['producer_nick','article_nick','item_num']));
// 	$pos = Showcase::getModelEasy($producer_nick, $article_nick, $item_num);
// 	//breadcrumb собираем в шаблоне из group
// 	//more собираем в шаблоне из columns
// 	//items и itemrows??? items - сделать отдельным запросом и отдельным слоем
// 	//catkit
	
// 	$this->ans['pos'] = $pos;
// 	return $this->ret();
// });

$meta->addArgument('producer_nick',['encode','notempty']);
$meta->addArgument('article_nick',['encode','notempty']);
$meta->addArgument('item_num', ['int'], function ($item_num, $pname) {
	if ($item_num > 255) return $this->fail('meta.required', $pname);
});
$meta->addArgument('catkit');
$meta->addAction('posseo', function () {
	extract($this->gets(['producer_nick','article_nick']), EXTR_REFS);
	$ans = &$this->ans;
	
	$pos = Showcase::getModelEasy($producer_nick, $article_nick);
	
	if (!$pos) {
		return Ans::err($ans, 'Position not found');
	}
	
	$article = $pos['article'];
	$producer = $pos['producer'];

	$link = 'catalog/'.urlencode($pos['producer_nick']).'/'.urlencode($pos['article_nick']);

	if (!empty($pos['Наименование'])) {
		$ans['title'] = $pos['Наименование'];
		if (Showcase::$conf['cleanname']) { //Если в наименовании нет артикула, добавляем
			$ans['title'] .= ' '. $pos['producer'].' '.$pos['article'];
		}
	} else $ans['title'] = $pos['producer'].' '.$pos['article'];
	

	if (!empty($pos['Описание'])) $ans['description'] = preg_replace("/[\s\n\t]+/u"," ",strip_tags($pos['Описание']));
	
	//if (!empty($pos['Наименование'])) $ans['description'] = 'Купить '.$pos['Наименование'];
	//if (Showcase::$conf['cleanname']) { //Если в наименовании нет артикула, добавляем
	//	$ans['description'] .= $pos['producer'].' '.$pos['article'];
	//}

	$ans['canonical'] = Seojson::getSite().'/'.$link;
	
	if (isset($pos['images'][0])) {
		$ans['image_src'] = Seojson::getSite().'/-imager/?w=400&src='.$pos['images'][0];	
	}
	
	return $this->ret();
});
$meta->addAction('pos', function () {
	extract($this->gets(['producer_nick','article_nick','item_num','catkit']), EXTR_REFS);
	$md = Showcase::initMark($this->ans);
	$pos = Showcase::getModelWhithItems($producer_nick, $article_nick, $item_num);
	
	
	if (!$pos) {
		http_response_code(404);
		return $this->err();
	}
	
	
	
	$this->ans['pos'] = $pos;


	$this->ans['breadcrumbs'][] = array(
		'title' => Showcase::$conf['title'],
		'href' => '',
		'add' => ':group'
	);

	$group = API::getGroupById($pos['group_id']);
	$path = [];
	do {
		$path[] = array(
			'title' => $group['group'],
			'href' => $group['group_nick']
		);
		$group = $group['parent'];
	} while ($group['parent']);
	$path = array_reverse($path);
	foreach($path as $p) {
		$this->ans['breadcrumbs'][] = $p;
	}

	$this->ans['breadcrumbs'][] = array(
		'active' => true, 
		'title' => $pos['producer'].'&nbsp;'.$pos['article']
	);

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

$r = $meta->init([
	'name'=>'showcase',
	'base'=>'-showcase/api2/'
]);

return $r;
