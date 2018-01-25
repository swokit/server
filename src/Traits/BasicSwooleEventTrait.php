<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-24
 * Time: 19:28
 */

namespace Inhere\Server\Traits;

use Inhere\Console\Utils\ProcessUtil;
use Inhere\Server\Helpers\ServerUtil;
use Swoole\Server;

/**
 * Trait BasicSwooleEventTrait
 * @package Inhere\Server\Traits
 */
trait BasicSwooleEventTrait
{
    /** @var int */
    protected $workerId = -1;

    /** @var int */
    protected $workerPid = 0;

    /** @var bool  */
    protected $taskWorker = false;

    /**************************************************************************
     * swoole event handler
     *************************************************************************/

    /**
     * on Master Start
     * @param  Server $server
     */
    public function onMasterStart(Server $server)
    {
        $masterPid = $server->master_pid;
        $rootPath = $this->config('rootPath');
        $rootPath = $rootPath ? " (at $rootPath)" : '';

        // save master process id to file.
        ServerUtil::createPidFile($masterPid, $this->pidFile);
        ProcessUtil::setTitle(sprintf('%s: master%s', $this->name, $rootPath));

        $this->log("The <comment>master</comment> process success started. (PID:<info>{$masterPid}</info>, pidFile: $this->pidFile)");
    }

    /**
     * on Master Stop
     * @param  Server $server
     */
    public function onMasterStop(Server $server)
    {
        $this->log("The swoole master process(PID: <info>{$server->master_pid})</info> stopped.");

        ServerUtil::removePidFile($this->pidFile);

        // self::addStat('stop_time', microtime(1));
    }

    /**
     * on Manager Start
     * @param  Server $server
     */
    public function onManagerStart(Server $server)
    {
        // $server->manager_pid;
        // $this->fire(self::ON_MANAGER_STARTED, [$this, $server]);

        // file_put_contents($this->pidFile, ',' . $server->manager_pid, FILE_APPEND);
        ProcessUtil::setTitle("{$this->name}: manager");

        $this->log("The <comment>manager</comment> process success started. (PID:{$server->manager_pid})");
    }

    /**
     * on Manager Stop
     * @param  Server $server
     */
    public function onManagerStop(Server $server)
    {
        // $this->fire(self::ON_MANAGER_STOPPED, [$this, $server]);
        $this->log("The manager process stopped. (PID {$server->manager_pid})");
    }

    /**
     * on Worker Start
     *   应当在onWorkerStart中创建连接对象
     * @link https://wiki.swoole.com/wiki/page/325.html
     * @param  Server $server
     * @param  int $workerId The worker index id in the all workers.
     */
    public function onWorkerStart(Server $server, $workerId)
    {
        $this->workerId = $workerId;
        $this->workerPid = $server->worker_pid;
        $this->taskWorker = (bool)$server->taskworker;
        $taskMark = $server->taskworker ? 'task-worker' : 'event-worker';

        $this->log("The #<cyan>{$workerId}</cyan> {$taskMark} process success started. (PID:{$server->worker_pid})");

        ProcessUtil::setTitle("{$this->name}: {$taskMark}");

        // ServerHelper::setUserAndGroup();
        // $this->fire(self::ON_WORKER_STARTED, [$this, $workerId, $this->taskWorker]);

        // 此数组中的文件表示进程启动前就加载了，所以无法reload
        // Show::write('进程启动前就加载了，无法reload的文件：');
        // Show::write(get_included_files());
    }

    /**
     * @param Server $server
     * @param int $workerId
     */
    public function onWorkerStop(Server $server, $workerId)
    {
        // $this->fire(self::ON_WORKER_STOPPED, [$this, $workerId]);
        $this->log("The swoole #<info>$workerId</info> worker process stopped. (PID:{$server->worker_pid})");
    }

    /**
     * @param Server $server
     * @param int $workerId
     */
    public function onWorkerExit(Server $server, $workerId)
    {
        // $this->fire(self::ON_WORKER_EXITED, [$this, $workerId]);
        $this->log("The swoole #<info>$workerId</info> worker process exited. (PID:{$server->worker_pid})");
    }

    /**
     * onPipeMessage
     *  能接收到 `$server->sendMessage()` 发送的消息
     * @param  Server $server
     * @param  int $srcWorkerId
     * @param  mixed $data
     */
    public function onPipeMessage(Server $server, $srcWorkerId, $data)
    {
        $this->log("#{$server->worker_id} message from #$srcWorkerId: $data");
    }

    /**
     * onConnect
     * @param Server $server
     * @param int $fd 客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
     * @param int $fromId
     */
    public function onConnect($server, $fd, $fromId)
    {
        $this->log("onConnect: Has a new client [fd:$fd] connection to the main server.(fromId: $fromId,workerId: {$server->worker_id})");
    }

    /**
     * @param Server $server
     * @param $fd
     */
    public function onClose($server, $fd)
    {
        $this->log("onConnect: The client [fd:$fd] connection closed on the main server.(workerId: {$server->worker_id})");
    }

    ////////////////////// Task Event //////////////////////

    /**
     * 处理异步任务( onTask )
     * @param  Server $server
     * @param  int $taskId
     * @param  int $fromId
     * @param  mixed $data
     */
    public function onTask(Server $server, $taskId, $fromId, $data)
    {
        $this->log('task worker received a new task', [
            'taskId' => $taskId,
            'fromId' => $fromId,
            'workerId' => $server->worker_id,
            'data' => $data
        ], 'debug');
        // 返回任务执行的结果(finish操作是可选的，也可以不返回任何结果)
        // $server->finish("$data -> OK");
    }

    /**
     * 处理异步任务的结果
     * @param  Server $server
     * @param  int $taskId
     * @param  mixed $data
     */
    public function onFinish(Server $server, $taskId, $data)
    {
        $this->log("task finished on the task worker. status: $data", [
            'taskId' => $taskId,
            'workerId' => $server->worker_id,
        ], 'debug');
    }

    /**
     * @return bool
     */
    public function isTaskWorker(): bool
    {
        return $this->taskWorker;
    }

    /**
     * @return bool
     */
    public function isUserWorker(): bool
    {
        return $this->workerId === -1 && $this->workerPid > 0;
    }

    /**
     * @param int $workerId
     */
    public function setWorkerId(int $workerId)
    {
        $this->workerId = $workerId;
    }

    /**
     * @return int
     */
    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    /**
     * @return int
     */
    public function getWorkerPid(): int
    {
        return $this->workerPid;
    }

    /**
     * @param int $workerPid
     */
    public function setWorkerPid(int $workerPid)
    {
        $this->workerPid = $workerPid;
    }
}
