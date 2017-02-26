<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/2/18
 * Time: 17:50
 */

namespace inhere\server;

use Swoole\Server as SwServer;
use Swoole\Websocket\Server as SwWSServer;
use Swoole\Websocket\Frame;
use Swoole\Http\Response as SwResponse;
use Swoole\Http\Request as SwRequest;

/**
 * Class WebSocketServer
 * WebSocket base on HTTP
 *
 * OpCode与数据类型
 *  WEBSOCKET_OPCODE_TEXT = 0x1 ，文本数据
 *  WEBSOCKET_OPCODE_BINARY = 0x2 ，二进制数据
 * @package inhere\server
 */
class WSServer extends HttpServer
{
    /**
     * @inheritdoc
     */
    protected function createMainServer()
    {
        $swOpts = $this->config['web_socket_server'];

        if ( !$swOpts['enable'] ) {
            return parent::createMainServer();
        }

        $handleHttp = 'DISABLED';
        $opts = $this->config['http_server'];
        $mode = $opts['mode'] === self::MODE_BASE ? SWOOLE_BASE : SWOOLE_PROCESS;

        // if want enable SSL(https)
        if ( self::PROTOCOL_HTTPS === $opts['type'] ) {
            $this->checkEnvWhenEnableSSL();
            $type = self::PROTOCOL_WSS;
            $socketType = SWOOLE_SOCK_TCP | SWOOLE_SSL;
        } else {
            $type = self::PROTOCOL_WS;
            $socketType = SWOOLE_SOCK_TCP;
        }

        // append current protocol event
        $this->swooleEvents = array_merge($this->swooleEvents, $this->swooleProtocolEvents[self::PROTOCOL_WS]);

        // enable http request handle
        if ( $opts['enable'] ) {
            $handleHttp = 'ENABLED';
            $this->swooleEvents[] = 'onRequest';
        }

        $this->addLog("Create a $type main server on <default>{$opts['host']}:{$opts['port']}</default> (http request handle: $handleHttp)",[], 'info');

        // create swoole WebSocket server
        $server = new SwWSServer($opts['host'], $opts['port'], $mode, $socketType);

        // enable tcp server
        $this->attachTcpOrUdpServer($server);

        return $server;
    }

    /**
     * @return array
     */
    public function getDefaultConfig()
    {
        $config = parent::getDefaultConfig();

        $config['web_socket_server']['enable'] = true;

        return $config;
    }

    public function sendMessageToAll($value='')
    {
        foreach($server->connections as $fd) {
            $server->push($fd, json_encode($data));
        }
    }

//////////////////////////////////////////////////////////////////////
/// swoole event handler
//////////////////////////////////////////////////////////////////////

    ////////////////////// WS Server event //////////////////////

    /**
     * webSocket 连接上时
     * @param  SwServer  $server
     * @param  SwRequest $request
     */
    public function onOpen(SwServer $server, SwRequest $request)
    {
        $this->addLog("Client [fd: {$request->fd}] open connection.");

        // var_dump($request->fd, $request->get, $request->server);
        // $server->push($request->fd, "hello, welcome\n");
    }

    /**
     * webSocket 收到消息时
     * @param  SwServer $server
     * @param           $frame
     */
    public function onMessage(SwServer $server, $frame)
    {
        $this->addLog("[fd: {$frame->fd}] Message: {$frame->data}");

        // $this->handleAllMessage($server, $frame->fd, $frame->data);
        // $server->push($frame->fd, "server: {$frame->data}");
    }

    /**
     * webSocket 建立连接后进行握手。WebSocket服务器已经内置了handshake，
     * 如果用户希望自己进行握手处理，可以设置onHandShake事件回调函数。
     * @param  SwServer $server
     * @param           $frame
     */
    // public function onHandShake(SwServer $server, $frame)
    // {
    //     $this->addLog("[fd: {$frame->fd}] Message: {$frame->data}");
    // }

    /**
     * webSocket断开连接
     * @param  SwServer $server
     * @param  int      $fd
     */
    public function onClose(SwServer $server, $fd)
    {
        /*
        返回数据：
        "websocket_status":0, // 此状态可以判断是否为WebSocket客户端。
        "server_port":9501,
        "server_fd":4,
        "socket_type":1,
        "remote_port":56554,
        "remote_ip":"127.0.0.1",
        "from_id":2,
        "connect_time":1487940465,
        "last_time":1487940465,
        "close_errno":0

        WEBSOCKET_STATUS_CONNECTION = 1，连接进入等待握手
        WEBSOCKET_STATUS_HANDSHAKE = 2，正在握手
        WEBSOCKET_STATUS_FRAME = 3，已握手成功等待浏览器发送数据帧
        */
        $fdInfo = $server->connection_info($fd);

        if ( $fdInfo['websocket_status'] > 0 ) {
            $this->addLog("Client-{$fd} is closed", $fdInfo);
        }
    }
}
