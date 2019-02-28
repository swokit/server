<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/6 0006
 * Time: 23:01
 */

namespace Swokit\Server\Listener\Server;

/**
 * Class AbstractListener
 * @package Swokit\Server\Listener\Server
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
    public function setParams(array $params): void
    {
        $this->params = $params;
    }
}
