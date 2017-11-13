<?php

/**
 * This is the controller that finds, checks and process all crontasks
 *
 * The default route to this controller is 'dev/cron'
 *
 */
class CronTaskController extends Controller
{

    /**
     * Tell the controller how noisy it may be
     *
     * @var int A number from 0 to 2
     */
    protected $verbosity = 1;

    /**
     * Tell the controller how noisy it may be
     * @deprecated Use setVerbosity instead
     * @param bool $quiet If set to true this controller will not emit debug noise
     */
    public function setQuiet($quiet)
    {
        $this->setVerbosity($quiet ? 0 : 1);

        $this->quiet = (bool) $quiet;
    }

    /**
     * Tell the controller how noisy it may be
     *
     * @param int $verbosity An integer from 0 to 2. 0 = no output, 1 = normal, 2 = debug
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = (int) $verbosity;
    }

    /**
     * Checks for cli or admin permissions and include the library
     *
     * @throws Exception
     */
    public function init()
    {
        parent::init();

        // Try load the CronExpression from the default composer vendor dirs
        if (!class_exists('Cron\CronExpression')) {
            $ds = DIRECTORY_SEPARATOR;
            require_once CRONTASK_MODULE_PATH . $ds . 'vendor' . $ds . 'autoload.php';
            if (!class_exists('Cron\CronExpression')) {
                throw new Exception('CronExpression library isn\'t loaded, please see crontask README');
            }
        }

        // Unless called from the command line, we need ADMIN privileges
        if (!Director::is_cli() && !Permission::check("ADMIN")) {
            Security::permissionFailure();
        }

        // set quiet flag based on CLI parameter
        if ($this->getRequest()->getVar('quiet')) {
            $this->setVerbosity(0);
        }
        if ($this->getRequest()->getVar('debug')) {
            $this->setVerbosity(2);
        }

    }

    /**
     * Determine if a task should be run
     *
     * @param CronTask $task
     * @param \Cron\CronExpression $cron
     */
    public function isTaskDue(CronTask $task, \Cron\CronExpression $cron)
    {
        // Get last run status
        $status = CronTaskStatus::get_status(get_class($task));

        // If the cron is due immediately, then run it
        $now = new DateTime(SS_Datetime::now()->getValue());
        if ($cron->isDue($now)) {
            if (empty($status) || empty($status->LastRun)) {
                return true;
            }
            // In case this process is invoked twice in one minute, supress subsequent executions
            $lastRun = new DateTime($status->LastRun);
            return $lastRun->format('Y-m-d H:i') != $now->format('Y-m-d H:i');
        }

        // If this is the first time this task is ever checked, no way to detect postponed execution
        if (empty($status) || empty($status->LastChecked)) {
            return false;
        }

        // Determine if we have passed the last expected run time
        $nextExpectedDate = $cron->getNextRunDate($status->LastChecked);
        return $nextExpectedDate <= $now;
    }

    /**
     * Default controller action
     *
     * @param SS_HTTPRequest $request
     */
    public function index(SS_HTTPRequest $request)
    {
        // Show more debug info with ?debug=1
        $isDebug = (bool)$request->getVar('debug');

        // Check each task
        $tasks = ClassInfo::implementorsOf('CronTask');
        if (empty($tasks)) {
            $this->output("There are no implementators of CronTask to run", 2);
            return;
        }
        foreach ($tasks as $subclass) {
            $task = Injector::inst()->create($subclass);
            $this->runTask($task, $isDebug);
        }
    }

    /**
     * Checks and runs a single CronTask
     *
     * @param CronTask $task
     */
    public function runTask(CronTask $task)
    {
        $cron = Cron\CronExpression::factory($task->getSchedule());
        $isDue = $this->isTaskDue($task, $cron);
        // Update status of this task prior to execution in case of interruption
        CronTaskStatus::update_status(get_class($task), $isDue);
        if ($isDue) {
            $this->output(get_class($task) . ' will start now.');
            $task->process();
        } else {
            $this->output(get_class($task) . ' will run at ' . $cron->getNextRunDate()->format('Y-m-d H:i:s') . '.', 2);
        }
    }

    /**
     * Output a message to the browser or CLI
     *
     * @param string $message
     */
    public function output($message, $minVerbosity = 1)
    {
        if ($this->verbosity < $minVerbosity) {
            return;
        }
        $timestamp = SS_Datetime::now()->Format('Y-m-d H:i:s');
        if (Director::is_cli()) {
            echo $timestamp . ' - ' . $message . PHP_EOL;
        } else {
            echo Convert::raw2xml($timestamp . ' - ' . $message) . '<br />' . PHP_EOL;
        }
    }
}
