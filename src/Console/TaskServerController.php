<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-25
 * Time: 10:58
 */

namespace Inhere\Server\Console\Controllers;

use Inhere\Server\BuiltIn\TaskServer;

/**
 * Class TaskServerController
 * @package App\Console\Controllers
 */
class TaskServerController extends ServerController
{
    protected static $name = 'taskServer';
    protected static $description = 'some operation for application task server';

    /**
     * @return TaskServer|mixed
     */
    protected function createServer()
    {
        $config = require BASE_PATH . '/config/server/task.php';

        return new TaskServer($config);
    }
}
