<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-08
 * Time: 17:45
 */

namespace inhere\server\rpc;

/**
 * Class JsonParser
 * @package inhere\server\rpc
 */
class JsonParser implements ParserInterface
{

    /**
     * @param string $data
     * @return mixed
     */
    public function decode($data)
    {
        return json_decode($data);
    }

    /**
     * @param mixed $data
     * @return string
     */
    public function encode($data)
    {
        return json_encode($data);
    }
}