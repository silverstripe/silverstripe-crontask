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

	public function requireDefaultRecords() {
		// Register each task
		$tasks = ClassInfo::subClassesFor('CronTask');

		foreach($tasks as $taskName) {
			if ($taskName == 'CronTask')
				continue;

			//Creating the task will register it if it's not registered
			$task = new $taskName();
		}
	}
}
