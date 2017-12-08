<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-07-20
 * Time: 15:20
 */

namespace Inhere\Server\Traits;

use Inhere\Console\IO\Input;
use Inhere\Console\Utils\Show;
use Inhere\Server\Helpers\ProcessHelper;
use Inhere\Server\Helpers\ServerHelper;
use Swoole\Coroutine;
use Swoole\Server;

/**
 * Class ServerManageTrait
 * @package Inhere\Server\Traits
 * @property Server $server
 * @property Input $input
 */
trait ServerManageTrait
{
    /*******************************************************************************
     * server commands
     ******************************************************************************/

    protected function beforeRun()
    {
        // something ...
    }

    /**
     * run
     * @throws \RuntimeException
     * @throws \Throwable
     */
    public function run()
    {
        $input = $this->input;
        $command = $input->getCommand();

        if (!$command || $input->sameOpt(['h', 'help'])) {
            return $this->showHelp($input->getScript());
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
    public function asDaemon($value = null)
    {
        if (null !== $value) {
            $this->daemon = (bool)$value;
            $this->config['swoole']['daemonize'] = (bool)$value;
        }

        return $this;
    }

    /**
     * Do start server
     * @param null|bool $daemon
     * @throws \Throwable
     */
    public function start($daemon = null)
    {
        ServerHelper::checkRuntimeEnv();

        if ($pid = $this->getPidFromFile(true)) {
            Show::error("The swoole server({$this->name}) have been started. (PID:{$pid})", -1);
        }

        if (null !== $daemon) {
            $this->asDaemon($daemon);
        }

        try {
            $this->bootstrap();

            self::$_stats['start_time'] = microtime(1);

            // display some messages
            $this->showStartStatus();

            $this->fire(self::ON_SERVER_START, [$this]);
            $this->beforeServerStart();

            // start server
            $this->server->start();
        } catch (\Throwable $e) {
            $this->handleException($e, __METHOD__);
        }
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

    public function version()
    {
        Show::write(sprintf('Swoole server manager tool, Version <comment>%s</comment> Update time %s', self::VERSION, self::UPDATE_TIME));
    }

    public function check()
    {
        $yes = '<info>√</info>';
        $no = '<danger>X</danger>';
        $info = [
            'The Php version is gt 7.1' => version_compare(PHP_VERSION, '7.1') ? $yes : $no,
            'The Swoole is installed' => class_exists(Server::class, false) ? $yes : $no,
            'The Swoole version is gt 2' => version_compare(SWOOLE_VERSION, '2.0') ? $yes : $no,
            'The Swoole Coroutine is enabled' => class_exists(Coroutine::class, false) ? $yes : $no,
        ];

        Show::aList($info, 'the env check result', [
            'keyStyle' => '',
            'sepChar' => ' | ',
            'ucFirst' => false,
        ]);
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
