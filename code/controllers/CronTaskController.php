<?php

use Cron\CronExpression;

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
		$this->quiet = $quiet;
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
	 * @param CronExpression $cron
	 */
	public function isTaskDue(CronTask $task, CronExpression $cron) {
		// Get last run status
		$status = CronTaskStatus::get_status(get_class($task));
		
		// If the cron is due immediately, then run it
		$now = new DateTime(SS_Datetime::now()->getValue());
		if($cron->isDue($now)) {
			if(empty($status->LastRun)) return true;
			// In case this process is invoked twice in one minute, supress subsequent executions
			$lastRun = new DateTime($status->LastRun);
			return $lastRun->format('Y-m-d H:i') != $now->format('Y-m-d H:i');
		}

		// If this is the first time this task is ever checked, no way to detect postponed execution
		if(empty($status->LastChecked)) return false;

		// Determine if we have passed the last expected run time
		$nextExpectedDate = $cron->getNextRunDate($status->LastChecked);
		return $nextExpectedDate <= $now;
	}

	/**
	 * Default controller action
	 *
	 * @param SS_HTTPRequest $request
	 */
	public function index(SS_HTTPRequest $request) {
		// Check each task
		$tasks = ClassInfo::implementorsOf('CronTask');
		if(empty($tasks)) {
			$this->output("There are no implementators of CronTask to run");
			return;
		}
		foreach($tasks as $subclass) {
			$task = new $subclass();
			$this->runTask($task);
		}
	}

	/**
	 * Checks and runs a single CronTask
	 *
	 * @param CronTask $task
	 */
	public function runTask(CronTask $task) {
		$status = CronTaskStatus::get_status(get_class($task));

		if (!$status->lock()) {
			$this->output(get_class($task).' is currently checking by other instance and will be skipped.');
			CronTaskStatus::update_status(get_class($task), false);
			return;
		}

		if ($this->isLongRunning($status)) {
			$this->output(get_class($task).' running longer than expected. Setting status to Error');
			SS_Log::log(get_class($task).' running longer than expected. Setting status to Error', SS_Log::WARN);
			CronTaskStatus::update_status(get_class($task), false. 'Error');
			return;
		}

		if (!$status->isEnabled()) {
			$this->output(get_class($task).' is disabled and will be skipped.');
			CronTaskStatus::update_status(get_class($task), false);
			return;
		}

		if (!$status->isChecking()) {
			$this->output(get_class($task).' is in status '.$status->Status . ' and will be skipped.');
			CronTaskStatus::update_status(get_class($task), false);
			return;
		}

		$cron = CronExpression::factory($status->ScheduleString);
		$isDue = $this->isTaskDue($task, $cron);
		// Update status of this task prior to execution in case of interruption
		CronTaskStatus::update_status(get_class($task), $isDue, $isDue ? 'Running' : null);
		if($isDue) {
			$this->output(get_class($task).' will start now.');
			$task->process();
			$status = CronTaskStatus::get_status(get_class($task));
		} else {
			$this->output(get_class($task).' will run at '.$cron->getNextRunDate()->format('Y-m-d H:i:s').'.');
		}

		$status->unlock();
	}

	/**
	 * Check is a task running longer than defined maximum execution time
	 *
	 * Maximum execution time is defined in the config.yml for each task
	 *
	 * @param CronTaskStatus $status
	 * @return bool
	 */
	private function isLongRunning($status) {
		if ($status->isRunning()) {
			return false;
		}

		$minutes = Config::inst()->get($status->TaskClass, 'MaxExecutionTime');
		if (is_null($minutes) || $minutes <= 0) {
			return false;
		}

		return (SS_Datetime::now()->Format('U') - $status->dbObject('LastRun')->Format('U')) < $minutes * 60;
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
}
