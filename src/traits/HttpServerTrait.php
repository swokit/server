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
     * [
     *  rid => SwRequest
     * ]
     */
    private $requests = [];

    /**
     * @var array
     * [
     *  rid => [ SwResponse, SwRequest ]
     * ]
     */
    private $contextMap = [];

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
//        $this->loadGlobalData($request);

        // start session
        if ($this->getOption('start_session', false)) {
            $this->startSession($response);
        }
    }

    /**
     * startSession
     * @param SwResponse $response
     */
    protected function startSession($response)
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

            $response->cookie(
                $name, $sid, time() + $opts['cookie_lifetime'],
                $opts['cookie_path'], $opts['cookie_domain'], $opts['cookie_secure'], $opts['cookie_httponly']
            );
        }

        $this->log("session name: {$name}, session id(cookie): {$_COOKIE[$name]}, session id: " . session_id());
    }

    /**
     * @param SwRequest $request
     * @return string
     */
    public function getRequestId(SwRequest $request)
    {
        return md5($request->server['request_time_float'] . $request->fd, true);
    }

    public function initRequestContext(SwRequest $request, SwResponse $response)
    {
        $this->setRequest($this->getRequestId($request), $request);
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
        register_shutdown_function([$this, 'handleFatal']);

        $uri = $request->server['request_uri'];

        // test: `curl 127.0.0.1:9501/ping`
        if ($uri === '/ping') {
            return $response->end('+PONG' . PHP_EOL);
        }

        if ($this->getOption('enable_static') && $this->handleStaticAccess($request, $response, $uri)) {
            $this->log("Access asset: $uri");
            return true;
        }

        $this->beforeRequest($request, $response);
        $this->initRequestContext($request, $response);

        try {
            if (!$cb = $this->dynamicRequestHandler) {
                $this->log("Please set the property 'dynamicRequestHandler' to handle dynamic request(if you need).", [], 'notice');
                $content = 'No content to display';
                $response->write($content);
            } else {
                // call user's handler
                $response = $cb($request, $response);
            }

            $this->respond($response);
        } catch (\Throwable $e) {
            $this->handleException($e, $request, $response);
        }

        $this->afterRequest($request, $response);

        return true;
    }

    /**
     * @param SwRequest $request
     * @param SwResponse $response
     */
    public function afterRequest(SwRequest $request, SwResponse $response)
    {
        $this->delRequest($this->getRequestId($request));

    }

    /**
     * @param SwResponse $response
     */
    public function beforeResponse(SwResponse $response)
    {
    }

    /**
     * @param SwResponse $response
     * @return mixed
     */
    public function respond(SwResponse $response)
    {
        $this->beforeResponse($response);

        // open gzip
        // $response->gzip(1);

        $ret = $response->end();

        $this->afterResponse($ret);

        return $ret;
    }

    /**
     * afterResponse
     * do some clear work
     */
    protected function afterResponse($ret)
    {
        // commit session data.
        // if started session by `session_start()`, call `session_write_close()` is required.
        if ($this->getOption('start_session', false)) {
            session_write_close();
        }

        // reset supper global var.
//        $this->resetGlobalData();
    }

    /**
     * @param SwResponse $response
     * @param $url
     * @param int $mode
     * @return mixed
     */
    public function redirect($response, $url, $mode = 302)
    {
        $response->status($mode);
        $response->header('Location', $url);

        return $this->respond($response);
    }

    /**
     * @param SwRequest $req
     * @return bool
     */
    public function isAjax(SwRequest $req)
    {
        if (isset($req->header['x-requested-with'])) {
            return $req->header['x-requested-with'] === 'XMLHttpRequest';
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
//        $this->handleException(new \ErrorException($str, 0, $num, $file, $line));
    }

    /**
     * @param \Throwable $e (\Exception \Error)
     * @param SwRequest $req
     * @param SwResponse $resp
     */
    public function handleException(\Throwable $e, $req, $resp)
    {
        $type = $e instanceof \ErrorException ? 'Error' : 'Exception';

        if ($this->isAjax($req)) {
            $resp->header('Content-Type', 'application/json; charset=utf-8');
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
            $resp->header('Content-Type', 'text/html; charset=utf-8');
            $content = PhpHelper::exceptionToString($e, false, $this->isDebug());
        }

        $resp->write($content);
        $this->respond($resp);
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

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

        var_dump($log);
//        if ($this->response) {
//            $this->response->status(500);
//            $this->response->end($log);
//        }
    }

    /**
     * @param int|string $rid
     * @return bool
     */
    public function hasRequest($rid)
    {
        return isset($this->requests[$rid]);
    }

    /**
     * @param int|string $rid
     * @param mixed $request
     * @param bool $override
     */
    public function setRequest($rid, $request, $override = false)
    {
        if (!isset($this->requests[$rid])) {
            $this->requests[$rid] = $request;
        } elseif ($override) {
            $this->requests[$rid] = $request;
        }
    }

    /**
     * @param null|int|string $rid
     * @return mixed
     */
    public function getRequest($rid)
    {
        return $this->requests[$rid] ?? null;
    }

    /**
     * @param $rid
     */
    public function delRequest($rid)
    {
        if (isset($this->requests[$rid])) {
            unset($this->requests[$rid]);
        }
    }

    /**
     * @param SwRequest $request
     * @return string
     */
    public function getRid(SwRequest $request)
    {
        return $this->getRequestId($request);
    }

    /**
     * @return array
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    /**
     * @return array
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * @param array $requests
     */
    public function setRequests(array $requests)
    {
        $this->requests = $requests;
    }
}
