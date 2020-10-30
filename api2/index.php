<?php
use akiyatkin\meta\Meta;
use infrajs\db\Db;
use akiyatkin\showcase\api2\API;


$meta = new Meta();

$meta->addAction('', function () {
	return $this->empty();
});

$meta->addArgument('search');

$meta->addFunction('int', function ($int) {
	return (int) $int;
});
$meta->addFunction('notempty', function ($val, $pname) {
	if (!$val) return $this->fail('meta.required', $pname);
});
$meta->addArgument('group_id', ['int','notempty']);


include('actions.php');


return $meta->init([
	'name'=>'showcase',
	'base'=>'-showcase/api2/'
]);