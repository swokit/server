<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-22
 * Time: 9:21
 */

namespace Inhere\Server;
use Monolog\Logger;

/**
 * Interface ServerInterface
 * @package Inhere\Server
 * @property \Swoole\Server|\Swoole\Websocket\Server $server
 */
interface ServerInterface
{
    const VERSION = '0.1.1';

    const UPDATE_TIME = '2017-02-17';

    // 运行模式
    // SWOOLE_PROCESS 业务代码在Worker进程中执行
    // SWOOLE_BASE    业务代码在Reactor进程中直接执行
    const MODE_BASE = 'base';
    const MODE_PROCESS = 'process';

    /**
     * the main server allow socket protocol type:
     * tcp udp http https(http + ssl) ws wss(webSocket + ssl)
     */
    const PROTOCOL_TCP = 'tcp';
    const PROTOCOL_UDP = 'udp';
    const PROTOCOL_HTTP = 'http';
    const PROTOCOL_HTTPS = 'https';
    const PROTOCOL_WS = 'ws';  // webSocket
    const PROTOCOL_WSS = 'wss'; // webSocket ssl

    /**
     * @var array
     */
    const SWOOLE_EVENTS = [
        // basic
        'start', 'shutdown', 'workerStart', 'workerStop', 'workerError', 'managerStart', 'managerStop',
        // special
        'pipeMessage',
        // tcp/udp
        'connect', 'receive', 'packet', 'close',
        // task
        'task', 'finish',
        // http server
        'request',
        // webSocket server
        'message', 'open', 'handShake'
    ];

    /*******************************
     * some events
     *******************************/
    // # 1. start ...
    const ON_BEFORE_RUN = 'beforeRun';
    const ON_BOOTSTRAP = 'bootstrap';
    const ON_SERVER_CREATE = 'serverCreate';
    const ON_SERVER_CREATED = 'serverCreated';
    // ## 1.1 user process ...
    const ON_PROCESS_CREATE = 'processCreate';
    const ON_PROCESS_CREATED = 'processCreated';
    const ON_PROCESS_STARTED = 'processStarted';
    // ## 1.2 port ...
    const ON_PORT_CREATE = 'portCreate';
    const ON_PORT_CREATED = 'portCreated';

    const ON_BOOTSTRAPPED = 'bootstrapped';
    const ON_SERVER_START = 'serverStart';

    // # 2. running ...
    // # 2.1 manager running ...
    const ON_MANAGER_STARTED = 'managerStarted';
    const ON_MANAGER_STOPPED = 'managerStopped';

    // # 2.2 worker running ...
    const ON_WORKER_STARTED = 'workerStarted';
    const ON_WORKER_STOPPED = 'workerStopped';

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
    public function log($msg, array $data = [], $level = Logger::INFO);

    /**
     * @return array
     */
    public function getSupportedProtocols();

    /**
     * @return array
     */
    public function getConfig();

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getValue(string $key, $default = null);
}
