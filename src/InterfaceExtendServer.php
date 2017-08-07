<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-22
 * Time: 9:21
 */

namespace inhere\server;

/**
 * Interface InterfaceExtendServer
 * @package inhere\server
 */
interface InterfaceExtendServer
{
    public function setMgr(InterfaceServer $mgr);

    public function init(InterfaceServer $mgr);
}
