<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-08-31
 * Time: 15:19
 */

namespace Inhere\Server\Context;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface ContextInterface
 * @package Inhere\Server\Context
 *
 * @property string $id The request context unique ID
 */
interface ContextInterface
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @param  string $id
     */
    public function setId($id);

    /**
     * @return string
     */
    public function getKey();

    /**
     * destroy something ...
     */
    public function destroy();

    /**
     * @return ServerRequestInterface
     */
    public function getRequest();

    /**
     * @return ResponseInterface
     */
    public function getResponse();

    /**
     * @return array
     */
    public function getArgs(): array;

    /**
     * @param array $args
     */
    public function setArgs(array $args);
}
