<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/1/25 0025
 * Time: 00:09
 */

namespace Inhere\Server;

use Inhere\Console\Utils\Show;
use Inhere\Server\Event\ServerEvent;
use Inhere\Server\Event\SwooleEvent;
use Inhere\Server\Traits\ServerEventManageTrait;
use Toolkit\PhpUtil\PhpException;
use SwooleKit\Util\ServerUtil;
use Inhere\Server\Traits\BasicSwooleEventTrait;
use Inhere\Server\Traits\ServerCreateTrait;
use Inhere\Server\Traits\ServerManageTrait;
use Psr\Log\LogLevel as Logger;
use Psr\Log\NullLogger;

/**
 * Class AbstractServer
 * @package Inhere\Server
 */
abstract class AbstractServer implements ServerInterface
{
    use ServerEventManageTrait, BasicSwooleEventTrait, ServerCreateTrait, ServerManageTrait;

    /** @var static */
    private static $instance;

    /** @var array */
    private static $_stats = [];

    /**
     * @var array
     */
    protected $swooleEvents = [
        // 'event'  => 'callback method',
        'start' => 'onMasterStart',
        'shutdown' => 'onMasterStop',

        'managerStart' => 'onManagerStart',
        'managerStop' => 'onManagerStop',

        'workerStart' => 'onWorkerStart',
        'workerStop' => 'onWorkerStop',
        'workerExit' => 'onWorkerExit',
        'workerError' => 'onWorkerError',

        'pipeMessage' => 'onPipeMessage',

        // Task 任务相关 (若配置了 task_worker_num 则必须注册这两个事件)
        'task' => 'onTask',
        'finish' => 'onFinish',
    ];

    /** @var string Current server name */
    protected $name = 'server';

    /**
     * @var \Swoole\Server
     */
    protected $server;

    /**
     * @var \Psr\Log\LoggerInterface|mixed
     */
    private $logger;

    /** @var mixed */
    private $errorHandler;

    /**
     * @var string
     */
    protected $pidFile;

    /**
     * @var bool
     */
    private $daemon;

    /** @var bool */
    private $bootstrapped = false;

    /**
     * @var array
     */
    protected $required = [
        'name', 'pidFile', 'rootPath'
    ];

    /**
     * config data
     * @var array
     */
    protected $config = [
        // basic config
        'name' => 'server',
        'debug' => false,
        'rootPath' => '',
        'pidFile' => '/tmp/swoole_server.pid',

        // error handle
        'error' => [
            // the error handler class
            'class' => '',
            'exitOnHandled' => true,
        ],

        // 当前server的日志配置(不是swoole的日志)
        'logger' => [
            'name' => 'server_log',
            'class' => '', // logger class
            'file' => './tmp/logs/test_server.log',
            'level' => Logger::DEBUG,
            'bufferSize' => 0, // 1000,
        ],

        // user options
        'options' => [],

        // for main server
        'server' => [
            'host' => '0.0.0.0',
            'port' => '8662',

            // 运行模式
            // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
            'mode' => 'process',
            'type' => 'tcp', // http https tcp udp ws wss

            // append register swoole events
            'events' => [], // e.g [ 'onRequest', ]
        ],

        // for listen servers
        'ports' => [
            // 'tcp1' => [
            //     'host' => '0.0.0.0',
            //     'port' => '9661',
            //     'type' => 'tcp',

            //      setting event handler
            //     'event_handler' => '', // e.g '\Inhere\Server\listeners\TcpListenHandler'
            //     'events'   => [], // e.g [ 'onReceive', ]
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
            // 启用 task worker, 必须为Server设置onTask和onFinish回调
            'task_worker_num' => 2,

            // 'reload_async' => true,
            'daemonize' => 0,

            // 'max_request' => 1000,

            // 在1.7.15以上版本中，当设置dispatch_mode = 1/3时会自动去掉onConnect/onClose事件回调。
            // see @link https://wiki.swoole.com/wiki/page/49.html
            // allow: 1 2 3 7
            // 'dispatch_mode' => 2,
            // 'log_file' , // '/tmp/swoole.log', // 不设置log_file会打印到屏幕
            'log_level' => 2,

            // 使用SSL必须在编译swoole时加入--enable-openssl选项 并且配置下面两项
            // 'ssl_cert_file' => __DIR__.'/config/ssl.crt',
            // 'ssl_key_file' => __DIR__.'/config/ssl.key',
        ]
    ];

    /**
     * @var array
     */
    protected $portsSettings = [];

    /**
     * @var array The settings for swoole. ($server->set($this->settings))
     */
    protected $settings = [];

    /**
     * @param array $config
     * @return static
     */
    public static function make(array $config = [])
    {
        return new static($config);
    }

