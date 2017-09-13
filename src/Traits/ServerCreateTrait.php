<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/8/1
 * Time: 下午7:02
 */

namespace Inhere\Server\Traits;

use inhere\console\utils\Show;
use inhere\library\helpers\Arr;
use Inhere\Server\ExtendServerInterface;
use Inhere\Server\PortListeners\PortListenerInterface;
use Inhere\Server\ServerInterface;
use Swoole\Http\Server as SWHttpServer;
use Swoole\Server;
use Swoole\Server\Port;
use Swoole\Websocket\Server as WSServer;

/**
 * Class ServerCreateTrait
 * @package Inhere\Server\Traits
 * @property Server $server
 */
trait ServerCreateTrait
{
    /**
     * @var ExtendServerInterface
     */
    protected $extServer;

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
     * [
     *      'port1' => true
     * ]
     */
    public $attachedNames = [];

    /**
     * @var array
     */
    private static $swooleProtocolEvents = [
        // TCP server callback
        'tcp' => ['onConnect', 'onReceive', 'onClose'],

        // UDP server callback
        'udp' => ['onPacket', 'onClose'],

        // HTTP server callback
        'http' => ['onRequest'],

        // Web Socket server callback
        'ws' => ['onMessage', 'onOpen', 'onHandShake', 'onClose'],
    ];

//////////////////////////////////////////////////////////////////////
/// create Main Server
//////////////////////////////////////////////////////////////////////

    /**
     * before create Server
     */
    public function beforeCreateServer()
    {
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

        $protocolEvents = self::$swooleProtocolEvents[self::PROTOCOL_HTTP];

        // create swoole server
        // 使用SSL必须在编译swoole时加入--enable-openssl选项,并且配置证书文件
        switch ($type) {
            case self::PROTOCOL_TCP:
                $server = new Server($host, $port, $mode, SWOOLE_SOCK_TCP);
                $protocolEvents = self::$swooleProtocolEvents[self::PROTOCOL_TCP];
                break;

            case self::PROTOCOL_UDP:
                $server = new Server($host, $port, $mode, SWOOLE_SOCK_UDP);
                $protocolEvents = self::$swooleProtocolEvents[self::PROTOCOL_TCP];
                break;

            case self::PROTOCOL_HTTP:
                $server = new SWHttpServer($host, $port, $mode);
                break;

            case self::PROTOCOL_HTTPS:
                $this->checkEnvWhenEnableSSL();
                $server = new SWHttpServer($host, $port, $mode, SWOOLE_SOCK_TCP | SWOOLE_SSL);
                break;

            case self::PROTOCOL_WS:
                $server = new WSServer($host, $port, $mode);
                $protocolEvents = self::$swooleProtocolEvents[self::PROTOCOL_WS];
                break;

            case self::PROTOCOL_WSS:
                $this->checkEnvWhenEnableSSL();
                $server = new WSServer($host, $port, $mode, SWOOLE_SOCK_TCP | SWOOLE_SSL);
                $protocolEvents = self::$swooleProtocolEvents[self::PROTOCOL_WS];
                break;

            default:
                $supportedProtocol = implode(',', $this->getSupportedProtocols());
                Show::error("The socket protocol <bold>$type</bold> is not supported. Allow: $supportedProtocol", 1);
                break;
        }

        $this->log("Create the main swoole server. on <cyan>$type://{$host}:{$port}</cyan>");

        $this->setSwooleEvents($protocolEvents);

        $this->server = $server;
    }

    /**
     * afterCreateServer
     * @throws \RuntimeException
     */
    protected function afterCreateServer()
    {
        if ($extServer = $this->config['main_server']['extend_server']) {
            /** @var ServerInterface $this */
            $this->extServer = new $extServer($this->config['options']);
            $this->extServer->setMgr($this);
        }

        // register swoole events handler
        $this->registerServerEvents();

        // setting swoole config
        $this->server->set($this->config['swoole']);

        // create Reload Worker
        $this->createHotReloader();
    }

