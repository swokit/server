<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/2/18
 * Time: 17:50
 */

namespace inhere\server;

use inhere\library\collections\Config;
use inhere\server\interfaces\ITcpListenHandler;
use inhere\server\interfaces\IUdpListenHandler;
use inhere\server\interfaces\IExtendServer;

use inhere\exceptions\InvalidArgumentException;
use inhere\library\env\Server as ServerEnv;
use inhere\console\utils\Interact;

use Swoole\Server as SwServer;
use Swoole\Http\Server as SwHttpServer;
use Swoole\Websocket\Server as SwWSServer;
use Swoole\Server\Port as SwServerPort;

/**
 * Class SuiteServer
 *  相对于其他的几个 server(TcpServer/HttpServer/WSServer) 自定义性更强，需要对swoole的运行逻辑有较好的理解
 * @package inhere\server
 */
class SuiteServer extends AServerManager
{
    /**
     * main server
     * @var SwServer
     */
    public $server;

    /**
     * The handler object instance for the main server
     * @var IExtendServer
     */
    protected $extendServer;

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
     * @param Config $config
     * @return $this
     */
    protected function init(Config $config)
    {
        parent::init($config);

        // register main server event method
        if ( $methods = $config->get('main_server.extend_events') ) {
            $this->setSwooleEvents($methods);
        }

        // register attach server from config
        if ( $attachServers = $config['attach_servers'] ) {
            foreach ($attachServers as $name => $conf) {
                $this->attachListenServer($name, $conf);
            }
        }

        // set outside extend server handler
        if ( $extendHandler = $config->get('main_server.extend_handler') ) {
            $this->setExtendServer($extendHandler);
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
                $this->cliOut->error("The socket protocol <bold>$type</bold> is not supported. Allow: $supportedProtocol", 1);
                break;
        }

        // server instanced
        if ( $server ) {
            $this->addLog("Create the main swoole server. on <default>{$host}:{$port}</default>(<info>$type</info>)");

            $this->setSwooleEvents($protocolEvents);

            // attach registered listen port server to main server
            $this->attachListenPortServers($server);
        }

        return $server;
    }

    /**
     * @inheritdoc
     */
    protected function registerMainServerEvents()
    {
        // use custom outside main server event handler
        if ( $handler = $this->extendServer ) {
            $className = get_class($handler);
            $isOutsideHandler = true;
        } else {
            // default register to self.
            $handler = $this;
            $className = static::class;
            $isOutsideHandler = false;
        }

        $events = $this->swooleEvents;
        $eventInfo = [];

        // register event to swoole
        foreach ($events as $name => $method) {

            // is a Closure callback, add by self::on()
            if ( $cb = $this->getEventCallback($name) ) {
                $eventInfo[] = [ $name, $method];
                $this->server->on($name, $cb);
            }

            if( method_exists($handler, $method) ) {
                $eventInfo[] = [ $name, "$className->$method"];
                $this->server->on($name, array($handler, $method));

            // if use Custom Outside Handler
            } else if ( $isOutsideHandler && method_exists($this, $method) ) {
                $eventInfo[] = [$name, static::class . "->$method"];
                $this->server->on($name, array($this, $method));
            }
        }

        $opts = [
            'showBorder' => 0,
            'tHead' => ['event name', 'event handler']
        ];
        $this->cliOut->table($eventInfo, 'Registered swoole events to the main server', $opts);
    }

//////////////////////////////////////////////////////////////////////
/// Event Handler
//////////////////////////////////////////////////////////////////////

    /**
     * register Event Handler Callback
     * @param $event
     * @param \Closure $cb
     * @return $this
     */
    public function on($event, \Closure $cb)
    {
        $event = trim($event);

        $this->setSwooleEvent($event, 'A-CLOSURE');

        $this->eventCallbacks[$event] = $cb;

        return $this;
    }

    /**
     * get register Event Handler Callback (register by self::on())
     * @param string $event Event name
     * @return \Closure|null
     */
    public function getEventCallback($event)
    {
        $event = strtolower($event);

        if (
            isset($this->eventCallbacks[$event]) &&
            ($cb = $this->eventCallbacks[$event]) &&
            ($cb instanceof \Closure)
        ) {
            return $cb;
        }

        return null;
    }

    /**
     * @param IExtendServer|string $extendServer
     */
    public function setExtendServer($extendServer)
    {
        if ( is_string($extendServer) ) {
            $handler = new $extendServer;
        } elseif (is_object($extendServer)) {
            $handler = $extendServer;
        } else {
            throw new \InvalidArgumentException('The parameter type only allow [string|object]');
        }

        if ( !($handler instanceof IExtendServer) ) {
            throw new \RuntimeException('The main server handler class must instanceof ' . IExtendServer::class );
        }

        $handler->setMgr($this);

        if ( $opts = $this->config->get('extend_options') ) {
            $handler->setOptions($opts, true);
        }

        $this->extendServer = $handler;
    }

