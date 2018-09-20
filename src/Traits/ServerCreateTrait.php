<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/8/1
 * Time: 下午7:02
 */

namespace SwoKit\Server\Traits;

use Inhere\Console\Utils\Show;
use SwoKit\Server\Component\HotReloading;
use SwoKit\Server\Event\ServerEvent;
use SwoKit\Server\Event\SwooleEvent;
use SwoKit\Server\Listener\Port\PortListenerInterface;
use SwoKit\Server\ServerInterface;
use Swoole\Http\Server as HttpServer;
use Swoole\Process;
use Swoole\Redis\Server as RedisServer;
use Swoole\Server;
use Swoole\Server\Port;
use Swoole\Websocket\Server as WebSocketServer;
use Toolkit\ArrUtil\Arr;
use Toolkit\Sys\ProcessUtil;

/**
 * Class ServerCreateTrait
 * @package SwoKit\Server\Traits
 * @property Server $server
 */
trait ServerCreateTrait
{
    /**
     * custom user process
     * @var Process[] [name => Process]
     */
    private $processes = [];

    /**
     * @var array [name => true]
     */
    private $processNames = [];

    /**
     * @var array [name => \Closure]
     */
    private $processCallbacks = [];

    /**
     * @var Port[]
     */
    private $ports = [];

    /**
     * @var array
     * [
     *      'port1' => true
     * ]
     */
    public $attachedNames = [];

    /**
     * attached listen port server callback(`Closure`)
     * [
     *   'name' => \Closure,
     *   'name1' => InterfacePortListener
     * ]
     * @var array
     */
    public $attachedListeners = [];

    /**
     * @var array
     */
    private static $swooleProtocolEvents = [
        // TCP server callback
        'tcp' => [
            'connect',
            'receive',
            'close'
        ],

        // UDP server callback
        'udp' => [
            'packet',
            'close'
        ],

        // HTTP server callback
        'http' => [
            'request'
        ],

        // Web Socket server callback
        'ws' => [
            'open',
            'close',
            'message',
            'handShake',
        ],

        // redis server callback
        'rds' => [
            'task',
            'finish',
        ],
    ];

    /************************************************************************************
     * create Main Server
     **********************************************************************************/

    /**
     * create Main Server
     * @inheritdoc
     */
    protected function createServer()
    {
        $server = null;
        $opts = $this->serverSettings;

        $host = $opts['host'];
        $port = $opts['port'];
        $mode = $opts['mode'] === self::MODE_BASE ? SWOOLE_BASE : SWOOLE_PROCESS;
        $type = \strtolower($opts['type']);

        $protoEvents = self::$swooleProtocolEvents[self::PROTOCOL_HTTP];

        // create swoole server
        // 使用SSL必须在编译swoole时加入--enable-openssl选项,并且配置证书文件
        switch ($type) {
            case self::PROTOCOL_TCP:
                $server = new Server($host, $port, $mode, SWOOLE_SOCK_TCP);
                $protoEvents = self::$swooleProtocolEvents[self::PROTOCOL_TCP];
                break;

            case self::PROTOCOL_UDP:
                $server = new Server($host, $port, $mode, SWOOLE_SOCK_UDP);
                $protoEvents = self::$swooleProtocolEvents[self::PROTOCOL_TCP];
                break;

            case self::PROTOCOL_HTTP:
                $server = new HttpServer($host, $port, $mode);
                break;

            case self::PROTOCOL_HTTPS:
                $this->checkEnvWhenEnableSSL();
                $server = new HttpServer($host, $port, $mode, SWOOLE_SOCK_TCP | SWOOLE_SSL);
                break;

            case self::PROTOCOL_RDS:
                $server = new RedisServer($host, $port, $mode);
                $protoEvents = self::$swooleProtocolEvents[self::PROTOCOL_RDS];
                break;

            case self::PROTOCOL_WS:
                $server = new WebSocketServer($host, $port, $mode);
                $protoEvents = self::$swooleProtocolEvents[self::PROTOCOL_WS];
                break;

            case self::PROTOCOL_WSS:
                $this->checkEnvWhenEnableSSL();
                $server = new WebSocketServer($host, $port, $mode, SWOOLE_SOCK_TCP | SWOOLE_SSL);
                $protoEvents = self::$swooleProtocolEvents[self::PROTOCOL_WS];
                break;

            default:
                $supportedProtocol = implode(',', $this->getSupportedProtocols());
                Show::error("The socket protocol <bold>$type</bold> is not supported. Allow: $supportedProtocol", 1);
                break;
        }

        $this->server = $server;
        $this->server->set($this->swooleSettings);

        $this->log("The main server was created successfully. Listening on <cyan>$type://{$host}:{$port}</cyan>");
        $this->setSwooleEvents($protoEvents);
    }

