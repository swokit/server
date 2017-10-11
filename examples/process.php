<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/11
 * Time: 下午8:20
 */

require __DIR__ . '/s-autoload.php';

/**
 * Class MyProcess
 */
class MyProcess extends \Inhere\Server\Process\UserProcess
{
    /**
     * {@inheritDoc}
     */
    public function started(\Swoole\Process $process)
    {
        parent::started($process);

        for ($j = 0; $j < 60; $j++) {
            printf("\rtimes: %s", $j);
            usleep(100000);
        }

        $process->exit(0);
//        exit(0);
    }
}

$p = new MyProcess([
    'name' => 'test',
    'daemon' => 1,
]);

$p->start();
