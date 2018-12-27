<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/1 0001
 * Time: 00:01
 */

namespace Swokit\Server\Event;

/**
 * Class SwooleEvent
 * @package Swokit\Server\Event
 */
final class SwooleEvent
{
    public const START = 'start';
    public const SHUTDOWN = 'shutdown';

    public const MANAGER_START = 'managerStart';
    public const MANAGER_STOP = 'managerStop';

    public const WORKER_START = 'workerStart';
    public const WORKER_STOP = 'workerStop';
    public const WORKER_EXIT = 'workerExit';
    public const WORKER_ERROR = 'workerError';

    public const PIPE_MESSAGE = 'pipeMessage';

    public const BUFFER_FULL = 'bufferFull';
    public const BUFFER_EMPTY = 'bufferEmpty';

    public const CONNECT = 'connect';
    public const RECEIVE = 'receive';
    public const CLOSE = 'close';

    /**
     * for task
     */
    public const TASK = 'task';
    public const FINISH = 'finish';

    /**
     * for http server
     */
    public const REQUEST = 'request';

    /**
     * for websocket server
     */
    public const OPEN = 'open';
    public const HANDSHAKE = 'handshake';
    public const MESSAGE = 'message';

    // basic events
    public const BASIC_EVENTS = [
        'start',
        'shutdown',
        'managerStart',
        'managerStop',
        'workerStart',
        'workerStop',
        'workerExit',
        'workerError'
    ];

    /**
     * @var array
     */
    public const BASIC_HANDLERS = [
        // basic
        'start' => 'onStart',
        'shutdown' => 'onShutdown',
        'managerStart' => 'onManagerStart',
        'managerStop' => 'onManagerStop',
        // worker
        'workerStart' => 'onWorkerStart',
        'workerStop' => 'onWorkerStop',
        'workerExit' => 'onWorkerExit',
        'workerError' => 'onWorkerError',
    ];

    /**
     * @var array
     */
    public const DEFAULT_HANDLERS = [
        // basic
        'start' => 'onStart',
        'shutdown' => 'onShutdown',
        'managerStart' => 'onManagerStart',
        'managerStop' => 'onManagerStop',

        // worker
        'workerStart' => 'onWorkerStart',
        'workerStop' => 'onWorkerStop',
        'workerExit' => 'onWorkerExit',
        'workerError' => 'onWorkerError',

        // special
        'pipeMessage' => 'onPipeMessage',
        'bufferFull' => 'onBufferFull',
        'bufferEmpty' => 'onBufferEmpty',

        // tcp/udp
        'connect' => 'onConnect',
        'receive' => 'onReceive',
        'packet' => 'onPacket',
        'close' => 'onClose',

        // task
        'task' => 'onTask',
        'finish' => 'onFinish',

        // http server
        'request' => 'onRequest',

        // webSocket server
        'open' => 'onOpen',
        'message' => 'onMessage',
        'handShake' => 'onHandshake'
    ];

    /**
     * @param string $event
     * @return string|null
     */
    public static function getHandler(string $event)
    {
        return self::DEFAULT_HANDLERS[$event] ?? $event;
    }

    /**
     * @param string $event
     * @return bool
     */
    public function isValid(string $event): bool
    {
        return isset(self::DEFAULT_HANDLERS[$event]);
    }

    /**
     * @return array
     */
    public static function getAllEvents(): array
    {
        return \array_keys(self::DEFAULT_HANDLERS);
    }
}
