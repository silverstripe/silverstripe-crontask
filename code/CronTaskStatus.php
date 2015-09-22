<?php

/**
 * Record status of each cron task execution
 *
 * @property string $LastChecked Date this task was last checked
 * @property string $LastRun Date this task was last run
 * @property string $TaskClass Class of this task
 * @property string $ScheduleString string schedule
 * @property enum $Status current status of the task
 * @property integer $RunningInstances current running instances of the task
 * @property enum $Priority priority of the task
 */
class CronTaskStatus extends DataObject {
	
	private static $db = array(
		'TaskClass'			=> 'Varchar(255)',
		'ScheduleString'	=> 'Varchar(255)',
		'LastChecked'		=> 'SS_Datetime',
		'LastRun'			=> 'SS_Datetime',
		'Status'			=> "Enum('On,Off,Running,Error','Off')",
		'RunningInstances'	=> 'Int',
		'Priority'			=> "Enum('High,Normal,Low','Normal')",
	);

	/**
	 * Get the status
	 *
	 * @param string $class Name of class which implements CronTask
	 * @return CronTaskStatus
	 */
	public static function get_status($class) {
		return static::get()
			->filter('TaskClass', $class)
			->first();
	}

	/**
	 * Update the status for a given class
	 *
	 * @param string $class Name of class which implements CronTask
	 * @param bool $wasRun Flag indicating that the task was run this request
	 * @param string $status if passed will update the status
	 * @return CronTaskStatus Status data object
	 */
	public static function update_status($class, $wasRun, $status = null) {
		// Get existing object
		$object = static::get()
			->filter('TaskClass', $class)
			->first();
		// Create new object if not found
		if(!$object) {
			$object = static::create();
			$object->TaskClass = $class;
		}
		// Update fields
		$now = SS_Datetime::now()->getValue();
		if($wasRun) $object->LastRun = $now;
		if($status) $object->Status = $status;
		$object->LastChecked = $now;
		$object->write();
		return $object;
	}

	public function requireDefaultRecords() {
		// Register each task
		$tasks = ClassInfo::subClassesFor('CronTask');

		foreach($tasks as $taskName)
			if ($taskName == 'CronTask')
				continue;

			//Creating the task will register it if it's not registered
			$task = new $taskName();
	}
}
