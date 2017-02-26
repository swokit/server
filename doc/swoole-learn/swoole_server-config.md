# swoole_server 配置说明 

server 通过 swoole_server->set 函数设置swoole_server运行时的各项参数配置。服务器启动后通过 `$serv->setting` 来访问set函数设置的参数数组。

> swoole_server->set只能在swoole_server->start前调用

示例：

```
$serv->set(array(
    'reactor_num' => 2, //reactor thread num
    'worker_num' => 4,    //worker process num
    'backlog' => 128,   //listen backlog
    'max_request' => 50,
    'dispatch_mode' => 1,
));
```

## 配置项

```
'reactor_num',
'worker_num',
'max_request' => 2000,
'max_conn',
'task_worker_num',
'task_ipc_mode',
'task_max_request',
'task_tmpdir',
'dispatch_mode',
'message_queue_key',
'daemonize' => 1,
'backlog',
'log_file' => [self::class, 'getLogFile'],
'heartbeat_check_interval',
'heartbeat_idle_time',
'open_eof_check',
'open_eof_split',
'package_eof',
'open_length_check',
'package_length_type',
'package_max_length',
'open_cpu_affinity',
'cpu_affinity_ignore',
'open_tcp_nodelay',
'tcp_defer_accept',
'ssl_cert_file',
'ssl_method',
'user',
'group',
'chroot',
'pipe_buffer_size',
'buffer_output_size',
'enable_unsafe_event',
'discard_timeout_request',
'enable_reuse_port',
```

## 配置说明

