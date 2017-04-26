<?php

$table = new swoole_table(1024);
$table->column('fd', swoole_table::TYPE_INT);
$table->create();

$ws = new swoole_websocket_server("0.0.0.0", 9502);
$ws->table = $table;;

//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {

    $ws->table->set($request->fd, array('fd' => $request->fd));//获取客户端id插入table

    foreach ($ws->table as $u) {
        var_dump($u); //输出整个table
    }
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {
    echo $frame->fd . ":{$frame->data}";

    foreach ($ws->table as $u) {
        $ws->push($u['fd'], $frame->data);//消息广播给所有客户端
    }
});

//监听WebSocket连接关闭事件
$ws->on('close', function ($ws, $fd) {
    echo "client-{$fd} is closed\n";
    $ws->table->del($fd);//从table中删除断开的id
});
$ws->start();


// 把table想成一个数据库，第一列是主键fd，第二列是自己定义的数据，上面也定义的fd。
