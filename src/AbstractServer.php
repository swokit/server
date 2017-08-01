<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 12:41
 */

namespace inhere\server;

use inhere\library\traits\ConfigTrait;
use inhere\server\helpers\ProcessHelper;
use inhere\server\helpers\ServerHelper;
use inhere\server\traits\ProcessManageTrait;
use Swoole\Process as SwProcess;
use Swoole\Server as SwServer;

use inhere\console\io\Input;
use inhere\console\io\Output;
use inhere\console\utils\Show;
use inhere\library\utils\LiteLogger;

/**
 * Class AServerManager
 * @package inhere\server
 *
 * Running processes:
 *
 * ```
 * create manager object ( $mgr = new SuiteServer )
 *       |
 *   init()
 *       |
 * some custom logic, if need. (eg: set a main server handler, set app service, register attach listen port service)
 *       |
 * SuiteServer::run()
 *       |
 *   bootstrap()
 *      |
 *   handleCommand() ------> start, stop, restart, help
 *      |
 *      | if need start server, go on.
 *      | no, exit.
 *      |
 *   startBaseService()
 *      |
 *   showInformation()
 *      |
 *    start() // do start swoole server.
 *
 * ```
 */
abstract class AbstractServer implements InterfaceServer
{
    use ProcessManageTrait;
    use ConfigTrait;

    /**
     * cli input instance
     * @var Input
     */
    protected $cliIn;

    /**
     * cli output instance
     * @var Output
     */
    protected $cliOut;

    /**
     * config data instance
     * @var array
     */
    protected $config = [
        // basic config
        'name' => '',
        'debug' => false,
        'root_path' => '',
        'pid_file' => '/tmp/swoole_server.pid',

        // will create a process auto reload server
        'auto_reload' => '', // 'src,config'

        // 当前server的日志配置(不是swoole的日志)
        'log_service' => [
            // 'name' => 'swoole_server_log'
            // 'basePath' => PROJECT_PATH . '/temp/logs/test_server',
            // 'logThreshold' => 0,
            // 'levels' => '',
        ],

        // for main server
        'main_server' => [
            'host' => '0.0.0.0',
            'port' => '8662',

            // 运行模式
            // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
            'mode' => 'process',
            'type' => 'tcp', // http https tcp udp ws wss

            // append register swoole events
            'extend_events' => [], // e.g [ 'onRequest', ]

            // use outside's extend event handler
            'extend_server' => '', // e.g '\inhere\server\extend\HttpServerHandler'
        ],

        // for attach servers
        'attach_servers' => [
            // 'tcp1' => [
            //     'host' => '0.0.0.0',
            //     'port' => '9661',
            //     'type' => 'tcp',

            // setting event handler
            //     'event_handler' => '', // e.g '\inhere\server\listeners\TcpListenHandler'
            //     'event_list'   => [], // e.g [ 'onReceive', ]
            // ],

            // 'udp1' => [
            //     'host' => '0.0.0.0',
            //     'port' => '9660',
            // ]
        ],

        // the swoole runtime setting
        'swoole' => [
            // 'user'    => '',
            'worker_num' => 4,
            'task_worker_num' => 2, // 启用 task worker,必须为Server设置onTask和onFinish回调
            'daemonize' => 0,
            'max_request' => 1000,
            // 在1.7.15以上版本中，当设置dispatch_mode = 1/3时会自动去掉onConnect/onClose事件回调。
            // see @link https://wiki.swoole.com/wiki/page/49.html
            'dispatch_mode' => 2,
            // 'log_file' , // '/tmp/swoole.log', // 不设置log_file会打印到屏幕

            // 使用SSL必须在编译swoole时加入--enable-openssl选项 并且配置下面两项
            // 'ssl_cert_file' => __DIR__.'/config/ssl.crt',
            // 'ssl_key_file' => __DIR__.'/config/ssl.key',
        ],
    ];

    /**
     * server manager
     * @var static
     */
    public static $mgr;
    private static $_statistics = [];

    private $bootstrapped = false;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var bool
     */
    private $daemon = false;

    /**
     * current server name
     * @var string
     */
    public $name;

    /**
     * pid File
     * @var string
     */
    public $pidFile = '';

    /**
     * @var \Swoole\Server
     * |SwHttpServer|SwWSServer
     */
    public $server;

    /**
     * @var SwProcess
     */
    public $reloadWorker;

