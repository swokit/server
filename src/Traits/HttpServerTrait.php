<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace Inhere\Server\Traits;


/*

http config:

```
'server' => [
    'host' => '0.0.0.0',
    'port' => '9662',

    // enable https(SSL)
    // 使用SSL必须在编译swoole时加入--enable-openssl选项 并且要在'swoole'中的配置相关信息
    'type' => 'http', // 'http' 'https'

    // 运行模式
    'mode' => 'process', // 'process' 'base'
],
'options' => [
    'ignoreFavicon' => true,
]
```
*/

/**
 * trait HttpServerTrait
 * @package Inhere\Server\Traits
 */
trait HttpServerTrait
{
}
