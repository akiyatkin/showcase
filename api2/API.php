<?php
namespace akiyatkin\showcase\api2;
use infrajs\db\Db;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\cache\CacheOnce;


class API {
	use CacheOnce; //once($name, $args, $fn) , $once
	public static function groupOptions($group_nick) {
		$props = API::once('props', [], function () {
			$conf = Data::loadShowcaseConfig();
			return $conf['groups'];
		});
		return $props[$group_nick] ?? [];
	}
	public static function getGroupByNick($group_nick) {
		$group_id = Db::col('SELECT group_id from showcase_groups WHERE group_nick = :group_nick', [
			':group_nick' => $group_nick
		]);
		return API::getGroupById($group_id);
	}
	public static function getGroupById($group_id) {
		return API::once('props', [$group_id], function ($group_id) {
			$group = Db::fetch('SELECT group_id, `group`, parent_id, group_nick, icon from showcase_groups WHERE group_id = :group_id',[
				':group_id' => $group_id
			]);
			if (!$group) return false;
			$group['options'] = API::groupOptions($group['group_nick']);
			if ($group['parent_id']) {
				$group['parent'] = API::getGroupById($group['parent_id']);
				$group['options'] = array_merge($group['parent']['options'], $group['options']);
			} else {
				$group['parent'] = false;
			}
			
			return $group;
		});
	}
	public static function getChilds($groups, $group = false) {
		//Найти общего предка для всех групп
		//Пропустить 1 вложенную группу
		//Отсортировать группы по их order

		$nicks = [];
		$path = [];
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

		$path = array_values($path);
		$level = sizeof($path);
		$childs = [];
		
		
		foreach ($groups as $k => $g) {
			if (empty($g['path'][$level])) continue;
			$nick = $g['path'][$level];
			
			$child = isset($g['path'][$level+1]) ? $g['path'][$level+1] : false;
			if (!isset($childs[$nick])) {
				$childs[$nick] = [
					'group' => $nicks[$nick]['group'],
					'group_nick' => $nicks[$nick]['group_nick'],
					'childs' => [],
					'min' => 0,
					'max' => 0
				];
				if (isset($g['icon'])) $childs[$nick]['icon'] = $g['icon'];
				if (isset($g['img'])) $childs[$nick]['img'] = $g['img'];
			}
			if (isset($g['min'])) {
				if (!$childs[$nick]['min'] || ($g['min'] && $g['min'] < $childs[$nick]['min'])) {
					$childs[$nick]['min'] = (float) $g['min'];
				}
				if (!$childs[$nick]['max'] || ($g['max'] && $g['max'] > $childs[$nick]['max'])) {
					$childs[$nick]['max'] = (float) $g['max'];
				}
			}
			
			if ($child) {
				if (!isset($childs[$nick]['childs'][$child])) {
					$childs[$nick]['childs'][$child] = [
						'group' => $nicks[$child]['group'],
						'group_nick' => $nicks[$child]['group_nick']
					];
				}
			}
		}

		if ($group) {
			if (!$childs
			 //&& $group_id != $group['group_id']
				) {
				//$childs[] = $group;
				if (isset($groups[0])) $childs[] = API::getGroupById($groups[0]['group_id']);
			}
		} else {
			if (sizeof($childs) == 1) $childs = [];
		}
		$childs = array_values($childs);
		return $childs;
	}
}