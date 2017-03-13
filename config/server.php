<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-27
 * Time: 11:37
 */

return [
    'debug' => true,
    'name' => 'slim',
    'pid_file' => PROJECT_PATH . '/examples/suite_server.pid',
    'log_service' => [
        'name'     => 'slim_server',
        'basePath' => PROJECT_PATH . '/examples/logs/suite_server',
        'logThreshold' => 0,
    ],
    'auto_reload' => [
        'enable' =>  true, // will create a process auto reload server
        'dirs' => 'src,config',
    ],
    // main server
    'main_server' => [
        'type' => 'ws', // http https tcp udp ws wss

        'event_handler' => \inhere\server\handlers\WSServerHandler::class,
        'event_list' => [ 'onRequest' ]
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
        'dispatch_mode' => 1,
        'log_file' => PROJECT_PATH . '/examples/logs/slim_server_swoole.log',
    ]
];
