<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/8/1
 * Time: 下午10:08
 */

namespace Inhere\Server\Traits;

use Inhere\Server\Helpers\ProcessHelper;
use Swoole\Server as SwServer;

/**
 * Trait SomeSwooleEventTrait
 * @package Inhere\Server\Traits
 */
trait SomeSwooleEventTrait
{
    /** @var int */
    protected $workId = -1;

    /** @var int */
    protected $workPid = 0;

    /** @var bool  */
    protected $taskWorker = false;

//////////////////////////////////////////////////////////////////////
/// swoole event handler
//////////////////////////////////////////////////////////////////////

    /**
     * on Master Start
     * @param  SwServer $server
     */
    public function onMasterStart(SwServer $server)
    {
        $masterPid = $server->master_pid;

        // save master process id to file.
        $this->createPidFile($masterPid);

        ProcessHelper::setTitle(sprintf('swoole: master (%s IN %s)', $this->name, $this->getValue('root_path')));

        $this->log("The <comment>master</comment> process success started. (PID:<info>{$masterPid}</info>, pid_file: $this->pidFile)");
    }

    /**
     * on Master Stop
     * @param  SwServer $server
     */
    public function onMasterStop(SwServer $server)
    {
        $this->log("The swoole master process(PID: <info>{$server->master_pid})</info> stopped.");

        $this->removePidFile();

        self::$_statistics['stop_time'] = microtime(1);
    }

    /**
     * on Manager Start
     * @param  SwServer $server
     */
    public function onManagerStart(SwServer $server)
    {
        // $server->manager_pid;
        $this->fire(self::ON_MANAGER_STARTED, [$this, $server]);

        // file_put_contents($this->pidFile, ',' . $server->manager_pid, FILE_APPEND);
        ProcessHelper::setTitle("swoole: manager ({$this->name})");

        $this->log("The <comment>manager</comment> process success started. (PID:{$server->manager_pid})");
    }

    /**
     * on Manager Stop
     * @param  SwServer $server
     */
    public function onManagerStop(SwServer $server)
    {
        $this->fire(self::ON_MANAGER_STOPPED, [$this, $server]);
        $this->log("The manager process stopped. (PID {$server->manager_pid})");
    }

    /**
     * on Worker Start
     *   应当在onWorkerStart中创建连接对象
     * @link https://wiki.swoole.com/wiki/page/325.html
     * @param  SwServer $server
     * @param  int $workerId The worker index id in the all workers.
     */
    public function onWorkerStart(SwServer $server, $workerId)
    {
        $this->workId = $workerId;
        $this->workPid = $server->worker_pid;
        $this->taskWorker = (bool)$server->taskworker;
        $taskMark = $server->taskworker ? 'task-worker' : 'event-worker';

        $this->log("The #<cyan>{$workerId}</cyan> {$taskMark} process success started. (PID:{$server->worker_pid})");

        ProcessHelper::setTitle("swoole: {$taskMark} ({$this->name})");

        // ServerHelper::setUserAndGroup();
        $this->fire(self::ON_WORKER_STARTED, [$this, $workerId]);

        // 此数组中的文件表示进程启动前就加载了，所以无法reload
        // Show::write('进程启动前就加载了，无法reload的文件：');
        // Show::write(get_included_files());
    }

    /**
     * @param SwServer $server
     * @param $workerId
     */
    public function onWorkerStop(SwServer $server, $workerId)
    {
        $this->fire(self::ON_WORKER_STOPPED, [$this, $workerId]);

        $this->log("The swoole #<info>$workerId</info> worker process stopped. (PID:{$server->worker_pid})");
    }

    /**
     * onPipeMessage
     *  能接收到 `$server->sendMessage()` 发送的消息
     * @param  SwServer $server
     * @param  int $srcWorkerId
     * @param  mixed $data
     */
    public function onPipeMessage(SwServer $server, $srcWorkerId, $data)
    {
        $this->log("#{$server->worker_id} message from #$srcWorkerId: $data");
    }

    /**
     * onConnect
     * @param SwServer $server
     * @param int $fd 客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
     * @param int $fromId
     */
    public function onConnect($server, $fd, $fromId)
    {
        $this->log("onConnect: Has a new client [fd:$fd] connection to the main server.(fromId: $fromId,workerId: {$server->worker_id})");
    }

    /**
     * @param SwServer $server
     * @param $fd
     */
    public function onClose($server, $fd)
    {
        $this->log("onConnect: The client [fd:$fd] connection closed on the main server.(workerId: {$server->worker_id})");
    }

    ////////////////////// Task Event //////////////////////

    /**
     * 处理异步任务( onTask )
     * @param  SwServer $server
     * @param  int $taskId
     * @param  int $fromId
     * @param  mixed $data
     */
    public function onTask(SwServer $server, $taskId, $fromId, $data)
    {
        // $this->log("Handle New AsyncTask[id:$taskId]");
        // 返回任务执行的结果(finish操作是可选的，也可以不返回任何结果)
        // $server->finish("$data -> OK");
    }

    /**
     * 处理异步任务的结果
     * @param  SwServer $server
     * @param  int $taskId
     * @param  mixed $data
     */
    public function onFinish(SwServer $server, $taskId, $data)
    {
        $this->log("AsyncTask[$taskId] Finish(task worker id: {$server->worker_id}). Data: $data");
    }

    /**
     * @return bool
     */
    public function isTaskWorker(): bool
    {
        return $this->taskWorker;
    }

    /**
     * @param int $workId
     */
    public function setWorkId(int $workId)
    {
        $this->workId = $workId;
    }

    /**
     * @return int
     */
    public function getWorkId(): int
    {
        return $this->workId;
    }

    /**
     * @return int
     */
    public function getWorkPid(): int
    {
        return $this->workPid;
    }

    /**
     * @param int $workPid
     */
    public function setWorkPid(int $workPid)
    {
        $this->workPid = $workPid;
    }

}
