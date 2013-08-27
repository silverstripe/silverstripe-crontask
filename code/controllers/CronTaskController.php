<?php
/**
 * This is the controller that finds, checks and process all crontasks
 * 
 * The default route to this controller is 'dev/cron'
 *
 */
class CronTaskController extends Controller {

	/**
	 * Only allow access to the index method
	 *
	 * @var array
	 */
	private static $allowed_actions = array(
		'index'
	);

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
		};

		// Unless called from the command line, we need ADMIN privileges
		if(!Director::is_cli() && !Permission::check("ADMIN")) {
			Security::permissionFailure();
		}
	}

	/**
	 * Default controller action
	 *
	 * @param object  $request
	 */
	public function index(SS_HTTPRequest $request) {
		foreach(ClassInfo::implementorsOf('CronTask') as $subclass) {
			$task = new $subclass();
			$cron = Cron\CronExpression::factory($task->getSchedule());
			if($cron->isDue()) {
				$this->output($subclass.' will start now.');
				$task->process();
				continue;
			}
			$this->output($subclass.' will run at '.$cron->getNextRunDate()->format('Y-m-d H:i:s').'.');
		}
	}

	/**
	 * Output a message to the browser or CLI
	 *
	 * @param string  $message
	 */
	public function output($message) {
		if(PHP_SAPI === 'cli') {
			echo $message.PHP_EOL;
		} else {
			echo $message.'<br>'.PHP_EOL;
		}
	}
}
