<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\handlers;

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
 *
 * ```
 *
 */
class HttpServerHandler extends AbstractServerHandler
{
    /**
     * handle request (for http server)
     * @var \Closure
     */
    public $requestHandler;

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

    /**
     * onWorkerStart
     * @param  SwServer $server
     * @param  int      $workerId
     */
    public function onWorkerStart(SwServer $server, $workerId)
    {
        $this->mgr->onWorkerStart($server, $workerId);

        // create application
        // if ( !$server->taskworker &&  ($callback = $this->createApplication()) ) {
        //     $this->mgr->app = $callback($this->mgr);

        //     $this->addLog("The app instance has been created, on the worker {$workerId}.");
        // }
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

        if ( $enableStatic && $this->handleStaticAssets($request, $response, $uri) ) {
            $this->addLog("Access asset: $uri");
            return true;
        }

        $this->addLog("$method $uri", [
            'GET' => $request->get,
            'POST' => $request->post,
        ]);

        // test: `curl 127.0.0.1:9501/ping`
        if ( $uri === '/ping' ) {
            return $response->end('+PONG' . PHP_EOL);
        }

        $this->response = $response;
        // $this->collectionRequestData($request);

        try {
            $bodyContent = $this->handleDynamicRequest($request, $response);

            // open gzip
            $response->gzip(1);

            $response->end($bodyContent);
        } catch (\Exception $e) {
            var_dump($e);
        }

        return true;
    }

    /**
     * create and register our application
     * @return callable|null
     */
    protected function createApplication()
    {
        /*
        return function($mgr) {

        };
        */

        return null;
    }

    /**
     * handle the Dynamic Request
     * @param SwRequest $request
     * @param SwResponse $response
     * @return string
     */
    protected function handleDynamicRequest(SwRequest $request, SwResponse $response)
    {
        $this->addLog("Please implements the method 'handleDynamicRequest' on the subclass.");

        // throw new \LogicException("Please setting the 'requestHandler' property.");

        return 'No content to display';
    }

    /**
     * 输出静态文件
     *
     * @param SwRequest $request
     * @param SwResponse $response
     * @param string $uri
     * @return bool
     */
    protected function handleStaticAssets(SwRequest $request, SwResponse $response, $uri = '')
    {
        $uri = $uri ?:$request->server['request_uri'];

        // 请求 /favicon.ico 过滤
        if ($request->server['path_info'] === '/favicon.ico' || $uri === '/favicon.ico') {
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