    /**
     * @return static
     */
    public static function instance()
    {
        return self::$instance;
    }

    /**
     * AbstractServer constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        self::$instance = $this;

        $this->init($config);
    }

    /**
     * @param array $config
     */
    protected function init(array $config)
    {
        // main server
        if (!empty($config['server'])) {
            $this->config['server'] = array_merge($this->config['server'], $config['server']);
            unset($config['server']);
        }

        // listen servers
        if (!empty($config['ports'])) {
            $this->config['ports'] = array_merge($this->config['ports'], $config['ports']);
            unset($config['ports']);
        }

        // for swoole
        if (!empty($config['swoole'])) {
            $this->config['swoole'] = array_merge($this->config['swoole'], $config['swoole']);
            unset($config['swoole']);
        }

        if ($config) {
            $this->config = array_merge($this->config, $config);
        }

        $this->validateConfig();

        $this->name = (string)$this->config['name'];
        $this->pidFile = (string)$this->config['pidFile'];
        $this->daemon = (bool)$this->config['swoole']['daemonize'];

        // create logger
        $this->logger = $this->makeLogger($this->config['logger']);

        // register error handler
        $this->registerErrorHandler($this->config['error']);

        // register attach server from config
        if ($attachServers = $this->config['ports']) {
            foreach ((array)$attachServers as $name => $conf) {
                $this->attachPortListener($name, $conf);
            }
        }

        // register main server event method
        if ($methods = $this->config['server']['events']) {
            $this->setSwooleEvents($methods);
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function validateConfig()
    {
        foreach ($this->required as $name) {
            if (empty($this->config[$name])) {
                throw new \InvalidArgumentException("The '$name' is must setting in config.");
            }
        }
    }

    /**
     * @param array $options
     * @return \Psr\Log\LoggerInterface|mixed|null
     */
    protected function makeLogger(array $options)
    {
        return new NullLogger();
    }

    /**
     * @param array $options
     */
    protected function registerErrorHandler(array $options)
    {
        // $errClass = Arr::remove($conf, 'class');
        // $this->errorHandler = new $errClass($this->logger, $conf);
        // $this->errorHandler->register();
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function asDaemon($value = null): self
    {
        if (null !== $value) {
            $this->daemon = (bool)$value;
            $this->config['swoole']['daemonize'] = (bool)$value;
        }

        return $this;
    }

    /**************************************************************************
     * swoole server start
     *************************************************************************/

    /**
     * Do start server
     * @param null|bool $daemon
     * @throws \Throwable
     */
    public function start($daemon = null)
    {
        ServerUtil::checkRuntimeEnv();

        if ($pid = ServerUtil::getPidFromFile($this->pidFile, true)) {
            Show::error("The swoole server({$this->name}) have been started. (PID:{$pid})", -1);
        }

        if (null !== $daemon) {
            $this->asDaemon($daemon);
        }

        try {
            $this->bootstrap();

            self::addStat('start_time', microtime(1));

            // display some messages
            $this->showStartStatus();

            // $this->fire(self::ON_SERVER_START, [$this]);
            $this->beforeServerStart();

            // start server
            $this->server->start();
        } catch (\Throwable $e) {
            $this->handleException($e, __METHOD__);
        }
    }

    protected function beforeServerStart()
    {
    }

    protected function beforeBootstrap()
    {
    }

    /**
     * before create Server
     */
    public function beforeCreateServer()
    {
    }

    /**
     * bootstrap start
     * @throws \RuntimeException
     */
    protected function bootstrap()
    {
        $this->bootstrapped = false;

        // prepare start server
        $this->fire(ServerEvent::BEFORE_BOOTSTRAP, [$this]);
        $this->beforeBootstrap();

        // do something for before create main server
        $this->fire(ServerEvent::BEFORE_SERVER_CREATE, [$this]);
        $this->beforeCreateServer();

        // create swoole server instance
        $this->createServer();

        // do something for after create main server(eg add custom process)
        $this->fire(ServerEvent::SERVER_CREATED, [$this]);
        $this->afterCreateServer();

        // attach Extend Server
        // $this->attachExtendServer();

        // register swoole events handler
        $this->registerServerEvents();

        // attach user's custom process
        $this->attachUserProcesses();

        // attach registered listen port server to main server
        $this->createListenServers($this->server);

        // prepared for start server
        $this->fire(ServerEvent::BOOTSTRAPPED, [$this]);
        $this->afterBootstrap();

        $this->bootstrapped = true;
    }

    /**
     * afterCreateServer
     * @throws \RuntimeException
     */
    protected function afterCreateServer()
    {
    }

    protected function afterBootstrap()
    {
        // do something ...
    }

    protected function showStartStatus()
    {
        // output a message before start
        if ($this->isDaemon()) {
            Show::write("You can use <info>stop</info> command to stop server.\n");
        } else {
            Show::write("Press <info>Ctrl-C</info> to quit.\n");
        }
    }

    /**
     * @param \Throwable|\Exception $e (\Exception \Error)
     * @param string $catcher
     */
    public function handleException($e, $catcher)
    {
        $content = PhpException::toString($e, $this->isDebug(), $catcher);

        $this->log($content, [], Logger::ERROR);
    }

    /**
     * @param array $context
     * @return array
     */
    protected function collectRuntimeContext(array $context): array
    {
        return array_merge($context, [
            'workerId' => $this->getWorkerId(),
            'workerPid' => $this->getWorkerPid(),
            'isTaskWorker' => $this->isTaskWorker(),
            'isUserWorker' => $this->isUserWorker(),
        ]);
    }

    /**************************************************************************
     * helper methods
     *************************************************************************/

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function config(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @param string $msg
     * @param array $context
     * @param int|string $type
     */
    public function log(string $msg, array $context = [], $type = 'info')
    {
        $context = $this->collectRuntimeContext($context);

        // if on Daemon, don't output log.
        if (!$this->isDaemon()) {
            list($ts, $ms) = explode('.', sprintf('%.4f', microtime(true)));
            $ms = str_pad($ms, 4, 0);
            $time = date('Y-m-d H:i:s', $ts);
            $json = $context ? ' ' . \json_encode($context, JSON_UNESCAPED_SLASHES) : '';
            // $type = Logger::getLevelName($type);

            Show::write(sprintf('[%s.%s] [%s.%s] %s %s', $time, $ms, $this->name, strtoupper($type), $msg, $json));
        }

        if ($this->logger) {
            $this->logger->$type(strip_tags($msg), $context);
        }
    }

    /**
     * @return array
     */
    public function getPortsSettings(): array
    {
        return $this->portsSettings;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * checkEnvWhenEnableSSL
     */
    protected function checkEnvWhenEnableSSL()
    {
        if (!\defined('SWOOLE_SSL')) {
            Show::error(
                "If you want use SSL(https), must add option '--enable-openssl' on the compile swoole.",
                1
            );
        }

        // check ssl config
        if (!$this->config['swoole']['ssl_cert_file'] || !$this->config['swoole']['ssl_key_file']) {
            Show::error(
                "If you want use SSL(https), must config the 'swoole.ssl_cert_file' and 'swoole.ssl_key_file'",
                1
            );
        }
    }

    /**
     * @return array
     */
    public function getSupportedProtocols(): array
    {
        return [
            self::PROTOCOL_HTTP,
            self::PROTOCOL_HTTPS,
            self::PROTOCOL_TCP,
            self::PROTOCOL_UDP,
            self::PROTOCOL_WS,
            self::PROTOCOL_WSS,
            self::PROTOCOL_RDS,
        ];
    }

    /**
     * @return array
     */
    public function allSwooleEvents(): array
    {
        return SwooleEvent::getAllEvents();
    }

    /**
     * @param string $event
     * @return bool
     */
    public function isSwooleEvent(string $event): bool
    {
        return isset(SwooleEvent::DEFAULT_HANDLERS[$event]);
    }

    /**
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    /**
     * @return mixed
     */
    public function getErrorHandler()
    {
        return $this->errorHandler;
    }

    /**
     * @param mixed $errorHandler
     */
    public function setErrorHandler($errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }

    /**
     * @param bool $checkRunning
     * @return int
     */
    public function getPidFromFile($checkRunning = false): int
    {
        return ServerUtil::getPidFromFile($this->pidFile, $checkRunning);
    }

    /**
     * @param string $name
     * @param $value
     */
    public static function addStat(string $name, $value)
    {
        self::$_stats[$name] = $value;
    }

    /**
     * @return array
     */
    public static function getStats(): array
    {
        return self::$_stats;
    }

    /**
     * @return bool
     */
    public function isDaemon(): bool
    {
        return $this->daemon;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return (bool)$this->config['debug'];
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return AbstractServer
     */
    public function setSwoole(array $config): AbstractServer
    {
        $this->config['swoole'] = \array_merge($this->config['swoole'], $config);

        return $this;
    }

    /**
     * @param array $swooleEvents
     * @return AbstractServer
     */
    public function setSwooleEvents(array $swooleEvents): AbstractServer
    {
        $this->swooleEvents = \array_merge($this->swooleEvents, $swooleEvents);

        return $this;
    }

    /**
     * @return array
     */
    public function getSwooleEvents(): array
    {
        return $this->swooleEvents;
    }

    /**
     * @return mixed|\Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param mixed|\Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
}
