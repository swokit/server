<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/2/18
 * Time: 17:50
 */

namespace inhere\server;

use inhere\console\utils\Show;
use inhere\exceptions\InvalidArgumentException;
use inhere\console\utils\Interact;

use inhere\server\portListeners\InterfacePortListener;
use Swoole\Server as SwServer;
use Swoole\Http\Server as SwHttpServer;
use Swoole\Websocket\Server as SwWSServer;
use Swoole\Server\Port as SwServerPort;

/**
 * Class SuiteServer
 *  相对于其他的几个 server(TcpServer/HttpServer/WSServer) 自定义性更强，需要对swoole的运行逻辑有较好的理解
 * @package inhere\server
 */
class SuiteServer extends AbstractServer
{
    /**
     * custom main server event handle callback
     * @var array
     * [
     *     'event' => callback handler
     *     ... ...
     * ]
     */
    protected $eventCallbacks = [];

    /**
     * attached listen port server callback(`Closure`)
     *
     * [
     *   'name' => \Closure
     * ]
     *
     * @var \Closure[]
     */
    public $attachedListeners = [];

    /**
     * @var array
     * [
     *      'port1' => true
     * ]
     */
    public $attachedNames = [];

    /**
     * @return $this
     */
    protected function init()
    {
        parent::init();

        // register attach server from config
        if ($attachServers = $this->config['attach_servers']) {
            foreach ((array)$attachServers as $name => $conf) {
                $this->attachListenServer($name, $conf);
            }
        }

        // register main server event method
        if ($methods = $this->getValue('main_server.extend_events')) {
            $this->setSwooleEvents($methods);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function createMainServer()
    {
        $server = null;
        $opts = $this->config['main_server'];

        $host = $opts['host'];
        $port = $opts['port'];
        $mode = $opts['mode'] === self::MODE_BASE ? SWOOLE_BASE : SWOOLE_PROCESS;
        $type = strtolower($opts['type']);

        $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_HTTP];

        // create swoole server
        // 使用SSL必须在编译swoole时加入--enable-openssl选项,并且配置证书文件
        switch ($type) {
            case self::PROTOCOL_TCP:
                $server = new SwServer($host, $port, $mode, SWOOLE_SOCK_TCP);
                $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_TCP];
                break;

            case self::PROTOCOL_UDP:
                $server = new SwServer($host, $port, $mode, SWOOLE_SOCK_UDP);
                $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_TCP];
                break;

            case self::PROTOCOL_HTTP:
                $server = new SwHttpServer($host, $port, $mode);
                break;

            case self::PROTOCOL_HTTPS:
                $this->checkEnvWhenEnableSSL();

                $server = new SwHttpServer($host, $port, $mode, SWOOLE_SOCK_TCP | SWOOLE_SSL);
                break;

            case self::PROTOCOL_WS:
                $server = new SwWSServer($host, $port, $mode);
                $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_WS];
                break;

            case self::PROTOCOL_WSS:
                $this->checkEnvWhenEnableSSL();

                $server = new SwWSServer($host, $port, $mode, SWOOLE_SOCK_TCP | SWOOLE_SSL);
                $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_WS];
                break;

            default:
                $supportedProtocol = implode(',', $this->getSupportedProtocols());
                Show::error("The socket protocol <bold>$type</bold> is not supported. Allow: $supportedProtocol", 1);
                break;
        }

        $this->log("Create the main swoole server. on <default>{$host}:{$port}</default>(<info>$type</info>)");

        $this->setSwooleEvents($protocolEvents);

        return $server;
    }

    /**
     * {@inheritDoc}
     */
    protected function afterCreateMainServer()
    {
        parent::afterCreateMainServer();

        // attach registered listen port server to main server
        $this->startListenPortServers($this->server);
    }

    /**
     * @inheritdoc
     */
    protected function registerMainServerEvents()
    {
        $events = $this->swooleEventMap;
        $eventInfo = [];

        // register event to swoole
        foreach ($events as $name => $method) {

            // is a Closure callback, add by self::on()
            if ($cb = $this->getEventCallback($name)) {
                $eventInfo[] = [$name, $method];
                $this->server->on($name, $cb);
            }

            // if use Custom Outside Handler
            if (method_exists($this, $method)) {
                $eventInfo[] = [$name, static::class . "->$method"];
                $this->server->on($name, array($this, $method));
            }
        }

        $opts = [
            'showBorder' => 0,
            'tHead' => ['event name', 'event handler']
        ];
        Show::table($eventInfo, 'Registered swoole events to the main server', $opts);
    }

