<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-08
 * Time: 17:45
 */

namespace Inhere\Server\Rpc;

use Inhere\Exceptions\DataParseException;

/**
 * Class JsonParser
 * @package Inhere\Server\Rpc
 */
class JsonParser extends ParserAbstracter
{
    /**
     * @var bool
     */
    private $toArray;

    public function __construct($toArray = true)
    {
        $this->name = 'json';
        $this->toArray = (bool)$toArray;
    }

    /**
     * @param string $string
     * @return mixed
     * @throws DataParseException
     */
    public function decode($string)
    {
        $data = json_decode(trim($string), $this->toArray);

        // parse error
        if (json_last_error() > 0) {
            throw new DataParseException('[Rpc] received data format is error! ERROR: ' . json_last_error_msg());
        }

        return $data;
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
