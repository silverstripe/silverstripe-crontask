<?php

class CronTaskController extends Controller {
	
	private static $allowed_actions = array(
		'index'
	);
	
	public function init() {
		parent::init();
		
		// Try load the CronExpression from the default composer vendor dirs
		if(!class_exists('Cron\CronExpression')) {
			$ds = DIRECTORY_SEPARATOR;
			require_once CRONTASK_MODULE_PATH . $ds . 'vendor' . $ds . 'autoload.php';
			if(!class_exists('Cron\CronExpression')) {
				throw new Exception('CronExpression library isn\'t loaded, please see crontask README');
			}
		};
		
		// Unless called from the command line, all CliControllers need ADMIN privileges
		if(!Director::is_cli() && !Permission::check("ADMIN")) {
			return Security::permissionFailure();
		}
	}

	public function index() {
		foreach(ClassInfo::implementorsOf('CronTask') as $subclass) {
			$task = new $subclass();
			$cron = Cron\CronExpression::factory($task->getSchedule());
			echo $subclass;
			if($cron->isDue()) {
				echo ' is executed'.PHP_EOL;
				$task->process();
			} else {
				echo ' will run next time at ';
				echo $cron->getNextRunDate()->format('Y-m-d H:i:s').PHP_EOL;
			}
		}
	}

	/**
	 * Overload this method to contain the task logic.
	 */
	public function process() {
		
	}
}