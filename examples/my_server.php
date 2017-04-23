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

// you can move config to a independent file.
// $config = require PROJECT_PATH . '/config/server.php';
$config = [
    'debug' => true,
    'name' => 'slim',
    'pid_file' => PROJECT_PATH . '/temp/suite_server.pid',
    'log_service' => [
        'name'     => 'slim_server',
        'basePath' => PROJECT_PATH . '/temp/logs/suite_server',
        'logThreshold' => 0,
    ],
    'auto_reload' => 'src,config',

    // for current main server/ outside extend server.
    'options' => [

    ],

    // main server
    'main_server' => [
        'type' => 'ws', // http https tcp udp ws wss
        'port' => 9501,

        'extend_handler' => \inhere\server\extend\WSServerHandler::class,
        'extend_events' => [ 'onRequest' ]
    ],

    // attach port server by config
    'attach_servers' => [
        'test0' => [
            'host' => '0.0.0.0',
            'port' => '9761',
            'type' => 'udp', //
            // must setting the handler class in config.
            'event_handler' => \inhere\server\handlers\UdpListenHandler::class,
            'event_list' => 'onPacket',
        ]
    ],

    'swoole' => [
        'user'    => 'www-data',
        'worker_num'    => 4,
        'task_worker_num' => 2,
        'daemonize'     => false,
        'max_request'   => 10000,
        // 'log_file' => PROJECT_PATH . '/temp/logs/slim_server_swoole.log',
    ]
];

$mgr = new SuiteServer($config);

SuiteServer::run();
