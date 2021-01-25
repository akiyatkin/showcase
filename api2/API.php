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
	
}