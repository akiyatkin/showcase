<?php
namespace akiyatkin\showcase;
use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\each\Each;
use infrajs\once\Once;
use infrajs\excel\Xlsx;
use infrajs\rubrics\Rubrics;
use infrajs\sequence\Sequence;
use infrajs\db\Db;
use infrajs\config\Config;
use akiyatkin\showcase\Data;
use infrajs\ans\Ans;
use infrajs\mark\Mark as Marker;

class Showcase {
	public static $conf;
	public static $list = array();
	public static function add($name, $fndef, $fncheck)
	{
		Showcase::$list[$name] = array('fndef' => $fndef, 'fncheck' => $fncheck);
	}
	public static function getDefaultMark() {
		$mark = new Marker('~auto/.showcase/');
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
	public static function initMark(&$ans = array(), $val = '', $art = '')
	{
		$val = Ans::GET('val');
		$val = Path::encode(Path::toutf(strip_tags($val)));
		$art = Ans::GET('art');
		if ($val && !$art) {

			$group = Showcase::getGroup($val);

			if (!isset($_GET['m'])) $_GET['m'] = '';
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
		$m = Path::toutf(Sequence::get($_GET, array('m')));
		$ar = Once::func( function ($m) {
			$mark = Showcase::getDefaultMark();
			$mark->setVal($m);
			$md = $mark->getData();
			$m = $mark->getVal();	
			return array('md' => $md, 'm' => $m);
		}, array($m));
		$ans['m'] = $ar['m'];
		$ans['md'] = $ar['md'];
		return $ar['md'];
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
	public static function getGroupsIn($md) {
		$groups = [];
		foreach ($md['group'] as $group => $one) {
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
	public static function search($md, &$ans, $page = 1) {
		$count = $md['count'];
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
		foreach ($md['producer'] as $prod => $one) {
			$producer_id = Data::col('SELECT producer_id from showcase_producers where producer_nick = ?',[$prod]);
			if ($producer_id) {
				$prods[] = $producer_id;
			}
		}
		if ($prods) { //Если есть группа надо достать все вложенные группы
			$ans['filters'][] = array( 
				'name' => 'producer',
				'value' => implode(', ',array_keys($md['producer'])),
				'title' => 'Производитель'
			);

			$prods = array_unique($prods);
			$prquery = implode(',', $prods);
			$prquery = 'and m.producer_id in ('.$prquery.')';
		}
		$nal1 = Data::initValue("Акция");
		$nal2 = Data::initValue("Распродажа");
		$nal3 = Data::initValue("В-наличии");

		$join = [];
		$no = [];
	
		foreach ($md['more'] as $prop_nick => $vals) {
			$prop = Data::fetch('SELECT * from showcase_props where prop_nick = ?',[$prop_nick]);
			if (!$prop) {
				$ans['filters'][] = array( 
					'name' => 'more.'.$prop_nick,
					'value' => implode(', ', array_keys($vals)),
					'title' => 'Нет свойства '.$prop_nick 
				);
				$no[] = 'and 1=0';
				continue;
			}
			$prop_id = $prop['prop_id'];
			$ans['filters'][] = array( 
				'name' => 'more.'.$prop_nick,
				'value' => implode(', ',array_keys($vals)),
				'title' => $prop['prop']
			);
			$type = Data::checkType($prop_nick);

			foreach ($vals as $val => $one) {
				if ($type == 'value') {
					if ($val == 'yes') {
						$join[] = 'INNER JOIN showcase_mvalues p'.$prop_id.' on (p'.$prop_id.'.model_id = m.model_id and p'.$prop_id.'.prop_id = '.$prop_id.')';;
					} else if ($val == 'no') {
						$no[] = 'and p'.$prop_id.'.prop_id is null';
						$join[] = 'LEFT JOIN showcase_mvalues p'.$prop_id.' on (p'.$prop_id.'.model_id = m.model_id and p'.$prop_id.'.prop_id = '.$prop_id.')';
					} else {
						$value_id = Data::initValue($val);
						$join[] = 'INNER JOIN showcase_mvalues p'.$prop_id.' on (p'.$prop_id.'.model_id = m.model_id and p'.$prop_id.'.prop_id = '.$prop_id.' and p'.$prop_id.'.value_id = '.$value_id.')';
					}
				} else {
					if ($val == 'yes') {
						$join[] = 'INNER JOIN showcase_mnumbers p'.$prop_id.' on (p'.$prop_id.'.model_id = m.model_id and p'.$prop_id.'.prop_id = '.$prop_id.')';
					} else if ($val == 'no') {
						$no[] = 'and p'.$prop_id.'.prop_id is null';
						$join[] = 'LEFT JOIN showcase_mnumbers p'.$prop_id.' on (p'.$prop_id.'.model_id = m.model_id and p'.$prop_id.'.prop_id = '.$prop_id.')';
					} else {
						$number = (float) $val;
						$join[] = 'INNER JOIN showcase_mnumbers p'.$prop_id.' on (p'.$prop_id.'.model_id = m.model_id and p'.$prop_id.'.prop_id = '.$prop_id.' and p'.$prop_id.'.number = '.$number.')';
					}
				}
			}
			
		}
		if (!empty($md['search'])) {
			$v = preg_split("/[\s\-]+/", mb_strtolower($md['search']));
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
					OR m.article_id LIKE "%'.$s.'%" 
					OR m.model_id LIKE "%'.$s.'%" 
					OR pr.producer_nick LIKE "%'.$s.'%"
				)';
			}
			$ans['filters'][] = array(
				'title' => 'Поиск',
				'name' => 'search',
				'value' => $md['search']
			);
		
			$no[] = $str;
		}


		
		$join = implode(' ', $join);
		
		$no = implode(' ', $no);	
		$start = ($page-1)*$count;

		$models = Data::all('
			SELECT SQL_CALC_FOUND_ROWS max(i.item_nick) as item_nick, mn.number, a.article_nick, pr.producer_nick, GROUP_CONCAT(m.group_id) from showcase_items i
			LEFT JOIN showcase_models m on i.model_id = m.model_id
			LEFT JOIN showcase_groups g on g.group_id = m.group_id
			LEFT JOIN showcase_producers pr on pr.producer_id = m.producer_id
			LEFT JOIN showcase_articles a on a.article_id = m.article_id
			LEFT JOIN showcase_mnumbers mn on (mn.model_id = m.model_id and (mn.item_num = i.item_num or mn.item_num = 0) and mn.prop_id = :cost_id)
			LEFT JOIN showcase_mvalues mn2 on (mn2.model_id = m.model_id and (mn2.item_num = i.item_num or mn2.item_num = 0) and mn2.prop_id = :nalichie_id)
			LEFT JOIN showcase_mvalues mn3 on (mn3.model_id = m.model_id and (mn3.item_num = i.item_num or mn3.item_num = 0) and mn3.prop_id = :image_id)
			'.$join.'
			WHERE 1=1 '.$grquery.' '.$prquery.' '.$no.'
			GROUP BY pr.producer_id, a.article_id
			ORDER BY 
			IF(mn3.value_id is null,1,0),
			IF(mn2.value_id = :nal1,0,1),
			IF(mn2.value_id = :nal2,0,1), 
			IF(mn2.value_id = :nal3,0,1), 
			IF(mn.number IS NULL,1,0), 
			mn.number
			limit '.$start.','.$count
		, [':cost_id' => $cost_id, ':nalichie_id' => $nalichie_id, ':image_id' => $image_id, 
			':nal1' => $nal1, ':nal2' => $nal2, ':nal3' => $nal3]);
		$size = Data::col('SELECT FOUND_ROWS()');
		foreach ($models as $k=>$m) {
			$models[$k] = Showcase::getModel($m['producer_nick'], $m['article_nick'], $m['item_nick']);
		}
		$ans['list'] = $models;


		$groups = Data::fetchto('
			SELECT max(v.value) as img, g.group, g.group_nick, g.group_id, g.parent_id, count(DISTINCT m.model_id) as `count` from showcase_models m
			LEFT JOIN showcase_groups g on g.group_id = m.group_id
			LEFT JOIN showcase_producers pr on pr.producer_id = m.producer_id
			LEFT JOIN showcase_articles a on a.article_id = m.article_id
			LEFT JOIN showcase_mvalues mn3 on (mn3.model_id = m.model_id and mn3.prop_id = :image_id)
			LEFT JOIN showcase_values v on mn3.value_id = v.value_id
			'.$join.'
			WHERE 1=1 '.$grquery.' '.$prquery.' '.$no.'
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

			usort($group['childs'], function ($a, $b){
				if ($a['found'] > $b['found']) return -1;
				if ($a['found'] < $b['found']) return 1;
				return 0;
			});
			return $r;
		}, true);
		
		$conf = Showcase::$conf;
		Xlsx::runGroups ($root, function &(&$group, $conf) {
			$r = null;
			while (isset($group['childs']) && sizeof($group['childs']) == 1 && isset($group['childs'][0]['childs'])) {
				$group['childs'] = array_values($group['childs'][0]['childs']);
			}
			
			$img = Rubrics::find($conf['icons'], $group['group_nick'], 'images');
			if ($img) $group['img'] = $img;
			return $r;
		});
		$ans['childs'] = array_values($root['childs']);
		foreach($ans['childs'] as $i => $ch) {
			if (empty($ans['childs'][$i]['childs'])) continue;
			foreach($ans['childs'][$i]['childs'] as $ii => $cch) {
				unset($ans['childs'][$i]['childs'][$ii]['childs']);
			}
		}
		if (sizeof($ans['childs']) == 1) unset($ans['childs']);
		$pages = ceil($size / $count);
		if ($pages < $page) $page = $pages;
		$ans['count'] = (int) $size;

		$ans['numbers'] = Showcase::numbers($page, $pages, 11);
	}
	public static function getColumns(){
		$options = Data::getOptions();
		return array_merge(Showcase::$columns, $options['columns']);
	}
	public static function getOptions(){
		$options = Data::getOptions();
		return $options;
	}
	public static $columns = array("images", "files", "texts","videos", "Наименование","Файл","Иллюстрации","Файлы","Фото","Цена","Описание","Скрыть-фильтры-в-полном-описании");
	public static function getModel($producer, $article, $item_nick = '') {
		$data = Data::fetch('SELECT 
			m.model_id, p.producer_nick, 
			p.producer, a.article_nick, 
			a.article, g.group_nick, g.group
			FROM showcase_models m
			INNER JOIN showcase_articles a on (a.article_id = m.article_id and a.article_nick = :article)
			LEFT JOIN showcase_producers p on (p.producer_id = m.producer_id and p.producer_nick = :producer)
			LEFT JOIN showcase_groups g on (g.group_id = m.group_id)
			order by m.article_id
			', [':article'=>$article,':producer'=>$producer]);
		if (!$data) return false;
		
		$item = Data::fetch('SELECT item_num, item, item_nick from showcase_items where model_id = ? and item_nick = ?',[$data['model_id'], $item_nick]);
		if ($item === false) return false;
		$item_num = $item['item_num'];
		//exit;

		$list1 = Data::all('SELECT p.prop, p.prop_nick, v.value as val, smv.order
			FROM showcase_mvalues smv, showcase_values v, showcase_props p
			WHERE smv.value_id = v.value_id
			AND p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND (smv.item_num = 0 or smv.item_num = ?)
			',[$data['model_id'], $item_num]);
		
		$list2 = Data::all('SELECT p.prop, p.prop_nick, smv.number as val, smv.order
			FROM showcase_mnumbers smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND (smv.item_num = 0 or smv.item_num = ?)
		',[$data['model_id'], $item_num]);
		foreach ($list2 as $i => $row) {
			$list2[$i]['val'] = (float) $row['val'];
		}
		
		$list3 = Data::all('SELECT p.prop, p.prop_nick, smv.text as val, smv.order
			FROM showcase_mtexts smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
			AND (smv.item_num = 0 or smv.item_num = ?)
			',[$data['model_id'], $item_num]);
		$list = array_merge($list1, $list2, $list3);

		usort($list, function($a, $b){
			if ($a['order'] > $b['order']) return -1;
			if ($a['order'] < $b['order']) return 1;
		});
		Showcase::makeMore($data, $list);
		$data += $item;
		
		if ($item_nick) {
			$its = Data::all('
					SELECT i.item_nick, ps.prop, ps.prop_nick, v.value as val from showcase_mvalues mv
					left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
					left join showcase_props ps on ps.prop_id = mv.prop_id
					left join showcase_values v on v.value_id = mv.value_id
					where mv.model_id = ? and mv.item_num > 0 
					UNION ALL
					SELECT i.item_nick, ps.prop, ps.prop_nick, mv.number as val from showcase_mnumbers mv
					left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
					left join showcase_props ps on ps.prop_id = mv.prop_id
					WHERE mv.model_id = ? and mv.item_num > 0 
					UNION ALL
					SELECT i.item_nick, ps.prop, ps.prop_nick, mv.text as val from showcase_mtexts mv
					left join showcase_items i on (i.item_num = mv.item_num and i.model_id = mv.model_id)
					left join showcase_props ps on ps.prop_id = mv.prop_id
					where mv.model_id = ? and mv.item_num > 0 

					',[$data['model_id'],$data['model_id'],$data['model_id']]);
			
			$items = [];
			foreach ($its as $i) {
				$item_nick = $i['item_nick'];
				if (!isset($items[$item_nick])) $items[$item_nick] = ['item_nick'=>$item_nick, 'list'=>[]];
				$items[$item_nick]['list'][] = $i;
			}
			unset($items[$data['item_nick']]);
			foreach ($items as $k=>$item) {
				$list = $item['list'];
				unset($items[$k]['list']);
				Showcase::makeMore($items[$k], $list);
			}
			$data['items'] = array_values($items);
		}
		$data += Showcase::getGroup($data['group_nick']);
		unset($data['childs']);

		if (isset($data['files'])) {
			foreach ($data['files'] as $i => $path) {
				$fd = Load::pathinfo($path);
				$fd['size'] = round(FS::filesize($path)/1000000, 2);
				if (!$fd['size']) $fd['size'] = '0.01';
				$data['files'][$i] = $fd;
			} 
		}
		return $data;
	}
	public static function makeMore(&$data, $list) {
		$option = Data::getOptions();
		$conf = Showcase::$conf;
		$columns = Showcase::getColumns();
		
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
		foreach ($data as $prop => $val) {
			if (is_array($val) && !in_array($prop, Data::$files)) {
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
			if ($md['producer']) foreach ($md['producer'] as $producer  =>  $v) break;
			else $producer = false;
			//is!, descr!, text!, name!, breadcrumbs!
			$ans['is'] = 'producer';
			$name = Showcase::getProducer($producer);
			$ans['name'] = $name;
			$ans['title'] = $name;

			
			$ans['breadcrumbs'][] = array('title' => $conf['title'], 'add' => 'producer:');
			
			$ans['breadcrumbs'][] = array('href' => 'producers','title' => 'Производители');
			$ans['breadcrumbs'][] = array('href' => $name, 'title' => $name);
			$ans['breadcrumbs'][sizeof($ans['breadcrumbs'])-1]['active']  =  true;

		} else if (!$md['group'] && $md['search']) {
			$ans['is'] = 'search';
			$ans['name'] = $md['search'];
			$ans['title'] = Path::encode($md['search'], true);
			$ans['breadcrumbs'][] = array('title' => $conf['title'], 'add' => 'search:');
			$ans['breadcrumbs'][] = array('href' => 'find','title' => 'Поиск');
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