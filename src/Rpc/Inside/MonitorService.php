<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/10
 * Time: 上午9:45
 */

namespace Inhere\Server\Rpc\Inside;

use Inhere\Server\Rpc\ServiceInterface;

/**
 * Class MonitorService
 * @package Inhere\Server\Rpc\Inside
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
