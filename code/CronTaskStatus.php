<?php

use Cron\CronExpression;

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
		'LastRun' => 'SS_Datetime',
	);

	private static $summary_fields = array(
		'TaskClass',
		'Status',
		'ScheduleString',
		'LastRun',
		'NextRun',
	);

	private static $searchable_fields = array(
		'TaskClass',
		'Status',
	);

	private static $casting = array(
		'NextRun' => 'SS_Datetime',
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
		$object = self::get_status($class);
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
	 * @return string Date time string of next run for this task
	 */
	public function getNextRun() {
		$cron = CronExpression::factory($this->ScheduleString);
		return DBField::create_field('SS_Datetime', $cron->getNextRunDate()->Format('U'))->getValue();
	}

	/**
	 * @return bool Is the task running
	 */
	public function isRunning() {
		return $this->Status == 'Running';
	}

	/**
	 * @return bool Has the task errored
	 */
	public function isErrored() {
		return $this->Status == 'Error';
	}

	/**
	 * @return bool Is the task enabled
	 */
	public function isEnabled() {
		return $this->Status == 'On' || $this->isRunning();
	}

	/**
	 * Configure the fields in the form
	 *
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$classField = $fields->dataFieldByName('TaskClass');
		$fields->replaceField('TaskClass', $classField->performReadonlyTransformation());
		$status = $this->dbObject('Status')->enumValues();
		unset($status['Running'], $status['Error']);

		$fields->dataFieldByName('Status')->setSource($status);
		$fields->removeByName('LastChecked');
		$fields->removeByName('LastRun');

		return $fields;
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
			if (!$this->isRunning()) {
				return $this->canEdit($member);
			}
		}
		return false;
	}

	/**
	 * Overwrite canDelete method so user can not delete tasks
	 *
	 * @param null|Member $member
	 * @return bool
	 */
	public function canDelete($member = null) {
		return false;
	}

	/**
	 * Overwrite canDelete method so user can not create tasks
	 *
	 * All new tasks will be registered by CronTaskController
	 *
	 * @param null|Member $member
	 * @return bool
	 */
	public function canCreate($member = null) {
		return false;
	}

	/**
	 * Validating the input
	 *
	 * ScheduleString will be validated before write
	 *
	 * @return ValidationResult
	 */
	public function validate() {
		$result = parent::validate();
		try {
			CronExpression::factory($this->ScheduleString);
		} catch (Exception $ex) {
			$result->error($ex->getMessage());
		}

		return $result;
	}
}
