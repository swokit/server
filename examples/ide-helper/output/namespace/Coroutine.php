<?php
namespace Swoole;

/**
 * @since 2.0.8
 */
class Coroutine
{


    /**
     * @return mixed
     */
    public static function create(){}

    /**
     * @return mixed
     */
    public static function cli_wait(){}

    /**
     * 挂起当前协程
     * @param string $corouindId
     * @return mixed
     */
    public static function suspend(string $corouindId){}

    /**
     * 恢复某个协程，使其继续运行。
     * @param string $coroutineId
     * @return mixed
     */
    public static function resume(string $coroutineId){}

    /**
     * @return mixed
     */
    public static function getuid(){}

    /**
     * @return mixed
     */
    public static function call_user_func(){}

    /**
     * @return mixed
     */
    public static function call_user_func_array(){}


}
