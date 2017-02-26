<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 12:41
 */

namespace inhere\server;

use inhere\server\interfaces\IServerManager;
use Swoole\Http\Server as SwHttpServer;
use Swoole\Websocket\Server as SwWSServer;
use Swoole\Process as SwProcess;
use Swoole\Server as SwServer;
use Swoole\Server\Port as SwServerPort;

use inhere\librarys\console\Input;
use inhere\librarys\console\Output;

use inhere\librarys\collections\Config;
use inhere\librarys\utils\SFLogger;

/**
 * Class AServerManager
 * @package inhere\server
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
    private static $started = false;
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
    protected $swooleEvents = [
        // 不设置为默认的回调名称
        // 'event'  => 'handler',
        'Start'     => 'onMasterStart',
        'Shutdown'  => 'onMasterStop',

        // 设置为默认的回调名称，`on + eventName`
        'onManagerStart',
        'onManagerStop',

        'onWorkerStart',
        'onWorkerStop',
        'onWorkerError',

        'onPipeMessage',

        // Task 任务相关 (若配置了 task_worker_num 则必须注册这两个事件)
        'onTask',   // 处理异步任务
        'onFinish', // 处理异步任务的结果
    ];

    /**
     * @var array
     */
    protected $swooleProtocolEvents = [
        // TCP server callback
        'tcp' => [ 'onConnect', 'onReceive', 'onClose' ],

        // UDP server callback
        'udp' => [ 'onPacket', 'onClose' ],

        // HTTP server callback
        'http' => [ 'onRequest'],

        // Web Socket server callback
        'ws' => [ 'onMessage', 'onOpen', 'onHandShake', 'onClose' ],
    ];

    /**
     * BaseServer constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        ServerHelper::checkRuntimeEnv();
        self::$mgr = $this;

        $this->cliIn = new Input;
        $this->cliOut = new Output;

        $this->config = new Config($this->getDefaultConfig());

        if ($config) {
            $this->config->loadArray($config);
        }
    }

    /**
     * run
     * @param  array $config
     * @param bool $start
     * @return static
     */
    public static function run($config = [], $start = true)
    {
        if ( !self::$mgr ) {
            new static($config);
        } else {
            self::$mgr->setConfig($config);
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
            'auto_reload' => true, // will create a process auto reload server
            'pid_file'  => '/tmp/swoole_server.pid',

            // 当前server的日志配置(不是swoole的日志)
            'log_service' => [
                // 'name' => 'swoole_server_log'
                // 'basePath' => PROJECT_PATH . '/temp/logs/test_server',
                // 'logThreshold' => 0,
            ],

            // the swoole runtime setting
            'swoole' => [
                // 'user'    => '',
                'worker_num'    => 4,
                'task_worker_num' => 2, // 启用 task worker,必须为Server设置onTask和onFinish回调
                'daemonize'     => 0,
                'max_request'   => 1000,
                // 在1.7.15以上版本中，当设置dispatch_mode = 1/3时会自动去掉onConnect/onClose事件回调。
                // see @link https://wiki.swoole.com/wiki/page/49.html
                'dispatch_mode' => 1,
                // 'log_file' , // '/tmp/swoole.log', // 不设置log_file会打印到屏幕

                // 使用SSL必须在编译swoole时加入--enable-openssl选项 并且配置下面两项
                // 'ssl_cert_file' => __DIR__.'/config/ssl.crt',
                // 'ssl_key_file' => __DIR__.'/config/ssl.key',
            ],
        ];
    }

    /**
     * @return array
     */
    public function getSwooleEventHandlers()
    {
        return [];
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
        $this->init()
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
        if (self::$started) {
            throw new \RuntimeException('Server have been started.');
        }

        if (!$this->bootstrapped) {
            throw new \RuntimeException('Call start before must run bootstrap().');
        }

        self::$_statistics['start_time'] = microtime(1);

        // create swoole server instance
        if (
            !($this->server = $this->createMainServer()) ||
            !($this->server instanceof SwServer)
        ) {
            throw new \RuntimeException('The server instance must instanceof ' . SwServer::class );
        }

        // register swoole events handler
        $events = $this->swooleEvents;
        $this->addLog("Register swoole events to the main server:\n " . implode(',', array_values($events)), [], 'info');

        $this->registerMainServerEvents($events);

        // setting swoole config
        $this->server->set($this->config['swoole']);

        $this->beforeServerStart();

        // 对于Server的配置即 $server->set() 中传入的参数设置，必须关闭/重启整个Server才可以重新加载
        $this->server->start();

        return (self::$started = true);
    }

    protected function init()
    {
        if (!$this->pidFile = $this->config->get('pid_file')) {
            throw new \RuntimeException('The config option \'pid_file\' is must setting');
        }

        // project root path
        if (!$this->config->get('root_path')) {
            if (defined('PROJECT_PATH')) {
                $this->setConfig(['root_path' => PROJECT_PATH]);
            } else {
                throw new \RuntimeException('The project path \'root_path\' is must setting');
            }
        }

        if ( !($this->name = $this->config->get('name')) ) {
            $this->name = basename($this->config->get('root_path'));
            $this->setConfig(['name' => $this->name]);
        }

        // $currentUser = ServerHelper::getCurrentUser();

        // Get unix user of the worker process.
        // if ( !$this->user = $this->config->get('swoole.user') ) {
        //     $this->user = $currentUser;
        // } else if (posix_getuid() !== 0 && $this->user != $currentUser) {
        //     $this->cliOut->block('You must have the root privileges to change uid and gid.', 'WARNING', 'warning');
        // }

        // Get server is debug mode
        $this->debug = (bool)$this->config->get('debug', false);

        return $this;
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

        $masterPid = ServerHelper::getPidByPidFile($this->pidFile);
        $masterIsStarted = ($masterPid > 0) && @posix_kill($masterPid, 0);

        // start: do Start Server
        if ( $command === 'start' ) {

            // check master process is running
            if ( $masterIsStarted ) {
                $this->cliOut->error("The swoole server({$this->name}) have been started. (PID:{$masterPid})", true);
            }

            // run is daemonize
            $this->daemonize = (bool)$this->cliIn->getBool('d', $this->config->get('swoole.daemonize', false));
            $this->setConfig(['swoole' => [ 'daemonize' => $this->daemonize ]]);

            // if isn't daemonize mode, don't save swoole log to file
            if ( !$this->daemonize ) {
                $this->setConfig(['swoole' => [ 'log_file' => null ]]);
            }

            return $this;
        }

        // check master process
        if ( !$masterIsStarted ) {
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
                $this->doReloadWorkers($masterPid, $this->cliIn->getBool('task'));
                break;

            case 'info':
                $this->showInformation();
                exit(0);
                break;

            case 'stat':
                $this->cliOut->notice("Sorry, The function un-completed!", 0);
                // $stats = $this->server->stats();
                // $this->cliOut->panel($stats, 'Server Stat Information');
                // exit(0);
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
        $supportCommands = ['start', 'reload', 'restart', 'stop', 'info', 'stat'];

        // show help info
        if (
            // no input
            !$command ||
            // command equal to 'help'
            $command === 'help' ||
            // is an not supported command
            !in_array($command, $supportCommands) ||
            // has option -h|--help
            $this->cliIn->getBool('h', false) ||
            $this->cliIn->getBool('help', false)
        ) {
            $this->showHelpPanel();
        }
    }

    protected function startBaseService()
    {
        // create log service instance
        if ( $logService = $this->config->get('log_service') ) {
            SFLogger::make($logService);
        }

        return $this;
    }

    /**
     * Show server info
     * @return static
     */
    protected function showInformation()
    {
        // output a message before start
        if ($this->daemonize) {
            $scriptName = $this->cliIn->getScriptName(); // 'bin/test_server.php'

            if ( strpos($scriptName, '.') && 'php' === pathinfo($scriptName,PATHINFO_EXTENSION) ) {
                $scriptName = 'php ' . $scriptName;
            }

            $this->cliOut->write("Input <info>$scriptName stop</info> to quit.\n");
        } else {
            $this->cliOut->write("Press <info>Ctrl-C</info> to quit.\n");
        }
    }

    /**
     * @return SwServer
     */
    abstract protected function createMainServer();

    public function beforeServerStart(\Closure $callback = null)
    {
        if ( $callback ) {
            $callback($this);
        }
    }

    /**
     * register Swoole Events
     * @param  array  $events
     */
    protected function registerMainServerEvents(array $events)
    {
        foreach ($events as $event => $callback ) {
            // ['onConnect'] --> 'Connect' , 'onConnect
            if ( is_int($event) ) {
                $event = substr($callback,2);
            }

            // e.g $server->on('Request', [$this, 'onRequest']);
            if ( method_exists($this, $callback) ) {
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
        if ( $pidFile = $this->pidFile ) {
            file_put_contents($this->pidFile, $masterPid);
        }

        ServerHelper::setProcessTitle("swoole: master ({$this->name} IN $projectPath)");

        $this->addLog("The master process success started. (PID:<notice>{$masterPid}</notice>, pid_file: $pidFile)");
    }

    /**
     * on Master Stop
     * @param  SwServer $server
     */
    public function onMasterStop(SwServer $server)
    {
        $this->addLog("The swoole master process stopped.");

        $this->doClear();
    }

    /**
     * onConnect
     * @param  SwServer $server
     * @param  int      $fd     客户端的唯一标识符. 一个自增数字，范围是 1 ～ 1600万
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
        ServerHelper::setProcessTitle("swoole: manager ({$this->name})");

        $this->addLog("The manager process success started. (PID:{$server->manager_pid})");
    }

    /**
     * on Manager Stop
     * @param  SwServer $server
     */
    public function onManagerStop(SwServer $server)
    {
        $this->addLog("The swoole manager process stopped.");
    }

    /**
     * on Worker Start
     *   应当在onWorkerStart中创建连接对象
     * @link https://wiki.swoole.com/wiki/page/325.html
     * @param  SwServer $server
     * @param  int           $workerId The worker index id in the all workers.
     */
    public function onWorkerStart(SwServer $server, $workerId)
    {
        $taskMark = $server->taskworker ? 'task-worker' : 'event-worker';

        $this->addLog("The <primary>{$workerId}</primary> {$taskMark} process success started. (PID:{$server->worker_pid})");

        ServerHelper::setProcessTitle("swoole: {$taskMark} ({$this->name})");

        // ServerHelper::setUserAndGroup();

        // 此数组中的文件表示进程启动前就加载了，所以无法reload
        // $this->cliOut->write('进程启动前就加载了，无法reload的文件：');
        // $this->cliOut->write(get_included_files());
    }

    public function onWorkerStop(SwServer $server)
    {
        $this->addLog("The swoole worker process stopped.");
    }

    /**
     * onPipeMessage
     *  能接收到 `$server->sendMessage` 发送的消息
     * @param  SwServer $server
     * @param  int           $srcWorkerId
     * @param  mixed        $data
     */
    public function onPipeMessage(SwServer $server, $srcWorkerId, $data)
    {
        $this->addLog("#{$server->worker_id} message from #$srcWorkerId: $data");
    }

    ////////////////////// Task Event //////////////////////

    /**
     * 处理异步任务( onTask )
     * @param  SwServer $server
     * @param  int           $taskId
     * @param  int           $fromId
     * @param  mixed         $data
     */
    public function onTask(SwServer $server, $taskId, $fromId, $data)
    {
        // $this->addLog("Handle New AsyncTask[id:$taskId]");
        // 返回任务执行的结果(finish操作是可选的，也可以不返回任何结果)
        // $server->finish("$data -> OK");
    }

    /**
     * 处理异步任务的结果
     * @param  SwServer $server
     * @param  int           $taskId
     * @param  mixed         $data
     */
    public function onFinish(SwServer $server, $taskId, $data)
    {
        $this->addLog("AsyncTask[$taskId] Finish. Data: $data");
    }

    protected function doClear()
    {
        if ( $this->pidFile && file_exists($this->pidFile) ) {
            unlink($this->pidFile);
        }

        self::$started = false;
        self::$_statistics['stop_time'] = microtime(1);
    }

//////////////////////////////////////////////////////////////////////
/// getter/setter method
//////////////////////////////////////////////////////////////////////

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
     * @param  null|string  $name
     * @return boolean
     */
    public function hasLogger($name = null)
    {
        $name = $name ? : $this->config->get('log_service.name');

        return $name && SFLogger::has($name);
    }

    /**
     * get Logger service
     * @return SFLogger
     */
    public function getLogger()
    {
        $name = $this->config->get('log_service.name');

        if ( $this->hasLogger($name) ) {
            return SFLogger::get($name);
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

//////////////////////////////////////////////////////////////////////
/// some help method(from swoole)
//////////////////////////////////////////////////////////////////////

    /**
     */
    protected function checkEnvWhenEnableSSL()
    {
        if ( !defined('SWOOLE_SSL')) {
            $this->cliOut->error('If you want use SSL(https), must add option --enable-openssl on the compile swoole.', 1);
        }

        // check ssl config
        if ( !$this->config->get('swoole.ssl_cert_file') || !$this->config->get('swoole.ssl_key_file')) {
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
        if ( $this->server ) {
            return $this->server->stop($workerId);
        }

        return false;
    }

    /**
     * do Reload Workers
     * @param  int      $masterPid
     * @param  boolean  $onlyTaskWorker
     */
    protected function doReloadWorkers($masterPid, $onlyTaskWorker = false)
    {
        // SIGUSR1: 向管理进程发送信号，将平稳地重启所有worker进程; 也可在PHP代码中调用`$server->reload()`完成此操作
        $sig = SIGUSR1;

        // SIGUSR2: only reload task worker
        if ( $onlyTaskWorker ) {
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
     * @param  int     $masterPid Master Pid
     * @param  boolean $quit      Quit, When stop success?
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
        while ( true ) {
            $masterIsStarted = ($masterPid > 0) && @posix_kill($masterPid, 0);

            if ( !$masterIsStarted ) {
                break;
            }

            // have been timeout
            if ( (time() - $startTime) >= $timeout ) {
                $this->cliOut->error("The swoole server({$this->name}) process stop fail!", -1);
            }

            usleep(10000);
            continue;
        }

        if ( $this->pidFile && file_exists($this->pidFile) ) {
            unlink($this->pidFile);
        }

        // stop success
        $this->cliOut->success("The swoole server({$this->name}) process stop success.", $quit);
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

        if ( strpos($scriptName, '.') && 'php' === pathinfo($scriptName,PATHINFO_EXTENSION) ) {
            $scriptName = 'php ' . $scriptName;
        }

        $this->cliOut->helpPanel(
        // Usage
            "$scriptName {start|reload|restart|stop|stat} [-d]",
            // Commands
            [
                'start'   => 'Start the server',
                'reload'  => 'Reload all workers of the started server',
                'restart' => 'Stop the server, After start the server.',
                'stop'    => 'Stop the server',
                'info'    => 'Show the server information for current project',
                'stat'    => 'Show the started server stat information',
                'help'    => 'Display this help message',
            ],
            // Options
            [
                '-d'         => 'Run the server on daemonize.',
                '--task'     => 'Only reload task worker, when reload server',
                '-h, --help' => 'Display this help message',
            ],
            // Examples
            [
                "<info>$scriptName start -d</info> Start server on daemonize mode.",
                "<info>$scriptName reload --task</info> Start server on daemonize mode."
            ],
            // Description
            'Swoole server manager tool, Version <comment>' . self::VERSION . '</comment>. Update time ' . self::UPDATE_TIME,
            $showHelpAfterQuit
        );
    }

    /**
     * output debug message
     * @param  string $msg
     * @param  array $data
     * @param string $type
     */
    public function addLog($msg, $data = [], $type = 'debug')
    {
        // if close debug, don't output debug log.
        if ( $this->debug || $type !== 'debug') {
            if ( !$this->daemonize ) {
                list($time, $micro) = explode('.', microtime(1));
                $time = date('Y-m-d H:i:s', $time);

                $data = $data ? json_encode($data) : '';
                $type = strtoupper($type);
                $this->cliOut->write("[{$time}.{$micro}] [$type] $msg {$data}");

            } else if ( $this->hasLogger() ) {
                $this->getLogger()->$type(strip_tags($msg), $data);
            }
        }
    }


}
