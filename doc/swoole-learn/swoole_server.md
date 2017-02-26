# swoole_server 

`swoole_server/swoole_http_server/swoole_websocket_server`

see [https://wiki.swoole.com/wiki/page/p-server.html](https://wiki.swoole.com/wiki/page/p-server.html)

## swoole_server 属性

see [https://wiki.swoole.com/wiki/page/157.html](https://wiki.swoole.com/wiki/page/157.html)

## 一些swoole_server函数

see [https://wiki.swoole.com/wiki/page/15.html](https://wiki.swoole.com/wiki/page/15.html)

### swoole_server->__construct

```
$serv = new swoole_server(string $host, int $port, int $mode = SWOOLE_PROCESS, int $sock_type = SWOOLE_SOCK_TCP);
```

- $host参数用来指定监听的ip地址，如127.0.0.1，或者外网地址，或者0.0.0.0监听全部地址
    - `IPv4`使用 `127.0.0.1`表示监听本机，`0.0.0.0`表示监听所有地址 
    - `IPv6` 使用 `::1` 表示监听本机，`:: (0:0:0:0:0:0:0:0)` 表示监听所有地址
- $port监听的端口，如9501，**监听小于1024端口需要root权限**，如果此端口被占用server->start时会失败
- $mode运行的模式，swoole提供了3种运行模式，默认为多进程模式
- $sock_type 指定socket的类型，支持TCP/UDP、TCP6/UDP6、UnixSock Stream/Dgram 6种
- 使用`$sock_type | SWOOLE_SSL`可以启用SSL加密。启用SSL后必须配置[ssl_key_file和ssl_cert_file](https://wiki.swoole.com/wiki/page/318.html)

构造函数中的参数与`swoole_server::addlistener`中是完全相同的.

1.7.11后增加了对Unix Socket的支持，详细请参见 [/wiki/page/16.html](https://wiki.swoole.com/wiki/page/16.html)

高负载的服务器，请务必调整[Linux内核参数](https://wiki.swoole.com/wiki/page/11)

[3种Server运行模式介绍](https://wiki.swoole.com/wiki/page/353.html)

> Swoole1.6版本之后PHP版本去掉了线程模式，原因是php的内存管理器在多线程下容易发生错误,
线程模式仅供C++中使用,BASE模式在1.6.4版本之后也可是使用多进程，设置worker_num来启用

### swoole_server->set 

swoole_server->set函数用于设置swoole_server运行时的各项参数。服务器启动后通过 `$serv->setting` 来访问set函数设置的参数数组。

> swoole_server->set只能在swoole_server->start前调用

配置项说明请看[swoole_server_config.md](swoole_server_config.md)

### swoole_server->on

注册Server的事件回调函数。

```
bool swoole_server->on(string $event, mixed $callback);
```

- 第1个参数是回调的名称, 大小写不敏感，具体内容参考回调函数列表，事件名称字符串不要加on
- 第2个函数是回调的PHP函数，可以是函数名的字符串，类静态方法，对象方法数组，匿名函数。

```
$serv = new swoole_server("127.0.0.1", 9501);
$serv->on('connect', function ($serv, $fd){
    echo "Client:Connect.\n";
});
$serv->on('receive', function ($serv, $fd, $from_id, $data) {
    $serv->send($fd, 'Swoole: '.$data);
    $serv->close($fd);
});
$serv->on('close', function ($serv, $fd) {
    echo "Client: Close.\n";
});
$serv->start();
```

### swoole_server->addListener/listen

Swoole提供了 `swoole_server::addListener`来增加监听的端口。 业务代码中可以通过调用 `swoole_server::connection_info`来获取某个连接来自于哪个端口。

函数原型：
```
bool|Swoole\Server\Port swoole_server->addListener(string $host, int $port, $type = SWOOLE_SOCK_TCP);
bool|Swoole\Server\Port swoole_server->listen(string $host, int $port, int $type);
```

- 监听1024以下的端口需要root权限
- 1.8.0版本增加了多端口监听的功能，监听成功后会返回`Swoole\Server\Port`对象
- 在此对象上可以设置另外的事件回调函数和运行参数(e.g onReceive,onConnect ...)
- 监听失败返回false，可调用`getLastError`方法获取错误码
- listen 是 addlistener 的别名。

您可以混合使用UDP/TCP，同时监听内网和外网端口。 示例：

```
$serv->addlistener("127.0.0.1", 9502, SWOOLE_SOCK_TCP);
$serv->addlistener("192.168.1.100", 9503, SWOOLE_SOCK_TCP);
$serv->addlistener("0.0.0.0", 9504, SWOOLE_SOCK_UDP);
//UnixSocket Stream
$serv->addlistener("/var/run/myserv.sock", 0, SWOOLE_UNIX_STREAM);
//TCP + SSL
$serv->addlistener("127.0.0.1", 9502, SWOOLE_SOCK_TCP | SWOOLE_SSL);
```

> listen方法在swoole-1.7.9以上版本可用

### swoole_server->addProcess

添加一个用户自定义的工作进程。

```
bool swoole_server->addProcess(swoole_process $process);
```

- `$process` 为 `swoole_process` 对象，注意 **不需要执行start** 。在swoole_server启动时会自动创建进程，并执行指定的子进程函数
创建的子进程可以调用$server对象提供的各个方法，如`connection_list/connection_info/stats`
- 在 `worker/task` 进程中可以调用 `$process` 提供的方法与子进程进行通信
- 在用户自定义进程中可以调用 `$server->sendMessage` 与 `worker/task` 进程通信

此函数通常用于创建一个特殊的工作进程，用于监控、上报或者其他特殊的任务。

- 子进程会托管到Manager进程，如果发生致命错误，manager进程会重新创建一个
- 子进程内不能使用 `swoole_server->task/taskwait` 接口
- 此函数在swoole-1.7.9以上版本可用

示例程序

```
$server = new swoole_server('127.0.0.1', 9501);

$process = new swoole_process(function($process) use ($server) {
    while (true) {
        $msg = $process->read();
        foreach($server->connections as $conn) {
            $server->send($conn, $msg);
        }
    }
});

$server->addProcess($process);

$server->on('receive', function ($serv, $fd, $from_id, $data) use ($process) {
    //群发收到的消息
    $process->write($data);
});

$server->start();
```

### swoole_server->start

启动server，监听所有TCP/UDP端口，函数原型：`bool swoole_server->start()`

启动成功后会创建 `worker_num + 2` 个进程。即是： `主进程 + Manager进程 + worker_num个Worker进程`。

启用task_worker会增加相应数量的子进程
函数列表中start之前的方法仅可在start调用前使用，在start之后的方法仅可在start调用后使用

#### 主进程

主进程内有多个Reactor线程，基于`epoll/kqueue`进行网络事件轮询。收到数据后转发到worker进程去处理

#### Manager进程

对所有worker进程进行管理，worker进程生命周期结束或者发生异常时自动回收，并创建新的worker进程

#### worker进程

对收到的数据进行处理，包括协议解析和响应请求。
启动失败扩展内会抛出致命错误，请检查php error_log的相关信息。errno={number}是标准的Linux Errno，可参考相关文档。

如果开启了log_file设置，信息会打印到指定的Log文件中。默认打印到屏幕

如果想要在开机启动时，自动运行你的Server，可以在`/etc/rc.local`文件中加入

```
/usr/bin/php /data/webroot/site/server.php
```

### swoole_server->reload

重启所有worker进程。 信息较多移到 [swoole_server-reload.md](swoole_server-reload.md)

### swoole_server->stop

使当前worker进程停止运行，并立即触发onWorkerStop回调函数。

```
function swoole_server->stop();
```

使用此函数代替`exit/die`结束Worker进程的生命周期
如果要结束其他Worker进程，可以在stop里面加上worker_id作为参数 或者使用 `swoole_process::kill($worker_pid)`

> 此方法在1.8.2或更高版本可用


### swoole_server->tick

tick定时器，可以自定义回调函数。此函数是 swoole_timer_tick 的别名。

> worker进程结束运行后，所有定时器都会自动销毁
`tick/after` 定时器不能在 `swoole_server->start` 之前使用

- 在onReceive中使用

```
function onReceive($server, $fd, $from_id, $data) {
    $server->tick(1000, function() use ($server, $fd) {
        $server->send($fd, "hello world");
    });
}
```

- 在onWorkerStart中使用

> 低于1.8.0版本task进程不能使用tick/after定时器，所以需要使用 `$serv->taskworker` 进行判断

task进程可以使用 `addtimer` 间隔定时器

```
function onWorkerStart(swoole_server $serv, $worker_id)
{
    if (!$serv->taskworker) {
        $timerId = $serv->tick(1000, function ($id) {
            var_dump($id);
        });
    } else {
        $serv->addtimer(1000);
    }
}
```

### swoole_server->after

在指定的时间后执行函数，需要swoole-1.7.7以上版本。

```
swoole_server->after(int $after_time_ms, mixed $callback_function);
```

after函数是一个一次性定时器，执行完成后就会销毁。此方法是 `swoole_timer_after` 函数的别名

- `$after_time_ms` 指定时间，单位为毫秒
- `$callback_function` 时间到期后所执行的函数，必须是可以调用的。callback函数不接受任何参数
- 低于1.8.0版本task进程不支持after定时器，仅支持addtimer定时器

> $after_time_ms 最大不得超过 86400000

### swoole_server->defer

延后执行一个PHP函数。Swoole底层会在EventLoop循环完成后执行此函数。此函数的目的是为了让一些PHP代码延后执行，程序优先处理IO事件。
defer函数的别名是 `swoole_event_defer`

```
function swoole_server->defer(callable $callback);
```

- $callback为可执行的函数变量，可以是字符串、数组、匿名函数

> defer函数在swoole-1.8.0或更高版本可用

使用实例

```
function query($server, $db) {
    $server->defer(function() use ($db) {
        $db->close();
    });
}
```

### swoole_server->clearTimer

清除tick/after定时器，此函数是 `swoole_timer_clear` 的别名。

使用示例：

```
$timer_id = $server->tick(1000, function ($id) {
    $server->clearTimer($id);
});
```

### swoole_server->close

关闭客户端连接，函数原型：

```
bool swoole_server->close(int $fd, bool $reset = false);
```

> swoole-1.8.0或更高版本可以使用$reset

- 操作成功返回true，失败返回false.
- Server主动close连接，也一样会触发onClose事件。
- 不要在close之后写清理逻辑。应当放置到onClose回调中处理
- `$reset` 设置为true会强制关闭连接，丢弃发送队列中的数据

### swoole_server->send

向客户端发送数据，函数原型：

```
bool swoole_server->send(int $fd, string $data, int $reactorThreadId = 0);
```

- $data，发送的数据。TCP协议最大不得超过2M，UDP协议不得超过64K
- 发送成功会返回true,失败会返回false，调用 $server->getLastError()方法可以得到失败的错误码

**TCP服务器**

- send操作具有原子性，多个进程同时调用send向同一个连接发送数据，不会发生数据混杂
- 如果要发送超过2M的数据，可以将数据写入临时文件，然后通过sendfile接口进行发送
- 通过设置`buffer_output_size`参数可以修改发送长度的限制
- 在发送超过8K的数据时，底层会启用Worker进程的共享内存，需要进行一次`Mutex->lock`操作
- 当Worker进程的管道缓存区已满时，发送8K数据将启用临时文件存储

> swoole-1.6以上版本不需要$reactorThreadId

**UDP服务器**

- send操作会直接在worker进程内发送数据包，不会再经过主进程转发
- 使用fd保存客户端IP，from_id保存from_fd和port
- 如果在onReceive后立即向客户端发送数据，可以不传$from_id
- 如果向其他UDP客户端发送数据，必须要传入from_id
- 在外网服务中发送超过64K的数据会分成多个传输单元进行发送，如果其中一个单元丢包，会导致整个包被丢弃。所以外网服务，建议发送1.5K以下的数据包


### swoole_server->sendfile

sendfile函数调用OS提供的sendfile系统调用，由操作系统直接读取文件并写入socket。sendfile只有2次内存拷贝，使用此函数可以降低发送大量文件时操作系统的CPU和内存占用。

```
bool swoole_server->sendfile(int $fd, string $filename);
```

- $filename 要发送的文件路径，如果文件不存在会返回false
- 操作成功返回true，失败返回false

> 此函数与 `swoole_server->send` 都是向客户端发送数据，不同的是sendfile的数据来自于指定的文件。

发送文件到TCP客户端连接。使用示例：

```
$serv->sendfile($fd, __DIR__.'/test.jpg');
```

### swoole_server->sendto

向任意的客户端IP:PORT发送UDP数据包。

函数原型：

```
bool swoole_server->sendto(string $ip, int $port, string $data, int $server_socket = -1);
```

> swoole_server->sendto 在1.7.10+版本可用

- $ip为IPv4字符串，如192.168.1.102。如果IP不合法会返回错误
- $port为 1-65535的网络端口号，如果端口错误发送会失败
- $data要发送的数据内容，可以是文本或者二进制内容
- $server_socket 服务器可能会同时监听多个UDP端口，此参数可以指定使用哪个端口发送数据包

示例：

```
//向IP地址为220.181.57.216主机的9502端口发送一个hello world字符串。
$server->sendto('220.181.57.216', 9502, "hello world");
//向IPv6服务器发送UDP数据包
$server->sendto('2600:3c00::f03c:91ff:fe73:e98f', 9501, "hello world");
```

> server必须监听了UDP的端口，才可以使用`swoole_server->sendto`
server必须监听了UDP6的端口，才可以使用`swoole_server->sendto`向IPv6地址发送数据


### swoole_server->sendwait

阻塞地向客户端发送数据。

有一些特殊的场景，Server需要连续向客户端发送数据，而 `swoole_server->send`数据发送接口是纯异步的，大量数据发送会导致内存发送队列塞满。

使用`swoole_server->sendwait` 就可以解决此问题，`swoole_server->sendwait` 会阻塞等待连接可写。直到数据发送完毕才会返回。

```
bool swoole_server->sendwait(int $fd, string $send_data);
```

> sendwait目前仅可用于SWOOLE_BASE模式

### swoole_server->sendMessage

此函数可以向任意worker进程或者task进程发送消息。在非主进程和管理进程中可调用。收到消息的进程会触发onPipeMessage事件。

```
bool swoole_server->sendMessage(string $message, int $dst_worker_id);
```

- $message为发送的消息数据内容，没有长度限制，但超过8K时会启动内存临时文件
- $dst_worker_id为目标进程的ID，范围是0 ~ (worker_num + task_worker_num - 1)

说明

- 使用 sendMessage 必须注册 **onPipeMessage** 事件回调函数
- 在Task进程内调用`sendMessage`是阻塞等待的，发送消息完成后返回
- 在Worker进程内调用`sendMessage`是异步的，消息会先存到发送队列，可写时向管道发送此消息
- 在User进程(by swoole_process)内调用`sendMessage`底层会自动判断当前的进程是异步还是同步选择不同的发送方式

>sendMessage接口在swoole-1.7.9以上版本可用
MacOS/FreeBSD下超过2K就会使用临时文件存储

实例

```
$serv = new swoole_server("0.0.0.0", 9501);
$serv->set(array(
    'worker_num' => 2,
    'task_worker_num' => 2,
));
$serv->on('pipeMessage', function($serv, $src_worker_id, $data) {
    echo "#{$serv->worker_id} message from #$src_worker_id: $data\n";
});
$serv->on('task', function ($serv, $task_id, $from_id, $data){
    var_dump($task_id, $from_id, $data);
});
$serv->on('finish', function ($serv, $fd, $from_id){

});
$serv->on('receive', function (swoole_server $serv, $fd, $from_id, $data) {
    if (trim($data) == 'task')
    {
        $serv->task("async task coming");
    }
    else
    {
        $worker_id = 1 - $serv->worker_id;
        $serv->sendMessage("hello task process", $worker_id);
    }
});

$serv->start();
```

### swoole_server->exist

检测fd对应的连接是否存在。在1.7.18以上版本可用

```
function swoole_server->exist(int $fd)
```

- `$fd` 对应的TCP连接存在返回true，不存在返回false

> 此接口是基于共享内存计算，没有任何IO操作

### swoole_server->pause

停止接收数据。

```
function swoole_server->pause(int $fd);
```

- $fd为连接的文件描述符

调用此函数后会将连接从EventLoop中移除，不再接收客户端数据。_此函数不影响发送队列的处理_

>pause方法仅可用于BASE模式

### swoole_server->close

恢复数据接收。与pause方法成对使用

```
function swoole_server->resume(int $fd);
```

- $fd为连接的文件描述符

调用此函数后会将连接重新加入到EventLoop中，继续接收客户端数据

> resume方法仅可用于BASE模式

### swoole_server->connection_info/getClientInfo

`connection_info` 函数用来获取连接的信息，别名是 `getClientInfo`. **需要swoole-1.5.8以上版本**

```
function swoole_server->connection_info(int $fd, int $from_id, bool $ignore_close = false)
```

- 如果传入的fd存在，将会返回一个数组
- 连接不存在或已关闭，返回false
- 第3个参数设置为true，即使连接关闭也会返回连接的信息

> connect_time, last_time 在v1.6.10+可用
connection_info可用于UDP服务器，但需要传入from_id参数

```
$fdinfo = $serv->connection_info($fd);
var_dump($fdinfo);
array(5) {
  ["from_id"]=>
  int(3)
  ["server_fd"]=>
  int(14)
  ["server_port"]=>
  int(9501)
  ["remote_port"]=>
  int(19889)
  ["remote_ip"]=>
  string(9) "127.0.0.1"
  ["connect_time"]=>
  int(1390212495)
  ["last_time"]=>
  int(1390212760)
}

$udp_client = $serv->connection_info($fd, $from_id);
var_dump($udp_client);
```

- from_id 来自哪个reactor线程
- server_fd 来自哪个server socket 这里不是客户端连接的fd
- server_port 来自哪个Server端口
- remote_port 客户端连接的端口
- remote_ip 客户端连接的ip
- connect_time 连接到Server的时间，单位秒
- last_time 最后一次发送数据的时间，单位秒
- close_errno 连接关闭的错误码，如果连接异常关闭，close_errno的值是非零，可以参考Linux错误信息列表

### swoole_server->connection_list

用来遍历当前Server所有的客户端连接，connection_list方法是基于共享内存的，不存在IOWait，遍历的速度很快。另外connection_list会返回所有TCP连接，而不仅仅是当前worker进程的TCP连接。

> 需要swoole-1.5.8以上版本
connection_list仅可用于TCP，UDP服务器需要自行保存客户端信息
SWOOLE_BASE模式下只能获取当前进程的连接

函数原型：

```
swoole_server::connection_list(int $start_fd = 0, int $pagesize = 10);
```

- 此函数接受2个参数，第1个参数是起始fd，第2个参数是每页取多少条，最大不得超过100.
- 调用失败返回false,成功将返回一个数字索引数组，元素是取到的$fd。数组会按从小到大排序。最后一个$fd作为新的start_fd再次尝试获取

应用场景: 给现在所有实际链接的用户发送消息,有点类似群发的感觉

示例：

```
$start_fd = 0;
while(true)
{
    $conn_list = $serv->connection_list($start_fd, 10);
    if($conn_list===false or count($conn_list) === 0)
    {
        echo "finish\n";
        break;
    }
    $start_fd = end($conn_list);
    var_dump($conn_list);
    foreach($conn_list as $fd)
    {
        $serv->send($fd, "broadcast");
    }
}
```

### swoole_server->bind

将连接绑定一个用户定义的ID，可以设置`dispatch_mode=5`设置已此ID值进行hash固定分配。 可以保证某一个UID的连接全部会分配到同一个Worker进程。

在默认的dispatch_mode=2设置下，server会按照socket fd来分配连接数据到不同的worker。因为fd是不稳定的，一个客户端断开后重新连接，fd会发生改变。这样这个客户端的数据就会被分配到别的Worker。

使用bind之后就可以按照用户定义的ID进行分配。即使断线重连，相同uid的TCP连接数据会被分配相同的Worker进程。

```
bool swoole_server::bind(int $fd, int $uid)
```

- $fd 连接的文件描述符
- $uid 指定UID

> 同一个连接只能被bind一次，如果已经绑定了uid，再次调用bind会返回false
可以使用$serv->connection_info($fd) 查看连接所绑定uid的值

### swoole_server->stats

得到当前Server的活动TCP连接数，启动时间，accpet/close的总次数等信息。

> stats()方法在1.7.5+后可用

```
array swoole_server->stats();
```

返回的结果数组示例：

```
array (
  'start_time' => 1409831644, // 服务器启动的时间
  'connection_num' => 1,  // 当前连接的数量
  'accept_count' => 1, // 接受了多少个连接
  'close_count' => 0, // 关闭的连接数量
  'tasking_num' => 0, // 当前正在排队的任务数
  // 请求数量
  'request_count' => 1000, // Server收到的请求次数
  'worker_request_count' => 100, // 当前Worker进程收到的请求次数

  // 消息队列状态 swoole-1.8.5版本增加了Task消息队列的统计数据。
  'task_queue_num' => 10, // 消息队列中的Task数量
  'task_queue_bytes' => 65536, // 消息队列的内存占用字节数
);
```

### swoole_server->task

投递一个异步任务到task_worker池中。 与任务相关的信息都移到 [swoole_server-task.md](swoole_server-task.md)

### swoole_server->heartbeat

检测服务器所有连接，并找出已经超过约定时间的连接。如果指定`if_close_connection`，则自动关闭超时的连接。未指定仅返回连接的fd数组。


函数原型：

```
array swoole_server::heartbeat(bool $if_close_connection = true);
```

- $if_close_connection是否关闭超时的连接，默认为true
- 调用成功将返回一个连续数组，元素是已关闭的$fd。失败返回false

> 需要swoole-1.6.10 以上版本 `$if_close_connection` 在1.7.4+可用

示例：

```
$closeFdArr = $serv->heartbeat();
```

### swoole_server->getLastError

获取最近一次操作错误的错误码。业务代码中可以根据错误码类型执行不同的逻辑。

```
function swoole_server->getLastError()
```

返回一个整型数字错误码

常见发送失败错误:

- 1001 连接已经被Server端关闭了，出现这个错误一般是代码中已经执行了`$serv->close()`关闭了某个连接，但仍然调用`$serv->send()`向这个连接发送数据
- 1002 连接已被Client端关闭了，Socket已关闭无法发送数据到对端
- 1003 正在执行close，onClose回调函数中不得使用`$serv->send()`
- 1004 连接已关闭
- 1005 连接不存在，传入 $fd 可能是错误的
- 1008 发送缓存区已满无法执行send操作，出现这个错误表示这个连接的对端无法及时收数据导致发送缓存区已塞满

### swoole_server->getSocket

调用此方法可以得到底层的socket句柄，返回的对象为sockets资源句柄。

> 此方法需要依赖PHP的sockets扩展，并且编译swoole时需要开启--enable-sockets选项

使用`socket_set_option`函数可以设置更底层的一些socket参数。

```
$socket = $server->getSocket();
if (!socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
    echo 'Unable to set option on socket: '. socket_strerror(socket_last_error()) . PHP_EOL;
}
```

- 监听端口

使用listen方法增加的端口，可以使用 `Swoole\Server\Port` 对象提供的 getSocket 方法。

```
$port = $server->listen('127.0.0.1', 9502, SWOOLE_SOCK_TCP);
$socket = $port->getSocket();
```

