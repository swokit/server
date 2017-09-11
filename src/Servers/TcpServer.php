<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace Inhere\Server\Servers;

use Inhere\Server\BoxServer;
use Swoole\Server as SwServer;

/*
Tcp config:

    'main_server' => [
        'host' => '0.0.0.0',
        'port' => '8662',
        'type' => 'tcp', // tcp udp

        // 运行模式
        // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
        'mode' => 'process',

        // use outside's event handler
        'event_handler' => '', // e.g \Inhere\Server\handlers\TcpServerHandler::class
        'event_list'   => [], // e.g [ 'onReceive', ]
    ],
*/

/**
 * Class TcpServerHandler
 * @package Inhere\Server\Servers
 */
class TcpServer extends BoxServer
{
    public function onConnect(SwServer $server, $fd)
    {
        $this->log("Has a new client [FD:$fd] connection.");
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

        // 群发收到的消息
        // $this->reloadWorker->write($data);

        // 投递异步任务
        // example 1 add task
        // $taskId = $server->task($data);
        // 需swoole-1.8.6或更高版本
        // $server->task("task data", -1, function (SwServer $server, $task_id, $data) {
        //     echo "Task Callback: ";
        //     var_dump($task_id, $data);
        // });

        // example 3 use sendMessage()
        // if (trim($data) == 'task') {
        //     $taskId = $server->task("async task coming");
        // } else {
        //     $worker_id = 1 - $server->worker_id;
        //     // can trigger onPipeMessage event.
        //     $server->sendMessage("hello task process", $worker_id);
        // }
    }

    public function onClose(SwServer $server, $fd)
    {
        $this->log("The client [FD:$fd] connection closed.");
    }
}
