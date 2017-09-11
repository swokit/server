<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-22
 * Time: 9:21
 */

namespace Inhere\Server;

use inhere\library\traits\OptionsTrait;

/**
 * Interface AbstractExtendServer
 * @package Inhere\Server
 *
 * @method mixed log($msg, array $data = [], $type = 'info')
 */
abstract class AbstractExtendServer implements InterfaceExtendServer
{
    use OptionsTrait;

    /**
     * @var InterfaceServer
     */
    protected $mgr;

    /**
     * Object constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if ($options) {
            $this->setOptions($options);
        }

        $this->init();
    }

    public function setMgr(InterfaceServer $mgr)
    {
        $this->mgr = $mgr;
    }

    protected function init()
    {
    }

    public function __call($method, array $args = [])
    {
        if (method_exists($this->mgr, $method)) {
            return $this->mgr->$method(...$args);
        }

        throw new \RuntimeException("Call the method [$method] not exists");
    }
}
