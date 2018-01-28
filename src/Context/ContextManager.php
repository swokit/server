<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-08-31
 * Time: 15:17
 */

namespace Inhere\Server\Context;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ContextManager
 * @package Inhere\Server\Context
 */
class ContextManager implements ContextManagerInterface
{
    /**
     * @var ContextInterface[]
     */
    private $contextList = [];

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return isset($this->contextList[$id]);
    }

    /**
     * @param ContextInterface $context
     */
    public function add(ContextInterface $context)
    {
        $this->contextList[$context->getId()] = $context;
    }

    /**
     * @param string|int $id
     * @return ContextInterface|null
     */
    public function get($id = null)
    {
        if (null === $id) {
            $id = $this->getDefaultId();
        }

        return $this->contextList[$id] ?? null;
    }

    /**
     * @param int|string|ContextInterface $id
     * @return ContextInterface|null
     */
    public function del($id = null)
    {
        if (null === $id) {
            $id = $this->getDefaultId();
        }

        if (\is_object($id) && $id instanceof ContextInterface) {
            $id = $id->getId();
        }

        if ($ctx = $this->get($id)) {
            unset($this->contextList[$id]);
        }

        return $ctx;
    }

    /**
     * clear
     */
    public function clear()
    {
        $this->contextList = [];
    }

    /**
     * @return int
     */
    public function count():int
    {
        return \count($this->contextList);
    }

    /**
     * @param null|int|string $id
     * @param bool $thrError
     * @return null|ServerRequestInterface
     */
    public function getRequest($id = null, $thrError = true)
    {
        if (null === $id) {
            $id = $this->getDefaultId();
        }

        if ($ctx = $this->get($id)) {
            return $ctx->getRequest();
        }

        if ($thrError) {
            throw new \RuntimeException("the request context is not exists for CoId:[$id]");
        }

        return null;
    }

    /**
     * @param null|int|string $id
     * @param bool $thrError
     * @return null|ResponseInterface
     */
    public function getResponse($id = null, $thrError = true)
    {
        if (null === $id) {
            $id = $this->getDefaultId();
        }

        if ($ctx = $this->get($id)) {
            return $ctx->getResponse();
        }

        if ($thrError) {
            throw new \RuntimeException("the request context is not exists for CoId:[$id]");
        }

        return null;
    }

    /**
     * @return int|string
     */
    protected function getDefaultId()
    {
        return 0;
    }

    /**
     * @return array
     */
    public function getContextList(): array
    {
        return $this->contextList;
    }

    /**
     * @param array $contextList
     */
    public function setContextList(array $contextList)
    {
        $this->contextList = $contextList;
    }

    /**
     * @return array
     */
    public function getIds()
    {
        return array_keys($this->contextList);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->contextList);
    }
}
