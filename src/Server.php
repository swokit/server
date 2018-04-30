<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 12:41
 */

namespace Inhere\Server;

use Inhere\Console\Utils\Show;
use Inhere\Server\Component\FileLogHandler;
use Inhere\Server\Event\SwooleEvent;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 * Class Server - Server Manager
 * @package Inhere\Server
 * Running processes:
 *
 * ```
 * ```
 */
class Server extends AbstractServer
{
    /**
     * @param array $opts
     * @return mixed|LoggerInterface
     */
    protected function makeLogger(array $opts)
    {
        $fileHandler = new FileLogHandler($opts['file'], (int)$opts['level'], (int)$opts['splitType']);
        $mainHandler = new FingersCrossedHandler($fileHandler, (int)$opts['level'], (int)$opts['bufferSize']);
        $fileHandler->setServer($this);

        $logger = new Logger($opts['name'] ?? 'server');
        $logger->pushHandler($mainHandler);

        return $logger;
    }

    /*******************************************************************************
     * start server logic
     ******************************************************************************/

    public function info()
    {
        $this->showInformation();
    }

    public function status()
    {
        $this->showRuntimeStatus();
    }

    /**
     * Show server info
     */
    protected function showInformation()
    {
        $swOpts = $this->config['swoole'];
        $main = $this->config['main_server'];
        $panelData = [
            'System Info' => [
                'PHP Version' => PHP_VERSION,
                'Operate System' => PHP_OS,
            ],
            'Swoole Info' => [
                'version' => SWOOLE_VERSION,
                'coroutine' => class_exists(Coroutine::class, false),
            ],
            'Swoole Config' => [
                'dispatch_mode' => $swOpts['dispatch_mode'],
                'worker_num' => $swOpts['worker_num'],
                'task_worker_num' => $swOpts['task_worker_num'],
                'max_request' => $swOpts['max_request'],
            ],
            'Main Server' => [
                'type' => $main['type'],
                'mode' => $main['mode'],
                'host' => $main['host'],
                'port' => $main['port'],
                'class' => static::class,
            ],
            'Project Config' => [
                'name' => $this->name,
                'path' => $this->config['rootPath'],
                'auto_reload' => $this->config['auto_reload'],
                'pidFile' => $this->config['pidFile'],
            ],
            'Server Log' => $this->config['log'],
        ];


        // 'Server Information'
        Show::mList($panelData, [
            'ucfirst' => false,
        ]);
        // Show::panel($panelData, 'Server Information');
    }

    /**
     * show server runtime status information
     */
    protected function showRuntimeStatus()
    {
        Show::notice('Sorry, The function un-completed!', 0);
    }

    /*******************************************************************************
     * getter/setter methods
     ******************************************************************************/

    /**
     * register a swoole Event Handler Callback
     * @param string $event
     * @param callable|string $handler
     */
    public function onSwoole($event, $handler)
    {
        $this->setSwooleEvent($event, $handler);
    }

    /**
     * @param string $event The event name
     * @param string|\Closure $cb The callback name
     */
    public function setSwooleEvent($event, $cb)
    {
        $event = \trim($event);

        if (!$this->isSwooleEvent($event)) {
            $supported = \implode(',', SwooleEvent::getAllEvents());
            Show::error("You want add a not supported swoole event: $event. supported: \n $supported", -2);
        }

        $this->swooleEvents[$event] = $cb;
    }

    /*******************************************************************************
     * some help method(from swoole)
     ******************************************************************************/

    /**
     * 获取对端socket的IP地址和端口
     * @param int $cid
     * @return array
     */
    public function getPeerName(int $cid): array
    {
        $data = $this->getClientInfo($cid);

        return [
            'ip' => $data['remote_ip'] ?? '',
            'port' => $data['remote_port'] ?? 0,
        ];
    }

    /**
     * @param int $cid
     * @return array
     * [
     *  // 大于0 是webSocket(=2) 等于0 是 http/...
     *  websocket_status => int [可选项] WebSocket连接状态，当服务器是Swoole\WebSocket\Server时会额外增加此项信息
     *  from_id => int
     *  server_fd => int 来自哪个server socket
     *  server_port => int 来自哪个Server端口
     *  remote_port => int 客户端连接的端口
     *  remote_ip => string 客户端连接的ip
     *  connect_time => int 连接到Server的时间，单位秒
     *  last_time => int  最后一次发送数据的时间，单位秒
     *  close_errno => int 连接关闭的错误码，如果连接异常关闭，close_errno的值是非零
     * ]
     */
    public function getClientInfo(int $cid): array
    {
        // @link https://wiki.swoole.com/wiki/page/p-connection_info.html
        return $this->server->getClientInfo($cid);
    }

    /**
     * @return int
     */
    public function getErrorNo(): int
    {
        return $this->server->getLastError();
    }

    /**
     * @return string
     */
    public function getErrorMsg(): string
    {
        $err = error_get_last();

        return $err['message'] ?? '';
    }

    /**
     * @return resource
     */
    public function getSocket(): resource
    {
        return $this->server->getSocket();
    }
}
