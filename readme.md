# php server

## run 

- create a instance 

```
$server = new TcpServer($config);

....

TcpServer::run();
```

- auto create instance

```
TcpServer::run();
```


## swoole supported event

```
'onStart' // 'onMasterStart',
'onShutdown' // 'onMasterStop',

'onManagerStart',
'onManagerStop',

'onWorkerStart',
'onWorkerStop',
'onWorkerError',

// 当工作进程收到由sendMessage发送的管道消息时会触发onPipeMessage事件。worker/task进程都可能会触发
'onPipeMessage',

// hhtp server 可注册的事件
'onRequest',

// TCP server 可注册的事件
'onConnect',
'onReceive',
'onClose',

// UDP server 可注册的事件
'onPacket',
// 'onClose',

// WebSocket server 可注册的事件
'onOpen',
'onHandShake',
'onMessage',

// Task 任务相关 (若配置了 task_worker_num 则必须注册这两个事件)
'onTask',   // 处理异步任务
'onFinish', // 处理异步任务的结
```

## 注意事项

- 在主服务器上追加监听的端口服务的事件不生效

```
//file: server.php 

$mainServer = new swoole_http_server('0.0.0.0', 9501);

// 追加监听tcp端口
// listen 是 addListener 的别名
$port = $mainServer->listen('0.0.0.0', 9601, SWOOLE_SOCK_TCP);
$port->on('receive', function($srv, $fd, $fromId, $data){
    $srv->send($fd, "Server: ".$data);
});

$mainServer->start();
```

在终端运行server: `php server.php`

再开一个终端测试追加监听的tcp服务是否成功

```
telnet 127.0.0.1 9661
text // 发现输入后什么都没有返回
```

最后发现必须要调用 `$port->set()` 才行。增加一行设置 tcp 服务的配置

```
... ...
$port->set([]); // 设置tcp监听的配置，可以覆盖继承的主server(swoole_http_server)配置

$mainServer->start();
```

重新运行server后再测试: 

```
telnet 127.0.0.1 9661
text 
Server text // 返回了消息
```

> NOTICE: 增加端口监听后，必须要调用 `$port->set()`. 不然不会触发监听服务上的事件,即使传入空数组也行，但不能不调用。

