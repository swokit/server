<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-17
 * Time: 9:27
 */

namespace Swokit\Server\Listener\Server;

use Swoole\Server;

/**
 * Interface ServerListenerInterface
 * @package Swokit\Server\Listener\Server
 */
interface ServerListenerInterface
{
    /**
     * @param Server $server
     * @param array  $params
     */
    public function handle(Server $server, ...$params);
}
