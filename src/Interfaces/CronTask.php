<?php

namespace SilverStripe\CronTask\Interfaces;

/**
 * By implementing this interface `sake cron-task` will be able to start in on the
 * expression that you return from getSchedule();
 *
 * @package crontask
 */
interface CronTask
{
    /**
     * Return a string for a CRON expression. If a "falsy" value is returned, the CronTaskController will assume the
     * CronTask is disabled.
     *
     * @return string
     */
    public function getSchedule();

    /**
     * When this script is supposed to run the CronTaskController will execute
     * process().
     *
     * @return void
     */
    public function process();
}
