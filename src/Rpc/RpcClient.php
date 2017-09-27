<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-08-30
 * Time: 15:06
 */

namespace Inhere\Server\Rpc;

use Swoole\Client;
use Swoole\Coroutine\Client as CoClient;

/**
 * Class RpcClient
 * @package Inhere\Server\Rpc
 */
class RpcClient
{
    /**
     * @var array
     * [
     *  name => [
     *      'host' => 'xxx.com',
     *      'ip' => '127.0.0.1:5685',
     *      'weight' => 0, // 0 - 100
     *  ],
     * ]
     */
    private $servers;

    /**
     * @var array
     */
    private $names;

    /**
     * @var array
     */
    private $setting = [
        'open_eof_check' => true,
        'package_eof' => "\r\n\r\n",
        'package_max_length' => 1024 * 1024 * 2,
        'socket_buffer_size' => 1024 * 1024 * 2, //2M缓存区
    ];

    /**
     * @var array
     */
    private $connections;

    /**
     * @var int
     */
    private $expire = 60;

    public function __construct(array $servers = [], array $setting = [])
    {
        $this->servers = $servers;

        if ($setting) {
            $this->setting = $setting;
        }
    }

    /**
     * @param string $method
     * @param array $args
     * @return array
     */
    public function __call($method, array $args = [])
    {
        $server = null;

        if (isset($args['_server'])) {
            $server = $args['_server'];
            unset($args['_server']);
        }

        return $this->call($method, $args, $server);
    }

    /**
     * @param $server
     * @param array $args
     * @return array
     */
    public function getServices($server = null, array $args = [])
    {
        return $this->call('_m/services', $args, $server);
    }

    public function call($service, array $args = [], $callback = null, $server = null)
    {
        if (!$server) {
            $server = array_rand($this->getNames());
        }

        $conn = $this->connections[$server] ?? null;

        if (!$conn) {
            $conf = $this->servers[$server] ?? null;

            if (!$conf) {
                throw new \InvalidArgumentException("the server name [$server] is not exists");
            }

            $conn = $this->createCoroClient($conf, $this->setting);

            $this->connections[$server] = $conn;
        }

        $conn->send('ddd');

        return [];
    }

    public function buildProtocolData($service, array $args, array $options = [])
    {
        $mTime = microtime(1);
        $params = json_encode($args);
        $meta = json_encode(array_merge([
            'id' => md5($mTime . $service),
            'time' => $mTime,
            'key' => 'sec key',
            'token' => 'request token',
        ], $options));

        return "RPC-S: $service\r\n" .
            "RPC-P: $params\r\n" .
            "RPC-M: $meta\r\n\r\n";
    }

    protected function createCoroClient(array $conf, array $setting = [])
    {
        $conn = new CoClient(SWOOLE_TCP);
        $conn->set($setting);
        $conn->connect($conf['host'], $conf['port'], 3);

        return $conn;
    }

    protected function createSyncClient(array $conf, array $setting = [])
    {
        $conn = new Client(SWOOLE_TCP | SWOOLE_KEEP);
        $conn->set($setting);
        $conn->connect($conf['host'], $conf['port'], 3);

        return $conn;
    }

    protected function createAsyncClient(array $conf, array $setting = [])
    {
        $conn = new Client(SWOOLE_TCP | SWOOLE_ASYNC);
        $conn->set($setting);
        $conn->on('connect', function (Client $cli) {

        });

        $conn->on('receive', function (Client $cli, $data) {
            $cli->send(str_repeat('A', 1024 * 1024 * 4) . "\n");
        });

        $conn->on('error', function (Client $cli) {
            echo "error\n";
        });
        $conn->on('close', function (Client $cli) {
            echo "Connection close\n";
        });
        $conn->on('bufferEmpty', function (Client $cli) {
            $cli->close();
        });
        $conn->connect($conf['host'], $conf['port'], 3);

        return $conn;
    }

    /**
     * @return array
     */
    public function getSetting(): array
    {
        return $this->setting;
    }

    /**
     * @param array $setting
     */
    public function setSetting(array $setting)
    {
        $this->setting = $setting;
    }

    /**
     * @return array
     */
    public function getNames(): array
    {
        if (null === $this->names) {
            $this->names = array_keys($this->servers);
        }

        return $this->names;
    }

    /**
     * @return array
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * @param array $servers
     */
    public function setServers(array $servers)
    {
        $this->servers = $servers;
    }
}
