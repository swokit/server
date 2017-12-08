<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace Inhere\Server\Servers;

use Inhere\Console\Utils\Show;
use Inhere\Library\Traits\OptionsTrait;
use Inhere\Server\Components\StaticResourceProcessor;
use Inhere\Server\HttpServerInterface;
use Inhere\Server\Server;
use Inhere\Server\Traits\HttpServerTrait;

/*

http config:

```
'main_server' => [
    'host' => '0.0.0.0',
    'port' => '9662',

    // enable https(SSL)
    // 使用SSL必须在编译swoole时加入--enable-openssl选项 并且要在'swoole'中的配置相关信息(@see AServerManager::defaultConfig())
    'type' => 'http', // 'http' 'https'

    // 运行模式
    // SWOOLE_PROCESS 业务代码在Worker进程中执行 SWOOLE_BASE 业务代码在Reactor进程中直接执行
    'mode' => 'process', // 'process' 'base'

    'event_handler' => \Inhere\Server\handlers\HttpServerHandler::class,
    'event_list' => [ '' ]
],
'options' => [

]
```
*/

/**
 * Class HttpServerHandler
 * @package Inhere\Server\handlers
 */
abstract class HttpServer extends Server
{
    use HttpServerTrait, OptionsTrait;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [], array $options = [])
    {
        parent::__construct($config);

        if ($options1 = $this->getValue('options')) {
            $this->setOptions($options1);
        }

        if ($options) {
            $this->setOptions($options);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function beforeServerStart()
    {
        if ($this->getOption('enableStatic')) {
            $opts = $this->getOption('staticSettings');
            $this->staticAccessHandler = new StaticResourceProcessor($opts);
        }
    }

    public function info()
    {
        parent::info();

        Show::title('some options for the http server');
        Show::mList($this->options);
    }

}
