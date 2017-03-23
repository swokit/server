<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\extend;

use inhere\server\AExtendServerHandler;
use Swoole\Server as SwServer;
use Swoole\Http\Response as SwResponse;
use Swoole\Http\Request as SwRequest;

/*

http config:

```
'main_server' => [
    'host' => '0.0.0.0',
    'port' => '9662',

    // enable https(SSL)
    // 使用SSL必须在编译swoole时加入--enable-openssl选项 并且要在'swoole'中的配置相关信息(@see AServerManager::defaultConfig())
    'type' => 'http', // 'http' 'https'

    // 运行模式
    // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
    'mode' => 'process', // 'process' 'base'

    'event_handler' => \inhere\server\handlers\HttpServerHandler::class,
    'event_list' => [ '' ]

    // static asset handle
    'enable_static' => true,
    'static_setting' => [
        // 'url_match' => 'assets dir',
        '/assets'  => 'public/assets',
        '/uploads' => 'public/uploads'
    ],
]
```

*/


/**
 * Class HttpServerHandler
 * @package inhere\server\handlers
 *
 */
class HttpServerHandler extends AExtendServerHandler
{
    /**
     * handle dynamic request (for http server)
     * @var \Closure
     */
    protected $dynamicRequestHandler;

    /**
     * @var SwResponse
     */
    public $response;

    /**
     * 静态文件类型
     *
     * @var array
     */
    public static $staticAssets = [
        'js'    => 'application/x-javascript',
        'css'   => 'text/css',
        'bmp'   => 'image/bmp',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'ico'   => 'image/x-icon',
        'json'  => 'application/json',
        'svg'   => 'image/svg+xml',
        'woff'  => 'application/font-woff',
        'woff2' => 'application/font-woff2',
        'ttf'   => 'application/x-font-ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'htm'   => 'text/html',
        'html'  => 'text/html',
    ];

    protected $options = [
        'session' => [
            'enable' => true,
            'name' => 'app_session',
            // 设置 cookie 的有效时间为 30 minute
            'cookie_lifetime' => 1800,
            // 'read_and_close'  => true,
        ],
        'request' => [
            'filterFavicon' => true,
        ],
        'response' => [
            'gzip' => true,
        ],
    ];

    /**
     * onWorkerStart
     * @param  SwServer $server
     * @param  int      $workerId
     */
//    public function onWorkerStart(SwServer $server, $workerId)
//    {
//        $this->mgr->onWorkerStart($server, $workerId);
//    }

    /**
     * set a handler to handle the Dynamic Request 处理动态资源请求
     * @param \Closure|callable $handler
     */
    public function handleDynamicRequest(callable $handler)
    {
        $this->dynamicRequestHandler = $handler;
    }

    protected function beforeRequest(SwRequest $request, SwResponse $response)
    {}

    protected function afterRequest(SwRequest $request, SwResponse $response)
    {}

    protected function prepareRequest(SwRequest $request, SwResponse $response)
    {
        $this->loadGlobalData($request);

        $setting = $this->options['session'];
        $name = $setting['name'];

        // if not exists, set it.
        if ( !$sid = $request->cookie[$name] ) {
            $sid = session_id();
            session_name($name);
            $response->cookie($name, $sid);
        }

        // start session
        session_start();

        $this->addLog("session name: {$name}, session id: {$sid}");

        return $this;
    }

    /**
     * 处理http请求
     * @param  SwRequest $request
     * @param  SwResponse $response
     * @return bool|mixed
     */
    public function onRequest(SwRequest $request, SwResponse $response)
    {
        // 捕获异常
        register_shutdown_function(array($this, 'handleFatal'));

        $uri = $request->server['request_uri'];
        $method = $request->server['request_method'];
        $enableStatic = $this->getConfig('main_server.enable_static', false);

        if ( $enableStatic && $this->handleStaticAccess($request, $response, $uri) ) {
            $this->addLog("Access asset: $uri");
            return true;
        }

        $this
            ->prepareRequest($request, $response)
            ->beforeRequest($request, $response);

        $this->addLog("$method $uri", [
            'GET' => $request->get,
            'POST' => $request->post,
        ]);

        // test: `curl 127.0.0.1:9501/ping`
        if ( $uri === '/ping' ) {
            return $response->end('+PONG' . PHP_EOL);
        }

        $this->response = $response;

        try {
            if ( !$cb = $this->dynamicRequestHandler ) {
                $this->addLog("Please set the property 'dynamicRequestHandler' to handle dynamic request(if you need.).", [], 'notice');
                $bodyContent = 'No content to display';
            } else {
                $bodyContent = $cb($request, $response);
            }

            // open gzip
            $response->gzip(1);

            $this->afterRequest($request, $response);

            $response->end($bodyContent);
        } catch (\Exception $e) {
            var_dump($e);
        }

        return true;
    }

