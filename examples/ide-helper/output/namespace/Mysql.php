<?php
namespace Swoole;

/**
 * @since 1.9.5
 */
class Mysql
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
     * @param $server_config[required]
     * @param $callback[required]
     * @return mixed
     */
    public function connect($server_config, $callback){}

    /**
     * @param $sql[required]
     * @param $callback[required]
     * @return mixed
     */
    public function query($sql, $callback){}

    /**
     * @return mixed
     */
    public function close(){}

    /**
     * @param $event_name[required]
     * @param $callback[required]
     * @return mixed
     */
    public function on($event_name, $callback){}


}
