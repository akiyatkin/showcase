<?php
namespace akiyatkin\showcase;
use akiyatkin\fs\FS;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\once\Once;
use infrajs\excel\Xlsx;
use infrajs\db\Db;
use akiyatkin\showcase\Data;

class Showcase {
	public static $conf;
	
	public static function getModel($producer, $article) {
		$data = Data::fetch('SELECT m.model_id, a.article, g.group, p.producer
			FROM showcase_models m, showcase_articles a, showcase_producers p, showcase_groups g
			WHERE m.article_id = a.article_id
			AND m.producer_id = p.producer_id
			AND m.group_id = g.group_id
			
			AND a.article_nick = ?
			', [$article]);
		$data['more'] = array();
		

		$list1 = Data::all('SELECT p.prop, v.value as val, smv.order
			FROM showcase_mvalues smv, showcase_values v, showcase_props p
			WHERE smv.value_id = v.value_id
			AND p.prop_id = smv.prop_id
			AND smv.model_id = ?
			',[$data['model_id']]);

		$list2 = Data::all('SELECT p.prop, smv.number as val, smv.order
			FROM showcase_mnumbers smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
		',[$data['model_id']]);
		

		$list3 = Data::all('SELECT p.prop, smv.text as val, smv.order
			FROM showcase_mtexts smv, showcase_props p
			WHERE p.prop_id = smv.prop_id
			AND smv.model_id = ?
			',[$data['model_id']]);
		$list = array_merge($list1, $list2, $list3);

		usort($list, function($a, $b){
			if ($a['order'] > $b['order']) return 1;
			if ($a['order'] < $b['order']) return -1;
		});

		foreach ($list as $row) {
			if (!isset($data['more'][$row['prop']])) $data['more'][$row['prop']] = [];
			$data['more'][$row['prop']][] = $row['val'];
		}

		foreach($data['more'] as $name => $val) {
			if (is_array($val)) {
				$data['more'][$name] = implode(', ', $val);
			}
		}

		return $data;
	}
	public static function search() {
		return Data::all('SELECT m.time, g.group_nick, g.group, a.article_nick, p.producer_nick, a.article, p.producer
			FROM showcase_models m, showcase_articles a, showcase_producers p, showcase_groups g
			WHERE m.article_id = a.article_id
			AND m.producer_id = p.producer_id
			AND m.group_id = g.group_id
			limit 0,100');
	}
}