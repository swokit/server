<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-27
 * Time: 11:36
 */

define('PROJECT_PATH', dirname(__DIR__));

require dirname(__DIR__) . '/test/boot.php';

// you can move config to a independent file.
// $config = require PROJECT_PATH . '/config/server.php';
$config = [
    'debug' => true,
    'name' => 'demo',
    'pidFile' => __DIR__ . '/logs/test_server.pid',

    'logger' => [
        'name' => 'slim_server',
        'basePath' => __DIR__ . '/logs/test_server',
        'logThreshold' => 0,
    ],

    'auto_reload' => 'src,config',

    // for current main server/ outside extend server.
    'options' => [

    ],

    // main server
    'server' => [
        'type' => 'tcp', // http https tcp udp ws wss rds
        'port' => 9501,
    ],

    // attach port server by config
    'ports' => [
        'port1' => [
            'host' => '0.0.0.0',
            'port' => '9761',
            'type' => 'udp',

            // must setting the handler class in config.
            'listener' => \Swokit\Server\Listener\Port\UdpListener::class,
        ]
    ],

    'swoole' => [
        'user' => 'www-data',
        'worker_num' => 4,
        'task_worker_num' => 2,
        'daemonize' => false,
        'max_request' => 10000,
        // 'log_file' => PROJECT_PATH . '/temp/logs/slim_server_swoole.log',
    ]
];

// $mgr = new \Swokit\Server\Extend\WebSocketServer($config);
$mgr = new \Swokit\Server\KitServer($config);

$mgr->attachListener('port2', new \Swokit\Server\Listener\Port\UdpListener([
    'host' => '0.0.0.0',
    'port' => '9762',
]));

try {
    $mgr->run();
} catch (Throwable $e) {
    var_dump($e);
}
