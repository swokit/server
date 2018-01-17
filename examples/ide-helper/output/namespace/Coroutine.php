<?php

namespace Swoole;

/**
 * @since 2.0.8
 */
class Coroutine
{
    /**
     * @param $callable
     * @return bool 创建成功返回true，失败返回false
     */
    public static function create($callable)
    {
    }

    /**
     * @return mixed
     */
    public static function cli_wait()
    {
    }

    /**
     * 挂起当前协程
     * @param string $corouindId
     * @return mixed
     */
    public static function suspend(string $corouindId)
    {
    }

    /**
     * 恢复某个协程，使其继续运行。
     * @param string $coroutineId
     * @return mixed
     */
    public static function resume(string $coroutineId)
    {
    }

    /**
     * @return mixed
     */
    public static function getuid()
    {
    }

    /**
     * @param int|float $seconds
     * @return mixed
     */
    public static function sleep($seconds)
    {
    }

    /**
     * @return mixed
     */
    public static function call_user_func()
    {
    }

    /**
     * @param mixed $func
     * @param array $args
     * @return mixed
     */
    public static function call_user_func_array($func, $args)
    {
    }

    /**
     * @param resource $handle
     * @param int $length
     * @return string|bool
     */
    public static function fread(resource $handle, int $length = 0)
    {
    }

    /**
     * @param resource $handle
     * @param string $data
     * @param int $length
     * @return int|bool
     */
    public static function fwrite(resource $handle, string $data, int $length = 0)
    {
    }

    /**
     * @param string $domain
     * @param int $family
     * @return string|bool
     */
    public static function gethostbyname(string $domain, int $family = AF_INET)
    {
    }
}
