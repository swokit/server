<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-25
 * Time: 10:14
 */

namespace SwoKit\Server;

use Swoole\Redis\Server;

/**
 * Class TaskServer - use the swoole redis server.
 * @package App\Server
 * @property \Swoole\Redis\Server $server
 * @link https://wiki.swoole.com/wiki/page/p-redis_server.html
 */
class TaskServer extends AbstractServer
{
    /**
     * @var string
     */
    private $dataFile;

    /**
     * @var array
     * [name => callback]
     */
    private $commands = [];

    /**
     * TaskServer constructor.
     * @param array $config
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config)
    {
        self::$required[] = 'dataFile';

        $this->config['dataFile'] = '/tmp/task.data';

        parent::__construct($config);
    }

    /**
     * @param array $config
     * @throws \InvalidArgumentException
     */
    protected function init(array $config)
    {
        parent::init($config);

        $this->serverSettings['type'] = self::PROTOCOL_RDS;
        $this->dataFile = $this->config['dataFile'];
    }

    /**
     * create Main Server
     * @inheritdoc
     */
    public function afterCreateServer()
    {
        // load task data
        if (\is_file($this->dataFile)) {
            $this->server->data = \unserialize(\file_get_contents($this->dataFile), ['allowed_classes' => false]);
        } else {
            $this->server->data = [];
        }

        // $this->initCommands($this->server);
        $this->registerCommands($this->server);
    }

    /**
     * @param string $command
     * @param $callback
     * @return TaskServer
     */
    public function addCommand(string $command, $callback): self
    {
        $this->commands[$command] = $callback;

        return $this;
    }

    /**
     * @param Server $server
     */
    private function registerCommands(Server $server)
    {
        foreach ($this->commands as $command => $callback) {
            $server->setHandler($command, function ($fd, $data) use ($server, $callback) {
                $callback($server, $fd, $data);
            });
        }

        $this->log('register some commands to the swoole redis server', [], 'debug');
    }

    /**
     * @param Server $server
     */
    public function onStart(Server $server)
    {
        parent::onStart($server);

        // $this->registerEureka();
    }

    /**
     * @param Server $server
     * @param int $workerId
     */
    public function onWorkerStart(Server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);

        // $this->loadTimerTasks($server);
    }

    /**
     * @param Server $server
     */
    public function onShutdown(Server $server)
    {
        parent::onShutdown($server);

        // $this->cancelEureka();
    }

    public function onTask(Server $server, $taskId, $fromId, $data)
    {
        parent::onTask($server, $taskId, $fromId, $data);

        $server->finish('OK');
    }

    public function onFinish(Server $server, $taskId, $data)
    {
        parent::onFinish($server, $taskId, $data);
    }
}
