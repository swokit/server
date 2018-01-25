<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-07-20
 * Time: 15:20
 */

namespace Inhere\Server\Traits;

use Inhere\Console\Utils\Show;
use Inhere\Console\Utils\ProcessUtil;
use Inhere\Server\Helpers\ProcessHelper;
use Inhere\Server\Helpers\ServerUtil;
use Swoole\Coroutine;
use Swoole\Server;

/**
 * Class ServerManageTrait
 * @package Inhere\Server\Traits
 * @property Server $server
 */
trait ServerManageTrait
{
    /**************************************************************************
     * swoole server manage
     *************************************************************************/

    /**
     * do Reload Workers
     * @param  boolean $onlyTaskWorker
     * @return int
     */
    public function reload($onlyTaskWorker = false): int
    {
        if (!$masterPid = $this->getPidFromFile(true)) {
            return Show::error("The swoole server({$this->name}) is not running.", true);
        }

        // SIGUSR1: 向管理进程发送信号，将平稳地重启所有worker进程; 也可在PHP代码中调用`$server->reload()`完成此操作
        $sig = SIGUSR1;

        if ($onlyTaskWorker) {
            $sig = SIGUSR2;
            Show::notice('Will only reload task worker');
        }

        if (!ProcessUtil::sendSignal($masterPid, $sig)) {
            Show::error("The swoole server({$this->name}) worker process reload fail!", -1);
        }

        return Show::success("The swoole server({$this->name}) worker process reload success.", 0);
    }

    /**
     * Do restart server
     * @param null|bool $daemon
     * @throws \Throwable
     */
    public function restart($daemon = null)
    {
        if ($this->getPidFromFile(true)) {
            $this->stop(false);
        }

        $this->start($daemon);
    }

    /**
     * Do stop swoole server
     * @param  boolean $quit Quit, When stop success?
     * @return int
     */
    public function stop($quit = true): int
    {
        if (!$masterPid = $this->getPidFromFile(true)) {
            return Show::error("The swoole server({$this->name}) is not running.");
        }

        // Show::write("The swoole server({$this->name}:{$masterPid}) process stopping ", false);

        ProcessUtil::killAndWait($masterPid, $error = null, $this->name);

        if ($error) {
            return Show::error($error);
        }

        ServerUtil::removePidFile($this->pidFile);

        // stop success
        return Show::write("The swoole server({$this->name}) process stop success", $quit);
    }

    public function version()
    {
        Show::write(sprintf('Swoole server manager tool, Version <comment>%s</comment> Update time %s', self::VERSION, self::UPDATE_TIME));
    }


    /**
     * 使当前worker进程停止运行，并立即触发onWorkerStop回调函数
     * @param null|int $workerId
     * @return bool
     */
    public function stopWorker(int $workerId = null): bool
    {
        if ($this->server) {
            return $this->server->stop($workerId);
        }

        return false;
    }
}
