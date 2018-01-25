<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-19
 * Time: 10:20
 */

namespace Inhere\Server\Console\Controllers;

use App\Server\AppServer;
use Inhere\Console\Controller;
use Inhere\Console\Utils\CliUtil;

/**
 * Class ServerController
 * @package App\Console\Controllers
 */
class ServerController extends Controller
{
    protected static $name = 'server';
    protected static $description = 'some operation for application server';

    public static function commandAliases()
    {
        return [
            'conf' => 'config',
        ];
    }

    /**
     * start a php built-in server for development
     * @usage
     *  {command} [-S HOST:PORT]
     *  {command} [-H HOST] [-p PORT]
     *  {command} [file=]web/index.php
     * @options
     *  -S STRING           The server address. e.g 127.0.0.1:8552
     *  -t STRING           The document root dir for server(<comment>web</comment>)
     *  -H,--host STRING    The server host address(<comment>127.0.0.1</comment>)
     *  -p,--port INTEGER   The server port address(<comment>8552</comment>)
     * @arguments
     *  file=STRING         The entry file for server. e.g web/index.php
     * @example
     *  {command} -S 127.0.0.1:8552 web/index.php
     */
    public function devCommand()
    {
        if (!$server = $this->getOpt('S')) {
            $server = $this->getSameOpt(['H', 'host'], '127.0.0.1');
        }

        if (!strpos($server, ':')) {
            $port = $this->getSameOpt(['p', 'port'], 8552);
            $server .= ':' . $port;
        }

        $version = PHP_VERSION;
        $workDir = $this->input->getPwd();
        $docDir = $this->getOpt('t');
        $docRoot = $docDir ? $workDir . '/' . $docDir : $workDir;

        $this->write([
            "PHP $version Development Server started\nServer listening on <info>$server</info>",
            "Document root is <comment>$docRoot</comment>",
            'You can use <comment>CTRL + C</comment> to stop run.',
        ]);

        // $command = "php -S {$server} -t web web/index.php";
        $command = "php -S {$server}";

        if ($docDir) {
            $command .= " -t $docDir";
        }

        if ($entryFile = $this->getSameArg(['file', 0])) {
            $command .= " $entryFile";
        }

        $this->write("<cyan>></cyan> <darkGray>$command</darkGray>");

        CliUtil::runCommand($command);
    }

    /**
     * @return AppServer|mixed
     */
    protected function createServer()
    {
        /** @var AppServer $server */
        $config = require BASE_PATH . '/config/server/app.php';

        return new AppServer($config);
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
