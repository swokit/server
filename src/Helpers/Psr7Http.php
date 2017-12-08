<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-08-29
 * Time: 15:25
 */

namespace Inhere\Server\Helpers;

use Inhere\Http\ServerRequest;
use Inhere\Http\Response;
use Inhere\Http\UploadedFile;
use Inhere\Http\Uri;

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwResponse;
use Swoole\Http\Request as SwRequest;

/**
 * Class Psr7Http
 * @package Sws\Components
 */
class Psr7Http
{
    /**
     * @param SwRequest $swRequest
     * @return ServerRequest
     */
    public static function createServerRequest(SwRequest $swRequest)
    {
        $uri = $swRequest->server['request_uri'];
        $method = $swRequest->server['request_method'];
        $request = new ServerRequest($method, Uri::createFromString($uri));

        // add attribute data
        $request->setAttribute('_fd', $swRequest->fd);
        $request->setAttribute('_swReq', $swRequest);

        // GET data
        if (isset($swRequest->get)) {
            $request->setParsedBody($swRequest->get);
        }

        // POST data
        if (isset($swRequest->post)) {
            $request->setParsedBody($swRequest->post);
        }

        // cookie data
        if (isset($swRequest->cookie)) {
            $request->setCookies($swRequest->cookie);
        }

        // FILES data
        if (isset($swRequest->files)) {
            $request->setUploadedFiles(UploadedFile::parseUploadedFiles($swRequest->files));
        }

        // SERVER data
        $serverData = array_change_key_case($swRequest->server, CASE_UPPER);

        if ($swRequest->header) {
            // headers
            $request->setHeaders($swRequest->header);

            // 将 HTTP 头信息赋值给 $serverData
            foreach ((array)$swRequest->header as $key => $value) {
                $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                $serverData[$_key] = $value;
            }
        }

        $request->setServerParams($serverData);

        return $request;
    }

    /**
     * @param null|array $headers
     * @return Response
     */
    public static function createResponse(array $headers = null)
    {
        // $headers = ['Content-Type' => 'text/html; charset=' . \Sws::get('config')->get('charset', 'UTF-8')];

        return new Response(200, $headers);
    }

    /**
     * @param Response|ResponseInterface $response
     * @param SwResponse $swResponse
     * @param bool $send
     * @return SwResponse|mixed
     */
    public static function respond(Response $response, SwResponse $swResponse, $send = true)
    {
        // set http status
        $swResponse->status($response->getStatus());

        // set headers
        foreach ($response->getHeadersObject()->getLines() as $name => $value) {
            $swResponse->header($name, $value);
        }

        // set cookies
        foreach ($response->getCookies()->toHeaders() as $value) {
            $swResponse->header('Set-Cookie', $value);
        }

        // write content
        if ($body = (string)$response->getBody()) {
            $swResponse->write($body);
        }

        // send response to client
        if ($send) {
            return $swResponse->end();
        }

        return $swResponse;
    }
}
