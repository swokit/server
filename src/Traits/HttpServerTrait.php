<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace Inhere\Server\Traits;

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

        // handle the static resource request
        $stHandler = $this->staticAccessHandler;

        if ($stHandler && $stHandler($request, $response, $uri)) {
            $this->log("Access asset: $uri");

            return true;
        }

        if ($error = $stHandler->getError()) {
            $this->log($error, [], Logger::ERROR);
        }

        // handle the Dynamic Request
        $this->handleHttpRequest($request, $response);

        return true;
    }

    abstract protected function handleHttpRequest(Request $request, Response $response);

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

        return $response->end();
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
}
