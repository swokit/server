<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server\interfaces;

use inhere\server\AServerManager;
use Swoole\Server as SwServer;

/**
 * Class IServerHandler
 * @package inhere\server\interfaces
 */
interface IServerHandler
{
    /**
     * @param AServerManager $mgr
     */
    public function setMgr(AServerManager $mgr);

    /**
     * @param null $key
     * @param null $default
     * @return \inhere\librarys\collections\Config|mixed
     */
    public function getConfig($key = null, $default = null);
}
