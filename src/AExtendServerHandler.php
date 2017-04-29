<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server;

use inhere\server\interfaces\IExtendServer;
use inhere\library\traits\TraitUseOption;

/**
 * Class AExtendServerHandler
 * @package inhere\server\handlers
 */
abstract class AExtendServerHandler implements IExtendServer
{
    use TraitUseOption;

    /**
     * @var AServerManager
     */
    protected $mgr;

    /**
     * options
     * @var array
     */
    protected $options = [];

    /**
     * AbstractServerHandler constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if ($options) {
            $this->setOptions($options);
        }
    }

    public function initCompleted()
    {
        // ... ...
    }

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
     * @return \inhere\library\collections\Config|mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if (null === $key) {
            return $this->mgr->getConfig();
        }

        return $this->mgr->getConfig()->get($key, $default);
    }

    /**
     * output log message
     * @see AServerManager::log()
     * @param  string $msg
     * @param  array $data
     * @param string $type
     */
    public function log($msg, $data = [], $type = 'debug')
    {
        $this->mgr->log($msg, $data, $type);
    }

    /**
     * getCliOut
     * @return \inhere\console\io\Output
     */
    public function getCliOut()
    {
        return $this->mgr->getCliOut();
    }

    /**
     * getCliOut
     * @return \inhere\console\io\Input
     */
    public function getCliIn()
    {
        return $this->mgr->getCliIn();
    }
}
