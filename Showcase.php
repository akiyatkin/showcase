<?php

namespace akiyatkin\showcase;

use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\each\Each;
use infrajs\excel\Xlsx;
use infrajs\rubrics\Rubrics;
use infrajs\event\Event;
use infrajs\config\Config;
use infrajs\template\Template;
use akiyatkin\showcase\Data;
use infrajs\ans\Ans;
use infrajs\db\Db;
use infrajs\sequence\Sequence as Seq;
use infrajs\mark\Mark as Marker;

use akiyatkin\showcase\api2\API;
use infrajs\lang\LangAns;
use infrajs\cache\CacheOnce;
use infrajs\user\UserMail;

Event::$classes['Showcase-position'] = function ($pos) {
	if (empty($pos['producer_nick']) || empty($pos['article_nick'])) return '';
	$id = $pos['producer_nick'] . ' ' . $pos['article_nick'];
	$id .= ' ' . ($pos['item_num'] ? $pos['item_num'] : 1);
	if (!empty($pos['catkit'])) $id .= ' ' . $pos['catkit'];
	return $id;
};
/*Event::$classes['Showcase-group'] = function (&$group) {
	return $group['group_nick'];
};
Event::$classes['Showcase-producer'] = function (&$prod) {
	return $prod['producer_nick'];
};*/

class Showcase
{
	public static $conf;
	public static $list = array();
	public static $name = 'showcase';
	use CacheOnce;
	use LangAns;

	public static function add($name, $fndef, $fncheck)
	{
		Showcase::$list[$name] = array('fndef' => $fndef, 'fncheck' => $fncheck);
	}
	public static function getDefaultMark()
	{
		$mark = new Marker();
		$mark->len = 4;
		foreach (Showcase::$list as $name => $v) {
			$mark->add($name, $v['fndef'], $v['fncheck']);
		}
		return $mark;
	}
	public static function getProducers()
	{
		$list = Data::getProducers();
		foreach ($list as $k => $prod) {
			unset($list[$k]['catalog']);
		}
		return $list;
	}
	public static function initMark(&$ans = array())
	{
		$val = Ans::GET('val');

		$val = Path::toutf(strip_tags($val));
		$nick = Path::encode($val);
		$art = Ans::GET('art');

		if ($val && !$art) {
			if (!isset($_GET['m'])) $_GET['m'] = '';

			if ($val == 'actions') {
				$_GET['m'] .= ':more.' . Path::encode('Наличие') . '.' . Path::encode('Акция') . '=1:more.' . Path::encode('Наличие') . '.' . Path::encode('Распродажа') . '=1';
			} else if ($val == 'items') {
				$_GET['m'] .= ':sort=items';
			} else {
				$group = Db::col('SELECT group_id from showcase_groups where group_nick = :group_nick',[
					':group_nick' => $nick
				]);
				
				if ($group) {
					$_GET['m'] .= ':group::.' . $nick . '=1';
				} else {
					$producer = Showcase::getProducer($nick);
					if ($producer) {
						$_GET['m'] .= ':producer::.' . $nick . '=1';
					} else {
						$_GET['m'] .= ':search=' . $val;
					}
				}
			}
		}

		$m = Path::toutf(Seq::get($_GET, array('m')));

		$key = 'initMark:' . $m;
		if (isset(Showcase::$once[$key])) $ar = Showcase::$once[$key];
		else {
			$mark = Showcase::getDefaultMark();

			$mark->setVal($m);

			$md = $mark->getData();
			//$m = $mark->getVal();	

			$m = $mark->getOrigVal($m);

			$ar = Showcase::$once[$key] = array('md' => $md, 'm' => $m);
		}





		$ans['m'] = $ar['m'];
		$ans['md'] = $ar['md'];

		//$group = false;
		//foreach ($ans['md']['group'] as $group => $one) break;
		//if (!$group) $group = Showcase::$conf['title'];

		//$group = Showcase::getGroup($group);
		//if (!$group) $group = Data::getGroups();

		//unset($group['childs']);
		//unset($group['catalog']);

		//$ans['group'] = $group;
		//$ans['group_nick'] = 
		return $ar['md'];
	}
	public static function getMean($prop_nick, $value_nick)
	{
		$type = Data::checkType($prop_nick);
		$row = ['type' => $type, 'mean' => $value_nick];
		if ($type == 'value') {
			$r = Showcase::getValue($value_nick);
			if ($r) {
				$row += $r;
				$row['mean'] = $row['value'];
			}
		} else if ($type == 'number') {
			$row['mean'] = (float) $value_nick;
		} else if ($type == 'text') {
			$row['mean'] = $value_nick;
		}
		return $row;
	}
	public static $once = array();
	public static function getValue($value_nick)
	{
		$key = 'getValue:' . $value_nick;
		if (isset(Showcase::$once[$key])) return Showcase::$once[$key];
		return Showcase::$once[$key] = Data::fetch('SELECT value_id, value_nick, value FROM showcase_values WHERE value_nick = ?', [$value_nick]);
	}

