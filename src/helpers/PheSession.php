<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-04
 * Time: 9:21
 */

namespace inhere\server\helpers;

use inhere\library\files\Directory;
use inhere\library\traits\LiteOptionsTrait;
use Swoole\Http\Response;
use Swoole\Http\Request;

/**
 * Class PhpSession
 * @package inhere\server\helpers
 */
class PhpSession
{
    use LiteOptionsTrait;

    /**
     * @var array
     */
    protected $options = [
        'start_session' => false,

        'save_path' => '', // app_session
        'name' => 'php_session', // app_session

        // 设置 cookie 的有效时间为 30 minute
        'cookie_lifetime' => 1800,
        'cookie_domain' => '',
        'cookie_path' => '/',
        'cookie_secure' => false,
        'cookie_httponly' => false,

        'cache_expire' => 1800,
    ];

    /**
     * startSession
     * @param Request $request
     * @param Response $response
     */
    public function start($request, $response)
    {
        // session
        $opts = $this->getOption('session');
        $name = $opts['name'] = $opts['name'] ?: session_name();

        if (($path = $opts['save_path']) && !is_dir($path)) {
            Directory::mkdir($path, 0775);
        }

        // start session
        session_name($name);
        //register_shutdown_function('session_write_close');
        session_start($opts);

        // Show::aList(session_get_cookie_params(), 'session cookie params');

        // if not exists, set it.
        if (!$sid = $request->cookie[$name] ?? '') {
            $sid = session_id();

            $response->cookie(
                $name, $sid, time() + $opts['cookie_lifetime'],
                $opts['cookie_path'], $opts['cookie_domain'], $opts['cookie_secure'], $opts['cookie_httponly']
            );
        }

        // $this->log("session name: {$name}, session id(cookie): {$sid}, session id: " . session_id());
    }

    public function close()
    {
        // if started session by `session_start()`, call `session_write_close()` is required.
        if ($this->getOption('start_session', false)) {
            session_write_close();
        }
    }
}