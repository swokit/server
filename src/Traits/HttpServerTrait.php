<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace Inhere\Server\Traits;

use Inhere\Library\Helpers\PhpHelper;
use Inhere\LibraryPlus\Log\Logger;
use Inhere\Server\Components\StaticResourceProcessor;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

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

    'event_handler' => \Inhere\Server\handlers\HttpServerHandler::class,
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
 * @package Inhere\Server\Traits
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
     * @var StaticResourceProcessor
     */
    protected $staticAccessHandler;

    /**
     * @var array
     */
    protected $options = [
        'startSession' => false,
        'ignoreFavicon' => false,

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

        'request' => [
            'ignoreFavicon' => true,
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
//        if ($this->getOption('startSession', false)) {
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
        $uri = $request->server['request_uri'];

        // test: `curl 127.0.0.1:9501/ping`
        if ($uri === '/ping') {
            return $response->end('+PONG' . PHP_EOL);
        }

        if (strtolower($uri) === '/favicon.ico' && $this->getOption('ignoreFavicon')) {
            return $response->end('+ICON');
        }

        $stHandler = $this->staticAccessHandler;

        if ($stHandler && $stHandler($request, $response, $uri)) {
            $this->log("Access asset: $uri");

            return true;
        }

        if ($error = $stHandler->getError()) {
            $this->log($error, [], Logger::ERROR);
        }

        $this->beforeRequest($request, $response);

        try {
            if (!$cb = $this->dynamicRequestHandler) {
                $this->log("Please set the property 'dynamicRequestHandler' to handle dynamic request(if you need).", [], Logger::NOTICE);
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
            $this->handleHttpException($e, $request, $response);
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
     * afterResponse. you can do some clear work
     * @param $ret
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
     * @param \Throwable $e (\Exception \Error)
     * @param Request $req
     * @param Response $resp
     */
    public function handleHttpException(\Throwable $e, $req, $resp)
    {
        $type = $e instanceof \ErrorException ? 'Error' : 'Exception';

        if ($this->isAjax($req)) {
            if ($resp) {
                $resp->header('Content-Type', 'application/json; charset=utf-8');
            }

            $content = json_encode([
                'code' => $e->getCode() ?: 500,
                'msg' => sprintf(
                    '%s(%d): %s, File: %s(Line %d), Catch By: %s',
                    $type,
                    $e->getCode(),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    __METHOD__
                ),
                'data' => $e->getTrace()
            ]);
        } else {
            if ($resp) {
                $resp->header('Content-Type', 'text/html; charset=utf-8');
            }

            $content = PhpHelper::exceptionToString($e, $this->isDebug(), false, __METHOD__);
        }

        $this->log(strip_tags($content), [], Logger::ERROR);

        if ($resp) {
            $resp->write($content);
            $this->respond($resp);
        }
    }
}