    /**
     * @var array
     */
    protected $swooleEventMap = [
        // 'event'  => 'callback',
        'start' => 'onMasterStart',
        'shutdown' => 'onMasterStop',

        'managerStart' => 'onManagerStart',
        'managerStop' => 'onManagerStop',

        'workerStart' => 'onWorkerStart',
        'workerStop' => 'onWorkerStop',
        'workerError' => 'onWorkerError',

        'pipeMessage' => 'onPipeMessage',

        // Task 任务相关 (若配置了 task_worker_num 则必须注册这两个事件)
        'task' => 'onTask',
        'finish' => 'onFinish',
    ];

    /**
     * @var array
     */
    protected $swooleProtocolEvents = [
        // TCP server callback
        'tcp' => ['onConnect', 'onReceive', 'onClose'],

        // UDP server callback
        'udp' => ['onPacket', 'onClose'],

        // HTTP server callback
        'http' => ['onRequest'],

        // Web Socket server callback
        'ws' => ['onMessage', 'onOpen', 'onHandShake', 'onClose'],
    ];

    /**
     * BaseServer constructor.
     * @param array $config
     * @param bool $bootstrap
     */
    public function __construct(array $config = [], $bootstrap = false)
    {
        ServerHelper::checkRuntimeEnv();
        self::$mgr = $this;

        $this->setConfig($config);
        $this->init();
    }

    /**
     * @return $this
     * @throws \RuntimeException
     */
    protected function init()
    {
        if (!$this->pidFile = $this->getValue('pid_file')) {
            throw new \RuntimeException('The config option \'pid_file\' is must setting');
        }

        // project root path
        if (!$this->getValue('root_path')) {
            if (defined('PROJECT_PATH')) {
                $this->setConfig(['root_path' => PROJECT_PATH]);
            } else {
                throw new \RuntimeException('The project path \'root_path\' is must setting');
            }
        }

        if (!($this->name = $this->getValue('name'))) {
            $this->name = basename($this->getValue('root_path'));
            $this->setConfig(['name' => $this->name]);
        }

        // $currentUser = ServerHelper::getCurrentUser();

        // Get unix user of the worker process.
        // if (!$this->user = $this->getValue('swoole.user')) {
        //     $this->user = $currentUser;
        // } else if (posix_getuid() !== 0 && $this->user != $currentUser) {
        //     Show::block('You must have the root privileges to change uid and gid.', 'WARNING', 'warning');
        // }

        // Get server is debug mode
        $this->debug = (bool)$this->getValue('debug', false);

        return $this;
    }

//////////////////////////////////////////////////////////////////////
/// runtime logic
//////////////////////////////////////////////////////////////////////

    /**
     * @return $this
     */
    public function bootstrap()
    {
        $this->bootstrapped = false;

        // prepare start server
        $this
            ->startBaseService()
            ->showInformation();

        // create swoole server instance
        $this->server = $this->createMainServer();

        if (!$this->server || !($this->server instanceof SwServer)) {
            throw new \RuntimeException('The server instance must instanceof ' . SwServer::class);
        }

        // do something for main server
        $this->afterCreateServer();

        $this->bootstrapped = true;

        return $this;
    }

    protected function startBaseService()
    {
        // create log service instance
        if ($logService = $this->getValue('log_service')) {
            LiteLogger::make($logService);
        }

        return $this;
    }

    /**
     * Show server info
     */
    protected function showInformation()
    {
        // output a message before start
        if ($this->daemon) {
            Show::write("You can use <info>stop</info> command to stop server.\n");
        } else {
            Show::write("Press <info>Ctrl-C</info> to quit.\n");
        }
    }

    /**
     * show server runtime status information
     */
    protected function showRuntimeStatus()
    {
        Show::notice('Sorry, The function un-completed!', 0);
    }

    /**
     * @return SwServer
     */
    abstract protected function createMainServer();

    /**
     * afterCreateServer
     * @throws \RuntimeException
     */
    protected function afterCreateServer()
    {
        // register swoole events handler
        $this->registerServerEvents();

        // setting swoole config
        $this->server->set($this->config['swoole']);

        // create Reload Worker
        $this->createHotReloader($this->server);
    }

    /**
     * before Server Start
     * @param \Closure|null $callback
     */
    public function beforeServerStart(\Closure $callback = null)
    {
        if ($callback) {
            $callback($this);
        }
    }

    /**
     * register Swoole Events
     */
    protected function registerServerEvents()
    {
        $events = $this->swooleEventMap;
        Show::aList($events, 'Registered swoole events to the main server:( event -> handler )');

        foreach ($events as $event => $callback) {

            // e.g $server->on('Request', [$this, 'onRequest']);
            if (method_exists($this, $callback)) {
                $this->server->on($event, [$this, $callback]);
            }
        }
    }

//////////////////////////////////////////////////////////////////////
/// swoole event handler
//////////////////////////////////////////////////////////////////////

