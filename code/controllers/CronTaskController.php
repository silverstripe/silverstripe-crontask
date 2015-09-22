<?php
/**
 * This is the controller that finds, checks and process all crontasks
 * 
 * The default route to this controller is 'dev/cron'
 *
 */
class CronTaskController extends Controller {

	/**
	 * If this controller is in quiet mode
	 *
	 * @var bool
	 */
	protected $quiet = false;

	/**
	 * Tell the controller how noisy it may be
	 *
	 * @param bool $quiet If set to true this controller will not emit debug noise
	 */
	public function setQuiet($quiet) {
		$this->quiet = true;
	}

	/**
	 * Checks for cli or admin permissions and include the library
	 *
	 * @throws Exception
	 */
	public function init() {
		parent::init();

		// Try load the CronExpression from the default composer vendor dirs
		if(!class_exists('Cron\CronExpression')) {
			$ds = DIRECTORY_SEPARATOR;
			require_once CRONTASK_MODULE_PATH . $ds . 'vendor' . $ds . 'autoload.php';
			if(!class_exists('Cron\CronExpression')) {
				throw new Exception('CronExpression library isn\'t loaded, please see crontask README');
			}
		}

		// Unless called from the command line, we need ADMIN privileges
		if(!Director::is_cli() && !Permission::check("ADMIN")) {
			Security::permissionFailure();
		}
	}

	/**
	 * Determine if a task should be run
	 *
	 * @param CronTask $task
	 * @param \Cron\CronExpression $cron
	 * @return bool
	 */
	public function isTaskDue(CronTask $task, \Cron\CronExpression $cron) {
		// If the cron is due immediately, then run it
		$now = new DateTime(SS_Datetime::now()->getValue());
		if($cron->isDue($now)) {
			if(!$task->getLastRun()) return true;
			// In case this process is invoked twice in one minute, supress subsequent executions
			$lastRun = new DateTime($task->getLastRun());
			return $lastRun->format('Y-m-d H:i') != $now->format('Y-m-d H:i');
		}

		// If this is the first time this task is ever checked, no way to detect postponed execution
		if(!$task->getLastChecked()) return false;

		// Determine if we have passed the last expected run time
		$nextExpectedDate = $cron->getNextRunDate($task->getLastChecked());
		return $nextExpectedDate <= $now;
	}

	/**
	 * Default controller action
	 *
	 * @param SS_HTTPRequest $request
	 */
	public function index(SS_HTTPRequest $request) {
		$taskStatuses = CronTaskStatus::get()->sort('Priority', 'ASC');
		if(empty($taskStatuses)) {
			$this->output("There are no implementators of CronTask to run");
			return;
		}
		foreach($taskStatuses as $taskStatus) {
			$taskName = $taskStatus->TaskClass;
			$task = new $taskName();
			$this->runTask($task);
		}
	}

	/**
	 * Checks and runs a single CronTask
	 *
	 * @param CronTask $task
	 */
	public function runTask(CronTask $task) {
		$this->checkForErrors($task);

		if ($task->getStatus() == 'Off' || $task->getStatus() == 'Error') {
			$this->output(get_class($task).' is with status '.$task->getStatus().' and is skipped.');
			return false;
		}

		if (!$task->allowMultipleInstances() && $task->getStatus() == 'Running') {
			$this->output(get_class($task).' is still running.');
			return false;
		}

		$cron = Cron\CronExpression::factory($task->getSchedule());
		$isDue = $this->isTaskDue($task, $cron);
		// Update status of this task prior to execution in case of interruption
		$task->updateTaskStatus(array(
			'LastChecked'	=> SS_Datetime::now()->getValue(),
		));
		if($isDue) {
			$this->output(get_class($task).' will start now.');
			$task->run();
		} else {
			$this->output(get_class($task).' will run at '.$cron->getNextRunDate()->format('Y-m-d H:i:s').'.');
		}
	}

	/**
	 * Output a message to the browser or CLI
	 *
	 * @param string $message
	 */
	public function output($message) {
		if($this->quiet) return;
		if(Director::is_cli()) {
			echo $message.PHP_EOL;
		} else {
			echo Convert::raw2xml($message).'<br />'.PHP_EOL;
		}
	}

	/**
	 * Check task for errors
	 *
	 * For now only execution time is checked against CrontTask->allowedExecutionTime()
	 *
	 * @param CronTask $task
	 */
	private function checkForErrors(CronTask $task) {
		//Checking only task in status 'Running'
		if ($task->getStatus() != 'Running')
			return;

		if (strtotime(SS_Datetime::now()->getValue()) - strtotime($task->getLastRun()) < $task->allowedExecutionTime())
			return;

		$task->updateTaskStatus(array(
			'Status'		=> 'Error',
			'LastChecked'	=> SS_Datetime::now()->getValue(),
		));

		$this->output(get_class($task).' running more than ' . $task->allowedExecutionTime() . ' seconds and it\'s status was updated to "Error".');
	}
}
