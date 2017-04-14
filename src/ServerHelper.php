<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:11
 */

namespace inhere\server;

use inhere\library\helpers\PhpHelper;

use Swoole\Websocket\Server as SwWSServer;

/**
 * Class ServerHelper
 * @package inhere\server
 */
class ServerHelper
{
    /**
     * 获取资源消耗
     * @param int $startTime
     * @param int $startMem
     * @return array
     */
    public static function runtime($startTime, $startMem)
    {
        // 显示运行时间
        $return['time'] = number_format((microtime(true) - $startTime), 4) . 's';

        $startMem =  array_sum(explode(' ',$startMem));
        $endMem   =  array_sum(explode(' ',memory_get_usage()));

        $return['memory'] = number_format(($endMem - $startMem)/1024) . 'kb';

        return $return;
    }

    /**
     *
     */
    public static function checkRuntimeEnv()
    {
        if ( !PhpHelper::isCli() ) {
            throw new \RuntimeException('Server must run in the CLI mode.');
        }

        if ( !PhpHelper::extIsLoaded('swoole', false) ) {
            throw new \RuntimeException('Run the server, extension \'swoole\' is required!');
        }
    }

    /**
     * send message to all client user
     * @param SwWSServer $server
     * @param array $data
     */
    public static function broadcastMessage(SwWSServer $server, $data)
    {
        foreach($server->connections as $fd) {
            $server->push($fd, json_encode((array)$data));
        }
    }

}
