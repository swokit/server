<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-17
 * Time: 9:27
 */

namespace Inhere\Server\Listener\Server;

/**
 * Class ServerEvents
 * @package Inhere\Server\Listener\Server
 */
final class ServerEvents
{
    /************************************************************
     * some events
     ************************************************************/

    // # 1. start ...
    const ON_BEFORE_RUN = 'beforeRun';
    const ON_BOOTSTRAP = 'bootstrap';

    const ON_SERVER_CREATE = 'serverCreate';
    const ON_SERVER_CREATED = 'serverCreated';

    // ## 1.1 user process ...
    const ON_PROCESS_CREATE = 'processCreate';
    const ON_PROCESS_CREATED = 'processCreated';
    const ON_PROCESS_STARTED = 'processStarted';

    // ## 1.2 port ...
    const ON_PORT_CREATE = 'portCreate';
    const ON_PORT_CREATED = 'portCreated';

    const ON_BOOTSTRAPPED = 'bootstrapped';
    const ON_SERVER_START = 'serverStart';

    // # 2. running ...
    // # 2.1 manager running ...
    const ON_MANAGER_STARTED = 'managerStarted';
    const ON_MANAGER_STOPPED = 'managerStopped';

    // # 2.2 worker running ...
    const ON_WORKER_STARTED = 'workerStarted';
    const ON_WORKER_STOPPED = 'workerStopped';
}
