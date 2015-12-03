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
 * @property boolean $Enabled is this task enabled or not by default true
 * @property boolean $IsLocked is set to true when CronTaskController start to check and process this task
 */
class CronTaskStatus extends DataObject {
	
	private static $db = array(
		'TaskClass' => 'Varchar(255)',
		'Enabled' => 'Boolean',
		'ScheduleString' => 'Varchar(255)',
		'Status' => "Enum('Running,Checking,Error,Pending','Pending')",
		'LastChecked' => 'SS_Datetime',
		'LastRun' => 'SS_Datetime',
	);

	private static $defaults = array(
		'Status' => 'Pending',
		'Enabled' => true,
	);

	private static $summary_fields = array(
		'TaskClass',
		'Enabled',
		'Status',
		'ScheduleString',
		'LastRun',
		'NextRun',
	);

	private static $searchable_fields = array(
		'TaskClass',
		'Enabled',
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
	 * By default all new tasks are in status 'Pending' and
	 * enabled. Default schedule string will be used.
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
			'Status' => Config::inst()->get('CronTaskStatus', 'defaultStatus')
				? Config::inst()->get('CronTaskStatus', 'defaultStatus')
				: 'Pending',
			'Enabled' => Config::inst()->get('CronTaskStatus', 'defaultIsEnabled')
				? Config::inst()->get('CronTaskStatus', 'defaultIsEnabled')
				: true,
		));

		return $object;
	}

	/**
	 * @return string Date time string of next run for this task
	 */
	public function getNextRun() {
		if (!$this->isEnabled()) {
			return '';
		}

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
	 * @return bool Is the task pending
	 */
	public function isPending() {
		return $this->Status == 'Pending';
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
		return $this->Enabled;
	}

	/**
	 * Configure the fields in the form
	 *
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$classField = $fields->dataFieldByName('TaskClass');
		$statusField = $fields->dataFieldByName('Status');
		$fields->replaceField('TaskClass', $classField->performReadonlyTransformation());
		$fields->replaceField('Status', $statusField->performReadonlyTransformation());

		//If task status is running/checking/errored we can force it to "Pending"
		if ($this->isRunning() || $this->isChecking() || $this->isErrored()) {
			$fields->insertAfter(new CheckboxField('ForceStatusToOff', 'Force status to "Pending"'), 'Status');
		}

		$fields->removeByName('LastChecked');
		$fields->removeByName('LastRun');

		return $fields;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if ($this->ForceStatusToOff) {
			$this->Status = 'Pending';
			$this->IsLocked = false;
			$this->ForceStatusToOff = false;
		}
	}

	/**
	 * Locking task
	 *
	 * No other instance of CronTaskController can
	 * check or process locked task
	 * Status will be set to 'Checking'
	 *
	 * If task is currently locked will return false
	 *
	 * @throws ValidationException
	 * @return boolean
	 */
	public function lock() {
		if ($this->IsLocked) {
			return false;
		}

		$this->IsLocked = true;
		$this->Status = 'Checking';
		$this->write();

		return true;
	}

	/**
	 * Unlock task
	 *
	 * Unlock task so other instances of CronTaskController can
	 * check and process it.
	 * Status will be set back to 'Pending'.
	 *
	 * @throws ValidationException
	 * @throws null
	 */
	public function unlock() {
		$this->IsLocked = false;
		$this->Status = 'Pending';
		$this->write();
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
