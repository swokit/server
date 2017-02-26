# swoole_server 属性

## swoole_server::$setting

`swoole_server::set()`函数所设置的参数会保存到 `swoole_server::$setting` 属性上。在回调函数中可以访问运行参数的值。

> 在swoole-1.6.11+可用

示例：

```
$serv = new swoole_server('127.0.0.1', 9501);
$serv->set(array('worker_num' => 4));

echo $serv->setting['worker_num'];
```

## swoole_server::$master_pid

返回当前服务器主进程的PID。**只能在`onStart/onWorkerStart`之后获取到**

## swoole_server::$manager_pid

返回当前服务器管理进程的PID。**只能在`onStart/onWorkerStart`之后获取到**

## swoole_server::$worker_pid

`int $serv->worker_pid;` 得到当前Worker进程的操作系统进程ID。与posix_getpid()的返回值相同。

## swoole_server::$worker_id

得到当前Worker进程的编号，包括Task进程。

```
int $server->worker_id;
```

这个属性与onWorkerStart时的$worker_id是相同的。

Worker进程ID范围是 `(0, $serv->setting['worker_num'])`

task进程ID范围是 `($serv->setting['worker_num'], $serv->setting['worker_num'] + $serv->setting['task_worker_num'])`

> 工作进程重启后worker_id的值是不变的

## swoole_server::$taskworker

布尔类型

- true表示当前的进程是Task工作进程
- false表示当前的进程是Worker进程

> 此属性在swoole-1.7.15以上版本可用

## swoole_server::$connections

TCP连接迭代器，可以使用foreach遍历服务器当前所有的连接，此属性的功能与`swoole_server->connection_list`是一致的，但是更加友好。遍历的元素为单个连接的fd。

> 注意 `$connections`属性是一个迭代器对象，不是PHP数组，所以不能用var_dump或者数组下标来访问，只能通过foreach进行遍历操作。

```
foreach($server->connections as $fd)
{
    $server->send($fd, "hello");
}

echo "当前服务器共有 ".count($server->connections). " 个连接\n";
```

>此属性在1.7.16以上版本可用
连接迭代器依赖pcre库（不是PHP的pcre扩展），未安装pcre库无法使用此功能
pcre库的安装方法， http://wiki.swoole.com/wiki/page/312.html
