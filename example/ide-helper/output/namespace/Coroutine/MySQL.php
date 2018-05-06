<?php
namespace Swoole\Coroutine;

/**
 * @since 2.0.8
 *
 * @property int affected_rows
 * @property int|string insert_id
 * @property bool connected
 *
 * @property string connect_error
 * @property int connect_errno
 */
class MySQL
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
     * @param array $conf
     * @return mixed
     */
    public function connect(array $conf){}

    /**
     * @param string $sql
     * @param float $timeout
     * @return array|bool
     */
    public function query(string $sql, double $timeout = 0){}

    /**
     * @return mixed
     */
    public function recv(){}

    /**
     * @return mixed
     */
    public function setDefer(){}

    /**
     * @return mixed
     */
    public function getDefer(){}

    /**
     * @return mixed
     */
    public function close(){}


}
