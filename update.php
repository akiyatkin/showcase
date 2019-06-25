<?php

use infrajs\db\Db;
use infrajs\ans\Ans;
use infrajs\path\Path;

//Path::mkdir('~auto/');
//Path::mkdir('~auto/.showcase/');

function scexec($sql) {
	$db = &Db::pdo();

	if (!$db) return;
	try {
		$r = $db->exec($sql);
	} catch (Exception $e) {
		echo '<pre>';
		print_r($e);
		die(print_r($db->errorInfo(), true));
	}

	if ($r === false) {
		Ans::err(print_r($db->errorInfo(), true));
	}
}

//PRICES

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_prices` (
	`price_id` SMALLINT unsigned NOT NULL AUTO_INCREMENT COMMENT 'id источника',
	`name` varchar(255) NOT NULL COMMENT 'Идентификационное имя источника - имя файла',
	`producer_id` SMALLINT unsigned COMMENT 'Производитель, ограничение для будущих операций',
	`time` DATETIME NULL DEFAULT NULL COMMENT 'Дата последнего внесения',
	`order` SMALLINT unsigned COMMENT 'Порядок применнеия данных',
	`count` SMALLINT unsigned COMMENT 'Количество записей',
	`duration` SMALLINT unsigned COMMENT 'Записывается время разбора данных',
	`ans` text NULL COMMENT '',
	PRIMARY KEY (`price_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;

scexec($sql);


//CATALOG

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_catalog` (
	`catalog_id` SMALLINT unsigned NOT NULL AUTO_INCREMENT COMMENT 'id источника',
	`name` varchar(255) NOT NULL COMMENT 'Идентификационное имя источника - имя файла',
	`producer_id` SMALLINT unsigned COMMENT 'Производитель, ограничение для будущих операций',
	`time` DATETIME NULL DEFAULT NULL COMMENT 'Дата последнего внесения',
	`order` SMALLINT unsigned COMMENT 'Порядок определяется при обновлении всех файлов',
	`count` SMALLINT unsigned COMMENT 'Количество записей',
	`duration` SMALLINT unsigned COMMENT 'Записывается время разбора данных',
	`ans` text NULL COMMENT '',
	PRIMARY KEY (`catalog_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;

scexec($sql);

//META

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_groups` (
	`group_id` SMALLINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`group` varchar(255) NOT NULL COMMENT '',
	`parent_id` SMALLINT unsigned NULL COMMENT '',
	`group_nick` varchar(255) NOT NULL COMMENT '',
	`icon` varchar(255) NULL COMMENT '',
	`catalog_id` SMALLINT unsigned NOT NULL COMMENT 'Кто записал структуру и может изменить её',
	PRIMARY KEY (`group_id`),
	UNIQUE INDEX (`group_nick`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_props` (
	`prop_id` SMALLINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`prop` varchar(255) NOT NULL COMMENT '',
	`prop_nick` varchar(255) NOT NULL COMMENT '',
	`type` TINYINT unsigned NOT NULL COMMENT '1 value, 2 number, 3 text',
	PRIMARY KEY (`prop_id`),
	UNIQUE INDEX (prop_nick)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_values` (
	`value_id` MEDIUMINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`value` varchar(255) NOT NULL COMMENT '',
	`value_nick` varchar(255) NOT NULL COMMENT '',
	PRIMARY KEY (`value_id`),
	UNIQUE INDEX (`value_nick`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_articles` (
	`article_id` MEDIUMINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`article` varchar(255) NOT NULL COMMENT '',
	`article_nick` varchar(255) NOT NULL COMMENT '',
	PRIMARY KEY (`article_id`),
	UNIQUE INDEX (`article_nick`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_producers` (
	`producer_id` SMALLINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`producer` varchar(255) NOT NULL COMMENT '',
	`producer_nick` varchar(255) NOT NULL COMMENT '',
	`logo` varchar(255) NULL COMMENT '',
	PRIMARY KEY (`producer_id`),
	UNIQUE (`producer_nick`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);


//MODELS

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_models` (
	`model_id` MEDIUMINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`catalog_id` SMALLINT unsigned COMMENT '',
	`producer_id` SMALLINT unsigned COMMENT '',
	`article_id` MEDIUMINT unsigned COMMENT '',
	`time` DATETIME NULL DEFAULT NULL COMMENT '',
	`group_id` SMALLINT unsigned NOT NULL COMMENT '',
	PRIMARY KEY (`model_id`),
	UNIQUE INDEX (`article_id`, `producer_id`),
	INDEX (group_id, producer_id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_items` (
	`model_id` MEDIUMINT unsigned NOT NULL COMMENT '',
	`item_num` SMALLINT unsigned NOT NULL COMMENT '',
	`item_nick` varchar(255) NOT NULL COMMENT '',
	`item` varchar(255) NOT NULL COMMENT '',
	PRIMARY KEY (`model_id`, `item_num`),
	UNIQUE (`model_id`,`item_nick`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
END;
scexec($sql);


$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_mvalues` (
	`model_id` MEDIUMINT unsigned NOT NULL COMMENT '',
	`item_num` SMALLINT unsigned NOT NULL COMMENT '',
	`prop_id` SMALLINT unsigned NOT NULL COMMENT '65 тыс',
	`value_id` MEDIUMINT unsigned NOT NULL COMMENT '16 млн',
	`price_id` SMALLINT unsigned NULL COMMENT '65 тыс',
	`order` SMALLINT unsigned NOT NULL COMMENT '',
	UNIQUE (`model_id`, `item_num`, `prop_id`, `value_id`),
	INDEX (model_id),
	INDEX (prop_id, value_id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_mnumbers` (
	`model_id` MEDIUMINT unsigned NOT NULL COMMENT '',
	`item_num` SMALLINT unsigned NOT NULL COMMENT '',
	`prop_id` SMALLINT unsigned NOT NULL COMMENT '65 тыс',
	`number` DECIMAL(19,2) NOT NULL COMMENT '',
	`price_id` SMALLINT unsigned NULL COMMENT '65 тыс',
	`order` SMALLINT unsigned NOT NULL COMMENT '',
	UNIQUE (`model_id`, `item_num`, `prop_id`, `number`),
	INDEX (model_id),
	INDEX (prop_id, number)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_mtexts` (
	`model_id` MEDIUMINT unsigned NOT NULL COMMENT '',
	`item_num` SMALLINT unsigned NOT NULL COMMENT '',
	`prop_id` SMALLINT unsigned NOT NULL COMMENT '',
	`text` text NOT NULL COMMENT '',
	`price_id` SMALLINT unsigned NULL COMMENT '65 тыс',
	`order` SMALLINT unsigned NOT NULL COMMENT '',
	UNIQUE (`model_id`, `item_num`, `prop_id`),
	INDEX (model_id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
END;
scexec($sql);