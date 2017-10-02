<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-25
 * Time: 11:23
 */

namespace Inhere\Server\Components;

use Inhere\Library\Helpers\PhpHelper;

/**
 * Class TimerManager - Swoole Timer Manager
 * @package Inhere\Server\Components
 * @link https://wiki.swoole.com/wiki/page/244.html
 */
class TimerManager
{
    const IDX_ID = 0;
    const IDX_HANDLER = 1;
    const IDX_PARAMS = 2;
    const IDX_TIME = 3;

    const STOP = 10;

    /**
     * @var array[]
     * [ name => [id, handler, params, timeMs] ]
     */
    private $timers = [];

    /**
     * @var array [id => name]
     */
    private $idNames = [];

    /**
     * add a tick timer 添加一个循环定时器
     * @param string $name
     * @param int $timeMs
     * @param callable $handler
     * @param array $params
     * @return $this
     */
    public function tick(string $name, int $timeMs, callable $handler, array $params = [])
    {
        return $this->add($name, $timeMs, $handler, $params);
    }

    /**
     * add a after timer 添加一个一次性定时器
     * @param string $name
     * @param int $timeMs
     * @param callable $handler
     * @param array $params
     * @return $this
     */
    public function after(string $name, int $timeMs, callable $handler, array $params = [])
    {
        return $this->add($name, $timeMs, $handler, $params, true);
    }

    /**
     * add a timer 添加一个定时器
     * @NOTICE
     * - 定时器仅在当前进程空间内有效
     * - 定时器是纯异步实现的，不能与阻塞IO的函数一起使用，否则定时器的执行时间会发生错乱
     * @param string $name
     * @param int $timeMs
     * @param callable $handler
     * @param array $params
     * @param bool $isOnce
     * @return $this
     */
    public function add(string $name, int $timeMs, callable $handler, array $params = [], $isOnce = false)
    {
        if ($this->has($name)) {
            throw new \InvalidArgumentException("The timer [$name] has been exists!");
        }

        if ($isOnce) {
            $id = swoole_timer_after($timeMs, [$this, 'dispatch'], $params);
        } else {
            $id = swoole_timer_tick($timeMs, [$this, 'dispatch'], $params);
        }

        $this->idNames[$id] = $name;
        $this->timers[$name] = [$id, $handler, $params, $timeMs];

        return $this;
    }

    /**
     * @param int $timerId
     * @param mixed $params
     * @return bool
     */
    public function dispatch(int $timerId, array $params = [])
    {
        if (!$name = $this->getTimerName($timerId)) {
            return false;
        }
        
        if (!$conf = $this->getTimer($name)) {
            return false;
        }

        $handler = $conf[self::IDX_HANDLER];
        $ret = PhpHelper::call($handler, $params);

        if ($ret === self::STOP) {
            $this->clear($name);
        }

        return true;
    }

    /**
     * @param string $name
     * @param null|int $index The index {@see self::IDX_*}
     * @return array|mixed
     */
    public function getTimer(string $name, int $index = null)
    {
        if ($conf = $this->timers[$name] ?? null) {
            return $index === null ? $conf[$index] : $conf;
        }
        
        return null;
    }

    /**
     * @param string $name
     * @return int
     */
    public function getTimerId(string $name)
    {
        return array_search($name, $this->idNames, true) ?: 0;
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function getTimerName(int $id) : ?string
    {
        return $this->idNames[$id] ?? null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name)
    {
        return isset($this->timers[$name]);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function clear(string $name)
    {
        if ($conf = $this->timers[$name] ?? null) {
            $id = $conf[0];

            unset($this->timers[$name], $this->idNames[$id]);

            // swoole_timer_clear 不能用于清除其他进程的定时器，只作用于当前进程
            return swoole_timer_clear($id);
        }

        return false;
    }

    public function __destruct()
    {
        foreach ($this->timers as $name => $handler) {
            $this->clear($name);
        }

        $this->timers = [];
    }

    /**
     * @return array
     */
    public function getTimers(): array
    {
        return $this->timers;
    }

    /**
     * @param array $timers
     */
    public function setTimers(array $timers)
    {
        $this->timers = $timers;
    }

    /**
     * @return array
     */
    public function getIdNames(): array
    {
        return $this->idNames;
    }
}
