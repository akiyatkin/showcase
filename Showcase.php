<?php
namespace akiyatkin\showcase;
use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\each\Each;
use infrajs\once\Once;
use infrajs\excel\Xlsx;
use infrajs\rubrics\Rubrics;
use infrajs\event\Event;
use infrajs\db\Db;
use infrajs\config\Config;
use infrajs\template\Template;
use akiyatkin\showcase\Data;
use infrajs\ans\Ans;
use infrajs\sequence\Sequence as Seq;
use infrajs\mark\Mark as Marker;

Event::$classes['Showcase-position'] = function ($pos) {
	$id = $pos['producer_nick'].' '.$pos['article_nick'];
	if (!empty($pos['item_nick'])) $id .= ' '.$pos['item_nick'];
	if (!empty($pos['catkit'])) $id .= ' '.$pos['catkit'];
	return $id;
};
/*Event::$classes['Showcase-group'] = function (&$group) {
	return $group['group_nick'];
};
Event::$classes['Showcase-producer'] = function (&$prod) {
	return $prod['producer_nick'];
};*/

class Showcase {
	public static $conf;
	public static $list = array();
	public static function add($name, $fndef, $fncheck)
	{
		Showcase::$list[$name] = array('fndef' => $fndef, 'fncheck' => $fncheck);
	}
	public static function getDefaultMark() {
		$mark = new Marker();
		$mark->len = 4;
		foreach (Showcase::$list as $name => $v) {
			$mark->add($name, $v['fndef'], $v['fncheck']);
		}
		return $mark;
	}
	public static function getProducers() {
		$list = Data::getProducers();
		foreach($list as $k => $prod) {
			unset($list[$k]['catalog']);
		}
		return $list;
	}
	public static function initMark(&$ans = array())
	{
		$val = Ans::GET('val');
		$val = Path::encode(Path::toutf(strip_tags($val)));
		$art = Ans::GET('art');
		
		if ($val && !$art) {
			if (!isset($_GET['m'])) $_GET['m'] = '';

			if ($val == 'actions') {
				$_GET['m'].=':more.Наличие-на-складе.Акция=1:more.Наличие-на-складе.Распродажа=1';
			} else if ($val == 'items') {
				$_GET['m'].=':sort=items';
			} else {
				$group = Showcase::getGroup($val);
				if ($group) {
					$_GET['m'].=':group::.'.$group['group_nick'].'=1';

				} else {
					$producer = Showcase::getProducer($val);
					if ($producer) {
						$_GET['m'].=':producer::.'.$val.'=1';
					} else {
						$_GET['m'].=':search='.$val;
					}
				}	
			}
		}
		
		$m = Path::toutf(Seq::get($_GET, array('m')));
		$ar = Once::func( function ($m = '') {
			$mark = Showcase::getDefaultMark();
			$mark->setVal($m);

			$md = $mark->getData();
			//$m = $mark->getVal();	
			
			$m = $mark->getOrigVal($m);
			
			
			return array('md' => $md, 'm' => $m);
		}, array($m));
		$ans['m'] = $ar['m'];
		$ans['md'] = $ar['md'];

		$group = false;
		foreach ($ans['md']['group'] as $group => $one) break;
		if (!$group) $group = Showcase::$conf['title'];
		$group = Showcase::getGroup($group);
		if (!$group) $group = Showcase::getGroup();
		unset($group['childs']);
		$ans['group'] = $group;

		return $ar['md'];
	}
	public static function getMean($prop_nick, $value_nick) {
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
	public static function getValue($value_nick) {
		return Once::func(function ($value_nick) {
			return Data::fetch('SELECT value_id, value_nick, value FROM showcase_values WHERE value_nick = ?', [$value_nick]);	
		},[$value_nick]);
	}
	
	public static function getProducer($producer_nick) {
		return Once::func(function ($producer_nick){
			return Data::col('SELECT producer_nick from showcase_producers where producer_nick = ?',[$producer_nick]);
		}, [$producer_nick]);
	}
	public static function getGroup($group_nick = false) {
		$group = Data::getGroups($group_nick);
		unset($group['catalog']);
		return $group;
	}
	public static function nestedGroups($group_id) {
		$groups = Data::all('SELECT group_id from showcase_groups where parent_id = ?',[$group_id]);
		foreach ($groups as $g) {
			$g = Showcase::nestedGroups($g['group_id']);
			$groups = array_merge($groups, $g);
		}
		return $groups;
	}
	public static function getGroupsIn($md = []) {
		
		$groups = [];
		if (!empty($md['group'])) foreach ($md['group'] as $group => $one) {
			$group_id = Data::col('SELECT group_id from showcase_groups where group_nick = ?',[$group]);
			if ($group_id == 1) {
				$groups = [];
			} else if ($group_id) {
				$gs = Showcase::nestedGroups($group_id);
				$gs = array_column($gs,'group_id');
				$gs[] = $group_id;
				$groups = array_merge($groups, $gs);
			}
		}
		if ($groups) { //Если есть группа надо достать все вложенные группы
			$groups = array_unique($groups);
		}
		return $groups;
		
	}
	public static function search($md = false, &$ans = array(), $page = 1) {
		if (empty($md['count'])) $count = 0;
		else $count = $md['count'];

		$cost_id = Data::initProp("Цена");
		$image_id = Data::initProp("images");
		$nalichie_id = Data::initProp("Наличие на складе");
		$ans['filters'] = [];
		$grquery = '';

		$groups = Showcase::getGroupsIn($md);
		if ($groups) {
			$grquery = implode(',', $groups);
			$grquery = 'and m.group_id in ('.$grquery.')';
		}
		$prquery = '';
		$prods = [];
		if(!empty($md['producer'])) foreach ($md['producer'] as $prod => $one) {
			$prod = Data::fetch('SELECT producer_id, producer, producer_nick from showcase_producers where producer_nick = ?',[$prod]);
			if ($prod) $prods[] = $prod;
		}
		if ($prods) { //Если есть группа надо достать все вложенные группы
			$prods = array_unique($prods);
			$ans['filters'][] = array( 
				'name' => 'producer',
				'value' => implode(', ', array_column($prods,'producer')),
				'title' => 'Производитель'
			);

			$prods = array_column($prods,'producer_id');
			$prquery = implode(',', $prods);
			$prquery = 'and m.producer_id in ('.$prquery.')';
		}
		$nal1 = Data::initValue("Акция");
		$nal2 = Data::initValue("Распродажа");
		$nal3 = Data::initValue("В наличии");

		$join = [];
		$no = [];
		
		if(!empty($md['more'])) foreach ($md['more'] as $prop_nick => $vals) {
			
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
							$titles[] = 'От '.$fncost($r[0]).' до '.$fncost($r[1]). ' руб.';	
						} else {
							$titles[] = 'От '.$r[0].' до '.$r[1];
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

			$prop = Data::fetch('SELECT * from showcase_props where prop_nick = ?',[$prop_nick]);
			if (!$prop) {
				$ans['filters'][] = array( 
					'name' => 'more.'.$prop_nick,
					'value' => $titles,
					'title' => 'Нет свойства '.$prop_nick 
				);
				$no[] = 'and 1=0';
				continue;
			}
			$prop_id = $prop['prop_id'];
			$ans['filters'][] = array( 
				'name' => 'more.'.$prop_nick,
				'value' => $titles,
				'title' => $prop['prop']
			);
			$type = Data::checkType($prop_nick);

			$un = $prop_id;
			if ($type == 'value') {
				if (isset($vals['no'])) {
					unset($vals['no']);
					$join[] = 'LEFT JOIN showcase_mvalues p'.$un.' on (p'.$un.'.model_id = m.model_id and p'.$un.'.prop_id = '.$prop_id.')';
					if ($vals) {
						$joinp = [];
						foreach ($vals as $val => $one) {
							$value_id = Data::initValue($val);
							$joinp[] = 'p'.$un.'.value_id = '.$value_id;
						}
						$vals = [];
						$joinp = implode(' OR ', $joinp);
						$no[] = 'and (p'.$un.'.value_id is null OR ('.$joinp.'))';
					} else {
						$no[] = 'and p'.$un.'.value_id is null';
					}
				} else if (isset($vals['yes'])) {
					unset($vals['yes']);
					$join[] = 'INNER JOIN showcase_mvalues p'.$un.' on (p'.$un.'.model_id = m.model_id and p'.$un.'.prop_id = '.$prop_id.')';
				}
			} else if ($type == 'text') {
				if (!empty($vals['no'])) {
					
					$join[] = 'LEFT JOIN showcase_mtexts p'.$un.' on (p'.$un.'.model_id = m.model_id and p'.$un.'.prop_id = '.$prop_id.')';

					
					$no[] = 'and (p'.$un.'.text is null)';
					//Только no
					unset($vals['no']);
					
					
					if ($vals) {
						$joinp = [];
						foreach ($vals as $val => $one) {
							$joinp[] = 'p'.$un.'.text = '.$val;
						}
						$vals = [];
						$joinp = implode(' OR ', $joinp);
						$no[] = 'and (p'.$un.'.text is null OR ('.$joinp.'))';
					}
					

				} else if (isset($vals['yes'])) {
					unset($vals['yes']);
					$join[] = 'INNER JOIN showcase_mtexts p'.$un.' on (p'.$un.'.model_id = m.model_id and p'.$un.'.prop_id = '.$prop_id.')';
				}
			} else if ($type == 'number') {
				if (!empty($vals['no']) || !empty($vals['minmax'])) {
					
					$join[] = 'LEFT JOIN showcase_mnumbers p'.$un.' on (p'.$un.'.model_id = m.model_id and p'.$un.'.prop_id = '.$prop_id.')';

					/*if (empty($vals['minmax']) && !empty($vals['no'])) {
						$no[] = 'and p'.$un.'.number is null';
					}*/


					$nn = !empty($vals['no']);
					$mm = !empty($vals['minmax']);
					//unset($vals['no']);
					if ($mm) {
						$r = explode('/',$vals['minmax']);
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
									$joinp[] = 'p'.$un.'.number = '.$val;
								}

								$vals = [];
								$joinp = implode(' OR ', $joinp);
								$no[] = 'and (p'.$un.'.number is null OR (p'.$un.'.number >= '.$min.' AND p'.$un.'.number <= '.$max.') OR ('.$joinp.'))';
							} else {
								$no[] = 'and (p'.$un.'.number is null OR (p'.$un.'.number >= '.$min.' AND p'.$un.'.number <= '.$max.'))';
							}
						} else {
							$no[] = 'and (p'.$un.'.number >= '.$min.' AND p'.$un.'.number <= '.$max.')';
						}
						
					} else {
						$no[] = 'and (p'.$un.'.number is null)';
						//Только no
						unset($vals['no']);
					}
							
					if ($vals) {
						$joinp = [];
						foreach ($vals as $val => $one) {
							$joinp[] = 'p'.$un.'.number = '.$val;
						}
						$vals = [];
						$joinp = implode(' OR ', $joinp);
						$no[] = 'and (p'.$un.'.number is null OR ('.$joinp.'))';
					}
					

				} else if (isset($vals['yes'])) {
					unset($vals['yes']);
					$join[] = 'INNER JOIN showcase_mnumbers p'.$un.' on (p'.$un.'.model_id = m.model_id and p'.$un.'.prop_id = '.$prop_id.')';
				}
			}
			//echo '<pre>';
			//print_r($join);
			//print_r($no);
			
