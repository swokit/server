<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\extend;

use Swoole\Server as SwServer;
use Swoole\Websocket\Frame;
use Swoole\Websocket\Server as SwWSServer;
use Swoole\Http\Response as SwResponse;
use Swoole\Http\Request as SwRequest;

/**
 * Class WSServerHandler
 * @package inhere\server\handlers
 *
 */
class WwbSocketServer extends HttpServer
{
    /**
     * frame list
     * @var array
     */
    public $frames = [];

    /**
     * @var array
     */
    public $connections = [];

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        parent::init();

        $this->options['response'] = array_merge([
            'keep_alive' => 1,
            'heart_time' => 1,
            'max_connect' => 10000,
            'max_frame_size' => 2097152,
        ], $this->options['response']);
    }

    /**
     * 处理http请求(如果需要的话)
     * @NOTICE 需要在注册此handler时，添加 'onRequest' 事件
     * @inheritdoc
     */
    public function onRequest(SwRequest $request, SwResponse $response)
    {
        // $response->end('Not found');
        parent::onRequest($request, $response);
    }

    ////////////////////// WS Server event //////////////////////

    /**
     * webSocket 连接上时
     * @param  SwWSServer $server
     * @param  SwRequest $request
     */
    public function onOpen(SwWSServer $server, SwRequest $request)
    {
        $this->rid = base_convert(str_replace('.', '', microtime(1)), 10, 16) . "0{$request->fd}";

        $this->log("onOpen: Client [fd:{$request->fd}] open connection.");

        // var_dump($request->fd, $request->get, $request->server);
        $server->push($request->fd, "hello, welcome\n");
    }

    /**
     * webSocket 收到消息时
     * @param  SwWSServer $server
     * @param  Frame $frame
     */
    public function onMessage(SwWSServer $server, Frame $frame)
    {
        $this->log("onMessage: Client [fd:{$frame->fd}] send message: {$frame->data}");

        // send message to all
        // ServerHelper::broadcastMessage($server, $frame->data);

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

        // is socket request
        if ($fdInfo['websocket_status'] > 0) {
            $this->log("onClose: Client #{$fd} is closed", $fdInfo);
        }
    }

    /**
     * send message to all client user
     * @param SwWSServer $server
     * @param array $data
     */
    public function broadcast(SwWSServer $server, $data)
    {
        foreach ($server->connections as $fd) {
            $server->push($fd, json_encode((array)$data));
        }
    }
}
