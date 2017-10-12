<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/12
 * Time: 下午7:18
 */

namespace Inhere\Server\Task;

/**
 * Class TimerTask
 * @package Inhere\Server\Task
 */
abstract class TimerTask implements TaskInterface
{
    public function beforeRun($args)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function run(array $args)
    {
        $this->beforeRun($args);
        $this->afterRun($args);
    }

    public function afterRun($args)
    {
    }
}
