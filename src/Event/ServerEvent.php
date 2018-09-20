<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-17
 * Time: 9:27
 */

namespace SwoKit\Server\Event;

/**
 * Class ServerEvents
 * @package SwoKit\Server\Event
 */
final class ServerEvent
{
    /************************************************************
     * some events
     ************************************************************/

    // # 1. start ...
    const BEFORE_RUN = 'beforeRun';
    const BEFORE_BOOTSTRAP = 'beforeBootstrap';

    const BEFORE_SERVER_CREATE = 'beforeServerCreate';
    const SERVER_CREATED = 'serverCreated';

    // ## 1.1 user process ...
    const BEFORE_PROCESS_CREATE = 'beforeProcessCreate';
    const PROCESS_CREATED = 'processCreated';
    const PROCESS_STARTED = 'processStarted';

    // ## 1.2 port ...
    const BEFORE_PORT_CREATE = 'beforePortCreate';
    const PORT_CREATED = 'portCreated';

    const BOOTSTRAPPED = 'bootstrapped';
    const BEFORE_SWOOLE_START = 'beforeSwooleStart';

    // # 2. running ...
    // # 2.1 master/manager running ...
    const STARTED = 'started';
    const SHUTDOWN = 'shutdown';
    const MANAGER_STARTED = 'managerStarted';
    const MANAGER_STOPPED = 'managerStopped';

    // # 2.2 worker running ...
    const WORKER_STARTED = 'workerStarted';
    const TASK_PROCESS_STARTED = 'taskProcessStarted';
    const WORK_PROCESS_STARTED = 'workProcessStarted';
    const WORKER_ERROR = 'workerError';
    const WORKER_STOPPED = 'workerStopped';
    const WORKER_EXITED = 'workerExited';
}
