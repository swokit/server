<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\extend;

use inhere\console\utils\Show;
use inhere\library\traits\OptionsTrait;
use inhere\server\AbstractExtendServer;

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

    'event_handler' => \inhere\server\handlers\HttpServerHandler::class,
    'event_list' => [ '' ]
],
'options' => [
    // static asset handle
    'static_setting' => [
        // 'url_match' => 'assets dir',
        '/assets'  => 'public/assets',
        '/uploads' => 'public/uploads'
    ],
]
```
*/

/**
 * Class HttpServerHandler
 * @package inhere\server\handlers
 *
 */
class HttpServer extends AbstractExtendServer
{
    use HttpServerTrait;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $options = [])
    {
        $this->options = $this->defaultOptions;

        parent::__construct($options);
    }

    public function beforeStart()
    {
        Show::mList($this->options);
    }

}
