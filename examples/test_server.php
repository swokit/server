<?php
/**
 * create http server by swoole.
 * RUN: php bin/http_server.php
 */
define('PROJECT_PATH', dirname(__DIR__));

require dirname(__DIR__) . '/../../autoload.php';

use inhere\server\TcpServer;
use inhere\server\HttpServer;
use inhere\server\WSServer;
use Swoole\Http\Request;
use Swoole\Http\Response;

date_default_timezone_set('Asia/Chongqing');

$daemonize = 0;

$config = [
    'debug' => true,
    'pid_file' => PROJECT_PATH . '/temp/test_server.pid',
    'log_service' => [
        'name' => 'swoole_server',
        'basePath' => PROJECT_PATH . '/temp/logs/test_server',
        'logThreshold' => 0,
    ],

    //
    'tcp_server' => [
        'enable' => 1,
    ],
    'http_server' => [
        'port'   => 9501,
        'type'   => 'http',
        'enable' => 1,
    ],
    'web_socket_server' => [
        'enable' => 1,
    ],

    'swoole' => [
        'user'    => 'www-data',
        'worker_num'    => 4,
        'task_worker_num'    => 2,
        'daemonize'     => $daemonize,
        'max_request'   => 1000,
        'dispatch_mode' => 2,
    ]
];

if ( $daemonize ) {
    $config['swoole']['log_file'] = PROJECT_PATH . '/temp/logs/test_server_swoole.log';
}

$mgr = new WSServer($config);

$mgr->createApplication(function($mgr) {
    // 实例化slim对象
   return require PROJECT_PATH . '/bootstrap/app_for_sw.php';
});

$mgr->handleRequest = function (WSServer $mgr, Request $request, Response $response)
{
    $ioc = $mgr->app->container;

    $serverData = array_change_key_case($request->server, CASE_UPPER);
    $ioc['environment'] = new \Slim\Http\Environment($serverData);

    // Run app
    // 重新创建相关信息对象
    $rq = $ioc->raw('request');
    $rs = $ioc->raw('response');
    $resp = $mgr->app->process( $rq($ioc), $rs($ioc) );

    $response->status($resp->getStatusCode());

    // @from \Slim\App::respond()
    // Headers
    foreach ($resp->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            $response->header($name, $value);
        }
    }

    return (string)$resp->getBody();
};

// TcpServer::run($config);
// HttpServer::run($config);
WSServer::run();
