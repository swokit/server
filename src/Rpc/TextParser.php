<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-08
 * Time: 17:45
 */

namespace Inhere\Server\Rpc;

/**
 * Class TextParser
 * @package Inhere\Server\Rpc
 */
class TextParser extends ParserAbstracter
{
    /**
     * TextParser constructor.
     */
    public function __construct()
    {
        $this->setName('json');
    }

    /**
     * @param string $data
     * @return mixed
     */
    public function decode($data)
    {
        return $this->validate(trim($data));
    }

    /**
     * @param mixed $data
     * @return string
     */
    public function encode($data)
    {
        return $data;
    }
}
