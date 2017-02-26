<?php

namespace inhere\server;

use swoole_http_server;
use swoole_websocket_server;
use swoole_http_request;
use swoole_http_response;
use swoole_process;

class HttpServer1
{
    private static $_masterPid = 0;
    public static $pidFile = '';
    public static $project = '';

    public static $instance;
    public static $get;
    public static $post;
    public static $header;
    public static $server;

    public $swServer;
    public $swResponse = null;

    /**
     * The application instance.
     * @var \slimExt\base\App
     */
    private $app;

    public static $events = [
        'Start'       => 'onMasterStart',
        // 'Open'       => 'onServerOpen',
        'Shutdown'    => 'onMasterStop',

        'ManagerStart' => 'onManagerStart',
        // 'ManagerStop' => 'onManagerStop',

        'WorkerStart' => 'onWorkerStart',
        // 'WorkerStop' => 'onWorkerStop',
        // 'WorkerError' => 'onWorkerError',

        'Request'     => 'onRequest',
        // 'Message'     => 'onMessage',
        // 'PipeMessage'     => 'onPipeMessage',

        // 仅对TCP服务有效
        // 'Connect'     => 'onSwooleConnect',
        // 'Receive'     => 'onSwooleReceive',
        // 'Close'     => 'onSwooleClose',
        // 'Packet'     => 'onSwoolePacket',


        // 'Task'     => 'onSwooleTask',
        // 'Finish'     => 'onSwooleFinish',
    ];

    public function __construct()
    {
        $string = "Running: \n  HOST 0.0.0.0:9501 \n  worker_num 5 \n";
        fwrite(\STDOUT, $string . PHP_EOL);

        // create application
        // $this->createApp();

        self::$pidFile = PROJECT_PATH . '/temp/http_server.pid';
        self::$project = basename(PROJECT_PATH);

        // create swoole server
        $this->swServer = new swoole_http_server("0.0.0.0", 9501);
        $this->swServer->set([
            'worker_num'    => 5,
            'daemonize'     => false,
            'max_request'   => 10000,
            'dispatch_mode' => 1,
            'log_file'      => PROJECT_PATH . '/temp/logs/http_server.log'
        ]);

        $this->registerSwooleEvents();

        // start swoole server
        $this->swServer->start();
    }

    public function createApp()
    {
        //实例化slim对象
        $this->app = require PROJECT_PATH . '/bootstrap/app_for_sw.php';
        // require_once PROJECT_PATH . '/bootstrap/app_for_sw.php';
    }

    public function registerSwooleEvents()
    {
        foreach (self::$events as $event => $handler ) {
            // e.g $this->swServer->on('request', [$this, 'onRequest']);
            $this->swServer->on($event, [$this, $handler]);
        }
    }

    public function onRequest(swoole_http_request $request, swoole_http_response $response)
    {
        //捕获异常
        register_shutdown_function(array($this, 'handleFatal'));

        //请求过滤
        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            return $response->end();
        }

        $string = '[' . date('Ymd H:i:s') . "] REQUEST_URI: " . $request->server['request_uri'];
        fwrite(\STDOUT, $string . PHP_EOL);

        $this->swResponse = $response;
        $this->collectionRequestData($request);

        // ob_start();
        try {
            // Run app
            // $this->app->run();
            $output = $this->app->run(true);
        } catch (Exception $e) {
            var_dump($e);
        }
        // $result = ob_get_contents();
        // ob_end_clean();

        $this->swResponse->end($output);
        // $this->swResponse = null;

        unset($result);
    }

    public function collectionRequestData($request)
    {
        if (isset($request->server)) {
            self::$server = $request->server;
            foreach ($request->server as $key => $value) {
                $_SERVER[strtoupper($key)] = $value;
            }
        }
        if (isset($request->header)) {
            self::$header = $request->header;
        }
        if (isset($request->get)) {
            self::$get = $request->get;
            foreach ($request->get as $key => $value) {
                $_GET[$key] = $value;
            }
        }
        if (isset($request->post)) {
            self::$post = $request->post;
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

    public function onMasterStart($serv)
    {
        self::$_masterPid = $serv->master_pid;

        file_put_contents(self::$pidFile, self::$_masterPid);
        // file_put_contents(self::$pidFile, ',' . $serv->manager_pid, FILE_APPEND);
        self::setProcessTitle('swoole: master (project: ' . self::$project . ' IN ' . PROJECT_PATH . ')');

        // require __DIR__ . '/../vendor/autoload.php';
        // session_start();
    }

    public function onMasterStop()
    {
        if ( self::$pidFile ) {
            unlink(self::$pidFile);
            self::$pidFile = '';
        }
    }

    public function onManagerStart($serv)
    {
        self::setProcessTitle('swoole: manager (project: ' . self::$project . ')');
    }

    public function onWorkerStart($serv)
    {
        self::setProcessTitle('swoole: worker (project: ' . self::$project . ')');

        // require __DIR__ . '/../vendor/autoload.php';
        // session_start();

        // create application
        $this->createApp();
    }


    /**
     * Get unix user of current porcess.
     *
     * @return string
     */
    protected static function getCurrentUser()
    {
        $user_info = posix_getpwuid(posix_getuid());
        return $user_info['name'];
    }

    protected static function getPid()
    {
        $pid_file = self::$pidFile;

        if (file_exists($pid_file)) {
            $pid = file_get_contents($pid_file);

            if (posix_getpgid($pid)) {
                return $pid;
            } else {
                unlink($pid_file);
            }
        }
        return false;
    }

    /**
     * Set process name.
     *
     * @param string $title
     * @return void
     */
    public static function setProcessTitle($title)
    {
        // >=php 5.5
        if (function_exists('cli_set_process_title') && !self::isMac()) {
            @cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        else {
            @swoole_set_process_name($title);
        }
    }

    /**
     * @return bool
     */
    public static function isMac()
    {
        return strstr(PHP_OS, 'Darwin') ? true : false;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new HttpServer;
        }
        return self::$instance;
    }
}

