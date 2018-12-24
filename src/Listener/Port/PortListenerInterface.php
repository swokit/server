<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace Swokit\Server\Listener\Port;

use Swokit\Server\ServerInterface;
use Swoole\Server;

/**
 * Class PortListenerInterface
 * @package Swokit\Server\Listener\Port
 */
interface PortListenerInterface
{
    /**
     * @param ServerInterface $mgr
     * @param Server $server
     * @return \Swoole\Server\Port
     */
    public function attachTo(ServerInterface $mgr, Server $server);

    /**
     * @param Server $server
     * @return Server\Port
     */
    public function createPortServer(Server $server);

    /**
     * @param ServerInterface $mgr
     */
    public function setMgr(ServerInterface $mgr);

    /**
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function getConfig($key = null, $default = null);

    public function getOptions();

    public function setOptions(array $options, $merge = false);
}
