<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-17
 * Time: 9:27
 */

namespace Swokit\Server\Event;

/**
 * Class ServerEvents
 * @package Swokit\Server\Event
 */
final class ServerEvent
{
    /************************************************************
     * some events
     ************************************************************/

    // # 1. start ...
    public const BEFORE_RUN       = 'beforeRun';
    public const BEFORE_BOOTSTRAP = 'beforeBootstrap';

    public const SERVER_CREATE  = 'server.create';
    public const SERVER_CREATED = 'server.created';

    // ## 1.1 user process ...
    public const PROCESS_CREATE  = 'process.create';
    public const PROCESS_CREATED = 'process.created';
    public const PROCESS_STARTED = 'process.started';

    // ## 1.2 port ...
    public const PORT_CREATE  = 'port.create';
    public const PORT_CREATED = 'port.created';

    public const BOOTSTRAPPED = 'bootstrapped';
    public const SWOOLE_START = 'swoole.start';

    // # 2. running ...
    // # 2.1 master/manager running ...
    public const STARTED         = 'master.started';
    public const SHUTDOWN        = 'master.shutdown';
    public const MANAGER_STARTED = 'manager.started';
    public const MANAGER_STOPPED = 'manager.stopped';

    // # 2.2 worker running ...
    public const WORKER_STARTED       = 'worker.started';
    public const TASK_PROCESS_STARTED = 'taskProcess.started';
    public const WORK_PROCESS_STARTED = 'workProcess.started';
    public const WORKER_ERROR         = 'worker.error';
    public const WORKER_EXITED        = 'worker.exited';
    public const WORKER_STOPPED       = 'worker.stopped';
}
