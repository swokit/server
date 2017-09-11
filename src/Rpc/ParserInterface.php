<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-08
 * Time: 17:40
 */

namespace Inhere\Server\Rpc;

/**
 * Interface ParserInterface
 * @package Inhere\Server\Rpc
 */
interface ParserInterface
{
    /**
     * @param string $data
     * @return mixed
     */
    public function decode($data);

    /**
     * @param mixed $data
     * @return mixed
     */
    public function validate($data);

    /**
     * @param mixed $data
     * @return string
     */
    public function encode($data);

    /**
     * @return string
     */
    public function getName(): string;
}
