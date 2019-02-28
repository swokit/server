<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace Swokit\Server\Listener\Port;

use Inhere\Console\Utils\Show;
use Monolog\Logger;
use Swokit\Server\ServerInterface;
use Swoole\Server;
use Toolkit\Traits\Config\OptionsTrait;

/**
 * Class BaseListener
 * @package Swokit\Server\Listener\Port
 */
abstract class PortListener implements PortListenerInterface
{
    use OptionsTrait;

    /**
     * @var ServerInterface
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

    /** @var \Closure */
    private $onBeforeCreate;

    /** @var \Closure */
    private $onAfterCreate;

    /**
     * ServerInterfaceHandler constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if ($options) {
            $this->setOptions($options);
        }

        $this->init();
    }

    protected function init(): void
    {
        // something ... ...
    }

    /**
     * @param ServerInterface $mgr
     * @param Server $server
     * @return \Swoole\Server\Port
     */
    public function attachTo(ServerInterface $mgr, Server $server)
    {
        $this->mgr = $mgr;

        return $this->createPortServer($server);
    }

    /**
     * @param ServerInterface $mgr
     */
    public function setMgr(ServerInterface $mgr): void
    {
        $this->mgr = $mgr;
    }

    /**
     * @param null $key
     * @param null $default
     * @return mixed
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
     * @see ServerInterface::log()
     * @param  string $msg
     * @param  array $data
     * @param int $level
     */
    public function log($msg, array $data = [], $level = Logger::INFO): void
    {
        $this->mgr->log($msg, $data, $level);
    }

    /**
     * @param \Closure $closure
     */
    public function beforeCreate(\Closure $closure): void
    {
        $this->onBeforeCreate = $closure;
    }

    /**
     * @param \Closure $closure
     */
    public function afterCreate(\Closure $closure): void
    {
        $this->onAfterCreate = $closure;
    }

    /**
     * @param Server $server
     * @return Server\Port
     */
    public function createPortServer(Server $server)
    {
        $type = $this->type;
        $allowed = [ServerInterface::PROTOCOL_TCP, ServerInterface::PROTOCOL_UDP];

        if (!\in_array($type, $allowed, true)) {
            $str = implode(',', $allowed);
            Show::error("Tha attach listen server type only allow: $str. don't support [$type]", 1);
        }

        if ($cb = $this->onBeforeCreate) {
            $cb($this);
        }

        $socketType = $this->type === ServerInterface::PROTOCOL_UDP ? SWOOLE_SOCK_UDP : SWOOLE_SOCK_TCP;

        $this->port = $server->addlistener($this->options['host'], $this->options['port'], $socketType);
        $this->port->set($this->getOption('setting'));

        $this->registerPortEvents();

        if ($cb = $this->onBeforeCreate) {
            $cb($this);
        }

        return $this->port;
    }

    /**
     * register Swoole Port Events
     */
    public function registerPortEvents(): void
    {
        foreach ((array)$this->options['events'] as $event => $method) {
            // ['onConnect'] --> 'Connect', 'onConnect
            if (\is_int($event)) {
                $event = substr($method, 2);
            }

            // e.g $server->on('Request', [$this, 'onRequest']);
            if (method_exists($this, $method)) {
                $this->port->on($event, [$this, $method]);
            }
        }
    }
}
