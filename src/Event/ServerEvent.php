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
    public const BEFORE_RUN = 'beforeRun';
    public const BEFORE_BOOTSTRAP = 'beforeBootstrap';

    public const BEFORE_SERVER_CREATE = 'beforeServerCreate';
    public const SERVER_CREATED = 'serverCreated';

    // ## 1.1 user process ...
    public const BEFORE_PROCESS_CREATE = 'beforeProcessCreate';
    public const PROCESS_CREATED = 'processCreated';
    public const PROCESS_STARTED = 'processStarted';

    // ## 1.2 port ...
    public const BEFORE_PORT_CREATE = 'beforePortCreate';
    public const PORT_CREATED = 'portCreated';

    public const BOOTSTRAPPED = 'bootstrapped';
    public const BEFORE_SWOOLE_START = 'beforeSwooleStart';

    // # 2. running ...
    // # 2.1 master/manager running ...
    public const STARTED = 'started';
    public const SHUTDOWN = 'shutdown';
    public const MANAGER_STARTED = 'managerStarted';
    public const MANAGER_STOPPED = 'managerStopped';

    // # 2.2 worker running ...
    public const WORKER_STARTED = 'workerStarted';
    public const TASK_PROCESS_STARTED = 'taskProcessStarted';
    public const WORK_PROCESS_STARTED = 'workProcessStarted';
    public const WORKER_ERROR = 'workerError';
    public const WORKER_STOPPED = 'workerStopped';
    public const WORKER_EXITED = 'workerExited';
}
