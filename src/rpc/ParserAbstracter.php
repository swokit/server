<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/9
 * Time: 下午10:59
 */

namespace inhere\server\rpc;

/**
 * Class ParserAbstracter
 * @package inhere\server\rpc
 */
abstract class ParserAbstracter implements ParserInterface
{
    /**
     * data format
     * @var array
     */
    private static $defaultMap = [
        // string(the service name)
        // e.g
        // 'user'      only the service name
        // 'user/info' info - the service class method name
        's' => '',
        'p' => null, // mixed(the request params),
        't' => 0, // int(the request time)
        'e' => null, // mixed(the extra data)
    ];

    /**
     * @param mixed $data
     * @return array
     */
    public function validate($data)
    {
        if (is_string($data)) {
            $data = [
                'd' => $data,
                't' => time(),
            ];
        }

        return array_merge(self::$defaultMap, $data);
    }

    /**
     * @return array
     */
    public static function getDefaultMap()
    {
        return self::$defaultMap;
    }
}
