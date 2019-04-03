<?php
use infrajs\load\Load;
use infrajs\rest\Rest;

$data = Load::loadJSON('~showcase.json');


return Rest::parse('-showcase/index.tpl');