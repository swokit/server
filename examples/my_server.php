<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-27
 * Time: 11:36
 */

define('PROJECT_PATH', dirname(__DIR__));

require dirname(__DIR__) . '/../../autoload.php';

use inhere\server\SuiteServer;


$config = require PROJECT_PATH . '/config/server.php';

$mgr = new SuiteServer($config);


SuiteServer::run();
