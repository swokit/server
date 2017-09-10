<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/10
 * Time: 上午10:43
 */

namespace inhere\server\rpc;

/**
 * Class RpcProtocol
 * @package inhere\server\rpc
 */
class RpcProtocol
{
    public function buildRequest($service, $params, $meta)
    {
        return "RPC-Service: $service\r\n" .
            "RPC-Params: $params\r\n" .
            "RPC-Meta: $meta\r\n\r\n";
    }

    public function buildResponse($service, $result, $meta)
    {
        return "RPC-Service: $service\r\n" .
            "RPC-Result: $result\r\n" .
            "RPC-Meta: $meta\r\n\r\n";
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
