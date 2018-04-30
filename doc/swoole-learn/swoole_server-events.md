# 事件回调函数

## 介绍

see [https://wiki.swoole.com/wiki/page/41.html](https://wiki.swoole.com/wiki/page/41.html)

swoole_server是事件驱动模式，所有的业务逻辑代码必须写在事件回调函数中。当特定的网络事件发生后，swoole底层会主动回调指定的PHP函数。

在swoole中共支持13种事件，具体详情请参考各个页面详细页. PHP语言有4种回调函数的写法

**事件执行顺序**

- 所有事件回调均在 `$server->start` 后发生
- 服务器关闭程序终止时最后一次事件是 `onShutdown`
- 服务器启动成功后，`onStart/onManagerStart/onWorkerStart`会在不同的进程内并发执行。
- `onReceive/onConnect/onClose/onTimer`在worker进程(包括task进程)中各自触发
- `worker/task` 进程启动/结束时会分别调用`onWorkerStart/onWorkerStop`
- onTask 事件仅在task进程中发生
- onFinish 事件仅在worker进程中发生

> onStart/onManagerStart/onWorkerStart 3个事件的执行顺序是不确定的

**异常捕获**

- swoole不支持 set_exception_handler函数
- 如果你的PHP代码有抛出异常逻辑，必须在事件回调函数顶层进行try/catch来捕获异常

```
$serv->on('Timer', function() {
    try {
        //some code
    } catch(Exception $e) {
        //exception code
    }
}
```

## 事件列表

```
'onStart' // 'onStart',
'onShutdown' // 'onShutdown',

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

### onStart

Server启动在主进程的主线程回调此函数，函数原型

```
function onStart(swoole_server $server);
```

在此事件之前Swoole Server已进行了如下操作

- 已创建了manager进程
- 已创建了worker子进程
- 已监听所有TCP/UDP端口
- 已监听了定时器
- 接下来要执行,主Reactor开始接收事件，客户端可以connect到Server

> onStart回调中，仅允许echo、打印Log、修改进程名称。不得执行其他操作。`onWorkerStart`和`onStart`回调是在不同进程中并行执行的，不存在先后顺序。

可以在onStart回调中，将`$serv->master_pid` 和 `$serv->manager_pid`的值保存到一个文件中。这样可以编写脚本，向这两个PID发送信号来实现关闭和重启的操作。

> 从1.7.5+ Master进程内不再支持定时器，onMasterConnect/onMasterClose2个事件回调也彻底移除。Master进程内不再保留任何PHP的接口。

onStart事件在Master进程的主线程中被调用。

> 在onStart中创建的全局资源对象不能在worker进程中被使用，因为发生onStart调用时，worker进程已经创建好了。
新创建的对象在主进程内，worker进程无法访问到此内存区域。
因此全局对象创建的代码需要放置在`swoole_server_start`之前。

### onShutdown

此事件在Server结束时发生，函数原型

```
function onShutdown(swoole_server $server);
```

在此之前Swoole Server已进行了如下操作

- 已关闭所有线程
- 已关闭所有worker进程
- 已close所有`TCP/UDP`监听端口
- 已关闭主Rector

> 强制kill进程不会回调onShutdown，如`kill -9`
需要使用`kill -15`来发送SIGTREM信号到主进程才能按照正常的流程终止

### onManagerStart

当管理进程启动时调用它，函数原型：

```
void onManagerStart(swoole_server $serv);
```

在这个回调函数中可以修改管理进程的名称。

> 注意manager进程中不能添加定时器,manager进程中可以调用task功能

### onManagerStop 

当管理进程结束时调用它，函数原型：

```
void onManagerStop(swoole_server $serv);
```

### onWorkerStart 

此事件在worker进程/task进程启动时发生。这里创建的对象可以在进程生命周期内使用。原型：

```
function onWorkerStart(swoole_server $server, int $worker_id);
```

- swoole1.6.11之后task_worker中也会触发onWorkerStart
- 发生PHP致命错误或者代码中主动调用exit时，Worker/Task进程会退出，管理进程会重新创建新的进程
- onWorkerStart/onStart是并发执行的，没有先后顺序

通过 `$worker_id` 参数的值来判断worker是普通worker还是task_worker。`$worker_id>= $serv->setting['worker_num']`时表示这个进程是task_worker。也可以用属性 `$serv->taskWorker` 判断。

下面的示例用于为task_worker和worker进程重命名。

```
$serv->on('WorkerStart', function ($serv, $worker_id){
    global $argv;
    if($worker_id >= $serv->setting['worker_num']) {
        swoole_set_process_name("php {$argv[0]} task worker");
    } else {
        swoole_set_process_name("php {$argv[0]} event worker");
    }
});
```

**应用提示(自动代码重载)**

如果想使用 `swoole_server_reload` 实现代码重载入，必须在`workerStart`中require你的业务文件，而不是在文件头部。 在onWorkerStart调用之前已包含的文件，不会重新载入代码。

可以将公用的，不易变的php文件放置到 `onWorkerStart`之前。 这样虽然不能重载入代码，但所有worker是共享的，不需要额外的内存来保存这些数据。
onWorkerStart之后的代码每个worker都需要在内存中保存一份

- `$worker_id` 是一个从 `0-$worker_num` 之间的数字，表示这个worker进程的ID
- `$worker_id` 和进程PID没有任何关系. `$serv->worker_pid` 可获取 pid

