<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/26
 * Time: 下午11:38
 */

namespace inhere\server\clients;

use Swoole\Http\Client;

/**
 * Class HttpClient
 * @package inhere\server\clients
 */
class HttpClient
{
    /**
     * @param string $host
     * @param int $port
     * @return Client
     */
    public static function make($host = '127.0.0.1', $port = 80)
    {
        return new Client($host, $port);
    }
}