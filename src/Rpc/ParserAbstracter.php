<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/9
 * Time: 下午10:59
 */

namespace Inhere\Server\Rpc;

/**
 * Class ParserAbstracter
 * @package Inhere\Server\Rpc
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
        'e' => null, // mixed(the extra data)
        'r' => [
            't' => 0, // int(the request time)
            'id' => 0,
        ],
    ];

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @param mixed $data
     * @return array
     */
    public function validate($data)
    {
        if (\is_string($data)) {
            $data = [
                'd' => $data,
                't' => time(),
            ];
        }

        return array_merge(self::$defaultMap, $data);
    }

    public function buildRequest($service, $params, $meta)
    {
        return "Rpc-Service: $service\r\n" .
            "Rpc-Params: $params\r\n" .
            "Rpc-Meta: $meta\r\n\r\n";
    }

    public function buildResponse($service, $result, $meta)
    {
        return "Rpc-Service: $service\r\n" .
            "Rpc-Result: $result\r\n" .
            "Rpc-Meta: $meta\r\n\r\n";
    }


    /**
     * @return array
     */
    public static function getDefaultMap()
    {
        return self::$defaultMap;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }
}
