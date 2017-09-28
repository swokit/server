<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:11
 */

namespace Inhere\Server\Helpers;

use inhere\library\helpers\PhpHelper;
use Swoole\Coroutine;

/**
 * Class ServerHelper
 * @package Inhere\Server
 */
class ServerHelper
{
    /**
     * 获取资源消耗
     * @param int $startTime
     * @param int|float $startMem
     * @param array $info
     * @return array
     */
    public static function runtime($startTime, $startMem, array $info = [])
    {
        // 显示运行时间
        $info['time'] = number_format(microtime(true) - $startTime, 4) . 's';

        $startMem = array_sum(explode(' ', $startMem));
        $endMem = array_sum(explode(' ', memory_get_usage()));

        $info['memory'] = number_format(($endMem - $startMem) / 1024) . 'kb';

        return $info;
    }

    /**
     * @throws \RuntimeException
     */
    public static function checkRuntimeEnv()
    {
        if (!PhpHelper::isCli()) {
            throw new \RuntimeException('Server must run in the CLI mode.');
        }

        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Run the server, extension \'swoole\' is required!');
        }
    }

    /**
     * @return bool
     */
    public static function coroutineIsEnabled()
    {
        return class_exists(Coroutine::class, false);
    }
}
