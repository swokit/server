<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/1/25 0025
 * Time: 00:09
 */

namespace Swokit\Server;

use Inhere\Console\Utils\Show;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as Logger;
use Swokit\Server\Event\ServerEvent;
use Swokit\Server\Event\SwooleEvent;
use Swokit\Server\Traits\HandleSwooleEventTrait;
use Swokit\Server\Traits\ServerCreateTrait;
use Swokit\Server\Traits\ServerEventManageTrait;
use Swokit\Server\Traits\ServerManageTrait;
use Swokit\Util\ServerUtil;
use Toolkit\PhpUtil\PhpException;

/**
 * Class BaseServer
 * @package Swokit\Server
 */
abstract class BaseServer implements ServerInterface
{
    use ServerEventManageTrait, HandleSwooleEventTrait, ServerCreateTrait, ServerManageTrait;

    /** @var static */
    private static $instance;

    /** @var array */
    private static $_stats = [];

    /** @var string Current server name */
    protected $name = 'server';

    /**
     * @var \Swoole\Server|\Swoole\Http\Server|\Swoole\WebSocket\Server
     */
    protected $server;

    /**
     * @var LoggerInterface|mixed
     */
    private $logger;

    /**
     * @var string
     */
    protected $pidFile;

    /** @var bool */
    private $bootstrapped = false;

    /**
     * @var array
     */
    protected static $required = [
        'name', 'rootPath'
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
        // eg '/var/run/swoole_server.pid'
        'pidFile' => '',
        // user options
        'options' => [],
    ];

    /**
     * @var array The main server settings
     */
    protected $serverSettings = [
        'type' => 'tcp', // http https tcp udp ws wss
        // 运行模式
        // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
        'mode' => 'process',

        'host' => '127.0.0.1',
        'port' => '8662',

        // append register swoole events
        'events' => [], // e.g [ 'request', ]
    ];

    /**
     * @var array The attached port server config.
     */
    protected $portsSettings = [
        // 'tcp1' => [
        //     'host' => '0.0.0.0',
        //     'port' => '9661',
        //     'type' => 'tcp',

        //      setting event handler
        //     'event_handler' => '', // e.g '\Swokit\Server\listeners\TcpListenHandler'
        //     'events'   => [], // e.g [ 'onReceive', ]
        // ],

        // 'udp1' => [
        //     'host' => '0.0.0.0',
        //     'port' => '9660',
        // ]
    ];

    /**
     * @var array The settings for swoole. ($server->set($this->settings))
     */
    protected $swooleSettings = [
        // 'user'    => '',
        'worker_num' => 1,
        // 启用 task worker, 必须为Server设置onTask和onFinish回调
        'task_worker_num' => 1,
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
    ];

    /**
     * @param array $config
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create(array $config = [])
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
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config)
    {
        self::$instance = $this;

        $this->parseConfig($config);

        $this->init();
    }

    /**
     * @param array $config
     * @throws \InvalidArgumentException
     */
    protected function parseConfig(array $config)
    {
        // main server
        if (!empty($config['server'])) {
            $this->serverSettings = \array_merge($this->serverSettings, $config['server']);
            unset($config['server']);
        }

        // listen servers
        if (!empty($config['ports'])) {
            $this->portsSettings = \array_merge($this->portsSettings, $config['ports']);
            unset($config['ports']);
        }

        // for swoole
        if (!empty($config['swoole'])) {
            $this->swooleSettings = \array_merge($this->swooleSettings, $config['swoole']);
            unset($config['swoole']);
        }

        if ($config) {
            $this->config = \array_merge($this->config, $config);
        }

        // collect attach port server from config
        if ($attachPorts = $this->portsSettings) {
            foreach ($attachPorts as $name => $conf) {
                $this->attachListener($name, $conf);
            }
        }

        // register main server event method
        if ($methods = $this->serverSettings['events']) {
            $this->setSwooleEvents($methods);
        }
    }

