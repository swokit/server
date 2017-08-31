<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\servers;

use Swoole\Server as SwServer;
use Swoole\Websocket\Frame;
use Swoole\Websocket\Server;
use Swoole\Http\Request;

/**
 * Class WebSocketServer
 * @package inhere\server\servers
 *
 */
class WebSocketServer extends HttpServer
{
    const OPCODE_CONTINUATION_FRAME = 0x0;
    const OPCODE_TEXT_FRAME         = 0x1;
    const OPCODE_BINARY_FRAME       = 0x2;
    const OPCODE_CONNECTION_CLOSE   = 0x8;
    const OPCODE_PING               = 0x9;
    const OPCODE_PONG               = 0xa;

    const CLOSE_NORMAL              = 1000;
    const CLOSE_GOING_AWAY          = 1001;
    const CLOSE_PROTOCOL_ERROR      = 1002;
    const CLOSE_DATA_ERROR          = 1003;
    const CLOSE_STATUS_ERROR        = 1005;
    const CLOSE_ABNORMAL            = 1006;
    const CLOSE_MESSAGE_ERROR       = 1007;
    const CLOSE_POLICY_ERROR        = 1008;
    const CLOSE_MESSAGE_TOO_BIG     = 1009;
    const CLOSE_EXTENSION_MISSING   = 1010;
    const CLOSE_SERVER_ERROR        = 1011;
    const CLOSE_TLS                 = 1015;

    const WEBSOCKET_VERSION         = 13;
    
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    
    /**
     * frame list
     * @var array
     */
    public $frames = [];

    /**
     * @var array
     */
    protected $connections = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [])
    {
        $this->defaultOptions['response'] = [
            'gzip' => true,
            'keep_alive' => 1,
            'heart_time' => 1,
            'max_connect' => 10000,
            'max_frame_size' => 2097152,
        ];

        parent::__construct($config);
    }

    /**
     * {@inheritDoc}
     */
    // public function init()
    // {
    //     parent::init();
    // }

    /**
     * 处理http请求(如果需要的话)
     * @NOTICE 需要在注册此handler时，添加 'onRequest' 事件
     */
//    public function onRequest(Request $request, Response $response)
//    {
        // $response->end('Not found');
//        parent::onRequest($request, $response);
//    }

    ////////////////////// WS Server event //////////////////////

    /**
     * webSocket 连接上时
     * @param  Server $server
     * @param  Request $request
     */
    public function onOpen($server, Request $request)
    {
        $this->log("onOpen: Client [fd:{$request->fd}] open connection.");

        // var_dump($request->fd, $request->get, $request->server);
        $server->push($request->fd, "hello, welcome\n");
    }

    /**
     * webSocket 收到消息时
     * @param  Server $server
     * @param  Frame $frame
     */
    public function onMessage($server, Frame $frame)
    {
        $this->log("onMessage: Client [fd:{$frame->fd}] send message: {$frame->data}");

        // send message to all
        // $this->broadcast($server, $frame->data);

        // send message to fd.
        $server->push($frame->fd, "server: {$frame->data}");
    }

    /**
     * webSocket 建立连接后进行握手。WebSocket服务器已经内置了handshake，
     * 如果用户希望自己进行握手处理，可以设置onHandShake事件回调函数。
     * @param  SwServer $server
     * @param           $frame
     */
    // public function onHandShake(SwServer $server, $frame)
    // {
    //     $this->log("[fd: {$frame->fd}] Message: {$frame->data}");
    // }

    /**
     * webSocket断开连接
     * @param  SwServer $server
     * @param  int $fd
     */
    public function onClose($server, $fd)
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

        // is socket request
        if ($fdInfo['websocket_status'] > 0) {
            $this->log("onClose: Client #{$fd} is closed", $fdInfo);
        }
    }

    /**
     * send message to all client user
     * @param Server $server
     * @param array $data
     */
//    public function broadcast(Server $server, $data)
//    {
//        foreach ($server->connections as $fd) {
//            $server->push($fd, json_encode($data));
//        }
//    }
}