    /**
     * 将原始请求信息转换到PHP超全局变量中
     * @param SwRequest $request
     */
    protected function loadGlobalData(SwRequest $request)
    {
        $serverData = array_change_key_case($request->server, CASE_UPPER);

        /**
         * 将HTTP头信息赋值给$_SERVER超全局变量
         */
        foreach ($request->header as $key => $value) {
            $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $serverData[$_key] = $value;
        }

        $_GET = $request->get;
        $_POST = $request->post;
        $_FILES = $request->files;
        $_COOKIE = $request->cookie;
        $_SERVER = $serverData;
        $_REQUEST = array_merge($request->get ?: [], $request->post ?: [], $request->cookie ?: []);
    }

    protected function resetGlobal()
    {
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = [];
    }

    protected function isWebSocket()
    {
        return isset($_SERVER['Upgrade']) && strtolower($_SERVER['Upgrade']) === 'websocket';
    }

    /**
     * handle Static Access 处理静态资源请求
     *
     * @param SwRequest $request
     * @param SwResponse $response
     * @param string $uri
     * @return bool
     */
    protected function handleStaticAccess(SwRequest $request, SwResponse $response, $uri = '')
    {
        $uri = $uri ?:$request->server['request_uri'];

        // 请求 /favicon.ico 过滤
        if (
            $this->options['request']['filterFavicon'] &&
            ( $request->server['path_info'] === '/favicon.ico' || $uri === '/favicon.ico')
        ) {
            return $response->end();
        }

        $setting = $this->getConfig('main_server.static_setting');

        // $this->addLog("begin check '.' point exists in the asset $uri");

        # 没有任何后缀 || 没有资源处理配置 返回交给php继续处理
        if (false === strrpos($uri, '.') || !$setting ) {
            return false;
        }

        $exts = array_keys(static::$staticAssets);
        $extReg = implode('|', $exts);

        // $this->addLog("begin match ext for the asset $uri, result: " . preg_match("/\.($extReg)/i", $uri, $matches), $exts);

        // 资源后缀匹配失败 返回交给php继续处理
        if ( 1 !== preg_match("/.($extReg)/i", $uri, $matches) ) {
            return false;
        }

        // $this->addLog("begin match rule for the asset $uri", $setting);

        // asset ext name. e.g $matches = [ '.css', 'css' ];
        $ext = $matches[1];

        # 静态路径 'assets/css/site.css'
        $arr = explode('/', ltrim($uri, '/'), 2);
        $urlBegin = '/' . array_shift($arr);
        $matched = false;
        $assetDir = '';

        foreach ($setting as $urlMatch => $assetDir) {
            // match success
            if ( $urlBegin === $urlMatch ) {
                $matched = true;
                break;
            }
        }

        // url匹配失败 返回交给php继续处理
        if ( !$matched ) {
            return false;
        }

        // if like 'css/site.css?135773232'
        $path = strpos($arr[0], '?') ? explode('?', $arr[0], 2)[0] : $arr[0];
        $file = $this->getConfig('root_path') . "/$assetDir/$path";

        // 必须要有内容类型
        $response->header('Content-Type', static::$staticAssets[$ext]);

        if ( is_file($file) ) {
            // 设置缓存头信息
            $time = 86400;
            $response->header('Cache-Control', 'max-age='. $time);
            $response->header('Pragma'       , 'cache');
            $response->header('Last-Modified', date('D, d M Y H:i:s \G\M\T', filemtime($file)));
            $response->header('Expires'      , date('D, d M Y H:i:s \G\M\T', time() + $time));
            // 直接发送文件 不支持gzip
            $response->sendfile($file);
        } else {
            $this->addLog("Assets $uri file not exists: $file",[], 'warning');

            $response->status(404);
            $response->end("Assets not found: $uri\n");
        }

        return true;
    }


    /**
     * Fatal Error的捕获
     *
     */
    public function handleFatal()
    {
        $error = error_get_last();
        if (!isset($error['type'])) {
            return;
        }
        switch ($error['type']) {
            case E_ERROR:
            case E_PARSE:
            case E_DEPRECATED:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                break;
            default:
                return;
        }
        $message = $error['message'];
        $file    = $error['file'];
        $line    = $error['line'];
        $log     = "\n异常提示：$message ($file:$line)\nStack trace:\n";
        $trace   = debug_backtrace(1);
        foreach ($trace as $i => $t) {
            if (!isset($t['file'])) {
                $t['file'] = 'unknown';
            }
            if (!isset($t['line'])) {
                $t['line'] = 0;
            }
            if (!isset($t['function'])) {
                $t['function'] = 'unknown';
            }
            $log .= "#$i {$t['file']}({$t['line']}): ";
            if (isset($t['object']) && is_object($t['object'])) {
                $log .= get_class($t['object']) . '->';
            }
            $log .= "{$t['function']}()\n";
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
        }
        if ($this->response) {
            $this->response->status(500);
            $this->response->end($log);
            $this->response = null;
        }
    }

}
