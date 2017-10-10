<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/10
 * Time: 下午11:24
 */

namespace Inhere\Server\Process;

use Inhere\Library\Helpers\ProcessHelper;
use Swoole\Process;

/**
 * Class UserProcess
 * @package Inhere\Server\Process
 */
abstract class UserProcess implements ProcessInterface
{
    /** @var Process  */
    private $process;

    /** @var string  */
    private $name;

    /**
     * class constructor.
     * @param string $name
     * @param bool $redirectIO
     */
    public function __construct(string $name, $redirectIO = false)
    {
        $this->name = $name;

        $this->process = new Process(function (Process $process) {
            if ($this->name) {
                ProcessHelper::setTitle($this->name);
            }

            $this->started($process);
        }, $redirectIO, self::PIPE_SOCK_DGRAM);
    }

    /**
     * start
     * @return int process id
     */
    public function start()
    {
        return $this->process->start();
    }

    /**
     * @param Process $process
     */
    public function started(Process $process)
    {
         swoole_event_add($process->pipe, [$this, 'onPipeRead']);
    }

    abstract public function onPipeRead();

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->process;
    }
}