    /**
     * register Swoole Events
     * @param array $events
     */
    protected function registerServerEvents(array $events)
    {
        $eventInfo = [];

        // register event to swoole
        foreach ($events as $name => $cb) {
            // is a Closure callback, add by self::onSwoole()
            if (\is_object($cb) && \method_exists($cb, '__invoke')) {
                $eventInfo[] = [$name, \get_class($cb)];
                $this->server->on($name, $cb);
                continue;
            }

            if (\is_string($cb)) {
                if (\is_int($name)) {
                    $name = $cb;
                }

                $method = SwooleEvent::getHandler($cb);
                if ($method && \method_exists($this, $method)) {
                    $eventInfo[] = [$name, static::class . "->$method"];
                    $this->server->on($name, [$this, $method]);
                } elseif (\function_exists($cb)) {
                    $eventInfo[] = [$name, $cb];
                    $this->server->on($name, $cb);
                }
            }
        }

        Show::table($eventInfo, 'Registered events to the server', [
            'showBorder' => 0,
            'columns' => ['event name', 'swoole event handler']
        ]);
    }

    /*******************************************************************************
     * custom user process
     ******************************************************************************/

    /**
     * attach user's custom process
     */
    protected function attachUserProcesses()
    {
        // create Reload Worker
        if ($reloader = $this->createHotReloader()) {
            $this->server->addProcess($reloader);
        }

        foreach ($this->processCallbacks as $name => $callback) {
            $this->createProcess($name, $callback);
        }
    }

    /**
     * @param string $name
     * @return null|Process
     */
    public function getProcess(string $name)
    {
        return $this->processes[$name] ?? null;
    }

    /**
     * @return Process[]
     */
    public function getProcesses(): array
    {
        return $this->processes;
    }

    /**
     * @param array $processes
     */
    public function addProcesses(array $processes)
    {
        foreach ($processes as $name => $callback) {
            $this->addProcess($name, $callback);
        }
    }

    /**
     * @param string $name
     * @param \Closure $callback
     */
    public function addProcess(string $name, \Closure $callback)
    {
        $this->processNames[$name] = true;
        $this->processCallbacks[$name] = $callback;
    }

    /**
     * @param string $name
     * @param \Closure $callback
     */
    public function createProcess(string $name, \Closure $callback)
    {
        $this->fire(ServerEvent::BEFORE_PROCESS_CREATE, [$this, $name]);

        $process = new Process(function (Process $p) use ($callback, $name) {
            $this->workerPid = $this->server->worker_pid = $p->pid;
            ProcessUtil::setTitle("swoole: {$name} ({$this->name})");

            $this->fire(ServerEvent::PROCESS_STARTED, [$this, $name]);
            $callback($p, $this);

            // 群发收到的消息
            // $p->write('message');
        });

        // addProcess 添加的用户进程中无法使用task投递任务，
        // 请使用 $server->sendMessage() 接口与工作进程通信
        $this->server->addProcess($process);
        $this->fire(ServerEvent::PROCESS_CREATED, [$this, $name]);
    }

