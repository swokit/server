<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/10
 * Time: 下午11:16
 */

namespace Inhere\Server\Process;

use Swoole\Process;

/**
 * Interface ProcessInterface
 * @package Inhere\Server\Process
 */
interface ProcessInterface
{
    const PIPE_NOT_CREATE = 0;
    const PIPE_SOCK_STREAM = 1;
    const PIPE_SOCK_DGRAM = 2;

    /**
     * @param Process $process
     */
    public function started(Process $process);

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return Process
     */
    public function getProcess(): Process;
}
