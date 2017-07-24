<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server\interfaces;

use inhere\server\AbstractServer;

/**
 * Class IPortListenHandler
 * @package inhere\server\interfaces
 */
interface IPortListenHandler
{
    /**
     * @param AbstractServer $mgr
     */
    public function setMgr(AbstractServer $mgr);

    /**
     * @param null $key
     * @param null $default
     * @return \inhere\library\collections\Config|mixed
     */
    public function getConfig($key = null, $default = null);

    public function getOptions();

    public function setOptions(array $options, $merge = false);
}
