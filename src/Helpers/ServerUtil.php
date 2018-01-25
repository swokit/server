<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-24
 * Time: 19:09
 */

namespace Inhere\Server\Helpers;

use Inhere\Console\Utils\ProcessUtil;
use Inhere\Console\Utils\Show;
use Swoole\Coroutine;
use Swoole\Server;

/**
 * Class ServerHelper
 * @package App\Server
 */
class ServerUtil
{
    /**
     * @throws \RuntimeException
     */
    public static function checkRuntimeEnv()
    {
        if (PHP_SAPI !== 'cli') {
            throw new \RuntimeException('Server must run in the CLI mode.');
        }

        if (!\extension_loaded('swoole')) {
            throw new \RuntimeException("Run the server, extension 'swoole' is required!");
        }
    }

    /**
     * see Runtime Env
     */
    public static function seeRuntimeEnv()
    {
        $yes = '<info>√</info>';
        $no = '<danger>X</danger>';
        $tips = '<danger>please disabled</danger>';
        $info = [
            'Php version is gt 7.1' => version_compare(PHP_VERSION, '7.1') ? $yes : $no,
            'Swoole is installed' => class_exists(Server::class, false) ? $yes : $no,
            'Swoole version is gt 2' => version_compare(SWOOLE_VERSION, '2.0') ? $yes : $no,
            'Swoole Coroutine is enabled' => class_exists(Coroutine::class, false) ? $yes : $no,
            'XDebug extension exists' => \extension_loaded('xdebug') ? $yes . "($tips)" : $no,
            'xProf extension exists' => \extension_loaded('xprof') ? $yes . "($tips)" : $no,
        ];

        Show::aList($info, 'the env check result', [
            'keyStyle' => '',
            'sepChar' => ' | ',
            'ucFirst' => false,
        ]);
    }

    /**
     * @param string $pidFile
     * @param bool $checkRunning
     * @return int
     */
    public static function getPidFromFile($pidFile, $checkRunning = false): int
    {
        return ProcessUtil::getPidByFile($pidFile, $checkRunning);
    }

    /**
     * @param int $masterPid
     * @param string $pidFile
     * @return bool|int
     */
    public static function createPidFile(int $masterPid, $pidFile)
    {
        if ($pidFile) {
            return file_put_contents($pidFile, $masterPid);
        }

        return false;
    }

    /**
     * @param string $pidFile
     * @return bool
     */
    public static function removePidFile($pidFile): bool
    {
        if ($pidFile && file_exists($pidFile)) {
            return unlink($pidFile);
        }

        return false;
    }
}
