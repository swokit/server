<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/9/10
 * Time: 上午9:06
 */

namespace Inhere\Server\Rpc;

/**
 * Class Request
 * @package Inhere\Server\Rpc
 */
class Request
{
    /**
     * @var string
     */
    private $service;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $params;

    /**
     * @var array
     * eg.
     * [
     *  'id' => 0,
     *  'time' => 0,
     *  'floatTime' => 0.0,
     *  'contentType' => 'json', // 'json' 'serialize' 'text'
     *  'acceptType' => 'json', // 'json' 'serialize' 'text'
     *   ... ...
     *   logId, token, api-key, server-name, host
     * ]
     */
    private $metas;

    /**
     * Request constructor.
     * @param string $service
     * @param array $params
     * @param array $meta
     */
    public function __construct($service, array $meta = [], array $params = [])
    {
        $this->service = $service;

        // split service name and method name
        list($this->name, $this->method) = $this->parseServiceString($service);

        $this->params = $params;
        $this->metas = $meta;
    }

    public function __destruct()
    {
        $this->destroy();
    }

    public function destroy()
    {
        $this->service = $this->name = $this->method = null;
        $this->params = $this->extra = $this->metas = null;
    }

    /**
     * @param string $service
     * @param string $sep
     * @return array
     */
    public function parseServiceString($service, $sep = '/')
    {
        $name = $method = '';
        $service = trim($service, "$sep ");

        // split service name and method name
        if ($service && strpos($service, $sep)) {
            list($name, $method,) = explode($sep, $service, 3);
        }

        return [$name, $method];
    }

    /**
     * @return string
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * @param string $service
     */
    public function setService(string $service)
    {
        $this->service = $service;
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

    /**
     * @return string
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return array
     */
    public function getMetas(): array
    {
        return $this->metas;
    }

    /**
     * @param array $metas
     */
    public function setMetas(array $metas)
    {
        $this->metas = $metas;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }
}
