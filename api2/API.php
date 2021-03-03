<?php
namespace akiyatkin\showcase\api2;
use infrajs\db\Db;
use infrajs\path\Path;
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
			$group = Db::fetch('SELECT group_id, `order`, `group`, parent_id, group_nick, icon from showcase_groups WHERE group_id = :group_id',[
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
	public static function updateSearch() {
		Data::exec('TRUNCATE `showcase_search`');
		$sql = 'SELECT SQL_CALC_FOUND_ROWS 
			m.model_id, m.group_id, 
			p.producer_id,
			p.producer_nick,
			g.group_nick,
			m.article_nick,
			(
				SELECT GROUP_CONCAT(v.value_nick SEPARATOR "-")
				from showcase_values v, showcase_iprops ip
				where ip.model_id = m.model_id
				and ip.value_id = v.value_id
			) as vals,
			(
				SELECT GROUP_CONCAT(ip.text SEPARATOR " ")
				from showcase_iprops ip
				where ip.model_id = m.model_id
			) as texts,
			(
				SELECT GROUP_CONCAT(FLOOR(ip.number) SEPARATOR "-")
				from showcase_iprops ip
				where ip.model_id = m.model_id
			) as numbers
			from 
				showcase_models m, 
				showcase_producers p,
				showcase_groups g
			where 
				p.producer_id = m.producer_id
				and m.group_id = g.group_id
		';
		$stmt = Db::cstmt($sql);
		$stmt->execute();	
		while ($row = $stmt->fetch()) {
			$model_id = $row['model_id'];
			$vals = $row['group_nick'].
					'-'.$row['producer_nick'].
					'-'.$row['article_nick'].
					'-'.$row['model_id'].
					'-'.$row['vals'];

			$texts = Path::encode($row['texts']);
			$numbers = $row['numbers'];
			if ($texts) $vals .= '-'.$texts;
			if ($numbers) $vals .= '-'.$numbers;
			
			$vals = explode('-', $vals);
			$vals = array_unique($vals);
			$vals = implode(' ', $vals);

			Data::exec(
				'INSERT INTO showcase_search (model_id, vals) VALUES(:model_id,:vals)',
				[
					':model_id' => $model_id, 
					':vals' => $vals
				]
			);
		}
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
					'order' => $nicks[$nick]['order'],
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
		usort($childs, function ($a, $b) {
		    return $a['order'] - $b['order'];
		});
		return $childs;
	}
}