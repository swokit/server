<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-07-20
 * Time: 15:20
 */

namespace Inhere\Server\Traits;

use inhere\console\io\Input;
use inhere\console\utils\Show;
use Inhere\Server\Helpers\AutoReloader;
use Inhere\Server\Helpers\ProcessHelper;
use Inhere\Server\Helpers\ServerHelper;
use Swoole\Process;
use Swoole\Server;

/**
 * Class ProcessManageTrait
 * @package Inhere\Server\Traits
 * @property Server $server
 * @property Input $input
 */
trait ProcessManageTrait
{
    /*******************************************************************************
     * server process
     ******************************************************************************/

    protected function beforeRun()
    {
        // something ...
    }

    /**
     * run
     * @throws \RuntimeException
     */
    public function run()
    {
        $input = $this->input;
        $command = $input->getCommand();

        if (!$command || $input->sameOpt(['h', 'help'])) {
            return $this->showHelp($input->getScript());
        }

        if (in_array($command, ['start', 'stop', 'restart', 'reload'], true)) {
            ServerHelper::checkRuntimeEnv();
        }

        $this->fire(self::ON_BEFORE_RUN, [$this]);
        $this->beforeRun();

        $method = $command;

        switch ($command) {
            case 'start':
                $yes = $input->getSameOpt(['d', 'daemon']);
                $this->start($yes);
                break;
            case 'restart':
                $yes = $input->getSameOpt(['d', 'daemon']);
                $this->restart($yes);
                break;
            case 'reload':
                $yes = $input->getSameOpt(['t', 'task']);
                $this->reload($yes);
                break;
            default:
                if (method_exists($this, $method)) {
                    $this->$method();
                } else {
                    Show::error("Command: $command is not exists!");
                    $this->showHelp($input->getScript(), 0);
                }
                break;
        }

        return 0;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function asDaemon($value = true)
    {
        $this->daemon = (bool)$value;
        $this->config['swoole']['daemonize'] = (bool)$value;

        return $this;
    }

    /**
     * Do start server
     * @param null|bool $daemon
     */
    public function start($daemon = null)
    {
        if ($pid = $this->getPidFromFile(true)) {
            Show::error("The swoole server({$this->name}) have been started. (PID:{$pid})", -1);
        }

        if (null !== $daemon) {
            $this->asDaemon($daemon);
        }

        if (!$this->bootstrapped) {
            $this->bootstrap();
        }

        self::$_statistics['start_time'] = microtime(1);

        // display some messages
        $this->showStartStatus();

        $this->fire(self::ON_SERVER_START, [$this]);
        $this->beforeServerStart();

        // 对于Server的配置即 $server->set() 中传入的参数设置，必须关闭/重启整个Server才可以重新加载
        $this->server->start();
    }

    protected function showStartStatus()
    {
        // output a message before start
        if ($this->daemon) {
            Show::write("You can use <info>stop</info> command to stop server.\n");
        } else {
            Show::write("Press <info>Ctrl-C</info> to quit.\n");
        }
    }

    /**
     * before Server Start
     */
    protected function beforeServerStart()
    {
    }

    /**
     * do Reload Workers
     * @param  boolean $onlyTaskWorker
     * @return int
     */
    public function reload($onlyTaskWorker = false)
    {
        if (!$masterPid = $this->getPidFromFile(true)) {
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
     * @param null|bool $daemon
     */
    public function restart($daemon = null)
    {
        if ($this->getPidFromFile(true)) {
            $this->stop(false);
        }

        $this->start($daemon);
    }

    /**
     * Do stop swoole server
     * @param  boolean $quit Quit, When stop success?
     * @return int
     */
    public function stop($quit = true)
    {
        if (!$masterPid = $this->getPidFromFile(true)) {
            return Show::error("The swoole server({$this->name}) is not running.", true);
        }

        Show::write("The swoole server({$this->name}:{$masterPid}) process stopping ", false);

        // do stop
        // 向主进程发送此信号(SIGTERM)服务器将安全终止；也可在PHP代码中调用`$server->shutdown()` 完成此操作
        $masterPid && posix_kill($masterPid, SIGTERM);

        $timeout = 10;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            Show::write('.', false);

            if (!@posix_kill($masterPid, 0)) {
                break;
            }

            // have been timeout
            if ((time() - $startTime) >= $timeout) {
                Show::error("The swoole server({$this->name}) process stop fail!", -1);
            }

            usleep(300000);
        }

        $this->removePidFile();

        // stop success
        return Show::write(" <success>Stopped</success>\nThe swoole server({$this->name}) process stop success", $quit);
    }

    public function help()
    {
        $this->showHelp($this->input->getScript());
    }

    public function info()
    {
        $this->showInformation();
    }

    public function check()
    {
        Show::table([
            ['Php version is gt 7', 'Yes'],
            ['The Swoole is installed', 'Yes'],
        ], 'check result', [
            'tHead' => ['condition', 'result'],
        ]);

        // $this->showInformation();
    }

    public function status()
    {
        $this->showRuntimeStatus();
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
     * @return bool
     * @throws \RuntimeException
     */
    protected function createHotReloader()
    {
        $reload = $this->config['auto_reload'];

        if (!$reload || !function_exists('inotify_init')) {
            return false;
        }

        $mgr = $this;
        $options = [
            'dirs' => $reload,
            // 'masterPid' => $this->server->master_pid
        ];

        // 创建用户自定义的工作进程worker
        $this->reloadWorker = new Process(function (Process $process) use ($options, $mgr) {
            ProcessHelper::setTitle("swoole: reloader ({$mgr->name})");

            $svrPid = $mgr->server->master_pid;
            $onlyReloadTask = isset($options['only_reload_task']) ? (bool)$options['only_reload_task'] : false;
            $dirs = array_map('trim', explode(',', $options['dirs']));

            $mgr->log("The reloader worker process success started. (PID:{$process->pid}, SVR_PID:$svrPid, Watched:<info>{$options['dirs']}</info>)");

            $kit = new AutoReloader($svrPid);
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
        $this->server->addProcess($this->reloadWorker);

        return true;
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

        Show::write(<<<TAG
<info>       _____
      / ___/      _______
      \__ \ | /| / / ___/
     ___/ / |/ |/ (__  )
    /____/|__/|__/____/ </info>powered by php
    
TAG
        );
        Show::helpPanel([
            'description' => 'Swoole server manager tool, Version <comment>' . $version . '</comment> Update time ' . $upTime,
            'usage' => "$scriptName {start|reload|restart|stop|...} [-d ...]",
            'commands' => [
                'start' => 'Start the server',
                'stop' => 'Stop the server',
                'reload' => 'Reload all workers of the started server',
                'restart' => 'Stop the server, After start the server.',
                'check' => 'Check current system information.',
                'info' => 'Show the server information for current project',
                'status' => 'Show the started server status information',
                'help' => 'Display this help message',
                'version' => 'Display this version message',
            ],
            'options' => [
                '-t, --task' => 'Only reload task worker, when reload server',
                '-d, --daemon' => 'Run the server on daemonize(on start/restart).',
                '-n, --worker-number' => 'started worker number',
                '--task-number' => 'started task worker number',
                '-h, --help' => 'Display this help message',
            ],
            'examples' => [
                "<info>$scriptName start -d</info> Start server on daemonize mode.",
                "<info>$scriptName reload --task</info> Start server on daemonize mode."
            ],
        ], $quit);

        return true;
    }

    /**
     * @param bool $checkRunning
     * @return int
     */
    public function getPidFromFile($checkRunning = false)
    {
        return ProcessHelper::getPidFromFile($this->pidFile, $checkRunning);
    }

    /**
     * @param (int) $masterPid
     * @return bool|int
     */
    protected function createPidFile($masterPid)
    {
        if ($this->pidFile) {
            return file_put_contents($this->pidFile, $masterPid);
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function removePidFile()
    {
        if ($this->pidFile && file_exists($this->pidFile)) {
            return unlink($this->pidFile);
        }

        return false;
    }
}
