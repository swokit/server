<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:11
 */

namespace Inhere\Server\Helpers;

use Inhere\Library\Helpers\PhpHelper;
use Swoole\Coroutine;

/**
 * Class ServerHelper
 * @package Inhere\Server
 */
class ServerHelper
{
    /**
     * @throws \RuntimeException
     */
    public static function checkRuntimeEnv()
    {
        if (!PhpHelper::isCli()) {
            throw new \RuntimeException('Server must run in the CLI mode.');
        }

        if (!\extension_loaded('swoole')) {
            throw new \RuntimeException('Run the server, extension \'swoole\' is required!');
        }
    }

    /**
     * @return bool
     */
    public static function coIsEnabled(): bool
    {
        return class_exists(Coroutine::class, false);
    }
}
