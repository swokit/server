<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/8/1
 * Time: 下午7:02
 */

namespace inhere\server\traits;


use inhere\console\utils\Show;
use inhere\library\helpers\Arr;
use inhere\server\portListeners\InterfacePortListener;
use Swoole\Server;
use Swoole\Http\Server as SWHttpServer;
use Swoole\Websocket\Server as WSServer;
use Swoole\Server\Port;

/**
 * Class ServerCreateTrait
 * @package inhere\server\traits
 *
 * @property Server $server
 */
trait ServerCreateTrait
{
    /**
     * attached listen port server callback(`Closure`)
     *
     * [
     *   'name' => \Closure,
     *   'name1' => InterfacePortListener
     * ]
     *
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

//////////////////////////////////////////////////////////////////////
/// create Main Server
//////////////////////////////////////////////////////////////////////

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
                $server = new Server($host, $port, $mode, SWOOLE_SOCK_TCP);
                $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_TCP];
                break;

            case self::PROTOCOL_UDP:
                $server = new Server($host, $port, $mode, SWOOLE_SOCK_UDP);
                $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_TCP];
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
                $protocolEvents = $this->swooleProtocolEvents[self::PROTOCOL_WS];
                break;

            case self::PROTOCOL_WSS:
                $this->checkEnvWhenEnableSSL();

                $server = new WSServer($host, $port, $mode, SWOOLE_SOCK_TCP | SWOOLE_SSL);
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
    protected function afterCreateServer()
    {
        // attach registered listen port server to main server
        $this->startListenServers($this->server);
    }

    /**
     * @inheritdoc
     */
    protected function registerServerEvents()
    {
        $eventInfo = [];

        // register event to swoole
        foreach ($this->swooleEventMap as $name => $cb) {
            // is a Closure callback, add by self::onSwoole()
            if (method_exists($cb, '__invoke')) {
                $eventInfo[] = [$name, get_class($cb)];
                $this->server->on($name, $cb);
            }

            // if use Custom Outside Handler
            if (method_exists($this, $cb)) {
                $eventInfo[] = [$name, static::class . "->$cb"];
                $this->server->on($name, [$this, $cb]);
            }
        }

        $opts = [
            'showBorder' => 0,
            'tHead' => ['event name', 'event handler']
        ];
        Show::table($eventInfo, 'Registered swoole events to the main server', $opts);
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
            $msg = "Attach the listen server <info>$name</info> to the main server.";

            if ($cb instanceof \Closure) {
                $port = $cb($server, $this);
            } else {
                /** @var InterfacePortListener $cb */
                $cb->setMgr($this);
                $port = $cb->createPortServer($server);
            }

            if ($port) {
                $type = $port->type === SWOOLE_SOCK_TCP ? 'tcp' : 'udp';
                $msg .= " on <blue>{$port->host}:{$port->port}</blue>($type)";
            }

            $this->log($msg);
        }
    }

    /**
     * attach add listen port to main server.
     * @param $name
     * @param \Closure|array|InterfacePortListener $config
     */
    public function attachListener($name, $config)
    {
        $this->attachPortListener($name, $config);
    }

    /**
     * attach add listen port to main server.
     * @param $name
     * @param \Closure|array|InterfacePortListener $config
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

            if (!$cb instanceof InterfacePortListener) {
                throw new \InvalidArgumentException(
                    'The event handler must implement of ' . InterfacePortListener::class
                );
            }

        } elseif ($config instanceof \Closure) {
            $cb = $config;

        }  elseif ($config instanceof InterfacePortListener) {
            $cb = $config;

        } else {
            throw new \InvalidArgumentException('The 2th argument type only allow [array|\Closure|InterfacePortListener].');
        }

        $this->attachedNames[$name] = true;
        $this->attachedListeners[$name] = $cb;
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
}
