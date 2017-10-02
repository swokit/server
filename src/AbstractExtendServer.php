<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-22
 * Time: 9:21
 */

namespace Inhere\Server;

use Inhere\Library\Traits\OptionsTrait;

/**
 * Interface AbstractExtendServer
 * @package Inhere\Server
 * @method mixed log($msg, array $data = [], $type = 200)
 */
abstract class AbstractExtendServer implements ExtendServerInterface
{
    use OptionsTrait;

    /**
     * @var ServerInterface
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

    public function setMgr(ServerInterface $mgr)
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
