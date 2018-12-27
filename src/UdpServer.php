<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace Swokit\Server;

use Swokit\Server\Face\UdpHandlerInterface;
use Swoole\Server;

/**
 * Class UdpServerHandler
 * @package Swokit\Server
 * UDP服务器与TCP服务器不同，UDP没有连接的概念。启动Server后，客户端无需Connect，
 * 直接可以向Server监听的端口发送数据包。对应的事件为 onPacket。
 */
class UdpServer extends BaseServer implements UdpHandlerInterface
{
    public function __construct(array $config)
    {
        $config['server']['type'] = self::PROTOCOL_UDP;
        parent::__construct($config);
    }

    /**
     * 接收数据的回调
     * @notice 如果没有此方法，会调用 `onReceive` 替代
     * @param  Server $server
     * @param  string $data 收到的数据内容，可能是文本或者二进制内容
     * @param  array $client 客户端信息包括address/port/server_socket 3项数据
     */
    public function onPacket(Server $server, string $data, array $client)
    {
        // $fd = unpack('L', pack('N', ip2long($client['address'])))[1];
        // $reactorId = ($client['server_socket'] << 16) + $client['port']

        $this->log("received client data: $data, workerId: {$server->worker_id}", $client);
    }
}
