<?php
namespace Swoole;

/**
 * @since 2.0.8
 */
class Table
{
    const TYPE_INT = 1;
    const TYPE_STRING = 7;
    const TYPE_FLOAT = 6;


    /**
     * @param $table_size[required]
     * @return mixed
     */
    public function __construct($table_size){}

    /**
     * @param $name[required]
     * @param $type[required]
     * @param $size[optional]
     * @return mixed
     */
    public function column($name, $type, $size=null){}

    /**
     * @return mixed
     */
    public function create(){}

    /**
     * @return mixed
     */
    public function destroy(){}

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function set($key, $value){}

    /**
     * @param $key[required]
     * @param $field[optional]
     * @return mixed
     */
    public function get($key, $field=null){}

    /**
     * @return mixed
     */
    public function count(){}

    /**
     * @param $key[required]
     * @return mixed
     */
    public function del($key){}

    /**
     * @param $key[required]
     * @param $field[optional]
     * @return mixed
     */
    public function exist($key, $field=null){}

    /**
     * @param $key[required]
     * @param $column[required]
     * @param $incrby[optional]
     * @return mixed
     */
    public function incr($key, $column, $incrby=null){}

    /**
     * @param $key[required]
     * @param $column[required]
     * @param $decrby[optional]
     * @return mixed
     */
    public function decr($key, $column, $decrby=null){}


}
