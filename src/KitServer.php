<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 12:41
 */

namespace Swokit\Server;

use Inhere\Console\Utils\Show;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Swokit\Server\Component\FileLogHandler;
use Swoole\Coroutine;

/**
 * Class KitServer - Generic Server Kit
 * @package Swokit\Server
 * Running processes:
 *
 * ```
 * ```
 *
 */
class KitServer extends BaseServer
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

    public function info(): void
    {
        $this->showInformation();
    }

    public function status(): void
    {
        $this->showRuntimeStatus();
    }

    /**
     * Show server info
     */
    protected function showInformation(): void
    {
        $swOpts = $this->swooleSettings;
        $settings = $this->serverSettings;
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
                'type' => $settings['type'],
                'mode' => $settings['mode'],
                'host' => $settings['host'],
                'port' => $settings['port'],
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
    protected function showRuntimeStatus(): void
    {
        Show::notice('Sorry, The function un-completed!', 0);
    }
}
