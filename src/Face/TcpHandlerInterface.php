<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-12-27
 * Time: 23:34
 */

namespace Swokit\Server\Face;

use Swoole\Server;

/**
 * Interface TcpHandlerInterface
 * @package Swokit\Server\Face
 */
interface TcpHandlerInterface
{
    /**
     * handle logic on swoole event: onConnect
     * @param Server $server
     * @param int $fd
     * @param int $fromId
     * @return mixed
     */
    public function onConnect(Server $server, int $fd, int $fromId);

    /**
     * handle receive data on swoole event: onReceive
     * @param Server $server
     * @param int $fd
     * @param int $fromId
     * @param $data
     * @return mixed
     */
    public function onReceive(Server $server, int $fd, int $fromId, $data);

    /**
     * handle receive data on swoole event: onClose
     * @param Server $server
     * @param int $fd
     * @return mixed
     */
    public function onClose(Server $server, int $fd);
}
