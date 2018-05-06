<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/11
 * Time: 下午11:33
 */

use Swoole\Process;

Process::signal(SIGALRM, function () {
    static $i = 0;
    echo "#{$i}\talarm\n";
    $i++;
    if ($i > 20) {
        Process::alarm(-1);
        exit;
    }
});

//100ms
Process::alarm(100 * 1000);

