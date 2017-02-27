<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/2/18
 * Time: 17:50
 */

namespace inhere\server;

use Swoole\Server as SwServer;
use Swoole\Http\Server as SwHttpServer;
use Swoole\Http\Response as SwResponse;
use Swoole\Http\Request as SwRequest;

/**
 * Class HttpServer
 * @package inhere\server
 */
class HttpServer extends TcpServer
{
    public static $getData;
    public static $postData;
    public static $headerData;
    public static $serverData;

    /**
     * The application instance.
     * @var \slimExt\base\App
     */
    public $app;

    /**
     * @var \Swoole\Server\Port
     */
    public $attachedListener;

    /**
     * @var \Swoole\Http\Response
     */
    public $swResponse;

    /**
     * create Application Callback
     * @var \Closure
     */
    private $createApplicationCallback;

    /**
     * handle Request Callback
     * @var \Closure
     */
    public $handleRequest;

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

    protected function init()
    {
        $this->swooleProtocolEvents['tcp'] = [
            'Connect' => 'onTcpConnect',
            'Close'   => 'onTcpClose',
            'Receive' => 'onTcpReceive',
        ];

        return parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function createMainServer()
    {
        $opts = $this->config['http_server'];

        if ( !$opts['enable'] ) {
            return parent::createMainServer();
        }

        $mode = $opts['mode'] === self::MODE_BASE ? SWOOLE_BASE : SWOOLE_PROCESS;

        // if want enable SSL(https)
        if ( self::PROTOCOL_HTTPS === $opts['type'] ) {
            $this->checkEnvWhenEnableSSL();
            $type = self::PROTOCOL_HTTPS;
            $socketType = SWOOLE_SOCK_TCP | SWOOLE_SSL;
        } else {
            $type = self::PROTOCOL_HTTP;
            $socketType = SWOOLE_SOCK_TCP;
        }

        // append current protocol event
        $this->swooleEvents = array_merge($this->swooleEvents, $this->swooleProtocolEvents[self::PROTOCOL_HTTP]);

        $this->addLog("Create a $type main server on <default>{$opts['host']}:{$opts['port']}</default>");

        // create swoole server
        $server = new SwHttpServer($opts['host'], $opts['port'], $mode, $socketType);

        // enable tcp server
        $this->attachTcpOrUdpServer($server);

        return $server;
    }

    /**
     * test: `telnet 127.0.0.1 9661`
     * @param SwServer $server
     */
    protected function attachTcpOrUdpServer(SwServer $server)
    {
        // enable tcp server
        if ($this->config->get('tcp_server.enable', false)) {

            $opts = $this->config['tcp_server'];
            $type = $opts['type'];
            $socketType = $type === 'udp' ? SWOOLE_SOCK_UDP : SWOOLE_SOCK_TCP;

            // listen() 此方法是 addListener() 的别名。返回的是 Swoole\Server\Port 实例
            $this->attachedListener = $server->listen($opts['host'], $opts['port'], $socketType);

            $this->addLog("Attach a $type listening service on <default>{$opts['host']}:{$opts['port']}</default>", [], 'info');

            $events = $this->swooleProtocolEvents[$type];
            $this->addLog("Register listen port events to the attached $type server:\n " . implode(',',$events), [], 'info');

            $this->registerListenerEvents($this->attachedListener, $events);

            // 设置tcp监听的配置，可以覆盖继承的主server(swoole_http_server)配置
            // NOTICE: 这里必须要调用 set(). 不然不会触发监听服务上的事件,即使传入空数组也行，但不能不调用。
            // $this->attachedListener->set($this->config['swoole']);
            $this->attachedListener->set([]);
        }
    }

    /**
     * createApplication
     * @param  \Closure|null $callback
     * @return static
     */
    public function createApplication(\Closure $callback = null)
    {
        /*
        $server = new HttpServer;
        $server->createApplication(function($mgr) {
            //实例化slim对象
            $mgr->app = require PROJECT_PATH . '/bootstrap/app_for_sw.php';
        });
        HttpServer::run();
        */

       $this->createApplicationCallback = $callback;

       return $this;
    }

    public function getDefaultConfig()
    {
        $config = parent::getDefaultConfig();

        $config['http_server'] = [
            'enable' => true,
            'host' => '0.0.0.0',
            'port' => '9662',

            // 运行模式
            // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
            'mode' => 'process', // 'process' 'base'

            // enable https(SSL)
            // 使用SSL必须在编译swoole时加入--enable-openssl选项 并且要在'swoole'中的配置相关信息(@see AServerManager::defaultConfig())
            'type' => 'http', // 'http' 'https'

            // static asset handle
            'enable_static' => true,
            'static_setting' => [
                // 'url_match' => 'assets dir',
                '/assets'  => 'public/assets',
                '/uploads' => 'public/uploads'
            ],
        ];

        // add swoole config
        $config['swoole']['http_parse_post'] = true;
        // POST/文件上传
        $config['swoole']['package_max_length'] = 1024 * 1024 * 10;

        return $config;
    }

//////////////////////////////////////////////////////////////////////
/// swoole event handler
//////////////////////////////////////////////////////////////////////

    /**
     * onWorkerStart
     * @param  SwServer $server
     * @param  int      $workerId
     */
    public function onWorkerStart(SwServer $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);

        // create application
        if ( !$server->taskworker &&  ($callback = $this->createApplicationCallback) ) {
            $this->app = $callback($this);

            $this->addLog("The app instance has been created, on the worker {$workerId}.", [
                $this->app->config->get('name'),
            ]);
        }
    }

