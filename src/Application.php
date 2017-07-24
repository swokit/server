<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-07-20
 * Time: 15:20
 */

namespace inhere\server;

use inhere\console\io\Input;
use inhere\console\io\Output;
use inhere\console\traits\InputOutputTrait;
use inhere\console\utils\Show;

/**
 * Class Application
 * @package inhere\server
 */
class Application
{
    use InputOutputTrait;

    private $serverClass;

    public function __construct($serverClass = null, Input $input = null, Output $output = null)
    {
        $this->serverClass = $serverClass ?: SuiteServer::class;
        $this->input = $input ?: new Input();
        $this->output = $output ?: new Output();

        $this->init();
    }

    protected function init()
    {}

    protected function makeServer()
    {
        return new SuiteServer();
    }

    public function run()
    {
        $command = $this->input->getCommand();
        if (!$command || $this->input->sameOpt(['h', 'help'])) {
            return $this->showHelp();
        }

        $method = "{$command}Command";

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        $this->output->error("Command: $command not exists!");

        return $this->showHelp();
    }

    public function startCommand()
    {
        # code...
    }

    public function restartCommand()
    {
        # code...
    }

    public function stopCommand()
    {
        # code...
    }

    public function reloadCommand()
    {
        # code...
    }

    public function infoCommand()
    {
        # code...
    }

    public function statusCommand()
    {
        # code...
    }

    public function helpCommand()
    {
        $this->showHelp();
    }

    public function showHelp($quit = false)
    {
        $scriptName = $this->input->getScriptName(); // 'bin/test_server.php'

        if (strpos($scriptName, '.') && 'php' === pathinfo($scriptName, PATHINFO_EXTENSION)) {
            $scriptName = 'php ' . $scriptName;
        }

        $version = AbstractServer::VERSION;
        $upTime = AbstractServer::UPDATE_TIME;
        $supportCommands = ['start', 'reload', 'restart', 'stop', 'info', 'status', 'help'];
        $commandString = implode('|', $supportCommands);

        Show::helpPanel([
            'description' => 'Swoole server manager tool, Version <comment>' . $version . '</comment>. Update time ' . $upTime,
            'usage' => "$scriptName {{$commandString}} [-d ...]",
            'commands' => [
                'start' => 'Start the server',
                'reload' => 'Reload all workers of the started server',
                'restart' => 'Stop the server, After start the server.',
                'stop' => 'Stop the server',
                'info' => 'Show the server information for current project',
                'status' => 'Show the started server status information',
                'help' => 'Display this help message',
            ],
            'options' => [
                '-d' => 'Run the server on daemonize.',
                '--task' => 'Only reload task worker, when reload server',
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