	public static function getProducer($producer_nick)
	{
		$key = 'getProducer:' . $producer_nick;
		if (isset(Showcase::$once[$key])) return Showcase::$once[$key];
		return Showcase::$once[$key] = Data::col('SELECT producer_nick from showcase_producers where producer_nick = ?', [$producer_nick]);
	}
	public static function getGroups()
	{
		return Data::getGroups();
	}
	public static function getGroup($group_nick = false)
	{
		$group = Data::getGroups($group_nick);
		unset($group['catalog']);
		return $group;
	}
	public static function nestedGroups($group_id)
	{
		$groups = Data::all('SELECT group_id from showcase_groups where parent_id = ?', [$group_id]);
		foreach ($groups as $g) {
			$g = Showcase::nestedGroups($g['group_id']);
			$groups = array_merge($groups, $g);
		}
		return $groups;
	}
	public static function getGroupsIn($md = [])
	{
		$groups = [];
		if (!empty($md['group'])) foreach ($md['group'] as $group_nick => $one) {
			if ($group_nick == Path::encode(Showcase::$conf['title'])) return [];
			$group_id = Data::col(
				'SELECT group_id from showcase_groups where group_nick = :group_nick', 
				[
					':group_nick' => $group_nick
				]
			);
			$gs = Showcase::nestedGroups($group_id);
			$gs = array_column($gs, 'group_id');
			$gs[] = $group_id;
			$groups = array_merge($groups, $gs);
		}
		$groups = array_unique($groups);
		return $groups;
	}
	public static function search($md = false, &$ans = array(), $page = 1, $showlist = false)
	{
		if (empty($md['count'])) $count = 0;
		else $count = $md['count'];

		$cost_id = Data::initProp("Цена");
		$name_id = Data::initProp("Наименование");
		$image_id = Data::initProp("images");
		$nalichie_id = Data::initProp("Наличие");
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
			$prod = Data::fetch('SELECT producer_id, producer, producer_nick from showcase_producers where producer_nick = ?', [$prod]);
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
		$nal1 = Data::initValue("Акция");
		$nal2 = Data::initValue("Распродажа");
		$nal3 = Data::initValue("В наличии");
		$nal4 = Data::initValue("Есть в наличии");
		$nal5 = Data::initValue("Мало");

		$join = [];
		$no = [];

		if (!empty($md['more'])) foreach ($md['more'] as $prop_nick => $vals) {

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

			$prop = Data::fetch('SELECT * from showcase_props where prop_nick = ?', [$prop_nick]);
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


					/*if (empty($vals['minmax']) && !empty($vals['no'])) {
						$no[] = 'and p'.$un.'.number is null';
					}*/


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
				$m['items'] = explode(',', $m['items']);
				foreach ($m['items'] as $j => $v) if (!$v) unset($m['items'][$j]);

				$models[$k] = Showcase::getModel($m['producer_nick'], $m['article_nick'], $m['item_num'], false, $m['items']);
			}
			$ans['list'] = $models;
		}



		$groupbinds += [':image_id' => $image_id];

		$groups = Data::fetchto('
			SELECT max(mn3.text) as img, g.group, g.group_nick, g.group_id, g.parent_id, count(DISTINCT m.model_id) as `count` from showcase_items i
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

		$root = Data::getGroups();

		Xlsx::runGroups($root, function & (&$group) use ($groups) {
			$r = null;
			$nick = $group['group_nick'];
			if (!isset($groups[$nick])) return $r;
			$group['found'] = $groups[$nick]['count'];
			$group['img'] = $groups[$nick]['img'];
			return $r;
		});

		Xlsx::runGroups($root, function & (&$group, $i, &$parent) {
			$r = null;
			if (!$parent) return $r;
			if (isset($group['img']) && empty($parent['img'])) $parent['img'] = $group['img'];
			if (!isset($group['found'])) {
				array_splice($parent['childs'], $i, 1);
				return $r;
			}
			if (!isset($parent['found'])) $parent['found'] = 0;
			$parent['found'] += $group['found'];

			if (!isset($group['childs'])) return $r;

			/*usort($group['childs'], function ($a, $b){
				if ($a['found'] > $b['found']) return -1;
				if ($a['found'] < $b['found']) return 1;
				return 0;
			});*/
			return $r;
		}, true);

		$conf = Showcase::$conf;
		Xlsx::runGroups($root, function & (&$group) use ($conf) {
			$r = null;
			while (isset($group['childs']) && sizeof($group['childs']) == 1 && isset($group['childs'][0]['childs'])) {
				$group['childs'] = array_values($group['childs'][0]['childs']);
			}

			$img = Rubrics::find(
				$conf['icons'],
				$group['group_nick'],
				Data::$images
			);
			if ($img) $group['img'] = $img;
			return $r;
		});

		if (!empty($root['childs'])) {
			$ans['childs'] = array_values($root['childs']);
			foreach ($ans['childs'] as $i => $ch) {
				if (empty($ans['childs'][$i]['childs'])) continue;
				foreach ($ans['childs'][$i]['childs'] as $ii => $cch) {
					unset($ans['childs'][$i]['childs'][$ii]['childs']);
				}
			}
			if (sizeof($ans['childs']) == 1 && isset($ans['group'])) {
				if ($ans['childs'][0]['group_nick'] == $ans['group']['group_nick']) {
					unset($ans['childs']);
				}
			}
		}



		if ($ans['showlist'] && $count) {
			$pages = ceil($size / $count);
			if ($pages < $page) $page = $pages;


			$ans['numbers'] = Showcase::numbers($page, $pages, 6);
		}
		return $ans;
	}