from [https://wiki.swoole.com/wiki/page/13.html](https://wiki.swoole.com/wiki/page/13.html)

### `reactor_num => 2` 

reactor线程数. 通过此参数来调节poll线程的数量，以充分利用多核(reactor_num和writer_num默认设置为CPU核数)

> 当设定的worker进程数小于reactor线程数时，会自动调低reactor线程的数量

### `worker_num => 4` 

设置启动的worker进程数量。swoole采用固定worker进程的模式。

PHP代码中是全异步非阻塞，worker_num配置为CPU核数的1-4倍即可。如果是同步阻塞，worker_num配置为100或者更高，具体要看每次请求处理的耗时和操作系统负载状况。

> PHP代码也可以使用memory_get_usage来检测进程的内存占用情况，发现接近memory_limit时，调用exit()退出进程。manager进程会回收此进程，然后重新启动一个新的Worker进程。

### `max_request => 2000` 

设置worker进程的最大任务数，默认为0，一个worker进程在处理完超过此数值的任务后将自动退出,manager会重新创建一个worker进程. 进程退出后会释放所有内存和资源。此选项用来防止worker进程内存溢出。

`max_request => 0` 设置为0表示不自动重启。在Worker进程中需要保存连接信息的服务，需要设置为0.

- max_request只能用于同步阻塞、无状态的请求响应式服务器程序
- 在swoole中真正维持客户端TCP连接的是master进程，worker进程仅处理客户端发送来的请求，因为客户端是不需要感知Worker进程重启的
- 纯异步的Server不应当设置max_request
- 使用Base模式时max_request是无效的

> 当worker进程内发生致命错误或者人工执行exit时，进程会自动退出。master进程会重新启动一个新的worker进程来继续处理请求
> onConnect/onClose不增加计数

### max_conn/max_connection

`max_conn => 5000` 此参数用来设置Server最大允许维持多少个tcp连接。超过此数量后，新进入的连接将被拒绝。

- max_connection 最大不得超过操作系统`ulimit -n`的值，否则会报一条警告信息，并重置为`ulimit -n`的值
- max_connection 默认值为 `ulimit -n`的值

### task_worker_num

配置task进程的数量，配置此参数后将会启用task功能。所以swoole_server务必要注册 `onTask/onFinish`2个事件回调函数。如果没有注册，服务器程序将无法启动。

task进程是同步阻塞的，配置方式与worker同步模式一致。

计算方法:

- 单个task的处理耗时，如100ms，那一个进程1秒就可以处理`1/0.1=10`个task
- task投递的速度，如每秒产生2000个task
- 2000/10=200，需要设置 `task_worker_num => 200`，启用200个task进程

> task进程内不能使用`swoole_server->task`方法
task进程内不能使用`mysql-async/redis-async/swoole_event`等异步IO函数

### task_ipc_mode 

设置task进程与worker进程之间通信的方式。

- `1` 使用unix socket通信，默认模式
- `2` 使用消息队列通信
- `3` 使用消息队列通信，并设置为争抢模式

模式2和模式3的不同之处是，模式2支持定向投递，`$serv->task($data, $task_worker_id)`` 可以指定投递到哪个task进程。
模式3是完全争抢模式，task进程会争抢队列，将无法使用定向投递，即使指定了`$task_worker_id`，在模式3下也是无效的。

> 设置为3后，task/taskwait将无法指定目标进程ID

**消息队列模式**

- 消息队列模式使用操作系统提供的内存队列存储数据，未指定 `mssage_queue_key`消息队列Key，将使用私有队列，在Server程序终止后会删除消息队列。
- 指定消息队列Key后Server程序终止后，消息队列中的数据不会删除，因此进程重启后仍然能取到数据
- 可使用`ipcrm -q` 消息队列ID手工删除消息队列数据

### task_max_request

设置task进程的最大任务数。一个task进程在处理完超过此数值的任务后将自动退出。这个参数是为了防止PHP进程内存溢出。如果不希望进程自动退出可以设置为0。

> task_max_request默认为5000，受swoole_config.h的SW_MAX_REQUEST宏控制
1.7.17以上版本默认值调整为0，不会主动退出进程

### task_tmpdir

设置task的数据临时目录，在swoole_server中，如果投递的数据超过8192字节，将启用临时文件来保存数据。这里的`task_tmpdir`就是用来设置临时文件保存的位置。

Swoole默认会使用/tmp目录存储task数据，如果你的Linux内核版本过低，`/tmp`目录不是内存文件系统，可以设置为 `/dev/shm/`

> 需要swoole-1.7.7+

### dispatch_mode

数据包分发策略。可以选择3种类型，默认为2

- `1` 轮循模式，收到会轮循分配给每一个worker进程
- `2` 固定模式，根据连接的文件描述符分配worker。这样可以保证同一个连接发来的数据只会被同一个worker处理
- `3` 抢占模式，主进程会根据Worker的忙闲状态选择投递，只会投递给处于闲置状态的Worker
- `4` IP分配，根据客户端IP进行取模hash，分配给一个固定的worker进程。 可以保证同一个来源IP的连接数据总会被分配到同一个worker进程。算法为 `ip2long(ClientIP) % worker_num`
- `5` UID分配，需要用户代码中调用 `$serv-> bind()` 将一个连接绑定1个uid。然后swoole根据UID的值分配到不同的worker进程。算法为 `UID % worker_num`，如果需要使用字符串作为UID，可以使用 `crc32(UID_STRING)`

>dispatch_mode 4,5两种模式，在 1.7.8以上版本可用

> 抢占式分配，每次都是空闲的worker进程获得数据。很合适`SOA/RPC`类的内部服务框架
当选择为`dispatch=3`抢占模式时，worker进程内发生 `onConnect/onReceive/onClose/onTimer` 会将worker进程标记为忙，不再接受新的请求。reactor会将新请求投递给其他状态为闲的worker进程

注意：

- 如果希望每个连接的数据分配给固定的worker进程，dispatch_mode需要设置为2
- dispatch_mode=1,3时，底层会屏蔽`onConnect/onClose`事件，原因是这2种模式下无法保证`onConnect/onClose/onReceive`的顺序
- 非请求响应式的服务器程序，请不要使用模式1或3

**UDP协议**

- dispatch_mode=2/4/5时为固定分配，底层使用客户端IP取模散列到不同的worker进程，算法为 ip2long(ClientIP) % worker_num
- dispatch_mode=1/3时随机分配到不同的worker进程

**BASE模式**

dispatch_mode配置在BASE模式是无效的，因为BASE不存在投递任务，当Reactor线程收到客户端发来的数据后会立即在当前线程/进程回调`onReceive`，不需要投递Worker进程。

### message_queue_key

设置消息队列的KEY，仅在task_ipc_mode = 2/3时使用。设置的Key仅作为Task任务队列的KEY，此参数的默认值为ftok($php_script_file, 1)

task队列在server结束后不会销毁，重新启动程序后，task进程仍然会接着处理队列中的任务。如果不希望程序重新启动后不执行旧的Task任务。可以手工删除此消息队列。

```
ipcs -q 
ipcrm -Q [msgkey]
```

### daemonize

守护进程化。设置 `daemonize => 1` 时，程序将转入后台作为守护进程运行。长时间运行的服务器端程序必须启用此项。

> 如果不启用守护进程，当ssh终端退出后，程序将被终止运行。

- 启用守护进程后，标准输入和输出会被重定向到 `log_file`
- 如果未设置log_file，将重定向到 /dev/null，所有打印屏幕的信息都会被丢弃

### backlog

Listen队列长度，如`backlog => 128`，此参数将决定最多同时有多少个等待accept的连接。

**关于tcp的backlog**

我们知道tcp有三次握手的过程，`客户端syn=>服务端syn+ack=>客户端ack`，当服务器收到客户端的ack后会将连接放到一个叫做accept queue的队列里面（注1），队列的大小由`backlog`参数和配置`somaxconn` 的最小值决定，我们可以通过`ss -lt`命令查看最终的`accept queue`队列大小，swoole的主进程调用accept（注2）从accept queue里面取走。 

当accept queue满了之后连接有可能成功（注4），也有可能失败，失败后客户端的表现就是连接被重置（注3）或者连接超时，而服务端会记录失败的记录，可以通过 `netstat -s|grep 'times the listen queue of a socket overflowed'`来查看日志。

如果出现了上述现象，你就应该调大该值了。 幸运的是swoole与`php-fpm/apache`等软件不同，并不依赖backlog来解决连接排队的问题。所以基本不会遇到上述现象。

- 注1: linux2.2之后握手过程分为`syn queue`和`accept queue`两个队列, syn queue长度由`tcp_max_syn_backlog`决定。
- 注2: 高版本内核调用的是accept4，为了节省一次set no block系统调用。
- 注3: 客户端收到`syn+ack`包就认为连接成功了，实际上服务端还处于半连接状态，有可能发送rst包给客户端，客户端的表现就是`Connection reset by peer`。
- 注4: 成功是通过tcp的重传机制，相关的配置有`tcp_synack_retries`和`tcp_abort_on_overflow`。

### log_file

log_file => '/data/log/swoole.log', 指定swoole错误日志文件。在swoole运行期发生的异常信息会记录到这个文件中。**默认会打印到屏幕**

注意log_file不会自动切分文件，所以需要定期清理此文件。观察log_file的输出，可以得到服务器的各类异常信息和警告。

log_file中的日志仅仅是做运行时错误记录，没有长久存储的必要。

> 开启守护进程模式后(`daemonize => true`)，标准输出将会被重定向到log_file。在PHP代码中 `echo/var_dump/print`等打印到屏幕的内容会写入到log_file文件

- 日志标号

在日志信息中，进程ID前会加一些标号，表示日志产生的线程/进程类型。

```
# Master进程
$ Manager进程
* Worker进程
^ Task进程
```

- 重新打开日志文件

在服务器程序运行期间日志文件被mv移动或unlink删除后，日志信息将无法正常写入，这时可以向Server发送`SIGRTMIN`信号实现重新打开日志文件。

> 在1.8.10或更高版本可用仅支持Linux平台

### log_level

设置swoole_server错误日志打印的等级，范围是0-5。低于log_level设置的日志信息不会抛出。

```
$serv->set(array(
    'log_level' => 1,
));
```

级别对应

```
0 =>DEBUG
1 =>TRACE
2 =>INFO
3 =>NOTICE
4 =>WARNING
5 =>ERROR
```

> 默认是0 也就是所有级别都打印

### heartbeat_check_interval

启用心跳检测，此选项表示每隔多久轮循一次，单位为秒。

如 `heartbeat_check_interval => 60`，表示每60秒，遍历所有连接，如果该连接在60秒内，没有向服务器发送任何数据，此连接将被强制关闭。

swoole_server并不会主动向客户端发送心跳包，而是被动等待客户端发送心跳。服务器端的`heartbeat_check`仅仅是检测连接上一次发送数据的时间，如果超过限制，将切断连接。

> heartbeat_check仅支持TCP连接

### heartbeat_idle_time

与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间。如

```
array(
    'heartbeat_idle_time' => 600, // TCP连接的最大闲置时间，单位s 
    'heartbeat_check_interval' => 60, //每隔多少秒检测一次，单位秒，Swoole会轮询所有TCP连接，将超过心跳时间的连接关闭掉
);
```

- 表示每60秒遍历一次，一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
- 启用heartbeat_idle_time后，服务器并不会主动向客户端发送数据包

> 如果只设置了`heartbeat_idle_time`未设置`heartbeat_check_interval`底层将不会创建心跳检测线程，PHP代码中可以调用heartbeat方法手工处理超时的连接

### open_eof_check

打开EOF检测，此选项将检测客户端连接发来的数据，当数据包结尾是指定的字符串时才会投递给Worker进程。否则会一直拼接数据包，直到超过缓存区或者超时才会中止。当出错时swoole底层会认为是恶意连接，丢弃数据并强制关闭连接。

```
array(
    'open_eof_check' => true, //打开EOF检测
    'package_eof' => "\r\n", //设置EOF
)
```

常见的`Memcache/SMTP/POP`等协议都是以`\r\n`结束的，就可以使用此配置。 开启后可以保证Worker进程一次性总是收到一个或者多个完整的数据包。

EOF检测不会从数据中间查找eof字符串，所以Worker进程可能会同时收到多个数据包，需要在应用层代码中自行 `explode("\r\n", $data)`来拆分数据包

> 1.7.15版本增加了`open_eof_split`，支持从数据中查找EOF，并切分数据

### open_eof_split

启用EOF自动分包。当设置`open_eof_check`后，底层检测数据是否以特定的字符串结尾来进行数据缓冲。 但默认只截取收到数据的末尾部分做对比。这时候可能会产生多条数据合并在一个包内。

启用open_eof_split参数后，底层会从数据包中间查找EOF，并拆分数据包。`onReceive`每次仅收到一个以EOF字串结尾的数据包。

> open_eof_split在1.7.15以上版本可用

```
array(
    'open_eof_split' => true, //打开EOF_SPLIT检测
    'package_eof' => "\r\n", //设置EOF
)
```

- 与 open_eof_check 的差异

- `open_eof_check` 只检查接收数据的末尾是否为 EOF，因此它的性能最好，几乎没有消耗
- `open_eof_check` 无法解决多个数据包合并的问题，比如同时发送两条带有 EOF 的数据，底层可能会一次全部返回
- `open_eof_split` 会从左到右对数据进行逐字节对比，查找数据中的 EOF 进行分包，性能较差。但是每次只会返回一个数据包

### package_eof

与 open_eof_check 或者 open_eof_split 配合使用，设置EOF字符串。

> package_eof 最大只允许传入8个字节的字符串

- 数据buffer

buffer主要是用于检测数据是否完整，如果不完整swoole会继续等待新的数据到来。直到收到完整的一个请求，才会一次性发送给worker进程。
这时`onReceive`会收到一个超过 `SW_BUFFER_SIZE`，小于 `$serv->setting['package_max_length']` 的数据。
目前仅提供了EOF检测、固定包头长度检测2种buffer模式。

```
open_eof_check => true, // 打开buffer
package_eof => "\r\n\r\n" // 设置EOF
```

### open_length_check

打开包长检测特性。包长检测提供了固定包头+包体这种格式协议的解析。启用后，可以保证Worker进程onReceive每次都会收到一个完整的数据包。

### package_length_type

长度值的类型，接受一个字符参数，与php的pack函数一致。目前swoole支持10种类型：

```
c：有符号、1字节
C：无符号、1字节
s ：有符号、主机字节序、2字节
S：无符号、主机字节序、2字节
n：无符号、网络字节序、2字节
N：无符号、网络字节序、4字节
l：有符号、主机字节序、4字节（小写L）
L：无符号、主机字节序、4字节（大写L）
v：无符号、小端字节序、2字节
V：无符号、小端字节序、4字节
```

### package_length_func


### package_max_length


### open_cpu_affinity


### cpu_affinity_ignore


### open_tcp_nodelay


### tcp_defer_accept


### ssl_cert_file


### ssl_method


### user

设置`worker/task`子进程的所属用户。服务器如果需要监听1024以下的端口，必须有root权限。但程序运行在root用户下，代码中一旦有漏洞，攻击者就可以以root的方式执行远程指令，风险很大。

配置了user项之后，可以让主进程运行在root权限下，子进程运行在普通用户权限下。

```
$serv->set(array('user' => 'apache'));
```

> 此配置在swoole-1.7.9以上版本可用,仅在使用root用户启动时有效

### group

设置worker/task子进程的进程用户组。与user配置相同，此配置是修改进程所属用户组，提升服务器程序的安全性。

```
$serv->set(array('group' => 'www-data'));
```

> 此配置在swoole-1.7.9以上版本可用, 仅在使用root用户启动时有效

### chroot

重定向Worker进程的文件系统根目录。此设置可以使进程对文件系统的读写与实际的操作系统文件系统隔离。提升安全性。

```
$serv->set(array('chroot' => '/data/server/'));
```

> 此配置在swoole-1.7.9以上版本可用

### pid_file

在Server启动时自动将master进程的PID写入到文件，在Server关闭时自动删除PID文件。

```
$server->set(array(
    'pid_file' => __DIR__.'/server.pid',
));
```

使用时需要注意如果Server非正常结束，PID文件不会删除，需要使用`swoole_process::kill($pid, 0)`来侦测进程是否真的存在

> 此选项在1.9.5或更高版本可用

### pipe_buffer_size


### buffer_output_size


### socket_buffer_size


### enable_unsafe_event


### discard_timeout_request


### enable_reuse_port


### ssl_ciphers


### enable_delay_receive


### open_http_protocol


### open_http2_protocol


### open_websocket_protocol

