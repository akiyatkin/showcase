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
	`ans` mediumtext NULL COMMENT '',
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
	`ans` mediumtext NULL COMMENT '',
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
	`order` SMALLINT unsigned COMMENT 'Порядок определяется загрузки данных',
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
	`type` SET("value","number","text") NOT NULL COMMENT 'В какой колонке и как хранятся значения',
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

/*
$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_articles` (
	`article_id` MEDIUMINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`article` varchar(255) NOT NULL COMMENT '',
	`article_nick` varchar(255) NOT NULL COMMENT '',
	PRIMARY KEY (`article_id`),
	UNIQUE INDEX (`article_nick`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);*/

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
//- !models (model_id, article, article_nick)					fix - producer_id, group_id, catalog_id
$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_models` (
	`model_id` MEDIUMINT unsigned NOT NULL AUTO_INCREMENT COMMENT '',
	`producer_id` SMALLINT unsigned COMMENT '',
	`article_nick` varchar(255) NOT NULL COMMENT '',
	`article` varchar(255) NOT NULL COMMENT '',

	`catalog_id` SMALLINT unsigned COMMENT '',
	`time` DATETIME NULL DEFAULT NULL COMMENT '',
	`group_id` SMALLINT unsigned NOT NULL COMMENT '',
	PRIMARY KEY (`model_id`),
	UNIQUE INDEX (`producer_id`,`article_nick`),
	INDEX (group_id, producer_id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
END;
scexec($sql);

//item_nick depricated
//item может повторятся (из него удаляется Наименование и могут быть потворы просто пронумерованные) 255 вместо 511
//Наименование удалять у нас концепция, что это читабельный артикул. и у позиций модели не может варьироваться.

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_items` (
	`model_id` MEDIUMINT unsigned NOT NULL COMMENT '',
	`item_num` SMALLINT unsigned NOT NULL COMMENT '',
	`item_nick` TEXT NOT NULL COMMENT '',
	`item` TEXT NOT NULL COMMENT '',
	PRIMARY KEY (`model_id`, `item_num`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
END;
scexec($sql);

$sql = <<<END
CREATE TABLE IF NOT EXISTS `showcase_iprops` (
	`model_id` MEDIUMINT unsigned NOT NULL COMMENT '',
	`item_num` SMALLINT unsigned NOT NULL COMMENT '',
	`prop_id` SMALLINT unsigned NOT NULL COMMENT '65 тыс',
	`value_id` MEDIUMINT unsigned NULL COMMENT '16 млн',
	`number` DECIMAL(19,2) NULL COMMENT '',
	`text` mediumtext NULL COMMENT '',
	`price_id` SMALLINT unsigned NULL COMMENT '65 тыс',
	`order` SMALLINT unsigned NOT NULL COMMENT '',
	UNIQUE (`model_id`, `item_num`, `prop_id`, `value_id`),
	INDEX (prop_id, value_id),
	INDEX (model_id, prop_id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
END;
scexec($sql);

/*$sql = <<<END
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
	`text` mediumtext NOT NULL COMMENT '',
	`price_id` SMALLINT unsigned NULL COMMENT '65 тыс',
	`order` SMALLINT unsigned NOT NULL COMMENT '',
	INDEX (model_id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
END;
scexec($sql);
*/