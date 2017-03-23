<?php
/**
 * create http server by swoole.
 * RUN: `php bin/suit_server.php` see more
 */
define('PROJECT_PATH', dirname(__DIR__));

require dirname(__DIR__) . '/../../autoload.php';

use inhere\server\SuiteServer;

date_default_timezone_set('Asia/Chongqing');

$config = [
    'debug' => true,
    'name' => 'suite',
    'pid_file' => PROJECT_PATH . '/temp/suite_server.pid',
    'log_service' => [
        'name'     => 'suite_server',
        'basePath' => PROJECT_PATH . '/temp/logs/suite_server',
        'logThreshold' => 0,
    ],

    // main server
    'main_server' => [
        'type' => 'ws', // http https tcp udp ws wss

        'event_handler' => '\inhere\server\handlers\WSServerHandler',
        'event_list' => ['onRequest']
    ],

    // attach port server by config
    'attach_servers' => [
        'test0' => [
            'host' => '0.0.0.0',
            'port' => '9761',
            'type' => 'udp', //
            // must setting the handler class in config.
            'event_handler' => '\inhere\server\handlers\UdpListenHandler',
            'event_list' => 'onPacket',
        ]
    ],

    'swoole' => [
        'user'    => 'www-data',
        'worker_num'    => 4,
        'task_worker_num' => 2,
        'daemonize'     => 0,
        'max_request'   => 10000,
        'dispatch_mode' => 1,
        'log_file' => PROJECT_PATH . '/temp/logs/suite_server_swoole.log',
    ]
];

$mgr = new SuiteServer($config);

//
// create attach listen port server
//

// use array
$mgr->attachListenServer('test1', [
    'host' => '0.0.0.0',
    'port' => '9762',
    'type' => 'udp', //
    'event_handler' => \inhere\server\handlers\UdpListen::class,
]);

// can also use Closure
$mgr->attachListenServer('test2', function(\Swoole\Server $srv, $mgr)
{
    $port = $srv->listen('0.0.0.0','9763', SWOOLE_SOCK_TCP);
    $port->set([]);

    // custom add event
    $handler = new \inhere\server\handlers\TcpListen();
    $handler->setMgr($mgr);
    $port->on('Receive', [$handler, 'onReceive']);

    return $port;
});

//
// register our application
//

/*
$mgr->createApplication = function($mgr) {
    // 实例化slim对象
    return require PROJECT_PATH . '/bootstrap/app_for_sw.php';
};

$mgr->requestHandler = function (SuiteServer $mgr, \Swoole\Http\Request $request, \Swoole\Http\Response $response)
{
    $ioc = $mgr->app->container;

    $serverData = array_change_key_case($request->server, CASE_UPPER);
    $ioc['environment'] = new \Slim\Http\Environment($serverData);

    // Run app
    // 重新创建相关信息对象
    $rq = $ioc->raw('request');
    $rs = $ioc->raw('response');
    $resp = $mgr->app->process( $rq($ioc), $rs($ioc) );

    // @from \Slim\App::respond()
    // Headers
    foreach ($resp->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            $response->header($name, $value);
        }
    }

    return (string)$resp->getBody();
};
*/

SuiteServer::run();
