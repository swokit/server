<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-03-27
 * Time: 16:17
 */

namespace Swokit\Server\Traits;

use Swokit\Server\Event\SwooleEvent;
use Toolkit\PhpUtil\PhpHelper;

/**
 * Class FixedEventStaticTrait
 * @package Inhere\Library\event
 */
trait ServerEventManageTrait
{
    /**
     * set the supported events, if you need.
     *  if it is empty, will allow register any event.
     * @var array
     */
    protected static $allowedEvents = [];

    /**
     * registered Events
     * @var array
     * [
     *  'event' => bool, // is once event
     * ]
     */
    private static $events = [];

    /**
     * events and handlers
     * @var array
     * [
     *  'event' => callable, // event handler
     * ]
     */
    private static $eventHandlers = [];

    /**
     * register a event handler
     * @param                $event
     * @param callable|mixed $handler
     * @param bool           $once
     */
    public function on(string $event, $handler, bool $once = false): void
    {
        if (self::isSupportedEvent($event)) {
            self::$eventHandlers[$event][] = $handler;
            self::$events[$event]          = $once;
        }
    }

    /**
     * register a once event handler
     * @param          $event
     * @param callable $handler
     */
    public function once($event, callable $handler): void
    {
        $this->on($event, $handler, true);
    }

    /**
     * trigger event
     * @param string $event
     * @param array  ...$args
     */
    public function fire(string $event, ...$args): void
    {
        if (!isset(self::$events[$event])) {
            return;
        }

        // call event handlers of the event.
        foreach ((array)self::$eventHandlers[$event] as $cb) {
            // return FALSE to stop go on handle.
            if (false === PhpHelper::call($cb, ...$args)) {
                break;
            }
        }

        // is a once event, remove it
        if (self::$events[$event]) {
            $this->off($event);
        }
    }

    /**
     * remove event and it's handlers
     * @param $event
     */
    public function off(string $event): void
    {
        if ($this->hasEvent($event)) {
            unset(self::$events[$event], self::$eventHandlers[$event]);
        }
    }

    /**
     * @param string $event
     * @return bool
     */
    public function hasEvent(string $event): bool
    {
        return isset(self::$events[$event]);
    }

    /**
     * check $name is a supported event name
     * @param string $event
     * @return bool
     */
    public static function isSupportedEvent(string $event): bool
    {
        if ($ets = self::$allowedEvents) {
            return \in_array($event, $ets, true);
        }

        return true;
    }

    /**
     * @return array
     */
    public static function getAllowedEvents(): array
    {
        return self::$allowedEvents;
    }

    /**
     * @param array $allowedEvents
     */
    public static function setSupportEvents(array $allowedEvents): void
    {
        self::$allowedEvents = $allowedEvents;
    }

    /**
     * @return array
     */
    public static function getEvents(): array
    {
        return self::$events;
    }

    /**
     * @return int
     */
    public static function countEvents(): int
    {
        return \count(self::$events);
    }

    /**
     * @var array
     */
    protected static $swooleEvents = [
        // 'event'  => 'callback method',
        'pipeMessage' => 'onPipeMessage',

        // Task 任务相关 (若配置了 task_worker_num 则必须注册这两个事件)
        'task'        => 'onTask',
        'finish'      => 'onFinish',
    ];

    /**
     * @param array $swooleEvents
     */
    public function setSwooleEvents(array $swooleEvents): void
    {
        self::$swooleEvents = \array_merge(self::$swooleEvents, $swooleEvents);
    }

    /**
     * @return array
     */
    public function getSwooleEvents(): array
    {
        return self::$swooleEvents;
    }

    /**
     * register a swoole Event Handler Callback
     * @param string          $event
     * @param callable|string $handler
     * @throws \InvalidArgumentException
     */
    public function onSwoole(string $event, $handler): void
    {
        $this->setSwooleEvent($event, $handler);
    }

    /**
     * @param string          $event The event name
     * @param string|\Closure $cb The callback name
     * @throws \InvalidArgumentException
     */
    public function setSwooleEvent(string $event, $cb): void
    {
        $event = \trim($event);

        if (!$this->isSwooleEvent($event)) {
            $supported = \implode(',', SwooleEvent::getAllEvents());

            throw new \InvalidArgumentException(
                "You want add a not supported swoole event: $event. supported: \n $supported"
            );
        }

        self::$swooleEvents[$event] = $cb;
    }
}
