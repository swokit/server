<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-07-24
 * Time: 14:16
 */

namespace Inhere\Server\Helpers;

use Inhere\Library\Helpers\PhpHelper;

/**
 * Class ProcessHelper
 * @package Inhere\Server\Helpers
 */
final class ProcessHelper
{

    /**
     * Set process title.
     * @param string $title
     * @return bool
     */
    public static function setTitle($title)
    {
        if (PhpHelper::isMac()) {
            return false;
        }

        if (\function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        }

        return true;
    }

    /**
     * send kill signal to the process
     * @param int $pid
     * @param bool $force
     * @param int $timeout
     * @return bool
     */
    public static function kill($pid, $force = false, $timeout = 3)
    {
        return self::sendSignal($pid, $force ? SIGKILL : SIGTERM, $timeout);
    }

    /**
     * send signal to the process
     * @param int $pid
     * @param int $signal
     * @param int $timeout
     * @return bool
     */
    public static function sendSignal($pid, $signal, $timeout = 0)
    {
        if ($pid <= 0) {
            return false;
        }

        // do send
        if ($ret = posix_kill($pid, $signal)) {
            return true;
        }

        // don't want retry
        if ($timeout <= 0) {
            return $ret;
        }

        // failed, try again ...

        $timeout = $timeout > 0 && $timeout < 10 ? $timeout : 3;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            // success
            if (!$isRunning = @posix_kill($pid, 0)) {
                break;
            }

            // have been timeout
            if ((time() - $startTime) >= $timeout) {
                return false;
            }

            // try again kill
            $ret = posix_kill($pid, $signal);
            usleep(10000);
        }

        return $ret;
    }

    /**
     * @param $pid
     * @return bool
     */
    public static function isRunning($pid)
    {
        return ($pid > 0) && @posix_kill($pid, 0);
    }

    /**
     * get Pid from File
     * @param string $pidFile
     * @param bool $check
     * @return int
     */
    public static function getPidFromFile($pidFile, $check = false)
    {
        if ($pidFile && file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);

            // check
            if ($check && self::isRunning($pid)) {
                return $pid;
            }

            unlink($pidFile);
        }

        return 0;
    }
}
