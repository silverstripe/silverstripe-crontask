<?php

namespace SilverStripe\CronTask\Tests\CronTaskTest;

use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\TestOnly;

/**
 * @package crontask
 */
class TestCron implements TestOnly, CronTask
{
    public static $times_run = 0;

    public function getSchedule()
    {
        // Use hourly schedule
        return '0 * * * *';
    }

    public function process()
    {
        ++self::$times_run;
    }
}