    /**
     * on Master Start
     * @param  SwServer $server
     */
    public function onMasterStart(SwServer $server)
    {
        $masterPid = $server->master_pid;
        $projectPath = $this->getValue('root_path');

        // save master process id to file.
        if ($pidFile = $this->pidFile) {
            file_put_contents($this->pidFile, $masterPid);
        }

        ProcessHelper::setTitle("swoole: master ({$this->name} IN $projectPath)");

        $this->log("The master process success started. (PID:<notice>{$masterPid}</notice>, pid_file: $pidFile)");
    }

    /**
     * on Master Stop
     * @param  SwServer $server
     */
    public function onMasterStop(SwServer $server)
    {
        $this->log("The swoole master process(PID: {$server->master_pid}) stopped.");

        $this->doClear();
    }

    /**
     * doClear
     */
    protected function doClear()
    {
        if ($this->pidFile && file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        self::$_statistics['stop_time'] = microtime(1);
    }

    /**
     * onConnect
     * @param  SwServer $server
     * @param  int $fd 客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
     */
    abstract public function onConnect(SwServer $server, $fd);

    abstract public function onClose(SwServer $server, $fd);

    /**
     * on Manager Start
     * @param  SwServer $server
     */
    public function onManagerStart(SwServer $server)
    {
        // file_put_contents($pidFile, ',' . $server->manager_pid, FILE_APPEND);
        ProcessHelper::setTitle("swoole: manager ({$this->name})");

        $this->log("The manager process success started. (PID:{$server->manager_pid})");
    }

    /**
     * on Manager Stop
     * @param  SwServer $server
     */
    public function onManagerStop(SwServer $server)
    {
        $this->log("The swoole manager process stopped. (PID {$server->manager_pid})");
    }

    /**
     * on Worker Start
     *   应当在onWorkerStart中创建连接对象
     * @link https://wiki.swoole.com/wiki/page/325.html
     * @param  SwServer $server
     * @param  int $workerId The worker index id in the all workers.
     */
    public function onWorkerStart(SwServer $server, $workerId)
    {
        $taskMark = $server->taskworker ? 'task-worker' : 'event-worker';

        $this->log("The #<primary>{$workerId}</primary> {$taskMark} process success started. (PID:{$server->worker_pid})");

        ProcessHelper::setTitle("swoole: {$taskMark} ({$this->name})");

        // ServerHelper::setUserAndGroup();

        // 此数组中的文件表示进程启动前就加载了，所以无法reload
        // Show::write('进程启动前就加载了，无法reload的文件：');
        // Show::write(get_included_files());
    }

    /**
     * @param SwServer $server
     * @param $workerId
     */
    public function onWorkerStop(SwServer $server, $workerId)
    {
        $this->log("The swoole #<info>$workerId</info> worker process stopped. (PID:{$server->worker_pid})");
    }

    /**
     * onPipeMessage
     *  能接收到 `$server->sendMessage()` 发送的消息
     * @param  SwServer $server
     * @param  int $srcWorkerId
     * @param  mixed $data
     */
    public function onPipeMessage(SwServer $server, $srcWorkerId, $data)
    {
        $this->log("#{$server->worker_id} message from #$srcWorkerId: $data");
    }

    ////////////////////// Task Event //////////////////////

    /**
     * 处理异步任务( onTask )
     * @param  SwServer $server
     * @param  int $taskId
     * @param  int $fromId
     * @param  mixed $data
     */
    public function onTask(SwServer $server, $taskId, $fromId, $data)
    {
        // $this->log("Handle New AsyncTask[id:$taskId]");
        // 返回任务执行的结果(finish操作是可选的，也可以不返回任何结果)
        // $server->finish("$data -> OK");
    }

    /**
     * 处理异步任务的结果
     * @param  SwServer $server
     * @param  int $taskId
     * @param  mixed $data
     */
    public function onFinish(SwServer $server, $taskId, $data)
    {
        $this->log("AsyncTask[$taskId] Finish. Data: $data");
    }

//////////////////////////////////////////////////////////////////////
/// swoole server method
//////////////////////////////////////////////////////////////////////

    /**
     * @param $clientId
     * @param $data
     * @return bool|mixed
     */
    public function send($clientId, $data)
    {
        return $this->server->send($clientId, $data);
    }

//////////////////////////////////////////////////////////////////////
/// getter/setter method
//////////////////////////////////////////////////////////////////////

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @return bool
     */
    public function isDaemon(): bool
    {
        return $this->daemon;
    }

    /**
     * @param array $events
     */
    public function setSwooleEvents(array $events)
    {
        foreach ($events as $key => $value) {
            $this->setSwooleEvent(
                is_int($key) && is_string($value) ? lcfirst(substr($value, 2)) : $key,
                $value
            );
        }
    }

    /**
     * register a swoole Event Handler Callback
     * @param string $event
     * @param callable|string $handler
     */
    public function onSwoole($event, $handler)
    {
        $this->setSwooleEvent($event, $handler);
    }

    /**
     * @param string $event The event name
     * @param string|\Closure $cb The callback name
     */
    public function setSwooleEvent($event, $cb)
    {
        $event = trim($event);

        if (!$this->isSupportedEvents($event)) {
            $supported = implode(',', self::SWOOLE_EVENTS);
            Show::error("You want add a not supported swoole event: $event. supported: \n $supported", -2);
        }

        $this->swooleEventMap[$event] = $cb;
    }

    /**
     * @return array
     */
    public function getSwooleEventMap()
    {
        return $this->swooleEventMap;
    }

    /**
     * has Logger service
     * @param  null|string $name
     * @return boolean
     */
    public function hasLogger($name = null)
    {
        $name = $name ?: $this->getValue('log_service.name');

        return $name && LiteLogger::has($name);
    }

    /**
     * get Logger service
     * @return LiteLogger
     * @throws \RuntimeException
     */
    public function getLogger()
    {
        $name = $this->getValue('log_service.name');

        if ($this->hasLogger($name)) {
            return LiteLogger::get($name);
        }

        throw new \RuntimeException('You don\'t config log service!');
    }

    /**
     * @return array
     */
    public function getSupportedProtocols()
    {
        return [
            self::PROTOCOL_HTTP,
            self::PROTOCOL_HTTPS,
            self::PROTOCOL_TCP,
            self::PROTOCOL_UDP,
            self::PROTOCOL_WS,
            self::PROTOCOL_WSS,
        ];
    }

    /**
     * @return array
     */
    public function getSwooleEvents()
    {
        return self::SWOOLE_EVENTS;
    }

    /**
     * @param string $event
     * @return bool
     */
    public function isSupportedEvents($event)
    {
        return in_array($event, self::SWOOLE_EVENTS, true);
    }

    /**
     * @param null|string $protocol
     * @return array
     */
    public function getSwooleProtocolEvents($protocol = null)
    {
        if (null === $protocol) {
            return $this->swooleProtocolEvents;
        }

        return $this->swooleProtocolEvents[$protocol] ?? null;
    }


//////////////////////////////////////////////////////////////////////
/// some help method(from swoole)
//////////////////////////////////////////////////////////////////////

    /**
     * checkEnvWhenEnableSSL
     */
    protected function checkEnvWhenEnableSSL()
    {
        if (!defined('SWOOLE_SSL')) {
            Show::error('If you want use SSL(https), must add option --enable-openssl on the compile swoole.', 1);
        }

        // check ssl config
        if (!$this->getValue('swoole.ssl_cert_file') || !$this->getValue('swoole.ssl_key_file')) {
            Show::error("If you want use SSL(https), must config the 'swoole.ssl_cert_file' and 'swoole.ssl_key_file'", 1);
        }
    }

    /**
     * 使当前worker进程停止运行，并立即触发onWorkerStop回调函数
     * @param null|int $workerId
     * @return bool
     */
    public function stopWorker($workerId = null)
    {
        if ($this->server) {
            return $this->server->stop($workerId);
        }

        return false;
    }

//////////////////////////////////////////////////////////////////////
/// some help method
//////////////////////////////////////////////////////////////////////

    /**
     * output log message
     * @param  string $msg
     * @param  array $data
     * @param string $type
     * @return void
     * @throws \RuntimeException
     */
    public function log($msg, array $data = [], $type = 'info')
    {
        if (!$this->debug && $type !== 'debug') {
            return;
        }

        // if close debug, don't output debug log.
        if (!$this->daemon) {
            list($ts, $ms) = explode('.', sprintf('%f', microtime(true)));
            $ms = str_pad($ms, 6, 0);
            $time = date('Y-m-d H:i:s', $ts);

            $json = $data ? json_encode($data) : '';
            $type = strtoupper($type);
            Show::write("[{$time}.{$ms}] [$type] $msg {$json}");
        }

        if ($this->hasLogger()) {
            $this->getLogger()->$type(strip_tags($msg), $data);
        }

        // return;
    }

    /**
     * output a debug log message
     * @param $msg
     * @param array $data
     * @throws \RuntimeException
     */
    public function debug($msg, array $data = [])
    {
        $this->log($msg, $data);
    }
}
