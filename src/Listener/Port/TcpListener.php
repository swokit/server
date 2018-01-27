<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace Inhere\Server\Listener\Port;

use Swoole\Server as SwServer;

/**
 * Class TcpListener
 * @package Inhere\Server\Listener\Port
 */
class TcpListener extends PortListener implements TcpListenerInterface
{
    // protected $type = 'tcp';

    /**
     * {@inheritDoc}
     */
    public function onConnect(SwServer $server, $fd)
    {
        $this->mgr->log("Has a new client [FD:$fd] connection.");
    }

    /**
     * 接收到数据
     *     使用 `fd` 保存客户端IP，`from_id` 保存 `from_fd` 和 `port`
     * @param  SwServer $server
     * @param  int $fd
     * @param  int $fromId
     * @param  mixed $data
     */
    public function onReceive(SwServer $server, $fd, $fromId, $data)
    {
        $data = trim($data);
        $this->log("Receive data [$data] from client [FD:$fd].");
        $server->send($fd, "I have been received your message.\n");

        // $this->onTaskReceive($server, $fd, $fromId, $data);

        // 群发收到的消息
        // $this->reloadWorker->write($data);
    }

    /**
     * {@inheritDoc}
     */
    public function onClose(SwServer $server, $fd)
    {
        $this->mgr->log("The client [FD:$fd] connection closed.");
    }
}
