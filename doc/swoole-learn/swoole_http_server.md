# swoole_http_server

swoole-1.7.7增加了内置Http服务器的支持，通过几行代码即可写出一个异步非阻塞多进程的Http服务器。
swoole_http_server继承自swoole_server，是一个完整的http服务器实现。swoole_http_server支持同步和异步2种模式。

> `http/websocket server` 服务器都是继承自swoole_server，所以swoole_server提供的API，如 `task/finish/tick` 等都能使用

```php
$http = new swoole_http_server("127.0.0.1", 9501);
$http->on('request', function ($request, $response) {
    $response->end("<h1>Hello Swoole. #".rand(1000, 9999)."</h1>");
});
$http->start();
swoole_http_server对Http协议的支持并不完整，建议仅作为应用服务器。并且在前端增加Nginx作为代理
```

## 使用Http2协议

- 需要依赖`nghttp2`库，下载[nghttp2](https://github.com/tatsuhiro-t/nghttp2)后编译安装
- 使用Http2协议必须开启openssl
- 需要高版本openssl必须支持TLS1.2、ALPN、NPN


    ./configure --enable-openssl --enable-http2

设置http服务器的 `open_http2_protocol` 为 `true`

```php
$serv = new swoole_http_server("127.0.0.1", 9501, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
$serv->set([
    'ssl_cert_file' => $ssl_dir . '/ssl.crt',
    'ssl_key_file' => $ssl_dir . '/ssl.key',
    'open_http2_protocol' => true,
]);
```
## swoole_http_server->on 

注册事件回调函数，与`swoole_server->on`相同。`swoole_http_server->on`的不同之处是：

- 不接受 `onConnect/onReceive` 回调设置
- 额外接受1种新的事件类型 `onRequest`

### onRequest事件

```php
$http_server->on('request', function(swoole_http_request $request, swoole_http_response $response) {
    $response->end("<h1>hello swoole</h1>");
})
```

在收到一个完整的Http请求后，会回调此函数。回调函数共有2个参数：

$request，Http请求信息对象，包含了header/get/post/cookie等相关信息
$response，Http响应对象，支持cookie/header/status等Http操作

在onRequest回调函数返回时底层会销毁$request和$response对象，如果未执行$response->end()操作，底层会自动执行一次$response->end("")

> onRequest在1.7.7后可用

注意：

- `$response/$request` 对象传递给其他函数时，不要加`&`引用符号
- `$response/$request` 对象传递给其他函数后，引用计数会增加，onRequest退出时不会销毁