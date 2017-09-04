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
use inhere\server\helpers\StaticAccessHandler;
use Swoole\Http\Server;
use Swoole\Http\Response;
use Swoole\Http\Request;

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
     * handle dynamic request (for http server)
     * @var \Closure
     */
    protected $dynamicRequestHandler;

    /**
     * handle static file access.
     * @var StaticAccessHandler
     */
    protected $staticAccessHandler;

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
            'dirMap' => [
                // 'url prefix' => 'assets dir',
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
     * @param  Server $server
     * @param  int $workerId
     */
//    public function onWorkerStart(Server $server, $workerId)
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
     * @param Request $request
     * @param Response $response
     */
    protected function beforeRequest(Request $request, Response $response)
    {
//        $this->loadGlobalData($request);

        // start session
//        if ($this->getOption('start_session', false)) {
            // $this->startSession($request, $response);
//        }
    }

    /**
     * 处理http请求
     * @param  Request $request
     * @param  Response $response
     * @return bool|mixed
     */
    public function onRequest(Request $request, Response $response)
    {
        // 捕获异常
        register_shutdown_function([$this, 'handleFatal']);

        $uri = $request->server['request_uri'];

        // test: `curl 127.0.0.1:9501/ping`
        if ($uri === '/ping') {
            return $response->end('+PONG' . PHP_EOL);
        }

        $stHandler = $this->staticAccessHandler;

        if ($stHandler && $stHandler($request, $response, $uri)) {
            $this->log("Access asset: $uri");
            return true;
        }

        if ($error = $stHandler->getError()) {
            $this->log($error, [], 'error');
        }

        $this->beforeRequest($request, $response);

        try {
            if (!$cb = $this->dynamicRequestHandler) {
                $this->log("Please set the property 'dynamicRequestHandler' to handle dynamic request(if you need).", [], 'notice');
                $content = 'No content to display';
                $response->write($content);
            } else {
                // call user's handler
                $response = $cb($request, $response);
                
                // if not instanceof Response
                if (!$response instanceof Response) {
                    $content = $response ?: 'NO CONTENT TO DISPLAY';
                    $response->write(is_string($content) ? $content : json_encode($content));
                }
            }

            // respond to client
            $this->respond($response);
        } catch (\Throwable $e) {
            $this->handleException($e, $request, $response);
        }

        $this->afterRequest($request, $response);

        return true;
    }

    /**
     * @param Request $request
     * @param Response $response
     */
    public function afterRequest(Request $request, Response $response)
    {
    }

    /**
     * @param Response $response
     */
    public function beforeResponse(Response $response)
    {
    }

    /**
     * @param Response $response
     * @return mixed
     */
    public function respond(Response $response)
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
//        if ($this->getOption('start_session', false)) {
//            session_write_close();
//        }
    }

    /**
     * @param Response $response
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
     * @param Request $req
     * @return bool
     */
    public function isAjax(Request $req)
    {
        if (isset($req->header['x-requested-with'])) {
            return $req->header['x-requested-with'] === 'XMLHttpRequest';
        }

        return false;
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
     * @param Request $req
     * @param Response $resp
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

        echo PhpHelper::dumpVars($log);
//        if ($this->response) {
//            $this->response->status(500);
//            $this->response->end($log);
//        }
    }
}
