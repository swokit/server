<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-12
 * Time: 14:33
 */

namespace Inhere\Server;

use Inhere\Library\Helpers\PhpHelper;
use Inhere\Server\Helper\ServerUtil;
use Swoole\Coroutine as SwCoroutine;

/**
 * Class Coroutine
 * @package Inhere\Server
 */
class Coroutine
{
    /**
     * the Coroutine id map
     * @var array
     * [
     *  child id => top id,
     *  child id => top id,
     *  ... ...
     * ]
     */
    private static $idMap = [];

    /**
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return ServerUtil::coroutineIsEnabled();
    }

    /**
     * get current coroutine id
     * @return int|string
     */
    public static function id()
    {
        if (!ServerUtil::coroutineIsEnabled()) {
            return 0;
        }

        return SwCoroutine::getuid();
    }

    /**
     * get top coroutine id
     * @return int|string
     */
    public static function tid()
    {
        if (!ServerUtil::coroutineIsEnabled()) {
            return 0;
        }

        $id = SwCoroutine::getuid();

        return self::$idMap[$id] ?? $id;
    }

    /**
     * create a child coroutine
     * @param callable $cb
     * @return bool
     */
    public static function create(callable $cb)
    {
        if (!ServerUtil::coroutineIsEnabled()) {
            return false;
        }

        $tid = self::tid();

        return SwCoroutine::create(function() use($cb, $tid) {
            $id = SwCoroutine::getuid();
            self::$idMap[$id] = $tid;

            PhpHelper::call($cb);
        });
    }

    /**
     * @param int|float $seconds
     */
    public static function sleep($seconds)
    {
        SwCoroutine::sleep($seconds);
    }

    /**
     * 挂起当前协程
     * @param string $coId
     */
    public static function suspend($coId)
    {
        SwCoroutine::suspend($coId);
    }

    /**
     * 恢复某个协程，使其继续运行。
     * @param string $coId
     */
    public static function resume($coId)
    {
        SwCoroutine::resume($coId);
    }
}
