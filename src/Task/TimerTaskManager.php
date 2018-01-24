<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-25
 * Time: 11:23
 */

namespace Inhere\Server\Task;

use Inhere\Library\Helpers\Obj;
use Inhere\Library\Helpers\PhpHelper;

/**
 * Class TimerTaskManager - Timed Task Manager, Schedule Task
 * @package Inhere\Server\Task
 * @link https://wiki.swoole.com/wiki/page/244.html
 */
class TimerTaskManager
{
    const IDX_ID = 0;
    const IDX_HANDLER = 1;
    const IDX_TIMES = 2;
    const IDX_TIME = 3;
    const IDX_PARAMS = 4;

    /**
     * some values
     *  -10 manual clear timer.
     *  -1  never stop/running
     *  0   stopped timer
     *  >0  run defined times, then stop it.
     */
    const ONCE = 1;
    const STOPPED = 0;
    const FOREVER = -1;
    const CLEAR = -10;

    /**
     * @var array[]
     * [ name => [id, handler, times, timeMs, params] ]
     */
    private $timers = [];

    /**
     * @var array [id => name]
     */
    private $idNames = [];

    /**
     * @var array [id => times]
     */
    private $runTimes = [];

    /**
     * 1 Direct scheduling
     * 2 save to queue
     * @var int 1,2
     */
    private $dispatchMode = 1;

    /**
     * class constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        Obj::smartConfigure($this, $config);
    }

    /**
     * add a tick timer 添加一个循环定时任务
     * @param string $name
     * @param int $timeMs Millisecond
     * @param callable $handler
     * @param array $params
     * @param int $times
     * @return $this
     */
    public function tick(string $name, int $timeMs, callable $handler, array $params = [], int $times = self::FOREVER)
    {
        return $this->add($name, $timeMs, $handler, $params, $times);
    }

    /**
     * add a after timer 添加一个一次性定时任务
     * @param string $name
     * @param int $timeMs Millisecond
     * @param callable $handler
     * @param array $params
     * @return $this
     */
    public function after(string $name, int $timeMs, callable $handler, array $params = [])
    {
        return $this->add($name, $timeMs, $handler, $params, self::ONCE);
    }

    /**
     * add a timer 添加一个定时任务
     * @NOTICE
     * - 定时器仅在当前进程空间内有效
     * - 定时器是纯异步实现的，不能与阻塞IO的函数一起使用，否则定时器的执行时间会发生错乱
     * @param string $name
     * @param int $timeMs
     * @param callable $handler
     * @param array $params
     * @param int $times 大于0表示定时任务需要执行的次数; 小于等于0 表示此定时器的状态
     * allowed values:
     *  -1 never stop/always running. 一直执行的定时任务
     *  0  stopped. 此定时任务暂时被停止执行
     *  >0 run defined times, then stop it. 需要执行的次数
     * @return $this
     */
    public function add(string $name, int $timeMs, callable $handler, array $params = [], int $times = self::FOREVER)
    {
        if ($this->has($name)) {
            throw new \InvalidArgumentException("The timer [$name] has been exists!");
        }

        if ($times === self::ONCE) {
            $id = swoole_timer_after($timeMs, [$this, 'dispatch'], $params);
        } else {
            $id = swoole_timer_tick($timeMs, [$this, 'dispatch'], $params);
        }

        $this->idNames[$id] = $name;
        $this->timers[$name] = [$id, $handler, $times, $timeMs, $params];

        if ($times > 0) {
            $this->runTimes[$id] = 0;
        }

        return $this;
    }

    /**
     * @param int $timerId
     * @param mixed $params
     * @return bool
     */
    public function dispatch(int $timerId, array $params = []): bool
    {
        if (!$name = $this->getTimerName($timerId)) {
            return false;
        }

        if (!$timer = $this->getTimer($name)) {
            return false;
        }

        $handler = $timer[self::IDX_HANDLER];
        $maxTimes = $timer[self::IDX_TIMES];

        // 停止的任务
        if ($maxTimes === self::STOPPED) {
            return true;
        }

        if ($maxTimes > 0) {
            $runTimes = $this->runTimes[$timerId];

            if ($runTimes >= $maxTimes) {
//                $this->timers[$name][self::IDX_TIMES] = self::STOPPED;
                $this->clear($name);

                return true;
            }
        }

        // run task
        $ret = PhpHelper::call($handler, $params);

        // 主动返回状态等于 -10, 表明想要清除定时器/任务
        if ($ret === self::CLEAR) {
            $this->clear($name);
        }

        if ($maxTimes > 0) {
            $this->runTimes[$timerId]++;

            return true;
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
        if ($timer = $this->timers[$name] ?? null) {
            return $index === null ? $timer[$index] : $timer;
        }

        return null;
    }

    /**
     * @param string $name
     * @return int
     */
    public function getTimerId(string $name): int
    {
        return array_search($name, $this->idNames, true) ?: 0;
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function getTimerName(int $id) : string
    {
        return $this->idNames[$id] ?? '';
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->timers[$name]);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function clear(string $name): bool
    {
        if ($timer = $this->timers[$name] ?? null) {
            $id = $timer[self::IDX_ID];

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

        $this->timers = $this->runTimes = [];
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

    /**
     * @return int
     */
    public function getDispatchMode(): int
    {
        return $this->dispatchMode;
    }

    /**
     * @param int $dispatchMode
     */
    public function setDispatchMode(int $dispatchMode)
    {
        $this->dispatchMode = $dispatchMode;
    }
}
