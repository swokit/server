<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\traits;

use inhere\console\utils\Show;
use inhere\library\files\Directory;
use inhere\library\helpers\PhpHelper;
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
 * trait HttpServerTrait
 * @package inhere\server\traits
 *
 */
trait HttpServerTrait
{
    /**
     * the request id
     * @var string
     */
    public $rid;

    /**
     * @var array
     */
    private $requests = [];

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
        'js' => 'application/x-javascript',
        'css' => 'text/css',
        'bmp' => 'image/bmp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'woff' => 'application/font-woff',
        'woff2' => 'application/font-woff2',
        'ttf' => 'application/x-font-ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'htm' => 'text/html',
        'html' => 'text/html',
    ];

    /**
     * @var array
     */
    protected $defaultOptions = [
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
        'document_root' => '', // enable_static_handler
        'assets' => [
            'ext' => [],
            'map' => [
                // 'url_match' => 'assets dir',
                '/assets' => 'public/assets',
                '/uploads' => 'public/uploads'
            ]
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
     * @param  int $workerId
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

        $this->loadGlobalData($request);

        // start session
        if ($this->getOption('start_session', false)) {
            $this->startSession();
        }
    }

    /**
     * startSession
     */
    protected function startSession()
    {
        // session
        $opts = $this->getOption('session');
        $name = $opts['name'] = $opts['name'] ?: session_name();

        if (($path = $opts['save_path']) && !is_dir($path)) {
            Directory::mkdir($path, 0775);
        }

        // start session
        session_name($name);
        //register_shutdown_function('session_write_close');
        session_start($opts);

        Show::aList(session_get_cookie_params(), 'session cookie params');

        // if not exists, set it.
        if (!$sid = $_COOKIE[$name] ?? '') {
            $_COOKIE[$name] = $sid = session_id();

            $this->response->cookie(
                $name, $sid, time() + $opts['cookie_lifetime'],
                $opts['cookie_path'], $opts['cookie_domain'], $opts['cookie_secure'], $opts['cookie_httponly']
            );
        }

        $this->log("session name: {$name}, session id(cookie): {$_COOKIE[$name]}, session id: " . session_id());
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

        $this->rid = base_convert(str_replace('.', '', microtime(1)), 10, 16) . "0{$request->fd}";
        $uri = $request->server['request_uri'];

        // test: `curl 127.0.0.1:9501/ping`
        if ($uri === '/ping') {
            return $response->end('+PONG' . PHP_EOL);
        }

        if ($this->getOption('enable_static') && $this->handleStaticAccess($request, $response, $uri)) {
            $this->log("Access asset: $uri");
            return true;
        }

        $status = 200;
        $headers = [];
        $this->beforeRequest($request, $response);

        try {
            if (!$cb = $this->dynamicRequestHandler) {
                $this->log("Please set the property 'dynamicRequestHandler' to handle dynamic request(if you need).", [], 'notice');
                $content = 'No content to display';
            } else {
                list($status, $headers, $content) = $cb($request, $response);
            }

            $this->respond($content, $status, $headers);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }

        $this->afterResponse();

        return true;
    }

    /**
     * @param string $content
     * @param int $status
     * @param array $headers
     * @return mixed
     */
    public function respond(string $content = '', int $status = 200, array $headers = [])
    {
        // $this->beforeResponse($content);
        $opts = $this->options['response'];

        // http status
        $this->response->status($status);

        // headers
        foreach ($headers as $name => $value) {
            $this->response->header($name, $value);
        }

        // open gzip
        if ($content && isset($opts['gzip']) && $opts['gzip']) {
            $this->response->gzip(1);
        }

        Show::write([
            "Response Status: <info>$status</info>"
        ]);
        Show::aList($headers, 'Response Headers');
        Show::aList($_SESSION ?: [],'server sessions');

        return $this->response->end($content);
    }

    /**
     * afterResponse
     * do some clear work
     */
    protected function afterResponse()
    {
        // commit session data.
        // if started session by `session_start()`, call `session_write_close()` is required.
        if ($this->getOption('start_session', false)) {
            session_write_close();
        }

        // reset supper global var.
        $this->resetGlobalData();
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
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);

        $uri = $request->server['request_uri'];
        $method = $request->server['request_method'];

        Show::title("[RID:{$this->rid}] $method $uri");
        Show::multiList([
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
    protected function resetGlobalData()
    {
        $this->response = $this->request = null;
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = [];
    }

    /**
     * @return bool
     */
    public function isWebSocket()
    {
        return isset($this->request->header['upgrade']) && strtolower($this->request->header['upgrade']) === 'websocket';
    }

    /**
     * @return bool
     */
    public function isAjax()
    {
        if (isset($this->request->header['x-requested-with'])) {
            return $this->request->header['x-requested-with'] === 'XMLHttpRequest';
        }

        return false;
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
        $uri = $uri ?: $request->server['request_uri'];

        // 请求 /favicon.ico 过滤
        if (
            ($request->server['path_info'] === '/favicon.ico' || $uri === '/favicon.ico')  &&
            $this->options['request']['filterFavicon']
        ) {
            return $response->end();
        }

        $setting = $this->getOption('assets');

        // 没有资源处理配置 || 没有任何后缀 返回交给php继续处理
        if (!$setting || false === strrpos($uri, '.')) {
            return false;
        }

        $extAry = array_keys(static::$staticAssets);
        $extReg = implode('|', $extAry);

//         $this->log("begin match ext for the asset $uri, result: " . preg_match("/\.($extReg)/i", $uri, $matches), $exts);

        // 资源后缀匹配失败 返回交给php继续处理
        if (1 !== preg_match("/.($extReg)/i", $uri, $matches)) {
            return false;
        }

        // asset ext name. e.g $matches = [ '.css', 'css' ];
        $ext = $matches[1];
        $map = $setting['map'] ?? [];

        # 静态路径 'assets/css/site.css'
        $arr = explode('/', ltrim($uri, '/'), 2);
        $urlBegin = '/' . array_shift($arr);
        $matched = false;
        $assetDir = '';

        foreach ($map as $urlMatch => $assetDir) {
            // match success
            if ($urlBegin === $urlMatch) {
                $matched = true;
                break;
            }
        }

        // url匹配失败 返回交给php继续处理
        if (!$matched) {
            return false;
        }

        // if like 'css/site.css?135773232'
        $path = strpos($arr[0], '?') ? explode('?', $arr[0], 2)[0] : $arr[0];
        $file = $this->getValue('root_path') . "/$assetDir/$path";

        // 必须要有内容类型
        $response->header('Content-Type', static::$staticAssets[$ext]);

        if (is_file($file)) {
            // 设置缓存头信息
            $time = 86400;
            $response->header('Cache-Control', 'max-age=' . $time);
            $response->header('Pragma', 'cache');
            $response->header('Last-Modified', date('D, d M Y H:i:s \G\M\T', filemtime($file)));
            $response->header('Expires', date('D, d M Y H:i:s \G\M\T', time() + $time));
            // 直接发送文件 不支持gzip
            $response->sendfile($file);
        } else {
            $this->log("Assets $uri file not exists: $file", [], 'warning');

            $response->status(404);
            $response->end("Assets not found: $uri\n");
        }

        return true;
    }

    /**
     * @param int $num
     * @param string $str
     * @param string $file
     * @param int $line
     * @internal  null|mixed $context
     */
    public function handleError($num, $str, $file, $line)
    {
        $this->handleException(new \ErrorException($str, 0, $num, $file, $line));
    }

    /**
     * @param \Throwable $e (\Exception \Error)
     */
    public function handleException(\Throwable $e)
    {
        $type = $e instanceof \ErrorException ? 'Error' : 'Exception';

        if ($this->isAjax()) {
            $headers = ['Content-Type', 'application/json; charset=utf-8'];
            $content = json_encode([
                'code' => $e->getCode() ?: 500,
                'msg'  => sprintf(
                    '%s(%d): %s, File: %s(Line %d)',
                    $type,
                    $e->getCode(),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ),
                'data' => $e->getTrace()
            ]);
        } else {
            $headers = ['Content-Type', 'text/html; charset=utf-8'];
            $content = PhpHelper::exceptionToString($e, false, $this->isDebug());
        }

        $this->respond($content, 200, $headers);
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
        $file = $error['file'];
        $line = $error['line'];
        $log = "\nException：$message\nFile:$file($line)\nStack trace:\n";
        $trace = debug_backtrace(1);

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
            $log .= '[URI:' . $_SERVER['REQUEST_URI'] . ']';
        }

        if ($this->response) {
            $this->response->status(500);
            $this->response->end($log);
        }
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->rid;
    }

    /**
     * @return string
     */
    public function getRid()
    {
        return $this->rid;
    }

    /**
     * @return array
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }
}
