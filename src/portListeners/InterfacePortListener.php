<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server\portListeners;

use inhere\server\AbstractServer;

/**
 * Class InterfacePortListener
 * @package inhere\server\portListeners
 */
interface InterfacePortListener
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
