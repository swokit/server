<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-19
 * Time: 10:20
 */

namespace Swokit\Server\Console\Controllers;

use Inhere\Console\Controller;

/**
 * Class ServerController
 * @package App\Console\Controllers
 */
class ServerController extends Controller
{
    protected static $name = 'server';
    protected static $description = 'some operation for application server';

    public static function commandAliases(): array
    {
        return [
            'conf' => 'config',
        ];
    }

    /**
     * @return \Swokit\Server\Server|mixed
     */
    protected function createServer()
    {
        // $config = require BASE_PATH . '/config/server/app.php';
        // return new HttpServer($config);
        throw new \RuntimeException('Please create server in sub-class');
    }

    /**
     * start the application server
     * @options
     *  -d, --daemon  run app server on the background
     * @throws \Throwable
     */
    public function startCommand()
    {
        $daemon = $this->getSameOpt(['d', 'daemon']);

        $this->createServer()->asDaemon($daemon)->start();
    }

    /**
     * restart the application server
     * @options
     *  -d, --daemon  run app server on the background
     * @throws \Throwable
     */
    public function restartCommand()
    {
        $daemon = $this->input->getSameOpt(['d', 'daemon']);

        $this->createServer()->asDaemon($daemon)->restart();
    }

    /**
     * reload the application server workers
     * @options
     *  -t, --task BOOL    Only reload task worker
     */
    public function reloadCommand()
    {
        $onlyTask = $this->input->getSameOpt(['task']);

        $this->createServer()->reload($onlyTask);
    }

    /**
     * stop the swoole application server
     */
    public function stopCommand()
    {
        $this->createServer()->stop();
    }
}
