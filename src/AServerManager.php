<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 12:41
 */

namespace inhere\server;

use inhere\library\helpers\ProcessHelper;
use Swoole\Http\Server as SwHttpServer;
use Swoole\Websocket\Server as SwWSServer;
use Swoole\Process as SwProcess;
use Swoole\Server as SwServer;

use inhere\server\interfaces\IServerManager;

use inhere\console\io\Input;
use inhere\console\io\Output;
use inhere\library\collections\Config;
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
abstract class AServerManager implements IServerManager
{
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
     * @var Config
     */
    public $config;

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
    private $daemonize = false;

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
     * @var SwServer|SwHttpServer|SwWSServer
     */
    public $server;

    /**
     * @var SwProcess
     */
    public $reloadWorker;

    /**
     * @var array
     */
    protected $supportedEvents = [
        // basic
        'start', 'shutdown', 'workerStart', 'workerStop', 'workerError', 'managerStart', 'managerStop',
        // special
        'pipeMessage',
        // tcp/udp
        'connect', 'receive', 'packet', 'close',
        // task
        'task', 'finish',
        // http server
        'request',
        // webSocket server
        'message', 'open', 'handShake'
    ];

    /**
     * @var array
     */
    protected $swooleEvents = [
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
     * @param Input|null $input
     * @param Output|null $output
     */
    public function __construct(array $config = [], Input $input = null, Output $output = null)
    {
        ServerHelper::checkRuntimeEnv();
        self::$mgr = $this;

        $this->cliIn = $input ?: new Input();
        $this->cliOut = $output ?: new Output();

        $this->config = new Config($this->getDefaultConfig());

        if ($config) {
            $this->config->loadArray($config);
        }

        $this->init($this->config);
    }

    /**
     * @param Config $config
     * @return $this
     */
    protected function init(Config $config)
    {
        if (!$this->pidFile = $config->get('pid_file')) {
            throw new \RuntimeException('The config option \'pid_file\' is must setting');
        }

        // project root path
        if (!$config->get('root_path')) {
            if (defined('PROJECT_PATH')) {
                $this->setConfig(['root_path' => PROJECT_PATH]);
            } else {
                throw new \RuntimeException('The project path \'root_path\' is must setting');
            }
        }

        if (!($this->name = $config->get('name'))) {
            $this->name = basename($config->get('root_path'));
            $this->setConfig(['name' => $this->name]);
        }

        // $currentUser = ServerHelper::getCurrentUser();

        // Get unix user of the worker process.
        // if (!$this->user = $this->config->get('swoole.user')) {
        //     $this->user = $currentUser;
        // } else if (posix_getuid() !== 0 && $this->user != $currentUser) {
        //     $this->cliOut->block('You must have the root privileges to change uid and gid.', 'WARNING', 'warning');
        // }

        // Get server is debug mode
        $this->debug = (bool)$config->get('debug', false);

        return $this;
    }

    /**
     * run
     * @param  array $config
     * @param bool $start
     * @return static
     */
    public static function run($config = [], $start = true)
    {
        if (!self::$mgr) {
            new static($config);
        }

        return self::$mgr->bootstrap($start);
    }

    /**
     * @return array
     */
    public function getDefaultConfig()
    {
        return [
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
    }

//////////////////////////////////////////////////////////////////////
/// runtime logic
//////////////////////////////////////////////////////////////////////

    /**
     * bootstrap
     * @param  boolean $start
     * @return static
     */
    public function bootstrap($start = true)
    {
        $this->bootstrapped = false;
        $this
            ->handleCommand()
            // prepare start server
            ->startBaseService()
            ->showInformation();

        $this->bootstrapped = true;

        if ($start) {
            $this->start();
        }

        return $this;
    }

    /**
     * Do start server
     */
    public function start()
    {
        if (!$this->bootstrapped) {
            $this->cliOut->error('Call start before must run bootstrap().', -500);
        }

        self::$_statistics['start_time'] = microtime(1);

        // create swoole server instance
        $this->server = $this->createMainServer();

        if (!$this->server || !($this->server instanceof SwServer)) {
            throw new \RuntimeException('The server instance must instanceof ' . SwServer::class);
        }

        // do something for main server
        $this->afterCreateMainServer();

        $this->beforeServerStart();

        // 对于Server的配置即 $server->set() 中传入的参数设置，必须关闭/重启整个Server才可以重新加载
        $this->server->start();

        return $this->bootstrapped;
    }

    /**
     * Handle Command
     * e.g
     *     php bin/test_server.php start -d
     * @return static
     */
    protected function handleCommand()
    {
        $command = $this->cliIn->getCommand(); // e.g 'start'

        $this->checkInputCommand($command);

        $masterPid = ProcessHelper::getPidFromFile($this->pidFile);
        $masterIsStarted = ($masterPid > 0) && @posix_kill($masterPid, 0);

        // start: do Start Server
        if ($command === 'start') {

            // check master process is running
            if ($masterIsStarted) {
                $this->cliOut->error("The swoole server({$this->name}) have been started. (PID:{$masterPid})", true);
            }

            // run is daemonize
            $this->daemonize = (bool)$this->cliIn->boolOpt('d', $this->config->get('swoole.daemonize'));
            $this->setConfig(['swoole' => ['daemonize' => $this->daemonize]]);

            // if isn't daemonize mode, don't save swoole log to file
            if (!$this->daemonize) {
                $this->setConfig(['swoole' => ['log_file' => null]]);
            }

            return $this;
        }

        // check master process
        if (!$masterIsStarted) {
            $this->cliOut->error("The swoole server({$this->name}) is not running.", true);
        }

        // switch command
        switch ($command) {
            case 'stop':
            case 'restart':
                // stop: stop and exit. restart: stop and start
                $this->doStopServer($masterPid, $command === 'stop');
                break;

            case 'reload':
                $this->doReloadWorkers($masterPid, $this->cliIn->boolOpt('task'));
                break;

            case 'info':
                $this->showInformation();
                exit(0);
                break;

            case 'status':
                $this->showRuntimeStatus();
                break;

            default:
                $this->cliOut->error("The command [{$command}] is don't supported!");
                $this->showHelpPanel();
                break;
        }

        return $this;
    }

    /**
     * @param string $command
     */
    protected function checkInputCommand($command)
    {
        $notSp = false;
        $supportCommands = ['start', 'reload', 'restart', 'stop', 'info', 'status'];

        // show help info
        if (
            // no input
            !$command ||
            // command equal to 'help'
            $command === 'help' ||
            // is an not supported command
            ($notSp = !in_array($command, $supportCommands, true)) ||
            // has option -h|--help
            $this->cliIn->boolOpt('h', false) ||
            $this->cliIn->boolOpt('help', false)
        ) {
            if ($notSp) {
                $this->cliOut->error("The command [$command] is not supported! Please see the help information below.");
            }

            $this->showHelpPanel();
        }
    }

    protected function startBaseService()
    {
        // create log service instance
        if ($logService = $this->config->get('log_service')) {
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
        if ($this->daemonize) {
            $scriptName = $this->cliIn->getScriptName(); // 'bin/test_server.php'

            if (strpos($scriptName, '.') && 'php' === pathinfo($scriptName, PATHINFO_EXTENSION)) {
                $scriptName = 'php ' . $scriptName;
            }

            $this->cliOut->write("Input <info>$scriptName stop</info> to quit.\n");
        } else {
            $this->cliOut->write("Press <info>Ctrl-C</info> to quit.\n");
        }
    }

    /**
     * show server runtime status information
     */
    protected function showRuntimeStatus()
    {
        $this->cliOut->notice("Sorry, The function un-completed!", 0);
    }

    /**
     * @return SwServer
     */
    abstract protected function createMainServer();

    /**
     * afterCreateMainServer
     */
    protected function afterCreateMainServer()
    {
        // register swoole events handler
        $this->registerMainServerEvents();

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
    protected function registerMainServerEvents()
    {
        $events = $this->swooleEvents;
        $this->cliOut->aList($events, 'Registered swoole events to the main server:( event -> handler )');

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
        $projectPath = $this->config->get('root_path');

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
        // $this->cliOut->write('进程启动前就加载了，无法reload的文件：');
        // $this->cliOut->write(get_included_files());
    }

    /**
     * @param SwServer $server
     * @param $workerId
     */
    public function onWorkerStop(SwServer $server, $workerId)
    {
        $this->log("The swoole #<info>$workerId</info> worker process stopped.");
    }

    /**
     * onPipeMessage
     *  能接收到 `$server->sendMessage` 发送的消息
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
    public function isDaemonize(): bool
    {
        return $this->daemonize;
    }

    /**
     * @param array $events
     */
    public function setSwooleEvents(array $events)
    {
        foreach ($events as $key => $value) {
            $this->setSwooleEvent(is_int($key) ? lcfirst(substr($value, 2)) : $key, $value);
        }
    }

    /**
     * @param string $event The event name
     * @param string $cbName The callback name
     */
    public function setSwooleEvent($event, $cbName)
    {
        if (!$this->isSupportedEvents($event)) {
            $supported = implode(',', $this->supportedEvents);
            $this->cliOut->error("You want add a not supported swoole event: $event. supported: \n $supported", -2);
        }

        $this->swooleEvents[$event] = $cbName;
    }

    /**
     * @return array
     */
    public function getSwooleEvents()
    {
        return $this->swooleEvents;
    }

    /**
     * set Config
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->config->loadArray($config);
    }

    /**
     * get Config
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     *  get CLI input instance
     * @return Input
     */
    public function getCliIn()
    {
        return $this->cliIn;
    }

    /**
     *  get CLI output instance
     * @return Output
     */
    public function getCliOut()
    {
        return $this->cliOut;
    }

    /**
     * has Logger service
     * @param  null|string $name
     * @return boolean
     */
    public function hasLogger($name = null)
    {
        $name = $name ?: $this->config->get('log_service.name');

        return $name && LiteLogger::has($name);
    }

    /**
     * get Logger service
     * @return LiteLogger
     */
    public function getLogger()
    {
        $name = $this->config->get('log_service.name');

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
    public function getSupportedEvents()
    {
        return $this->supportedEvents;
    }

    /**
     * @param string $event
     * @return bool
     */
    public function isSupportedEvents($event)
    {
        return in_array($event, $this->supportedEvents);
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

        return isset($this->swooleProtocolEvents[$protocol]) ? $this->swooleProtocolEvents[$protocol] : null;
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
            $this->cliOut->error('If you want use SSL(https), must add option --enable-openssl on the compile swoole.', 1);
        }

        // check ssl config
        if (!$this->config->get('swoole.ssl_cert_file') || !$this->config->get('swoole.ssl_key_file')) {
            $this->cliOut->error("If you want use SSL(https), must config the 'swoole.ssl_cert_file' and 'swoole.ssl_key_file'", 1);
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

    /**
     * do Reload Workers
     * @param  int $masterPid
     * @param  boolean $onlyTaskWorker
     */
    public function doReloadWorkers($masterPid, $onlyTaskWorker = false)
    {
        // SIGUSR1: 向管理进程发送信号，将平稳地重启所有worker进程; 也可在PHP代码中调用`$server->reload()`完成此操作
        $sig = SIGUSR1;

        // SIGUSR2: only reload task worker
        if ($onlyTaskWorker) {
            $sig = SIGUSR2;
            $this->cliOut->notice("Will only reload task worker");
        }

        if (!posix_kill($masterPid, $sig)) {
            $this->cliOut->error("The swoole server({$this->name}) worker process reload fail!", -1);
        }

        $this->cliOut->success("The swoole server({$this->name}) worker process reload success.", 0);
    }

    /**
     * Do stop swoole server
     * @param  int $masterPid Master Pid
     * @param  boolean $quit Quit, When stop success?
     */
    protected function doStopServer($masterPid, $quit = true)
    {
        $this->cliOut->write("The swoole server({$this->name}) process stopping ...");

        // do stop
        // 向主进程发送此信号(SIGTERM)服务器将安全终止；也可在PHP代码中调用`$server->shutdown()` 完成此操作
        $masterPid && posix_kill($masterPid, SIGTERM);

        $timeout = 5;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            $masterIsStarted = ($masterPid > 0) && @posix_kill($masterPid, 0);

            if (!$masterIsStarted) {
                break;
            }

            // have been timeout
            if ((time() - $startTime) >= $timeout) {
                $this->cliOut->error("The swoole server({$this->name}) process stop fail!", -1);
            }

            usleep(10000);
            continue;
        }

        if ($this->pidFile && file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        // stop success
        $this->cliOut->success("The swoole server({$this->name}) process stop success.", $quit);
    }

    /**
     * create code hot reload worker
     * @see https://wiki.swoole.com/wiki/page/390.html
     * @param  SwServer $server
     * @return bool
     */
    protected function createHotReloader(SwServer $server)
    {
        $mgr = $this;
        $reload = $this->config->get('auto_reload');

        if (!$reload || !function_exists('inotify_init')) {
            return false;
        }

        $options = [
            'dirs' => $reload,
            'masterPid' => $server->master_pid
        ];

        // 创建用户自定义的工作进程worker
        $this->reloadWorker = new SwProcess(function (SwProcess $process) use ($options, $mgr) {

            ProcessHelper::setTitle("swoole: reloader ({$mgr->name})");
            $kit = new AutoReloader($options['masterPid']);

            $onlyReloadTask = isset($options['only_reload_task']) ? (bool)$options['only_reload_task'] : false;
            $dirs = array_map('trim', explode(',', $options['dirs']));

            $mgr->log("The reloader worker process success started. (PID: {$process->pid}, Watched: <info>{$options['dirs']}</info>)");

            $kit
                ->addWatches($dirs, $this->config->get('root_path'))
                ->setReloadHandler(function ($pid) use ($mgr, $onlyReloadTask) {
                    $mgr->log("Begin reload workers process. (Master PID: {$pid})");
                    $mgr->server->reload($onlyReloadTask);
                    // $mgr->doReloadWorkers($pid, $onlyReloadTask);
                });

            //Interact::section('Watched Directory', $kit->getWatchedDirs());

            $kit->run();

            // while (true) {
            //     $msg = $process->read();
            //     // 重启所有worker进程
            //     if ( $msg === 'reload' ) {
            //         $onlyReloadTaskWorker = false;

            //         $server->reload($onlyReloadTaskWorker);
            //     } else {
            //         foreach($server->connections as $conn) {
            //             $server->send($conn, $msg);
            //         }
            //     }
            // }
        });

        // addProcess添加的用户进程中无法使用task投递任务，请使用 $server->sendMessage() 接口与工作进程通信
        $server->addProcess($this->reloadWorker);

        return true;
    }

//////////////////////////////////////////////////////////////////////
/// some help method
//////////////////////////////////////////////////////////////////////

    /**
     * Show help
     * @param  boolean $showHelpAfterQuit
     */
    public function showHelpPanel($showHelpAfterQuit = true)
    {
        $scriptName = $this->cliIn->getScriptName(); // 'bin/test_server.php'

        if (strpos($scriptName, '.') && 'php' === pathinfo($scriptName, PATHINFO_EXTENSION)) {
            $scriptName = 'php ' . $scriptName;
        }

        $this->cliOut->helpPanel([
            'description' => 'Swoole server manager tool, Version <comment>' . self::VERSION . '</comment>. Update time ' . self::UPDATE_TIME,
            'usage' => "$scriptName {start|reload|restart|stop|status} [-d]",
            'commands' => [
                'start' => 'Start the server',
                'reload' => 'Reload all workers of the started server',
                'restart' => 'Stop the server, After start the server.',
                'stop' => 'Stop the server',
                'info' => 'Show the server information for current project',
                'status' => 'Show the started server status information',
                'help' => 'Display this help message',
            ],
            'options' => [
                '-d' => 'Run the server on daemonize.',
                '--task' => 'Only reload task worker, when reload server',
                '-h, --help' => 'Display this help message',
            ],
            'examples' => [
                "<info>$scriptName start -d</info> Start server on daemonize mode.",
                "<info>$scriptName reload --task</info> Start server on daemonize mode."
            ],
        ], $showHelpAfterQuit);
    }

    /**
     * output log message
     * @param  string $msg
     * @param  array $data
     * @param string $type
     * @return void
     */
    public function log($msg, $data = [], $type = 'info')
    {
        if (!$this->debug && $type !== 'debug') {
            return;
        }

        // if close debug, don't output debug log.
        if (!$this->daemonize) {
            [$ts, $ms] = explode('.', sprintf('%f', microtime(true)));
            $ms = str_pad($ms, 6, 0);
            $time = date('Y-m-d H:i:s', $ts);

            $json = $data ? json_encode($data) : '';
            $type = strtoupper($type);
            $this->cliOut->write("[{$time}.{$ms}] [$type] $msg {$json}");
        }

        if ($this->hasLogger()) {
            $this->getLogger()->$type(strip_tags($msg), $data);
        }

        return;
    }

    /**
     * output a debug log message
     * @param $msg
     * @param array $data
     */
    public function debug($msg, $data = [])
    {
        $this->log($msg, $data);
    }
}
