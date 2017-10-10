<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-27
 * Time: 9:54
 */

namespace Inhere\Server\Process;

use Inhere\Library\Helpers\ProcessHelper;
use Swoole\Process;

/**
 * Class ProcessServer
 * @package Inhere\Server\Process
 * @link https://wiki.swoole.com/wiki/page/p-process.html
 */
class ProcessServer
{
    /** @var int master pid */
    public $pid = 0;

    /** @var array  */
    public $workerIds = [];

    /** @var array  */
    public $processes = [];

    /** @var int  */
    public $maxPrecess = 1;

    /** @var int  */
    public $newIndex = 0;

    /** @var string  */
    private $key;

    /**
     * 进程间通信方式
     * @var string
     */
    private $ipcMode = 'pipe'; // queue

    public function __construct(string $key)
    {
        try {
            ProcessHelper::setTitle(sprintf('php-ps: %s', 'master'));
            $this->pid = getmypid();
            $this->run();
            $this->processWait();
        } catch (\Throwable $e) {
            exit('ALL ERROR: ' . $e->getMessage());
        }
    }

    public function run()
    {
        for ($i = 0; $i < $this->maxPrecess; $i++) {
            $this->createProcess();
        }
    }

    public function createProcess($index = null)
    {
        $process = new Process(function (Process $worker) use ($index) {
            if (null === $index) {
                $index = $this->newIndex;
                $this->newIndex++;
            }

            ProcessHelper::setTitle(sprintf('php-ps: worker%s', $index));

            // $recv = $worker->pop();

            for ($j = 0; $j < 16000; $j++) {
                $this->checkMasterPid($worker);
                echo "msg: {$j}\n";
                sleep(1);
            }
        }, false, false);

        $process->useQueue();
        $pid = $process->start();

        $this->workerIds[$index] = $pid;
        $this->processes[$index] = $this->processes;

        return $pid;
    }

    /**
     * @param Process $worker
     */
    public function checkMasterPid($worker)
    {
        if (!Process::kill($this->pid, 0)) {
            $worker->exit();
            // 这句提示,实际是看不到的.需要写到日志中
            echo "Master process exited, I [{$worker['pid']}] also quit\n";
        }
    }

    public function rebootProcess($ret)
    {
        $pid = $ret['pid'];
        $index = array_search($pid, $this->workerIds, true);

        if ($index !== false) {
            $new_pid = $this->createProcess((int)$index);
            echo "rebootProcess: {$index}={$new_pid} Done\n";

            return;
        }

        throw new \RuntimeException('rebootProcess Error: no pid');
    }

    public function processWait()
    {
        while (1) {
            if (count($this->workerIds)) {
                if ($ret = Process::wait()) {
                    $this->rebootProcess($ret);
                }
            } else {
                break;
            }
        }
    }

    /**
     * @link https://wiki.swoole.com/wiki/page/220.html
     */
    public function asyncWait()
    {
        Process::signal(SIGCHLD, function($sig) {
            //必须为false，非阻塞模式
            while($ret =  Process::wait(false)) {
                echo "PID={$ret['pid']}\n";
            }
        });
    }
}
