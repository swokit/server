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
    $inhereDir = dirname(__DIR__, 2);
    $vendorDir = dirname($inhereDir);

    $map = [
        // 'inhere\\library\examples\\' => __DIR__,
        'Psr\\Log\\' => $vendorDir . '/psr/log/Psr/Log',
        'inhere\\library\\' => $inhereDir . '/library/src',
        'inhere\\console\\' => $inhereDir . '/console/src',
        'inhere\\server\\' => dirname(__DIR__) . '/src',
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

function include_file($file) {
    include $file;
}
