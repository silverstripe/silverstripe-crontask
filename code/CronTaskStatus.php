<?php

/**
 * Record status of each cron task execution
 *
 * @property string $LastChecked Date this task was last checked
 * @property string $LastRun Date this task was last run
 * @property string $TaskClass Class of this task
 * @property string $ScheduleString schedule string
 * @property string $Status status of the task
 */
class CronTaskStatus extends DataObject {
	
	private static $db = array(
		'TaskClass' => 'Varchar(255)',
		'ScheduleString' => 'Varchar(255)',
		'Status' => "Enum('On,Off,Running,Error','On')",
		'LastChecked' => 'SS_Datetime',
		'LastRun' => 'SS_Datetime'
	);

	public static $summary_fields = array(
		'TaskClass',
		'Status',
		'ScheduleString',
		'LastChecked',
		'LastRun'
	);

	public static $searchable_fields = array(
		'TaskClass',
		'Status'
	);

	/**
	 * Get the status
	 *
	 * @param string $class Name of class which implements CronTask
	 * @return CronTaskStatus
	 */
	public static function get_status($class) {
		$object = static::get()
			->filter('TaskClass', $class)
			->first();

		// Create new object if one does not exists
		if (!$object) {
			$object = static::register_task($class);
		}

		return $object;
	}

	/**
	 * Update the status for a given class
	 *
	 * @param string $class Name of class which implements CronTask
	 * @param bool $wasRun Flag indicating that the task was run this request
	 * @param string $status Flag indicating new status of the task
	 * @return CronTaskStatus Status data object
	 */
	public static function update_status($class, $wasRun, $status = null) {
		// Get existing object
		$object = static::get()
			->filter('TaskClass', $class)
			->first();
		// Create new object if not found
		if(!$object) {
			$object = $object = static::register_task($class);
		}
		// Update fields
		$now = SS_Datetime::now()->getValue();
		if($wasRun) $object->LastRun = $now;
		if($status) $object->Status = $status;
		$object->LastChecked = $now;
		$object->write();
		return $object;
	}

	/**
	 * Register new task
	 *
	 * By default all new tasks are in status 'On' and
	 * will use default schedule string
	 *
	 * @param string $class
	 * @return static
	 */
	public static function register_task($class) {
		$object = static::create();
		$inst = singleton($class);
		$object->update(array(
			'TaskClass' => $class,
			'ScheduleString' => $inst->getSchedule(),
			'Status' => 'On'
		));

		return $object;
	}

	/**
	 * Overwrite canEdit method so only tasks extending CronTaskEditable could be edited
	 *
	 * @param null|Member $member
	 * @return bool
	 */
	public function canEdit($member = null) {
		$inst = singleton($this->TaskClass);
		if($inst instanceof CronTaskEditable) {
			return $inst->canEdit($member);
		} else {
			return false;
		}
	}

	/**
	 * Overwrite canDelete method so user can not delete tasks
	 *
	 * @param null $member
	 * @return bool
	 */
	public function canDelete($member = null) {
		return false;
	}
}
