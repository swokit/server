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
 * Class IExtendServer
 * @package inhere\server\interfaces
 */
interface IExtendServer
{
    /**
     * @param AServerManager $mgr
     */
    public function setMgr(AServerManager $mgr);

    /**
     * @param null $key
     * @param null $default
     * @return \inhere\library\collections\Config|mixed
     */
    public function getConfig($key = null, $default = null);

    public function getOptions();

    public function setOptions($options, $merge = false);
}