    public function onRequest(SwRequest $request, SwResponse $response)
    {
        // 捕获异常
        register_shutdown_function(array($this, 'handleFatal'));

        $uri = $request->server['request_uri'];
        $method = $request->server['request_method'];
        $enableStatic = $this->config->get('http_server.enable_static', false);

        if ( $enableStatic && $this->handleStaticAssets($request, $response, $uri) ) {
            $this->addLog("Access asset: $uri");
            return true;
        }

        $this->addLog("$method $uri", [
            'app name' => \Slim::$app->config->get('name'),
            'GET' => $request->get,
            'POST' => $request->post,
        ]);

        // test: `curl 127.0.0.1:9501/ping`
        if ( $uri === '/ping' ) {
            return $response->end('+PONG' . PHP_EOL);
        }

        $this->swResponse = $response;
        // $this->collectionRequestData($request);

        try {
            if ( !($cb = $this->handleRequest) || !($cb instanceof \Closure) ) {
                $this->addLog("Please setting the 'handleRequest' property to handle http request.", [], 'error');

                throw new \LogicException("Please setting the 'handleRequest' property.");
            }

            $bodyContent = $cb($this, $request, $response);

            // open gzip
            $response->gzip(1);

            $response->end($bodyContent);
        } catch (\Exception $e) {
            var_dump($e);
        }

        return true;
    }

//    public function onPipeMessage(SwServer $server, $srcWorkerId, $data)
//    {}

//////////////////////////////////////////////////////////////////////
/// listen port handler
//////////////////////////////////////////////////////////////////////

    public function onTcpConnect(SwServer $server, $fd)
    {
        $this->addLog("Has a new client [FD:$fd] connection on the listen port.");
    }

    public function onTcpClose(SwServer $server, $fd)
    {
        $this->addLog("The client [FD:$fd] connection closed on the listen port.");
    }

    /**
     * TCP Port 接收到数据
     *     使用 `fd` 保存客户端IP，`from_id` 保存 `from_fd` 和 `port`
     * @param  SwServer $server
     * @param  int           $fd
     * @param  int           $fromId
     * @param  mixed         $data
     */
    public function onTcpReceive(SwServer $server, $fd, $fromId, $data)
    {
        $this->addLog("Receive data [$data] from client [FD:$fd] on the listen port.");

        $server->send($fd, "Server: ".$data);
    }

//////////////////////////////////////////////////////////////////////
/// some help method
//////////////////////////////////////////////////////////////////////

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

        $setting = $this->config->get('http_server.static_setting');

        // $this->addLog("begin check '.' point exists in the asset $uri");

        # 没有任何后缀 || 没有资源处理配置 返回交给php继续处理
        if (false === strrpos($uri, '.') || !$setting ) {
           return false;
        }

        $exts = array_keys(static::$staticAssets);
        $extReg = implode('|', $exts);

        // $this->addLog("begin match ext for the asset $uri, result: " . preg_match("/\.($extReg)/i", $uri, $matches), $exts);

        // 资源后缀匹配失败 返回交给php继续处理
        if ( 1 !== preg_match("/\.($extReg)/i", $uri, $matches) ) {
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
        $file = $this->config->get('root_path') . "/$assetDir/$path";

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
     * Collection Request Data
     * @param  SwRequest $request
     */
    public function collectionRequestData(SwRequest $request)
    {
        if (isset($request->server)) {
            self::$serverData = $request->server;
            foreach ($request->server as $key => $value) {
                $_SERVER[strtoupper($key)] = $value;
            }
        }

        if (isset($request->header)) {
            self::$headerData = $request->header;
        }

        if (isset($request->get)) {
            self::$getData = $request->get;
            foreach ($request->get as $key => $value) {
                $_GET[$key] = $value;
            }
        }

        if (isset($request->post)) {
            self::$postData = $request->post;
            foreach ($request->post as $key => $value) {
                $_POST[$key] = $value;
            }
        }
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
        if ($this->swResponse) {
            $this->swResponse->status(500);
            $this->swResponse->end($log);
            $this->swResponse = null;
        }
    }


}

