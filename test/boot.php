<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/28
 * Time: 下午10:36
 */

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');

spl_autoload_register(function($class)
{
    $swokitDir = dirname(__DIR__, 2);
    $inhereDir = dirname($swokitDir) . '/inhere';
    $vendorDir = dirname($swokitDir);

    $map = [
        'Psr\\Log\\' => $vendorDir . '/psr/log/Psr/Log',
        'Inhere\\Console\\' => $inhereDir . '/console/src',
        'Toolkit\\Cli\\' => $vendorDir . '/toolkit/toolkit/libs/cli-utils/src',
        'Toolkit\\Sys\\' => $vendorDir . '/toolkit/toolkit/libs/sys-utils/src',
        'Toolkit\\PhpUtil\\' => $vendorDir . '/toolkit/toolkit/libs/php-utils/src',
        'SwoKit\\Util\\' => $swokitDir . '/utils/src',
        'SwoKit\\Server\\' => dirname(__DIR__) . '/src',
    ];

    foreach ($map as $np => $dir) {
        if (0 === strpos($class, $np)) {
            $path = str_replace('\\', '/', substr($class, strlen($np)));
            $file = $dir . "/{$path}.php";

            if (is_file($file)) {
                include_file($file);
            }
        }
    }
});

include dirname(__DIR__) . '/src/Helper/functions.php';

function include_file($file) {
    include $file;
}
