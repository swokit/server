<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-22
 * Time: 9:21
 */

namespace inhere\server\interfaces;

/**
 * Interface IServerManager
 * @package inhere\server\interfaces
 */
interface IServerManager
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
    const PROTOCOL_TCP  = 'tcp';
    const PROTOCOL_UDP  = 'udp';
    const PROTOCOL_HTTP = 'http';
    const PROTOCOL_HTTPS = 'https';
    const PROTOCOL_WS    = 'ws';  // webSocket
    const PROTOCOL_WSS   = 'wss'; // webSocket ssl

    public static function run($config = [], $start = true);

    public function bootstrap($start = true);

    public function start();

     /**
     * @return array
     */
    public function getSupportedProtocols();
}
