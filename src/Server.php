<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 12:41
 */

namespace SwoKit\Server;

use Inhere\Console\Utils\Show;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SwoKit\Server\Component\FileLogHandler;
use Swoole\Coroutine;

/**
 * Class Server - Generic Server
 * @package SwoKit\Server
 * Running processes:
 *
 * ```
 * ```
 */
class Server extends AbstractServer
{
    /**
     * @param array $opts
     * @return mixed|LoggerInterface
     */
    protected function makeLogger(array $opts)
    {
        $fileHandler = new FileLogHandler($opts['file'], (int)$opts['level'], (int)$opts['splitType']);
        $mainHandler = new FingersCrossedHandler($fileHandler, (int)$opts['level'], (int)$opts['bufferSize']);
        $fileHandler->setServer($this);

        $logger = new Logger($opts['name'] ?? 'server');
        $logger->pushHandler($mainHandler);

        return $logger;
    }

    /*******************************************************************************
     * start server logic
     ******************************************************************************/

    public function info()
    {
        $this->showInformation();
    }

    public function status()
    {
        $this->showRuntimeStatus();
    }

    /**
     * Show server info
     */
    protected function showInformation()
    {
        $swOpts = $this->swooleSettings;
        $main = $this->serverSettings;
        $panelData = [
            'System Info' => [
                'PHP Version' => PHP_VERSION,
                'Operate System' => PHP_OS,
            ],
            'Swoole Info' => [
                'version' => SWOOLE_VERSION,
                'coroutine' => class_exists(Coroutine::class, false),
            ],
            'Swoole Config' => [
                'dispatch_mode' => $swOpts['dispatch_mode'],
                'worker_num' => $swOpts['worker_num'],
                'task_worker_num' => $swOpts['task_worker_num'],
                'max_request' => $swOpts['max_request'],
            ],
            'Main Server' => [
                'type' => $main['type'],
                'mode' => $main['mode'],
                'host' => $main['host'],
                'port' => $main['port'],
                'class' => static::class,
            ],
            'Project Config' => [
                'name' => $this->name,
                'path' => $this->config['rootPath'],
                'auto_reload' => $this->config['auto_reload'],
                'pidFile' => $this->config['pidFile'],
            ],
            'Server Log' => $this->config['log'],
        ];

        // 'Server Information'
        Show::mList($panelData, [
            'ucfirst' => false,
        ]);
        // Show::panel($panelData, 'Server Information');
    }

    /**
     * show server runtime status information
     */
    protected function showRuntimeStatus()
    {
        Show::notice('Sorry, The function un-completed!', 0);
    }
}
