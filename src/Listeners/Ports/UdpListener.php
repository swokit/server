<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:23
 */

namespace Inhere\Server\Listeners\Ports;

use Swoole\Server as SwServer;

/**
 * Class UdpListener
 * @package Inhere\Server\Listeners\Ports
 */
class UdpListener extends PortListener implements UdpListenerInterface
{
    /**
     * @var string
     */
    protected $type = 'udp';

    /**
     * 接收到UDP数据包时回调此函数，发生在worker进程中
     * @notice 如果没有此方法，会调用 `onReceive` 替代
     * @param  SwServer $server
     * @param  string $data 收到的数据内容，可能是文本或者二进制内容
     * @param  array $clientInfo 客户端信息包括address/port/server_socket 3项数据
     */
    public function onPacket(SwServer $server, $data, array $clientInfo)
    {
        // $fd = unpack('L', pack('N', ip2long($addr['address'])))[1];
        // $reactor_id = ($addr['server_socket'] << 16) + $addr['port']
    }

}
