<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-22
 * Time: 9:21
 */

namespace Swokit\Server;

use Monolog\Logger;

/**
 * Interface ServerInterface
 * @package Swokit\Server
 * @property \Swoole\Server|\Swoole\Websocket\Server $server
 */
interface ServerInterface
{
    public const VERSION = '0.1.1';

    public const UPDATE_TIME = '2017-02-17';

    // 运行模式
    // SWOOLE_PROCESS 业务代码在Worker进程中执行
    // SWOOLE_BASE    业务代码在Reactor进程中直接执行
    public const MODE_BASE = 'base';
    public const MODE_PROCESS = 'process';

    /**
     * the main server allow socket protocol type:
     * tcp udp http https(http + ssl) ws wss(webSocket + ssl)
     */
    public const PROTOCOL_TCP = 'tcp';
    public const PROTOCOL_UDP = 'udp';
    public const PROTOCOL_HTTP = 'http';
    public const PROTOCOL_HTTPS = 'https';
    public const PROTOCOL_RDS = 'rds';  // redis
    public const PROTOCOL_WS = 'ws';  // webSocket
    public const PROTOCOL_WSS = 'wss'; // webSocket ssl

    /**
     * @var array
     */
    public const SWOOLE_EVENTS = [
        // basic
        'start', 'shutdown', 'workerStart', 'workerStop', 'workerExit', 'workerError', 'managerStart', 'managerStop',
        // special
        'pipeMessage', 'bufferFull', 'bufferEmpty',
        // tcp/udp
        'connect', 'receive', 'packet', 'close',
        // task
        'task', 'finish',
        // http server
        'request',
        // webSocket server
        'message', 'open', 'handShake'
    ];

//    public static function run(array $config = [], $start = true);

//    public function bootstrap($start = true);

    public function start();

    /**
     * record log message
     * @param string $msg
     * @param array $data
     * @param int $level
     * @return void
     */
    public function log(string $msg, array $data = [], $level = Logger::INFO): void;

    /**
     * @return array
     */
    public function getSupportedProtocols(): array;

    /**
     * @return array
     */
    public function getConfig(): array;

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function config(string $key, $default = null);
}
