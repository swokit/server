<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/10
 * Time: 上午10:43
 */

namespace Inhere\Server\Rpc;

/**
 * Class RpcProtocol
 * @package Inhere\Server\Rpc
 */
class RpcProtocol
{
    const KEY_SERVICE = 'Rpc-Service';
    const KEY_META = 'Rpc-Meta';

    const KEY_PARAMS = 'Rpc-Params';
    const KEY_RESULT = 'Rpc-Result';

    /**
     * @param string $service
     * @param string $params
     * @param string $metas
     * @return string
     */
    public function buildRequest($service, $metas, $params)
    {
        return sprintf(
            "%s: %s\r\n%s: %s\r\n%s: %s\r\n\r\n",
            self::KEY_SERVICE, $service, self::KEY_META, $metas, self::KEY_PARAMS, $params
        );
    }

    /**
     * @param string $service
     * @param string $meta
     * @param string $result
     * @return string
     */
    public function buildResponse($service, $meta, $result)
    {
        return sprintf(
            "%s: %s\r\n%s: %s\r\n%s: %s\r\n\r\n",
            self::KEY_SERVICE, $service, self::KEY_RESULT, $result, self::KEY_META, $meta
        );
    }

    public function parseRequest($buffer)
    {
        // 解析客户端发送过来的协议
        $hasService = preg_match('/Rpc-Service:\s(.*);\r\n/i', $buffer, $service);
        $hasParams = preg_match('/Rpc-Params:\s(.*);\r\n/i', $buffer, $params);
        $hasMeta = preg_match('/Rpc-Meta:\s(.*);\r\n/i', $buffer, $meta);
    }

    public function parseResponse($buffer)
    {
        // 解析服务端发送过来的协议
        $hasService = preg_match('/Rpc-Service:\s(.*);\r\n/i', $buffer, $service);
        $hasResult = preg_match('/Rpc-Result:\s(.*);\r\n/i', $buffer, $result);
        $hasMeta = preg_match('/Rpc-Meta:\s(.*);\r\n/i', $buffer, $meta);
    }
}
