<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace SwoKit\Server\Listener\Port;

use Swoole\Server as SwServer;

/**
 * Class TcpListenerInterface
 * @package SwoKit\Server\Listener\Port
 */
interface TcpListenerInterface //extends InterfacePortListener
{
    /**
     * onConnect
     * 有新的连接进入时，在worker进程中回调
     * @param  SwServer $server
     * @param  int $fd 客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
     *                          发送数据/关闭连接时需要此参数
     */
    public function onConnect(SwServer $server, $fd);

    /**
     * 接收到数据
     *     使用 `fd` 保存客户端IP，`from_id` 保存 `from_fd` 和 `port`
     * @param  SwServer $server
     * @param  int $fd
     * @param  int $fromId
     * @param  mixed $data
     */
    public function onReceive(SwServer $server, $fd, $fromId, $data);

    /**
     * onClose
     * TCP客户端连接关闭后，在worker进程中回调此函数
     * @param  SwServer $server
     * @param  int $fd 客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
     */
    public function onClose(SwServer $server, $fd);

}
