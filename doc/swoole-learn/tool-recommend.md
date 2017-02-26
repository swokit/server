# 工具推荐使用

from [https://wiki.swoole.com/wiki/page/5.html](https://wiki.swoole.com/wiki/page/5.html)

## lsof工具的使用

Linux平台提供了lsof工具可以查看某个进程打开的文件句柄。可以用于跟踪swoole的工作进程所有打开的socket、file、资源。

也可以用工具 `netstat` 查看相关信息

### lsof 一些命令

```
lsof `which httpd` //那个进程在使用apache的可执行文件
lsof /etc/passwd //那个进程在占用/etc/passwd
lsof /dev/hda6 //那个进程在占用hda6
lsof /dev/cdrom //那个进程在占用光驱
lsof -c sendmail //查看sendmail进程的文件使用情况
lsof -c courier -u ^zahn //显示出那些文件被以courier打头的进程打开，但是并不属于用户zahn
lsof -p 30297 //显示那些文件被pid为30297的进程打开
lsof -D /tmp 显示所有在/tmp文件夹中打开的instance和文件的进程。但是symbol文件并不在列

lsof -u1000 //查看uid是100的用户的进程的文件使用情况
lsof -utony //查看用户tony的进程的文件使用情况
lsof -u^tony //查看不是用户tony的进程的文件使用情况(^是取反的意思)
lsof -i //显示所有打开的端口
lsof -i:80 //显示所有打开80端口的进程
lsof -i -U //显示所有打开的端口和UNIX domain文件
lsof -i UDP@[url]www.akadia.com:123 //显示那些进程打开了到www.akadia.com的UDP的123(ntp)端口的链接
lsof -i tcp@ohaha.ks.edu.tw:ftp -r //不断查看目前ftp连接的情况(-r，lsof会永远不断的执行，直到收到中断信号,+r，lsof会一直执行，直到没有档案被显示,缺省是15s刷新)
lsof -i tcp@ohaha.ks.edu.tw:ftp -n //lsof -n 不将IP转换为hostname，缺省是不加上-n参数
```

## tcpdump抓包工具的使用

在调试网络通信程序是tcpdump是必备工具。tcpdump很强大，可以看到网络通信的每个细节。如TCP，可以看到3次握手，`PUSH/ACK`数据推送，close4次挥手，全部细节。包括每一次网络收包的字节数，时间等。

最简单的一个使用示例：

```
sudo tcpdump -i any tcp port 9501
```

- `-i` 参数制定了网卡，any表示所有网卡
- `tcp` 指定仅监听TCP协议
- `port` 制定监听的端口

>tcpdump需要root权限. 需要要看通信的数据内容，可以加 `-Xnlps0` 参数，其他更多参数请参见网上的文章

运行结果:

```
13:29:07.788802 IP localhost.42333 > localhost.9501: Flags [S], seq 828582357, win 43690, options [mss 65495,sackOK,TS val 2207513 ecr 0,nop,wscale 7], length 0
13:29:07.788815 IP localhost.9501 > localhost.42333: Flags [S.], seq 1242884615, ack 828582358, win 43690, options [mss 65495,sackOK,TS val 2207513 ecr 2207513,nop,wscale 7], length 0
13:29:07.788830 IP localhost.42333 > localhost.9501: Flags [.], ack 1, win 342, options [nop,nop,TS val 2207513 ecr 2207513], length 0
13:29:10.298686 IP localhost.42333 > localhost.9501: Flags [P.], seq 1:5, ack 1, win 342, options [nop,nop,TS val 2208141 ecr 2207513], length 4
13:29:10.298708 IP localhost.9501 > localhost.42333: Flags [.], ack 5, win 342, options [nop,nop,TS val 2208141 ecr 2208141], length 0
13:29:10.298795 IP localhost.9501 > localhost.42333: Flags [P.], seq 1:13, ack 5, win 342, options [nop,nop,TS val 2208141 ecr 2208141], length 12
13:29:10.298803 IP localhost.42333 > localhost.9501: Flags [.], ack 13, win 342, options [nop,nop,TS val 2208141 ecr 2208141], length 0
13:29:11.563361 IP localhost.42333 > localhost.9501: Flags [F.], seq 5, ack 13, win 342, options [nop,nop,TS val 2208457 ecr 2208141], length 0
13:29:11.563450 IP localhost.9501 > localhost.42333: Flags [F.], seq 13, ack 6, win 342, options [nop,nop,TS val 2208457 ecr 2208457], length 0
13:29:11.563473 IP localhost.42333 > localhost.9501: Flags [.], ack 14, win 342, options [nop,nop,TS val 2208457 ecr 2208457], length 0
```

说明：

```
13:29:11.563473 时间带有精确到微妙
localhost.42333 > localhost.9501 表示通信的流向，42333是客户端，9501是服务器端
[S] 表示这是一个SYN请求
[.] 表示这是一个ACK确认包，(client)SYN->(server)SYN->(client)ACK 就是3次握手过程
[P] 表示这个是一个数据推送，可以是从服务器端向客户端推送，也可以从客户端向服务器端推
[F] 表示这是一个FIN包，是关闭连接操作，client/server都有可能发起
[R] 表示这是一个RST包，与F包作用相同，但RST表示连接关闭时，仍然有数据未被处理。可以理解为是强制切断连接
win 342是指滑动窗口大小
length 12指数据包的大小
```

## strace工具的使用 

strace可以跟踪系统调用的执行情况，在程序发生问题后，可以用strace分析和跟踪问题。 

> FreeBSD/MacOS下可以使用`dtruss` `truss`

使用方法：

```
strace -o /tmp/strace.log -f -p $PID
```

参数说明：

```
-f 表示跟踪多线程和多进程，如果不加-f参数，无法抓取到子进程和子线程的运行情况
-o 表示将结果输出到一个文件中
-p $PID，指定跟踪的进程ID，通过ps aux可以看到
-tt 打印系统调用发生的时间，精确到微妙
-s 限定字符串打印的长度，如recvfrom系统调用收到的数据，默认只打印32字节
-c 实时统计每个系统调用的耗时
-T 打印每个系统调用的耗时
```

## gdb工具的使用 

GDB是GNU开源组织发布的一个强大的UNIX下的程序调试工具，可以用来调试C/C++开发的程序，PHP和Swoole是使用C语言开发的，所以可以拥GDB来调试PHP+Swoole的程序。

gdb调试是命令行交互式的，需要掌握常用的指令。

使用方法

```
gdb -p 进程ID
gdb php
gdb php core
```

gdb有3种使用方式：

- 跟踪正在运行的PHP程序，使用gdb -p 进程ID
- 使用gdb运行并调试PHP程序，使用gdb php -> run server.php 进行调试
- PHP程序发生coredump后使用gdb加载core内存镜像进行调试 gdb php core

> 如果PATH环境变量中没有php，gdb时需要指定绝对路径，如`gdb /usr/local/bin/php`

### 常用指令

- `p`：print，打印C变量的值
- `c`：continue，继续运行被中止的程序
- `b`：breakpoint，设置断点，可以按照函数名设置，如b zif_php_function，也可以按照源代码的行数指定断点，如b src/networker/Server.c:1000
- `t`：thread，切换线程，如果进程拥有多个线程，可以使用t指令，切换到不同的线程
- `ctrl + c`：中断当前正在运行的程序，和c指令配合使用
- `n`：next，执行下一行，单步调试
- `info threads`：查看运行的所有线程
- `l`：list，查看源码，可以使用l 函数名 或者 l 行号
- `bt`：backtrace，查看运行时的函数调用栈
- `finish`：完成当前函数
- `f`：frame，与bt配合使用，可以切换到函数调用栈的某一层
- `r`：run，运行程序

### zbacktrace

zbacktrace是PHP源码包提供的一个gdb自定义指令，功能与bt指令类似，与bt不同的是zbacktrace看到的调用栈是PHP函数调用栈，而不是C函数。

下载php-src，解压后从根目录中找到一个.gdbinit文件，在gdb shell中输入

```
source .gdbinit
zbacktrace
```

> .gdbinit还提供了其他更多指令，可以查看源码了解详细的信息。

### 使用gdb+zbacktrace跟踪死循环问题

```
gdb -p 进程ID
```

- 使用ps aux工具找出发生死循环的Worker进程ID
- gdb -p跟踪指定的进程
- 反复调用 ctrl + c 、zbacktrace、c 查看程序在哪段PHP代码发生循环
- 找到对应的PHP代码进行解决

## perf工具的使用 

perf工具是Linux内核提供一个非常强大的动态跟踪工具，`perf top`指令可用于实时分析正在执行程序的性能问题。与callgrind、xdebug、xhprof等工具不同，perf无需修改代码导出profile结果文件。

使用方法

```
perf top -p [进程ID]
```

perf结果中会清楚地展示当前进程运行时各个C函数的执行耗时，可以了解哪个C函数占用CPU资源较多。

如果你熟悉Zend VM，某些Zend函数调用过多，可以说明你的程序中大量使用了某些函数，导致CPU占用过高，针对性的进行优化

## 编译PHP扩展的相关工具 

首先你需要下载一份扩展的源码，可以到github或者pecl.php.net上下载，解压后放到一个目录中，cd进入此目录。

- autoconf

根据`config.m4`生成configure脚本，phpize是基于autoconf的封装。

- phpize

phpize这个工具是php官方提供的，用于将PHP扩展的config.m4解析生成`./configure` 脚本。

- configure

这个脚本是用来检测系统和环境状态，依赖库和头文件是否存在，编译配置等

- php-config

这个工具执行后会打印当前PHP安装在哪里目录，API版本号是什么，扩展目录在哪里等信息。configure脚本需要依赖它找到PHP安装的目录

- make

用来将`.c`源文件编译为目标文件。`make install`将编译好的扩展文件，如swoole.so安装到PHP的扩展目录下

- gcc

编译器，将`*.c`源文件编译为目标文件。并连接所有目标文件生成swoole.so

- clang

另外一种编译器，`FreeBSD/MacOS`下用的比较多。

### 扩展安装过程

```
phpize
./configure [option ...]
make
make install
```



