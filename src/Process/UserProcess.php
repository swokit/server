<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/10
 * Time: 下午11:24
 */

namespace Inhere\Server\Process;

use Inhere\Library\Helpers\Obj;
use Inhere\Library\Helpers\ProcessHelper;
use Swoole\Process;
use Swoole\Server;

/**
 * Class UserProcess
 * @package Inhere\Server\Process
 */
abstract class UserProcess implements ProcessInterface
{
    /** @var string */
    private $name;

    /** @var int process pid */
    public $pid = 0;

    /** @var Process */
    private $process;

    /** @var bool */
    private $allowStart = true;

    /** @var bool */
    private $daemon = false;

    /** @var bool */
    private $redirectIO = false;

    /** @var bool|int */
    private $pipeType = false;

    /** @var bool */
    private $useQueue = false;

    /** @var int msg queue key */
    private $queueKey = 0;

    /** @var int msg queue mode */
    private $queueMode = 2;

    /** @var bool */
    private $queueBlock = true;

    /** @var Server */
    private $server;

    /**
     * class constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        Obj::smartConfigure($this, $config);
        $pid = getmypid();
printf("i am master, pid: $pid\n");
        $this->process = new Process(function (Process $process) {
            if ($this->name) {
                ProcessHelper::setTitle($this->name);
            }

            // if use daemon, require fetch pid again.
//            if ($this->daemon) {
                $this->pid = $process->pid;
//            }
            printf("i am worker, pid: {$this->pid}\n");

            $this->started($process);
        }, $this->redirectIO, $this->pipeType);

        // enable msg queue
        if ($this->pipeType === false && $this->useQueue) {
            $queueMode = $this->queueMode;

            if (!$this->queueBlock) {
                $queueMode |= Process::IPC_NOWAIT;
            }

            if (!$ok = $this->process->useQueue($this->queueKey, $queueMode)) {
                throw new \RuntimeException('create queue failed. error: ' . swoole_strerror(swoole_errno()));
            }
        }
    }

    /**
     * @param Server $server
     */
    public function attachTo(Server $server)
    {
        $this->server = $server;
        $this->allowStart = false;

        $server->addProcess($this->process);
    }

    /**
     * start
     * @param bool $wait
     * @param bool $async
     */
    public function start($wait = true, $async = false)
    {
        if (!$this->allowStart) {
            throw new \LogicException('This process is already on the swoole server and is not allowed to be started alone.');
        }

        if ($this->daemon) {
            Process::daemon();
        }

        $this->pid = $this->process->start();

        if ($wait) {
            $async ? $this->asyncWait() : $this->wait();
        }
    }

    public function wait()
    {
        if ($ret = Process::wait()) {
            echo "exited\n";
        }
    }

    /**
     * @link https://wiki.swoole.com/wiki/page/220.html
     */
    public function asyncWait()
    {
        Process::signal(SIGCHLD, function ($sig) {
            // 必须为false，非阻塞模式
            while ($ret = Process::wait(false)) {
                echo "exited\n";
            }
        });
    }

    /**
     * @param Process $process
     */
    public function started(Process $process)
    {
        swoole_event_add($process->pipe, [$this, 'onPipeRead']);
    }

    public function onPipeRead()
    {
        // do something
    }

    /**
     * @return string
     */
    public function getName(): string
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
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->process;
    }

    /**
     * @return bool
     */
    public function isDaemon(): bool
    {
        return $this->daemon;
    }

    /**
     * @param bool $daemon
     */
    public function setDaemon($daemon)
    {
        $this->daemon = (bool)$daemon;
    }

    /**
     * @return bool
     */
    public function isRedirectIO(): bool
    {
        return $this->redirectIO;
    }

    /**
     * @param bool $redirectIO
     */
    public function setRedirectIO($redirectIO)
    {
        $this->redirectIO = (bool)$redirectIO;
    }

    /**
     * @return bool|int
     */
    public function getPipeType()
    {
        return $this->pipeType;
    }

    /**
     * @param bool|int $pipeType
     */
    public function setPipeType($pipeType)
    {
        $this->pipeType = $pipeType;
    }

    /**
     * @return bool
     */
    public function isUseQueue(): bool
    {
        return $this->useQueue;
    }

    /**
     * @param bool $useQueue
     */
    public function setUseQueue($useQueue)
    {
        $this->useQueue = (bool)$useQueue;
    }

    /**
     * @return int
     */
    public function getQueueKey(): int
    {
        return $this->queueKey;
    }

    /**
     * @param int $queueKey
     */
    public function setQueueKey(int $queueKey)
    {
        $this->queueKey = $queueKey;
    }

    /**
     * @return bool
     */
    public function isQueueBlock(): bool
    {
        return $this->queueBlock;
    }

    /**
     * @param bool $queueBlock
     */
    public function setQueueBlock($queueBlock)
    {
        $this->queueBlock = (bool)$queueBlock;
    }

    /**
     * @return int
     */
    public function getQueueMode(): int
    {
        return $this->queueMode;
    }

    /**
     * @param int $queueMode
     */
    public function setQueueMode(int $queueMode)
    {
        $this->queueMode = $queueMode;
    }
}
