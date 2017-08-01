<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-20
 * Time: 15:20
 */

namespace inhere\server\portListeners;

use inhere\console\utils\Show;
use inhere\library\traits\OptionsTrait;
use inhere\server\AbstractServer;
use Swoole\Server;

/**
 * Class BaseListener
 * @package inhere\server\portListeners
 */
abstract class PortListener implements InterfacePortListener
{
    use OptionsTrait;

    /**
     * @var AbstractServer
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
        'host' => '127.0.0.1',
        'port' => 0,
        'events' => [
            'onConnect'
        ],
    ];

    /**
     * @var Server\Port
     */
    private $port;

    /**
     * AbstractServerHandler constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if ($options) {
            $this->setOptions($options);
        }
    }

    /**
     * @param AbstractServer $mgr
     */
    public function setMgr(AbstractServer $mgr)
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
     * @see AbstractServer::log()
     * @param  string $msg
     * @param  array $data
     * @param string $type
     */
    public function log($msg, array $data = [], $type = 'debug')
    {
        $this->mgr->log($msg, $data, $type);
    }

    /**
     * @param Server $server
     */
    public function createPortServer(Server $server)
    {
        $type = $this->type;
        $allowed = [AbstractServer::PROTOCOL_TCP, AbstractServer::PROTOCOL_UDP];

        if (!in_array($type, $allowed, true)) {
            $str = implode(',', $allowed);
            Show::error("Tha attach listen server type only allow: $str. don't support [$type]", 1);
        }

        $socketType = $this->type === AbstractServer::PROTOCOL_UDP ? SWOOLE_SOCK_UDP : SWOOLE_SOCK_TCP;

        $this->port = $server->addlistener($this->options['host'], $this->options['port'], $socketType);
        $this->port->set($this->mgr->getValue('swoole'));

        $this->registerServerEvents();
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
