<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server\portListeners;

use inhere\library\traits\OptionsTrait;
use inhere\server\AbstractServer;

/**
 * Class BaseListener
 * @package inhere\server\portListeners
 */
abstract class PortListener implements InterfacePortListener
{
    use OptionsTrait;

    /**
     * @var AbstractServer
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

    /**
     * @param AbstractServer $mgr
     */
    public function setMgr(AbstractServer $mgr)
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

        return $this->mgr->getValue($key, $default);
    }

    /**
     * output log message
     * @see AbstractServer::log()
     * @param  string $msg
     * @param  array $data
     * @param string $type
     */
    public function log($msg, array $data = [], $type = 'debug')
    {
        $this->mgr->log($msg, $data, $type);
    }
}
