<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-27
 * Time: 9:54
 */

namespace Inhere\Server\Components;

use Swoole\Process as SwProcess;

/**
 * Class ProcessManager
 * @package Inhere\Server\Components
 */
class ProcessManager
{
    /** @var int master pid */
    public $pid = 0;

    /** @var array  */
    public $works = [];

    /** @var int  */
    public $maxPrecess = 1;

    /** @var int  */
    public $newIndex = 0;

    public function __construct()
    {
        try {
            swoole_set_process_name(sprintf('php-ps:%s', 'master'));
            $this->pid = posix_getpid();
            $this->run();
            $this->processWait();
        } catch (\Throwable $e) {
            exit('ALL ERROR: ' . $e->getMessage());
        }
    }

    public function run()
    {
        for ($i = 0; $i < $this->maxPrecess; $i++) {
            $this->CreateProcess();
        }
    }

    public function CreateProcess($index = null)
    {
        $process = new SwProcess(function (SwProcess $worker) use ($index) {
            if (null === $index) {
                $index = $this->newIndex;
                $this->newIndex++;
            }

            swoole_set_process_name(sprintf('php-ps:%s', $index));

            for ($j = 0; $j < 16000; $j++) {
                $this->checkMpid($worker);
                echo "msg: {$j}\n";
                sleep(1);
            }
        }, false, false);

        $pid = $process->start();
        $this->works[$index] = $pid;

        return $pid;
    }

    /**
     * @param SwProcess $worker
     */
    public function checkMpid($worker)
    {
        if (!SwProcess::kill($this->pid, 0)) {
            $worker->exit();
            // 这句提示,实际是看不到的.需要写到日志中
            echo "Master process exited, I [{$worker['pid']}] also quit\n";
        }
    }

    public function rebootProcess($ret)
    {
        $pid = $ret['pid'];
        $index = array_search($pid, $this->works, true);

        if ($index !== false) {
            $new_pid = $this->CreateProcess((int)$index);
            echo "rebootProcess: {$index}={$new_pid} Done\n";

            return;
        }

        throw new \RuntimeException('rebootProcess Error: no pid');
    }

    public function processWait()
    {
        while (1) {
            if (count($this->works)) {
                if ($ret = SwProcess::wait()) {
                    $this->rebootProcess($ret);
                }
            } else {
                break;
            }
        }
    }
}