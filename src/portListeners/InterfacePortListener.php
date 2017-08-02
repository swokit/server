<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server\portListeners;

use inhere\server\InterfaceServer;
use Swoole\Server;

/**
 * Class InterfacePortListener
 * @package inhere\server\portListeners
 */
interface InterfacePortListener
{

    /**
     * @param InterfaceServer $mgr
     * @param Server $server
     * @return \Swoole\Server\Port
     */
    public function init(InterfaceServer $mgr, Server $server);

    /**
     * @param Server $server
     * @return Server\Port
     */
    public function createPortServer(Server $server);

    /**
     * @param InterfaceServer $mgr
     */
    public function setMgr(InterfaceServer $mgr);

    /**
     * @param null $key
     * @param null $default
     * @return \inhere\library\collections\Config|mixed
     */
    public function getConfig($key = null, $default = null);

    public function getOptions();

    public function setOptions(array $options, $merge = false);
}
