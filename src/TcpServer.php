<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/2/18
 * Time: 17:50
 */

namespace inhere\server;

use inhere\console\utils\Interact;
use inhere\librarys\env\Server as ServerEnv;

use Swoole\Process as SwProcess;
use Swoole\Server as SwServer;
use Swoole\Server\Port as SwServerPort;

/**
 * Class TcpServer
 *
 * @package inhere\server
 * @link https://wiki.swoole.com/wiki/page/p-instruction.html
 *
 * 'onConnect',
 * 'onClose',
 * 'onReceive', // for TCP, 也可投递异步任务
 *
 * 这几个仅对TCP服务有效
 *
 * 'onPacket',  // for UDP
 *
 * UDP服务器与TCP服务器不同，UDP没有连接的概念。
 * 启动Server后，客户端无需Connect，直接可以向Server监听的端口发送数据包。对应的事件为onPacket。
 */
class TcpServer extends AServerManager
{
    /**
     * @inheritdoc
     */
    protected function createMainServer()
    {
        $opts = $this->config['tcp_server'];
        $type = $opts['type'];
        $mode = $opts['mode'] === self::MODE_BASE ? SWOOLE_BASE : SWOOLE_PROCESS;

        if ( $type === self::PROTOCOL_UDP ) {
            $socketType = SWOOLE_SOCK_UDP;
        } else {
            $type = self::PROTOCOL_TCP;
            $socketType = SWOOLE_SOCK_TCP;
        }

        // append current protocol event
        $this->addSwooleEvents($this->swooleProtocolEvents[$type]);

        $this->addLog("Create a $type server on <default>{$opts['host']}:{$opts['port']}</default>", [], 'info');

        $server = new SwServer($opts['host'], $opts['port'], $mode, $socketType);

        return $server;
    }

    /**
     * register Swoole Port Events
     * @param  SwServerPort $port Port instance or port server name.
     * @param  array  $events
     */
    public function registerListenerEvents($port, array $events)
    {
        foreach ($events as $event => $method ) {
            // ['onConnect'] --> 'Connect' , 'onConnect
            if ( is_int($event) ) {
                $event = substr($method,2);
            }

            // e.g $server->on('Request', [$this, 'onRequest']);
            if ( method_exists($this, $method) ) {
                $port->on($event, [$this, $method]);
            }
        }
    }

    public function beforeServerStart(\Closure $callback = null)
    {
        parent::beforeServerStart($callback);

        // you can override it on the subclass.
        $this->createReloadWorker($this->server);
    }

    public function createApplication(\Closure $callback = null)
    {
        if ($callback) {
            return $callback($this);
        }

        return null;
    }

//////////////////////////////////////////////////////////////////////
/// swoole event handler
//////////////////////////////////////////////////////////////////////

    public function onConnect(SwServer $server, $fd)
    {
        $this->addLog("Has a new client [FD:$fd] connection.");
    }

    /**
     * 接收到数据
     *     使用 `fd` 保存客户端IP，`from_id` 保存 `from_fd` 和 `port`
     * @param  SwServer $server
     * @param  int           $fd
     * @param  int           $fromId
     * @param  mixed         $data
     */
    public function onReceive(SwServer $server, $fd, $fromId, $data)
    {
        $this->addLog("Receive data [$data] from client [FD:$fd].");

        // $server->send($fd, 'I have been received your message.');

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
        $this->addLog("The client [FD:$fd] connection closed.");
    }

    /**
     * 当socket_type 为 udp 时，接收数据的回调
     * @notice 如果没有此方法，会调用 `onReceive` 替代
     * @param SwServer $server
     */
    public function onPacket(SwServer $server)
    {
    }

    /**
     * Show server info
     */
    protected function showInformation()
    {
        $sEnv = new ServerEnv;
        $swOpts = $this->config->get('swoole');
        $http = $this->config->get('http_server');
        unset($http['static_setting']);
        $panelData = [
            'Operate System' => $sEnv->get('os'),
            'PHP Version' => PHP_VERSION,
            'Swoole Info' => [
                'version' => SWOOLE_VERSION,
                'coroutine' => class_exists('\Swoole\Coroutine', false) ? 'yes' : 'no',
            ],
            'Swoole Config' => [
                'dispatch_mode'   => $swOpts['dispatch_mode'],
                'worker_num'      => $swOpts['worker_num'],
                'task_worker_num' => $swOpts['task_worker_num'],
                'max_request'     => $swOpts['max_request'],
            ],
            'TCP Server' => $this->config->get('tcp_server'),
            'HTTP Server' => $http,
            'WebSocket Server' => $this->config->get('web_socket_server'),
            'Server Class' => '<primary>' . static::class . '</primary>',
            'Project Config' => [
                'name' => $this->name,
                'path' => $this->config->get('root_path'),
                'auto_reload' => $this->config->get('auto_reload'),
            ],
        ];

        Interact::panel($panelData, 'Server Information');

        parent::showInformation();
    }

    public function getDefaultConfig()
    {
        return [
            // basic config
            'name' => '',
            'debug' => false,
            'root_path' => '',
            'auto_reload' => true, // will create a process auto reload server
            'pid_file'  => '/tmp/swoole_server.pid',
            // 当前server的日志配置(不是swoole的日志)
            'log_service' => [
                // 'name' => 'swoole_server_log'
                // 'basePath' => PROJECT_PATH . '/temp/logs/test_server',
                // 'logThreshold' => 0,
            ],

            // the tcp server setting
            'tcp_server' => [
                'enable' => true,
                'host' => '0.0.0.0',
                'port' => '9661',

                // 运行模式
                // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
                'mode' => 'process',

                // the socket type: tcp|udp
                'type' => 'tcp',
            ],
            // the webSocket server setting
            // webSocket server will listen same host and port as http_server
            'web_socket_server' => [
                'enable' => false,
            ],

            // the swoole runtime setting
            'swoole' => [
                // 'user'    => '',
                'worker_num'    => 4,
                'task_worker_num' => 2, // 启用 task worker,必须为Server设置onTask和onFinish回调
                'daemonize'     => 0,
                'max_request'   => 1000,
                'dispatch_mode' => 1,
                // 'log_file' , // '/tmp/swoole.log', // 不设置log_file会打印到屏幕
            ],
        ];
    }


}
