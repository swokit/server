<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018/9/20
 * Time: 上午11:26
 */

require dirname(__DIR__) . '/test/boot.php';

$tcp = new \SwoKit\Server\TcpServer([
    'debug' => true,
    'name' => 'demo-tcp',
    'rootPath' => __DIR__,
    'pidFile' => __DIR__ . '/logs/demo-tcp.pid',
    'server' => [
        'port' => 12091
    ],
    'swoole' => [
        'log_file' => __DIR__ . '/logs/swoole_tcp_server.log',
    ],
]);

$tcp->addProcess('hot-reload', \SwoKit\Server\Func\fileWatcherProcess(__DIR__. '/logs/file-change.key',[__DIR__ . '/example']));

$tcp->start();
