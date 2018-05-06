<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/1 0001
 * Time: 01:05
 */

namespace Inhere\Server;

use Inhere\Server\Event\SwooleEvent;

/**
 * Class GeneralServer
 * @package Inhere\Server
 */
class GeneralServer extends AbstractServer
{
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
        $swOpts = $this->config['swoole'];
        $main = $this->config['main_server'];
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
