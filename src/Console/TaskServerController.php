<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-25
 * Time: 10:58
 */

namespace Swokit\Server\Console\Controllers;

use Swokit\Server\RedisServer;

/**
 * Class TaskServerController
 * @package App\Console\Controllers
 */
class TaskServerController extends ServerController
{
    protected static $name = 'taskServer';
    protected static $description = 'some operation for application task server';

    /**
     * @return RedisServer|mixed
     */
    protected function createServer()
    {
        $config = require BASE_PATH . '/config/server/task.php';

        return new RedisServer($config);
    }
}
