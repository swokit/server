<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/12
 * Time: 下午7:53
 */

namespace Inhere\Server\Task;

/**
 * Class CronExpression 解析 CronTab格式
 * @package Inhere\Server\Task
 * Schedule parts must map to:
 * minute [0-59], hour [0-23], day of month, month [1-12|JAN-DEC], day of week [1-7|MON-SUN], and an optional year.
 */
class CronExpression
{
    public static $error;

    /**
     *  解析cronTab的定时格式，linux只支持到分钟/，这个类支持到秒
     * @param string $expression :
     *
     *      0     1    2    3    4    5
     *      *     *    *    *    *    *
     *      -     -    -    -    -    -
     *      |     |    |    |    |    |
     *      |     |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     *      |     |    |    |    +----- month (1 - 12)
     *      |     |    |    +------- day of month (1 - 31)
     *      |     |    +--------- hour (0 - 23)
     *      |     +----------- min (0 - 59)
     *      +------------- sec (0-59)
     * @example
     *
     * // 15minutes between 6pm and 6am
     * '0,15,30,45 18-06 * * *'
     *
     * // 每小时的第3和第15分钟执行
     * '3,15 * * * *'
     *
     * @param int $startTime timestamp [default=current timestamp]
     * @return int unix timestamp - 下一分钟内执行是否需要执行任务，如果需要，则把需要在那几秒执行返回
     * @throws \InvalidArgumentException 错误信息
     */
    public static function parse($expression, $startTime = null)
    {
        $expression = str_replace('  ', ' ', trim($expression));

        if (
            !preg_match(
            '/^((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)$/i',
                $expression
            ) &&
            !preg_match(
                '/^((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)\s+((\*(\/\d+)?)|[\d\-\,\/]+)$/i',
                $expression
            )
        ) {
            self::$error = "Invalid cron string: . $expression";
            return false;
        }

        if ($startTime && !is_numeric($startTime)) {
            self::$error = 'startTime must be a valid unix timestamp';
            return false;
        }

        if (!$startTime) {
            $startTime = time();
        }

        $cron = preg_split("/[\s]+/i", trim($expression));
        $len = \count($cron);

        if ($len === 6) {
            $date = [
                'sec' => empty($cron[0]) ? [1 => 1] : self::parseCronNode($cron[0], 0, 59),
                'min' => self::parseCronNode($cron[1], 0, 59),
                'hour' => self::parseCronNode($cron[2], 0, 23),
                'day' => self::parseCronNode($cron[3], 1, 31),
                'month' => self::parseCronNode($cron[4], 1, 12),
                'week' => self::parseCronNode($cron[5], 0, 6),
            ];
        } elseif ($len === 5) {
            $date = [
                'sec' => [1 => 1],
                'min' => self::parseCronNode($cron[0], 0, 59),
                'hour' => self::parseCronNode($cron[1], 0, 23),
                'day' => self::parseCronNode($cron[2], 1, 31),
                'month' => self::parseCronNode($cron[3], 1, 12),
                'week' => self::parseCronNode($cron[4], 0, 6),
            ];
        } else {
            return false;
        }

        // string(13) "07 16 12 4 10"
        // $dateStr = date('i H d w m', $startTime);
        $dateStr = date('i G j w n', $startTime);

        if (
            \in_array((int)date('i', $startTime), $date['min'], true) &&
            \in_array((int)date('G', $startTime), $date['hours'], true) &&
            \in_array((int)date('j', $startTime), $date['day'], true) &&
            \in_array((int)date('w', $startTime), $date['week'], true) &&
            \in_array((int)date('n', $startTime), $date['month'], true)
        ) {
            return $date['sec'];
        }

        return null;
    }

    /**
     * 解析单个配置的含义
     * @param $s
     * @param $min
     * @param $max
     * @return array
     */
    protected static function parseCronNode($s, $min, $max)
    {
        $result = [];
        $v1 = explode(',', $s);

        foreach ($v1 as $v2) {
            $v3 = explode('/', $v2);
            $v4 = explode('-', $v3[0]);
            $step = empty($v3[1]) ? 1 : (int)$v3[1];

//            $_min = count($v4) === 2 ? (int)$v4[0] : ($v3[0] === '*' ? $min : (int)$v3[0]);
//            $_max = count($v4) === 2 ? (int)$v4[1] : ($v3[0] === '*' ? $max : (int)$v3[0]);

            if (\count($v4) === 2) {
                $_min = (int)$v4[0];
                $_max = (int)$v4[1];
            } else {
                $_min = $v3[0] === '*' ? $min : (int)$v3[0];
                $_max = $v3[0] === '*' ? $max : (int)$v3[0];
            }

            for ($i = $_min; $i <= $_max; $i += $step) {
                if ($i < $min) {
                    $result[$min] = $min;
                } elseif ($i > $max) {
                    $result[$max] = $max;
                } else {
                    $result[$i] = $i;
                }
            }
        }

        ksort($result);

        return $result;
    }
}
