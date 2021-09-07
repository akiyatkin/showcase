<?php
namespace akiyatkin\showcase;
use infrajs\path\Path;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\api2\API;

class Catkit {
	public static function implode($kit, $char = '&') {

		$kit = array_reduce($kit, function ($gr, $k){
			return array_merge($gr, $k);
		}, []);
		$catkit = [];
		foreach ($kit as $p) {
			if (!empty($p['article_nick'])) {
				$c = $p['article_nick'];
				if ($p['item_num'] != 1) $c .= ':'.$p['item_num'];
			} else {
				$c = $p['present'];
			}
			$catkit[] = $c;
		}
		$catkit = implode($char, $catkit);
				
		return $catkit;
	}
	public static function run(&$kit, $callback) {
		foreach ($kit as $group => $list) {
			foreach ($list as $k => &$pos) {
				$r = $callback($pos, $group, $k);
				if (!is_null($r)) return $r;
			}
		}
	}
	public static function explode($catkit, $producer_nick) {
		$r = preg_split('/[&,]/', $catkit);
		$catkit = array_map( function ($catkit) use ($producer_nick) {
			$r = explode(':', $catkit);
			$article_nick = Path::encode($r[0]);
			if (!empty($r[1])) $item_num = Path::encode($r[1]);
			else $item_num = 1;

			$p = Showcase::getModelEasy($producer_nick, $article_nick, $item_num);
			if (!$p) return null;
			if (!$p) $p = [];
			$p['present'] = trim($catkit);
			return $p;
		}, $r);
		$catkit = array_filter($catkit);
		$kit = [];

		foreach ($catkit as $res) {
			$group = empty($res["Группа в комплекте"]) ? (empty($res["group"]) ? '' : $res["group"]) : $res["Группа в комплекте"];
			if (empty($kit[$group])) $kit[$group] = [];
			$kit[$group][] = $res;
		}
		
		return $kit;
	}
	public static function present($kit) {
		$kit = array_reduce($kit, function ($gr, $k){
			return array_merge($gr, $k);
		}, []);
		$catkit = [];
		foreach ($kit as $p) {
			$c = $p['article'];
			if ($p['item_num']) $c .= ':'.$p['item_num'];
			$catkit[] = $c;
		}
		$catkit = implode(', ', $catkit);
		return $catkit;
	}
	public static function apply(&$pos) {
		if (!empty($pos['catkit'])) {
			$catkit = $pos['catkit'];
			$pos['iscatkit'] = true; //Если новый catkit ec
		} else {
			if (empty($pos['Комплектация'])) return false;
			$catkit = $pos['Комплектация'];
		}
		
		
		$emptycat = [];
		$emptycost = [];
		$find = [];
		$kit = Catkit::explode($catkit, $pos['producer_nick']);
		$cost = 0;
		$count = 0;
		Catkit::run($kit, function($p, $group, $i) use (&$count, &$cost, &$kit, &$emptycat, &$emptycost, &$find) {
			if (empty($p['article_nick'])) {
				$emptycat[] = $p['present'];
				
				unset($kit[$group][$i]);
				if (empty($kit[$group])) unset($kit[$group]);
				return;
			}
			$count++;
			if (empty($p['Цена'])) {
				$emptycost[] = $p['present'];
				$p['Цена'] = false;
			}
			$find[] = $p['present'];
			$cost += $p['Цена'];
		});
		
		$pos['catkit'] = Catkit::implode($kit);

		$pos['catkits'] = explode('&', $pos['catkit']);
		
		$pos['Комплектация'] = Catkit::present($kit);

		$pos['Цена'] = $cost;
		$pos['kit'] = $kit;
		$pos['kitcount'] = $count;
		
		if ($emptycat) {
			$pos['more']['Нет информации по комплектующим'] = implode(', ', array_unique($emptycat));
			unset($pos['Цена']);
		} else {
			unset($pos['more']['Нет информации по комплектующим']);
		}
		if ($emptycost) {
			$pos['more']['Нет цены по комплектующим'] = implode(', ', array_unique($emptycost));
			unset($pos['Цена']);
		} else {
			unset($pos['more']['Нет цены по комплектующим']);
		}
	}
	public static function setKitlist(&$pos) {
		$kit = Catkit::implode(['sadf'=>[$pos]]); //Группа не участует в запросе (safd)
		//Проверяем у кого есть комплектующие и заносим из в список
		$mark = Showcase::getDefaultMark();
		$mark->setVal(':more.compatibilities.'.$kit.'=1:count=50');
		$md = $mark->getData();
		$data = Showcase::search($md);
		if (empty($data['list'])) return;
		
		$pos['kitlist'] = array_reduce($data['list'], function ($carry, $p) {
			if (empty($p['Группа в комплекте'])) $p['Группа в комплекте'] = '';		
			if(empty($carry[$p['Группа в комплекте']])) $carry[$p['Группа в комплекте']] = [];
			$p['kitid'] = $p['article_nick'].($p['item_num']?(':'.$p['item_num']):'');
			$carry[$p['Группа в комплекте']][] = $p;

			return $carry;
		},[]);
		if (empty($pos['kitlist'])) unset($pos['kitlist']);
		else $pos['catkitgroups'] = Showcase::getOption(['catkitgroups']);
	}
	public static function setCompatibilities(&$pos) {
		if (empty($pos['compatibilities'])) return;
		//Наполняем комплекты, к которым подходит текущая позиция

		$r = explode(',',$pos['compatibilities']);
		if (sizeof($r) > 3) {
			unset($pos['compatibilities']);
			return;
		}
		$pos['compatibilities'] = Catkit::explode($pos['compatibilities'], $pos['producer_nick']);
	}
	public static function setKitPhoto(&$pos) {
		/*
			Если есть выбранные комплектации, то по одной фото каждого комплектующего добавляется в систему в добавленном порядке. В showcase.json добавлен параметр для групп firstkitphoto, в нём указывается "Группа в комплекте" фото комплектующих из этой группы встаёт на первое место. Но не выше, чем собственное фото системы.
		*/
		if (empty($pos['kit'])) return; 
		
		//Ищем картинки если есть выбранный kit
		//Свойство kit содержит массив созданный из Комплектации это выбранные комплектующие
		//$group = Showcase::getGroup($pos['group_nick']);
		$images = [];
		if (isset($pos['group_id'])) {
			$group = API::getGroupById($pos['group_id']);
			$firstkitphoto = empty($group['showcase']['firstkitphoto']) ? '' : $group['showcase']['firstkitphoto'];
			if ($firstkitphoto) {
				$kitlist = array_reduce($pos['kit'], function ($carry, $p){
					if (empty($p['Группа в комплекте'])) $p['Группа в комплекте'] = '';
						
					if(empty($carry[$p['Группа в комплекте']])) $carry[$p['Группа в комплекте']] = [];
					$carry[$p['Группа в комплекте']][] = $p;
					return $carry;
				},[]);	
				if (isset($kitlist[$firstkitphoto])) { //и есть выбранный главный компонент
					foreach ($kitlist[$firstkitphoto] as $p) { //Берём фото главного
						if (empty($p['images'])) continue;
						$images[]= $p['images'][0];
					}
				}
			}
		}
		Catkit::run($pos['kit'], function ($p) use (&$images) {
			if (empty($p['images'])) return; 
			$images[]= $p['images'][0];//Берём фото остальных
		});

		if (empty($pos['images'])) $pos['images'] = [];
		$pos['images'] = array_unique(array_merge($pos['images'], $images));
	}
}