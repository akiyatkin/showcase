<?php
use akiyatkin\meta\Meta;
use akiyatkin\fs\FS;
use akiyatkin\showcase\Showcase;
use akiyatkin\showcase\Data;
use infrajs\access\Access;
use infrajs\config\Config;
use infrajs\load\Load;
use infrajs\rubrics\Rubrics;
use infrajs\db\Db;
use infrajs\path\Path;
use akiyatkin\showcase\apin\API;

$meta = new Meta();

Access::debug(true);
$meta->addArgument('producer_nick', function ($producer_nick) {
	$prods = API::getProducers();
	$this->ans['prods'] = $prods;
	if (empty($prods[$producer_nick])) return $this->fail();

	return $producer_nick;
});
$meta->addArgument('type', function ($type) {
	$types = array_diff(Data::$files, ['folders']);
	$this->ans['types'] = $types;	
	if (!in_array($type, $types)) return $this->fail();
	return $type;
});
$meta->addAction('filesproducers', function ($producer_nick) {
	$prods = API::getProducers();
	$this->ans['prods'] = $prods;
	return $this->ret();
});
$meta->addAction('files', function () {
	//images, folders
	extract($this->gets(['producer_nick','type']));
	$this->ans['producer_nick'] = $producer_nick;
	$this->ans['images'] = API::getFiles($producer_nick, $type);
	
	return $this->ret();
});
$meta->addAction('applyfiles', function () {
	//images, folders
	extract($this->gets(['producer_nick','type']));

	
	$res = API::apply($producer_nick, $type);
	
	$this->ans['producer_nick'] = $producer_nick;
	$this->ans['type'] = $type;
	$this->ans['res'] = $res;
	// $this->ans['files'] = $files;
	// $this->ans['poss'] = $poss;
	return $this->ret();
});
$meta->addAction('applyicons', function () {
});
$meta->addAction('applyallfiles', function () {
	//images, folders
	$this->ans['ress'] = API::applyAll();
	return $this->ret();
});


return $meta->init([
	'base'=>'-showcase/apin/'
]);