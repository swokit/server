# swoole task

## swoole_server->task

投递一个异步任务到task_worker池中。此函数是 **非阻塞** 的，执行完毕会立即返回。worker进程可以继续处理新的请求。

> Async Task功能在1.6.4版本增加，默认不启动task功能，需要在手工设置 `task_worker_num` 来启动此功能
task_worker的数量由task_worker_num调整，如 `task_worker_num => 64`，表示启动64个进程来接收异步任务

```
int swoole_server::task(mixed $data, int $dst_worker_id = -1) 
// > 1.8.6
int swoole_server::task(mixed $data, int $dst_worker_id = -1 [, callable $on_finish_callback = null]) 
```

- `$data` 要投递的任务数据，可以为除资源类型之外的任意PHP变量
- `$dst_worker_id` 可以制定要给投递给哪个task进程，传入ID即可，范围是 `0 - ($serv->task_worker_num -1)`
- 调用成功，返回值为整数$task_id，表示此任务的ID。如果有finish回应，`onFinish`回调中会携带$task_id参数. 失败返回 false

未指定目标Task进程，调用task方法会判断Task进程的忙闲状态，底层只会向处于空闲状态的Task进程投递任务。如果所有Task进程均处于忙的状态，底层会轮询投递任务到各个进程。可以使用 `server->stats` 方法获取当前正在排队的任务数量。

1.8.6版本增加了第三个参数，可以直接设置 `onFinish` 函数，如果任务设置了回调函数，Task返回结果时会直接执行指定的回调函数， **不再执行Server的onFinish回调**

> $dst_worker_id在1.6.11+后可用，默认为随机投递
$task_id是从0-42亿的整数，在当前进程内是唯一的
task方法不能在task进程/用户自定义进程中调用

### 相关事件

- onReceive
- onTask
- onFinish

### example

```
$task_id = $serv->task("some data");

// 需swoole-1.8.6或更高版本 
$serv->task("some data", -1, function (swoole_server $serv, $task_id, $data) {
    echo "Task Callback: ";
    var_dump($task_id, $data);
});
```

### 应用场景

此功能用于将慢速的任务异步地去执行，比如一个聊天室服务器，可以用它来进行发送广播。当任务完成时，在task进程中调用 `$serv->finish("finish")` 告诉worker进程此任务已完成。当然swoole_server->finish是可选的。

task底层使用Unix Socket管道通信，是全内存的，没有IO消耗。单进程读写性能可达 100万/s， 不同的进程使用不同的管道通信，可以最大化利用多核。

### 配置参数

`swoole_server->task/taskwait/finish` 3个方法当传入的$data数据超过8K时会启用临时文件来保存。当临时文件内容超过 `server->package_max_length` 时底层会抛出一个警告。此警告不影响数据的投递，过大的Task可能会存在性能问题。

```
WARN: task package is too big.
```

> server->package_max_length 默认为2M

### 注意事项

- 使用 `swoole_server_task` 必须为Server设置 `onTask` 和 `onFinish` 回调，否则swoole_server->start会失败
- task操作的次数必须小于onTask处理速度，如果投递容量超过处理能力，task会塞满缓存区，导致worker进程发生阻塞。worker进程将无法接收新的请求
- 使用addProcess添加的用户进程中无法使用task投递任务，请使用 `sendMessage` 接口与工作进程通信

## swoole_server->taskwait

函数原型：

```
string $result = swoole_server->taskwait(mixed $task_data, float $timeout = 0.5, int $dst_worker_id = -1);
```

taskwait与task方法作用相同，用于投递一个异步的任务到task进程池去执行。与task不同的是taskwait是阻塞等待的，直到任务完成或者超时返回。

- `$result` 为任务执行的结果，由`$serv->finish`函数发出。如果此任务超时，这里会返回false。
- 第3个参数可以制定要给投递给哪个task进程，传入ID即可，范围是 `0 - serv->task_worker_num`

taskwait是阻塞接口，如果你的Server是 **全异步** 的请使用`swoole_server::task`和`swoole_server::finish`,不要使用taskwait

> $dst_worker_id在1.6.11+后可用，默认为随机投递. taskwait方法不能在task进程中调用

## swoole_server->taskWaitMulti

并发执行多个Task

```
array swoole_server->taskWaitMulti(array $tasks, double $timeout);
```

- $tasks 必须为数字索引数组，不支持关联索引数组，底层会遍历$tasks将任务逐个投递到Task进程
- $timeout 为浮点型，单位为秒

执行成功返回一个结果数据，数组的key与传入$tasks的key一致
某个任务执行超时不会影响其他任务，返回的结果数据中将不包含超时的任务(可用 `isset($results[key])` 检查)

> taskWaitMulti接口在1.8.8或更高版本可用

- 使用实例

```
$tasks[] = mt_rand(1000, 9999); //任务1
$tasks[] = mt_rand(1000, 9999); //任务2
$tasks[] = mt_rand(1000, 9999); //任务3
var_dump($tasks);

//等待所有Task结果返回，超时为10s
$results = $serv->taskWaitMulti($tasks, 10.0);

if (!isset($results[0])) {
    echo "任务1执行超时了\n";
}
if (isset($results[1])) {
    echo "任务2的执行结果为{$results[1]}\n";
}
if (isset($results[2])) {
    echo "任务3的执行结果为{$results[2]}\n";
}
```

## swoole_server->finish 

此函数用于在task进程中通知worker进程，投递的任务已完成。此函数可以传递结果数据给worker进程。

```
$serv->finish("response");
```

> 注意：使用finish函数必须为Server设置onFinish回调函数。此函数只可用于task进程的onTask回调中

- finish方法可以连续多次调用，Worker进程会多次触发onFinish事件
- 在onTask回调函数中调用过finish方法后，return数据依然会触发onFinish事件(finish + return, 触发2次)

> swoole_server::finish是可选的。如果worker进程不关心任务执行的结果，不需要调用此函数
在onTask回调函数中return字符串，等同于调用finish
