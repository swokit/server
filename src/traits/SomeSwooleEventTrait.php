<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/8/1
 * Time: 下午10:08
 */

namespace inhere\server\traits;

use inhere\server\helpers\ProcessHelper;
use Swoole\Server as SwServer;

/**
 * Trait SomeSwooleEventTrait
 * @package inhere\server\traits
 */
trait SomeSwooleEventTrait
{

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

        $this->log("The master process success started. (PID:<notice>{$masterPid}</notice>, pid_file: $this->pidFile)");
    }

    /**
     * on Master Stop
     * @param  SwServer $server
     */
    public function onMasterStop(SwServer $server)
    {
        $this->log("The swoole master process(PID: {$server->master_pid}) stopped.");

        $this->doClear();
    }

    /**
     * doClear
     */
    protected function doClear()
    {
        $this->removePidFile();

        self::$_statistics['stop_time'] = microtime(1);
    }

    /**
     * onConnect
     * @param  SwServer $server
     * @param  int $fd 客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
     */
    public function onConnect(SwServer $server, $fd)
    {
        $this->log("onConnect: Has a new client [fd:$fd] connection to the main server.(workerId: {$server->worker_id})");
    }

    /**
     * @param SwServer $server
     * @param $fd
     */
    public function onClose(SwServer $server, $fd)
    {
        $this->log("onConnect: The client [fd:$fd] connection closed on the main server.(workerId: {$server->worker_id})");
    }

    /**
     * on Manager Start
     * @param  SwServer $server
     */
    public function onManagerStart(SwServer $server)
    {
        // file_put_contents($pidFile, ',' . $server->manager_pid, FILE_APPEND);
        ProcessHelper::setTitle("swoole: manager ({$this->name})");

        $this->log("The manager process success started. (PID:{$server->manager_pid})");
    }

    /**
     * on Manager Stop
     * @param  SwServer $server
     */
    public function onManagerStop(SwServer $server)
    {
        $this->log("The swoole manager process stopped. (PID {$server->manager_pid})");
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
        $taskMark = $server->taskworker ? 'task-worker' : 'event-worker';

        $this->log("The #<primary>{$workerId}</primary> {$taskMark} process success started. (PID:{$server->worker_pid})");

        ProcessHelper::setTitle("swoole: {$taskMark} ({$this->name})");

        // ServerHelper::setUserAndGroup();

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

}
