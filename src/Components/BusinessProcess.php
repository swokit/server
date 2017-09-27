<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-27
 * Time: 9:44
 */

namespace Inhere\Server\Components;

use Inhere\Server\Helpers\ProcessHelper;
use Swoole\Process as SwProcess;
use Swoole\Serialize;
use function Sws\app;

/**
 * Class BusinessProcess
 * @package Inhere\Server\Components
 */
class BusinessProcess
{
    const PIPE_NOT_CREATE = 0;
    const PIPE_SOCK_STREAM = 1;
    const PIPE_SOCK_DGRAM = 2;

    /** @var  string */
    private $name;

    /** @var SwProcess  */
    private $process;

    /** @var int  */
    private $bufferSize = 64 * 1024;

    /** @var int  */
    private $index = 0;

    /**
     * Process constructor.
     * @param $name
     */
    public function __construct($name)
    {
        $this->name = $name;
        $this->workerId = app()->server->worker_id;
        $this->process = new SwProcess([$this, 'onStarted'], false, self::PIPE_SOCK_DGRAM);

        // add to server, will auto start it.
        app()->server->addProcess($this->process);
    }

    /**
     * @param SwProcess $process
     */
    public function onStarted(SwProcess $process)
    {
        if ($this->name) {
            ProcessHelper::setTitle($this->name);
        }

        swoole_event_add($process->pipe, [$this, 'onRead']);
        app()->server->worker_id = $this->workerId;
    }

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

            app()->server->sendMessage($bag, $data['workerId']);
        }
    }

    /**
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
        $data['workerId'] = app()->server->worker_id;
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
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
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
     * @return SwProcess
     */
    public function getProcess(): SwProcess
    {
        return $this->process;
    }


}