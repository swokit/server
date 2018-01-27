<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-17
 * Time: 9:27
 */

namespace Inhere\Server\Listener\Server;

use Inhere\Server\Server;

/**
 * Interface ServerListenerInterface
 * @package Inhere\Server\Listener\Server
 */
interface ServerListenerInterface
{
    /**
     * @param Server $server
     */
    public function handle(Server $server): void;
}
