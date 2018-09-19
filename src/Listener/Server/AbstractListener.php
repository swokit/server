<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/6 0006
 * Time: 23:01
 */

namespace SwoKit\Server\Listener\Server;

/**
 * Class AbstractListener
 * @package SwoKit\Server\Listener\Server
 */
abstract class AbstractListener implements ServerListenerInterface
{
    /** @var array Event params */
    protected $params = [];

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }
}
