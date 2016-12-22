<?php

namespace SilverStripe\CronTask;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Record status of each cron task execution
 *
 * @property string $LastChecked Date this task was last checked
 * @property string $LastRun Date this task was last run
 * @property string $TaskClass Class of this task
 *
 * @package crontask
 */
class CronTaskStatus extends DataObject
{
    /**
     * {@inheritDoc}
     * @var array
     */
    private static $db = array(
        'TaskClass' => 'Varchar(255)',
        'LastChecked' => 'DBDatetime',
        'LastRun' => 'DBDatetime',
    );

    /**
     * Get the status
     *
     * @param string $class Name of class which implements CronTask
     *
     * @return CronTaskStatus
     */
    public static function get_status($class)
    {
        return static::get()
            ->filter('TaskClass', $class)
            ->first();
    }

    /**
     * Update the status for a given class
     *
     * @param string $class Name of class which implements CronTask
     * @param bool $wasRun Flag indicating that the task was run this request
     *
     * @return CronTaskStatus Status data object
     */
    public static function update_status($class, $wasRun)
    {
        // Get existing object
        $object = static::get()
            ->filter('TaskClass', $class)
            ->first();
        // Create new object if not found
        if (!$object) {
            $object = static::create();
            $object->TaskClass = $class;
        }
        // Update fields
        $now = DBDatetime::now()->getValue();
        if ($wasRun) {
            $object->LastRun = $now;
        }
        $object->LastChecked = $now;
        $object->write();
        return $object;
    }
}