//////////////////////////////////////////////////////////////////////
/// Event Handler
//////////////////////////////////////////////////////////////////////

    /**
     * register a swoole Event Handler Callback
     * @param string $event
     * @param callable|string $handler
     */
    public function onSwoole($event, $handler)
    {
        // $this->server->on($event, $handler);
        $event = trim($event);
        $this->setSwooleEvent($event, 'A custom handler');
        $this->eventCallbacks[$event] = $handler;

        $this->setSwooleEvent($event, $handler);
    }

    /**
     * get register Event Handler Callback (register by self::on())
     * @param string $event Event name
     * @return \Closure|null
     */
    public function getEventCallback($event)
    {
        $event = strtolower($event);
        return $this->eventCallbacks[$event] ?? null;
    }

//////////////////////////////////////////////////////////////////////
/// attach listen port server
//////////////////////////////////////////////////////////////////////

    /**
     * start Listen Port Servers
     * @param SwServer $server
     */
    protected function startListenPortServers(SwServer $server)
    {
        foreach ($this->attachedListeners as $name => $cb) {
            $msg = "Attach the listen server <info>$name</info> to the main server.";
            $port = $cb($server, $this);

            if ($port) {
                $type = $port->type === SWOOLE_SOCK_TCP ? 'tcp' : 'udp';
                $msg .= " on <blue>{$port->host}:{$port->port}</blue>($type)";
            }

            $this->log($msg);
        }
    }

    /**
     * register Swoole Port Events
     * @param  SwServerPort $port Port instance or port server name.
     * @param  $handler
     * @param  array $events
     */
    public function registerAttachServerEvents($port, $handler, array $events)
    {
        foreach ($events as $event => $method) {
            // ['onConnect'] --> 'Connect', 'onConnect
            if (is_int($event)) {
                $event = substr($method, 2);
            }

            // e.g $server->on('Request', [$this, 'onRequest']);
            if (method_exists($handler, $method)) {
                $port->on($event, [$handler, $method]);
            }
        }
    }

    /**
     * attach add listen port to main server.
     * @param $name
     * @param \Closure|array $config
     * @return SwServerPort
     */
    public function attachListener($name, $config)
    {
        return $this->attachListenServer($name, $config);
    }

    public function attachServer($name, $config)
    {
        return $this->attachListenServer($name, $config);
    }

    public function attachListenServer($name, $config)
    {
        $name = strtolower($name);

        if (isset($this->attachedNames[$name])) {
            throw new \RuntimeException("The add listen port server [$name] exists!");
        }

        if (is_array($config)) {
            $cb = function (SwServer $server, SuiteServer $mgr) use ($config) {
                $type = $config['type'];
                $allowed = [self::PROTOCOL_TCP, self::PROTOCOL_UDP];

                if (!in_array($type, $allowed, true)) {
                    $str = implode(',', $allowed);
                    Show::error("Tha attach listen server type only allow: $str. don't support [$type]", 1);
                }

                $socketType = $type === self::PROTOCOL_UDP ? SWOOLE_SOCK_UDP : SWOOLE_SOCK_TCP;
                $evtHandler = $config['event_handler'];
                $handler = new $evtHandler;

                if ($handler instanceof InterfacePortListener) {
                    throw new InvalidArgumentException(
                        'The event handler must implement of ' . InterfacePortListener::class
                    );
                }

                $handler->setMgr($mgr);

                $port = $server->listen($config['host'], $config['port'], $socketType);
                $port->set($mgr->config['swoole']);

                $mgr->registerAttachServerEvents($port, $handler, (array)$config['event_list']);

                return $port;
            };

        } elseif ($config instanceof \Closure) {
            $cb = $config;

        } else {
            throw new InvalidArgumentException('The 2th argument type only allow [array|\Closure].');
        }

        $this->attachedNames[$name] = true;
        $this->attachedListeners[$name] = $cb;

        return $this;
    }

    /**
     * @param $name
     * @return SwServerPort
     */
    public function getAttachedListener($name)
    {
        return $this->getAttachedServer($name);
    }

    public function getAttachedServer($name)
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
    public function isAttachedServer($name)
    {
        return isset($this->attachedNames[$name]);
    }

//////////////////////////////////////////////////////////////////////
/// other
//////////////////////////////////////////////////////////////////////

    /**
     * {@inheritDoc}
     */
    public function onConnect(SwServer $server, $fd)
    {
        $this->log("onConnect: Has a new client [fd:$fd] connection to the main server.");
    }

    /**
     * @param SwServer $server
     * @param $fd
     */
    public function onClose(SwServer $server, $fd)
    {
        $this->log("onConnect: The client [fd:$fd] connection closed on the main server.");
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

        Interact::panel($panelData, 'Server Information');

        parent::showInformation();
    }

}