    /**
     * create code hot reload worker
     * @see https://wiki.swoole.com/wiki/page/390.html
     * @return Process|false
     * @throws \RuntimeException
     */
    protected function createHotReloader()
    {
        $reload = $this->config['auto_reload'];

        if (!$reload || !\function_exists('inotify_init')) {
            return false;
        }

        $mgr = $this;
        $options = [
            'dirs' => $reload,
            // 'masterPid' => $this->server->master_pid
        ];

        // 创建用户自定义的工作进程worker
        return new Process(function (Process $process) use ($options, $mgr) {
            $pid = $process->pid;

            $this->workerPid = $this->server->worker_pid = $pid;
            ProcessUtil::setTitle("swoole: hot-reload ({$mgr->name})");

            // $pid = $process->pid;
            $svrPid = $mgr->server->master_pid;
            $onlyReloadTask = isset($options['only_reload_task']) ? (bool)$options['only_reload_task'] : false;
            $dirs = array_map('trim', explode(',', $options['dirs']));

            $this->log("The <info>hot-reload</info> worker process success started. (PID:{$pid}, SVR_PID:$svrPid, Watched:<info>{$options['dirs']}</info>)");

            $kit = new HotReloading($svrPid);
            $kit
                ->addWatches($dirs, $this->config['rootPath'])
                ->setReloadHandler(function ($pid) use ($mgr, $onlyReloadTask) {
                    $mgr->log("Begin reload workers process. (Master PID: {$pid})");
                    $mgr->server->reload($onlyReloadTask);
                    // $mgr->doReloadWorkers($pid, $onlyReloadTask);
                });

            //Interact::section('Watched Directory', $kit->getWatchedDirs());

            $kit->run();
        });
    }

    /*******************************************************************************
     * attach listen port server
     ******************************************************************************/

    /**
     * create Listen Port Servers
     * @param Server $server
     */
    protected function createListenServers(Server $server)
    {
        $this->fire(ServerEvent::BEFORE_PORT_CREATE, [$this]);

        foreach ($this->attachedListeners as $name => $cb) {
            $info = '';

            if ($cb instanceof \Closure) {
                $port = $cb($server, $this);
            } else {
                /**
                 * @var ServerInterface $this
                 * @var PortListenerInterface $cb
                 */
                $port = $cb->attachTo($this, $server);
            }

            if ($port) {
                $type = $port->type === SWOOLE_SOCK_TCP ? 'tcp' : 'udp';
                $info = "(<cyan>$type://{$port->host}:{$port->port}</cyan>)";
            }

            $this->log("Attach the port listen server <info>$name</info>$info to the main server");
        }

        $this->attachedListeners = []; // reset.
        $this->fire(ServerEvent::PORT_CREATED, [$this]);
    }

    /**
     * attach add listen port to main server.
     * @param $name
     * @param \Closure|array|PortListenerInterface $config
     * @throws \InvalidArgumentException
     */
    public function attachListener(string $name, $config)
    {
        $this->attachPort($name, $config);
    }

    /**
     * attach add listen port to main server.
     * @param string $name
     * @param \Closure|array|PortListenerInterface $config
     * @throws \InvalidArgumentException
     */
    public function attachPort(string $name, $config)
    {
        if (isset($this->attachedNames[strtolower($name)])) {
            throw new \InvalidArgumentException("The add listen port server [$name] has been exists!");
        }

        if (\is_array($config)) {
            $class = Arr::remove($config, 'listener');

            if (!$class) {
                throw new \InvalidArgumentException("Please setting the 'listener' class for the port server: $name");
            }

            $cb = new $class($config);

            if (!$cb instanceof PortListenerInterface) {
                throw new \InvalidArgumentException(
                    'The event handler must implement of ' . PortListenerInterface::class
                );
            }
        } elseif ($config instanceof \Closure || $config instanceof PortListenerInterface) {
            $cb = $config;

        } else {
            throw new \InvalidArgumentException('The 2th argument type only allow [array|\Closure|InterfacePortListener].');
        }

        $this->attachedNames[$name] = true;
        $this->attachedListeners[$name] = $cb;
    }

    /**
     * @param string $name
     * @return Port|mixed
     */
    public function getAttachedListener(string $name)
    {
        return $this->getAttachedServer($name);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getAttachedServer(string $name)
    {
        if (!isset($this->attachedNames[$name])) {
            throw new \RuntimeException("The add listen port server [$name] don't exists!");
        }

        return $this->attachedListeners[$name];
    }

    /**
     * @param $name
     * @return bool
     */
    public function isAttachedServer(string $name): bool
    {
        return isset($this->attachedNames[$name]);
    }

    /**
     * @param null|string $protocol
     * @return array|null
     */
    public function getSwooleProtocolEvents($protocol = null)
    {
        if (null === $protocol) {
            return self::$swooleProtocolEvents;
        }

        return self::$swooleProtocolEvents[$protocol] ?? null;
    }
}