			if ($vals) {
				$un = $prop_id.'v';
				$joinp = [];
				if ($type == 'value') {
					foreach ($vals as $val => $one) {
						$value_id = Data::initValue($val);
						$joinp[] = 'p'.$un.'.value_id = '.$value_id;
					}
				} else if ($type == 'number') {
					foreach ($vals as $val => $one) {
						$number = (float) $val;
						$joinp[] = 'p'.$un.'.number = '.$number;
					}
				} else if ($type == 'text') {
				}
				$joinp = implode(' OR ', $joinp);
				$join[] = 'INNER JOIN showcase_m'.$type.'s p'.$un.' on (p'.$un.'.model_id = i.model_id and (p'.$un.'.item_num = i.item_num or p'.$un.'.item_num = 0) and p'.$un.'.prop_id = '.$prop_id.' and ('.$joinp.'))';
			}
		}

		if (!empty($md['search'])) {
			$search = Path::encode($md['search']);
			$v = preg_split("/[\s\-]+/", mb_strtolower($search));
			$str = '';
			foreach ($v as $i => $s) {
				$v[$i] = preg_replace("/ы$/","",$s);
				$s = $v[$i];
				$str .= 'and (m.model_id in (SELECT smv'.$i.'.model_id from showcase_values sv'.$i.'
					inner join showcase_mvalues smv'.$i.' on smv'.$i.'.value_id = sv'.$i.'.value_id
					where sv'.$i.'.value_nick like "%'.$s.'%") 

					OR m.model_id in (SELECT svt'.$i.'.model_id from showcase_mtexts svt'.$i.'
					where svt'.$i.'.text like "%'.$s.'%") 

					OR m.model_id in (SELECT svn'.$i.'.model_id from showcase_mnumbers svn'.$i.'
					where svn'.$i.'.number like "%'.$s.'%") 

					OR a.article_nick LIKE "%'.$s.'%" 
					OR g.group_nick LIKE "%'.$s.'%" 
					OR p.group_nick LIKE "%'.$s.'%" 
					OR m.article_id LIKE "%'.$s.'%" 
					OR m.model_id LIKE "%'.$s.'%" 
					OR pr.producer_nick LIKE "%'.$s.'%"
				)';
			}
			$ans['filters'][] = array(
				'title' => 'Поиск',
				'name' => 'search',
				'value' => Path::encode($md['search'], true)
			);
			$no[] = $str;
		}


		
		$join = implode(' ', $join);
		
		$no = implode(' ', $no);	
		$start = ($page-1)*$count;

		if (!empty($md['sort']) && $md['sort'] == 'items') {
			$sort = 'IF(i.item_nick = "",1,0),';
		} else {
			$sort = '';
		}

		if ($md['reverse']) {
			$asc = "ASC";
		} else {
			$asc = "DESC";
		}
		

		if ($count) $limit = 'limit '.$start.','.$count;
		else $limit = '';
		$sql = '
			SELECT SQL_CALC_FOUND_ROWS max(i.item_nick) as item_nick, mn.number, a.article_nick, pr.producer_nick, GROUP_CONCAT(distinct m.group_id) from showcase_items i
			LEFT JOIN showcase_models m on i.model_id = m.model_id
			LEFT JOIN showcase_groups g on g.group_id = m.group_id
			LEFT JOIN showcase_groups p on g.parent_id = p.group_id
			LEFT JOIN showcase_producers pr on pr.producer_id = m.producer_id
			LEFT JOIN showcase_articles a on a.article_id = m.article_id
			LEFT JOIN showcase_mnumbers mn on (mn.model_id = m.model_id and (mn.item_num = i.item_num or mn.item_num = 0) and mn.prop_id = :cost_id)
			LEFT JOIN showcase_mvalues mn2 on (mn2.model_id = m.model_id and (mn2.item_num = i.item_num or mn2.item_num = 0) and mn2.prop_id = :nalichie_id)
			LEFT JOIN showcase_mtexts mn3 on (mn3.model_id = m.model_id and (mn3.item_num = i.item_num or mn3.item_num = 0) and mn3.prop_id = :image_id)
			'.$join.'
			WHERE 1=1 '.$grquery.' '.$prquery.' '.$no.'
			GROUP BY pr.producer_id, a.article_id
			ORDER BY 
			'.$sort.'
			IF(mn3.text is null,1,0),
			IF(mn2.value_id = :nal1,0,1),
			IF(mn2.value_id = :nal2,0,1), 
			IF(mn2.value_id = :nal3,0,1), 
			IF(mn.number IS NULL,1,0), 
			mn.number '.$asc.'
			'.$limit.'
			';
		//echo '<pre>';
		//echo $sql;
		//print_r($md);
		
		
		$models = Data::all($sql, [':cost_id' => $cost_id, ':nalichie_id' => $nalichie_id, ':image_id' => $image_id, 
			':nal1' => $nal1, ':nal2' => $nal2, ':nal3' => $nal3]
		);
		
		$size = Data::col('SELECT FOUND_ROWS()');
		foreach ($models as $k=>$m) {
			$models[$k] = Showcase::getModel($m['producer_nick'], $m['article_nick'], $m['item_nick']);
		}
		$ans['list'] = $models;


		$groups = Data::fetchto('
			SELECT max(mn3.text) as img, g.group, g.group_nick, g.group_id, g.parent_id, count(DISTINCT m.model_id) as `count` from showcase_items i
			LEFT JOIN showcase_models m on m.model_id = i.model_id
			LEFT JOIN showcase_groups g on g.group_id = m.group_id
			LEFT JOIN showcase_groups p on g.parent_id = p.group_id
			LEFT JOIN showcase_producers pr on pr.producer_id = m.producer_id
			LEFT JOIN showcase_articles a on a.article_id = m.article_id
			LEFT JOIN showcase_mtexts mn3 on (mn3.model_id = m.model_id and mn3.prop_id = :image_id)
			'.$join.'
			WHERE m.model_id = i.model_id '.$grquery.' '.$prquery.' '.$no.'
			GROUP BY m.group_id
			','group_nick',[':image_id' => $image_id]);
		//Найти общего предка для всех групп
		//Пропустить 1 вложенную группу
		//Отсортировать группы по их order
		
		$root = Data::getGroups();
		
		Xlsx::runGroups($root, function &(&$group) use ($groups){
			$r = null;
			$nick = $group['group_nick'];
			if (!isset($groups[$nick])) return $r;
			$group['found'] = $groups[$nick]['count'];
			$group['img'] = $groups[$nick]['img'];
			return $r;
		});

		Xlsx::runGroups ($root, function &(&$group, $i, &$parent) {
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
		Xlsx::runGroups ($root, function &(&$group, $conf) {
			$r = null;
			while (isset($group['childs']) && sizeof($group['childs']) == 1 && isset($group['childs'][0]['childs'])) {
				$group['childs'] = array_values($group['childs'][0]['childs']);
			}
			
			$img = Rubrics::find($conf['icons'], $group['group_nick'], Data::$images);
			if ($img) $group['img'] = $img;
			return $r;
		});
		
		$ans['childs'] = array_values($root['childs']);
		
		
		foreach ($ans['childs'] as $i => $ch) {
			if (empty($ans['childs'][$i]['childs'])) continue;
			foreach($ans['childs'][$i]['childs'] as $ii => $cch) {
				unset($ans['childs'][$i]['childs'][$ii]['childs']);
			}
		}
		if (sizeof($ans['childs']) == 1 && isset($ans['group'])) {
			if ($ans['childs'][0]['group_nick'] == $ans['group']['group_nick']) {
				unset($ans['childs']);
			}
		}
		if ($count) {
			$pages = ceil($size / $count);
			if ($pages < $page) $page = $pages;
			$ans['count'] = (int) $size;

			$ans['numbers'] = Showcase::numbers($page, $pages, 7);
		}
		return $ans;
	}


	public static $columns = array("producer","article","producer_nick","article_nick", "Наименование","Файл","Иллюстрации","Файлы","Фото","Цена","Описание","Скрыть-фильтры-в-полном-описании","Наличие-на-складе","Прайс");
	public static function getOption($right = [], $def = null) {
		$options = Once::func( function (){
			$options = Data::getOptions();
			$options['columns'] = array_merge(Data::$files, Showcase::$columns, $options['columns']);
			$options['columns'] = array_map( function ($val) {
				return Path::encode($val);
			}, $options['columns']);
			return $options;
		});
		$res = Seq::get($options, $right);
		if (is_null($res)) return $def;
		return $res;
	}
	public static function getOptions(){
		$options = Data::getOptions();
		return $options;
	}
	
	public static function getModelShow($producer_nick, $article_nick, $item_nick = '', $catkit = []) {
		$pos = Showcase::getModel($producer_nick, $article_nick, $item_nick, $catkit);
		if (!$pos) return $pos;
		if (isset($pos['texts'])) {
			foreach ($pos['texts'] as $i => $src) {
				$pos['texts'][$i]  =  Rubrics::article($src);//Изменение текста не отражается как изменение 
				//$pos['texts'][$i]  =  Load::loadTEXT('-doc/get.php?src='.$src);//Изменение текста не отражается как изменение 
			}
		}
		if (isset($pos['files'])) {
			foreach ($pos['files'] as $i => $path) {
				$fd = Load::pathinfo($path);
				$fd['size'] = round(FS::filesize($path)/1000000, 2);
				if (!$fd['size']) $fd['size'] = '0.01';
				$pos['files'][$i] = $fd;
			} 
		}
		//Event::tik('Showcase-position.onshow'); //Позиция вызывается в двух слоях по разным запросам - ошибка в этом
		Event::fire('Showcase-position.onshow', $pos);
		return $pos;
	}
	public static function getModel($producer_nick, $article_nick, $item_nick = '', $catkit = []) {
		$data = Data::fetch('SELECT 
			m.model_id, p.producer_nick, p.logo, g.icon,
			p.producer, a.article_nick, 
			a.article, g.group_nick, g.group
			FROM showcase_models m
			INNER JOIN showcase_articles a on (a.article_id = m.article_id and a.article_nick = :article)
			INNER JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = :producer)
			INNER JOIN showcase_groups g on (g.group_id = m.group_id)
			order by m.article_id
			', [':article'=>$article_nick,':producer'=>$producer_nick]);
		if (!$data) return false;
		//print_r($data);
		$item = Data::fetch('SELECT item_num, item, item_nick from showcase_items where model_id = ? and item_nick = ?',[$data['model_id'], $item_nick]);
		if ($item === false) return false;
		$item_num = $item['item_num'];
		//exit;
		
		$list1 = Data::all('SELECT p.prop, p.prop_nick, v.value as val, min(smv.order) as `order`
			FROM showcase_mvalues smv, showcase_values v, showcase_props p
			WHERE smv.value_id = v.value_id
			AND p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND (smv.item_num = 0 or smv.item_num = ?)
			group by p.prop_nick, v.value_nick
			',[$data['model_id'], $item_num]);
		
		$list2 = Data::all('SELECT p.prop, p.prop_nick, smv.number as val, min(smv.order) as `order`
			FROM showcase_mnumbers smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND (smv.item_num = 0 or smv.item_num = ?)
			group by p.prop_nick, val
		',[$data['model_id'], $item_num]);
		foreach ($list2 as $i => $row) {
			$list2[$i]['val'] = (float) $row['val'];
		}
		
		$list3 = Data::all('SELECT p.prop, p.prop_nick, smv.text as val, min(smv.order) as `order`
			FROM showcase_mtexts smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND (smv.item_num = 0 or smv.item_num = ?)
			group by p.prop_nick, val
			',[$data['model_id'], $item_num]);
		$list = array_merge($list1, $list2, $list3);

		usort($list, function($a, $b){
			if ($a['order'] > $b['order']) return 1;
			if ($a['order'] < $b['order']) return -1;
			return 1;//Фото специфицированная ддля позиции будет выше фото модели
		});

		
		Showcase::makeMore($data, $list);
		$data += $item;
		
		//if ($item_nick) {
		$its = Data::all('
				SELECT i.item, i.item_nick, ps.prop, ps.prop_nick, v.value as val from showcase_mvalues mv
				left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
				left join showcase_props ps on ps.prop_id = mv.prop_id
				left join showcase_values v on v.value_id = mv.value_id
				where mv.model_id = :model_id and mv.item_num > 0 
				UNION ALL
				SELECT i.item, i.item_nick, ps.prop, ps.prop_nick, mv.number as val from showcase_mnumbers mv
				left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
				left join showcase_props ps on ps.prop_id = mv.prop_id
				WHERE mv.model_id = :model_id and mv.item_num > 0 
				UNION ALL
				SELECT i.item, i.item_nick, ps.prop, ps.prop_nick, mv.text as val from showcase_mtexts mv
				left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
				left join showcase_props ps on ps.prop_id = mv.prop_id
				where mv.model_id = :model_id and mv.item_num > 0 

				',[':model_id' => $data['model_id']]);
		
		$items = [];
		foreach ($its as $i) {
			$itemn = $i['item_nick'];
			if (!isset($items[$itemn])) $items[$itemn] = ['item'=>$i['item'], 'item_nick'=>$itemn, 'list'=>[]];
			$items[$itemn]['list'][] = $i;
		}
		if ($items) {
			if ($item_nick) {
				//unset($items[$data['item_nick']]);
				foreach ($items as $k=>$item) {
					$list = $item['list'];
					unset($items[$k]['list']);
					Showcase::makeMore($items[$k], $list);
				}
			} else {
				foreach ($items as $k=>$item) break;
				$data['item'] = $item['item'];
				$data['item_nick'] = $item['item_nick'];


				//Showcase::makeMore($data, $item['list']);
				foreach ($items as $k=>$item) {
					$list = $item['list'];
					unset($items[$k]['list']);
					Showcase::makeMore($items[$k], $list);
				}
			}
			$data['items'] = array_values($items);
		}
			

		//}
		$g = Showcase::getGroup($data['group_nick']);
		$data += $g;
		unset($data['childs']);
		
		if ($catkit) $data['catkit'] = implode('&', $catkit);

		Event::fire('Showcase-position.onsearch', $data); //Позиция для общего списка
		return $data;
	}
	public static function makeMore(&$data, $list) {
		$option = Data::getOptions();
		$conf = Showcase::$conf;
		$columns = Showcase::getOption(['columns']);
		
		
		
		
		
		$more = array();
		foreach ($list as $row) {
			$prop = $row['prop'];
			$prop_nick = $row['prop_nick'];
			if (in_array($prop_nick, $columns)) {
				if (!isset($data[$prop])) $data[$prop] = [];
				$data[$prop][] = $row['val'];
			} else {
				if (!isset($more[$prop])) $more[$prop] = [];
				$more[$prop][] = $row['val'];
			}
		}

		//$files = Showcase::getOption(['files']);
		foreach ($data as $prop => $val) {
			if (
				//!in_array($prop, $files) && 
				is_array($val) 
				&& !in_array($prop, Data::$files) 
				&& !in_array($prop,['more'])) {
				$data[$prop] = implode(', ', $val);
			}
		}
		
		if ($more) {

			foreach($more as $name => $val) {
				if (is_array($val)) {
					$more[$name] = implode(', ', $val);
				}
			}
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

		if ($pages<=$plen) {
			$ar = array_fill(0, $pages+1, 1);
			$ar = array_keys($ar);
			array_shift($ar);
		} else {
			$plen=$plen-1;
			$lside=$plen/2+1;//Последняя цифра после которой появляется переход слева
			$rside=$pages-$lside-1;//Первая цифра после которой справа появляется переход
			$islspace=$page>$lside;
			$isrspace=$page<$rside+2;
			$ar = array(1);
			if ($isrspace&&!$islspace) {
				for ($i = 0; $i < $plen-2; $i++) {
					$ar[] = $i+2;
				}
				$ar[]=0;
				$ar[] = $pages;
			} else if (!$isrspace&&$islspace) {
				$ar[]=0;
				for ($i=0; $i<$plen-1; $i++) {
					$ar[] = $pages-$plen/2+$i-3;
				}
			} else if ($isrspace&&$islspace) {
				$nums=$plen/2-2;//Количество цифр показываемых сбоку от текущей когда есть $islspace далее текущая
				$ar[]=0;
				for ($i=0; $i<$nums*2+1; $i++) {
					$ar[] = $page-$plen/2+$i+2;
				}
				$ar[]=0;
				$ar[] = $pages;
			}
		}
		
		Each::exec($ar, function &(&$num) use ($page) {
			$n = $num;
			$num = array('num' => $n, 'title' => $n);
			if (!$num['num']) {
				$num['empty']=true;
				$num['num']='';
				$num['title']='&nbsp;';
			}
			if ($n==$page) {
				$num['active']=true;
			}
			$r = null;
			return $r;
		});
		if (sizeof($ar)<2) {
			return false;
		}
		$prev = array('num' => $page-1, 'title' => '&laquo;');
		if ($page<=1) {
			$prev['empty']=true;
		}

		array_unshift($ar, $prev);
		$next = array('num' => $page+1, 'title' => '&raquo;');
		if ($page>=$pages) {
			$next['empty']=true;
		}
		array_push($ar, $next);
		return $ar;
	}
	public static function makeBreadcrumb($md, &$ans, $page = 1) {
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
			$prod =  Data::fetch('SELECT producer, producer_nick from showcase_producers where producer_nick = ?',[$producer_nick]);
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
			$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['active']  =  true;

		} else if (!$md['group'] && $md['search']) {
			$ans['is'] = 'search';
			$ans['name'] = $md['search'];
			$ans['title'] = Path::encode($md['search'], true);
			$ans['breadcrumbs'][] = array('title' => $conf['title'], 'add' => 'search:');
			//$ans['breadcrumbs'][] = array('href' => 'find','title' => 'Поиск');
			$ans['breadcrumbs'][] = array('title' => $ans['name']);
			$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['active']  =  true;
		} else {
			//is!, descr!, text!, name!, breadcrumbs!, title
			if ($md['group']) foreach ($md['group'] as $group  =>  $v) break;
			else $group = false;

			$root = Data::getGroups();
			if ($group) {
				$group = Xlsx::runGroups($root, function &($g) use ($group){
					if ($g['group_nick'] == $group) return $g;
	  				$r = null;
	  				return $r;
				});
			} else {
				$group = $root;
			}
			$ans['is'] = 'group';	
			$ans['breadcrumbs'][] = array('href' => '','title' => $conf['title'], 'add' => 'group:');
			if (isset($group['path'])) {
				array_map(function ($p) use (&$ans) {
					$group = Showcase::getGroup($p);
					$ans['breadcrumbs'][] = array('href' => $group['group_nick'],'title' => $group['group']);
				}, $group['path']);
			}
			if (sizeof($ans['breadcrumbs']) == 1) {
				array_unshift($ans['breadcrumbs'],array('main' => true,"title" => "Главная","nomark" => true));
			}
			
				$ans['name'] = $group['group'];//имя группы длинное
				$ans['title'] = $group['group'];

			
			//$ans['descr']  =  isset($group['descr']['Описание группы']) ? $group['descr']['Описание группы'] : '';
			
			
			
			
			if (!isset($group['path'])) {
				if (sizeof($ans['filters'])) { //Если есть выбранные фильтры, ссылка на каталог сбрасывает выбор
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['href'] = '';
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['add'] = false;
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['nomark'] = true;
				} else {
					$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['active'] =  true;
				}
				//$ans['breadcrumbs'][] = array('href' => 'producers','title' => 'Производители');
			} else {
				$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['active'] =  true;	
			}
		}

	}
}