<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-12-08
 * Time: 10:23
 */

namespace Swokit\Server\Func;

use Swokit\Server\BaseServer;
use Swokit\Server\Component\ModifyWatcher;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Timer;

function hotReloadProcess(string $dirs, bool $onlyReloadTask = false): \Closure
{
    return function (Process $process, BaseServer $mgr) use ($dirs, $onlyReloadTask) {
        $pid = $process->pid;
        $svrPid = $mgr->getMasterPid();
        $dirsArr = \array_map('trim', explode(',', $dirs));

        $mgr->log("The <info>hot-reload</info> worker process success started. (PID:{$pid}, SVR_PID:$svrPid, Watched:<info>{$dirs}</info>)");

        $kit = new \Swokit\Server\Component\HotReloading($svrPid);
        $kit
            ->addWatches($dirsArr, $mgr->config('rootPath'))
            ->setReloadHandler(function ($pid) use ($mgr, $onlyReloadTask) {
                $mgr->log("Begin reload workers process. (Master PID: {$pid})");
                $mgr->getServer()->reload($onlyReloadTask);
                // $mgr->doReloadWorkers($pid, $onlyReloadTask);
            });

        //Interact::section('Watched Directory', $kit->getWatchedDirs());

        $kit->run();
    };
}

function fileWatcherProcess(string $keyFile, array $dirs): \Closure
{
    return function (Process $process, BaseServer $mgr) use ($keyFile, $dirs) {
        $pid = $process->pid;
        $svrPid = $mgr->getMasterPid();

        $mgr->log("The <info>hot-reload</info> worker process success started. (PID:{$pid}, SVR_PID:$svrPid, Watched:)", $dirs);

        $cdc = new ModifyWatcher($keyFile);
        $cdc->watchDir($dirs);

        Timer::tick(1000, function () use ($cdc, $mgr) {
            $mid = $mgr->getMasterPid();

            if ($cdc->isChanged()) {
                $mgr->log("Begin reload workers process. (Master PID: {$mid})");
                $mgr->getServer()->reload();
            }
        });
    };
}

function app_server($port, $host = '0.0.0.0', array $config = [])
{

}

/**
 * @todo
 * @param string $file
 * @param array $data
 */
function include_file(string $file, array $data = [])
{
    $fp = fopen($file, 'rb');

    Coroutine::fread($fp);
}
