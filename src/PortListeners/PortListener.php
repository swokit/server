<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace Inhere\Server\PortListeners;

use inhere\console\utils\Show;
use inhere\library\traits\OptionsTrait;
use Inhere\Server\InterfaceServer;
use Swoole\Server;

/**
 * Class BaseListener
 * @package Inhere\Server\PortListeners
 */
abstract class PortListener implements InterfacePortListener
{
    use OptionsTrait;

    /**
     * @var InterfaceServer
     */
    protected $mgr;

    /**
     * @var string
     */
    protected $type = 'tcp';

    /**
     * options
     * @var array
     */
    protected $options = [
        'enable' => true,
        'host' => '127.0.0.1',
        'port' => 0,
        'events' => [
            'onConnect'
        ],
        'setting' => [],
    ];

    /**
     * @var Server\Port
     */
    private $port;

    /**
     * @var \Closure
     */
    private $beforeCreateServerHandler;

    /**
     * InterfaceServerHandler constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if ($options) {
            $this->setOptions($options);
        }

        $this->init();
    }

    protected function init()
    {
        // something ... ...
    }

    /**
     * @param InterfaceServer $mgr
     * @param Server $server
     * @return \Swoole\Server\Port
     */
    public function attachTo(InterfaceServer $mgr, Server $server)
    {
        $this->mgr = $mgr;
        return $this->createPortServer($server);
    }

    /**
     * @param InterfaceServer $mgr
     */
    public function setMgr(InterfaceServer $mgr)
    {
        $this->mgr = $mgr;
    }

    /**
     * @param null $key
     * @param null $default
     * @return \inhere\library\collections\Config|mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if (null === $key) {
            return $this->mgr->getConfig();
        }

        return $this->mgr->getValue($key, $default);
    }

    /**
     * output log message
     * @see InterfaceServer::log()
     * @param  string $msg
     * @param  array $data
     * @param string $type
     */
    public function log($msg, array $data = [], $type = 'debug')
    {
        $this->mgr->log($msg, $data, $type);
    }

    /**
     * @param \Closure $closure
     */
    public function beforeCreateServer(\Closure $closure)
    {
        $this->beforeCreateServerHandler = $closure;
    }

    /**
     * @param Server $server
     * @return Server\Port
     */
    public function createPortServer(Server $server)
    {
        $type = $this->type;
        $allowed = [InterfaceServer::PROTOCOL_TCP, InterfaceServer::PROTOCOL_UDP];

        if (!in_array($type, $allowed, true)) {
            $str = implode(',', $allowed);
            Show::error("Tha attach listen server type only allow: $str. don't support [$type]", 1);
        }

        if ($cb = $this->beforeCreateServerHandler) {
            $cb($this);
        }

        $socketType = $this->type === InterfaceServer::PROTOCOL_UDP ? SWOOLE_SOCK_UDP : SWOOLE_SOCK_TCP;

        $this->port = $server->addlistener($this->options['host'], $this->options['port'], $socketType);
        $this->port->set($this->getOption('setting'));

        $this->registerServerEvents();

        return $this->port;
    }

    /**
     * register Swoole Port Events
     */
    public function registerServerEvents()
    {
        foreach ((array)$this->options['events'] as $event => $method) {
            // ['onConnect'] --> 'Connect', 'onConnect
            if (is_int($event)) {
                $event = substr($method, 2);
            }

            // e.g $server->on('Request', [$this, 'onRequest']);
            if (method_exists($this, $method)) {
                $this->port->on($event, [$this, $method]);
            }
        }
    }
}
