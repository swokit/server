<?php
namespace Swoole;

/**
 * @since 1.9.5
 */
class Redis
{


    /**
     * @return mixed
     */
    public function __construct(){}

    /**
     * @return mixed
     */
    public function __destruct(){}

    /**
     * @param $event_name[required]
     * @param $callback[required]
     * @return mixed
     */
    public function on($event_name, $callback){}

    /**
     * @param $host[required]
     * @param $port[required]
     * @param $callback[required]
     * @return mixed
     */
    public function connect($host, $port, $callback){}

    /**
     * @return mixed
     */
    public function close(){}

    /**
     * @param $command[required]
     * @param $params[required]
     * @return mixed
     */
    public function __call($command, $params){}


}
