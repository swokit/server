<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/12
 * Time: 下午8:13
 */

namespace Inhere\Server\Task;

use Cron\CronExpression;

/**
 * Class ScheduleCheck
 * @package Inhere\Server\Task
 */
class ScheduleCheck
{
    /**
     * @param string|callable $schedule
     * @return bool
     */
    public static function isDue($schedule): bool
    {
        if (\is_callable($schedule)) {
            return $schedule();
        }

        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $schedule);

        if ($dateTime !== false) {
            return $dateTime->format('Y-m-d H:i') === date('Y-m-d H:i');
        }

        return CronExpression::factory((string)$schedule)->isDue();
    }
}
