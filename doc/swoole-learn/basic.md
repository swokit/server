
使用 `fd` 保存客户端IP，`from_id` 保存 `from_fd` 和 `port`

## swoole支持的Socket类型

from [https://wiki.swoole.com/wiki/page/16.html](https://wiki.swoole.com/wiki/page/16.html)

- `SWOOLE_TCP/SWOOLE_SOCK_TCP` tcp ipv4 socket
- `SWOOLE_TCP6/SWOOLE_SOCK_TCP6` tcp ipv6 socket
- `SWOOLE_UDP/SWOOLE_SOCK_UDP` udp ipv4 socket
- `SWOOLE_UDP6/SWOOLE_SOCK_UDP6` udp ipv6 socket
- `SWOOLE_UNIX_DGRAM` unix socket dgram
- `SWOOLE_UNIX_STREAM` unix socket stream

> Unix Socket仅在1.7.1+后可用，此模式下$host参数必须填写可访问的文件路径，$port参数忽略
Unix Socket模式下，客户端$fd将不再是数字，而是一个文件路径的字符串
SWOOLE_TCP等是1.7.0+后提供的简写方式，与1.7.0前的SWOOLE_SOCK_TCP是等同的


## 流程图

- 运行流程图

![](images/swoole.jpg)

- 进程/线程结构图


![](images/process.jpg)