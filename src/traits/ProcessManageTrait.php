<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-07-20
 * Time: 15:20
 */

namespace inhere\server\traits;

use inhere\console\io\Input;
use inhere\console\utils\Show;
use inhere\server\helpers\AutoReloader;
use inhere\server\helpers\ProcessHelper;
use Swoole\Process;
use Swoole\Server;

/**
 * Class ProcessManageTrait
 * @package inhere\server\traits
 *
 * @property Server $server
 */
trait ProcessManageTrait
{
    /**
     * run
     * @throws \RuntimeException
     */
    public function run()
    {
        $input = new Input;
        $command = $input->getCommand();
        if (!$command || $input->sameOpt(['h', 'help'])) {
            return $this->showHelp($input->getScript());
        }

        $method = $command;

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        Show::error("Command: $command is not exists!");

        return $this->showHelp($input->getScript(), 0);
    }

    /**
     * @return $this
     */
    public function asDaemon()
    {
        $this->config['swoole']['daemonize'] = true;

        return $this;
    }

    protected function beforeStart()
    {

    }

    /**
     * Do start server
     */
    public function start()
    {
        if ($masterPid = $this->getMasterPid(true)) {
            return Show::error("The swoole server({$this->name}) have been started. (PID:{$masterPid})", true);
        }

        if (!$this->bootstrapped) {
            $this->bootstrap();
        }

        $this->beforeStart();

        self::$_statistics['start_time'] = microtime(1);

        $this->beforeServerStart();

        // 对于Server的配置即 $server->set() 中传入的参数设置，必须关闭/重启整个Server才可以重新加载
        $this->server->start();

        return 0;
    }

    /**
     * do Reload Workers
     * @param  boolean $onlyTaskWorker
     * @return int
     */
    public function reload($onlyTaskWorker = false)
    {
        if (!$masterPid = $this->getMasterPid(true)) {
            return Show::error("The swoole server({$this->name}) is not started.", true);
        }

        // SIGUSR1: 向管理进程发送信号，将平稳地重启所有worker进程; 也可在PHP代码中调用`$server->reload()`完成此操作
        $sig = SIGUSR1;

        // SIGUSR2: only reload task worker
        if ($onlyTaskWorker) {
            $sig = SIGUSR2;
            Show::notice('Will only reload task worker');
        }

        if (!posix_kill($masterPid, $sig)) {
            Show::error("The swoole server({$this->name}) worker process reload fail!", -1);
        }

        return Show::success("The swoole server({$this->name}) worker process reload success.", 0);
    }

    /**
     * Do restart server
     */
    public function restart()
    {
        if ($this->getMasterPid(true)) {
            $this->stop(false);
        }

        return $this->start();
    }

    /**
     * Do stop swoole server
     * @param  boolean $quit Quit, When stop success?
     * @return int
     */
    public function stop($quit = true)
    {
        if (!$masterPid = $this->getMasterPid(true)) {
            return Show::error("The swoole server({$this->name}) is not running.", true);
        }

        Show::write("The swoole server({$this->name}) process stopping ...");

        // do stop
        // 向主进程发送此信号(SIGTERM)服务器将安全终止；也可在PHP代码中调用`$server->shutdown()` 完成此操作
        $masterPid && posix_kill($masterPid, SIGTERM);

        $timeout = 5;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            $isRunning = ($masterPid > 0) && @posix_kill($masterPid, 0);

            if (!$isRunning) {
                break;
            }

            // have been timeout
            if ((time() - $startTime) >= $timeout) {
                Show::error("The swoole server({$this->name}) process stop fail!", -1);
            }

            usleep(10000);
            continue;
        }

        if ($this->pidFile && file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        // stop success
        return Show::success("The swoole server({$this->name}) process stop success.", $quit);
    }

    /**
     * 使当前worker进程停止运行，并立即触发onWorkerStop回调函数
     * @param null|int $workerId
     * @return bool
     */
    public function stopWorker($workerId = null)
    {
        if ($this->server) {
            return $this->server->stop($workerId);
        }

        return false;
    }

