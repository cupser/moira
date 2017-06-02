<?php

define("APP_PATH", realpath(dirname(__FILE__) . '/../')); /* 指向public的上一级 */

require_once APP_PATH . '/application/constant.php';
require_once APP_PATH . '/application/function/function.php';
require_once APP_PATH . '/application/errorCode.php';
require_once 'redis_key.php';


$app = new Yaf\Application(APP_PATH . "/conf/application.ini");

define('DEBUG', boolval($app->getConfig()->get('application.debug')));

$app->bootstrap()->run();
