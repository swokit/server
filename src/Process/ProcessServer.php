<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-27
 * Time: 9:54
 */

namespace Inhere\Server\Process;

use Inhere\Library\Helpers\Obj;
use Inhere\Library\Helpers\ProcessHelper;
use Swoole\Process;

/**
 * Class ProcessServer - multi process
 * @package Inhere\Server\Process
 * @link https://wiki.swoole.com/wiki/page/p-process.html
 */
class ProcessServer
{
    const PIPE_MODE = 'pipe';
    const QUEUE_MODE = 'queue';

    /** @var string */
    private $name = 'php-ps';

    /** @var int master pid */
    public $masterPid = 0;

    /** @var array */
    private $workerIds = [];

    /** @var array */
    private $processes = [];

    /** @var int */
    public $maxPrecess = 1;

    /** @var int */
    private $newIndex = 0;

    /** @var int worker pid */
    public $pid = 0;

    /** @var Process */
    private $process;

    /**
     * 进程间通信方式
     * @var string
     */
    public $ipcMode = self::PIPE_MODE;

    /**
     * @var int
     */
    private $timer;

    /**
     * ProcessServer constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        Obj::smartConfigure($this, $config);

        $this->masterPid = getmypid();

        ProcessHelper::setTitle(sprintf('%s: %s', $this->name, 'master'));
    }

    public function run()
    {
        try {
            for ($i = 0; $i < $this->maxPrecess; $i++) {
                $this->createProcess();
            }

            $this->wait();

        } catch (\Throwable $e) {
            exit('ALL ERROR: ' . $e->getMessage());
        }
    }

    /**
     * @param null $index
     * @return mixed
     */
    public function createProcess($index = null)
    {
        $pipeType = false;

        if ($this->ipcMode === self::PIPE_MODE) {
            $pipeType = 2;
        }

        $process = new Process(function (Process $worker) use ($index) {
            if (null === $index) {
                $index = $this->newIndex;
                $this->newIndex++;
            }

            ProcessHelper::setTitle(sprintf('%s: worker%s', $this->name, $index));

            $this->pid = $worker->pid;
            $this->process = $worker;
            $this->timer = swoole_timer_tick(3000, [$this, 'checkMasterPid']);

            $this->execute($worker);
        }, false, $pipeType);

        if ($this->ipcMode === self::QUEUE_MODE) {
            $process->useQueue();
        }

        // start process.
        $pid = $process->start();

        $this->workerIds[$index] = $pid;
        $this->processes[$index] = $this->processes;

        return $pid;
    }

    public function execute(Process $worker)
    {
        // $recv = $worker->pop();
    }

    /**
     * param Process $worker
     */
    public function checkMasterPid()
    {
        if (!Process::kill($this->masterPid, 0)) {
            // clear timer
            if ($this->timer) {
                swoole_timer_clear($this->timer);
            }

            $this->process->exit();

            // 这句提示,实际是看不到的.需要写到日志中
            echo sprintf("Master process exited, I %d also quit\n", $this->pid);

            exit(0);
        }
    }

    /**
     * @param array $ret
     */
    public function rebootProcess(array $ret)
    {
        $pid = $ret['pid'];
        $index = array_search($pid, $this->workerIds, true);

        if ($index !== false) {
            $newPid = $this->createProcess((int)$index);
            echo "reboot process: worker#{$index},PID: {$newPid} Done\n";

            return;
        }

        throw new \RuntimeException('reboot process Error: no pid');
    }

    public function wait()
    {
        while (\count($this->workerIds)) {
            if ($ret = Process::wait()) {
                $this->rebootProcess($ret);
            }
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
                $this->rebootProcess($ret);
            }
        });
    }
}
