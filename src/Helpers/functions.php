<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-12-08
 * Time: 10:23
 */

namespace Inhere\Server\Func;

use Swoole\Coroutine;

function app_server($port, $host = '0.0.0.0', array $config = [])
{

}

/**
 * @todo
 * @param string $file
 * @param array $data
 */
function include_file(string $file, array $data = [])
{
    $fp = fopen($file, 'r');

    Coroutine::fread($fp);
}