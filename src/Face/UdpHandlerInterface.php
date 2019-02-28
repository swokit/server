<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-12-27
 * Time: 23:48
 */

namespace Swokit\Server\Face;

use Swoole\Server;

/**
 * Interface UdpHandlerInterface
 * @package Swokit\Server\Face
 */
interface UdpHandlerInterface
{
    /**
     * handle receive data on swoole event: onPacket
     * @param Server $server
     * @param string $data
     * @param array  $client
     * @return mixed
     */
    public function onPacket(Server $server, string $data, array $client);

}
