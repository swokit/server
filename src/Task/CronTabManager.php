<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/12
 * Time: 下午8:06
 */

namespace Inhere\Server\Task;

/**
 * Class CronTabManager
 * @package Inhere\Server\Task
 *
 * @ref https://github.com/jobbyphp/jobby/blob/master/src/Jobby.php
 */
class CronTabManager
{

    /**
     * @param string $job
     * @param array  $config
     */
    protected function runUnix($job, array $config)
    {
        $command = $this->getExecutableCommand($job, $config);
        $binary = $this->getPhpBinary();
        $output = $config['debug'] ? 'debug.log' : '/dev/null';

        exec("$binary $command 1> $output 2>&1 &");
    }

    /**
     * @param string $job
     * @param array  $config
     */
    protected function runWindows($job, array $config)
    {
        // Run in background (non-blocking). From
        // http://us3.php.net/manual/en/function.exec.php#43834
        $binary = $this->getPhpBinary();
        $command = $this->getExecutableCommand($job, $config);

        pclose(popen("start \"blah\" /B \"$binary\" $command", "r"));
    }
}
