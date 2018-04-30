<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-03-27
 * Time: 16:17
 */

namespace Inhere\Server\Traits;

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
     * @param $event
     * @param callable|mixed $handler
     * @param bool $once
     */
    public function on(string $event, $handler, $once = false)
    {
        if (self::isSupportedEvent($event)) {
            self::$eventHandlers[$event][] = $handler;
            self::$events[$event] = (bool)$once;
        }
    }

    /**
     * register a once event handler
     * @param $event
     * @param callable $handler
     */
    public function once($event, callable $handler)
    {
        $this->on($event, $handler, true);
    }

    /**
     * trigger event
     * @param $event
     * @param array $args
     */
    public function fire(string $event, array $args = [])
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
    public function off(string $event)
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
    public static function setSupportEvents(array $allowedEvents)
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
}
