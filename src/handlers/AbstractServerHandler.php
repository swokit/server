<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server\handlers;

use inhere\server\AServerManager;
use inhere\server\interfaces\IServerHandler;
use Swoole\Server as SwServer;

/**
 * Class AbstractServerHandler
 * @package inhere\server\handlers
 */
abstract class AbstractServerHandler implements IServerHandler
{
    /**
     * @var AServerManager
     */
    protected $mgr;

    /**
     * @param AServerManager $mgr
     */
    public function setMgr(AServerManager $mgr)
    {
        $this->mgr = $mgr;
    }

    /**
     * @param null $key
     * @param null $default
     * @return \inhere\librarys\collections\Config|mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if ( null === $key ) {
            return $this->mgr->getConfig();
        }

        return $this->mgr->getConfig()->get($key, $default);
    }

    /**
     * output debug message
     * @see AServerManager::addLog()
     * @param  string $msg
     * @param  array $data
     * @param string $type
     */
    public function addLog($msg, $data = [], $type = 'debug')
    {
        $this->mgr->addLog($msg, $data, $type);
    }
}
