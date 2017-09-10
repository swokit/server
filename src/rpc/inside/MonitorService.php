<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/10
 * Time: 上午9:45
 */

namespace inhere\server\rpc\inside;

use inhere\server\rpc\ServiceInterface;

/**
 * Class MonitorService
 * @package inhere\server\rpc\inside
 */
class MonitorService implements ServiceInterface
{
    public function services()
    {
        return [
            'name' => 'config',
            'name1' => 'config',
        ];
    }
}
