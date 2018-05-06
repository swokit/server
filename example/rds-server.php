<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/6 0006
 * Time: 23:38
 */

use Swoole\Redis\Server;

require dirname(__DIR__) . '/test/boot.php';

// implement a simple redis server.
$rdsServer = \Inhere\Server\TaskServer::create([]);

$rdsServer->addCommand('get', function (Server $server, $fd, $data) {
    if (\count($data) === 0) {
        $server->send(
            $fd,
            Server::format(Server::ERROR, "ERR wrong number of arguments for 'GET' command")
        );
    }

    $key = $data[0];

    if (empty($server->data[$key])) {
        $server->send($fd, Server::format(Server::NIL));
    }

    // swoole 2 需要用 `$server->send()` 返回数据
    $server->send($fd, Server::format(Server::STRING, $server->data[$key]));
});

$rdsServer->addCommand('set', function (Server $server, $fd, $data) {
    if (\count($data) < 2) {
        $server->send(
            $fd,
            Server::format(Server::ERROR, "ERR wrong number of arguments for 'SET' command")
        );
    }

    $key = $data[0];
    $server->data[$key] = $data[1];

    $server->send($fd, Server::format(Server::STATUS, 'OK'));
});

$rdsServer->addCommand('sAdd', function (Server $server, $fd, $data) {
    $total = \count($data);

    if ($total < 2) {
        $server->send(
            $fd,
            Server::format(Server::ERROR, "ERR wrong number of arguments for 'sAdd' command")
        );
    }

    $key = $data[0];

    if (!isset($server->data[$key])) {
        $array[$key] = [];
    }

    $count = 0;
    for ($i = 1; $i < $total; $i++) {
        $value = $data[$i];
        if (!isset($server->data[$key][$value])) {
            $server->data[$key][$value] = 1;
            $count++;
        }
    }

    $server->send($fd, Server::format(Server::INT, $count));
});


$rdsServer->addCommand('sMembers', function (Server $server, $fd, $data) {
    if (\count($data) < 1) {
        $server->send(
            $fd,
            Server::format(Server::ERROR, "ERR wrong number of arguments for 'sMembers' command")
        );
    }

    $key = $data[0];

    if (!isset($server->data[$key])) {
        $this->log("want to get a not exists key '$key'", ['fd' => $fd], 'debug');

        $server->send($fd, Server::format(Server::NIL));
    }

    $server->send($fd, Server::format(Server::SET, array_keys($server->data[$key])));
});

$rdsServer->addCommand('hSet', function (Server $server, $fd, $data) {
    if (\count($data) < 3) {
        $server->send(
            $fd,
            Server::format(Server::ERROR, "ERR wrong number of arguments for 'hSet' command")
        );
    }

    $key = $data[0];

    if (!isset($server->data[$key])) {
        $array[$key] = array();
    }

    $field = $data[1];
    $value = $data[2];

    $count = !isset($server->data[$key][$field]) ? 1 : 0;
    $server->data[$key][$field] = $value;

    $server->send($fd, Server::format(Server::INT, $count));
});

$rdsServer->addCommand('hGetAll', function (Server $server, $fd, $data) {
    if (\count($data) < 1) {
        $server->send(
            $fd,
            Server::format(Server::ERROR, "ERR wrong number of arguments for 'hGetAll' command")
        );
    }

    $key = $data[0];

    if (!isset($server->data[$key])) {
        $this->log("want to get a not exists key '$key'", ['fd' => $fd], 'debug');

        $server->send($fd, Server::format(Server::NIL));
    }

    $server->send($fd, Server::format(Server::MAP, $server->data[$key]));
});

$rdsServer->addCommand('lPush', function (Server $server, $fd, $data) {
    $taskId = $server->task($data);

    if ($taskId === false) {
        $server->send($fd, Server::format(Server::ERROR));
    }

    $this->log('success add a task', ['taskId' => $taskId, 'fd' => $fd], 'debug');

    $server->send($fd, Server::format(Server::INT, $taskId));
});
