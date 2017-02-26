<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-02-24
 * Time: 16:04
 */

namespace inhere\server\handlers;

use Swoole\Http\Response as SwResponse;
use Swoole\Http\Request as SwRequest;

/**
 * Class HttpServerHandler
 * @package inhere\server\handlers
 *
 */
class HttpServerHandler extends AbstractServerHandler
{
    /**
     * 处理http请求
     * @param  SwRequest  $request
     * @param  SwResponse $response
     */
    public function onRequest(SwRequest $request, SwResponse $response)
    {
        // var_dump($request->get, $request->post);
        $response->header("Content-Type", "text/html; charset=utf-8");
        $response->end("<h1>Hello Swoole. #".rand(1000, 9999)."</h1>\n");
    }
}
