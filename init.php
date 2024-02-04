<?php

require_once('Record.php');
require_once('MySqlException.php');
require_once('Find.php');
require_once('Product.php');

$config = require_once('config.php');

try {
    Record::setConnection(new \mysqli($config['host'], $config['login'], $config['password'], $config['name']));
} catch (\Exception $e) {
    throw new MySqlException($e->getMessage() . ' Please configure mysql settings in config.php');
}

Record::q("SET NAMES '". $config['encode'] . "'");