	public static $columns = array(
		"model_id", "item_num", "producer", "items", "itemrows","itemmore",
		"group_nick", "group_id", "group","icon",
		"article", "producer_nick", "article_nick", 
		"Наименование", "Файл", "Иллюстрации", "Файлы", "Фото", "Цена", "Описание", 
		"Скрыть-фильтры-в-полном-описании", "Наличие", "Прайс"
	);
	public static function getOption($right = [], $def = null)
	{

		$key = 'getOption:';
		if (isset(Showcase::$once[$key])) $options = Showcase::$once[$key];
		else {
			$options = Data::getOptions();
			$props = array_keys($options['props']);
			$options['columns'] = array_merge(Data::$files, Showcase::$columns, $options['columns'], $props);

			$options['columns'] = array_map(function ($val) {
				return Path::encode($val);
			}, $options['columns']);
			$options['columns'] = array_unique($options['columns']);
			Showcase::$once[$key] = $options;
		}

		$res = Seq::get($options, $right);
		if (is_null($res)) return $def;
		return $res;
	}
	public static function getOptions()
	{
		$options = Data::getOptions();
		return $options;
	}
	public static function getModelShow($producer_nick, $article_nick, $item_nicknum = '', $catkit = false, $items = [])
	{
		$pos = Showcase::getModel($producer_nick, $article_nick, $item_nicknum, $catkit, $items);

		if (!$pos) return $pos;
		$pos['show'] = true; //Метка что даные для показа... На странице модели покаываем цену позиции! Одну цену. А в карточке товра вилку.
		if (isset($pos['texts'])) {
			foreach ($pos['texts'] as $i => $src) {
				$pos['texts'][$i]  =  Rubrics::article($src); //Изменение текста не отражается как изменение 
				//$pos['texts'][$i]  =  Load::loadTEXT('-doc/get.php?src='.$src);//Изменение текста не отражается как изменение 
			}
		}
		if (isset($pos['files'])) {
			foreach ($pos['files'] as $i => $path) {
				$fd = Load::pathinfo($path);
				$fd['size'] = round(FS::filesize($path) / 1000000, 2);
				if (!$fd['size']) $fd['size'] = '0.01';
				$pos['files'][$i] = $fd;
			}
		}
		//Event::tik('Showcase-position.onshow'); //Позиция вызывается в двух слоях по разным запросам - ошибка в этом
		$opt = Showcase::getOptions();
		$pos['showcase']['props'] = Data::initProps($opt, array_keys($opt['props']));

		Event::fire('Showcase-position.onshow', $pos);
		return $pos;
	}
	public static function setItem($data, $item_nick)
	{
		$item = $data['items'][$item_nick];
		foreach ($data['itemrows'] as $name) {
			unset($data[$name]);
			if (isset($data['more'])) unset($data['more'][$name]);
		}
		if (isset($item['more'])) {
			$data['more'] = $item['more'] + $data['more'];
			unset($item['more']);
		}
		$data = $item + $data;
		return $data;
	}
	public static function isInt($id)
	{
		if ($id === '') {
			return false;
		}
		if (!$id) {
			$id = 0;
		}
		$idi = (float) $id;
		$idi = (string) $idi; //12 = '12 asdf' а если и то и то строка '12'!='12 asdf'
		return $id == $idi;
	}
	public static function getModelById($model_id, $item_nicknum = '', $catkit = '', $myitems = []) {
		$sql = 'select m.article_nick, p.producer_nick from showcase_models m
			left join showcase_producers p on p.producer_id = m.producer_id
			where m.model_id = :model_id
		';
		$ar = Db::fetch($sql,['model_id'=> $model_id]);
		if (!$ar) return false;
		return Showcase::getModel($ar['producer_nick'], $ar['article_nick'], $item_nicknum, $catkit, $myitems);
	}
	public static function getCost($producer_nick, $article_nick, $item_num = 1) {
		
		$sql = 'SELECT ip.number from showcase_models m
			left join showcase_producers p on p.producer_id = m.producer_id
			left join showcase_items i on i.model_id = m.model_id
			left join showcase_iprops ip on (ip.item_num = i.item_num and ip.model_id = i.model_id)
			left join showcase_props pr on pr.prop_id = ip.prop_id
			WHERE  
			m.article_nick = :article_nick
			and p.producer_nick = :producer_nick
			and pr.prop_nick = :prop_nick
		';
		$prop_nick = Path::encode('Цена');
		
		$cost = Db::col($sql, [
			'article_nick'=> $article_nick,
			'prop_nick' => $prop_nick,
			'producer_nick'=> $producer_nick
		]);
		return (float) $cost;
	}
	public static function getModel($producer_nick, $article_nick, $item_nicknum = '', $catkit = '', $myitems = [])
	{
		return static::once('getModel', [$producer_nick, $article_nick, $item_nicknum, $catkit, $myitems], function ($producer_nick, $article_nick, $item_nicknum, $catkit, $myitems) {
			// $catkit - выбранная комплектация
			// $myitems - какие позиции будут внутри модели
			if ($item_nicknum && $myitems) $myitems[] = $item_nicknum;


			/*uasort($data['items'], function ($a, $b) {
				if ($a['item_num'] == $b['item_num']) {
					return 0;
				}
				return ($a['item_num'] < $b['item_num']) ? -1 : 1;
			});*/
			$data = Showcase::getFullModel($producer_nick, $article_nick);
			if (!$data) return false;
			if ($item_nicknum && $data['item_nick'] != $item_nicknum && $data['item_num'] != $item_nicknum) {
				//Первая позиция не подходим ищим в items

				if (empty($data['items'])) return false;

				$item_nick = false;
				if (Showcase::isInt($item_nicknum) && $item_nicknum <= sizeof($data['items'])) {
					$keys = array_keys($data['items']);
					$item_nick = $keys[--$item_nicknum];
					$data = Showcase::setItem($data, $item_nick);
				} else if (isset($data['items'][$item_nicknum])) {
					$data = Showcase::setItem($data, $item_nicknum);
				} else {
					return false;
				}
			}

			if (!empty($data['items']) && $myitems) {
				foreach ($data['items'] as $k => $item) {
					if (!in_array($item['item_num'], $myitems)) unset($data['items'][$k]);
				}
				if (sizeof($data['items']) == 0) return false;
				if (sizeof($data['items']) == 1) {
					unset($data['items']);
					unset($data['itemrows']);
					unset($data['itemmore']);
				}
			}


			if (!empty($data['items'])) {
				$min = false;
				$max = false;
				if (!empty($data['Цена'])) {
					$min = $data['Цена'];
					$max = $data['Цена'];
				}
				foreach ($data['items'] as $item) {
					if (empty($item['Цена'])) continue;
					if (!$min || $min > $item['Цена']) $min = $item['Цена'];
					if (!$max || $max < $item['Цена']) $max = $item['Цена'];
				}
				if ($min != $max) {
					$data['min'] = $min;
					$data['max'] = $max;
				}
			}

			//if ($catkit) $data['catkit'] = implode('&', $catkit); //Выбраная комплектация
			$data['catkit'] = $catkit; //Выбраная комплектация
			//Event::tik('Showcase-position.onsearch');

			Event::fire('Showcase-position.onsearch', $data); //Позиция для общего списка

			return $data;
		});
	}
	public static function getModelEasyById($model_id)
	{

		$sql = 'SELECT 
			m.model_id, m.article_nick, m.article, 
			p.producer_nick, p.logo, p.producer, 
			g.group_nick, g.group_id, g.group, g.icon
			FROM showcase_models m 
			left JOIN showcase_producers p on (p.producer_id = m.producer_id)
			left JOIN showcase_groups g on (g.group_id = m.group_id)
			where m.model_id = :model_id
			';
		$pos = Data::fetch($sql, [':model_id' => $model_id]);
		
		if (!$pos) return false;

		//надо определить itemrows
		
		$list = Data::all('SELECT 
			p.prop, p.prop_nick, 
			v.value, 
			ip.number, ip.text, ip.order as `order`, ip.item_num
			FROM showcase_iprops ip
			LEFT JOIN showcase_values v on v.value_id = ip.value_id
			LEFT JOIN showcase_props p on p.prop_id = ip.prop_id
			WHERE ip.model_id = :model_id and ip.item_num = :item_num
		', [
			':model_id' => $pos['model_id'],
			":item_num" => 1
		]);
		foreach ($list as $p => $prop) {
			$val = $prop['value'] ?? $prop['number'] ?? $prop['text'];
			$name = $prop['prop'];
			if (in_array($name, Data::$files)) {
				if (!isset($pos[$name])) $pos[$name] = [];
				$pos[$name][] = $val;
			} else {
				if (isset($pos[$name])) $pos[$name] .= ', '.$val;
				else $pos[$name] = $val;
			}	
		}
		$pos['item_num'] = "1";
		return $pos;
	}
	public static function getModelWhithItems($producer_nick, $article_nick, $choice_item_num = 1)
	{

		$sql = 'SELECT 
			m.model_id, m.group_id, m.article_nick, m.article, 
			p.producer_nick, p.logo, p.producer, 
			g.group_nick, g.group, g.icon
			FROM showcase_models m 
			INNER JOIN showcase_producers p on p.producer_id = m.producer_id
			INNER JOIN showcase_groups g on g.group_id = m.group_id
			WHERE m.article_nick = :article_nick
				and p.producer_nick = :producer_nick
			ORDER by m.model_id
		';
		$pos = Data::fetch($sql, [
			':article_nick' => $article_nick, 
			':producer_nick' => $producer_nick
		]);

		if (!$pos) return false;

		$list = Data::all('SELECT 
			p.prop, p.prop_nick, 
			v.value, 
			ip.number, ip.text, ip.order as `order`, ip.item_num
			FROM showcase_iprops ip
			LEFT JOIN showcase_values v on v.value_id = ip.value_id
			LEFT JOIN showcase_props p on p.prop_id = ip.prop_id
			WHERE ip.model_id = :model_id
		', [
			':model_id' => $pos['model_id']
		]);
		
		$items = [];
		$itemrows = [];
		foreach ($list as $p => $prop) {
			$val = $prop['value'] ?? $prop['number'] ?? $prop['text'];
			$name = $prop['prop'];
			$itemrows[$name] = $val;
			$item_num = $prop['item_num'];

			if (empty($items[$item_num])) $items[$item_num] = [];

			if (in_array($name, Data::$files)) {
				if (!isset($items[$item_num][$name])) $items[$item_num][$name] = [];
				$items[$item_num][$name][] = $val;
			} else {
				if (isset($items[$item_num][$name])) $items[$item_num][$name] .= ', '.$val;
				else $items[$item_num][$name] = $val;
			}	
		}
		foreach ($itemrows as $prop => $val) {
			$euqal = true;
			foreach($items as $item_num => $item) {
				if ($val !== $item[$prop]) {
					$euqal = false;
					break;
				}
			}
			if ($euqal) {
				$pos[$prop] = $val;
				foreach($items as $item_num => $item) {
					unset($items[$item_num][$prop]);
				}
			}
		}
		if (!isset($items[$choice_item_num])) $choice_item_num = 1;
		$pos = $pos + $items[$choice_item_num];
		$pos['item_num'] = $choice_item_num;
		$pos['items'] = $items;
		$pos['itemrows'] = array_keys($items[$choice_item_num]);

		$columns = Showcase::getOption()['columns'];

		$pos['itemmore'] = [];
		foreach($pos['itemrows'] as $name) {
			$nick = Path::encode($name);
			if (in_array($nick, $columns)) continue;
			$pos['itemmore'][] = $name;
		}
		
		$more = [];
		foreach ($pos as $i => $v) {
			$nick = Path::encode($i);
			if (in_array($nick, $columns)) continue;
			$more[$i] = $v;
			unset($pos[$i]);
		}
		$pos['more'] = $more;

		return $pos;
	}

	public static function getModelEasy($producer_nick, $article_nick, $item_num = 1)
	{

		$sql = 'SELECT 
			m.model_id, m.group_id, m.article_nick, m.article, 
			p.producer_nick, p.logo, p.producer, 
			i.item_num,
			g.group_nick, g.group, g.icon
			FROM showcase_models m 
			INNER JOIN showcase_items i on i.model_id = m.model_id
			INNER JOIN showcase_producers p on p.producer_id = m.producer_id
			INNER JOIN showcase_groups g on g.group_id = m.group_id
			WHERE m.article_nick = :article_nick
				and p.producer_nick = :producer_nick
				and i.item_num = :item_num
			ORDER by m.model_id
		';
		$pos = Data::fetch($sql, [
			':article_nick' => $article_nick, 
			':producer_nick' => $producer_nick, 
			':item_num' => $item_num
		]);

		if (!$pos) return false;

		
		$list = Data::all('SELECT 
			p.prop, p.prop_nick, 
			v.value, 
			ip.number, ip.text, ip.order as `order`, ip.item_num
			FROM showcase_iprops ip
			LEFT JOIN showcase_values v on v.value_id = ip.value_id
			LEFT JOIN showcase_props p on p.prop_id = ip.prop_id
			WHERE ip.model_id = :model_id and ip.item_num = :item_num
		', [
			':model_id' => $pos['model_id'], 
			':item_num' => $pos['item_num']
		]);
		
		foreach ($list as $p => $prop) {
			$val = $prop['value'] ?? $prop['number'] ?? $prop['text'];
			$name = $prop['prop'];
			if (in_array($name, Data::$files)) {
				if (!isset($pos[$name])) $pos[$name] = [];
				$pos[$name][] = $val;
			} else {
				if (isset($pos[$name])) $pos[$name] .= ', '.$val;
				else $pos[$name] = $val;
			}	
		}
		return $pos;
	}
	public static function getFullModel($producer_nick, $article_nick)
	{

		$sql = 'SELECT 
			m.model_id, m.article_nick, m.article, 
			p.producer_nick, p.logo, p.producer, 
			i.item_nick, i.item_num, i.item, 
			g.group_nick, g.group, g.icon
			FROM showcase_models m 
			LEFT JOIN showcase_items i on (i.model_id = m.model_id)
			INNER JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = :producer)
			INNER JOIN showcase_groups g on (g.group_id = m.group_id)
			where m.article_nick = :article
			order by m.model_id
			';
		$data = Data::fetchto($sql, 'item_num', [':article' => $article_nick, ':producer' => $producer_nick]);
		if (!$data) return false;

		//надо определить itemrows
		foreach ($data as $pos) break;
		$list = Data::all('SELECT 
			p.prop, p.prop_nick, 
			v.value, 
			ip.number, ip.text, ip.order as `order`, ip.item_num
			FROM showcase_iprops ip
			LEFT JOIN showcase_values v on v.value_id = ip.value_id
			LEFT JOIN showcase_props p on p.prop_id = ip.prop_id
			WHERE ip.model_id = ?
			order by ip.item_num
			', [$pos['model_id']]);

		//$calc[prop_nick][value][item_num] = true
		//Если у prop_nick есть value не указанный у какого-то item_num, prop идёт в itemrows

		$calc = [];
		$icount = sizeof($data);
		$items = [];
		foreach ($list as $p => $prop) {
			if (!is_null($prop['number'])) $list[$p]['val'] = (float)$prop['number'];
			else if (!is_null($prop['text'])) $list[$p]['val'] = $prop['text'];
			else if (!is_null($prop['value'])) $list[$p]['val'] = $prop['value'];
			else $list[$p]['val'] = '';

			if (!isset($calc[$list[$p]['prop']])) $calc[$list[$p]['prop']] = [];
			if (isset($calc[$list[$p]['prop']][$list[$p]['val']][$list[$p]['item_num']])) continue;
			$calc[$list[$p]['prop']][$list[$p]['val']][$list[$p]['item_num']] = $prop; //Дефолтным считается первое

		}
		$itemrows = [];
		$itemmore = [];
		foreach ($calc as $prop => $vals) {
			foreach ($vals as $item_num => $count) {
				if ($icount == sizeof($count)) continue;
				if (!Showcase::isColumn($prop)) $itemmore[] = $prop;
				$itemrows[] = $prop;
				break;
			}
		}

		$pos['list'] = [];
		foreach ($list as $p => $prop) {

			$item_num = $list[$p]['item_num'];
			if ($item_num == 1) $pos['list'][] = $prop;
			if (!in_array($prop['prop'], $itemrows)) continue;

			$item_nick = $data[$item_num]['item_nick'];

			if (empty($items[$item_nick])) $items[$item_nick] = [
				'item' => $data[$item_num]['item'],
				'item_num' => $item_num,
				'item_nick' => $item_nick,
				'list' => []
			];
			$items[$item_nick]['list'][] = $prop;
		}



		Showcase::makeMore($pos, $pos['list']);
		foreach ($items as $k => $item) {
			Showcase::makeMore($items[$k], $item['list']);
		}

		if ($itemrows) $pos['itemrows'] = $itemrows;
		if ($itemmore) $pos['itemmore'] = $itemmore;
		if ($items) $pos['items'] = $items;

		$g = Showcase::getGroup($pos['group_nick']);

		$pos += array_intersect_key($g, array_flip(['group_id', 'parent_id', 'parent_nick', 'parent', 'path', 'showcase']));

		return $pos;
	}
	/*public static function getModel2($producer_nick, $article_nick, $item_nick = '', $catkit = [], $myitems = []) {
		// $catkit - выбранная комплектация
		// $myitems - какие позиции будут внутри модели
		if ($item_nick && $myitems) $myitems[] = $item_nick;
		$data = Data::fetchto('SELECT 
			m.model_id, p.producer_nick, p.logo, g.icon,
			p.producer, m.article_nick,
			m.article, i.item_nick, i.item_num, i.item, g.group_nick, g.group
			FROM showcase_models m 
			left join showcase_items i on (i.model_id = m.model_id)
			INNER JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = :producer)
			INNER JOIN showcase_groups g on (g.group_id = m.group_id)
			where m.article_nick = :article
			order by m.model_id
			', 'item_nick', [':article'=>$article_nick,':producer'=>$producer_nick]);
		if (!$data) return false;

		if(isset($data[$item_nick])) {
			$data = $data[$item_nick];
		} else {
			foreach ($data as $data) break;
		}
		
		$item_num = $data['item_num'];
		$item_nick = $data['item_nick'];
		
		
		
		$list1 = Data::all('SELECT p.prop, p.prop_nick, v.value as val, min(smv.order) as `order`
			FROM showcase_iprops smv, showcase_values v, showcase_props p
			WHERE smv.value_id = v.value_id
			AND p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND smv.item_num = ?
			AND smv.value_id is not null
			group by p.prop_nick, v.value_nick
			',[$data['model_id'], $item_num]);

		
		
		$list2 = Data::all('SELECT p.prop, p.prop_nick, smv.number as val, min(smv.order) as `order`
			FROM showcase_iprops smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND smv.item_num = ?
			AND smv.number is not null
			group by p.prop_nick, val
		',[$data['model_id'], $item_num]);
		foreach ($list2 as $i => $row) {
			$list2[$i]['val'] = (float) $row['val'];
		}
		
		$list3 = Data::all('SELECT p.prop, p.prop_nick, smv.text as val, min(smv.order) as `order`
			FROM showcase_iprops smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND smv.item_num = ?
			and smv.text is not null
			group by p.prop_nick, val
			',[$data['model_id'], $item_num]);
		$list = array_merge($list1, $list2, $list3);
		
		usort($list, function($a, $b){
			if ($a['order'] > $b['order']) return 1;
			if ($a['order'] < $b['order']) return -1;
			return 1;//Фото специфицированная ддля позиции будет выше фото модели
		});


		
		Showcase::makeMore($data, $list);

		//if ($item_nick) {
		$its = Data::all('
				SELECT i.item, i.item_nick, ps.prop, ps.prop_nick, v.value as val from showcase_iprops mv
				left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
				left join showcase_props ps on ps.prop_id = mv.prop_id
				left join showcase_values v on v.value_id = mv.value_id
				where mv.model_id = :model_id and mv.value_id is not null
				UNION ALL
				SELECT i.item, i.item_nick, ps.prop, ps.prop_nick, mv.number as val from showcase_iprops mv
				left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
				left join showcase_props ps on ps.prop_id = mv.prop_id
				WHERE mv.model_id = :model_id and mv.number is not null
				UNION ALL
				SELECT i.item, i.item_nick, ps.prop, ps.prop_nick, mv.text as val from showcase_iprops mv
				left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
				left join showcase_props ps on ps.prop_id = mv.prop_id
				where mv.model_id = :model_id and mv.text is not null

				',[':model_id' => $data['model_id']]);
		
		
		
		$items = [];
		foreach ($its as $i) {
			$itemn = $i['item_nick'];
			if ($myitems && !in_array($itemn, $myitems)) continue;
			if (!isset($items[$itemn])) $items[$itemn] = ['item' => $i['item'], 'item_nick' => $itemn, 'list' => []];
			$items[$itemn]['list'][] = $i;
		}
		
		$icount = sizeof($items); 
		$calc = [];
		foreach ($items as $j => $item) {
			foreach ($item['list'] as $k => $prop) {
				$p = $prop['prop_nick'];
				$v = $prop['val'];

				if (!isset($calc[$p])) {
					$calc[$p] = $prop;
					$calc[$p]['val'] = '';
					$calc[$p]['vals'] = [];
				}
				if (!isset($calc[$p]['vals'][$v])) $calc[$p]['vals'][$v] = 0;
				$calc[$p]['vals'][$v]++;
				//Надо найти свойства, которые есть у всех item. 
				//Для этого каждой пары свойства должно быть icount и 
			}
		};
		
		foreach ($calc as $p => $pp) {
			foreach ($calc[$p]['vals'] as $v => $kp) {
				if ($calc[$p]['vals'][$v] == $icount) {
					unset($calc[$p]['vals'][$v]);
				}
			}
			if (!$calc[$p]['vals']) unset($calc[$p]);
		}
		
		
		foreach ($items as $j => $item) {
			$mycalc = $calc;
			foreach ($item['list'] as $k => $prop) {
				$p = $prop['prop_nick'];
				$v = $prop['val'];
				if (empty($mycalc[$p])) unset($items[$j]['list'][$k]);
				$mycalc[$p]['ready'] = true;
			}
			foreach ($mycalc as $p => $pp) {
				if (isset($mycalc[$p]['ready'])) continue;
				unset($mycalc[$p]['ready']);
				unset($mycalc[$p]['vals']);
				$items[$j]['list'][] = $mycalc[$p];
			}
		}
		

		if ($items) {
			foreach ($items as $k=>$item) {
				$list = $item['list'];
				unset($items[$k]['list']);
				Showcase::makeMore($items[$k], $list);
			}
			$data['items'] = array_values($items);
			if(sizeof($data['items']) == 1) unset($data['items']);
		}
		
		if (!empty($data['items'])) {
			$min = false;
			$max = false;
			if(!empty($data['Цена'])) {
				$min = $data['Цена'];
				$max = $data['Цена'];
			}
			foreach ($data['items'] as $item) {
				if (empty($item['Цена'])) continue;
				if (!$min || $min > $item['Цена']) $min = $item['Цена'];
				if (!$max || $max < $item['Цена']) $max = $item['Цена'];
			}
			if ($min != $max) {
				$data['min'] = $min;
				$data['max'] = $max;
			}
		}
		$g = Showcase::getGroup($data['group_nick']);
		$data += array_intersect_key($g, array_flip(['group_id','parent_id','parent_nick','parent','path']));
		
		
		if ($catkit) $data['catkit'] = implode('&', $catkit); //Выбраная комплектация

		Event::fire('Showcase-position.onsearch', $data); //Позиция для общего списка
		return $data;
	}*/
	public static function isColumn($prop)
	{
		$columns = Showcase::getOption(['columns']);
		$prop_nick = Path::encode($prop);
		return in_array($prop_nick, $columns) || in_array($prop, $columns);
	}
	public static function makeMore(&$data, $list)
	{
		$more = array();
		foreach ($list as $row) {
			$prop = $row['prop'];
			$prop_nick = $row['prop_nick'];
			if (Showcase::isColumn($prop)) {
				if (!isset($data[$prop])) $data[$prop] = [];

				$data[$prop][] = $row['val'];
			} else {
				if (!isset($more[$prop])) $more[$prop] = [];
				$more[$prop][] = $row['val'];
			}
		}

		unset($data['list']);
		//$files = Showcase::getOption(['files']);
		foreach ($data as $prop => $val) {
			if (
				//!in_array($prop, $files) && 
				is_array($val)
				&& !in_array($prop, Data::$files)
				&& !in_array($prop, ['more'])
			) {
				$data[$prop] = implode(', ', $val);
			}
		}
		//ksort($data);
		if ($more) {
			foreach ($more as $name => $val) {
				if (is_array($val)) {
					$more[$name] = implode(', ', $val);
				}
			}
			ksort($data);
			$data['more'] = $more;
		}
	}
	public static function numbers($page, $pages, $plen = 11)
	{
		//$plen=11;//Только нечётные и больше 6 - количество показываемых циферок
		/*
		$pages=10
		$plen=6

		(1)2345-10
		1(2)345-10
		12(3)45-10
		123(4)5-10
		1-4(5)6-10
		1-5(6)7-10
		1-6(7)8910
		1-67(8)910
		1-678(9)10
		1-6789(10)

		$lside=$plen/2+1=4//Последняя цифра после которой появляется переход слева
		$rside=$pages-$lside-1=6//Первая цифра после которой справа появляется переход
		$islspace=$page>$lside//нужна ли пустая вставка слева
		$isrspace=$page<$rside
		$nums=$plen/2-2;//Количество цифр показываемых сбоку от текущей когда есть $islspace далее текущая


		*/
		if ($pages <= $plen) {
			$ar = array_fill(0, $pages + 1, 1);
			$ar = array_keys($ar);
			array_shift($ar);
		} else {
			$plen = $plen - 1;
			$lside = floor($plen / 2) + 1; //Последняя цифра после которой появляется переход слева

			$rside = $pages - $lside - 1; //Первая цифра после которой справа появляется переход
			$islspace = $page >= $lside;
			$isrspace = $page < $rside + 2;


			$ar = array(1);
			if ($isrspace && !$islspace) {

				for ($i = 0; $i < $plen - 2; $i++) {
					$ar[] = $i + 2;
				}
				$ar[] = 0;
				$ar[] = $pages;
			} else if (!$isrspace && $islspace) {

				$ar[] = 0;
				for ($i = 0; $i < $plen - 1; $i++) {
					$ar[] = $pages - ceil($plen / 2) + $i;
				}
			} else if ($isrspace && $islspace) {

				$nums = $plen / 2 - 2; //Количество цифр показываемых сбоку от текущей когда есть $islspace далее текущая
				$ar[] = 0;
				for ($i = 0; $i < $nums * 2 + 2; $i++) {
					$ar[] = $page - ceil($plen / 2) + $i + 2;
				}
				$ar[] = 0;
				$ar[] = $pages;
			}
		}

		Each::exec($ar, function & (&$num) use ($page) {
			$n = $num;
			$num = array('num' => $n, 'title' => $n);
			if (!$num['num']) {
				$num['empty'] = true;
				$num['num'] = '';
				$num['title'] = '&nbsp;';
			}
			if ($n == $page) {
				$num['active'] = true;
			}
			$r = null;
			return $r;
		});
		if (sizeof($ar) < 2) {
			return false;
		}
		$prev = array('num' => $page - 1, 'title' => '&laquo;');
		if ($page <= 1) {
			$prev['empty'] = true;
		}

		array_unshift($ar, $prev);
		$next = array('num' => $page + 1, 'title' => '&raquo;');
		if ($page >= $pages) {
			$next['empty'] = true;
		}
		array_push($ar, $next);
		return $ar;
	}
	public static function makeBreadcrumb($md, &$ans, $page = 1)
	{
		/*$ans["breadcrumbs"]= [
	        [
	            "main" => true,
	            "title" => "Главная",
	            "nomark" => true
	        ],
	        [
	            "href" => "",
	            "title" => "Каталог",
	            "add" => "group:",
	            "active" => true
	        ]
	    ];*/
		$conf = Config::get('showcase');
		if (!$md['group'] && $md['producer'] && sizeof($md['producer'])  ==  1) { //ПРОИЗВОДИТЕЛЬ
			foreach ($md['producer'] as $producer_nick  =>  $v) break;

			//is!, descr!, text!, name!, breadcrumbs!
			$ans['is'] = 'producer';
			//$name = Showcase::getProducer($producer);
			$prod =  Data::fetch('SELECT producer, producer_nick from showcase_producers where producer_nick = ?', [$producer_nick]);
			if ($prod) {
				$name = $prod['producer'];
				$ans['name'] = $prod['producer'];
				$ans['title'] = $prod['producer_nick'];
			} else {
				$ans['name'] = 'Производитель не найден';
				$ans['title'] = $prod['producer_nick'];
			}



			$ans['breadcrumbs'][] = array('title' => $conf['title'], 'add' => 'producer:');

			//$ans['breadcrumbs'][] = array('href' => 'producers','title' => 'Производители');
			$ans['breadcrumbs'][] = array('href' => $ans['title'], 'title' => $ans['name']);
			$ans['breadcrumbs'][sizeof($ans['breadcrumbs']) - 1]['active']  =  true;
		} else if (!$md['group'] && $md['search']) {
			$ans['is'] = 'search';
			$ans['name'] = $md['search'];
			$ans['title'] = strip_tags($md['search']);
			$ans['breadcrumbs'][] = array('title' => $conf['title'], 'add' => 'search:');
			//$ans['breadcrumbs'][] = array('href' => 'find','title' => 'Поиск');
			$ans['breadcrumbs'][] = array('title' => $ans['name']);
			$ans['breadcrumbs'][sizeof($ans['breadcrumbs']) - 1]['active']  =  true;
		} else {
			//is!, descr!, text!, name!, breadcrumbs!, title
			//if ($md['group']) foreach ($md['group'] as $group  =>  $v) break;
			//else $group = false;

			$group_nick = Path::encode(Showcase::$conf['title']);
			foreach ($md['group'] ?? [] as $group_nick => $one) break;
			$group_id = Db::col('SELECT group_id from showcase_groups where group_nick = :group_nick', [
				':group_nick' => $group_nick
			]);
			$group = API::getGroupById($group_id);
			
			$ans['is'] = 'group';
			$ans['breadcrumbs'][] = array('href' => '', 'title' => $conf['title'], 'add' => 'group:');

			$path = [];
			$parent = $group;
			while ($parent['parent']) {
				$path[] = array('href' => $parent['group_nick'], 'title' => $parent['group']);
				$parent = $parent['parent'];
			}

			$ans['breadcrumbs'] = array_merge($ans['breadcrumbs'], array_reverse($path));

			if (sizeof($ans['breadcrumbs']) == 1) {
				array_unshift($ans['breadcrumbs'], array('main' => true, "title" => "Главная", "nomark" => true));
			}

			$ans['name'] = $group['group']; //имя группы длинное
			$ans['title'] = $group['group_nick'];


			//$ans['descr']  =  isset($group['descr']['Описание группы']) ? $group['descr']['Описание группы'] : '';




			if (!isset($group['path'])) {
				if (sizeof($ans['filters'])) { //Если есть выбранные фильтры, ссылка на каталог сбрасывает выбор
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs']) - 1]['href'] = '';
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs']) - 1]['add'] = false;
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs']) - 1]['nomark'] = true;
				} else {
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs']) - 1]['active'] =  true;
				}
				//$ans['breadcrumbs'][] = array('href' => 'producers','title' => 'Производители');
			} else {
				$ans['breadcrumbs'][sizeof($ans['breadcrumbs']) - 1]['active'] =  true;
			}
		}
	}
}
