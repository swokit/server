<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace SwoKit\Server;

use Swoole\Server;

/*
Tcp config:
    'server' => [
        'host' => '0.0.0.0',
        'port' => '8662',
        'type' => 'tcp', // tcp udp

        // 运行模式
        // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
        'mode' => 'process',

        // use outside's event handler
        'event_handler' => '', // e.g \SwoKit\Server\handlers\TcpServerHandler::class
        'event_list'   => [], // e.g [ 'onReceive', ]
    ],
*/

/**
 * Class TcpServerHandler
 * @package SwoKit\Server
 */
class TcpServer extends AbstractServer
{
    public function __construct(array $config)
    {
        $config['server']['type'] = self::PROTOCOL_TCP;
        parent::__construct($config);
    }

    /**
     * onConnect
     * @param Server $server
     * @param int $fd 客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
     * @param int $fromId
     */
    public function onConnect(Server $server, int $fd, int $fromId)
    {
        $this->log("onConnect: Has a new client [fd:$fd] connection to the main server.(fromId: $fromId,workerId: {$server->worker_id})");
    }

    /**
     * 接收到数据
     *   使用 `fd` 保存客户端IP，`from_id` 保存 `from_fd` 和 `port`
     * @param  Server $server
     * @param  int $fd
     * @param  int $fromId
     * @param  mixed $data
     */
    public function onReceive(Server $server, $fd, $fromId, $data)
    {
        $data = trim($data);
        $this->log("Receive data [$data] from client [FD:$fd]. fromId: $fromId");
        $server->send($fd, "I have been received your message.\n");

        // 群发收到的消息
        // $this->reloadWorker->write($data);

        // 投递异步任务
        // example 1 add task
        // $taskId = $server->task($data);
        // 需swoole-1.8.6或更高版本
        // $server->task("task data", -1, function (Server $server, $task_id, $data) {
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

    /**
     * @param Server $server
     * @param $fd
     */
    public function onClose($server, $fd)
    {
        $this->log("onClose: The client [fd:$fd] connection closed on the main server.(workerId: {$server->worker_id})");
    }
}