    /**
     * register Swoole Events
     */
    protected function registerServerEvents()
    {
        $eventInfo = [];
        // Show::aList($this->swooleEventMap, 'Registered swoole events to the main server: event -> handler');

        // register event to swoole
        foreach (self::$swooleEventMap as $name => $cb) {
            // is a Closure callback, add by self::onSwoole()
            if (is_object($cb) && method_exists($cb, '__invoke')) {
                $eventInfo[] = [$name, get_class($cb)];
                $this->server->on($name, $cb);

                // if use Custom Outside Handler
            } elseif ($this->extServer && method_exists($this->extServer, $cb)) {
                $eventInfo[] = [$name, get_class($this->extServer) . "->$cb"];
                $this->server->on($name, [$this->extServer, $cb]);
                // if use Custom Outside Handler
            } elseif (method_exists($this, $cb)) {
                $eventInfo[] = [$name, static::class . "->$cb"];
                $this->server->on($name, [$this, $cb]);
            } elseif (function_exists($cb)) {
                $eventInfo[] = [$name, $cb];
                $this->server->on($name, $cb);
            }
        }

        Show::table($eventInfo, 'Registered events to the main server', [
            'showBorder' => 0,
            'tHead' => ['event name', 'event handler']
        ]);
    }

//////////////////////////////////////////////////////////////////////
/// attach listen port server
//////////////////////////////////////////////////////////////////////

    /**
     * start Listen Port Servers
     * @param Server $server
     */
    protected function startListenServers(Server $server)
    {
        foreach ($this->attachedListeners as $name => $cb) {
            $msg = "Attach the listen server <info>$name</info> to the main server";

            if ($cb instanceof \Closure) {
                $port = $cb($server, $this);
            } else {
                /**
                 * @var PortListenerInterface $cb
                 * @var ServerInterface $this
                 */
                $port = $cb->attachTo($this, $server);
            }

            if ($port) {
                $type = $port->type === SWOOLE_SOCK_TCP ? 'tcp' : 'udp';
                $msg .= "(<cyan>$type://{$port->host}:{$port->port}</cyan>)";
            }

            $this->log($msg);
        }
    }

    /**
     * attach add listen port to main server.
     * @param $name
     * @param \Closure|array|PortListenerInterface $config
     */
    public function attachListener($name, $config)
    {
        $this->attachPortListener($name, $config);
    }

    /**
     * attach add listen port to main server.
     * @param $name
     * @param \Closure|array|PortListenerInterface $config
     */
    public function attachPortListener($name, $config)
    {
        $name = strtolower($name);

        if (isset($this->attachedNames[$name])) {
            throw new \RuntimeException("The add listen port server [$name] exists!");
        }

        if (is_array($config)) {
            $class = Arr::remove($config, 'listener');
            $cb = new $class($config);

            if (!$cb instanceof PortListenerInterface) {
                throw new \InvalidArgumentException(
                    'The event handler must implement of ' . PortListenerInterface::class
                );
            }

        } elseif ($config instanceof \Closure) {
            $cb = $config;

        } elseif ($config instanceof PortListenerInterface) {
            $cb = $config;

        } else {
            throw new \InvalidArgumentException('The 2th argument type only allow [array|\Closure|InterfacePortListener].');
        }

        $this->attachedNames[$name] = true;
        $this->attachedListeners[$name] = $cb;
    }

    /**
     * @param ExtendServerInterface $extServer
     */
    public function setExtServer(ExtendServerInterface $extServer)
    {
        $this->extServer = $extServer;
    }

    /**
     * @return ExtendServerInterface
     */
    public function getExtServer()
    {
        return $this->extServer;
    }

    /**
     * @param $name
     * @return Port
     */
    public function getAttachedListener($name)
    {
        return $this->getAttachedServer($name);
    }

    /**
     * @param $name
     * @return mixed
     */
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

    /**
     * @param null|string $protocol
     * @return array
     */
    public function getSwooleProtocolEvents($protocol = null)
    {
        if (null === $protocol) {
            return self::$swooleProtocolEvents;
        }

        return self::$swooleProtocolEvents[$protocol] ?? null;
    }
}
