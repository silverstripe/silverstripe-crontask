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
		$tasks = ClassInfo::subclassesFor('CronTask');
		if(empty($tasks)) {
			$this->output("There are no implementations of CronTask to run");
			return;
		}
		foreach($tasks as $subclass) {
			if ($subclass == 'CronTask') {
				continue;
			}

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
		$class_name = get_class($task);

		$status = CronTaskStatus::get_status($class_name);

		$cron = CronExpression::factory($status->ScheduleString);
		$isDue = $this->isTaskDue($task, $cron);
		if($isDue) {
			$this->output($class_name . ' will start now.');
			$task->doProcess();
			foreach($task->getMessages() as $message)
				$this->output($class_name . ': ' . $message);
		} else {
			$this->output($class_name . ' will run at '.$cron->getNextRunDate()->format('Y-m-d H:i:s').'.');
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
}
