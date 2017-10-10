<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-27
 * Time: 9:44
 */

namespace Inhere\Server\Process;

use Inhere\Server\Helpers\ProcessHelper;
use Swoole\Process;
use Swoole\Serialize;
use Swoole\Server;

/**
 * Class LogicProcess
 * @package Inhere\Server\Process
 */
class LogicProcess implements ProcessInterface
{
    /** @var  string */
    private $name;

    /** @var Server  */
    private $server;

    /** @var Process  */
    private $process;

    /** @var int  */
    private $bufferSize = 64 * 1024;

    /** @var int  */
    private $index = 0;

    /** @var int */
    private $workerId;

    /**
     * class constructor.
     * @param string $name 进程名称
     * @param Server $server Swoole server instance
     * @param int $workerId  要与此进程通信的 worker id
     */
    public function __construct(string $name, Server $server, $workerId)
    {
        $this->name = $name;
        $this->server = $server;
        $this->workerId = $workerId;
        $this->process = new Process([$this, 'started'], false, self::PIPE_SOCK_DGRAM);

        // add to server, will auto start it.
        $server->addProcess($this->process);
    }

    /**
     * @param Process $process
     */
    public function started(Process $process)
    {
        if ($this->name) {
            ProcessHelper::setTitle($this->name);
        }

        swoole_event_add($process->pipe, [$this, 'onRead']);

//        $this->server->worker_id = $this->workerId;
        $this->server->dst_worker_id = $this->workerId;
    }

    /**
     * 接收到 worker 的调用，执行相关方法。需要返回值的就发送返回值到worker
     * on pipe Read
     */
    public function onRead()
    {
        $received = Serialize::unpack($this->process->read($this->bufferSize));

        $data = $received['data'];
        $func = $data['func'];
        $result = $this->$func(...$data['args']);

        // 是否需要返回结果
        if ($data['returned']) {
            $newData['result'] = $result;
            $newData['token'] = $data['token'];
            $bag = $this->packData('rpc', $newData);

            $this->server->sendMessage($data['workerId'], $bag);
        }
    }

    /**
     * 在 worker 进程中调用 此进程 来执行方法
     * @param string $name
     * @param array $arguments
     * @param bool $returned
     * @return string
     */
    public function call($name, array $arguments = [], $returned = true)
    {
        $this->index++;

        $data['args'] = $arguments;
        $data['func'] = $name;
        $data['token'] = $this->genToken($name);
//        $data['workerId'] = $this->server->worker_id;
        $data['workerId'] = $this->server->dst_worker_id;
        $data['returned'] = $returned;

        $this->process->write($this->packData('rpc', $data));

        return $data['token'];
    }

    /**
     * @param $type
     * @param $data
     * @param null $func
     * @return mixed
     */
    public function packData($type, $data, $func = null)
    {
        return Serialize::pack([
            'type' => $type,
            'data' => $data,
            'func' => $func,
        ]);
    }

    public function genToken($name)
    {
        return sprintf('insideProcessRpc:%s%d', $name, $this->index);
    }

    /**
     * @return null|string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * @param int $bufferSize
     */
    public function setBufferSize(int $bufferSize)
    {
        $this->bufferSize = $bufferSize;
    }

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->process;
    }
}