    /**
     * @return IExtendServer
     *
     * eg:
     *
     * ```
     * \inhere\server\handlers\HttpServerHandler
     * \inhere\server\handlers\TcpServerHandler
     * ```
     */
    public function getExtendServer()
    {
        return $this->extendServer;
    }


//////////////////////////////////////////////////////////////////////
/// attach listen port server
//////////////////////////////////////////////////////////////////////

    /**
     * @param SwServer $server
     */
    protected function attachListenPortServers(SwServer $server)
    {
        foreach ($this->attachedListeners as $name => $cb) {
            $msg = "Attach the listen server <info>$name</info> to the main server.";

            $port = $cb($server, $this);

            if ($port) {
                $type = $port->type === SWOOLE_SOCK_TCP ? 'tcp' : 'udp';
                $msg .= " on <default>{$port->host}:{$port->port}</default>($type)";
            }

            $this->addLog($msg);
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
        foreach ($events as $event => $method ) {
            // ['onConnect'] --> 'Connect', 'onConnect
            if ( is_int($event) ) {
                $event = substr($method,2);
            }

            // e.g $server->on('Request', [$this, 'onRequest']);
            if ( method_exists($handler, $method) ) {
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

        if ( isset($this->attachedNames[$name]) ) {
            throw new \RuntimeException("The add listen port server [$name] exists!");
        }

        if ( is_array($config) ) {
            $cb = function(SwServer $server, SuiteServer $mgr) use ($config) {
                $type = $config['type'];
                $allowed = [self::PROTOCOL_TCP, self::PROTOCOL_UDP];

                if ( !in_array($type, $allowed) ) {
                    $str = implode(',', $allowed);
                    $this->cliOut->error("Tha attach listen server type only allow: $str. don't support [$type]",1);
                }

                $socketType = $type === self::PROTOCOL_UDP ? SWOOLE_SOCK_UDP : SWOOLE_SOCK_TCP;
                $evtHandler = $config['event_handler'];
                $handler =  new $evtHandler;

                if ( !($handler instanceof ITcpListenHandler) && !($handler instanceof IUdpListenHandler) ) {
                    throw new InvalidArgumentException(
                        'The event handler must implement of ' . ITcpListenHandler::class . ' Or ' . IUdpListenHandler::class
                    );
                }

                $handler->setMgr($mgr);

                $port = $server->listen($config['host'], $config['port'], $socketType);
                $port->set($mgr->config['swoole']);

                $mgr->registerAttachServerEvents($port, $handler, (array)$config['event_list']);

                return $port;
            };

        } elseif ( $config instanceof \Closure ) {
            $cb = $config;

        } else {
            throw new InvalidArgumentException("The 2th argument type only allow [array|\\Closure].");
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
        if ( !isset($this->attachedNames[$name]) ) {
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

    public function onConnect(SwServer $server, $fd)
    {
        $this->addLog("Has a new client [FD:$fd] connection to the main server.");
    }

    public function onClose(SwServer $server, $fd)
    {
        $this->addLog("The client [FD:$fd] connection closed on the main server.");
    }

    /**
     * Show server info
     */
    protected function showInformation()
    {
        $sEnv = new ServerEnv;
        $swOpts = $this->config->get('swoole');
        $main = $this->config->get('main_server');
        $panelData = [
            'Operate System' => $sEnv->get('os'),
            'PHP Version' => PHP_VERSION,
            'Swoole Info' => [
                'version' => SWOOLE_VERSION,
                'coroutine' => class_exists('\Swoole\Coroutine', false),
            ],
            'Swoole Config' => [
                'dispatch_mode'   => $swOpts['dispatch_mode'],
                'worker_num'      => $swOpts['worker_num'],
                'task_worker_num' => $swOpts['task_worker_num'],
                'max_request'     => $swOpts['max_request'],
            ],
            'Main Server' => [
                'type' => $main['type'],
                'mode' => $main['mode'],
                'host' => $main['host'],
                'port' => $main['port'],
                'class' => static::class
            ],
            'Project Config' => [
                'name' => $this->name,
                'path' => $this->config->get('root_path'),
                'auto_reload' => $this->config->get('auto_reload'),
            ],
        ];

        Interact::panel($panelData, 'Server Information');

        parent::showInformation();
    }

    /**
     * @return array
     */
    public function getDefaultConfig()
    {
        $config = [
            // for main server
            'main_server' => [
                'host' => '0.0.0.0',
                'port' => '8662',

                // 运行模式
                // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
                'mode' => 'process',
                'type' => 'tcp', // http https tcp udp ws wss

                // append register swoole events
                'extend_events'   => [], // e.g [ 'onRequest', ]

                // use outside's extend event handler
                'extend_handler' => '', // e.g '\inhere\server\extend\HttpServerHandler'
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

            // for current main server/ outside extend server options.
            // eg: @see \inhere\server\extend\HttpServerHandler::options
            'extend_options' => [

            ],
        ];

        return array_merge(parent::getDefaultConfig(), $config);
    }

}
