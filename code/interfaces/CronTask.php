<?php
/**
 * By implementing this interface a /dev/cron will be able to start in on the
 * expression that you return frmo getSchedule();
 *
 */
abstract class CronTask extends Object {
	/**
	 * @var CronTaskStatus
	 */
	private $statusObj;

	/**
	 * Contains all registered messages
	 *
	 * @var array
	 */
	private $messages = array();

	/**
	 * get the status object for the task
	 *
	 * @return CronTaskStatus
	 */
	public function getStatusObject() {
		if (!$this->statusObj) {
			$this->setStatusObject(CronTaskStatus::get_status(get_class($this)));
		}
		return $this->statusObj;
	}

	/**
	 * Setter for status object
	 *
	 * @param $statusObj CronTaskStatus
	 * @return $this
	 */
	public function setStatusObject($statusObj) {
		$this->statusObj = $statusObj;
		//set up any defaults if it doesn't exist
		return $this;
	}

	/**
	 * A helper to set the status on the CronTaskStatus object
	 *
	 * @param $status string
	 * @param bool $wasRun Flag indicating that the task was run this request default: false
	 * @return $this
	 */
	public function setStatus($status, $wasRun = false) {
		CronTaskStatus::update_status(get_class($this), $wasRun, $status);

		return $this;
	}

	/**
	 * A helper to get the status on the CronTaskStatus object
	 *
	 * @return string
	 */
	public function getStatus() {
		return $this->getStatusObject()->Status;
	}

	/**
	 * Return a string for a CRON expression
	 *
	 * @return string
	 */
	abstract public function getSchedule();

	/**
	 * When this script is supposed to run the CronTaskController will execute
	 * process().
	 *
	 * @return mixed
	 */
	abstract protected function process();

	/**
	 * Check if user can edit this task
	 *
	 * @param null|Member $member
	 * @return mixed
	 */
	public function canEdit($member = null) {
		return false;
	}

	/**
	 * Run the task
	 *
	 * If task is executed will return true,
	 * otherwise will return false.
	 *
	 * @return boolean
	 */
	public function doProcess() {
		if (in_array(false, $this->invokeWithExtensions('onBeforeProcess'))) {
			return false;
		}

		$this->setStatus('Running', true);
		$result = $this->process();

		if ($this->invokeWithExtensions('onAfterProcess', $result)) {
			$this->setStatus('Pending');
		}

		return true;
	}

	/**
	 * Executed before running the task
	 *
	 * Used to prepare and check that the task can be executed.
	 * For example check network, needed disk space and so on.
	 * If for some reason task can not be executed set status
	 * and return false.
	 *
	 * @return boolean
	 */
	protected function onBeforeProcess() {
		if (!$this->getStatusObject()->isEnabled()) {
			$this->addMessage('Current status of "' . get_class($this) . '" is "' . $this->getStatusObject()->Status . '" and will be skipped.', SS_Log::NOTICE);
			return false;
		}

		if ($this->isLongRunning()) {
			$this->addMessage(get_class($this) . " is running longer than expected. Setting it's status to Error.", SS_Log::WARN);

			$this->setStatus('Error');
			return false;
		}

		return true;
	}

	/**
	 * Executed after running the task
	 *
	 * Used to cleanup after task execution if needed and
	 * to confirm task was executed successfully.
	 * If task was not executed successfully then update the status
	 * and return false. This will prevent the status to be changed
	 * to "Pending"
	 *
	 * @param mixed $result
	 * @return boolean
	 */
	protected function onAfterProcess($result) {
		return true;
	}

	/**
	 * Check is a task running longer than defined maximum execution time
	 *
	 * Maximum execution time is defined in the config.yml for each task
	 *
	 * @return bool
	 */
	protected function isLongRunning() {
		if ($this->getStatusObject()->isRunning()) {
			return false;
		}

		$minutes = Config::inst()->get($this->getStatusObject()->TaskClass, 'MaxExecutionTime');
		if (is_null($minutes) || $minutes <= 0) {
			return false;
		}

		return (SS_Datetime::now()->Format('U') - $this->getStatusObject()->dbObject('LastRun')->Format('U')) < $minutes * 60;
	}

	/**
	 * Return all registered messages
	 *
	 * @return array
	 */
	public function getMessages() {
		return $this->messages;
	}

	/**
	 * Add a message
	 *
	 * @param string $message
	 * @param int $log_level - if set will log the message with the given log level
	 */
	protected function addMessage($message, $log_level = null) {
		$this->messages[] = $message;

		if (!is_null($log_level)) {
			SS_Log::log('Current status of "' . get_class($this) . '" is "' . $this->getStatusObject()->Status . '" and will be skipped.', $log_level);
		}
	}
}
