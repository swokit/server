<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/26
 * Time: 下午11:40
 */

namespace inhere\server\clients;

use inhere\exceptions\ConnectionException;
use Swoole\Client;

/**
 * Class TcpClient
 * @package inhere\server\clients
 */
class TcpClient
{
    /**
     * @param string $host
     * @param int $port
     * @param array $options
     * @return Client
     * @throws ConnectionException
     */
    public static function make($host = '127.0.0.1', $port = 80, array $options = [])
    {
        // enable async
        // $async = ($options['async'] ?? false) ? SWOOLE_SOCK_ASYNC : null;

        $client = new Client(SWOOLE_SOCK_TCP);

        if (!$client->connect($host, $port, -1)) {
            throw new ConnectionException("connect failed. Error: {$client->errCode}\n");
        }

        return $client;
    }
}