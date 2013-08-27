<?php

class CronTaskController extends Controller {
	
	private static $allowed_actions = array(
		'index'
	);
	
	public function init() {
		parent::init();
		// Unless called from the command line, all CliControllers need ADMIN privileges
		if(!Director::is_cli() && !Permission::check("ADMIN")) {
			return Security::permissionFailure();
		}
	}

	public function index() {
		foreach(ClassInfo::implementorsOf('CronTask') as $subclass) {
			$task = new $subclass();
			$task->getSchedule();
			echo $subclass . " - ";
			echo $task->getSchedule();
			echo PHP_EOL;
			
		}
	}

	/**
	 * Overload this method to contain the task logic.
	 */
	public function process() {
		
	}
}