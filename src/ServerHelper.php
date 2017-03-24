<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:11
 */

namespace inhere\server;

use inhere\librarys\helpers\PhpHelper;

use Swoole\Websocket\Server as SwWSServer;

/**
 * Class ServerHelper
 * @package inhere\server
 */
class ServerHelper
{
    /**
     * 获取资源消耗
     * @param int $startTime
     * @param int $startMem
     * @return array
     */
    public static function runtime($startTime, $startMem)
    {
        // 显示运行时间
        $return['time'] = number_format((microtime(true) - $startTime), 4) . 's';

        $startMem =  array_sum(explode(' ',$startMem));
        $endMem   =  array_sum(explode(' ',memory_get_usage()));

        $return['memory'] = number_format(($endMem - $startMem)/1024) . 'kb';

        return $return;
    }

    /**
     *
     */
    public static function checkRuntimeEnv()
    {
        if ( !PhpHelper::isCli() ) {
            throw new \RuntimeException('Server must run in the CLI mode.');
        }

        if ( !PhpHelper::extIsLoaded('swoole', false) ) {
            throw new \RuntimeException('Run the server, extension \'swoole\' is required!');
        }
    }

    /**
     * get Pid By PidFile
     * @param string $pidFile
     * @return int
     */
    public static function getPidByPidFile($pidFile)
    {
        if ($pidFile && file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);

            if (posix_getpgid($pid)) {
                return $pid;
            } else {
                unlink($pidFile);
            }
        }

        return 0;
    }

    /**
     * send message to all client user
     * @param SwWSServer $server
     * @param array $data
     */
    public static function broadcastMessage(SwWSServer $server, $data)
    {
        foreach($server->connections as $fd) {
            $server->push($fd, json_encode((array)$data));
        }
    }


//////////////////////////////////////////////////////////////////////
/// some help method(from workman)
//////////////////////////////////////////////////////////////////////

    /**
     * 杀死所有进程
     * @param $name
     * @param int $sigNo
     * @return string
     */
    public static function killProcessByName($name, $sigNo = 9)
    {
        $cmd = 'ps -eaf |grep "' . $name . '" | grep -v "grep"| awk "{print $2}"|xargs kill -' . $sigNo;

        return exec($cmd);
    }

    /**
     * Get unix user of current process.
     *
     * @return string
     */
    public static function getCurrentUser()
    {
        $userInfo = posix_getpwuid(posix_getuid());

        return $userInfo['name'];
    }

    /**
     * Set unix user and group for current process.
     * @param $user
     * @param string $group
     * @return string|true
     */
    public static function setUserAndGroup($user, $group = '')
    {
        // Get uid.
        if (!$userInfo = posix_getpwnam($user)) {
            return "Warning: User {$user} not exists";
        }

        $uid = $userInfo['uid'];

        // Get gid.
        if ($group) {

            if (!$groupInfo = posix_getgrnam($group)) {
                return "Warning: Group {$group} not exists";
            }

            $gid = $groupInfo['gid'];
        } else {
            $gid = $userInfo['gid'];
        }

        if ( !posix_initgroups($userInfo['name'], $gid) ) {
            return "Warning: The user [{$user}] is not in the user group ID [GID:{$gid}].";
        }

        // Set uid and gid.
        // if ($uid != posix_getuid() || $gid != posix_getgid()) {
            if (!posix_setgid($gid) || !posix_setuid($uid)) {
                return "Warning: change gid or uid fail.";
            }
        // }

        return true;
    }

    /**
     * Set process title.
     * @param string $title
     * @return void
     */
    public static function setProcessTitle($title)
    {
        // >=php 5.5
        if (function_exists('cli_set_process_title') && !PhpHelper::isMac()) {
            cli_set_process_title($title);

            // Need process title when php<=5.5
        } else {
            swoole_set_process_name($title);
        }
    }
}
