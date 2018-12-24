<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/11
 * Time: 下午11:19
 */

use Swoole\Process;

/**
 * @param Process $process
 */
function process_started(Process $process)
{
    printf("i am worker, pid: {$process->pid}\n");

    for ($j = 0; $j < 60; $j++) {
        printf("\rtimes: %s", $j);
        usleep(100000);
    }

    $process->exit(0);
}

$pid = getmypid();
printf("i am master, pid: {$pid}\n");

$p = new Process('process_started');
$p->start();

if ($ret = Process::wait()) {
    echo "\nworker PID={$ret['pid']} exited. now master exiting\n";
    exit; // 父进程退出
}

Process::signal(SIGTERM, function ($signo) {
    echo "shutdown.\n";
});

Process::signal(SIGCHLD, function ($sig) {
    // 必须为false，非阻塞模式
    while ($ret = Process::wait(false)) {
        echo "\nworker PID={$ret['pid']} exited. now master exiting\n";

        exit; // 父进程退出
    }
});
