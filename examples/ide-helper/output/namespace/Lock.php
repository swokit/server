<?php
namespace Swoole;

/**
 * @since 1.9.5
 */
class Lock
{


    /**
     * @param $type[optional]
     * @param $filename[optional]
     * @return mixed
     */
    public function __construct($type=null, $filename=null){}

    /**
     * @return mixed
     */
    public function __destruct(){}

    /**
     * @return mixed
     */
    public function lock(){}

    /**
     * @return mixed
     */
    public function trylock(){}

    /**
     * @return mixed
     */
    public function lock_read(){}

    /**
     * @return mixed
     */
    public function trylock_read(){}

    /**
     * @return mixed
     */
    public function unlock(){}


}
