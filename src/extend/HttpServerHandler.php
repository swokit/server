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
],
'options' => [
    // static asset handle
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
     * @var string
     */
    public $requestId;

    /**
     * @var SwRequest
     */
    public $request;

    /**
     * @var SwResponse
     */
    public $response;

    /**
     * handle dynamic request (for http server)
     * @var \Closure
     */
    protected $dynamicRequestHandler;

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

    /**
     * @var array
     */
    protected $options = [
        'start_session' => false,

        // @link http://php.net/manual/zh/session.configuration.php
        'session' => [
            'save_path' => '', // app_session
            'name' => 'php_session', // app_session

            // 设置 cookie 的有效时间为 30 minute
            'cookie_lifetime' => 1800,
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_secure' => false,
            'cookie_httponly' => false,

            'cache_expire' => 1800,
        ],

        // static asset handle
        'enable_static' => true,
        'assets_dir' => [
            // 'url_match' => 'assets dir',
            '/assets'  => 'public/assets',
            '/uploads' => 'public/uploads'
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

    /**
     * @param SwRequest $request
     * @param SwResponse $response
     */
    protected function beforeRequest(SwRequest $request, SwResponse $response)
    {
        $this->request = $request;
        $this->response = $response;
        $this->requestId = base_convert( str_replace('.', '', microtime(1)) . rand(100, 999), 10, 16);

        // $this->getCliOut()->aList('Extend Options:',$this->options);

        $this->loadGlobalData($request);

        // start session
        if ($this->getOption('start_session', false)) {
            $this->startSession();
        }
    }

    /**
     */
    protected function startSession()
    {
        // session
        $opts = $this->options['session'];
        $name = $opts['name'] = $opts['name'] ?: session_name();

        if ( ($path = $opts['save_path']) && !is_dir($path) ) {
            mkdir($path, 0755, true);
        }

        // start session
        session_name($name);
        //register_shutdown_function('session_write_close');
        session_start($opts);

        $this->getCliOut()->aList('session cookie params', session_get_cookie_params());

        // if not exists, set it.
        if ( !$sid = $this->request->cookie[$name] ) {
            $sid = session_id();

            $this->response->cookie(
                $name, $sid, time() + $opts['cookie_lifetime'],
                $opts['cookie_path'], $opts['cookie_domain'], $opts['cookie_secure'], $opts['cookie_httponly']
            );
        }

        $this->addLog("session name: {$name}, session id(cookie): {$_COOKIE[$name]}, session id: " . session_id());
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

        // test: `curl 127.0.0.1:9501/ping`
        if ( $uri === '/ping' ) {
            return $response->end('+PONG' . PHP_EOL);
        }

        if ( $this->handleStaticAccess($request, $response, $uri) ) {
            $this->addLog("Access asset: $uri");
            return true;
        }

        $this->beforeRequest($request, $response);

        $method = $request->server['request_method'];
        $this->addLog("$method $uri", [
            'GET' => $request->get,
            'POST' => $request->post,
        ]);

        try {
            if ( !$cb = $this->dynamicRequestHandler ) {
                $this->addLog("Please set the property 'dynamicRequestHandler' to handle dynamic request(if you need).", [], 'notice');
                $bodyContent = 'No content to display';
            } else {
                $bodyContent = $cb($request, $response);
            }

            $this->respond($bodyContent);
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return true;
    }

    /**
     * @param string $content
     * @return mixed
     */
    public function respond($content = '')
    {
        $this->beforeResponse($content);

        $opts = $this->options['response'];

        // open gzip
        if ( $content && isset($opts['gzip']) && $opts['gzip'] ) {
            $this->response->gzip(1);
        }

        return $this->response->end($content);
    }

    /**
     * @param $content
     */
    protected function beforeResponse($content)
    {
        // commit session data.
        // if started session by `session_start()`, call `session_write_close()` is required.
        if ( $this->getOption('start_session', false) ) {
            session_write_close();
        }

        // reset supper global var.
        $this->resetGlobal();
    }

    /**
     * @param $url
     * @param int $mode
     * @return mixed
     */
    public function redirect($url, $mode = 302)
    {
        $this->response->status($mode);
        $this->response->header('Location', $url);

        return $this->respond();
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
        foreach ((array)$request->header as $key => $value) {
            $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $serverData[$_key] = $value;
        }

        $_GET = $request->get ?: [];
        $_POST = $request->post ?: [];
        $_FILES = $request->files ?: [];
        $_COOKIE = $request->cookie ?: [];
        $_SERVER = $serverData;
        $_REQUEST = array_merge($request->get ?: [], $request->post ?: [], $request->cookie ?: []);

        $this->getCliOut()->title("[RID:{$this->requestId}]");
        $this->getCliOut()->multiList([
            'request GET' => $_GET,
            'request POST' => $_POST,
            'request COOKIE' => $_COOKIE,
            // 'request Headers' => $request->header ?: [],
            // 'server Data' => $request->server ?: [],
        ]);
    }

    /**
     * reset Global
     */
    protected function resetGlobal()
    {
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = [];
    }

    /**
     * @return bool
     */
    protected function isWebSocket()
    {
        return isset($this->request->header['upgrade']) && strtolower($this->request->header['upgrade']) === 'websocket';
    }

    protected function handleException($e)
    {
        var_dump($e);
    }

    /**
     * handle Static Access 处理静态资源请求
     *
     * @param SwRequest $request
     * @param SwResponse $response
     * @param string $uri
     * @return bool
     */
    protected function handleStaticAccess(SwRequest $request, SwResponse $response, $uri)
    {
        $uri = $uri ?:$request->server['request_uri'];

        // 请求 /favicon.ico 过滤
        if (
            $this->options['request']['filterFavicon'] &&
            ( $request->server['path_info'] === '/favicon.ico' || $uri === '/favicon.ico')
        ) {
            return $response->end();
        }

        $setting = $this->getOption('assets');

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
        }
    }

    /**
     * output debug message
     * @see AServerManager::addLog()
     * @param  string $msg
     * @param  array $data
     * @param string $type
     */
    public function addLog($msg, $data = [], $type = 'debug')
    {
        $this->mgr->addLog("[rid:{$this->requestId}] " . $msg, $data, $type);
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

}
