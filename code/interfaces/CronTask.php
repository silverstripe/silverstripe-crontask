<?php
/**
 * By implementing this abstract class a /dev/cron will be able to start in on the
 * expression that you return from getSchedule();
 *
 */
abstract class CronTask {
	protected $taskStatus;

	public function __construct(CronTaskStatus $taskStatus = null) {
		if (empty($taskStatus))
			$this->loadCronTaskStatus();
		else
			$this->taskStatus = $taskStatus;
	}

	/**
	 * When this script is supposed to run the CronTaskController will execute
	 * process().
	 *
	 * @return void
	 */
	protected abstract function process();

	/**
	 * Get latest registered task status
	 *
	 * @return string
	 */
	public function getStatus() {
		return $this->taskStatus->Status;
	}

	/**
	 * Get latest registered count of running task instances
	 *
	 * @return mixed
	 */
	public function getRunningInstances() {
		return $this->taskStatus->RunningInstances;
	}

	/**
	 * Return last time task was started
	 *
	 * @return SS_Datetime
	 */
	public function getLastRun() {
		return $this->taskStatus->LastRun;
	}

	/**
	 * Return last time task was checked
	 *
	 * @return SS_Datetime
	 */
	public function getLastChecked() {
		return $this->taskStatus->LastChecked;
	}

	/**
	 * Update task status
	 *
	 * Before writing the changes will refresh data from database
	 *
	 * @param $data
	 * @throws ValidationException
	 * @throws null
	 */
	public function updateTaskStatus($data, $updateInstances = 0) {
		$this->loadCronTaskStatus();
		$this->taskStatus->update($data);
		$this->taskStatus->write();
	}


	/**
	 * Run the task
	 *
	 * Will change the status of the task and will update number of running instances
	 */
	public function run() {
		$this->loadCronTaskStatus();
		$this->taskStatus->update(array(
			'LastRun'			=> SS_Datetime::now()->getValue(),
			'Status'			=> 'Running',
			'RunningInstances'	=> $this->taskStatus->RunningInstances + 1
		));
		$this->taskStatus->write();
		$this->process();
		$this->loadCronTaskStatus();
		if ($this->taskStatus->RunningInstances + 1)
		$this->taskStatus->update(array(
			'Status'			=> $this->taskStatus->RunningInstances - 1 > 0 ? $this->taskStatus->Status : 'On',
			'RunningInstances'	=> $this->taskStatus->RunningInstances - 1
		));
		$this->taskStatus->write();
	}

	/**
	 * Return CronTaskStatus for this task
	 *
	 * @return CronTaskStatus
	 */
	protected function loadCronTaskStatus() {
		$taskStatus = CronTaskStatus::get()->filter('TaskClass', get_class($this))->first();
		$this->taskStatus = empty($taskStatus)
			? $this->registerTask()
			: $taskStatus;

		return $this->taskStatus;
	}

	/**
	 * Register task in DB by creating CronTaskStatus record
	 *
	 * @return CronTaskStatus
	 * @throws ValidationException
	 * @throws null
	 */
	private function registerTask() {
		$cronTaskStatus = new CronTaskStatus(array(
			'TaskClass'			=> get_class($this),
			'ScheduleString'	=> $this->getSchedule(),
			'Status'			=> $this->defaultStatus(),
			'RunningInstances'	=> 0,
			'Priority'			=> $this->defaultPriority(),
		));
		$cronTaskStatus->write();

		return $cronTaskStatus;
	}

	/**
	 * Return a string for a CRON expression
	 *
	 * @return string
	 */
	public function getSchedule() {
		return isset($this->taskStatus)
			? $this->taskStatus->ScheduleString
			: $this->defaultSchedule();
	}

	/**
	 * Return default status of the task
	 *
	 * @return string
	 */
	public function defaultStatus() {
		return 'Off';
	}

	/**
	 * Return default priority of the task
	 *
	 * @return string
	 */
	public function defaultPriority() {
		return 'Normal';
	}

	/**
	 * Return default schedule string
	 *
	 * @return string
	 */
	public function defaultSchedule() {
		return '* * * * *';
	}

	/**
	 * Return if multiple instances can run simultaneous
	 *
	 * @return bool
	 */
	public function allowMultipleInstances() {
		return false;
	}

	/**
	 * Return execution time of a task in seconds
	 *
	 * @return int
	 */
	public function allowedExecutionTime() {
		return 5 * 60;
	}
}
