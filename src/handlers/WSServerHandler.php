<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\handlers;

use Swoole\Server as SwServer;
use Swoole\Http\Response as SwResponse;
use Swoole\Http\Request as SwRequest;

/**
 * Class WSServerHandler
 * @package inhere\server\handlers
 *
 */
class WSServerHandler extends AbstractServerHandler
{
    /**
     * 处理http请求(如果需要的话)
     * @param  SwRequest  $request
     * @param  SwResponse $response
     */
    public function onRequest(SwRequest $request, SwResponse $response)
    {
        // var_dump($request->get, $request->post);
        $response->header("Content-Type", "text/html; charset=utf-8");
        $response->end("<h1>Hello Swoole. #".rand(1000, 9999)."\n</h1>");
    }

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
        $server->push($request->fd, "hello, welcome\n");
    }

    /**
     * webSocket 收到消息时
     * @param  SwServer $server
     * @param           $frame
     */
    public function onMessage(SwServer $server, $frame)
    {
        $this->addLog("Client [fd: {$frame->fd}] Message: {$frame->data}");

        // $this->handleAllMessage($server, $frame->fd, $frame->data);
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

        // is socket request
        if ( $fdInfo['websocket_status'] > 0 ) {
            $this->addLog("Client-{$fd} is closed", $fdInfo);
        }
    }
}
