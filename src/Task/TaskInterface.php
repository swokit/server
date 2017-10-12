<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/12
 * Time: 下午7:20
 */

namespace Inhere\Server\Task;

/**
 * Interface TaskInterface
 * @package Inhere\Server\Task
 */
interface TaskInterface
{
    /**
     * @param array $args
     * @return mixed
     */
    public function run(array $args);
}