    protected function init()
    {
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function validateConfig()
    {
        foreach (static::$required as $name) {
            if (empty($this->config[$name])) {
                throw new \InvalidArgumentException("The '$name' is must setting in config.");
            }
        }
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function asDaemon($value = null): self
    {
        if (null !== $value) {
            $this->swooleSettings['daemonize'] = (bool)$value;
        }

        return $this;
    }

    /**************************************************************************
     * swoole server start
     *************************************************************************/

    protected function prepareStart()
    {
        $this->name = (string)$this->config['name'];

        if (!$this->config['pidFile']) {
            $this->config['pidFile'] = '/var/run/swoole_' . $this->name . '.pid';
        }

        $this->pidFile = $this->config['pidFile'];
    }

    /**
     * Do start server
     * @param null|bool $daemon
     */
    public function start($daemon = null)
    {
        ServerUtil::checkRuntimeEnv();

        $this->validateConfig();
        $this->prepareStart();

        if ($pid = $this->getPidFromFile(true)) {
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

            $this->fire(ServerEvent::SWOOLE_START, $this);
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
        $this->fire(ServerEvent::BEFORE_BOOTSTRAP, $this);
        $this->beforeBootstrap();

        // do something for before create main server
        $this->fire(ServerEvent::SERVER_CREATE, $this);
        $this->beforeCreateServer();

        // create swoole server instance
        $this->createServer();

        // do something for after create main server(eg add custom process)
        $this->fire(ServerEvent::SERVER_CREATED, $this);
        $this->afterCreateServer();

        // attach Extend Server
        // $this->attachExtendServer();

        // register swoole events handler
        $this->registerServerEvents(SwooleEvent::BASIC_EVENTS);
        $this->registerServerEvents(self::$swooleEvents);

        // attach user's custom process
        $this->attachUserProcesses();

        // attach registered listen port server to main server
        $this->createListenServers($this->server);

        // prepared for start server
        $this->fire(ServerEvent::BOOTSTRAPPED, $this);
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
     * @param \Throwable $e (\Exception \Error)
     * @param string $catcher
     */
    public function handleException($e, $catcher)
    {
        $content = PhpException::toString($e, $this->isDebug(), $catcher);

        $this->log($content, [], Logger::ERROR);
    }

    /**
     * @param \Throwable $e (\Exception \Error)
     * @param string $catcher
     */
    public function handleWorkerException($e, $catcher)
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
        return \array_merge($context, [
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
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

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
     * @param string $format
     * @param mixed ...$args
     */
    public function logf(string $format, ...$args)
    {
        $this->log(\sprintf($format, ...$args));
    }

    /**
     * @param string $msg
     * @param array $context
     * @param int|string $type
     */
    public function log(string $msg, array $context = [], $type = 'info')
    {
        if (!$this->isDebug()) {
            return;
        }

        $context = $this->collectRuntimeContext($context);

        // if on Daemon, don't output log.
        if (!$this->isDaemon()) {
            list($ts, $ms) = explode('.', sprintf('%.4f', microtime(true)));
            $ms = \str_pad($ms, 4, 0);
            $time = \date('Y-m-d H:i:s', $ts);
            $json = $context ? ' ' . \json_encode($context, \JSON_UNESCAPED_SLASHES) : '';
            // $type = Logger::getLevelName($type);

            Show::write(\sprintf('[%s.%s] [%s.%s] %s %s', $time, $ms, $this->name, \strtoupper($type), $msg, $json));
        }

        if ($this->logger) {
            $this->logger->$type(\strip_tags($msg), $context);
        }
    }

    /**
     * @return array
     */
    public function getServerSettings(): array
    {
        return $this->serverSettings;
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
    public function getSwooleSettings(): array
    {
        return $this->swooleSettings;
    }

    /**
     * @return \Swoole\Server|\Swoole\Http\Server|\Swoole\WebSocket\Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param array $serverSettings
     */
    public function setServerSettings(array $serverSettings): void
    {
        $this->serverSettings = \array_merge($this->serverSettings, $serverSettings);
    }

    /**
     * @param array $swooleSettings
     */
    public function setSwooleSettings(array $swooleSettings): void
    {
        $this->swooleSettings = \array_merge($this->swooleSettings, $swooleSettings);
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
        if (!$this->swooleSettings['ssl_cert_file'] || !$this->swooleSettings['ssl_key_file']) {
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

    /*******************************************************************************
     * some help method(from swoole)
     ******************************************************************************/

    /**
     * 获取对端socket的IP地址和端口
     * @param int $cid
     * @return array
     */
    public function getPeerName(int $cid): array
    {
        $data = $this->getClientInfo($cid);
        return [
            'ip' => $data['remote_ip'] ?? '',
            'port' => $data['remote_port'] ?? 0,
        ];
    }

    /**
     * @param int $cid
     * @return array
     * [
     *  // 大于0 是webSocket(=2) 等于0 是 http/...
     *  websocket_status => int [可选项] WebSocket连接状态，当服务器是Swoole\WebSocket\Server时会额外增加此项信息
     *  from_id => int
     *  server_fd => int 来自哪个server socket
     *  server_port => int 来自哪个Server端口
     *  remote_port => int 客户端连接的端口
     *  remote_ip => string 客户端连接的ip
     *  connect_time => int 连接到Server的时间，单位秒
     *  last_time => int  最后一次发送数据的时间，单位秒
     *  close_errno => int 连接关闭的错误码，如果连接异常关闭，close_errno的值是非零
     * ]
     */
    public function getClientInfo(int $cid): array
    {
        // @link https://wiki.swoole.com/wiki/page/p-connection_info.html
        return $this->server->getClientInfo($cid);
    }

    /**
     * @return int
     */
    public function getErrorNo(): int
    {
        return $this->server->getLastError();
    }

    /**
     * @return string
     */
    public function getErrorMsg(): string
    {
        $err = \error_get_last();
        return $err['message'] ?? '';
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->server->getSocket();
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
     * @param bool $checkRunning
     * @return int
     */
    public function getPidFromFile(bool $checkRunning = false): int
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
        return (bool)$this->swooleSettings['daemonize'];
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
     * @return mixed|LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param mixed|LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
