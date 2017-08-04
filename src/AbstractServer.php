<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 12:41
 */

namespace inhere\server;

use inhere\console\utils\Show;

use inhere\library\traits\ConfigTrait;
use inhere\library\utils\LiteLogger;

use inhere\server\helpers\ServerHelper;
use inhere\server\traits\ProcessManageTrait;
use inhere\server\traits\ServerCreateTrait;
use inhere\server\traits\SomeSwooleEventTrait;

use Swoole\Process;
use Swoole\Server;

/**
 * Class AServerManager
 * @package inhere\server
 *
 * Running processes:
 *
 * ```
 * ```
 */
abstract class AbstractServer implements InterfaceServer
{
    use ConfigTrait;
    use ProcessManageTrait;
    use ServerCreateTrait;
    use SomeSwooleEventTrait;

    /**
     * @var int
     */
    protected $masterPid;

    /**
     * @var int
     */
    protected $managerPid;

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

        'options' => []
    ];

    /**
     * server manager
     * @var static
     */
    public static $mgr;

    /**
     * @var array
     */
    protected static $_statistics = [];

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
     * @var Server
     */
    public $server;

    /**
     * @var Process
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
     */
    public function __construct(array $config = [])
    {
        ServerHelper::checkRuntimeEnv();
        self::$mgr = $this;

        $this->setConfig($config);

        $this->init();
        $this->bootstrap();
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

        // register attach server from config
        if ($attachServers = $this->config['attach_servers']) {
            foreach ((array)$attachServers as $name => $conf) {
                $this->attachPortListener($name, $conf);
            }
        }

        // register main server event method
        if ($methods = $this->getValue('main_server.extend_events')) {
            $this->setSwooleEvents($methods);
        }

        return $this;
    }

//////////////////////////////////////////////////////////////////////
/// runtime logic
//////////////////////////////////////////////////////////////////////

    /**
     * bootstrap
     */
    public function bootstrap()
    {
        $this->bootstrapped = false;

        // prepare start server
        $this->startBaseService();

        // display some messages
        $this->showInformation();

        // do something for before create main server
        $this->beforeCreateServer();

        // create swoole server instance
        $this->createMainServer();

        // do something for after create main server
        $this->afterCreateServer();

        $this->bootstrapped = true;
    }

    protected function startBaseService()
    {
        // create log service instance
        if ($logService = $this->getValue('log_service')) {
            LiteLogger::make($logService);
        }
    }

    /**
     * Show server info
     */
    protected function showInformation()
    {
        $swOpts = $this->config['swoole'];
        $main = $this->config['main_server'];
        $panelData = [
            'PHP Version' => PHP_VERSION,
            'Operate System' => PHP_OS,
            'Swoole Info' => [
                'version' => SWOOLE_VERSION,
                'coroutine' => class_exists('\Swoole\Coroutine', false),
            ],
            'Swoole Config' => [
                'dispatch_mode' => $swOpts['dispatch_mode'],
                'worker_num' => $swOpts['worker_num'],
                'task_worker_num' => $swOpts['task_worker_num'],
                'max_request' => $swOpts['max_request'],
            ],
            'Main Server' => [
                'type' => $main['type'],
                'mode' => $main['mode'],
                'host' => $main['host'],
                'port' => $main['port'],
                'class' => static::class,
                'extClass' => $main['extend_server'] ?? 'NO setting',
            ],
            'Project Config' => [
                'name' => $this->name,
                'path' => $this->config['root_path'],
                'auto_reload' => $this->config['auto_reload'],
                'pid_file' => $this->config['pid_file'],
            ],
            'Server Log' => $this->config['log_service'],
        ];


        // 'Server Information'
        Show::mList($panelData);
        // Show::panel($panelData, 'Server Information');

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

    /**
     * @return int
     */
    public function getMasterPid(): int
    {
        return $this->masterPid;
    }

    /**
     * @return int
     */
    public function getManagerPid(): int
    {
        return $this->managerPid;
    }

    /**
     * @return array
     */
    public static function getStatistics(): array
    {
        return self::$_statistics;
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

            Show::write(sprintf('[%s.%s] [%s] %s %s', $time, $ms, strtoupper($type), $msg, $json));
        }

        if ($this->hasLogger()) {
            $this->getLogger()->log($type, strip_tags($msg), $data);
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
