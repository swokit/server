<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018-01-25
 * Time: 10:14
 */

namespace Inhere\Server\BuiltIn;

use Swoole\Redis\Server;
use MyLib\PhpUtil\PhpHelper;

/**
 * Class TaskServer
 * @package App\Server
 * @property \Swoole\Redis\Server $server
 */
class TaskServer extends \Inhere\Server\Server
{
    /**
     * @var string
     */
    private $dataFile;

    /**
     * TaskServer constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config['dataFile'] = '/tmp/task.data';
        $this->required[] = 'dataFile';

        parent::__construct($config);
    }

    protected function init(array $config)
    {
        parent::init($config);

        $this->config['server']['type'] = 'rds';
        $this->dataFile = $this->config['dataFile'];
    }

    /**
     * create Main Server
     * @inheritdoc
     */
    public function afterCreateServer()
    {
        // load task data
        if (is_file($this->dataFile)) {
            $this->server->data = unserialize(file_get_contents($this->dataFile), ['allowed_classes' => false]);
        } else {
            $this->server->data = [];
        }

        $this->initCommands($this->server);
    }

    /**
     * init Command Handlers for task server
     * @param Server|\Swoole\Server $server
     */
    protected function initCommands(Server $server)
    {
        $this->log('register some commands to the swoole redis server', [], 'debug');

        $server->setHandler('get', function ($fd, $data) use ($server) {
            if (\count($data) === 0) {
                $server->send(
                    $fd,
                    Server::format(Server::ERROR, "ERR wrong number of arguments for 'GET' command")
                );
            }

            $key = $data[0];

            if (empty($server->data[$key])) {
                $server->send($fd, Server::format(Server::NIL));
            }

            // swoole 2 需要用 `$server->send()` 返回数据
            $server->send($fd, Server::format(Server::STRING, $server->data[$key]));
        });

        $server->setHandler('set', function ($fd, $data) use ($server) {
            if (\count($data) < 2) {
                $server->send(
                    $fd,
                    Server::format(Server::ERROR, "ERR wrong number of arguments for 'SET' command")
                );
            }

            $key = $data[0];
            $server->data[$key] = $data[1];

            $server->send($fd, Server::format(Server::STATUS, 'OK'));
        });

        $server->setHandler('sAdd', function ($fd, $data) use ($server) {
            $total = \count($data);

            if ($total < 2) {
                $server->send(
                    $fd,
                    Server::format(Server::ERROR, "ERR wrong number of arguments for 'sAdd' command")
                );
            }

            $key = $data[0];

            if (!isset($server->data[$key])) {
                $array[$key] = [];
            }

            $count = 0;
            for ($i = 1; $i < $total; $i++) {
                $value = $data[$i];
                if (!isset($server->data[$key][$value])) {
                    $server->data[$key][$value] = 1;
                    $count++;
                }
            }

            $server->send($fd, Server::format(Server::INT, $count));
        });

        $server->setHandler('sMembers', function ($fd, $data) use ($server) {
            if (\count($data) < 1) {
                $server->send(
                    $fd,
                    Server::format(Server::ERROR, "ERR wrong number of arguments for 'sMembers' command")
                );
            }

            $key = $data[0];

            if (!isset($server->data[$key])) {
                $this->log("want to get a not exists key '$key'", ['fd' => $fd], 'debug');

                $server->send($fd, Server::format(Server::NIL));
            }

            $server->send($fd, Server::format(Server::SET, array_keys($server->data[$key])));
        });

        $server->setHandler('hSet', function ($fd, $data) use ($server) {
            if (\count($data) < 3) {
                $server->send(
                    $fd,
                    Server::format(Server::ERROR, "ERR wrong number of arguments for 'hSet' command")
                );
            }

            $key = $data[0];

            if (!isset($server->data[$key])) {
                $array[$key] = array();
            }

            $field = $data[1];
            $value = $data[2];

            $count = !isset($server->data[$key][$field]) ? 1 : 0;
            $server->data[$key][$field] = $value;

            $server->send($fd, Server::format(Server::INT, $count));
        });

        $server->setHandler('hGetAll', function ($fd, $data) use ($server) {
            if (\count($data) < 1) {
                $server->send(
                    $fd,
                    Server::format(Server::ERROR, "ERR wrong number of arguments for 'hGetAll' command")
                );
            }

            $key = $data[0];

            if (!isset($server->data[$key])) {
                $this->log("want to get a not exists key '$key'", ['fd' => $fd], 'debug');

                $server->send($fd, Server::format(Server::NIL));
            }

            $server->send($fd, Server::format(Server::MAP, $server->data[$key]));
        });

        $server->setHandler('lPush', function ($fd, $data) use ($server) {
            $taskId = $server->task($data);

            if ($taskId === false) {
                $server->send($fd, Server::format(Server::ERROR));
            }

            $this->log('success add a task', ['taskId' => $taskId, 'fd' => $fd], 'debug');

            $server->send($fd, Server::format(Server::INT, $taskId));
        });
    }

    /**
     * @param Server $server
     */
    public function onMasterStart(Server $server)
    {
        parent::onMasterStart($server);

        $this->registerEureka();
    }

    /**
     * @param Server $server
     * @param int $workerId
     */
    public function onWorkerStart(Server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);

        $this->loadTimerTasks($server);
    }

    /**
     * @param Server $server
     */
    public function onMasterStop(Server $server)
    {
        parent::onMasterStop($server);

        $this->cancelEureka();
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

    private function registerEureka()
    {
        if (APP_ENV === 'dev') {
            $ok = false;
        } else {
            /** @see EurekaAPI::register() */
            $ok = container('eureka')->register();
        }

        $this->log('register service to Eureka server: ' . ($ok ? 'OK' : 'FAIL'));
    }

    private function cancelEureka()
    {
        if (APP_ENV === 'dev') {
            $ok = false;
        } else {
            /** @see EurekaAPI::cancel() */
            $ok = container('eureka')->cancel();
        }

        $this->log('cancel service from Eureka server: ' . ($ok ? 'OK' : 'FAIL'));
    }

    /**
     * @param Server $server
     */
    private function loadTimerTasks(Server $server)
    {
        // save task data to disk.
        PhpHelper::mkdir(\dirname($this->dataFile));
        $server->tick(10000, function () use ($server) {
            if ($server->data) {
                file_put_contents($this->dataFile, serialize($server->data));
                $this->log('save task data to dataFile', [], 'debug');
            }
        });

        // model ranking build task
        if (!$this->isTaskWorker()) {
            $server->tick(10000, function () {
                $this->log('do model ranking build task', [], 'debug');
            });

            if (APP_ENV !== 'dev') {
                // 5 s
                $server->tick(5000, function () {
                    /** @see EurekaAPI::heartbeat() */
                    $ok = container('eureka')->heartbeat();

                    $this->log('do heartbeat for Eureka: ' . ($ok ? 'OK' : 'FAIL'), [], 'debug');
                });
            }
        }
    }
}