    /**
     * create code hot reload worker
     * @see https://wiki.swoole.com/wiki/page/390.html
     * @param  Server $server
     * @return bool
     * @throws \RuntimeException
     */
    protected function createHotReloader(Server $server)
    {
        $mgr = $this;
        $reload = $this->config['auto_reload'];

        if (!$reload || !function_exists('inotify_init')) {
            return false;
        }

        $options = [
            'dirs' => $reload,
            'masterPid' => $server->master_pid
        ];

        // 创建用户自定义的工作进程worker
        $this->reloadWorker = new Process(function (Process $process) use ($options, $mgr) {
            ProcessHelper::setTitle("swoole: reloader ({$mgr->name})");
            $kit = new AutoReloader($options['masterPid']);

            $onlyReloadTask = isset($options['only_reload_task']) ? (bool)$options['only_reload_task'] : false;
            $dirs = array_map('trim', explode(',', $options['dirs']));

            $mgr->log("The reloader worker process success started. (PID: {$process->pid}, Watched: <info>{$options['dirs']}</info>)");

            $kit
                ->addWatches($dirs, $this->config['root_path'])
                ->setReloadHandler(function ($pid) use ($mgr, $onlyReloadTask) {
                    $mgr->log("Begin reload workers process. (Master PID: {$pid})");
                    $mgr->server->reload($onlyReloadTask);
                    // $mgr->doReloadWorkers($pid, $onlyReloadTask);
                });

            //Interact::section('Watched Directory', $kit->getWatchedDirs());

            $kit->run();

            // while (true) {
            //     $msg = $process->read();
            //     // 重启所有worker进程
            //     if ( $msg === 'reload' ) {
            //         $onlyReloadTaskWorker = false;

            //         $server->reload($onlyReloadTaskWorker);
            //     } else {
            //         foreach($server->connections as $conn) {
            //             $server->send($conn, $msg);
            //         }
            //     }
            // }
        });

        // addProcess添加的用户进程中无法使用task投递任务，请使用 $server->sendMessage() 接口与工作进程通信
        $server->addProcess($this->reloadWorker);

        return true;
    }

    /**
     * @param bool $checkRunning
     * @return int
     */
    public function getMasterPid($checkRunning = false)
    {
        return ProcessHelper::getPidFromFile($this->pidFile, $checkRunning);
    }

    /**
     * @param string $scriptName
     * @param bool $quit
     * @return bool
     */
    public function showHelp($scriptName, $quit = false)
    {
        // 'bin/test_server.php'
        // $scriptName = $input->getScriptName();

        if (strpos($scriptName, '.') && 'php' === pathinfo($scriptName, PATHINFO_EXTENSION)) {
            $scriptName = 'php ' . $scriptName;
        }

        $version = static::VERSION;
        $upTime = static::UPDATE_TIME;
        $supportCommands = ['start', 'reload', 'restart', 'stop', 'info', 'status', 'help'];
        $commandString = implode('|', $supportCommands);

        Show::helpPanel([
            'description' => 'Swoole server manager tool, Version <comment>' . $version . '</comment> Update time ' . $upTime,
            'usage' => "$scriptName {{$commandString}} [-d ...]",
            'commands' => [
                'start' => 'Start the server',
                'stop' => 'Stop the server',
                'reload' => 'Reload all workers of the started server',
                'restart' => 'Stop the server, After start the server.',
                'info' => 'Show the server information for current project',
                'status' => 'Show the started server status information',
                'help' => 'Display this help message',
            ],
            'options' => [
                '-d' => 'Run the server on daemonize(on start/restart).',
                '--task' => 'Only reload task worker, when reload server',
                '-n, --worker-number' => 'started worker number',
                '-h, --help' => 'Display this help message',
            ],
            'examples' => [
                "<info>$scriptName start -d</info> Start server on daemonize mode.",
                "<info>$scriptName reload --task</info> Start server on daemonize mode."
            ],
        ], $quit);

        return true;
    }
}
